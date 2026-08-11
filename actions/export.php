<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/schema_helpers.php';
require_once __DIR__ . '/../config/merged_lecture.php';
require_once __DIR__ . '/../config/timetable_helpers.php';

// Auto-add credit_hours_lab if missing
db_add_column_if_missing($conn, 'course', 'credit_hours_lab', 'TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER credit_hours');
merged_lecture_ensure_schema($conn);

$role       = $_SESSION['role'] ?? '';
$user_id    = (int)($_SESSION['user_id'] ?? 0);
$session_id = (int)($_GET['academic_session_id'] ?? 0);
$format     = $_GET['format'] ?? 'csv';
// Faculty / student: academic_session_id=0 exports all sessions for that user (matches portal auto-load).
$faculty_all_sessions = ($role === 'faculty' && $session_id === 0);
$student_all_sessions = ($role === 'student' && $session_id === 0);

if ((!$session_id && !$faculty_all_sessions && !$student_all_sessions) || !in_array($format, ['csv', 'pdf', 'ics'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// Format time as "H:MM" (no leading zero on hour)
function fmt_time(?string $t): string {
    if (!$t) return '';
    $p = explode(':', $t);
    return (int)($p[0] ?? 0) . ':' . str_pad($p[1] ?? '00', 2, '0', STR_PAD_LEFT);
}

function duration_hours(?string $start, ?string $end): string {
    if (!$start || !$end) return '';
    return tt_duration_label($start, $end);
}

$day_names = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];

// Build schedule query — same format as schedule.php
$sql = 'SELECT
    s.semester, s.section,
    s.academic_session_id,
    c.code AS course_code, c.name AS course_name,
    c.credit_hours AS cht, COALESCE(c.credit_hours_lab,0) AS chl,
    d.name AS department_name, d.code AS department_code,
    f.full_name AS faculty_name,
    r.room_number,
    t.slot_label, t.start_time, t.end_time, t.day_of_week,
    acs.name AS session_name,
    acs.start_date AS session_start,
    acs.end_date AS session_end
FROM schedule s
JOIN course c            ON c.id  = s.course_id
LEFT JOIN department d   ON d.id  = c.department_id
JOIN faculty f           ON f.id  = s.faculty_id
JOIN room r              ON r.id  = s.room_id
JOIN time_slot t         ON t.id  = s.time_slot_id
JOIN academic_session acs ON acs.id = s.academic_session_id
WHERE ';
$params = [];
$types  = '';

if ($faculty_all_sessions || $student_all_sessions) {
    $sql .= '1=1';
} else {
    $sql .= 's.academic_session_id = ?';
    $params[] = $session_id;
    $types    = 'i';
}

if ($role === 'faculty') {
    if ($user_id > 0) {
        $sql .= ' AND (s.faculty_id = ? OR s.course_id IN (SELECT cf.course_id FROM course_faculty cf WHERE cf.faculty_id = ?))';
        $params[] = $user_id;
        $params[] = $user_id;
        $types   .= 'ii';
    } else {
        $sql .= ' AND 1=0';
    }
}
if ($role === 'student') {
    $st = $conn->prepare('SELECT semester, section, degree, department_id FROM student WHERE id = ? LIMIT 1');
    $st->bind_param('i', $user_id);
    $st->execute();
    $stu = $st->get_result()->fetch_assoc();
    if ($stu) {
        $stuSem = (int)$stu['semester'];
        $stuSec = trim((string)$stu['section']);
        $sql .= ' AND ( (s.semester = ? AND s.section = ?) OR EXISTS (
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
            $sql .= ' AND COALESCE(c.department_id, 0) = ?';
            $params[] = $stuDept;
            $types .= 'i';
        }
    }
}
$sql .= ' ORDER BY acs.start_date DESC, t.day_of_week, t.start_time';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = [];
$res  = $stmt->get_result();
while ($r = $res->fetch_assoc()) $rows[] = $r;

// Get session name for title
$sess_name = '';
if ($faculty_all_sessions || $student_all_sessions) {
    $sess_name = 'All academic sessions';
} elseif (!empty($rows)) {
    $sess_name = $rows[0]['session_name'] ?? '';
}

// ─── CSV ────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $fn = ($faculty_all_sessions || $student_all_sessions) ? 'timetable_all_sessions' : 'timetable_' . $session_id;
    header('Content-Disposition: attachment; filename="' . $fn . '.csv"');
    $out = fopen('php://output', 'w');
    // Header row matching the image format
    fputcsv($out, ['Program','Semester','Section','Session','CHT','CHL','Course Title','Teacher Name','Duration','Day','Time','Room','Remarks']);
    foreach ($rows as $r) {
        $dept_code  = strtoupper($r['department_code'] ?? '');
        $degree     = ''; // not in export query directly; use dept_code as program approximation
        $program    = ($dept_code ? 'BS' . $dept_code : '');
        $time_range = fmt_time($r['start_time']) . ' - ' . fmt_time($r['end_time']);
        $duration   = duration_hours($r['start_time'], $r['end_time']);
        $day_name   = $day_names[(int)($r['day_of_week'] ?? 1)] ?? '';
        $remarks    = '';
        fputcsv($out, [
            $program,
            $r['semester'],
            $r['section'],
            $r['session_name'] ?? '',
            $r['cht'] ?? '',
            $r['chl'] ?? '',
            $r['course_name'],
            $r['faculty_name'],
            $duration,
            $day_name,
            $time_range,
            $r['room_number'],
            $remarks
        ]);
    }
    fclose($out);
    exit;
}

