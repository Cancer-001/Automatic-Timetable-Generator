# Timetable Management System – Project Structure & SRS Achievement

## Project structure

```
assigment/
├── assets/
│   ├── css/
│   │   └── style.css          # Global styles, layout, toasts
│   └── js/
│       └── app.js             # jQuery setup, showToast(), AJAX error handling
├── auth/
│   ├── login.php              # Login page
│   ├── process_login.php      # Login API (admin/faculty/student)
│   ├── check_role.php         # Role-based access
│   └── logout.php             # Session destroy
├── admin/                     # Admin dashboard
│   ├── header.php             # Nav + app wrapper
│   ├── footer.php
│   ├── index.php              # Dashboard home
│   ├── departments.php        # Department CRUD
│   ├── courses.php            # Course CRUD + course-faculty assign
│   ├── faculty.php            # Faculty CRUD
│   ├── students.php           # Student CRUD
│   ├── rooms.php              # Room CRUD
│   ├── sessions.php           # Academic session CRUD
│   └── generate.php           # Timetable generate + manual move
├── faculty/
│   ├── header.php, footer.php
│   ├── index.php              # My timetable
│   └── substitution.php      # Substitution requests
├── student/
│   ├── header.php, footer.php
│   └── index.php              # Timetable + export CSV/PDF
├── actions/                   # REST-style APIs (JSON)
│   ├── departments.php        # GET/POST/PUT/DELETE
│   ├── courses.php
│   ├── faculty.php
│   ├── students.php
│   ├── rooms.php
│   ├── sessions.php
│   ├── course_faculty.php     # Assign faculty to courses
│   ├── schedule.php           # Get schedule (by session, faculty, student)
│   ├── generate.php           # Auto-generate timetable
│   ├── schedule_move.php      # Manual move with conflict check
│   ├── time_slots.php
│   ├── export.php             # CSV/PDF export
│   └── substitution.php
├── config/
│   ├── db.php                 # MySQL connection
│   ├── db.example.php         # Example for production
│   └── api_response.php       # Standard error/success helpers
├── database/
│   ├── schema.sql             # Full DB schema + time_slot seed
│   ├── seed.php               # Sample data (departments, students, courses, etc.)
│   └── refresh_db.php         # Reset DB to fresh state
├── install.php                # One-time: create DB, run schema, default admin
│
├── window_setup.bat           # Windows: full install
├── window_run_project.bat     # Windows: start app
├── window_runseed.bat         # Windows: seed data
├── window_refresh_db.bat      # Windows: reset DB
├── linux_mac_setup.sh         # Linux/Mac: setup
├── linux_mac_run_project.sh   # Linux/Mac: start app
├── linux_mac_runseed.sh       # Linux/Mac: seed data
├── linux_mac_refresh_db.sh    # Linux/Mac: reset DB
│
├── INSTRUCTIONS.md            # How to run (for students)
├── PROJECT_STRUCTURE_AND_SRS.md  # This file
├── DEPLOYMENT.md              # Production deployment
└── README.md                  # Overview
```

---

## SRS achievement summary

| SRS / Requirement area        | Implementation |
|-------------------------------|----------------|
| **User roles**                | Admin, Faculty, Student; login checks in `auth/`; role in session; `check_role.php` per area. |
| **Department management**     | Admin CRUD; `department` table; `actions/departments.php`; duplicate name/code validation. |
| **Academic session**          | Admin CRUD; `academic_session`; `actions/sessions.php`. |
| **Course management**         | Admin CRUD; `course` table (code, name, credit_hours, semester, sessions_per_week); `actions/courses.php`. |
| **Faculty management**        | Admin CRUD; `faculty` table; `actions/faculty.php`. |
| **Student management**        | Admin CRUD; `student` table (semester, section); `actions/students.php`. |
| **Room management**           | Admin CRUD; `room` table (room_number, capacity, type); `actions/rooms.php`. |
| **Faculty–course assignment** | Admin “Assign Faculty” per course; `course_faculty` table; `actions/course_faculty.php`. |
| **Time slots**                | `time_slot` table; seeded in `schema.sql`; `actions/time_slots.php`. |
| **Timetable generation**      | Admin “Generate”; `actions/generate.php`; conflict checks (no double teacher/room per slot). |
| **Manual schedule move**     | Admin move with conflict check; `actions/schedule_move.php`. |
| **Faculty view**              | Faculty timetable by session; `schedule.php` filtered by `faculty_id`. |
| **Student view**              | Student timetable by session, filtered by semester/section; `schedule.php`. |
| **Export**                    | Student: CSV and PDF/print via `actions/export.php`. |
| **Substitution requests**     | Faculty submit; `substitution_request` table; `actions/substitution.php`. |
| **Security**                  | Session-based auth; prepared statements; role checks on APIs. |
| **Consistent API errors**     | `api_response.php`; `success`/`message`; toasts on front end. |

---

## Technology alignment

- **Backend:** PHP with MySQLi (SRS: server-side logic and data).
- **Frontend:** HTML5, CSS3, JavaScript, jQuery, AJAX, Bootstrap 5 (SRS: web interface).
- **Database:** MySQL/MariaDB; normalized schema; foreign keys; `schema.sql` and migrations via `install.php` / `refresh_db.php`.
