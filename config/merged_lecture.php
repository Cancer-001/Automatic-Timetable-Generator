<?php
/**
 * Merged lectures: one schedule row + schedule_merge_member rows so multiple
 * semester/section cohorts share the same faculty/room/time slot.
 *
 * Conflict rules (enforced in create_merged_lecture and schedule_move):
 * - Faculty free at slot
 * - Room free at slot
 * - Each cohort has no other class at that slot (schedule row or merge member)
 * - Sum of cohort headcounts (enrolled in course, else cohort size) <= room.capacity
 */

/**
 * Additive schema: bridge table + flag on schedule. Safe to call on every request.
 */
function merged_lecture_ensure_schema(mysqli $conn): void {
    require_once __DIR__ . '/schema_helpers.php';
    db_add_column_if_missing($conn, 'schedule', 'is_merged_lecture', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER section');
    $conn->query("CREATE TABLE IF NOT EXISTS schedule_merge_member (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT UNSIGNED NOT NULL,
        semester TINYINT UNSIGNED NOT NULL,
        section VARCHAR(32) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_merge_member (schedule_id, semester, section),
        INDEX idx_merge_schedule (schedule_id),
        FOREIGN KEY (schedule_id) REFERENCES schedule(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * @param list<array{semester:int|string,section:string}> $cohorts
 * @return list<array{semester:int,section:string}>
 */
function merged_lecture_normalize_cohorts(array $cohorts): array {
    $out = [];
    $seen = [];
    foreach ($cohorts as $row) {
        $sem = (int) ($row['semester'] ?? $row['sem'] ?? 0);
        $sec = trim((string) ($row['section'] ?? ''));
        if ($sem < 1 || $sec === '') {
            continue;
        }
        $k = $sem . ':' . strtolower($sec);
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = ['semester' => $sem, 'section' => $sec];
    }
    usort($out, static function ($a, $b): int {
        if ($a['semester'] !== $b['semester']) {
            return $a['semester'] <=> $b['semester'];
        }
        return strcasecmp($a['section'], $b['section']);
    });
    return $out;
}

function merged_lecture_cohort_headcount(mysqli $conn, int $course_id, int $academic_session_id, int $semester, string $section): int {
    $sec = trim($section);
    $st = $conn->prepare(
        'SELECT COUNT(DISTINCT e.student_id) AS c
         FROM enrollment e
         INNER JOIN student st ON st.id = e.student_id AND st.is_active = 1
         WHERE e.course_id = ? AND e.academic_session_id = ? AND e.status = \'enrolled\'
           AND st.semester = ? AND LOWER(TRIM(st.section)) = LOWER(?)'
    );
    $st->bind_param('iiis', $course_id, $academic_session_id, $semester, $sec);
    $st->execute();
    $n = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
    if ($n > 0) {
        return $n;
    }
    $st2 = $conn->prepare(
        'SELECT COUNT(*) AS c FROM student WHERE is_active = 1 AND semester = ? AND LOWER(TRIM(section)) = LOWER(?)'
    );
    $st2->bind_param('is', $semester, $sec);
    $st2->execute();
    return (int) ($st2->get_result()->fetch_assoc()['c'] ?? 0);
}

/**
 * @return array<int,int> course_id => session count already on timetable for this cohort (primary row + merge membership)
 */
function merged_lecture_count_course_sessions_for_cohort(mysqli $conn, int $academic_session_id, int $semester, string $section): array {
    merged_lecture_ensure_schema($conn);
    $sec = trim($section);
    $counts = [];

    $q1 = $conn->prepare(
        'SELECT course_id, COUNT(*) AS c FROM schedule
         WHERE academic_session_id = ? AND semester = ? AND LOWER(TRIM(section)) = LOWER(?)
         GROUP BY course_id'
    );
    $q1->bind_param('iis', $academic_session_id, $semester, $sec);
    $q1->execute();
    $r1 = $q1->get_result();
    while ($row = $r1->fetch_assoc()) {
        $cid = (int) $row['course_id'];
        $counts[$cid] = ($counts[$cid] ?? 0) + (int) $row['c'];
    }

    $q2 = $conn->prepare(
        'SELECT s.course_id, COUNT(DISTINCT s.id) AS c
         FROM schedule_merge_member m
         INNER JOIN schedule s ON s.id = m.schedule_id
         WHERE s.academic_session_id = ? AND m.semester = ? AND LOWER(TRIM(m.section)) = LOWER(?)
         GROUP BY s.course_id'
    );
    $q2->bind_param('iis', $academic_session_id, $semester, $sec);
    $q2->execute();
    $r2 = $q2->get_result();
    while ($row = $r2->fetch_assoc()) {
        $cid = (int) $row['course_id'];
        $counts[$cid] = ($counts[$cid] ?? 0) + (int) $row['c'];
    }

    return $counts;
}

/**
 * Find a conflicting schedule entry for this cohort at slot (excluding optional schedule id).
 *
 * @return array{course_name:string,schedule_id:int}|null
 */
/**
 * All cohorts represented by this schedule row (primary + merge members).
 *
 * @return list<array{semester:int,section:string}>
 */
function merged_lecture_schedule_cohorts(mysqli $conn, int $schedule_id): array {
    merged_lecture_ensure_schema($conn);
    $st = $conn->prepare('SELECT semester, section FROM schedule WHERE id = ? LIMIT 1');
    $st->bind_param('i', $schedule_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        return [];
    }
    $cohorts = [['semester' => (int) $row['semester'], 'section' => trim((string) $row['section'])]];
    $st2 = $conn->prepare('SELECT semester, section FROM schedule_merge_member WHERE schedule_id = ? ORDER BY semester, section');
    $st2->bind_param('i', $schedule_id);
    $st2->execute();
    $r2 = $st2->get_result();
    while ($m = $r2->fetch_assoc()) {
        $cohorts[] = ['semester' => (int) $m['semester'], 'section' => trim((string) $m['section'])];
    }
    return merged_lecture_normalize_cohorts($cohorts);
}

function merged_lecture_find_cohort_slot_conflict(
    mysqli $conn,
    int $academic_session_id,
    int $time_slot_id,
    int $semester,
    string $section,
    int $exclude_schedule_id = 0
): ?array {
    merged_lecture_ensure_schema($conn);
    $sec = trim($section);
    $sql = 'SELECT s.id, c.name AS course_name FROM schedule s
        JOIN course c ON c.id = s.course_id
        WHERE s.academic_session_id = ? AND s.time_slot_id = ?
          AND s.id != ?
          AND (
            (s.semester = ? AND LOWER(TRIM(s.section)) = LOWER(?))
            OR EXISTS (
                SELECT 1 FROM schedule_merge_member m
                WHERE m.schedule_id = s.id AND m.semester = ? AND LOWER(TRIM(m.section)) = LOWER(?)
            )
          )
        LIMIT 1';
    $st = $conn->prepare($sql);
    $sem2 = $semester;
    $sec2 = $sec;
    $st->bind_param('iiiisis', $academic_session_id, $time_slot_id, $exclude_schedule_id, $semester, $sec, $sem2, $sec2);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    return ['schedule_id' => (int) $row['id'], 'course_name' => (string) $row['course_name']];
}

/**
 * Validate merged lecture; returns human-readable errors (empty = ok).
 *
 * @param list<array{semester:int,section:string}> $cohorts
 * @return list<string>
 */
function merged_lecture_validate_conflicts(
    mysqli $conn,
    int $academic_session_id,
    int $course_id,
    int $faculty_id,
    int $room_id,
    int $time_slot_id,
    array $cohorts,
    int $exclude_schedule_id = 0
): array {
    merged_lecture_ensure_schema($conn);
    $errors = [];

    if (count($cohorts) < 2) {
        $errors[] = 'Merged lecture requires at least two distinct semester/section cohorts.';
        return $errors;
    }

    $fr = $conn->prepare('SELECT id FROM faculty WHERE id = ? AND is_active = 1 LIMIT 1');
    $fr->bind_param('i', $faculty_id);
    $fr->execute();
    if (!$fr->get_result()->fetch_assoc()) {
        $errors[] = 'Faculty is not available (inactive or missing).';
    }

    $rr = $conn->prepare('SELECT id, capacity FROM room WHERE id = ? AND is_active = 1 LIMIT 1');
    $rr->bind_param('i', $room_id);
    $rr->execute();
    $roomRow = $rr->get_result()->fetch_assoc();
    if (!$roomRow) {
        $errors[] = 'Room is not available.';
    }
    $capacity = $roomRow ? (int) $roomRow['capacity'] : 0;

    $cr = $conn->prepare('SELECT id FROM course WHERE id = ? AND is_active = 1 LIMIT 1');
    $cr->bind_param('i', $course_id);
    $cr->execute();
    if (!$cr->get_result()->fetch_assoc()) {
        $errors[] = 'Course is not found or inactive.';
    }

    $tr = $conn->prepare('SELECT id FROM time_slot WHERE id = ? LIMIT 1');
    $tr->bind_param('i', $time_slot_id);
    $tr->execute();
    if (!$tr->get_result()->fetch_assoc()) {
        $errors[] = 'Time slot is invalid.';
    }

    $sr = $conn->prepare('SELECT id FROM academic_session WHERE id = ? LIMIT 1');
    $sr->bind_param('i', $academic_session_id);
    $sr->execute();
    if (!$sr->get_result()->fetch_assoc()) {
        $errors[] = 'Academic session is invalid.';
    }

    if ($errors !== []) {
        return $errors;
    }

    $stF = $conn->prepare(
        'SELECT id FROM schedule WHERE academic_session_id = ? AND faculty_id = ? AND time_slot_id = ? AND id != ? LIMIT 1'
    );
    $stF->bind_param('iiii', $academic_session_id, $faculty_id, $time_slot_id, $exclude_schedule_id);
    $stF->execute();
    if ($stF->get_result()->fetch_assoc()) {
        $errors[] = 'Faculty is already teaching another class in this time slot.';
    }

    $stR = $conn->prepare(
        'SELECT id FROM schedule WHERE academic_session_id = ? AND room_id = ? AND time_slot_id = ? AND id != ? LIMIT 1'
    );
    $stR->bind_param('iiii', $academic_session_id, $room_id, $time_slot_id, $exclude_schedule_id);
    $stR->execute();
    if ($stR->get_result()->fetch_assoc()) {
        $errors[] = 'Room is already booked in this time slot.';
    }

    foreach ($cohorts as $ch) {
        $conf = merged_lecture_find_cohort_slot_conflict(
            $conn,
            $academic_session_id,
            $time_slot_id,
            $ch['semester'],
            $ch['section'],
            $exclude_schedule_id
        );
        if ($conf !== null) {
            $errors[] = 'Cohort Sem ' . $ch['semester'] . ' / Sec ' . $ch['section']
                . ' already has "' . $conf['course_name'] . '" at this time.';
        }
    }

    if ($errors !== []) {
        return $errors;
    }

    $totalStudents = 0;
    foreach ($cohorts as $ch) {
        $totalStudents += merged_lecture_cohort_headcount($conn, $course_id, $academic_session_id, $ch['semester'], $ch['section']);
    }
    if ($totalStudents > $capacity) {
        $errors[] = 'Room capacity (' . $capacity . ') is less than combined cohort size (' . $totalStudents . ' students for this course).';
    }

    return $errors;
}

/**
 * Create one schedule row (primary cohort on the row) plus merge members for the rest.
 *
 * @param list<array{semester:int|string,section:string}> $section_groups
 * @return array{success:bool,message:string,schedule_id?:int,errors?:list<string>}
 */
function create_merged_lecture(
    mysqli $conn,
    int $academic_session_id,
    int $course_id,
    int $faculty_id,
    int $room_id,
    int $time_slot_id,
    array $section_groups
): array {
    merged_lecture_ensure_schema($conn);
    $cohorts = merged_lecture_normalize_cohorts($section_groups);
    $errors = merged_lecture_validate_conflicts(
        $conn,
        $academic_session_id,
        $course_id,
        $faculty_id,
        $room_id,
        $time_slot_id,
        $cohorts,
        0
    );
    if ($errors !== []) {
        return ['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors];
    }

    $primary = $cohorts[0];
    $rest = array_slice($cohorts, 1);

    $conn->begin_transaction();
    try {
        $ins = $conn->prepare(
            'INSERT INTO schedule
            (academic_session_id, course_id, faculty_id, room_id, time_slot_id, semester, section, is_merged_lecture)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $semP = $primary['semester'];
        $secP = $primary['section'];
        $ins->bind_param(
            'iiiiiis',
            $academic_session_id,
            $course_id,
            $faculty_id,
            $room_id,
            $time_slot_id,
            $semP,
            $secP
        );
        if (!$ins->execute()) {
            throw new RuntimeException('Could not create schedule row: ' . $conn->error);
        }
        $schedule_id = (int) $conn->insert_id;

        $mem = $conn->prepare(
            'INSERT INTO schedule_merge_member (schedule_id, semester, section) VALUES (?, ?, ?)'
        );
        foreach ($rest as $ch) {
            $sm = $ch['semester'];
            $sc = $ch['section'];
            $mem->bind_param('iis', $schedule_id, $sm, $sc);
            if (!$mem->execute()) {
                throw new RuntimeException('Could not add merge member: ' . $conn->error);
            }
        }

        $conn->commit();
        return [
            'success'     => true,
            'message'     => 'Merged lecture created.',
            'schedule_id' => $schedule_id,
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
