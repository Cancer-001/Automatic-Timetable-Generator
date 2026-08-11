<?php
/**
 * One-time utility:
 * Assign exactly three active faculty members per active course
 * in assignmentupdated.course_faculty using round-robin distribution.
 *
 * Safe scope: touches only course_faculty table in current DB connection.
 */
require_once __DIR__ . '/../config/db.php';

if (!defined('DB_NAME') || DB_NAME !== 'assignmentupdated') {
    fwrite(STDERR, "Aborted: unexpected DB target.\n");
    exit(1);
}

$courses = [];
$res = $conn->query("SELECT id FROM course WHERE is_active = 1 ORDER BY id");
while ($row = $res->fetch_assoc()) {
    $courses[] = (int)$row['id'];
}

$faculty = [];
$res = $conn->query("SELECT id FROM faculty WHERE is_active = 1 ORDER BY id");
while ($row = $res->fetch_assoc()) {
    $faculty[] = (int)$row['id'];
}

if (count($courses) === 0) {
    fwrite(STDERR, "No active courses found.\n");
    exit(1);
}
if (count($faculty) < 3) {
    fwrite(STDERR, "Need at least 3 active faculty members.\n");
    exit(1);
}

$conn->begin_transaction();
try {
    $conn->query("DELETE FROM course_faculty");

    $ins = $conn->prepare("INSERT INTO course_faculty (course_id, faculty_id) VALUES (?, ?)");
    $inserted = 0;
    $n = count($faculty);

    foreach ($courses as $i => $courseId) {
        $f1 = $faculty[$i % $n];
        $f2 = $faculty[($i + 1) % $n];
        $f3 = $faculty[($i + 2) % $n];

        foreach ([$f1, $f2, $f3] as $fid) {
            $ins->bind_param('ii', $courseId, $fid);
            $ins->execute();
            $inserted++;
        }
    }

    $conn->commit();

    $check = $conn->query("
        SELECT COUNT(*) AS courses_total,
               SUM(cnt = 3) AS courses_with_three
        FROM (
            SELECT c.id, COUNT(cf.faculty_id) AS cnt
            FROM course c
            LEFT JOIN course_faculty cf ON cf.course_id = c.id
            WHERE c.is_active = 1
            GROUP BY c.id
        ) t
    ")->fetch_assoc();

    echo "Done.\n";
    echo "Active courses: " . count($courses) . "\n";
    echo "Active faculty: " . count($faculty) . "\n";
    echo "Rows inserted into course_faculty: " . $inserted . "\n";
    echo "Courses with exactly 3 faculty: " . (int)$check['courses_with_three'] . " / " . (int)$check['courses_total'] . "\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "Failed: " . $e->getMessage() . "\n");
    exit(1);
}
