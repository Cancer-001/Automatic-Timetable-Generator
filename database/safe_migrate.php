<?php
/**
 * Safe additive migration for existing XAMPP/MAMP databases.
 *
 * This script only creates missing tables/columns/indexes. It does not drop
 * tables, truncate tables, or delete existing data.
 *
 * Run from project root:
 *   php database/safe_migrate.php
 */

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/db.php';
require_once $baseDir . '/config/schema_helpers.php';

mysqli_report(MYSQLI_REPORT_OFF);
$conn->set_charset('utf8mb4');

function out($message) {
    echo $message . PHP_EOL;
}

function table_exists($conn, $table) {
    $tableLike = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$tableLike'");
    return $res && $res->num_rows > 0;
}

function run_sql($conn, $sql, $label) {
    if ($conn->query($sql)) {
        out('[OK] ' . $label);
        return true;
    }
    out('[WARN] ' . $label . ' - ' . $conn->error);
    return false;
}

function ensure_table($conn, $table, $sql) {
    if (table_exists($conn, $table)) {
        out('[SKIP] table exists: ' . $table);
        return;
    }
    run_sql($conn, $sql, 'created table: ' . $table);
}

function ensure_column($conn, $table, $column, $definition) {
    if (!table_exists($conn, $table)) {
        out('[WARN] table missing, cannot add column: ' . $table . '.' . $column);
        return;
    }
    if (db_column_exists($conn, $table, $column)) {
        out('[SKIP] column exists: ' . $table . '.' . $column);
        return;
    }
    if (db_add_column_if_missing($conn, $table, $column, $definition)) {
        out('[OK] added column: ' . $table . '.' . $column);
    } else {
        out('[WARN] add column failed: ' . $table . '.' . $column . ' - ' . $conn->error);
    }
}

function ensure_index($conn, $table, $index, $sql) {
    if (!table_exists($conn, $table)) {
        out('[WARN] table missing, cannot add index: ' . $table . '.' . $index);
        return;
    }
    $indexLike = $conn->real_escape_string($index);
    $tableSql = db_identifier($table);
    $res = $conn->query("SHOW INDEX FROM $tableSql WHERE Key_name = '$indexLike'");
    if ($res && $res->num_rows > 0) {
        out('[SKIP] index exists: ' . $table . '.' . $index);
        return;
    }
    run_sql($conn, $sql, 'added index: ' . $table . '.' . $index);
}

out('Safe migration started for database: ' . DB_NAME);