// ─── PDF / Print ─────────────────────────────────────────────────────────────
if ($format === 'pdf') {
    header('Content-Type: text/html; charset=utf-8');
    $title = 'Timetable' . ($sess_name ? ' — ' . $sess_name : '');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,Helvetica,sans-serif;font-size:11px;padding:16px;color:#111;}
h2{font-size:15px;margin-bottom:12px;color:#1e3a8a;}
table{border-collapse:collapse;width:100%;}
th{background:#1e3a8a;color:#fff;padding:7px 8px;text-align:left;font-size:10.5px;white-space:nowrap;}
td{border:1px solid #d1d5db;padding:6px 8px;vertical-align:middle;}
tr:nth-child(even) td{background:#f0f4ff;}
tr:nth-child(odd)  td{background:#ffffff;}
.badge-program{background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:4px;font-weight:700;font-size:10px;}
.badge-section{background:#dcfce7;color:#166534;padding:2px 5px;border-radius:4px;font-size:10px;}
.text-center{text-align:center;}
@media print{body{padding:8px;}th{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style></head><body>';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<table><thead><tr>
        <th>Program</th><th>Semester</th><th>Section</th><th>Session</th>
        <th>CHT</th><th>CHL</th><th>Course Title</th>
        <th>Teacher Name</th><th>Duration</th><th>Day</th><th>Time</th><th>Room</th><th>Remarks</th>
    </tr></thead><tbody>';
    foreach ($rows as $r) {
        $dept_code  = strtoupper($r['department_code'] ?? '');
        $program    = $dept_code ? 'BS' . $dept_code : '—';
        $time_range = fmt_time($r['start_time']) . ' - ' . fmt_time($r['end_time']);
        $duration   = duration_hours($r['start_time'], $r['end_time']);
        $day_name   = $day_names[(int)($r['day_of_week'] ?? 1)] ?? '';
        echo '<tr>
            <td><span class="badge-program">' . htmlspecialchars($program) . '</span></td>
            <td class="text-center">' . (int)$r['semester'] . '</td>
            <td class="text-center"><span class="badge-section">' . htmlspecialchars($r['section']) . '</span></td>
            <td>' . htmlspecialchars($r['session_name'] ?? '') . '</td>
            <td class="text-center">' . (int)($r['cht'] ?? 0) . '</td>
            <td class="text-center">' . (int)($r['chl'] ?? 0) . '</td>
            <td>' . htmlspecialchars($r['course_name']) . '</td>
            <td>' . htmlspecialchars($r['faculty_name']) . '</td>
            <td class="text-center">' . $duration . '</td>
            <td>' . htmlspecialchars($day_name) . '</td>
            <td>' . htmlspecialchars($time_range) . '</td>
            <td>' . htmlspecialchars($r['room_number']) . '</td>
            <td></td>
        </tr>';
    }
    echo '</tbody></table>';
    echo '<script>window.onload=function(){window.print();}</script>';
    echo '</body></html>';
    exit;
}

// ─── ICS ─────────────────────────────────────────────────────────────────────
if ($format === 'ics') {
    $byDay = [1=>'MO',2=>'TU',3=>'WE',4=>'TH',5=>'FR',6=>'SA',7=>'SU'];
    $icsEsc = function($s) { return str_replace(['\\',';',',',"\n"],['\\\\','\\;','\\,','\\n'],$s); };

    $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Timetable System//EN\r\nCALSCALE:GREGORIAN\r\n";
    foreach ($rows as $r) {
        $dow  = max(1, min(7, (int)($r['day_of_week'] ?? 1)));
        $st   = $r['start_time'] ?? '08:00:00';
        $et   = $r['end_time']   ?? '08:50:00';
        $startDate = !empty($r['session_start']) ? $r['session_start'] : date('Y-m-d');
        $endDate   = !empty($r['session_end'])   ? $r['session_end']   : date('Y-m-d', strtotime('+3 months'));
        $sd   = new DateTime($startDate); $sd->setTime(0,0,0);
        $cur  = (int)$sd->format('N');
        $diff = $dow - $cur; if ($diff < 0) $diff += 7;
        $sd->modify("+{$diff} days");
        $until  = (new DateTime($endDate))->format('Ymd');
        $dtS    = $sd->format('Ymd') . 'T' . substr(str_replace(':','',$st),0,6) . '00';
        $sdE    = clone $sd; $ep = explode(':',$et);
        $sdE->setTime((int)($ep[0]??8),(int)($ep[1]??50));
        $dtE    = $sdE->format('Ymd') . 'T' . substr(str_replace(':','',$et),0,6) . '00';
        $evSid  = (int)($r['academic_session_id'] ?? $session_id);
        $out .= "BEGIN:VEVENT\r\nUID:tt-{$evSid}-".uniqid()."@timetable\r\n";
        $out .= "DTSTART:{$dtS}\r\nDTEND:{$dtE}\r\n";
        $out .= "RRULE:FREQ=WEEKLY;BYDAY=" . $byDay[$dow] . ";UNTIL={$until}\r\n";
        $out .= "SUMMARY:" . $icsEsc($r['course_name']) . "\r\n";
        $out .= "LOCATION:" . $icsEsc($r['room_number']) . "\r\n";
        $out .= "DESCRIPTION:" . $icsEsc($r['faculty_name']) . "\r\nEND:VEVENT\r\n";
    }
    $out .= "END:VCALENDAR\r\n";
    header('Content-Type: text/calendar; charset=utf-8');
    $icsFn = ($faculty_all_sessions || $student_all_sessions) ? 'timetable_all_sessions' : 'timetable_' . $session_id;
    header('Content-Disposition: attachment; filename="' . $icsFn . '.ics"');
    echo $out;
    exit;
}
