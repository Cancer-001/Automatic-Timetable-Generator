<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api_response.php';
require_once __DIR__ . '/../config/schema_helpers.php';
require_once __DIR__ . '/../config/assignment_defaults_sync.php';
require_once __DIR__ . '/../config/timetable_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Additive schema: detailed assignments (does not alter existing course_faculty columns)
$conn->query("CREATE TABLE IF NOT EXISTS course_faculty_assignment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NOT NULL,
    academic_session_id INT UNSIGNED DEFAULT NULL,
    degree_id INT UNSIGNED DEFAULT NULL,
    section VARCHAR(32) DEFAULT NULL,
    preferred_day_of_week TINYINT UNSIGNED DEFAULT NULL COMMENT '1=Mon .. 5=Fri',
    preferred_start_time TIME DEFAULT NULL,
    preferred_end_time TIME DEFAULT NULL,
    room_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cfa_course (course_id),
    INDEX idx_cfa_faculty (faculty_id),
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_session(id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES room(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$chkDow = @$conn->query("SHOW COLUMNS FROM course_faculty_assignment LIKE 'preferred_day_of_week'");
if ($chkDow && $chkDow->num_rows === 0) {
    @$conn->query("ALTER TABLE course_faculty_assignment ADD COLUMN preferred_day_of_week TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '1=Mon .. 5=Fri' AFTER section");
}
// DB-level class context lock with normalized nullable keys (prevents race-condition duplicates).
@db_add_column_if_missing($conn, 'course_faculty_assignment', 'cfa_ctx_session', 'INT UNSIGNED GENERATED ALWAYS AS (COALESCE(academic_session_id, 0)) STORED');
@db_add_column_if_missing($conn, 'course_faculty_assignment', 'cfa_ctx_degree', 'INT UNSIGNED GENERATED ALWAYS AS (COALESCE(degree_id, 0)) STORED');
@db_add_column_if_missing($conn, 'course_faculty_assignment', 'cfa_ctx_section', "VARCHAR(32) GENERATED ALWAYS AS (LOWER(TRIM(COALESCE(section, '')))) STORED");
$idxCtx = @$conn->query("SHOW INDEX FROM course_faculty_assignment WHERE Key_name = 'uq_cfa_class_ctx'");
if (!$idxCtx || $idxCtx->num_rows === 0) {
    @$conn->query("ALTER TABLE course_faculty_assignment
        ADD UNIQUE KEY uq_cfa_class_ctx (course_id, cfa_ctx_session, cfa_ctx_degree, cfa_ctx_section)");
}

function sync_course_faculty_link(mysqli $conn, int $course_id, int $faculty_id): void {
    $stmt = $conn->prepare('INSERT IGNORE INTO course_faculty (course_id, faculty_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $course_id, $faculty_id);
    $stmt->execute();
}

function unsync_course_faculty_if_unused(mysqli $conn, int $course_id, int $faculty_id): void {
    $st = $conn->prepare('SELECT COUNT(*) AS c FROM course_faculty_assignment WHERE course_id = ? AND faculty_id = ?');
    $st->bind_param('ii', $course_id, $faculty_id);
    $st->execute();
    $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    if ($c === 0) {
        $del = $conn->prepare('DELETE FROM course_faculty WHERE course_id = ? AND faculty_id = ?');
        $del->bind_param('ii', $course_id, $faculty_id);
        $del->execute();
    }
}

function validate_visiting_faculty_slot(
    mysqli $conn,
    int $faculty_id,
    ?int $preferred_day_of_week,
    ?string $preferred_start,
    ?string $preferred_end
): ?string {
    $fmt12 = static function (?string $t): string {
        $raw = trim((string)$t);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime('1970-01-01 ' . substr($raw, 0, 8));
        if ($ts === false) {
            return substr($raw, 0, 5);
        }
        return date('h:i A', $ts);
    };
    $st = $conn->prepare('SELECT full_name, faculty_type, visiting_day_of_week, visiting_start_time, visiting_end_time FROM faculty WHERE id = ? AND is_active = 1 LIMIT 1');
    if (!$st) {
        return 'Could not validate faculty availability.';
    }
    $st->bind_param('i', $faculty_id);
    $st->execute();
    $f = $st->get_result()->fetch_assoc();
    if (!$f) {
        return 'Selected faculty was not found or is inactive.';
    }
    if (strtolower((string)($f['faculty_type'] ?? 'permanent')) !== 'visiting') {
        return null;
    }
    $vDow = isset($f['visiting_day_of_week']) ? (int)$f['visiting_day_of_week'] : 0;
    $vStart = trim((string)($f['visiting_start_time'] ?? ''));
    $vEnd = trim((string)($f['visiting_end_time'] ?? ''));
    if ($vDow < 1 || $vDow > 5 || $vStart === '' || $vEnd === '') {
        return 'This is a visiting faculty member, but no valid visiting-time window is configured in Faculty Management.';
    }
    if ($preferred_day_of_week === null || $preferred_start === null || $preferred_end === null) {
        return 'This is a visiting faculty member. Please select day, start time, and end time within the faculty visiting window.';
    }
    $normTime = static function (?string $time): ?string {
        $raw = trim((string)$time);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
            $h = (int)$m[1];
            $i = (int)$m[2];
            $s = isset($m[3]) ? (int)$m[3] : 0;
            if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59 && $s >= 0 && $s <= 59) {
                return sprintf('%02d:%02d:%02d', $h, $i, $s);
            }
        }
        return null;
    };
    $slotStart = $normTime($preferred_start);
    $slotEnd = $normTime($preferred_end);
    $visitStart = $normTime($vStart);
    $visitEnd = $normTime($vEnd);
    if ($slotStart === null || $slotEnd === null || $visitStart === null || $visitEnd === null) {
        return 'Invalid time format. Please use HH:MM time values.';
    }
    $inWindow = (
        (int)$preferred_day_of_week === $vDow
        && $slotStart >= $visitStart
        && $slotEnd <= $visitEnd
    );
    if ($inWindow) {
        return null;
    }
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
    $name = trim((string)($f['full_name'] ?? 'This faculty'));
    return $name . ' is a visiting faculty member and can only be assigned on ' . ($days[$vDow] ?? 'the configured day') . ' between ' . $fmt12($visitStart) . ' and ' . $fmt12($visitEnd) . '. You selected ' . ($days[(int)$preferred_day_of_week] ?? 'another day') . ' ' . $fmt12($slotStart) . ' - ' . $fmt12($slotEnd) . '. Please choose a valid slot.';
}

function find_assignment_conflict(
    mysqli $conn,
    int $course_id,
    int $faculty_id,
    ?int $academic_session_id,
    ?int $degree_id,
    ?string $section,
    ?int $preferred_day_of_week,
    ?string $preferred_start,
    ?string $preferred_end,
    ?int $room_id,
    ?int $exclude_assignment_id = null
): ?array {
    $sectionNorm = ($section === null) ? '' : trim($section);

    // Strict class lock: one faculty per same course/session/degree/section context.
    $sqlClass = 'SELECT cfa.id, cfa.faculty_id, f.full_name
        FROM course_faculty_assignment cfa
        INNER JOIN faculty f ON f.id = cfa.faculty_id
        WHERE cfa.course_id = ?
          AND COALESCE(cfa.academic_session_id, 0) = COALESCE(?, 0)
          AND COALESCE(cfa.degree_id, 0) = COALESCE(?, 0)
          AND LOWER(TRIM(COALESCE(cfa.section, ""))) = LOWER(TRIM(?))
          AND cfa.faculty_id <> ?';
    $typesClass = 'iiisi';
    $paramsClass = [$course_id, $academic_session_id, $degree_id, $sectionNorm, $faculty_id];
    if ($exclude_assignment_id !== null) {
        $sqlClass .= ' AND cfa.id <> ?';
        $typesClass .= 'i';
        $paramsClass[] = $exclude_assignment_id;
    }
    $sqlClass .= ' LIMIT 1';
    $stmtClass = $conn->prepare($sqlClass);
    if ($stmtClass) {
        $stmtClass->bind_param($typesClass, ...$paramsClass);
        $stmtClass->execute();
        $rowClass = $stmtClass->get_result()->fetch_assoc();
        if ($rowClass) {
            return ['type' => 'class', 'row' => $rowClass];
        }
    }

    // Time-slot checks require complete day + start + end.
    if ($preferred_day_of_week === null || $preferred_start === null || $preferred_end === null) {
        return null;
    }

    // Faculty double-booking in same academic session.
    $sqlFaculty = 'SELECT cfa.id
        FROM course_faculty_assignment cfa
        WHERE cfa.faculty_id = ?
          AND COALESCE(cfa.academic_session_id, 0) = COALESCE(?, 0)
          AND cfa.preferred_day_of_week = ?
          AND cfa.preferred_start_time IS NOT NULL
          AND cfa.preferred_end_time IS NOT NULL
          AND (? < cfa.preferred_end_time AND ? > cfa.preferred_start_time)';
    $typesFaculty = 'iiiss';
    $paramsFaculty = [$faculty_id, $academic_session_id, $preferred_day_of_week, $preferred_start, $preferred_end];
    if ($exclude_assignment_id !== null) {
        $sqlFaculty .= ' AND cfa.id <> ?';
        $typesFaculty .= 'i';
        $paramsFaculty[] = $exclude_assignment_id;
    }
    $sqlFaculty .= ' LIMIT 1';
    $stmtFaculty = $conn->prepare($sqlFaculty);
    if ($stmtFaculty) {
        $stmtFaculty->bind_param($typesFaculty, ...$paramsFaculty);
        $stmtFaculty->execute();
        $rowFaculty = $stmtFaculty->get_result()->fetch_assoc();
        if ($rowFaculty) {
            return ['type' => 'faculty', 'row' => $rowFaculty];
        }
    }

    // Room double-booking in same academic session.
    if ($room_id !== null) {
        $sqlRoom = 'SELECT cfa.id
            FROM course_faculty_assignment cfa
            WHERE cfa.room_id = ?
              AND COALESCE(cfa.academic_session_id, 0) = COALESCE(?, 0)
              AND cfa.preferred_day_of_week = ?
              AND cfa.preferred_start_time IS NOT NULL
              AND cfa.preferred_end_time IS NOT NULL
              AND (? < cfa.preferred_end_time AND ? > cfa.preferred_start_time)';
        $typesRoom = 'iiiss';
        $paramsRoom = [$room_id, $academic_session_id, $preferred_day_of_week, $preferred_start, $preferred_end];
        if ($exclude_assignment_id !== null) {
            $sqlRoom .= ' AND cfa.id <> ?';
            $typesRoom .= 'i';
            $paramsRoom[] = $exclude_assignment_id;
        }
        $sqlRoom .= ' LIMIT 1';
        $stmtRoom = $conn->prepare($sqlRoom);
        if ($stmtRoom) {
            $stmtRoom->bind_param($typesRoom, ...$paramsRoom);
            $stmtRoom->execute();
            $rowRoom = $stmtRoom->get_result()->fetch_assoc();
            if ($rowRoom) {
                return ['type' => 'room', 'row' => $rowRoom];
            }
        }
    }

    return null;
}

function normalize_assignment_time_input(?string $value): ?string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $raw)) {
        return null;
    }
    return tt_norm_time($raw);
}

