<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/schema_helpers.php';
require_once __DIR__ . '/../config/merged_lecture.php';
require_once __DIR__ . '/../config/timetable_helpers.php';

// Auto-add missing columns safely
db_add_column_if_missing($conn, 'course', 'credit_hours_lab', 'TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER credit_hours');
db_add_column_if_missing($conn, 'time_slot', 'slot_type', "ENUM('lecture','lab') NOT NULL DEFAULT 'lecture' AFTER slot_label");
merged_lecture_ensure_schema($conn);

$session_id = (int)($_GET['academic_session_id'] ?? 0);
$faculty_id = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : null;
$semester   = isset($_GET['semester'])   ? (int)$_GET['semester']   : null;
$section    = isset($_GET['section'])    ? trim($_GET['section'])    : null;
$batch_list = isset($_GET['batch_list']) && $_GET['batch_list'] === '1';
$generated_sessions = isset($_GET['generated_sessions']) && $_GET['generated_sessions'] === '1';

if ($generated_sessions) {
    $sql = "SELECT
                acs.id,
                acs.name,
                acs.start_date,
                acs.end_date,
                COUNT(s.id) AS generated_rows,
                MAX(s.created_at) AS latest_generated_at
            FROM schedule s
            INNER JOIN academic_session acs ON acs.id = s.academic_session_id
            GROUP BY acs.id, acs.name, acs.start_date, acs.end_date
            ORDER BY MAX(s.created_at) DESC, acs.start_date DESC";
    $res = $conn->query($sql);
    $out = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[] = [
                'id' => (int)($r['id'] ?? 0),
                'name' => (string)($r['name'] ?? ''),
                'start_date' => (string)($r['start_date'] ?? ''),
                'end_date' => (string)($r['end_date'] ?? ''),
                'generated_rows' => (int)($r['generated_rows'] ?? 0),
                'latest_generated_at' => (string)($r['latest_generated_at'] ?? ''),
            ];
        }
    }
    echo json_encode(['success' => true, 'list' => $out]);
    exit;
}

// Faculty or student may omit academic_session_id to load all relevant classes across every session.
if (!$session_id && !$faculty_id) {
    $gateRole = $_SESSION['role'] ?? '';
    if ($gateRole !== 'faculty' && $gateRole !== 'student') {
        echo json_encode(['success' => false, 'message' => 'academic_session_id or faculty_id required']);
        exit;
    }
}

$role    = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);
/** Faculty: when true, only rows where this faculty is the scheduled instructor (e.g. substitution picker). */
$strict_faculty = ($role === 'faculty' && isset($_GET['strict_faculty']) && $_GET['strict_faculty'] === '1');

if ($batch_list) {
    // Recent generated semester/section batches for a selected academic session.
    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'academic_session_id required']);
        exit;
    }
    $stmt = $conn->prepare(
        "SELECT x.semester, x.section,
                COUNT(*) AS total_rows,
                MAX(x.created_at) AS generated_at
         FROM (
            SELECT s.id, s.semester, s.section, s.created_at
            FROM schedule s
            WHERE s.academic_session_id = ?
            UNION ALL
            SELECT s.id, m.semester, m.section, s.created_at
            FROM schedule s
            INNER JOIN schedule_merge_member m ON m.schedule_id = s.id
            WHERE s.academic_session_id = ?
         ) x
         GROUP BY x.semester, x.section
         ORDER BY MAX(x.created_at) DESC, x.semester ASC, x.section ASC"
    );
    $stmt->bind_param('ii', $session_id, $session_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($r = $res->fetch_assoc()) {
        $list[] = [
            'semester' => (int)($r['semester'] ?? 0),
            'section' => (string)($r['section'] ?? ''),
            'total_rows' => (int)($r['total_rows'] ?? 0),
            'generated_at' => (string)($r['generated_at'] ?? ''),
        ];
    }
    echo json_encode(['success' => true, 'list' => $list]);
    exit;
}

// Faculty timetable: all sessions; rows where they teach OR are on the course teaching pool (like cohort-wide student view).
if ($role === 'faculty') {
    $faculty_id = null;
    $semester   = null;
    $section    = null;
}
// Student cohort comes from DB only — ignore semester/section query params.
if ($role === 'student') {
    $faculty_id = null;
    $semester   = null;
    $section    = null;
}

// Build WHERE conditions
$whereParts = ['1=1'];
$params     = [];
$types      = '';

