<?php

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/db.php';

$mode = $argv[1] ?? 'setup';
$prefix = 'VERIFYTMP';

function find_id(mysqli $conn, string $sql) {
    $res = $conn->query($sql);
    if (!$res) {
        return null;
    }
    $row = $res->fetch_assoc();
    return $row ? (int)array_values($row)[0] : null;
}

function delete_by_query(mysqli $conn, string $sql): void {
    $conn->query($sql);
}

if ($mode === 'cleanup') {
    $courseId = find_id($conn, "SELECT id FROM course WHERE code = 'VERIFYTMP-CS101' LIMIT 1");
    $otherCourseId = find_id($conn, "SELECT id FROM course WHERE code = 'VERIFYTMP-OTHER101' LIMIT 1");
    $sessionId = find_id($conn, "SELECT id FROM academic_session WHERE name = 'VERIFYTMP Session' LIMIT 1");
    $facultyA = find_id($conn, "SELECT id FROM faculty WHERE email = 'verifytmp.faculty.a@example.test' LIMIT 1");
    $facultyB = find_id($conn, "SELECT id FROM faculty WHERE email = 'verifytmp.faculty.b@example.test' LIMIT 1");
    $roomId = find_id($conn, "SELECT id FROM room WHERE room_number = 'VERIFYTMP-ROOM' LIMIT 1");
    $degreeId = find_id($conn, "SELECT id FROM degree WHERE name = 'VERIFYTMP Computer Science' LIMIT 1");

    if ($courseId) {
        delete_by_query($conn, "DELETE FROM schedule WHERE course_id = $courseId");
        delete_by_query($conn, "DELETE FROM course_faculty_assignment WHERE course_id = $courseId");
        delete_by_query($conn, "DELETE FROM course_faculty WHERE course_id = $courseId");
        delete_by_query($conn, "DELETE FROM course WHERE id = $courseId");
    }
    if ($otherCourseId) {
        delete_by_query($conn, "DELETE FROM schedule WHERE course_id = $otherCourseId");
        delete_by_query($conn, "DELETE FROM course_faculty_assignment WHERE course_id = $otherCourseId");
        delete_by_query($conn, "DELETE FROM course_faculty WHERE course_id = $otherCourseId");
        delete_by_query($conn, "DELETE FROM course WHERE id = $otherCourseId");
    }
    if ($facultyA) {
        delete_by_query($conn, "DELETE FROM faculty WHERE id = $facultyA");
    }
    if ($facultyB) {
        delete_by_query($conn, "DELETE FROM faculty WHERE id = $facultyB");
    }
    if ($roomId) {
        delete_by_query($conn, "DELETE FROM room WHERE id = $roomId");
    }
    if ($sessionId) {
        delete_by_query($conn, "DELETE FROM academic_session WHERE id = $sessionId");
    }
    if ($degreeId) {
        delete_by_query($conn, "DELETE FROM degree WHERE id = $degreeId");
    }

    echo json_encode(['success' => true, 'mode' => 'cleanup']) . PHP_EOL;
    exit;
}

$csDept = find_id($conn, "SELECT id FROM department WHERE code = 'CS' LIMIT 1");
$otherDept = find_id($conn, "SELECT id FROM department WHERE code = 'IT' LIMIT 1");
$studentId = find_id($conn, "SELECT id FROM student WHERE email = 'student1@seed.edu' LIMIT 1");
$student = $conn->query("SELECT id, semester, section, department_id FROM student WHERE id = $studentId LIMIT 1")->fetch_assoc();

if (!$csDept || !$otherDept || !$studentId || !$student) {
    echo json_encode(['success' => false, 'message' => 'Seed data missing for verification']) . PHP_EOL;
    exit(1);
}

delete_by_query($conn, "DELETE FROM course_faculty_assignment WHERE course_id IN (SELECT id FROM course WHERE code IN ('VERIFYTMP-CS101','VERIFYTMP-OTHER101'))");
delete_by_query($conn, "DELETE FROM course_faculty WHERE course_id IN (SELECT id FROM course WHERE code IN ('VERIFYTMP-CS101','VERIFYTMP-OTHER101'))");
delete_by_query($conn, "DELETE FROM schedule WHERE course_id IN (SELECT id FROM course WHERE code IN ('VERIFYTMP-CS101','VERIFYTMP-OTHER101'))");
delete_by_query($conn, "DELETE FROM course WHERE code IN ('VERIFYTMP-CS101','VERIFYTMP-OTHER101')");
delete_by_query($conn, "DELETE FROM faculty WHERE email IN ('verifytmp.faculty.a@example.test','verifytmp.faculty.b@example.test')");
delete_by_query($conn, "DELETE FROM room WHERE room_number = 'VERIFYTMP-ROOM'");
delete_by_query($conn, "DELETE FROM academic_session WHERE name = 'VERIFYTMP Session'");
delete_by_query($conn, "DELETE FROM degree WHERE name = 'VERIFYTMP Computer Science'");

