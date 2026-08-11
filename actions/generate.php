<?php
/**
 * Timetable generation.
 *
 * Source of truth order:
 * 1) course_faculty_assignment rows for the selected session/degree/section
 * 2) course_faculty pool
 * 3) optional auto assignment
 */
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/schema_helpers.php';
require_once __DIR__ . '/../config/merged_lecture.php';
require_once __DIR__ . '/../config/timetable_helpers.php';

db_add_column_if_missing($conn, 'course', 'credit_hours_lab', 'TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER credit_hours');
db_add_column_if_missing($conn, 'time_slot', 'slot_type', "ENUM('lecture','lab') NOT NULL DEFAULT 'lecture' AFTER slot_label");
merged_lecture_ensure_schema($conn);
tt_ensure_flexible_time_slot_index($conn);

$rawInput = file_get_contents('php://input');
$input = $rawInput ? (json_decode($rawInput, true) ?? []) : ($_POST ?? []);

$session_id = (int)($input['academic_session_id'] ?? 0);
$degree_id = (int)($input['degree_id'] ?? 0);
$semester = (int)($input['semester'] ?? 1);
$section = trim((string)($input['section'] ?? 'A'));
$clear_first = !empty($input['clear_first']);
$course_ids_input = $input['course_ids'] ?? [];
$auto_assign = !empty($input['auto_assign_missing_faculty']);

if ($session_id <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Academic session required.']);
    exit;
}
if ($degree_id <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Degree is required for timetable generation.']);
    exit;
}
if ($semester <= 0 || $section === '') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Semester and section are required.']);
    exit;
}

$start = microtime(true);

function norm_time($time) {
    return tt_norm_time($time);
}

function check_overlap($s1, $e1, $s2, $e2) {
    $s1 = norm_time($s1);
    $e1 = norm_time($e1);
    $s2 = norm_time($s2);
    $e2 = norm_time($e2);
    return ($s1 < $e2) && ($e1 > $s2);
}

function add_unplaced(&$unplaced, $course, $type, $reason) {
    $label = trim((string)($course['code'] ?? ''));
    if ($label === '') $label = 'Course #' . (int)($course['id'] ?? 0);
    $unplaced[] = [
        'course_id' => (int)($course['id'] ?? 0),
        'course_code' => $label,
        'component' => $type,
        'reason' => $reason,
        'label' => $label . ' (' . $type . '): ' . $reason,
    ];
}

function remove_one_component(array &$to_schedule, string $component): void {
    $idx = array_search($component, $to_schedule, true);
    if ($idx !== false) {
        array_splice($to_schedule, $idx, 1);
        return;
    }
    if (!empty($to_schedule)) {
        array_shift($to_schedule);
    }
}

function manual_component_for_assignment(array $manual, array $to_schedule): string {
    $roomType = strtolower((string)($manual['room_type'] ?? ''));
    if ($roomType === 'lab') {
        return 'lab';
    }
    if (in_array('lecture', $to_schedule, true)) {
        return 'lecture';
    }
    return $to_schedule[0] ?? 'lecture';
}

function slot_conflict_reason(array $existing_slots, int $faculty_id, int $room_id, int $semester, string $section, array $slot): ?string {
    foreach ($existing_slots as $ex) {
        if ((int)$ex['day_of_week'] !== (int)$slot['day_of_week']) {
            continue;
        }
        if (!check_overlap($slot['start_time'], $slot['end_time'], $ex['start_time'], $ex['end_time'])) {
            continue;
        }
        if ((int)$ex['room_id'] === $room_id) {
            return 'Room conflict at ' . norm_time($slot['start_time']) . ' - ' . norm_time($slot['end_time']) . '.';
        }
        if ((int)$ex['faculty_id'] === $faculty_id) {
            return 'Faculty conflict at ' . norm_time($slot['start_time']) . ' - ' . norm_time($slot['end_time']) . '.';
        }
        if ((int)$ex['semester'] === $semester && strcasecmp(trim((string)$ex['section']), $section) === 0) {
            return 'Cohort already has a class at this time.';
        }
    }
    return null;
}

function remember_slot(&$existing_slots, int $faculty_id, int $room_id, int $semester, string $section, array $slot): void {
    $existing_slots[] = [
        'faculty_id' => $faculty_id,
        'room_id' => $room_id,
        'semester' => $semester,
        'section' => $section,
        'day_of_week' => (int)$slot['day_of_week'],
        'start_time' => $slot['start_time'],
        'end_time' => $slot['end_time'],
    ];
}