ensure_table($conn, 'degree', "CREATE TABLE degree (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(128) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

ensure_table($conn, 'calendar_event', "CREATE TABLE calendar_event (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    event_type ENUM('lecture','lab','exam','holiday','custom') NOT NULL DEFAULT 'custom',
    academic_session_id INT UNSIGNED DEFAULT NULL,
    course_id INT UNSIGNED DEFAULT NULL,
    faculty_id INT UNSIGNED DEFAULT NULL,
    department_id INT UNSIGNED DEFAULT NULL,
    semester TINYINT UNSIGNED DEFAULT NULL,
    section VARCHAR(32) DEFAULT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    is_all_day TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ce_session (academic_session_id),
    INDEX idx_ce_faculty (faculty_id),
    INDEX idx_ce_student (semester, section),
    INDEX idx_ce_start (start_datetime),
    INDEX idx_ce_end (end_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

ensure_table($conn, 'course_faculty', "CREATE TABLE course_faculty (
    course_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (course_id, faculty_id),
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

ensure_table($conn, 'course_faculty_assignment', "CREATE TABLE course_faculty_assignment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NOT NULL,
    academic_session_id INT UNSIGNED DEFAULT NULL,
    degree_id INT UNSIGNED DEFAULT NULL,
    section VARCHAR(32) DEFAULT NULL,
    preferred_day_of_week TINYINT UNSIGNED DEFAULT NULL COMMENT '1=Mon .. 5=Fri',
    preferred_start_time TIME DEFAULT NULL,
    preferred_end_time TIME DEFAULT NULL,
    room_id INT UNSIGNED DEFAULT NULL,
    cfa_ctx_session INT UNSIGNED GENERATED ALWAYS AS (COALESCE(academic_session_id, 0)) STORED,
    cfa_ctx_degree INT UNSIGNED GENERATED ALWAYS AS (COALESCE(degree_id, 0)) STORED,
    cfa_ctx_section VARCHAR(32) GENERATED ALWAYS AS (LOWER(TRIM(COALESCE(section, '')))) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cfa_course (course_id),
    INDEX idx_cfa_faculty (faculty_id),
    UNIQUE KEY uq_cfa_class_ctx (course_id, cfa_ctx_session, cfa_ctx_degree, cfa_ctx_section),
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_session(id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES room(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

ensure_table($conn, 'schedule_merge_member', "CREATE TABLE schedule_merge_member (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    section VARCHAR(32) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_merge_member (schedule_id, semester, section),
    INDEX idx_merge_schedule (schedule_id),
    FOREIGN KEY (schedule_id) REFERENCES schedule(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

ensure_column($conn, 'student', 'academic_session_id', 'INT UNSIGNED DEFAULT NULL AFTER department_id');
ensure_column($conn, 'student', 'degree_id', 'INT UNSIGNED DEFAULT NULL AFTER academic_session_id');
ensure_column($conn, 'student', 'degree', "VARCHAR(32) NOT NULL DEFAULT 'BS' AFTER degree_id");
ensure_column($conn, 'student', 'roll_no', 'VARCHAR(64) DEFAULT NULL AFTER degree');
ensure_column($conn, 'student', 'is_frozen', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');

ensure_column($conn, 'faculty', 'degree_id', 'INT UNSIGNED DEFAULT NULL AFTER department_id');
ensure_column($conn, 'faculty', 'faculty_type', "ENUM('permanent','visiting') NOT NULL DEFAULT 'permanent' AFTER full_name");
ensure_column($conn, 'faculty', 'visiting_day_of_week', 'TINYINT UNSIGNED DEFAULT NULL AFTER faculty_type');
ensure_column($conn, 'faculty', 'visiting_start_time', 'TIME DEFAULT NULL AFTER visiting_day_of_week');
ensure_column($conn, 'faculty', 'visiting_end_time', 'TIME DEFAULT NULL AFTER visiting_start_time');

ensure_column($conn, 'course', 'credit_hours_lab', 'TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER credit_hours');
ensure_column($conn, 'time_slot', 'slot_type', "ENUM('lecture','lab') NOT NULL DEFAULT 'lecture' AFTER slot_label");
ensure_column($conn, 'schedule', 'is_merged_lecture', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER section');

ensure_column($conn, 'course_faculty_assignment', 'preferred_day_of_week', "TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '1=Mon .. 5=Fri' AFTER section");
ensure_column($conn, 'course_faculty_assignment', 'cfa_ctx_session', 'INT UNSIGNED GENERATED ALWAYS AS (COALESCE(academic_session_id, 0)) STORED');
ensure_column($conn, 'course_faculty_assignment', 'cfa_ctx_degree', 'INT UNSIGNED GENERATED ALWAYS AS (COALESCE(degree_id, 0)) STORED');
ensure_column($conn, 'course_faculty_assignment', 'cfa_ctx_section', "VARCHAR(32) GENERATED ALWAYS AS (LOWER(TRIM(COALESCE(section, '')))) STORED");

ensure_index($conn, 'time_slot', 'uq_slot', 'ALTER TABLE time_slot ADD UNIQUE KEY uq_slot (day_of_week, start_time, slot_type)');
ensure_index($conn, 'schedule', 'uq_student_slot', 'ALTER TABLE schedule ADD UNIQUE KEY uq_student_slot (academic_session_id, semester, section, time_slot_id)');
ensure_index($conn, 'course_faculty_assignment', 'uq_cfa_class_ctx', 'ALTER TABLE course_faculty_assignment ADD UNIQUE KEY uq_cfa_class_ctx (course_id, cfa_ctx_session, cfa_ctx_degree, cfa_ctx_section)');

$degreeCount = $conn->query('SELECT COUNT(*) AS n FROM degree');
$degreeCount = $degreeCount ? (int)($degreeCount->fetch_assoc()['n'] ?? 0) : 0;
if ($degreeCount === 0) {
    $stmt = $conn->prepare('INSERT INTO degree (code, name) VALUES (?, ?)');
    $rows = [
        ['BS', 'Bachelor of Science'],
        ['BBA', 'Bachelor of Business Administration'],
    ];
    foreach ($rows as $row) {
        $stmt->bind_param('ss', $row[0], $row[1]);
        $stmt->execute();
    }
    out('[OK] inserted default degree rows');
} else {
    out('[SKIP] degree rows already exist');
}

out('Safe migration finished.');
