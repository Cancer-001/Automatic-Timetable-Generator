# Unstaged Changes Documentation

This file documents the current unstaged modifications in the working tree.

## Scope Snapshot

- Unstaged modified files: 25
- Net diff: 1662 insertions, 252 deletions
- Main areas touched: `actions/`, `admin/`, `database/`, `assets/`

## High-Level Functional Changes

### 1) Timetable Generation Guardrails and UX

- Added session-semester parity validation in generation flow:
  - Fall sessions -> odd semesters only (`1, 3, 5, 7`)
  - Spring sessions -> even semesters only (`2, 4, 6, 8`)
- Added/updated user-facing feedback (inline result + toast behavior) for invalid session/semester combinations.
- Refined Generate page merged lecture controls by removing searchable text inputs from select-only fields (course/faculty/room/day/time-slot).

Files:
- `actions/generate.php`
- `admin/generate.php`

### 2) Schedule Time Formatting

- Timetable API now formats `time_range` in 12-hour format with AM/PM for display consistency.

File:
- `actions/schedule.php`

### 3) Academic Sessions: Current/Future-First Behavior

- Session listing API now defaults to current/upcoming sessions (`end_date >= CURDATE()`), with support for override via query flag (`include_past=1`).
- Session ordering updated to chronological ascending (`start_date ASC`) so nearest upcoming/current terms appear first.

File:
- `actions/sessions.php`

### 4) Dynamic Session Seeding

- Replaced hardcoded academic session seed block with date-driven generation.
- Seed now starts from current term (Spring/Summer/Fall based on today) and generates a rolling future window.

File:
- `database/seed.php`

### 5) Course Catalog Alignment (Semester-Wise)

- Course seed data updated to align with semester-wise curriculum and revised course titles/codes.
- Credit/session handling in seed updated around revised catalog entries.
- BSCS replacement script updated to align with the same semester-wise catalog structure.

Files:
- `database/seed.php`
- `database/replace_courses_bscs_2023.php`

### 6) Catalog Import Removal

Catalog import feature was removed from admin flow and project files.

- Removed nav entry in admin header
- Removed import page/action/template files

Current unstaged effect:
- Header/menu cleanup remains in unstaged diffs

File in unstaged set:
- `admin/header.php`

Removed files (already removed in working tree history of this session):
- `admin/catalog_import.php`
- `actions/catalog_import.php`
- `catalog_import_template.csv`

### 7) UI/CRUD Enhancements Across Admin and Actions

Additional unstaged updates include broader CRUD/pagination/search/validation improvements across core modules.

Actions layer files:
- `actions/course_faculty.php`
- `actions/courses.php`
- `actions/departments.php`
- `actions/faculty.php`
- `actions/rooms.php`
- `actions/students.php`

Admin UI files:
- `admin/calendar.php`
- `admin/courses.php`
- `admin/degrees.php`
- `admin/departments.php`
- `admin/faculty.php`
- `admin/rooms.php`
- `admin/sessions.php`
- `admin/students.php`

Assets:
- `assets/css/style.css`
- `assets/js/app.js`

Database support scripts/schema:
- `database/migrations.sql`
- `database/refresh_db.php`
- `database/schema.sql`

## Unstaged File List (Exact)

- `actions/course_faculty.php`
- `actions/courses.php`
- `actions/departments.php`
- `actions/faculty.php`
- `actions/generate.php`
- `actions/rooms.php`
- `actions/schedule.php`
- `actions/sessions.php`
- `actions/students.php`
- `admin/calendar.php`
- `admin/courses.php`
- `admin/degrees.php`
- `admin/departments.php`
- `admin/faculty.php`
- `admin/generate.php`
- `admin/rooms.php`
- `admin/sessions.php`
- `admin/students.php`
- `assets/css/style.css`
- `assets/js/app.js`
- `database/migrations.sql`
- `database/refresh_db.php`
- `database/replace_courses_bscs_2023.php`
- `database/schema.sql`
- `database/seed.php`

## Notes

- This document intentionally summarizes behavior-level impact instead of line-by-line diffs.
- Use `git diff --stat` and `git diff <file>` for low-level inspection per file.