function write_generated_assignment(mysqli $conn, int $course_id, int $faculty_id, int $session_id, int $degree_id, string $section, array $slot, int $room_id): void {
    $chk = $conn->prepare(
        'SELECT id FROM course_faculty_assignment
         WHERE course_id = ?
           AND COALESCE(academic_session_id, 0) = ?
           AND COALESCE(degree_id, 0) = ?
           AND LOWER(TRIM(COALESCE(section, ""))) = LOWER(TRIM(?))
         LIMIT 1'
    );
    if (!$chk) return;
    $chk->bind_param('iiis', $course_id, $session_id, $degree_id, $section);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        return;
    }
    $ins = $conn->prepare(
        'INSERT INTO course_faculty_assignment
         (course_id, faculty_id, academic_session_id, degree_id, section, preferred_day_of_week, preferred_start_time, preferred_end_time, room_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$ins) return;
    $dow = (int)$slot['day_of_week'];
    $start = norm_time($slot['start_time']);
    $end = norm_time($slot['end_time']);
    $ins->bind_param('iiiisissi', $course_id, $faculty_id, $session_id, $degree_id, $section, $dow, $start, $end, $room_id);
    @$ins->execute();
}

// Fetch courses.
$courses = [];
$ids = [];
if (!empty($course_ids_input) && is_array($course_ids_input)) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $course_ids_input))));
}
$whereCoursesBase = 'WHERE c.is_active = 1 AND c.semester = ?';
$courseParamsBase = [$semester];
$courseTypesBase = 'i';
tt_apply_degree_course_filter($conn, $whereCoursesBase, $courseParamsBase, $courseTypesBase, $degree_id, 'c');
$whereCourses = $whereCoursesBase;
$courseParams = $courseParamsBase;
$courseTypes = $courseTypesBase;
if (!empty($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $whereCourses .= " AND c.id IN ($ph)";
    foreach ($ids as $id) {
        $courseParams[] = $id;
        $courseTypes .= 'i';
    }
}
$stmt = $conn->prepare("SELECT c.* FROM course c $whereCourses ORDER BY c.id");
$stmt->bind_param($courseTypes, ...$courseParams);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $courses[] = $r;

if (empty($courses)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => "No courses found for the selected degree and semester $semester."]);
    exit;
}

// Clear the existing generated rows for the selected degree/semester/section context
// before writing new faculty-slot mappings, so stale teacher names cannot survive.
if ($clear_first) {
    $clearCourseIdsForRun = [];
    $clearStmt = $conn->prepare("SELECT c.id FROM course c $whereCoursesBase ORDER BY c.id");
    $clearStmt->bind_param($courseTypesBase, ...$courseParamsBase);
    $clearStmt->execute();
    $clearRes = $clearStmt->get_result();
    while ($clearRes && ($row = $clearRes->fetch_assoc())) {
        $clearCourseIdsForRun[] = (int)$row['id'];
    }
    $courseIdsForRun = array_values(array_unique($clearCourseIdsForRun));
    if (empty($courseIdsForRun)) {
        $courseIdsForRun = array_values(array_unique(array_map(fn($row) => (int)$row['id'], $courses)));
    }
    $ph = implode(',', array_fill(0, count($courseIdsForRun), '?'));
    $sql = "DELETE FROM schedule
            WHERE academic_session_id = ?
              AND (
                (semester = ? AND LOWER(TRIM(section)) = LOWER(?))
                OR EXISTS (
                    SELECT 1 FROM schedule_merge_member m
                    WHERE m.schedule_id = schedule.id
                      AND m.semester = ?
                      AND LOWER(TRIM(m.section)) = LOWER(?)
                )
              )
              AND course_id IN ($ph)";
    $types2 = 'iisis' . str_repeat('i', count($courseIdsForRun));
    $params2 = [$session_id, $semester, $section, $semester, $section];
    foreach ($courseIdsForRun as $id) {
        $params2[] = $id;
    }
    $del = $conn->prepare($sql);
    $del->bind_param($types2, ...$params2);
    $del->execute();
}

// Rooms.
$rooms = [];
$res = $conn->query("SELECT id, room_number, room_type FROM room WHERE is_active = 1 ORDER BY id");
while ($res && ($r = $res->fetch_assoc())) $rooms[] = $r;
if (empty($rooms)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No active rooms found.']);
    exit;
}