if ($session_id) {
    $whereParts[] = 's.academic_session_id = ?';
    $params[]     = $session_id;
    $types       .= 'i';
}
if ($faculty_id) {
    $whereParts[] = 's.faculty_id = ?';
    $params[]     = $faculty_id;
    $types       .= 'i';
}

// Role-based filters
if ($role === 'faculty') {
    if ($user_id <= 0) {
        $whereParts[] = '1=0';
    } elseif ($strict_faculty) {
        $whereParts[] = 's.faculty_id = ?';
        $params[]     = $user_id;
        $types       .= 'i';
    } else {
        $whereParts[] = '(s.faculty_id = ? OR s.course_id IN (SELECT cf.course_id FROM course_faculty cf WHERE cf.faculty_id = ?))';
        $params[]     = $user_id;
        $params[]     = $user_id;
        $types       .= 'ii';
    }
}
if ($role === 'student') {
    $st = $conn->prepare('SELECT semester, section, department_id, degree_id FROM student WHERE id = ? LIMIT 1');
    $st->bind_param('i', $user_id);
    $st->execute();
    $stu = $st->get_result()->fetch_assoc();
    if (!$stu) {
        echo json_encode(['success' => true, 'list' => []]);
        exit;
    }
    $stuSem = (int)$stu['semester'];
    $stuSec = trim((string)$stu['section']);
    $whereParts[] = '( (s.semester = ? AND s.section = ?) OR EXISTS (
        SELECT 1 FROM schedule_merge_member m
        WHERE m.schedule_id = s.id AND m.semester = ? AND LOWER(TRIM(m.section)) = LOWER(?)
    ) )';
    $params[]     = $stuSem;
    $params[]     = $stuSec;
    $params[]     = $stuSem;
    $params[]     = $stuSec;
    $types       .= 'isis';
    $stuDept = (int)($stu['department_id'] ?? 0);
    if ($stuDept > 0) {
        $whereParts[] = 'COALESCE(c.department_id, 0) = ?';
        $params[]     = $stuDept;
        $types       .= 'i';
    }
} elseif ($role !== 'faculty' && $semester !== null && $semester > 0 && $section !== null && $section !== '') {
    // Admin (and other non-student, non-faculty): primary row OR merged cohort member — so merged lectures show for every section
    $whereParts[] = '( (s.semester = ? AND LOWER(TRIM(s.section)) = LOWER(?)) OR EXISTS (
        SELECT 1 FROM schedule_merge_member m
        WHERE m.schedule_id = s.id AND m.semester = ? AND LOWER(TRIM(m.section)) = LOWER(?)
    ) )';
    $params[]     = $semester;
    $params[]     = $section;
    $params[]     = $semester;
    $params[]     = $section;
    $types       .= 'isis';
} elseif ($role !== 'faculty' && $role !== 'student') {
    if ($semester !== null && $semester > 0) {
        $whereParts[] = 's.semester = ?';
        $params[]     = $semester;
        $types       .= 'i';
    }
    if ($section !== null && $section !== '') {
        $whereParts[] = 'LOWER(TRIM(s.section)) = LOWER(?)';
        $params[]     = $section;
        $types       .= 's';
    }
}

$whereSQL = implode(' AND ', $whereParts);

