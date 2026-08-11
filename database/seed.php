<?php
/**
 * Seed sample data: multiple departments, students, faculty, courses, rooms, sessions.
 * Run via RUN_SEEDS.bat or: php database/seed.php
 */
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/db.php';
require_once $baseDir . '/config/schema_helpers.php';

echo "Seeding database...\n";

$conn->set_charset('utf8mb4');

// Check tables exist
$r = $conn->query("SHOW TABLES LIKE 'department'");
if (!$r || $r->num_rows === 0) {
    die("Run install.php first to create the database and tables.\n");
}

$defaultPass = password_hash('student123', PASSWORD_DEFAULT);

// --- Degrees (latest module support) ---
$conn->query("CREATE TABLE IF NOT EXISTS degree (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(128) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
db_add_column_if_missing($conn, 'faculty', 'degree_id', 'INT UNSIGNED DEFAULT NULL AFTER department_id');
db_add_column_if_missing($conn, 'faculty', 'faculty_type', "ENUM('permanent','visiting') NOT NULL DEFAULT 'permanent' AFTER full_name");
db_add_column_if_missing($conn, 'faculty', 'visiting_day_of_week', 'TINYINT UNSIGNED DEFAULT NULL AFTER faculty_type');
db_add_column_if_missing($conn, 'faculty', 'visiting_start_time', 'TIME DEFAULT NULL AFTER visiting_day_of_week');
db_add_column_if_missing($conn, 'faculty', 'visiting_end_time', 'TIME DEFAULT NULL AFTER visiting_start_time');
db_add_column_if_missing($conn, 'student', 'degree_id', 'INT UNSIGNED DEFAULT NULL AFTER department_id');
db_add_column_if_missing($conn, 'student', 'academic_session_id', 'INT UNSIGNED DEFAULT NULL AFTER department_id');

$degrees = [
    ['code' => 'BS', 'name' => 'Bachelor of Science'],
    ['code' => 'BBA', 'name' => 'Bachelor of Business Administration'],
];
$stmt = $conn->prepare('INSERT IGNORE INTO degree (code, name) VALUES (?, ?)');
foreach ($degrees as $deg) {
    $stmt->bind_param('ss', $deg['code'], $deg['name']);
    $stmt->execute();
}
$degreeIds = [];
$res = $conn->query('SELECT id, code FROM degree WHERE is_active = 1');
while ($row = $res->fetch_assoc()) $degreeIds[strtoupper($row['code'])] = (int)$row['id'];
echo "Degrees: " . count($degrees) . "\n";

// --- Departments (insert if code not exists) ---
$departments = [
    ['code' => 'CS', 'name' => 'Computer Science'],
    ['code' => 'IT', 'name' => 'Information Technology'],
    ['code' => 'EE', 'name' => 'Electrical Engineering'],
    ['code' => 'BBA', 'name' => 'Business Administration'],
    ['code' => 'MATH', 'name' => 'Mathematics'],
    ['code' => 'PHYS', 'name' => 'Physics'],
    ['code' => 'ENG', 'name' => 'English'],
    ['code' => 'CHEM', 'name' => 'Chemistry'],
];
$stmt = $conn->prepare('INSERT IGNORE INTO department (code, name) VALUES (?, ?)');
foreach ($departments as $d) {
    $stmt->bind_param('ss', $d['code'], $d['name']);
    $stmt->execute();
}
echo "Departments: " . count($departments) . "\n";

// Get department ids for linking (code -> id)
$deptIds = [];
$res = $conn->query('SELECT id, code FROM department');
while ($row = $res->fetch_assoc()) $deptIds[$row['code']] = (int)$row['id'];

// --- Students ---
// Alignment: (1) Min 5 students per department (CS, IT, EE, BBA, MATH, PHYS, ENG, CHEM).
//            (2) At least one student per department in semester 2 section A so "My Timetable"
//                shows results when admin generates for Semester 2, Section A (e.g. Spring 2026).
$students = [
    ['email' => 'student1@seed.edu', 'name' => 'Ali Ahmed', 'dept' => 'CS', 'sem' => 2, 'sec' => 'A'],
    ['email' => 'student2@seed.edu', 'name' => 'Sara Khan', 'dept' => 'CS', 'sem' => 1, 'sec' => 'A'],
    ['email' => 'student3@seed.edu', 'name' => 'Omar Hassan', 'dept' => 'IT', 'sem' => 2, 'sec' => 'B'],
    ['email' => 'student4@seed.edu', 'name' => 'Fatima Ali', 'dept' => 'IT', 'sem' => 2, 'sec' => 'B'],
    ['email' => 'student5@seed.edu', 'name' => 'Hassan Raza', 'dept' => 'EE', 'sem' => 2, 'sec' => 'A'],
    ['email' => 'student6@seed.edu', 'name' => 'Ayesha Malik', 'dept' => 'EE', 'sem' => 3, 'sec' => 'A'],
    ['email' => 'student7@seed.edu', 'name' => 'Usman Farooq', 'dept' => 'BBA', 'sem' => 1, 'sec' => 'A'],
    ['email' => 'student8@seed.edu', 'name' => 'Zainab Hussain', 'dept' => 'BBA', 'sem' => 2, 'sec' => 'B'],
    ['email' => 'student9@seed.edu', 'name' => 'Ibrahim Shah', 'dept' => 'MATH', 'sem' => 1, 'sec' => 'A'],
    ['email' => 'student10@seed.edu', 'name' => 'Maryam Noor', 'dept' => 'MATH', 'sem' => 2, 'sec' => 'A'],
    ['email' => 'student11@seed.edu', 'name' => 'Bilal Tariq', 'dept' => 'CS', 'sem' => 3, 'sec' => 'B'],
    ['email' => 'student12@seed.edu', 'name' => 'Hina Javed', 'dept' => 'CS', 'sem' => 4, 'sec' => 'A'],
    ['email' => 'student13@seed.edu', 'name' => 'Kamran Siddiq', 'dept' => 'IT', 'sem' => 3, 'sec' => 'A'],
    ['email' => 'student14@seed.edu', 'name' => 'Nimra Yousaf', 'dept' => 'IT', 'sem' => 4, 'sec' => 'B'],
    ['email' => 'student15@seed.edu', 'name' => 'Saad Ali', 'dept' => 'EE', 'sem' => 2, 'sec' => 'B'],
    ['email' => 'student16@seed.edu', 'name' => 'Anum Sheikh', 'dept' => 'BBA', 'sem' => 3, 'sec' => 'A'],
    ['email' => 'student17@seed.edu', 'name' => 'Hamza Iqbal', 'dept' => 'PHYS', 'sem' => 1, 'sec' => 'A'],
    ['email' => 'student18@seed.edu', 'name' => 'Laiba Aslam', 'dept' => 'PHYS', 'sem' => 2, 'sec' => 'A'],
    ['email' => 'student19@seed.edu', 'name' => 'Owais Khan', 'dept' => 'CHEM', 'sem' => 1, 'sec' => 'B'],
    ['email' => 'student20@seed.edu', 'name' => 'Sadia Imran', 'dept' => 'ENG', 'sem' => 2, 'sec' => 'A'],
    ['email' => 'student21@seed.edu', 'name' => 'Rashid Mahmood', 'dept' => 'CS', 'sem' => 2, 'sec' => 'B'],
    ['email' => 'student22@seed.edu', 'name' => 'Amina Farooq', 'dept' => 'CS', 'sem' => 3, 'sec' => 'A'],
    ['email' => 'student23@seed.edu', 'name' => 'Tariq Hussain', 'dept' => 'IT', 'sem' => 1, 'sec' => 'A'],
    ['email' => 'student24@seed.edu', 'name' => 'Zara Akhtar', 'dept' => 'IT', 'sem' => 4, 'sec' => 'B'],
    ['email' => 'student25@seed.edu', 'name' => 'Faisal Rashid', 'dept' => 'EE', 'sem' => 1, 'sec' => 'B'],
    ['email' => 'student26@seed.edu', 'name' => 'Sana Mahmood', 'dept' => 'EE', 'sem' => 4, 'sec' => 'A'],
    ['email' => 'student27@seed.edu', 'name' => 'Waqas Ahmed', 'dept' => 'BBA', 'sem' => 2, 'sec' => 'A'],
    ['email' => 'student28@seed.edu', 'name' => 'Hira Khan', 'dept' => 'BBA', 'sem' => 4, 'sec' => 'B'],
    ['email' => 'student29@seed.edu', 'name' => 'Yusuf Ali', 'dept' => 'MATH', 'sem' => 3, 'sec' => 'B'],
    ['email' => 'student30@seed.edu', 'name' => 'Mariam Saleem', 'dept' => 'MATH', 'sem' => 4, 'sec' => 'A'],
    ['email' => 'student31@seed.edu', 'name' => 'Asad Iqbal', 'dept' => 'PHYS', 'sem' => 3, 'sec' => 'A'],
    ['email' => 'student32@seed.edu', 'name' => 'Nadia Sheikh', 'dept' => 'PHYS', 'sem' => 4, 'sec' => 'B'],
    ['email' => 'student33@seed.edu', 'name' => 'Khalid Jamil', 'dept' => 'CHEM', 'sem' => 2, 'sec' => 'A'],
    ['email' => 'student34@seed.edu', 'name' => 'Saima Raza', 'dept' => 'CHEM', 'sem' => 3, 'sec' => 'B'],
    ['email' => 'student35@seed.edu', 'name' => 'Imran Malik', 'dept' => 'ENG', 'sem' => 3, 'sec' => 'A'],
    ['email' => 'student36@seed.edu', 'name' => 'Aisha Noor', 'dept' => 'ENG', 'sem' => 4, 'sec' => 'B'],
    ['email' => 'student37@seed.edu', 'name' => 'Danish Khan', 'dept' => 'CHEM', 'sem' => 1, 'sec' => 'C'],
    ['email' => 'student38@seed.edu', 'name' => 'Hafsa Tariq', 'dept' => 'ENG', 'sem' => 4, 'sec' => 'B'],
    ['email' => 'student39@seed.edu', 'name' => 'Junaid Abbas', 'dept' => 'IT', 'sem' => 2, 'sec' => 'A'],
    ['email' => 'student40@seed.edu', 'name' => 'Rabia Hassan', 'dept' => 'IT', 'sem' => 3, 'sec' => 'B'],
    ['email' => 'student41@seed.edu', 'name' => 'Arslan Farooq', 'dept' => 'EE', 'sem' => 3, 'sec' => 'A'],
    ['email' => 'student42@seed.edu', 'name' => 'Saba Yousaf', 'dept' => 'EE', 'sem' => 2, 'sec' => 'B'],
    ['email' => 'student43@seed.edu', 'name' => 'Zeeshan Ahmed', 'dept' => 'BBA', 'sem' => 1, 'sec' => 'B'],
    ['email' => 'student44@seed.edu', 'name' => 'Fizza Malik', 'dept' => 'BBA', 'sem' => 4, 'sec' => 'A'],
    ['email' => 'student45@seed.edu', 'name' => 'Haris Siddiq', 'dept' => 'MATH', 'sem' => 1, 'sec' => 'B'],
    ['email' => 'student46@seed.edu', 'name' => 'Ayesha Riaz', 'dept' => 'MATH', 'sem' => 2, 'sec' => 'C'],
    ['email' => 'student47@seed.edu', 'name' => 'Bilal Qureshi', 'dept' => 'PHYS', 'sem' => 3, 'sec' => 'B'],
    ['email' => 'student48@seed.edu', 'name' => 'Sanaullah Khan', 'dept' => 'CHEM', 'sem' => 4, 'sec' => 'A'],
    ['email' => 'student49@seed.edu', 'name' => 'Hina Bashir', 'dept' => 'ENG', 'sem' => 1, 'sec' => 'C'],
    ['email' => 'student50@seed.edu', 'name' => 'Usman Ghani', 'dept' => 'CS', 'sem' => 2, 'sec' => 'C'],
];
$studentDefaultDegreeId = $degreeIds['BS'] ?? null;
$stmt = $conn->prepare('INSERT IGNORE INTO student (email, password_hash, full_name, department_id, degree_id, semester, section) VALUES (?, ?, ?, ?, ?, ?, ?)');
foreach ($students as $s) {
    $deptId = isset($deptIds[$s['dept']]) ? $deptIds[$s['dept']] : null;
    $stmt->bind_param('ssssiis', $s['email'], $defaultPass, $s['name'], $deptId, $studentDefaultDegreeId, $s['sem'], $s['sec']);
    $stmt->execute();
}
echo "Students: " . count($students) . " (password: student123)\n";
// Verify alignment: min 5 per department
$byDept = [];
foreach ($students as $s) {
    $d = $s['dept'];
    $byDept[$d] = ($byDept[$d] ?? 0) + 1;
}
foreach (array_keys($deptIds) as $code) {
    $n = $byDept[$code] ?? 0;
    if ($n < 5) echo "  WARNING: $code has $n students (min 5).\n";
    else echo "  $code: $n students\n";
}

// --- Faculty (CS Department - 36 members from facultycs.xlsx) ---
$facultyPass = password_hash('faculty123', PASSWORD_DEFAULT);
$faculty = [
    ['email' => 'dr.ansar.munir@cs.edu.pk', 'name' => 'Dr Ansar Munir', 'dept' => 'CS'],
    ['email' => 'imran.ali@cs.edu.pk', 'name' => 'Imran Ali', 'dept' => 'CS'],
    ['email' => 'nasir.hussain@cs.edu.pk', 'name' => 'Nasir Hussain', 'dept' => 'CS'],
    ['email' => 'aqsa.altaf@cs.edu.pk', 'name' => 'Aqsa Altaf', 'dept' => 'CS'],
    ['email' => 'zeeshan.ali@cs.edu.pk', 'name' => 'Zeeshan Ali', 'dept' => 'CS'],
    ['email' => 'arfa.tariq@cs.edu.pk', 'name' => 'Arfa Tariq', 'dept' => 'CS'],
    ['email' => 'fizzah.ishtiaq@cs.edu.pk', 'name' => 'Fizzah Ishtiaq', 'dept' => 'CS'],
    ['email' => 'bisma.imran@cs.edu.pk', 'name' => 'Bisma Imran', 'dept' => 'CS'],
    ['email' => 'ahmad.hamza@cs.edu.pk', 'name' => 'Ahmad Hamza', 'dept' => 'CS'],
    ['email' => 'dr.muhammad.moavia@cs.edu.pk', 'name' => 'Dr. Muhammad Moavia', 'dept' => 'CS'],
    ['email' => 'namra.shamin@cs.edu.pk', 'name' => 'Namra Shamin', 'dept' => 'CS'],
    ['email' => 'hadia.rehan@cs.edu.pk', 'name' => 'Hadia Rehan', 'dept' => 'CS'],
    ['email' => 'kainat.sajjad@cs.edu.pk', 'name' => 'Kainat Sajjad', 'dept' => 'CS'],
    ['email' => 'sundas.fida.hussain@cs.edu.pk', 'name' => 'Sundas Fida Hussain', 'dept' => 'CS'],
    ['email' => 'mohsin.raza@cs.edu.pk', 'name' => 'Mohsin Raza', 'dept' => 'CS'],
    ['email' => 'arbab.khan@cs.edu.pk', 'name' => 'Arbab Khan', 'dept' => 'CS'],
    ['email' => 'sonia.jamil@cs.edu.pk', 'name' => 'Sonia Jamil', 'dept' => 'CS'],
    ['email' => 'abdul.basit@cs.edu.pk', 'name' => 'Abdul Basit', 'dept' => 'CS'],
    ['email' => 'majid.khawar@cs.edu.pk', 'name' => 'Majid Khawar', 'dept' => 'CS'],
    ['email' => 'dr.zia.ur.rehman@cs.edu.pk', 'name' => 'Dr. Zia ur Rehman', 'dept' => 'CS'],
    ['email' => 'zohair.haider@cs.edu.pk', 'name' => 'Zohair Haider', 'dept' => 'CS'],
    ['email' => 'sana.fatima@cs.edu.pk', 'name' => 'Sana Fatima', 'dept' => 'CS'],
    ['email' => 'shakab.ahmad@cs.edu.pk', 'name' => 'Shakab Ahmad', 'dept' => 'CS'],
    ['email' => 'qasim.niaz@cs.edu.pk', 'name' => 'Qasim Niaz', 'dept' => 'CS'],
    ['email' => 'dr.abdullah.shah@cs.edu.pk', 'name' => 'Dr. Abdullah Shah', 'dept' => 'CS'],
    ['email' => 'ans.khalid@cs.edu.pk', 'name' => 'Ans Khalid', 'dept' => 'CS'],
    ['email' => 'saleh.rehman@cs.edu.pk', 'name' => 'Saleh Rehman', 'dept' => 'CS'],
    ['email' => 'parveen.bano@cs.edu.pk', 'name' => 'Parveen Bano', 'dept' => 'CS'],
    ['email' => 'muhammad.arslan@cs.edu.pk', 'name' => 'Muhammad Arslan', 'dept' => 'CS'],
    ['email' => 'qandeel.asghar@cs.edu.pk', 'name' => 'Qandeel Asghar', 'dept' => 'CS'],
    ['email' => 'muhammad.abrar@cs.edu.pk', 'name' => 'Muhammad Abrar', 'dept' => 'CS'],
    ['email' => 'dr.mishal@cs.edu.pk', 'name' => 'Dr. Mishal', 'dept' => 'CS'],
    ['email' => 'allah.rakha@cs.edu.pk', 'name' => 'Allah Rakha', 'dept' => 'CS'],
    ['email' => 'hafiz.muhammad.ejaz@cs.edu.pk', 'name' => 'Hafiz Muhammad Ejaz', 'dept' => 'CS'],
    ['email' => 'safia.sultana@cs.edu.pk', 'name' => 'Safia Sultana', 'dept' => 'CS'],
    ['email' => 'azka.fatima@cs.edu.pk', 'name' => 'Azka Fatima', 'dept' => 'CS'],
];
$facultyDefaultDegreeId = $degreeIds['BS'] ?? null;
$stmt = $conn->prepare('INSERT IGNORE INTO faculty (email, password_hash, full_name, department_id, degree_id) VALUES (?, ?, ?, ?, ?)');
foreach ($faculty as $f) {
    $deptId = isset($deptIds[$f['dept']]) ? $deptIds[$f['dept']] : null;
    $stmt->bind_param('sssii', $f['email'], $facultyPass, $f['name'], $deptId, $facultyDefaultDegreeId);
    $stmt->execute();
}
echo "Faculty: " . count($faculty) . " (password: faculty123)\n";

// --- Courses (aligned to provided semester catalog and credit split) ---
$courses = [
    // Semester 1
    ['code' => 'BSCS101', 'name' => 'Introduction to ICT', 'dept' => 'CS', 'sem' => 1, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => null],
    ['code' => 'BSCS102', 'name' => 'Calculus and Analytical Geometry', 'dept' => 'CS', 'sem' => 1, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS103', 'name' => 'English Composition & Comprehension', 'dept' => 'CS', 'sem' => 1, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS104', 'name' => 'Applied Physics', 'dept' => 'CS', 'sem' => 1, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => null],
    ['code' => 'BSCS105', 'name' => 'Islamic Studies', 'dept' => 'CS', 'sem' => 1, 'credit' => 2, 'theory' => 2, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS106', 'name' => 'Pakistan Studies', 'dept' => 'CS', 'sem' => 1, 'credit' => 2, 'theory' => 2, 'lab' => 0, 'prereq' => null],
    // Semester 2
    ['code' => 'BSCS201', 'name' => 'Programming Fundamentals', 'dept' => 'CS', 'sem' => 2, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => null],
    ['code' => 'BSCS202', 'name' => 'Communication and Presentation Skills', 'dept' => 'CS', 'sem' => 2, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS203', 'name' => 'Linear Algebra', 'dept' => 'CS', 'sem' => 2, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS204', 'name' => 'Probability and Statistics', 'dept' => 'CS', 'sem' => 2, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS205', 'name' => 'Basic Electronics', 'dept' => 'CS', 'sem' => 2, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => null],
    // Semester 3
    ['code' => 'BSCS301', 'name' => 'Object Oriented Programming', 'dept' => 'CS', 'sem' => 3, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => 'BSCS201'],
    ['code' => 'BSCS302', 'name' => 'Discrete Structures', 'dept' => 'CS', 'sem' => 3, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS303', 'name' => 'Professional Practices', 'dept' => 'CS', 'sem' => 3, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS304', 'name' => 'Multivariable Calculus', 'dept' => 'CS', 'sem' => 3, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS305', 'name' => 'Data Structures and Algorithms', 'dept' => 'CS', 'sem' => 3, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => 'BSCS301'],
    // Semester 4
    ['code' => 'BSCS401', 'name' => 'Design and Analysis of Algorithms', 'dept' => 'CS', 'sem' => 4, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => 'BSCS305'],
    ['code' => 'BSCS402', 'name' => 'Theory of Automata', 'dept' => 'CS', 'sem' => 4, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS403', 'name' => 'Database Systems', 'dept' => 'CS', 'sem' => 4, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => 'BSCS301'],
    ['code' => 'BSCS404', 'name' => 'Differential Equations', 'dept' => 'CS', 'sem' => 4, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS405', 'name' => 'Computer Architecture and Assembly Language', 'dept' => 'CS', 'sem' => 4, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => null],
    // Semester 5
    ['code' => 'BSCS501', 'name' => 'Compiler Construction', 'dept' => 'CS', 'sem' => 5, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS502', 'name' => 'Operating Systems', 'dept' => 'CS', 'sem' => 5, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => null],
    ['code' => 'BSCS503', 'name' => 'Software Engineering', 'dept' => 'CS', 'sem' => 5, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS504', 'name' => 'Computer Networks', 'dept' => 'CS', 'sem' => 5, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => null],
    ['code' => 'BSCS505', 'name' => 'Technical and Business Writing', 'dept' => 'CS', 'sem' => 5, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    // Semester 6
    ['code' => 'BSCS601', 'name' => 'Artificial Intelligence', 'dept' => 'CS', 'sem' => 6, 'credit' => 4, 'theory' => 3, 'lab' => 1, 'prereq' => null],
    ['code' => 'BSCS602', 'name' => 'Web Technologies', 'dept' => 'CS', 'sem' => 6, 'credit' => 3, 'theory' => 2, 'lab' => 1, 'prereq' => null],
    ['code' => 'BSCS603', 'name' => 'Information Security', 'dept' => 'CS', 'sem' => 6, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS604', 'name' => 'Elective-I', 'dept' => 'CS', 'sem' => 6, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS605', 'name' => 'Elective-II', 'dept' => 'CS', 'sem' => 6, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    // Semester 7
    ['code' => 'BSCS701', 'name' => 'Final Year Project-I', 'dept' => 'CS', 'sem' => 7, 'credit' => 3, 'theory' => 0, 'lab' => 3, 'prereq' => null],
    ['code' => 'BSCS702', 'name' => 'Human Computer Interaction', 'dept' => 'CS', 'sem' => 7, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS703', 'name' => 'Elective-III', 'dept' => 'CS', 'sem' => 7, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS704', 'name' => 'Elective-IV', 'dept' => 'CS', 'sem' => 7, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS705', 'name' => 'Introduction to Management', 'dept' => 'CS', 'sem' => 7, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    // Semester 8
    ['code' => 'BSCS801', 'name' => 'Final Year Project-II', 'dept' => 'CS', 'sem' => 8, 'credit' => 3, 'theory' => 0, 'lab' => 3, 'prereq' => 'BSCS701'],
    ['code' => 'BSCS802', 'name' => 'Elective-V', 'dept' => 'CS', 'sem' => 8, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS803', 'name' => 'Organizational Behavior', 'dept' => 'CS', 'sem' => 8, 'credit' => 3, 'theory' => 3, 'lab' => 0, 'prereq' => null],
    ['code' => 'BSCS804', 'name' => 'Civic and Community Engagement', 'dept' => 'CS', 'sem' => 8, 'credit' => 2, 'theory' => 2, 'lab' => 0, 'prereq' => null],
];
$isActive = 1;
$firstDeptId = !empty($deptIds) ? (int)reset($deptIds) : null;
if (!$firstDeptId) {
    die("No departments found. Seed departments first.\n");
}
// Insert all courses first (prerequisite_course_id = NULL)
$stmt = $conn->prepare('INSERT IGNORE INTO course (code, name, credit_hours, credit_hours_lab, semester, department_id, sessions_per_week, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$courseInserted = 0;
foreach ($courses as $c) {
    $deptId = isset($deptIds[$c['dept']]) ? (int)$deptIds[$c['dept']] : $firstDeptId;
    $credit = (int)($c['credit'] ?? 3);
    $lab = (int)($c['lab'] ?? 0);
    $theory = max(0, (int)($c['theory'] ?? max(0, $credit - $lab)));
    // Persist real lab component from curriculum (mixed theory/lab courses keep their lab count).
    $creditHoursLab = $lab;
    $sessions = (int) ceil(max(0, $theory) / 1.5) + (int) ceil(max(0, $lab) / 1.5);
    if ($sessions < 1) $sessions = 1;
    $stmt->bind_param('ssiiiiii', $c['code'], $c['name'], $credit, $creditHoursLab, $c['sem'], $deptId, $sessions, $isActive);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) $courseInserted++;
    } else {
        echo "Course insert failed for {$c['code']}: " . $conn->error . "\n";
    }
}
$countRes = $conn->query('SELECT COUNT(*) AS n FROM course WHERE is_active = 1');
$courseCount = $countRes ? (int)$countRes->fetch_assoc()['n'] : 0;
if ($courseCount === 0) {
    echo "WARNING: No active courses in database. Trying direct INSERT for CS101...\n";
    $ok = $conn->query("INSERT INTO course (code, name, credit_hours, semester, department_id, sessions_per_week, is_active) VALUES ('CS101', 'Programming I', 3, 1, " . (int)$firstDeptId . ", 3, 1)");
    if (!$ok) echo "Direct INSERT error: " . $conn->error . "\n";
    else echo "CS101 inserted. Run seed again to add remaining courses.\n";
}
// Set prerequisite_course_id for courses that have one
$upd = $conn->prepare('UPDATE course SET prerequisite_course_id = ? WHERE code = ?');
foreach ($courses as $c) {
    if (empty($c['prereq'])) continue;
    $pr = $conn->prepare('SELECT id FROM course WHERE code = ? LIMIT 1');
    $pr->bind_param('s', $c['prereq']);
    $pr->execute();
    $prRes = $pr->get_result()->fetch_assoc();
    if ($prRes) {
        $prereqId = (int)$prRes['id'];
        $courseCode = $c['code'];
        $upd->bind_param('is', $prereqId, $courseCode);
        $upd->execute();
    }
}
echo "Courses: " . count($courses) . "\n";

// --- Course-Faculty assignments (so Generate can assign teachers) ---
$res = $conn->query('SELECT id FROM course WHERE code IN (\'CS101\',\'IT201\',\'EE101\',\'MATH101\') ORDER BY code');
$courseIds = [];
while ($row = $res->fetch_assoc()) $courseIds[$row['id']] = true;
// Use all available CS faculty for course assignments
$res = $conn->query("SELECT f.id FROM faculty f LEFT JOIN department d ON d.id = f.department_id WHERE d.code = 'CS' OR f.department_id IS NULL ORDER BY f.id");
$facultyIds = [];
while ($row = $res->fetch_assoc()) $facultyIds[] = (int)$row['id'];
if (count($facultyIds) >= 3) {
    $res = $conn->query('SELECT id, code FROM course WHERE is_active = 1 ORDER BY id');
    $cfStmt = $conn->prepare('INSERT IGNORE INTO course_faculty (course_id, faculty_id) VALUES (?, ?)');
    while ($row = $res->fetch_assoc()) {
        $cid = (int)$row['id'];
        $cfStmt->bind_param('ii', $cid, $facultyIds[0]);
        $cfStmt->execute();
        $cfStmt->bind_param('ii', $cid, $facultyIds[1]);
        $cfStmt->execute();
        $cfStmt->bind_param('ii', $cid, $facultyIds[2]);
        $cfStmt->execute();
    }
    echo "Course–Faculty: three instructors per course (INSERT IGNORE).\n";
} elseif (count($facultyIds) >= 2) {
    echo "WARNING: Need at least 3 faculty for generator; only " . count($facultyIds) . " matched query. Add faculty or run generate to normalize.\n";
}

// --- Rooms ---
$rooms = [
    ['room_number' => 'R101', 'capacity' => 40, 'type' => 'classroom'],
    ['room_number' => 'R102', 'capacity' => 35, 'type' => 'classroom'],
    ['room_number' => 'LAB1', 'capacity' => 25, 'type' => 'lab'],
    ['room_number' => 'LAB2', 'capacity' => 25, 'type' => 'lab'],
    ['room_number' => 'R103', 'capacity' => 40, 'type' => 'classroom'],
    ['room_number' => 'R104', 'capacity' => 35, 'type' => 'classroom'],
    ['room_number' => 'LAB3', 'capacity' => 30, 'type' => 'lab'],
    ['room_number' => 'LAB4', 'capacity' => 30, 'type' => 'lab'],
    ['room_number' => 'HALL-A', 'capacity' => 100, 'type' => 'hall'],
    ['room_number' => 'HALL-B', 'capacity' => 120, 'type' => 'hall'],
];

// Add additional rooms up to 50 total for testing pagination
$totalRoomsTarget = 50;
for ($i = count($rooms) + 1; $i <= $totalRoomsTarget; $i++) {
    $num = 100 + $i; // start from R(101+)
    $rooms[] = [
        'room_number' => 'R' . $num,
        'capacity'    => 30 + (($i - 1) % 40),
        'type'        => 'classroom',
    ];
}
$stmt = $conn->prepare('INSERT IGNORE INTO room (room_number, capacity, room_type) VALUES (?, ?, ?)');
foreach ($rooms as $r) {
    $stmt->bind_param('sis', $r['room_number'], $r['capacity'], $r['type']);
    $stmt->execute();
}
echo "Rooms: " . count($rooms) . "\n";

// --- Academic Sessions (dynamic from current date) ---
// Build current + future terms so old sessions do not dominate dropdowns.
$today = new DateTimeImmutable('today');
$yearNow = (int)$today->format('Y');
$monthNow = (int)$today->format('n');

// Determine current term anchor.
if ($monthNow >= 2 && $monthNow <= 6) {
    $term = 'Spring';
    $termYear = $yearNow;
} elseif ($monthNow >= 7 && $monthNow <= 8) {
    $term = 'Summer';
    $termYear = $yearNow;
} else {
    // Sep-Jan map to Fall of current year, Jan maps to previous year's Fall.
    $term = 'Fall';
    $termYear = ($monthNow === 1) ? ($yearNow - 1) : $yearNow;
}

$nextTerm = static function (string $t, int $y): array {
    if ($t === 'Spring') return ['Summer', $y];
    if ($t === 'Summer') return ['Fall', $y];
    return ['Spring', $y + 1];
};

$termDates = static function (string $t, int $y): array {
    if ($t === 'Spring') {
        return [$y . '-02-01', $y . '-06-15'];
    }
    if ($t === 'Summer') {
        return [$y . '-06-16', $y . '-08-31'];
    }
    // Fall spans year boundary.
    return [$y . '-09-01', ($y + 1) . '-01-31'];
};

$sessionsSeed = [];
$seedTerms = 12; // roughly 4 academic years (Spring/Summer/Fall cycles)
for ($i = 0; $i < $seedTerms; $i++) {
    [$st, $en] = $termDates($term, $termYear);
    $sessionsSeed[] = ['name' => $term . ' ' . $termYear, 'start' => $st, 'end' => $en];
    [$term, $termYear] = $nextTerm($term, $termYear);
}
foreach ($sessionsSeed as $s) {
    $name = $conn->real_escape_string($s['name']);
    $start = $conn->real_escape_string($s['start']);
    $end = $conn->real_escape_string($s['end']);
    $conn->query("INSERT IGNORE INTO academic_session (name, start_date, end_date) VALUES ('$name', '$start', '$end')");
}
$countSessRes = $conn->query('SELECT COUNT(*) AS n FROM academic_session');
$sessCount = $countSessRes ? (int)$countSessRes->fetch_assoc()['n'] : count($sessionsSeed);
echo "Academic sessions: " . $sessCount . "\n";

// Ensure seeded students are tied to a concrete academic session for Students filters.
// Prefer the first seeded session (current term anchor), fallback to earliest future, then any oldest.
$defaultSessionName = $sessionsSeed[0]['name'] ?? '';
$defaultSessionId = 0;
if ($defaultSessionName !== '') {
    $stSess = $conn->prepare('SELECT id FROM academic_session WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
    $stSess->bind_param('s', $defaultSessionName);
    $stSess->execute();
    $sr = $stSess->get_result()->fetch_assoc();
    if ($sr) {
        $defaultSessionId = (int)$sr['id'];
    }
}
if ($defaultSessionId <= 0) {
    $resSess = $conn->query('SELECT id FROM academic_session WHERE end_date >= CURDATE() ORDER BY start_date ASC LIMIT 1');
    if ($resSess && ($rSess = $resSess->fetch_assoc())) {
        $defaultSessionId = (int)$rSess['id'];
    }
}
if ($defaultSessionId <= 0) {
    $resSess = $conn->query('SELECT id FROM academic_session ORDER BY start_date ASC LIMIT 1');
    if ($resSess && ($rSess = $resSess->fetch_assoc())) {
        $defaultSessionId = (int)$rSess['id'];
    }
}
if ($defaultSessionId > 0) {
    $upStu = $conn->prepare('UPDATE student SET academic_session_id = ? WHERE academic_session_id IS NULL');
    $upStu->bind_param('i', $defaultSessionId);
    $upStu->execute();
    echo "Students session default applied: session_id={$defaultSessionId}\n";
}

echo "\nSeed complete. You can log in as:\n";
echo "  Admin:  admin@isp.edu.pk / admin123\n";
echo "  Faculty login: [name]@cs.edu.pk / faculty123 (e.g. imran.ali@cs.edu.pk)\n";
echo "  Student: student1@seed.edu / student123\n";
$conn->close();
