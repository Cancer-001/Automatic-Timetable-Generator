-- ============================================================
-- CS Faculty Seed — 36 members from facultycs.xlsx
-- Database: assignmentupdated
-- Password for all: faculty123
-- Run in phpMyAdmin or MySQL CLI after install.php
-- ============================================================

USE assignmentupdated;

-- Ensure CS department exists
INSERT IGNORE INTO department (name, code) VALUES ('Computer Science', 'CS');

SET @cs_dept = (SELECT id FROM department WHERE code = 'CS' LIMIT 1);

-- Insert 36 CS Faculty members (INSERT IGNORE = safe to run multiple times)
-- NOTE: Passwords are hashed via PHP. Use the seed script below instead.
-- Run: php database/seed_cs_faculty.php
