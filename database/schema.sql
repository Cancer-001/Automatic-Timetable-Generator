-- Timetable Management System - Full Schema (SRS / ERD)
-- Database: assignmentupdated
-- Run via install.php or phpMyAdmin / mysql CLI

CREATE DATABASE IF NOT EXISTS assignmentupdated;
USE assignmentupdated;

DROP TABLE IF EXISTS course_faculty;
DROP TABLE IF EXISTS course_faculty_assignment;
DROP TABLE IF EXISTS calendar_event;
DROP TABLE IF EXISTS schedule;
DROP TABLE IF EXISTS faculty;
DROP TABLE IF EXISTS student;
DROP TABLE IF EXISTS degree;
DROP TABLE IF EXISTS course;
DROP TABLE IF EXISTS room;
DROP TABLE IF EXISTS time_slot;
DROP TABLE IF EXISTS admin;
DROP TABLE IF EXISTS department;
DROP TABLE IF EXISTS academic_session;
DROP TABLE IF EXISTS substitution_request;
DROP TABLE IF EXISTS enrollment;

CREATE TABLE department (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(200) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE academic_session (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(128) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(128) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE faculty (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(128) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(128) NOT NULL,
    faculty_type ENUM('permanent','visiting') NOT NULL DEFAULT 'permanent',
    visiting_day_of_week TINYINT UNSIGNED DEFAULT NULL,
    visiting_start_time TIME DEFAULT NULL,
    visiting_end_time TIME DEFAULT NULL,
    department_id INT UNSIGNED DEFAULT NULL,
    availability_notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE student (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(128) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(128) NOT NULL,
    department_id INT UNSIGNED DEFAULT NULL,
    degree VARCHAR(32) NOT NULL DEFAULT 'BS',
    roll_no VARCHAR(64) DEFAULT NULL UNIQUE,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    section VARCHAR(32) NOT NULL DEFAULT 'A',
    is_active TINYINT(1) DEFAULT 1,
    is_frozen TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Course (course code, credit hours, semester, optional prerequisite)
CREATE TABLE course (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    credit_hours TINYINT UNSIGNED NOT NULL DEFAULT 3,
    credit_hours_lab TINYINT UNSIGNED NOT NULL DEFAULT 0,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    department_id INT UNSIGNED DEFAULT NULL,
    sessions_per_week TINYINT UNSIGNED NOT NULL DEFAULT 3,
    is_active TINYINT(1) DEFAULT 1,
    prerequisite_course_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL,
    FOREIGN KEY (prerequisite_course_id) REFERENCES course(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Room (room number, capacity, type)
CREATE TABLE room (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(32) NOT NULL UNIQUE,
    capacity INT UNSIGNED NOT NULL DEFAULT 30,
    room_type ENUM('classroom', 'lab', 'hall') NOT NULL DEFAULT 'classroom',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TimeSlot (days, start/end times)
CREATE TABLE time_slot (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT UNSIGNED NOT NULL COMMENT '1=Mon..7=Sun',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_label VARCHAR(32) NOT NULL,
    slot_type ENUM('lecture','lab') NOT NULL DEFAULT 'lecture',
    UNIQUE KEY uq_slot (day_of_week, start_time, slot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Schedule (master table: links course, faculty, room, time_slot, section/semester)
-- Which faculty can teach which course (for generator)
CREATE TABLE course_faculty (
    course_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (course_id, faculty_id),
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_session_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    time_slot_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    section VARCHAR(32) NOT NULL DEFAULT 'A',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_session_id) REFERENCES academic_session(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES room(id) ON DELETE CASCADE,
    FOREIGN KEY (time_slot_id) REFERENCES time_slot(id) ON DELETE CASCADE,
    UNIQUE KEY uq_room_slot (academic_session_id, room_id, time_slot_id),
    UNIQUE KEY uq_faculty_slot (academic_session_id, faculty_id, time_slot_id),
    UNIQUE KEY uq_student_slot (academic_session_id, semester, section, time_slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Student course enrollment (per academic session)
CREATE TABLE enrollment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    academic_session_id INT UNSIGNED NOT NULL,
    status ENUM('enrolled', 'dropped') NOT NULL DEFAULT 'enrolled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (student_id, course_id, academic_session_id),
    FOREIGN KEY (student_id) REFERENCES student(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_session(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Substitution requests (faculty)
CREATE TABLE substitution_request (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT UNSIGNED NOT NULL,
    schedule_id INT UNSIGNED NOT NULL,
    requested_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedule(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin is inserted by install.php with hashed password (admin123)

-- Seed time slots: Lecture=1.5hr (90min), Lab=1hr (60min), Mon-Fri
INSERT INTO time_slot (day_of_week, start_time, end_time, slot_label, slot_type) VALUES
(1,'08:00:00','09:30:00','Mon-1','lecture'),
(1,'09:30:00','11:00:00','Mon-2','lecture'),
(1,'11:00:00','12:30:00','Mon-3','lecture'),
(1,'12:30:00','14:00:00','Mon-4','lecture'),
(1,'14:00:00','15:30:00','Mon-5','lecture'),
(2,'08:00:00','09:30:00','Tue-1','lecture'),
(2,'09:30:00','11:00:00','Tue-2','lecture'),
(2,'11:00:00','12:30:00','Tue-3','lecture'),
(2,'12:30:00','14:00:00','Tue-4','lecture'),
(2,'14:00:00','15:30:00','Tue-5','lecture'),
(3,'08:00:00','09:30:00','Wed-1','lecture'),
(3,'09:30:00','11:00:00','Wed-2','lecture'),
(3,'11:00:00','12:30:00','Wed-3','lecture'),
(3,'12:30:00','14:00:00','Wed-4','lecture'),
(3,'14:00:00','15:30:00','Wed-5','lecture'),
(4,'08:00:00','09:30:00','Thu-1','lecture'),
(4,'09:30:00','11:00:00','Thu-2','lecture'),
(4,'11:00:00','12:30:00','Thu-3','lecture'),
(4,'12:30:00','14:00:00','Thu-4','lecture'),
(4,'14:00:00','15:30:00','Thu-5','lecture'),
(5,'08:00:00','09:30:00','Fri-1','lecture'),
(5,'09:30:00','11:00:00','Fri-2','lecture'),
(5,'11:00:00','12:30:00','Fri-3','lecture'),
(5,'12:30:00','14:00:00','Fri-4','lecture'),
(5,'14:00:00','15:30:00','Fri-5','lecture'),
(1,'08:00:00','09:00:00','Mon-Lab1','lab'),
(1,'09:00:00','10:00:00','Mon-Lab2','lab'),
(1,'10:00:00','11:00:00','Mon-Lab3','lab'),
(1,'11:00:00','12:00:00','Mon-Lab4','lab'),
(1,'13:00:00','14:00:00','Mon-Lab5','lab'),
(2,'08:00:00','09:00:00','Tue-Lab1','lab'),
(2,'09:00:00','10:00:00','Tue-Lab2','lab'),
(2,'10:00:00','11:00:00','Tue-Lab3','lab'),
(2,'11:00:00','12:00:00','Tue-Lab4','lab'),
(2,'13:00:00','14:00:00','Tue-Lab5','lab'),
(3,'08:00:00','09:00:00','Wed-Lab1','lab'),
(3,'09:00:00','10:00:00','Wed-Lab2','lab'),
(3,'10:00:00','11:00:00','Wed-Lab3','lab'),
(3,'11:00:00','12:00:00','Wed-Lab4','lab'),
(3,'13:00:00','14:00:00','Wed-Lab5','lab'),
(4,'08:00:00','09:00:00','Thu-Lab1','lab'),
(4,'09:00:00','10:00:00','Thu-Lab2','lab'),
(4,'10:00:00','11:00:00','Thu-Lab3','lab'),
(4,'11:00:00','12:00:00','Thu-Lab4','lab'),
(4,'13:00:00','14:00:00','Thu-Lab5','lab'),
(5,'08:00:00','09:00:00','Fri-Lab1','lab'),
(5,'09:00:00','10:00:00','Fri-Lab2','lab'),
(5,'10:00:00','11:00:00','Fri-Lab3','lab'),
(5,'11:00:00','12:00:00','Fri-Lab4','lab'),
(5,'13:00:00','14:00:00','Fri-Lab5','lab');
