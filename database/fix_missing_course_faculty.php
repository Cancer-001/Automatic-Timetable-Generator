<?php
/**
 * Creates the course_faculty table if it is missing (e.g. old DB created before this table existed).
 * Run once: php database/fix_missing_course_faculty.php
 * Does not delete any data. After this, run seed to populate course_faculty: window_runseed.bat
 */
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/db.php';

$exists = $conn->query("SHOW TABLES LIKE 'course_faculty'");
if ($exists && $exists->num_rows > 0) {
    echo "Table course_faculty already exists. Nothing to do.\n";
    $conn->close();
    exit(0);
}

$sql = "CREATE TABLE course_faculty (
    course_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (course_id, faculty_id),
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "Table course_faculty created successfully.\n";
    echo "Run window_runseed.bat to add course–faculty assignments (optional).\n";
} else {
    echo "Error: " . $conn->error . "\n";
    exit(1);
}
$conn->close();
