<?php
/**
 * One-off script: read DB and print summary (table counts + sample rows).
 * Run: php database/read_db.php
 */
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/db.php';

echo "Database: " . DB_NAME . "\n";
echo str_repeat('-', 50) . "\n";

$tables = ['department', 'student', 'faculty', 'course', 'room', 'academic_session', 'time_slot', 'schedule', 'enrollment'];
foreach ($tables as $t) {
    $r = @$conn->query("SELECT COUNT(*) AS n FROM `$t`");
    $n = $r && ($row = $r->fetch_assoc()) ? (int)$row['n'] : 0;
    echo sprintf("%-20s %d\n", $t . ':', $n);
}

echo "\nSample: department\n";
$res = $conn->query("SELECT id, code, name FROM department LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) echo "  " . $row['id'] . " | " . $row['code'] . " | " . $row['name'] . "\n";
}

echo "\nSample: student (first 5)\n";
$res = $conn->query("SELECT id, email, full_name, semester, section FROM student LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) echo "  " . $row['id'] . " | " . $row['email'] . " | " . $row['full_name'] . " | sem " . $row['semester'] . " " . $row['section'] . "\n";
}

$conn->close();
echo "\nDone.\n";