// Faculty.
$faculty = [];
$res = $conn->query("SELECT id, full_name, department_id FROM faculty WHERE is_active = 1 ORDER BY id");
while ($res && ($r = $res->fetch_assoc())) $faculty[(int)$r['id']] = $r;
if (empty($faculty)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No active faculty found.']);
    exit;
}

// Time slots.
$time_slots = [];
$slots_by_exact = [];
$res = $conn->query("SELECT * FROM time_slot ORDER BY day_of_week, start_time");
while ($res && ($r = $res->fetch_assoc())) {
    $r['start_time'] = norm_time($r['start_time']);
    $r['end_time'] = norm_time($r['end_time']);
    $time_slots[(int)$r['id']] = $r;
    $key = (int)$r['day_of_week'] . '|' . $r['start_time'] . '|' . $r['end_time'];
    $slots_by_exact[$key][] = $r;
}

// Course-faculty pool.
$course_faculty_map = [];
$res = $conn->query('SELECT course_id, faculty_id FROM course_faculty ORDER BY faculty_id');
while ($res && ($r = $res->fetch_assoc())) {
    $course_faculty_map[(int)$r['course_id']][] = (int)$r['faculty_id'];
}

// Manual assignments for selected context.
$manual_assignments = [];
$stManual = $conn->prepare(
    'SELECT cfa.*, r.room_type
     FROM course_faculty_assignment cfa
     LEFT JOIN room r ON r.id = cfa.room_id
     WHERE cfa.academic_session_id = ?
       AND cfa.degree_id = ?
       AND LOWER(TRIM(COALESCE(cfa.section, ""))) = LOWER(TRIM(?))
     ORDER BY cfa.id'
);
$stManual->bind_param('iis', $session_id, $degree_id, $section);
$stManual->execute();
$mres = $stManual->get_result();
while ($m = $mres->fetch_assoc()) {
    $cid = (int)$m['course_id'];
    $manual_assignments[$cid] = $m;
    if (!isset($course_faculty_map[$cid])) $course_faculty_map[$cid] = [];
    $fid = (int)$m['faculty_id'];
    if ($fid > 0 && !in_array($fid, $course_faculty_map[$cid], true)) {
        $course_faculty_map[$cid][] = $fid;
    }
}

// Existing schedule conflicts include primary rows and merged member cohorts.
$existing_slots = [];
$res_sched = $conn->prepare(
    'SELECT s.faculty_id, s.room_id, s.semester, s.section, t.day_of_week, t.start_time, t.end_time
     FROM schedule s
     JOIN time_slot t ON s.time_slot_id = t.id
     WHERE s.academic_session_id = ?
     UNION ALL
     SELECT s.faculty_id, s.room_id, m.semester, m.section, t.day_of_week, t.start_time, t.end_time
     FROM schedule s
     JOIN schedule_merge_member m ON m.schedule_id = s.id
     JOIN time_slot t ON s.time_slot_id = t.id
     WHERE s.academic_session_id = ?'
);
$res_sched->bind_param('ii', $session_id, $session_id);
$res_sched->execute();
$rs = $res_sched->get_result();
while ($row = $rs->fetch_assoc()) {
    $row['start_time'] = norm_time($row['start_time']);
    $row['end_time'] = norm_time($row['end_time']);
    $existing_slots[] = $row;
}

$stmt_in = $conn->prepare(
    'INSERT INTO schedule (academic_session_id, course_id, faculty_id, room_id, time_slot_id, semester, section, is_merged_lecture)
     VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
);

$inserted = 0;
$scheduled = [];
$unplaced = [];
$auto_assigned_count = 0;
$manual_used_count = 0;
$writeback_done = [];

