<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api_response.php';
require_once __DIR__ . '/../config/merged_lecture.php';

merged_lecture_ensure_schema($conn);

// Safe additive migration for calendar events table
$conn->query("CREATE TABLE IF NOT EXISTS calendar_event (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    event_type ENUM('lecture','lab','exam','holiday','custom') NOT NULL DEFAULT 'custom',
    academic_session_id INT UNSIGNED DEFAULT NULL,
    course_id INT UNSIGNED DEFAULT NULL,
    faculty_id INT UNSIGNED DEFAULT NULL,
    department_id INT UNSIGNED DEFAULT NULL,
    semester TINYINT UNSIGNED DEFAULT NULL,
    section VARCHAR(32) DEFAULT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    is_all_day TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ce_session (academic_session_id),
    INDEX idx_ce_faculty (faculty_id),
    INDEX idx_ce_student (semester, section),
    INDEX idx_ce_start (start_datetime),
    INDEX idx_ce_end (end_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$role = $_SESSION['role'];
$userId = (int)$_SESSION['user_id'];

if ($method === 'GET') {
    $sessionId = (int)($_GET['academic_session_id'] ?? 0);
    $departmentId = (int)($_GET['department_id'] ?? 0);
    $semesterFilter = (int)($_GET['semester'] ?? 0);

    $events = [];

    // 1) Timetable (schedule) as recurring weekly events
    $whereParts = ['1=1'];
    $params = [];
    $types = '';
    if ($sessionId > 0) {
        $whereParts[] = 's.academic_session_id = ?';
        $params[] = $sessionId;
        $types .= 'i';
    }
    if ($departmentId > 0) {
        $whereParts[] = 'c.department_id = ?';
        $params[] = $departmentId;
        $types .= 'i';
    }
    if ($semesterFilter > 0) {
        $whereParts[] = '(s.semester = ? OR EXISTS (
            SELECT 1 FROM schedule_merge_member mf
            WHERE mf.schedule_id = s.id AND mf.semester = ?
        ))';
        $params[] = $semesterFilter;
        $params[] = $semesterFilter;
        $types .= 'ii';
    }
    if ($role === 'faculty') {
        if ($userId > 0) {
            // Faculty calendar must only show slots where this faculty member is
            // the final scheduled instructor for that exact day/time.
            $whereParts[] = 's.faculty_id = ?';
            $params[] = $userId;
            $types .= 'i';
        } else {
            $whereParts[] = '1=0';
        }
    } elseif ($role === 'student') {
        $st = $conn->prepare('SELECT semester, section, department_id FROM student WHERE id = ? LIMIT 1');
        $st->bind_param('i', $userId);
        $st->execute();
        $stu = $st->get_result()->fetch_assoc();
        if (!$stu) {
            echo json_encode(['success' => true, 'events' => []]);
            exit;
        }
        $stuSem = (int)$stu['semester'];
        $stuSec = trim((string)$stu['section']);
        $whereParts[] = '( (s.semester = ? AND s.section = ?) OR EXISTS (
            SELECT 1 FROM schedule_merge_member m
            WHERE m.schedule_id = s.id AND m.semester = ? AND LOWER(TRIM(m.section)) = LOWER(?)
        ) )';
        $params[] = $stuSem;
        $params[] = $stuSec;
        $params[] = $stuSem;
        $params[] = $stuSec;
        $types .= 'isis';
        $stuDept = (int)($stu['department_id'] ?? 0);
        if ($stuDept > 0) {
            $whereParts[] = 'COALESCE(c.department_id, 0) = ?';
            $params[] = $stuDept;
            $types .= 'i';
        }
    }
    $whereParts[] = "(
        NOT EXISTS (
            SELECT 1
            FROM course_faculty_assignment cfa_scope
            WHERE cfa_scope.course_id = s.course_id
              AND COALESCE(cfa_scope.academic_session_id, 0) = COALESCE(s.academic_session_id, 0)
              AND LOWER(TRIM(COALESCE(cfa_scope.section, ''))) = LOWER(TRIM(COALESCE(s.section, '')))
        )
        OR EXISTS (
            SELECT 1
            FROM course_faculty_assignment cfa_match
            WHERE cfa_match.course_id = s.course_id
              AND cfa_match.faculty_id = s.faculty_id
              AND COALESCE(cfa_match.academic_session_id, 0) = COALESCE(s.academic_session_id, 0)
              AND LOWER(TRIM(COALESCE(cfa_match.section, ''))) = LOWER(TRIM(COALESCE(s.section, '')))
        )
    )";

    $whereSQL = implode(' AND ', $whereParts);
    $sql = "SELECT s.id, s.course_id, s.faculty_id, s.semester, s.section, s.academic_session_id,
                   c.name AS course_name, c.code AS course_code,
                   f.full_name AS faculty_name,
                   d.name AS department_name, d.code AS department_code,
                   r.room_number,
                   t.day_of_week, t.start_time, t.end_time, COALESCE(t.slot_type,'lecture') AS slot_type,
                   acs.name AS session_name, acs.start_date, acs.end_date,
                   COALESCE(s.is_merged_lecture, 0) AS is_merged_lecture,
                   COALESCE(
                       (
                           SELECT NULLIF(TRIM(CONCAT(COALESCE(dg2.code, ''), CASE WHEN dg2.name IS NOT NULL AND TRIM(dg2.name) <> '' THEN CONCAT(' - ', dg2.name) ELSE '' END)), '')
                           FROM course_faculty_assignment cfa2
                           LEFT JOIN degree dg2 ON dg2.id = cfa2.degree_id
                           WHERE cfa2.course_id = s.course_id
                             AND cfa2.faculty_id = s.faculty_id
                             AND COALESCE(cfa2.academic_session_id, 0) = COALESCE(s.academic_session_id, 0)
                             AND LOWER(TRIM(COALESCE(cfa2.section, ''))) = LOWER(TRIM(COALESCE(s.section, '')))
                           ORDER BY cfa2.id DESC
                           LIMIT 1
                       ),
                       CASE WHEN d.code IS NOT NULL AND TRIM(d.code) <> '' THEN CONCAT('BS', UPPER(d.code)) ELSE '' END
                   ) AS program_name
            FROM schedule s
            JOIN course c ON c.id = s.course_id
            JOIN faculty f ON f.id = s.faculty_id
            LEFT JOIN department d ON d.id = c.department_id
            JOIN room r ON r.id = s.room_id
            JOIN time_slot t ON t.id = s.time_slot_id
            JOIN academic_session acs ON acs.id = s.academic_session_id
            WHERE $whereSQL";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $scheduleId = (int)($r['id'] ?? 0);
        $cohorts = merged_lecture_schedule_cohorts($conn, $scheduleId);
        if ($cohorts === []) {
            $cohorts = [[
                'semester' => (int)($r['semester'] ?? 0),
                'section' => (string)($r['section'] ?? ''),
            ]];
        }
        $totalStudents = 0;
        $semesterLabels = [];
        foreach ($cohorts as $ch) {
            $chSem = (int)($ch['semester'] ?? 0);
            $chSec = trim((string)($ch['section'] ?? ''));
            if ($chSem > 0) {
                $semesterLabels[] = $chSem . ($chSec !== '' ? ('-' . $chSec) : '');
                $totalStudents += merged_lecture_cohort_headcount($conn, (int)$r['course_id'], (int)$r['academic_session_id'], $chSem, $chSec);
            }
        }
        $semesterLabels = array_values(array_unique($semesterLabels));
        $rawDay = max(1, min(7, (int)$r['day_of_week']));
        // DB: 1=Mon..7=Sun -> FullCalendar: 1=Mon..6=Sat,0=Sun
        $dow = $rawDay % 7;
        $color = ($r['slot_type'] === 'lab') ? '#16a34a' : '#2563eb';
        $cohortLabel = implode(', ', $semesterLabels);
        $title = $r['course_code'] . ' - ' . $r['course_name'];
        if ((int)($r['is_merged_lecture'] ?? 0) === 1 && $cohortLabel !== '') {
            $title .= ' [' . $cohortLabel . ']';
        }
        $events[] = [
            'id' => 'sch_' . $r['id'],
            'title' => $title,
            'daysOfWeek' => [$dow],
            'startTime' => substr((string)$r['start_time'], 0, 5),
            'endTime' => substr((string)$r['end_time'], 0, 5),
            'startRecur' => $r['start_date'],
            'endRecur' => date('Y-m-d', strtotime($r['end_date'] . ' +1 day')),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'source' => 'schedule',
                'event_type' => $r['slot_type'],
                'faculty_id' => (int)($r['faculty_id'] ?? 0),
                'faculty_name' => $r['faculty_name'],
                'room_number' => $r['room_number'],
                'department_name' => $r['department_name'] ?? '',
                'department_code' => $r['department_code'] ?? '',
                'program_name' => (string)($r['program_name'] ?? ''),
                'semester' => (int)$r['semester'],
                'semester_labels' => $semesterLabels,
                'cohorts_label' => $cohortLabel,
                'section' => (string)$r['section'],
                'total_students' => $totalStudents,
                'is_merged_lecture' => (int)($r['is_merged_lecture'] ?? 0) === 1,
                'session_name' => $r['session_name']
            ]
        ];
    }
    // 2) Custom events (exam/holiday/custom)
    $whereParts = ['1=1'];
    $params = [];
    $types = '';
    if ($sessionId > 0) {
        $whereParts[] = '(ce.academic_session_id = ? OR ce.academic_session_id IS NULL)';
        $params[] = $sessionId;
        $types .= 'i';
    }
    if ($role === 'faculty') {
        $whereParts[] = '(ce.faculty_id IS NULL OR ce.faculty_id = ?)';
        $params[] = $userId;
        $types .= 'i';
    } elseif ($role === 'student') {
        $st = $conn->prepare('SELECT semester, section, department_id FROM student WHERE id = ? LIMIT 1');
        $st->bind_param('i', $userId);
        $st->execute();
        $stu = $st->get_result()->fetch_assoc();
        if ($stu) {
            $whereParts[] = '(ce.semester IS NULL OR ce.semester = ?)';
            $params[] = (int)$stu['semester'];
            $types .= 'i';
            $whereParts[] = '(ce.section IS NULL OR ce.section = ?)';
            $params[] = trim((string)$stu['section']);
            $types .= 's';
            $stuDept = (int)($stu['department_id'] ?? 0);
            if ($stuDept > 0) {
                $whereParts[] = '(ce.department_id IS NULL OR ce.department_id = ?)';
                $params[] = $stuDept;
                $types .= 'i';
            }
        }
    }
    $whereSQL = implode(' AND ', $whereParts);
    $sql = "SELECT ce.* FROM calendar_event ce WHERE $whereSQL ORDER BY ce.start_datetime";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $colorMap = [
            'lecture' => '#2563eb',
            'lab' => '#16a34a',
            'exam' => '#dc2626',
            'holiday' => '#f97316',
            'custom' => '#f97316'
        ];
        $etype = $r['event_type'] ?: 'custom';
        $color = $colorMap[$etype] ?? '#7c3aed';
        if (stripos((string)$r['title'], 'unavailable') !== false) {
            $color = '#6b7280';
        }
        $events[] = [
            'id' => 'evt_' . $r['id'],
            'title' => $r['title'],
            'start' => str_replace(' ', 'T', (string)$r['start_datetime']),
            'end' => str_replace(' ', 'T', (string)$r['end_datetime']),
            'allDay' => (bool)$r['is_all_day'],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'source' => 'custom',
                'event_id' => (int)$r['id'],
                'event_type' => $etype,
                'notes' => (string)($r['notes'] ?? '')
            ]
        ];
    }

    echo json_encode(['success' => true, 'events' => $events]);
    exit;
}