$stmt = $conn->prepare("INSERT INTO degree (code, name) VALUES (?, ?)");
$code = 'BSCS';
$name = 'VERIFYTMP Computer Science';
$stmt->bind_param('ss', $code, $name);
$stmt->execute();
$degreeId = (int)$conn->insert_id;

$stmt = $conn->prepare("INSERT INTO academic_session (name, start_date, end_date, is_active) VALUES (?, ?, ?, 1)");
$sessionName = 'VERIFYTMP Session';
$startDate = '2026-04-01';
$endDate = '2026-05-31';
$stmt->bind_param('sss', $sessionName, $startDate, $endDate);
$stmt->execute();
$sessionId = (int)$conn->insert_id;

$hash = password_hash('faculty123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO faculty (email, password_hash, full_name, department_id, is_active) VALUES (?, ?, ?, ?, 1)");
$email = 'verifytmp.faculty.a@example.test';
$fullName = 'Verify Faculty A';
$stmt->bind_param('sssi', $email, $hash, $fullName, $csDept);
$stmt->execute();
$facultyA = (int)$conn->insert_id;

$email = 'verifytmp.faculty.b@example.test';
$fullName = 'Verify Faculty B';
$stmt->bind_param('sssi', $email, $hash, $fullName, $csDept);
$stmt->execute();
$facultyB = (int)$conn->insert_id;

$stmt = $conn->prepare("INSERT INTO room (room_number, capacity, room_type, is_active) VALUES (?, 40, 'classroom', 1)");
$roomNumber = 'VERIFYTMP-ROOM';
$stmt->bind_param('s', $roomNumber);
$stmt->execute();
$roomId = (int)$conn->insert_id;

$stmt = $conn->prepare("INSERT INTO course (code, name, credit_hours, credit_hours_lab, semester, department_id, sessions_per_week, is_active) VALUES (?, ?, 2, 0, ?, ?, 1, 1)");
$courseCode = 'VERIFYTMP-CS101';
$courseName = 'Verify CS Course';
$semester = (int)$student['semester'];
$stmt->bind_param('ssii', $courseCode, $courseName, $semester, $csDept);
$stmt->execute();
$courseId = (int)$conn->insert_id;

$courseCode = 'VERIFYTMP-OTHER101';
$courseName = 'Verify Other Dept Course';
$stmt->bind_param('ssii', $courseCode, $courseName, $semester, $otherDept);
$stmt->execute();
$otherCourseId = (int)$conn->insert_id;

$stmt = $conn->prepare("INSERT INTO course_faculty (course_id, faculty_id) VALUES (?, ?)");
$stmt->bind_param('ii', $courseId, $facultyA);
$stmt->execute();
$stmt->bind_param('ii', $courseId, $facultyB);
$stmt->execute();
$stmt->bind_param('ii', $otherCourseId, $facultyB);
$stmt->execute();

$section = (string)$student['section'];
$stmt = $conn->prepare("INSERT INTO course_faculty_assignment (course_id, faculty_id, academic_session_id, degree_id, section, preferred_day_of_week, preferred_start_time, preferred_end_time, room_id) VALUES (?, ?, ?, ?, ?, 1, '08:00:00', '09:30:00', ?)");
$stmt->bind_param('iiiisi', $courseId, $facultyA, $sessionId, $degreeId, $section, $roomId);
$stmt->execute();

$out = [
    'success' => true,
    'mode' => 'setup',
    'student_id' => $studentId,
    'student_department_id' => (int)$student['department_id'],
    'student_semester' => (int)$student['semester'],
    'student_section' => (string)$student['section'],
    'session_id' => $sessionId,
    'degree_id' => $degreeId,
    'course_id' => $courseId,
    'other_course_id' => $otherCourseId,
    'faculty_a' => $facultyA,
    'faculty_b' => $facultyB,
    'room_id' => $roomId,
];

echo json_encode($out) . PHP_EOL;