foreach ($courses as $c) {
    $cid = (int)$c['id'];
    $sessions_count = max(1, (int)$c['sessions_per_week']);
    $total_cr = max(0, (float)$c['credit_hours']);
    $lab_cr = max(0, (float)($c['credit_hours_lab'] ?? 0));
    if ($lab_cr > $total_cr) $lab_cr = $total_cr;

    $sessions_lab = 0;
    if ($total_cr > 0 && $lab_cr > 0) {
        $sessions_lab = (int) round(($sessions_count * $lab_cr) / $total_cr);
        $sessions_lab = max(1, min($sessions_count, $sessions_lab));
    }
    $sessions_theory = max(0, $sessions_count - $sessions_lab);

    $to_schedule = [];
    for ($i = 0; $i < $sessions_theory; $i++) $to_schedule[] = 'lecture';
    for ($i = 0; $i < $sessions_lab; $i++) $to_schedule[] = 'lab';
    if (empty($to_schedule)) $to_schedule[] = 'lecture';

    $manual = $manual_assignments[$cid] ?? null;
    $fid_to_use = 0;
    if ($manual) {
        $fid_to_use = (int)$manual['faculty_id'];
    } else {
        $assigned_fac_ids = $course_faculty_map[$cid] ?? [];
        if (!empty($assigned_fac_ids)) {
            $sec_ord = ord(strtoupper(substr($section, 0, 1) ?: 'A'));
            $fid_to_use = (int)$assigned_fac_ids[$sec_ord % count($assigned_fac_ids)];
        } elseif ($auto_assign) {
            $pool = array_filter($faculty, fn($f) => (int)$f['department_id'] === (int)$c['department_id']);
            if (empty($pool)) $pool = $faculty;
            $pool_values = array_values($pool);
            $sec_ord = ord(strtoupper(substr($section, 0, 1) ?: 'A'));
            $fid_to_use = (int)$pool_values[((int)$cid + $sec_ord) % count($pool_values)]['id'];
            $q_cf = $conn->prepare('INSERT IGNORE INTO course_faculty (course_id, faculty_id) VALUES (?, ?)');
            $q_cf->bind_param('ii', $cid, $fid_to_use);
            $q_cf->execute();
            $auto_assigned_count++;
        }
    }

    if ($fid_to_use <= 0) {
        foreach ($to_schedule as $component) {
            add_unplaced($unplaced, $c, $component, 'No faculty assignment found for this course.');
        }
        continue;
    }

    // Manual preference is hard priority. If it cannot be placed, do not auto-place that same component elsewhere.
    if ($manual && (int)$manual['preferred_day_of_week'] > 0 && !empty($manual['preferred_start_time']) && !empty($manual['preferred_end_time']) && (int)$manual['room_id'] > 0) {
        $key = (int)$manual['preferred_day_of_week'] . '|' . norm_time($manual['preferred_start_time']) . '|' . norm_time($manual['preferred_end_time']);
        $candidates = $slots_by_exact[$key] ?? [];
        $manual_room_id = (int)$manual['room_id'];
        $roomType = strtolower((string)($manual['room_type'] ?? ''));
        $component = manual_component_for_assignment($manual, $to_schedule);
        $manualSlot = null;
        if (empty($candidates)) {
            $manualSlot = tt_find_or_create_time_slot(
                $conn,
                (int)$manual['preferred_day_of_week'],
                (string)$manual['preferred_start_time'],
                (string)$manual['preferred_end_time'],
                $component
            );
            if ($manualSlot) {
                $time_slots[(int)$manualSlot['id']] = $manualSlot;
                $slotKey = (int)$manualSlot['day_of_week'] . '|' . norm_time($manualSlot['start_time']) . '|' . norm_time($manualSlot['end_time']);
                $slots_by_exact[$slotKey][] = $manualSlot;
            }
        } else {
            $manualSlot = $candidates[0];
            foreach ($candidates as $cand) {
                if ($roomType === 'lab' && ($cand['slot_type'] ?? '') === 'lab') {
                    $manualSlot = $cand;
                    break;
                }
                if ($roomType !== 'lab' && ($cand['slot_type'] ?? '') !== 'lab') {
                    $manualSlot = $cand;
                    break;
                }
            }
            $component = (($manualSlot['slot_type'] ?? 'lecture') === 'lab') ? 'lab' : 'lecture';
        }

        if (!$manualSlot) {
            add_unplaced($unplaced, $c, $component, 'Manual preference has an invalid day/time and was not auto-moved.');
            remove_one_component($to_schedule, $component);
        } else {
            $reason = slot_conflict_reason($existing_slots, $fid_to_use, $manual_room_id, $semester, $section, $manualSlot);
            if ($reason !== null) {
                add_unplaced($unplaced, $c, $component, 'Manual preference conflict: ' . $reason);
                remove_one_component($to_schedule, $component);
            } else {
                $slotId = (int)$manualSlot['id'];
                $stmt_in->bind_param('iiiiiis', $session_id, $cid, $fid_to_use, $manual_room_id, $slotId, $semester, $section);
                if ($stmt_in->execute()) {
                    remember_slot($existing_slots, $fid_to_use, $manual_room_id, $semester, $section, $manualSlot);
                    $inserted++;
                    $manual_used_count++;
                    $scheduled[] = [
                        'course_id' => $cid,
                        'course_code' => (string)($c['code'] ?? ''),
                        'component' => $component,
                        'manual' => true,
                    ];
                    remove_one_component($to_schedule, $component);
                } else {
                    add_unplaced($unplaced, $c, $component, 'Manual preference insert failed: ' . $conn->error);
                    remove_one_component($to_schedule, $component);
                }
            }
        }
    }

    foreach ($to_schedule as $required_type) {
        $placed = false;
        $typeSlots = array_values(array_filter($time_slots, fn($ts) => ($ts['slot_type'] ?? 'lecture') === $required_type));
        if (empty($typeSlots)) {
            add_unplaced($unplaced, $c, $required_type, 'No ' . $required_type . ' time slots exist.');
            continue;
        }
        $roomCandidates = array_values(array_filter($rooms, function($room) use ($required_type) {
            $isLab = strtolower((string)$room['room_type']) === 'lab';
            return $required_type === 'lab' ? $isLab : !$isLab;
        }));
        if (empty($roomCandidates)) {
            add_unplaced($unplaced, $c, $required_type, 'No active ' . ($required_type === 'lab' ? 'lab' : 'lecture') . ' room exists.');
            continue;
        }
        $lastReason = 'No conflict-free slot found.';
        foreach ($typeSlots as $slot) {
            if ($placed) break;
            foreach ($roomCandidates as $room) {
                $rid = (int)$room['id'];
                $reason = slot_conflict_reason($existing_slots, $fid_to_use, $rid, $semester, $section, $slot);
                if ($reason !== null) {
                    $lastReason = $reason;
                    continue;
                }
                $slotId = (int)$slot['id'];
                $stmt_in->bind_param('iiiiiis', $session_id, $cid, $fid_to_use, $rid, $slotId, $semester, $section);
                if ($stmt_in->execute()) {
                    remember_slot($existing_slots, $fid_to_use, $rid, $semester, $section, $slot);
                    $inserted++;
                    if (empty($writeback_done[$cid]) && empty($manual_assignments[$cid])) {
                        write_generated_assignment($conn, $cid, $fid_to_use, $session_id, $degree_id, $section, $slot, $rid);
                        $writeback_done[$cid] = true;
                    }
                    $scheduled[] = [
                        'course_id' => $cid,
                        'course_code' => (string)($c['code'] ?? ''),
                        'component' => $required_type,
                        'manual' => false,
                    ];
                    $placed = true;
                    break;
                }
                $lastReason = 'Insert failed: ' . $conn->error;
            }
        }
        if (!$placed) {
            add_unplaced($unplaced, $c, $required_type, $lastReason);
        }
    }
}