if ($role !== 'admin') {
    api_error('Only admin can modify custom calendar events.', 403);
}

if ($method === 'POST') {
    $title = trim((string)($input['title'] ?? ''));
    $eventType = trim((string)($input['event_type'] ?? 'custom'));
    $start = trim((string)($input['start_datetime'] ?? ''));
    $end = trim((string)($input['end_datetime'] ?? ''));
    $sessionId = !empty($input['academic_session_id']) ? (int)$input['academic_session_id'] : null;
    $semester = !empty($input['semester']) ? (int)$input['semester'] : null;
    $section = isset($input['section']) ? trim((string)$input['section']) : null;
    $isAllDay = !empty($input['is_all_day']) ? 1 : 0;
    $notes = isset($input['notes']) ? trim((string)$input['notes']) : null;

    if ($title === '' || $start === '' || $end === '') {
        api_error('Title, start and end are required.');
    }
    $stmt = $conn->prepare('INSERT INTO calendar_event (title, event_type, academic_session_id, semester, section, start_datetime, end_datetime, is_all_day, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssiisssisi', $title, $eventType, $sessionId, $semester, $section, $start, $end, $isAllDay, $notes, $userId);
    if (!$stmt->execute()) api_error('Could not create event.');
    echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Event created.']);
    exit;
}

