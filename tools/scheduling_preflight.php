<?php
/**
 * Read-only scheduling preflight checks.
 *
 * Run:
 *   php tools/scheduling_preflight.php
 */

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/db.php';
require_once $baseDir . '/config/schema_helpers.php';

$failures = 0;

function line($status, $message) {
    echo '[' . $status . '] ' . $message . PHP_EOL;
}

function check_true($ok, $message) {
    global $failures;
    if ($ok) {
        line('OK', $message);
    } else {
        $failures++;
        line('FAIL', $message);
    }
}

function table_exists_readonly(mysqli $conn, string $table): bool {
    $tableLike = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$tableLike'");
    return $res && $res->num_rows > 0;
}

line('INFO', 'Scheduling preflight for database: ' . DB_NAME);

$requiredTables = ['degree', 'course_faculty_assignment', 'calendar_event', 'schedule_merge_member'];
foreach ($requiredTables as $table) {
    check_true(table_exists_readonly($conn, $table), 'table exists: ' . $table);
}

$requiredColumns = [
    'student' => ['academic_session_id', 'degree_id', 'degree', 'roll_no', 'is_frozen'],
    'faculty' => ['degree_id', 'faculty_type', 'visiting_day_of_week', 'visiting_start_time', 'visiting_end_time'],
    'course' => ['credit_hours_lab'],
    'time_slot' => ['slot_type'],
    'schedule' => ['is_merged_lecture'],
    'course_faculty_assignment' => ['preferred_day_of_week', 'preferred_start_time', 'preferred_end_time', 'room_id'],
];
foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        check_true(db_column_exists($conn, $table, $column), 'column exists: ' . $table . '.' . $column);
    }
}

$degreeCount = 0;
$res = $conn->query('SELECT COUNT(*) AS c FROM degree WHERE is_active = 1');
if ($res) $degreeCount = (int)($res->fetch_assoc()['c'] ?? 0);
check_true($degreeCount > 0, 'active degree rows available');

$labSlots = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM time_slot WHERE COALESCE(slot_type, 'lecture') = 'lab'");
if ($res) $labSlots = (int)($res->fetch_assoc()['c'] ?? 0);
check_true($labSlots > 0, 'lab time slots available');

$labRooms = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM room WHERE is_active = 1 AND LOWER(room_type) = 'lab'");
if ($res) $labRooms = (int)($res->fetch_assoc()['c'] ?? 0);
check_true($labRooms > 0, 'active lab rooms available');

$labCourses = 0;
$res = $conn->query('SELECT COUNT(*) AS c FROM course WHERE is_active = 1 AND COALESCE(credit_hours_lab, 0) > 0');
if ($res) $labCourses = (int)($res->fetch_assoc()['c'] ?? 0);
check_true($labCourses > 0, 'lab-bearing courses available');

$badManual = [];
$sql = "SELECT cfa.id, c.code, cfa.preferred_day_of_week, cfa.preferred_start_time, cfa.preferred_end_time
        FROM course_faculty_assignment cfa
        JOIN course c ON c.id = cfa.course_id
        LEFT JOIN time_slot ts ON ts.day_of_week = cfa.preferred_day_of_week
            AND ts.start_time = cfa.preferred_start_time
            AND ts.end_time = cfa.preferred_end_time
        WHERE cfa.preferred_day_of_week IS NOT NULL
          AND cfa.preferred_start_time IS NOT NULL
          AND cfa.preferred_end_time IS NOT NULL
          AND ts.id IS NULL
        LIMIT 10";
$res = $conn->query($sql);
while ($res && ($row = $res->fetch_assoc())) {
    $badManual[] = '#' . $row['id'] . ' ' . $row['code'] . ' ' . $row['preferred_day_of_week'] . ' ' . $row['preferred_start_time'] . '-' . $row['preferred_end_time'];
}
check_true(empty($badManual), 'manual assignment preferences map to existing time slots' . (empty($badManual) ? '' : ': ' . implode('; ', $badManual)));

$badMerge = [];
$sql = "SELECT s.id
        FROM schedule s
        WHERE COALESCE(s.is_merged_lecture, 0) = 1
          AND (SELECT COUNT(*) FROM schedule_merge_member m WHERE m.schedule_id = s.id) = 0
        LIMIT 10";
$res = $conn->query($sql);
while ($res && ($row = $res->fetch_assoc())) {
    $badMerge[] = (int)$row['id'];
}
check_true(empty($badMerge), 'merged schedule rows have member cohorts' . (empty($badMerge) ? '' : ': ' . implode(', ', $badMerge)));

if ($failures > 0) {
    line('INFO', $failures . ' preflight check(s) failed.');
    exit(1);
}

line('INFO', 'All scheduling preflight checks passed.');