// Full SELECT — note: use double-quoted PHP string so SQL single quotes are safe
$selectSQL = "SELECT
    s.id,
    s.semester,
    s.section,
    COALESCE(s.is_merged_lecture, 0) AS is_merged_lecture,
    s.course_id,
    s.faculty_id,
    s.room_id,
    s.time_slot_id,
    s.academic_session_id,
    c.code             AS course_code,
    c.name             AS course_name,
    c.credit_hours     AS cht,
    COALESCE(c.credit_hours_lab, 0) AS chl,
    d.name             AS department_name,
    d.code             AS department_code,
    f.full_name        AS faculty_name,
    r.room_number,
    t.slot_label,
    COALESCE(t.slot_type, 'lecture') AS slot_type,
    t.day_of_week,
    t.start_time,
    t.end_time,
    acs.name           AS session_name,
    acs.start_date     AS session_start,
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
        (
            SELECT NULLIF(TRIM(CONCAT(COALESCE(dg3.code, ''), CASE WHEN dg3.name IS NOT NULL AND TRIM(dg3.name) <> '' THEN CONCAT(' - ', dg3.name) ELSE '' END)), '')
            FROM course_faculty_assignment cfa3
            LEFT JOIN degree dg3 ON dg3.id = cfa3.degree_id
            WHERE cfa3.course_id = s.course_id
              AND COALESCE(cfa3.academic_session_id, 0) = COALESCE(s.academic_session_id, 0)
              AND LOWER(TRIM(COALESCE(cfa3.section, ''))) = LOWER(TRIM(COALESCE(s.section, '')))
              AND cfa3.degree_id IS NOT NULL
            ORDER BY cfa3.id DESC
            LIMIT 1
        ),
        (
            SELECT NULLIF(TRIM(CONCAT(COALESCE(dg4.code, ''), CASE WHEN dg4.name IS NOT NULL AND TRIM(dg4.name) <> '' THEN CONCAT(' - ', dg4.name) ELSE '' END)), '')
            FROM course_faculty_assignment cfa4
            LEFT JOIN degree dg4 ON dg4.id = cfa4.degree_id
            WHERE cfa4.course_id = s.course_id
              AND COALESCE(cfa4.academic_session_id, 0) = COALESCE(s.academic_session_id, 0)
              AND cfa4.degree_id IS NOT NULL
            ORDER BY cfa4.id DESC
            LIMIT 1
        ),
        (
            SELECT NULLIF(TRIM(CONCAT(COALESCE(dg5.code, ''), CASE WHEN dg5.name IS NOT NULL AND TRIM(dg5.name) <> '' THEN CONCAT(' - ', dg5.name) ELSE '' END)), '')
            FROM faculty f2
            LEFT JOIN degree dg5 ON dg5.id = f2.degree_id
            WHERE f2.id = s.faculty_id AND f2.degree_id IS NOT NULL
            LIMIT 1
        )
    ) AS program_name,
    (SELECT GROUP_CONCAT(
            CONCAT('Sem ', m.semester, ' Sec ', m.section)
            ORDER BY m.semester, m.section
            SEPARATOR ' · '
        )
        FROM schedule_merge_member m
        WHERE m.schedule_id = s.id
    ) AS merge_extra_cohorts
FROM schedule s
JOIN course c              ON c.id  = s.course_id
LEFT JOIN department d     ON d.id  = c.department_id
JOIN faculty f             ON f.id  = s.faculty_id
JOIN room r                ON r.id  = s.room_id
JOIN time_slot t           ON t.id  = s.time_slot_id
JOIN academic_session acs  ON acs.id = s.academic_session_id
WHERE $whereSQL
ORDER BY acs.start_date DESC, t.day_of_week, t.start_time";

$stmt = $conn->prepare($selectSQL);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res  = $stmt->get_result();

$day_names = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];

$list = [];
while ($r = $res->fetch_assoc()) {
    // Duration comes from the actual stored start/end time, including custom manual slots.
    $r['duration'] = tt_duration_label($r['start_time'] ?? '00:00:00', $r['end_time'] ?? '00:00:00');

    // Full day name
    $r['day_name'] = $day_names[(int)($r['day_of_week'] ?? 1)] ?? 'Monday';

    // Time range in 12-hour format with AM/PM, e.g. "8:00 AM - 9:30 AM"
    $fmtT = function($t) {
        if (!$t) return '';
        $p = explode(':', $t);
        $h24 = (int)($p[0] ?? 0);
        $min = str_pad($p[1] ?? '00', 2, '0', STR_PAD_LEFT);
        $suffix = $h24 >= 12 ? 'PM' : 'AM';
        $h12 = $h24 % 12;
        if ($h12 === 0) $h12 = 12;
        return $h12 . ':' . $min . ' ' . $suffix;
    };
    $r['time_range'] = $fmtT($r['start_time']) . ' - ' . $fmtT($r['end_time']);

    $list[] = $r;
}

$payload = ['success' => true, 'list' => $list];
// Faculty: courses linked in course_faculty (pool) may exist without schedule rows (generator picks one instructor per slot).
if ($role === 'faculty' && $user_id > 0) {
    $linked = [];
    $lc = $conn->prepare(
        'SELECT c.id AS course_id, c.code AS course_code, c.name AS course_name, c.semester,
                d.code AS department_code, d.name AS department_name
         FROM course_faculty cf
         INNER JOIN course c ON c.id = cf.course_id AND COALESCE(c.is_active, 1) = 1
         LEFT JOIN department d ON d.id = c.department_id
         WHERE cf.faculty_id = ?
         ORDER BY c.semester ASC, c.code ASC'
    );
    if ($lc) {
        $lc->bind_param('i', $user_id);
        $lc->execute();
        $lres = $lc->get_result();
        while ($row = $lres->fetch_assoc()) {
            $linked[] = $row;
        }
    }
    $payload['linked_courses'] = $linked;
}

echo json_encode($payload);