if ($method === 'PUT') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) api_error('Event id required.');
    $title = trim((string)($input['title'] ?? ''));
    $eventType = trim((string)($input['event_type'] ?? 'custom'));
    $start = trim((string)($input['start_datetime'] ?? ''));
    $end = trim((string)($input['end_datetime'] ?? ''));
    $sessionId = !empty($input['academic_session_id']) ? (int)$input['academic_session_id'] : null;
    $semester = !empty($input['semester']) ? (int)$input['semester'] : null;
    $section = isset($input['section']) ? trim((string)$input['section']) : null;
    $isAllDay = !empty($input['is_all_day']) ? 1 : 0;
    $notes = isset($input['notes']) ? trim((string)$input['notes']) : null;
    if ($title === '' || $start === '' || $end === '') api_error('Title, start and end are required.');
    $stmt = $conn->prepare('UPDATE calendar_event SET title=?, event_type=?, academic_session_id=?, semester=?, section=?, start_datetime=?, end_datetime=?, is_all_day=?, notes=? WHERE id=?');
    $stmt->bind_param('ssiisssisi', $title, $eventType, $sessionId, $semester, $section, $start, $end, $isAllDay, $notes, $id);
    if (!$stmt->execute()) api_error('Could not update event.');
    echo json_encode(['success' => true, 'message' => 'Event updated.']);
    exit;
}

if ($method === 'DELETE') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) api_error('Event id required.');
    $stmt = $conn->prepare('DELETE FROM calendar_event WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) api_error('Could not delete event.');
    echo json_encode(['success' => true, 'message' => 'Event deleted.']);
    exit;
}

api_error('Method not allowed.', 405);
