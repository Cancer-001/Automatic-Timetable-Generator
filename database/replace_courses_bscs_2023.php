<?php
/**
 * Replace ALL courses with BSCS scheme 2023 (from HEC-style framework PDF).
 * Deletes existing courses (cascades schedule, course_faculty, enrollment, etc.).
 *
 * Run: php database/replace_courses_bscs_2023.php
 */
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/db.php';

$conn->set_charset('utf8mb4');

$r = $conn->query("SHOW TABLES LIKE 'course'");
if (!$r || $r->num_rows === 0) {
    die("Course table missing. Run install.php first.\n");
}

// Computer Science department
$code = 'CS';
$name = 'Computer Science';
$conn->query("INSERT IGNORE INTO department (code, name) VALUES ('CS', 'Computer Science')");
$st = $conn->prepare('SELECT id FROM department WHERE code = ? LIMIT 1');
$st->bind_param('s', $code);
$st->execute();
$drow = $st->get_result()->fetch_assoc();
$deptId = $drow ? (int)$drow['id'] : null;

// [semester, display name, total_credits, theory_hrs, lab_hrs]
$rows = [
    // Semester 1
    [1, 'Introduction to ICT', 4, 3, 1],
    [1, 'Calculus and Analytical Geometry', 3, 3, 0],
    [1, 'English Composition & Comprehension', 3, 3, 0],
    [1, 'Applied Physics', 4, 3, 1],
    [1, 'Islamic Studies', 2, 2, 0],
    [1, 'Pakistan Studies', 2, 2, 0],
    // Semester 2
    [2, 'Programming Fundamentals', 4, 3, 1],
    [2, 'Communication and Presentation Skills', 3, 3, 0],
    [2, 'Linear Algebra', 3, 3, 0],
    [2, 'Probability and Statistics', 3, 3, 0],
    [2, 'Basic Electronics', 4, 3, 1],
    // Semester 3
    [3, 'Object Oriented Programming', 4, 3, 1],
    [3, 'Discrete Structures', 3, 3, 0],
    [3, 'Professional Practices', 3, 3, 0],
    [3, 'Multivariable Calculus', 3, 3, 0],
    [3, 'Data Structures and Algorithms', 4, 3, 1],
    // Semester 4
    [4, 'Design and Analysis of Algorithms', 3, 3, 0],
    [4, 'Theory of Automata', 3, 3, 0],
    [4, 'Database Systems', 4, 3, 1],
    [4, 'Differential Equations', 3, 3, 0],
    [4, 'Computer Architecture and Assembly Language', 4, 3, 1],
    // Semester 5
    [5, 'Compiler Construction', 3, 3, 0],
    [5, 'Operating Systems', 4, 3, 1],
    [5, 'Software Engineering', 3, 3, 0],
    [5, 'Computer Networks', 4, 3, 1],
    [5, 'Technical and Business Writing', 3, 3, 0],
    // Semester 6
    [6, 'Artificial Intelligence', 4, 3, 1],
    [6, 'Web Technologies', 3, 2, 1],
    [6, 'Information Security', 3, 3, 0],
    [6, 'Elective-I', 3, 3, 0],
    [6, 'Elective-II', 3, 3, 0],
    // Semester 7
    [7, 'Final Year Project-I', 3, 0, 3],
    [7, 'Human Computer Interaction', 3, 3, 0],
    [7, 'Elective-III', 3, 3, 0],
    [7, 'Elective-IV', 3, 3, 0],
    [7, 'Introduction to Management', 3, 3, 0],
    // Semester 8
    [8, 'Final Year Project-II', 3, 0, 3],
    [8, 'Elective-V', 3, 3, 0],
    [8, 'Organizational Behavior', 3, 3, 0],
    [8, 'Civic and Community Engagement', 2, 2, 0],
];

$conn->begin_transaction();

try {
    // Clear links first (MySQL may allow CASCADE from course delete, but be explicit for older setups)
    $conn->query('DELETE FROM course_faculty');
    $conn->query('DELETE FROM enrollment');
    $conn->query('DELETE FROM substitution_request');
    $conn->query('DELETE FROM schedule');
    $conn->query('DELETE FROM course');

    $ins = $conn->prepare(
        'INSERT INTO course (code, name, credit_hours, credit_hours_lab, semester, department_id, sessions_per_week, is_active, prerequisite_course_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, NULL)'
    );

    $perSemCounter = [];
    foreach ($rows as $rec) {
        [$sem, $cname, $totalCr, $th, $lh] = $rec;
        $perSemCounter[$sem] = ($perSemCounter[$sem] ?? 0) + 1;
        $n = $perSemCounter[$sem];
        $courseCode = sprintf('BSCS%d%02d', $sem, $n);

        // Generator treats course as lab if credit_hours_lab > 0; use only for pure lab-contact rows (theory = 0)
        $labCr = ($th === 0 && $lh > 0) ? 1 : 0;

        // Weekly session targets: roughly match credit load (cap for timetable)
        $sessions = min(6, max(1, $totalCr));

        $ins->bind_param(
            'ssiiiii',
            $courseCode,
            $cname,
            $totalCr,
            $labCr,
            $sem,
            $deptId,
            $sessions
        );
        if (!$ins->execute()) {
            throw new RuntimeException('Insert failed: ' . $conn->error);
        }
    }

    $conn->commit();
    echo 'OK: Replaced all courses with BSCS 2023 scheme. Count: ' . count($rows) . ".\n";
    echo "Department: Computer Science (CS). Codes: BSCS{semester}{01..}.\n";
} catch (Throwable $e) {
    $conn->rollback();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
