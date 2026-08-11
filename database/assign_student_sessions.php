<?php
require_once __DIR__ . '/../config/db.php';

$sessions = [];
$res = $conn->query('SELECT id FROM academic_session ORDER BY start_date DESC, id DESC');
while ($row = $res->fetch_assoc()) {
    $sessions[] = (int)$row['id'];
}

if (empty($sessions)) {
    echo "No academic sessions found.\n";
    exit(1);
}

$students = [];
$res = $conn->query('SELECT id FROM student WHERE is_active = 1 ORDER BY id');
while ($row = $res->fetch_assoc()) {
    $students[] = (int)$row['id'];
}

if (empty($students)) {
    echo "No active students found.\n";
    exit(0);
}

$stmt = $conn->prepare('UPDATE student SET academic_session_id = ? WHERE id = ?');
$assigned = 0;
$sessionCount = count($sessions);
foreach ($students as $idx => $studentId) {
    $sessionId = $sessions[$idx % $sessionCount];
    $stmt->bind_param('ii', $sessionId, $studentId);
    if ($stmt->execute()) {
        $assigned += (int)$stmt->affected_rows;
    }
}

echo "Assigned sessions to active students.\n";
echo "Students processed: " . count($students) . "\n";
echo "Rows updated: " . $assigned . "\n";

$dist = $conn->query('SELECT academic_session_id, COUNT(*) AS c FROM student WHERE is_active = 1 GROUP BY academic_session_id ORDER BY academic_session_id');
while ($row = $dist->fetch_assoc()) {
    echo 'Session ' . (int)$row['academic_session_id'] . ': ' . (int)$row['c'] . "\n";
}