$elapsed = round(microtime(true) - $start, 2);
$unplacedUnique = [];
$seenUnplaced = [];
foreach ($unplaced as $u) {
    $k = $u['course_id'] . '|' . $u['component'] . '|' . $u['reason'];
    if (isset($seenUnplaced[$k])) continue;
    $seenUnplaced[$k] = true;
    $unplacedUnique[] = $u;
}

$status = empty($unplacedUnique) ? 'success' : ($inserted > 0 ? 'partial' : 'failed');
$msg = ($status === 'success')
    ? "Generated $inserted slot(s) in {$elapsed}s."
    : (($status === 'partial')
        ? "Partially generated $inserted slot(s); " . count($unplacedUnique) . ' item(s) could not be scheduled.'
        : 'No timetable slots were generated; ' . count($unplacedUnique) . ' item(s) could not be scheduled.');
if ($manual_used_count > 0) $msg .= " Respected $manual_used_count manual preference(s).";
if ($auto_assigned_count > 0) $msg .= " Auto-assigned $auto_assigned_count faculty link(s).";

ob_end_clean();
echo json_encode([
    'success' => $status !== 'failed',
    'status' => $status,
    'inserted' => $inserted,
    'scheduled_count' => count($scheduled),
    'unscheduled_count' => count($unplacedUnique),
    'manual_preferences_used' => $manual_used_count,
    'elapsed_seconds' => $elapsed,
    'message' => $msg,
    'scheduled' => $scheduled,
    'unplaced' => array_map(fn($u) => $u['label'], $unplacedUnique),
    'unscheduled' => $unplacedUnique,
]);