if ($method === 'GET') {
    if (!empty($_GET['session_courses']) && $_GET['session_courses'] === '1') {
        $session_id = (int)($_GET['academic_session_id'] ?? 0);
        $semester = (int)($_GET['semester'] ?? 0);
        if ($session_id <= 0 || $semester <= 0) {
            echo json_encode(['success' => true, 'course_ids' => []]);
            exit;
        }
        $stmtSc = $conn->prepare('SELECT DISTINCT c.id AS course_id
            FROM course c
            INNER JOIN course_faculty_assignment cfa ON cfa.course_id = c.id
            WHERE c.is_active = 1
              AND c.semester = ?
              AND cfa.academic_session_id = ?
            ORDER BY c.code');
        $stmtSc->bind_param('ii', $semester, $session_id);
        $stmtSc->execute();
        $resSc = $stmtSc->get_result();
        $ids = [];
        while ($row = $resSc->fetch_assoc()) {
            $ids[] = (int)$row['course_id'];
        }
        echo json_encode(['success' => true, 'course_ids' => $ids]);
        exit;
    }

    $course_id = (int)($_GET['course_id'] ?? 0);
    if (!$course_id) {
        api_error('Course required.');
    }

    // Legacy: flat faculty list for timetable "Move" dropdown and other callers
    if (!empty($_GET['simple']) && $_GET['simple'] === '1') {
        $res = $conn->query("SELECT f.id, f.full_name, f.email FROM faculty f JOIN course_faculty cf ON cf.faculty_id = f.id WHERE cf.course_id = $course_id AND f.is_active = 1 ORDER BY f.full_name");
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        echo json_encode($rows);
        exit;
    }

    // Fill assignment rows for Timetable-pool-only faculty so Session/Degree/Section/Time/Room show like manually added courses.
    $ctx = assignment_defaults_resolve_context($conn);
    if ($ctx) {
        assignment_defaults_sync_one_course(
            $conn,
            $course_id,
            $ctx['session_id'],
            $ctx['pref_start'],
            $ctx['pref_end'],
            $ctx['room_ids'],
            $ctx['degree_id']
        );
    }

    $sql = "SELECT cfa.id AS assignment_id,
            cfa.course_id,
            cfa.faculty_id,
            f.full_name AS faculty_name,
            f.email AS faculty_email,
            c.code AS course_code,
            c.name AS course_name,
            cfa.academic_session_id,
            acs.name AS session_name,
            cfa.degree_id,
            dg.name AS degree_name,
            cfa.section,
            cfa.preferred_day_of_week,
            cfa.preferred_start_time,
            cfa.preferred_end_time,
            cfa.room_id,
            r.room_number
        FROM course_faculty_assignment cfa
        JOIN faculty f ON f.id = cfa.faculty_id
        JOIN course c ON c.id = cfa.course_id
        LEFT JOIN academic_session acs ON acs.id = cfa.academic_session_id
        LEFT JOIN degree dg ON dg.id = cfa.degree_id
        LEFT JOIN room r ON r.id = cfa.room_id
        WHERE cfa.course_id = ?
        ORDER BY cfa.id";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $assignments = [];
    while ($r = $res->fetch_assoc()) {
        $assignments[] = $r;
    }

    // Rows only in course_faculty (e.g. auto-assigned by timetable Generate) — modal used to show empty.
    $sqlCfOnly = "SELECT
            NULL AS assignment_id,
            cf.course_id,
            cf.faculty_id,
            f.full_name AS faculty_name,
            f.email AS faculty_email,
            c.code AS course_code,
            c.name AS course_name,
            NULL AS academic_session_id,
            NULL AS session_name,
            NULL AS degree_id,
            NULL AS degree_name,
            NULL AS section,
            NULL AS preferred_day_of_week,
            NULL AS preferred_start_time,
            NULL AS preferred_end_time,
            NULL AS room_id,
            NULL AS room_number
        FROM course_faculty cf
        INNER JOIN faculty f ON f.id = cf.faculty_id AND f.is_active = 1
        INNER JOIN course c ON c.id = cf.course_id
        WHERE cf.course_id = ?
          AND NOT EXISTS (
            SELECT 1 FROM course_faculty_assignment cfa
            WHERE cfa.course_id = cf.course_id AND cfa.faculty_id = cf.faculty_id
          )
        ORDER BY f.full_name";
    $stCf = $conn->prepare($sqlCfOnly);
    if ($stCf) {
        $stCf->bind_param('i', $course_id);
        $stCf->execute();
        $resCf = $stCf->get_result();
        while ($r = $resCf->fetch_assoc()) {
            $r['from_course_faculty_pool'] = true;
            $assignments[] = $r;
        }
    }

    echo json_encode(['success' => true, 'assignments' => $assignments]);
    exit;
}

if ($method === 'POST') {
    $course_id = (int)($input['course_id'] ?? 0);
    $faculty_id = (int)($input['faculty_id'] ?? 0);
    if (!$course_id || !$faculty_id) {
        api_error('Course and faculty required.');
    }

    $academic_session_id = !empty($input['academic_session_id']) ? (int)$input['academic_session_id'] : null;
    $degree_id = !empty($input['degree_id']) ? (int)$input['degree_id'] : null;
    $section = isset($input['section']) ? trim((string)$input['section']) : null;
    if ($section === '') {
        $section = null;
    }

    $pst = isset($input['preferred_start_time']) ? trim((string)$input['preferred_start_time']) : '';
    $pet = isset($input['preferred_end_time']) ? trim((string)$input['preferred_end_time']) : '';
    $preferred_start = normalize_assignment_time_input($pst);
    $preferred_end = normalize_assignment_time_input($pet);
    if (($pst !== '' && $preferred_start === null) || ($pet !== '' && $preferred_end === null)) {
        api_error('Preferred start/end time is invalid.');
    }

    $room_id = !empty($input['room_id']) ? (int)$input['room_id'] : null;

    $pref_dow = null;
    if (isset($input['preferred_day_of_week']) && $input['preferred_day_of_week'] !== '') {
        $d = (int) $input['preferred_day_of_week'];
        if ($d >= 1 && $d <= 5) {
            $pref_dow = $d;
        }
    }
    $slotParts = [
        $pref_dow !== null,
        $preferred_start !== null,
        $preferred_end !== null
    ];
    $slotPartsCount = 0;
    foreach ($slotParts as $hasPart) {
        if ($hasPart) $slotPartsCount++;
    }
    if ($slotPartsCount > 0 && $slotPartsCount < 3) {
        api_error('Provide complete slot details: day, start time, and end time together.');
    }
    if ($preferred_start !== null && $preferred_end !== null && tt_minutes_from_time($preferred_end) <= tt_minutes_from_time($preferred_start)) {
        api_error('Preferred end time must be after preferred start time.');
    }
    if ($academic_session_id === null || $degree_id === null || $section === null || $pref_dow === null || $preferred_start === null || $preferred_end === null || $room_id === null) {
        api_error('All fields are required: session, degree, section, day, faculty, preferred start, preferred end, and room.');
    }
    $visitingError = validate_visiting_faculty_slot($conn, $faculty_id, $pref_dow, $preferred_start, $preferred_end);
    if ($visitingError !== null) {
        api_error($visitingError);
    }

    $conflict = find_assignment_conflict(
        $conn,
        $course_id,
        $faculty_id,
        $academic_session_id,
        $degree_id,
        $section,
        $pref_dow,
        $preferred_start,
        $preferred_end,
        $room_id
    );
    if ($conflict) {
        $msg = 'Already assigned: conflict exists for this class/day/time.';
        if ($conflict['type'] === 'class') {
            $name = trim((string)($conflict['row']['full_name'] ?? ''));
            $msg = $name !== ''
                ? 'Already assigned: this class context is locked to ' . $name . '.'
                : 'Already assigned: this class context already has another faculty.';
        } elseif ($conflict['type'] === 'faculty') {
            $msg = 'Already assigned: selected faculty is busy at this time.';
        } elseif ($conflict['type'] === 'room') {
            $msg = 'Already assigned: selected room is occupied at this time.';
        }
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    if ($pref_dow !== null) {
        $stmt = $conn->prepare('INSERT INTO course_faculty_assignment
            (course_id, faculty_id, academic_session_id, degree_id, section, preferred_day_of_week, preferred_start_time, preferred_end_time, room_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param(
            'iiiisissi',
            $course_id,
            $faculty_id,
            $academic_session_id,
            $degree_id,
            $section,
            $pref_dow,
            $preferred_start,
            $preferred_end,
            $room_id
        );
    } else {
        $stmt = $conn->prepare('INSERT INTO course_faculty_assignment
            (course_id, faculty_id, academic_session_id, degree_id, section, preferred_start_time, preferred_end_time, room_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param(
            'iiiisssi',
            $course_id,
            $faculty_id,
            $academic_session_id,
            $degree_id,
            $section,
            $preferred_start,
            $preferred_end,
            $room_id
        );
    }

    if (!$stmt->execute()) {
        if ((int)$conn->errno === 1062) {
            api_error('Already assigned: this class context already has another faculty.');
        }
        api_error('Could not save assignment.');
    }

    sync_course_faculty_link($conn, $course_id, $faculty_id);

    echo json_encode(['success' => true, 'message' => 'Faculty assigned.', 'assignment_id' => $conn->insert_id]);
    exit;
}

if ($method === 'PUT') {
    $assignment_id = (int) ($input['assignment_id'] ?? 0);
    $course_id = (int) ($input['course_id'] ?? 0);
    if (!$assignment_id || !$course_id) {
        api_error('Assignment id and course required.');
    }

    $st = $conn->prepare('SELECT id, course_id, faculty_id FROM course_faculty_assignment WHERE id = ?');
    if (!$st) {
        api_error('DB error.');
    }
    $st->bind_param('i', $assignment_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row || (int) $row['course_id'] !== $course_id) {
        api_error('Assignment not found.');
    }
    $old_faculty_id = (int) $row['faculty_id'];

    $faculty_id = !empty($input['faculty_id']) ? (int) $input['faculty_id'] : $old_faculty_id;
    if (!$faculty_id) {
        api_error('Faculty required.');
    }

    $academic_session_id = !empty($input['academic_session_id']) ? (int) $input['academic_session_id'] : null;
    $degree_id = !empty($input['degree_id']) ? (int) $input['degree_id'] : null;
    $section = isset($input['section']) ? trim((string) $input['section']) : null;
    if ($section === '') {
        $section = null;
    }

    $pst = isset($input['preferred_start_time']) ? trim((string) $input['preferred_start_time']) : '';
    $pet = isset($input['preferred_end_time']) ? trim((string) $input['preferred_end_time']) : '';
    $preferred_start = normalize_assignment_time_input($pst);
    $preferred_end = normalize_assignment_time_input($pet);
    if (($pst !== '' && $preferred_start === null) || ($pet !== '' && $preferred_end === null)) {
        api_error('Preferred start/end time is invalid.');
    }

    $room_id = !empty($input['room_id']) ? (int) $input['room_id'] : null;

    $pref_dow = null;
    if (isset($input['preferred_day_of_week']) && $input['preferred_day_of_week'] !== '') {
        $d = (int) $input['preferred_day_of_week'];
        if ($d >= 1 && $d <= 5) {
            $pref_dow = $d;
        }
    }
    $slotParts = [
        $pref_dow !== null,
        $preferred_start !== null,
        $preferred_end !== null
    ];
    $slotPartsCount = 0;
    foreach ($slotParts as $hasPart) {
        if ($hasPart) $slotPartsCount++;
    }
    if ($slotPartsCount > 0 && $slotPartsCount < 3) {
        api_error('Provide complete slot details: day, start time, and end time together.');
    }
    if ($preferred_start !== null && $preferred_end !== null && tt_minutes_from_time($preferred_end) <= tt_minutes_from_time($preferred_start)) {
        api_error('Preferred end time must be after preferred start time.');
    }
    if ($academic_session_id === null || $degree_id === null || $section === null || $pref_dow === null || $preferred_start === null || $preferred_end === null || $room_id === null) {
        api_error('All fields are required: session, degree, section, day, faculty, preferred start, preferred end, and room.');
    }
    $visitingError = validate_visiting_faculty_slot($conn, $faculty_id, $pref_dow, $preferred_start, $preferred_end);
    if ($visitingError !== null) {
        api_error($visitingError);
    }

    $conflict = find_assignment_conflict(
        $conn,
        $course_id,
        $faculty_id,
        $academic_session_id,
        $degree_id,
        $section,
        $pref_dow,
        $preferred_start,
        $preferred_end,
        $room_id,
        $assignment_id
    );
    if ($conflict) {
        $msg = 'Already assigned: conflict exists for this class/day/time.';
        if ($conflict['type'] === 'class') {
            $name = trim((string)($conflict['row']['full_name'] ?? ''));
            $msg = $name !== ''
                ? 'Already assigned: this class context is locked to ' . $name . '.'
                : 'Already assigned: this class context already has another faculty.';
        } elseif ($conflict['type'] === 'faculty') {
            $msg = 'Already assigned: selected faculty is busy at this time.';
        } elseif ($conflict['type'] === 'room') {
            $msg = 'Already assigned: selected room is occupied at this time.';
        }
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    $upd = $conn->prepare(
        'UPDATE course_faculty_assignment SET
            faculty_id = ?,
            academic_session_id = ?,
            degree_id = ?,
            section = ?,
            preferred_day_of_week = ?,
            preferred_start_time = ?,
            preferred_end_time = ?,
            room_id = ?
         WHERE id = ? AND course_id = ?'
    );
    if (!$upd) {
        api_error('DB prepare failed.');
    }
    $upd->bind_param(
        'iiisissiii',
        $faculty_id,
        $academic_session_id,
        $degree_id,
        $section,
        $pref_dow,
        $preferred_start,
        $preferred_end,
        $room_id,
        $assignment_id,
        $course_id
    );
    if (!$upd->execute()) {
        if ((int)$conn->errno === 1062) {
            api_error('Already assigned: this class context already has another faculty.');
        }
        api_error('Could not update assignment.');
    }

    if ($faculty_id !== $old_faculty_id) {
        unsync_course_faculty_if_unused($conn, $course_id, $old_faculty_id);
    }
    sync_course_faculty_link($conn, $course_id, $faculty_id);

    echo json_encode(['success' => true, 'message' => 'Assignment updated.']);
    exit;
}

if ($method === 'DELETE') {
    $assignment_id = (int)($input['assignment_id'] ?? $_GET['assignment_id'] ?? 0);

    if ($assignment_id) {
        $st = $conn->prepare('SELECT course_id, faculty_id FROM course_faculty_assignment WHERE id = ?');
        $st->bind_param('i', $assignment_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if (!$row) {
            api_error('Assignment not found.');
        }
        $course_id = (int)$row['course_id'];
        $faculty_id = (int)$row['faculty_id'];

        $del = $conn->prepare('DELETE FROM course_faculty_assignment WHERE id = ?');
        $del->bind_param('i', $assignment_id);
        if (!$del->execute()) {
            api_error('Could not remove assignment.');
        }
        unsync_course_faculty_if_unused($conn, $course_id, $faculty_id);
        echo json_encode(['success' => true, 'message' => 'Assignment removed.']);
        exit;
    }

    // Legacy: remove link without assignment row
    $course_id = (int)($input['course_id'] ?? $_GET['course_id'] ?? 0);
    $faculty_id = (int)($input['faculty_id'] ?? $_GET['faculty_id'] ?? 0);
    if (!$course_id || !$faculty_id) {
        api_error('Assignment id or course and faculty required.');
    }

    $d1 = $conn->prepare('DELETE FROM course_faculty_assignment WHERE course_id = ? AND faculty_id = ?');
    $d1->bind_param('ii', $course_id, $faculty_id);
    $d1->execute();

    $stmt = $conn->prepare('DELETE FROM course_faculty WHERE course_id = ? AND faculty_id = ?');
    $stmt->bind_param('ii', $course_id, $faculty_id);
    if (!$stmt->execute()) {
        api_error('Could not remove assignment.');
    }
    echo json_encode(['success' => true, 'message' => 'Faculty removed from course.']);
    exit;
}

api_error('Method not allowed.', 405);
