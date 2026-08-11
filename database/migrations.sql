-- Migrations: ensure tables that may be missing on old schema are created.
-- Run after schema.sql during Refresh. Uses IF NOT EXISTS so safe with current schema.

USE assignmentupdated;

-- Course–faculty assignment table (required for timetable generator).
CREATE TABLE IF NOT EXISTS course_faculty (
    course_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (course_id, faculty_id),
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Student table: add degree, roll_no, and is_frozen columns if missing (safe on fresh schema too).
ALTER TABLE student
    ADD COLUMN IF NOT EXISTS degree VARCHAR(32) NOT NULL DEFAULT 'BS' AFTER department_id,
    ADD COLUMN IF NOT EXISTS roll_no VARCHAR(64) DEFAULT NULL AFTER degree,
    ADD COLUMN IF NOT EXISTS is_frozen TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

-- Tie students to an academic term (filters in Admin → Students and portals).
ALTER TABLE student
    ADD COLUMN IF NOT EXISTS academic_session_id INT UNSIGNED DEFAULT NULL AFTER department_id;

-- Course table: add credit_hours_lab column if missing.
ALTER TABLE course
    ADD COLUMN IF NOT EXISTS credit_hours_lab TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER credit_hours;

-- Time slot updates: add slot_type column and replace slots with new durations.
-- Lecture = 1.5 hours (90 min), Lab = 1 hour (60 min).
ALTER TABLE time_slot
    ADD COLUMN IF NOT EXISTS slot_type ENUM('lecture','lab') NOT NULL DEFAULT 'lecture' AFTER slot_label;

-- Rebuild unique key to include slot_type (compatible with MySQL 5.7+)
-- Step 1: drop old key if it exists (ignore error if it does not)
ALTER IGNORE TABLE time_slot DROP INDEX uq_slot;
-- Step 2: add new key (ignore error if it already exists)
ALTER IGNORE TABLE time_slot ADD UNIQUE KEY uq_slot (day_of_week, start_time, slot_type);

-- Remove ALL old slots and replace with correct durations
DELETE FROM time_slot WHERE 1=1;
INSERT IGNORE INTO time_slot (day_of_week, start_time, end_time, slot_label, slot_type) VALUES
(1,'08:00:00','09:30:00','Mon-1','lecture'),(1,'09:30:00','11:00:00','Mon-2','lecture'),(1,'11:00:00','12:30:00','Mon-3','lecture'),(1,'12:30:00','14:00:00','Mon-4','lecture'),(1,'14:00:00','15:30:00','Mon-5','lecture'),
(2,'08:00:00','09:30:00','Tue-1','lecture'),(2,'09:30:00','11:00:00','Tue-2','lecture'),(2,'11:00:00','12:30:00','Tue-3','lecture'),(2,'12:30:00','14:00:00','Tue-4','lecture'),(2,'14:00:00','15:30:00','Tue-5','lecture'),
(3,'08:00:00','09:30:00','Wed-1','lecture'),(3,'09:30:00','11:00:00','Wed-2','lecture'),(3,'11:00:00','12:30:00','Wed-3','lecture'),(3,'12:30:00','14:00:00','Wed-4','lecture'),(3,'14:00:00','15:30:00','Wed-5','lecture'),
(4,'08:00:00','09:30:00','Thu-1','lecture'),(4,'09:30:00','11:00:00','Thu-2','lecture'),(4,'11:00:00','12:30:00','Thu-3','lecture'),(4,'12:30:00','14:00:00','Thu-4','lecture'),(4,'14:00:00','15:30:00','Thu-5','lecture'),
(5,'08:00:00','09:30:00','Fri-1','lecture'),(5,'09:30:00','11:00:00','Fri-2','lecture'),(5,'11:00:00','12:30:00','Fri-3','lecture'),(5,'12:30:00','14:00:00','Fri-4','lecture'),(5,'14:00:00','15:30:00','Fri-5','lecture'),
(1,'08:00:00','09:00:00','Mon-Lab1','lab'),(1,'09:00:00','10:00:00','Mon-Lab2','lab'),(1,'10:00:00','11:00:00','Mon-Lab3','lab'),(1,'11:00:00','12:00:00','Mon-Lab4','lab'),(1,'13:00:00','14:00:00','Mon-Lab5','lab'),
(2,'08:00:00','09:00:00','Tue-Lab1','lab'),(2,'09:00:00','10:00:00','Tue-Lab2','lab'),(2,'10:00:00','11:00:00','Tue-Lab3','lab'),(2,'11:00:00','12:00:00','Tue-Lab4','lab'),(2,'13:00:00','14:00:00','Tue-Lab5','lab'),
(3,'08:00:00','09:00:00','Wed-Lab1','lab'),(3,'09:00:00','10:00:00','Wed-Lab2','lab'),(3,'10:00:00','11:00:00','Wed-Lab3','lab'),(3,'11:00:00','12:00:00','Wed-Lab4','lab'),(3,'13:00:00','14:00:00','Wed-Lab5','lab'),
(4,'08:00:00','09:00:00','Thu-Lab1','lab'),(4,'09:00:00','10:00:00','Thu-Lab2','lab'),(4,'10:00:00','11:00:00','Thu-Lab3','lab'),(4,'11:00:00','12:00:00','Thu-Lab4','lab'),(4,'13:00:00','14:00:00','Thu-Lab5','lab'),
(5,'08:00:00','09:00:00','Fri-Lab1','lab'),(5,'09:00:00','10:00:00','Fri-Lab2','lab'),(5,'10:00:00','11:00:00','Fri-Lab3','lab'),(5,'11:00:00','12:00:00','Fri-Lab4','lab'),(5,'13:00:00','14:00:00','Fri-Lab5','lab');

-- Add DB-level student conflict prevention constraint (MySQL 5.7+ compatible)
ALTER IGNORE TABLE schedule ADD UNIQUE KEY uq_student_slot (academic_session_id, semester, section, time_slot_id);

-- Calendar custom events (admin managed)
CREATE TABLE IF NOT EXISTS calendar_event (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rich faculty–course assignments (Admin Courses popup); keeps course_faculty in sync for generator
CREATE TABLE IF NOT EXISTS course_faculty_assignment (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure context lock columns/index exist on older installs.
ALTER TABLE course_faculty_assignment
    ADD COLUMN IF NOT EXISTS preferred_day_of_week TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '1=Mon .. 5=Fri' AFTER section,
    ADD COLUMN IF NOT EXISTS cfa_ctx_session INT UNSIGNED GENERATED ALWAYS AS (COALESCE(academic_session_id, 0)) STORED,
    ADD COLUMN IF NOT EXISTS cfa_ctx_degree INT UNSIGNED GENERATED ALWAYS AS (COALESCE(degree_id, 0)) STORED,
    ADD COLUMN IF NOT EXISTS cfa_ctx_section VARCHAR(32) GENERATED ALWAYS AS (LOWER(TRIM(COALESCE(section, '')))) STORED;
ALTER IGNORE TABLE course_faculty_assignment ADD UNIQUE KEY uq_cfa_class_ctx (course_id, cfa_ctx_session, cfa_ctx_degree, cfa_ctx_section);
