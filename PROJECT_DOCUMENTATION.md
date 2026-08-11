# Timetable Management System — Complete Project Documentation

**For students:** This document explains the whole project in simple language. You do not need to know programming to understand what the system does and how it is built.

---

## 1. What Is This Project?

The **Timetable Management System** is a **website** that helps a school or college to:

- **Store** information: departments, courses, teachers (faculty), students, rooms, and academic sessions (e.g. Fall 2026).
- **Build** a timetable automatically: it assigns courses to time slots and rooms so that no teacher and no room is used twice at the same time.
- **Let three types of users** use the system:
  - **Admin** — sets up everything and generates the timetable.
  - **Faculty** — see their own timetable, set preferences, request a substitute.
  - **Student** — see their timetable, enroll in courses, export to Excel/PDF/Calendar.

You use it by opening the website in a browser (e.g. Chrome) and logging in with email and password.

---

## 2. How Do I Run the Project?

**You do not need to understand the code to run it.** Follow the steps in **[INSTRUCTIONS.md](INSTRUCTIONS.md)**.

In short:

- **Windows:** Run `window_setup.bat` once, then use `window_run_project.bat` to start the app and open the browser.
- **Login:** Email `admin@isp.edu.pk`, Password `admin123`.

The rest of this document is about **how the project is organized and what kind of code it uses**, so you can understand it even without programming experience.

---

## 3. Two Big Parts: What You See vs What Runs on the Server

Think of the system in two parts:

| Part | Where it runs | What it does |
|------|----------------|--------------|
| **Frontend** | **Your browser** (Chrome, Edge, etc.) | Shows the pages, buttons, forms, and tables. When you click something, it can send a request to the server or show a message. |
| **Backend** | **The server** (your computer with XAMPP, or a web server) | Receives your requests, reads or saves data in the database, and sends back results (e.g. list of courses, success/error). |

- **Frontend** = what you see and click (pages, styles, buttons, dropdowns).
- **Backend** = the logic and data (login check, save course, generate timetable, read from database).

The **database** is where all data is stored (departments, courses, faculty, students, rooms, sessions, timetable). The backend talks to the database; the frontend talks to the backend.

---

## 4. What Technologies Are Used? (In Simple Words)

| Name | Used for | Simple idea |
|------|----------|-------------|
| **HTML** | Structure of pages | Defines headings, forms, buttons, tables (like the skeleton of the page). |
| **CSS** | Look of pages | Colors, fonts, spacing, layout (how the page looks). |
| **JavaScript** | Behavior in the browser | Reacts to clicks, loads data without full page refresh, shows messages (toasts). |
| **jQuery** | Easier JavaScript | A library that makes it simpler to select elements and send requests (e.g. load list of sessions). |
| **Bootstrap** | Ready-made design | A library for buttons, forms, tables, modals, so the site looks clean and works on mobile too. |
| **Bootstrap Icons** | Small pictures | Icons like the eye for “show/hide password.” |
| **PHP** | Backend logic | Runs on the server. Handles login, saves data, reads from database, generates timetable. |
| **MySQL** | Database | Stores all data in tables (departments, courses, faculty, students, rooms, schedule, etc.). |
| **AJAX** | Loading data without reload | The page sends a small request (e.g. “give me list of courses”) and gets back data (often JSON) without refreshing the whole page. |
| **JSON** | Data format | A simple text format used when the frontend and backend send each other lists or messages (e.g. `{"success": true, "message": "Saved"}`). |

So:

- **Frontend** = HTML + CSS + JavaScript (and jQuery, Bootstrap, Bootstrap Icons).  
- **Backend** = PHP (and it uses MySQL to read/write the database).  
- **Communication** = AJAX with JSON.

---

## 5. Folder Structure — What Each Folder Is For

The project is organized in **folders**. Each folder has a clear role.

| Folder | Purpose (simple) | Type of code |
|--------|-------------------|--------------|
| **auth/** | Login page, checking who is logged in, logout | PHP (backend) + HTML/JS (frontend in login page) |
| **admin/** | All admin pages: departments, courses, faculty, students, rooms, sessions, generate timetable, substitutions | PHP pages + HTML + JavaScript (and Bootstrap) |
| **faculty/** | Faculty pages: my timetable, availability, substitution request | PHP pages + HTML + JavaScript |
| **student/** | Student pages: my timetable, course enrollment | PHP pages + HTML + JavaScript |
| **actions/** | “Behind-the-scenes” endpoints that the pages call to get or save data (e.g. list of courses, save a course, generate timetable) | PHP only (returns JSON) |
| **config/** | Settings: database connection, validation rules, standard way to return success/error | PHP only |
| **database/** | Scripts to create tables and add sample data | SQL + PHP (seed, refresh) |
| **assets/css/** | One main file for styles (colors, layout, buttons) | CSS |
| **assets/js/** | Shared scripts (e.g. show a toast message, password toggle) | JavaScript (jQuery) |

There are also **single files** in the root:

- **index.php** — First page; redirects you to login or to admin/faculty/student area.
- **install.php** — One-time setup: creates the database and tables and the default admin user.

---

## 6. Modules — What Each Part Does and What Code It Uses

A **module** here means a group of files that together do one job (e.g. “login” or “admin courses”).

---

### 6.1 Auth Module (Login / Logout / Who Is Logged In)

**What it does:**  
Shows the login page. When you enter email and password, it checks if you are Admin, Faculty, or Student and sends you to the right area. It also protects pages so that only the right role can open them (e.g. only admin can open admin pages).

**Main files:**

| File | What it does | Code type |
|------|--------------|-----------|
| `auth/login.php` | Displays the login form (email, password, eye toggle) | HTML + CSS + JavaScript (jQuery) |
| `auth/process_login.php` | Receives email/password, checks database, sets session, returns redirect | PHP |
| `auth/check_role.php` | Included on every admin/faculty/student page; checks if user is logged in and has the right role | PHP |
| `auth/logout.php` | Clears the session and sends user back to login | PHP |

**Flow:**  
Browser shows `login.php` → user submits → browser sends data to `process_login.php` → PHP checks admin, then faculty, then student table → if match, PHP sets session and returns “go to admin” (or faculty/student) → browser redirects.

---

### 6.2 Admin Module

**What it does:**  
Lets the admin manage all master data (departments, courses, faculty, students, rooms, academic sessions), assign faculty to courses, **generate** the timetable (with conflict checks), **move** a class to another room/slot, and **approve or reject** faculty substitution requests.

**Main files:**

| File | What it does | Code type |
|------|--------------|-----------|
| `admin/header.php` | Common top of every admin page: menu (Courses, Faculty, Students, Rooms, etc.), scripts (jQuery, Bootstrap, app.js), Bootstrap Icons | HTML + PHP |
| `admin/footer.php` | Common bottom of every admin page | HTML + PHP |
| `admin/index.php` | Dashboard: counts (departments, courses, faculty, …) and links to each section | PHP + HTML |
| `admin/departments.php` | List, add, edit, delete departments | PHP + HTML + JavaScript (AJAX calls to `actions/departments.php`) |
| `admin/courses.php` | List, add, edit, delete courses; “Assign Faculty” to courses (for timetable generation) | PHP + HTML + JavaScript (AJAX to `actions/courses.php`, `actions/course_faculty.php`) |
| `admin/faculty.php` | List, add, edit, delete faculty (with password field and eye toggle) | PHP + HTML + JavaScript (AJAX to `actions/faculty.php`) |
| `admin/students.php` | List, add, edit, delete students (with password field and eye toggle) | PHP + HTML + JavaScript (AJAX to `actions/students.php`) |
| `admin/rooms.php` | List, add, edit, delete rooms | PHP + HTML + JavaScript (AJAX to `actions/rooms.php`) |
| `admin/sessions.php` | List, add, edit, delete academic sessions (name, start date, end date) | PHP + HTML + JavaScript (AJAX to `actions/sessions.php`) |
| `admin/generate.php` | Select session, semester, section; choose which courses to include; generate timetable; view schedule; move a class (with conflict check) | PHP + HTML + JavaScript (AJAX to `actions/generate.php`, `actions/schedule.php`, `actions/schedule_move.php`, etc.) |
| `admin/substitutions.php` | List all substitution requests; approve or reject (with notes) | PHP + HTML + JavaScript (AJAX to `actions/substitution.php`) |

**Pattern:**  
Each admin page loads data by calling the right file in **actions/** (e.g. `actions/courses.php`). Those **actions** return **JSON**. The JavaScript on the page gets that JSON and fills the table or shows a message (toast).

---

### 6.3 Faculty Module

**What it does:**  
Lets faculty see their timetable, save availability preferences, and submit substitution requests. They can also export their timetable to calendar (.ics).

**Main files:**

| File | What it does | Code type |
|------|--------------|-----------|
| `faculty/header.php` | Common top: menu (My Timetable, Availability, Substitution, Logout), jQuery, Bootstrap, app.js, Bootstrap Icons, toast container | HTML + PHP |
| `faculty/footer.php` | Common bottom | HTML + PHP |
| `faculty/index.php` | My Timetable: choose session, see schedule (only their classes), export to calendar | PHP + HTML + JavaScript (calls `actions/sessions.php`, `actions/schedule.php`, `actions/export.php`) |
| `faculty/availability.php` | Form to enter “availability notes” (e.g. no Fridays); save to database | PHP + HTML + JavaScript (calls `actions/faculty_availability.php`) |
| `faculty/substitution.php` | Choose session, choose a scheduled class, enter date and reason; submit request; see list of “My requests” (pending/approved/rejected) | PHP + HTML + JavaScript (calls `actions/sessions.php`, `actions/schedule.php`, `actions/substitution.php`) |

---

### 6.4 Student Module

**What it does:**  
Lets students see their timetable (filtered by their semester and section), enroll in courses (with prerequisite check), and export timetable to Excel (CSV), PDF, or Calendar (.ics).

**Main files:**

| File | What it does | Code type |
|------|--------------|-----------|
| `student/header.php` | Common top: menu (My Timetable, Enrollment, Logout), jQuery, Bootstrap, app.js, toast container | HTML + PHP |
| `student/footer.php` | Common bottom | HTML + PHP |
| `student/index.php` | My Timetable: choose session, see schedule (only their semester/section), export CSV/PDF/ICS | PHP + HTML + JavaScript (calls `actions/sessions.php`, `actions/schedule.php`, `actions/export.php`) |
| `student/enrollment.php` | Shows list of courses they can enroll in (for current session); Enroll button; checks prerequisite | PHP + HTML + JavaScript (calls `actions/enrollment.php`) |

---

### 6.5 Actions Module (Backend APIs)

**What it does:**  
These files do **not** show a page. They are called by the frontend (admin/faculty/student pages) when the user does something (e.g. “load courses,” “save faculty,” “generate timetable”). They read or write the database and return **JSON** (e.g. `{ "success": true, "message": "Saved" }` or a list of items).

**Main files:**

| File | What it does | Called by |
|------|--------------|-----------|
| `actions/departments.php` | Get list, add, update, delete departments | Admin (departments, and dropdowns in courses/faculty/students) |
| `actions/courses.php` | Get list, add, update, delete courses | Admin (courses) |
| `actions/course_faculty.php` | Get faculty assigned to a course; add; remove | Admin (courses – Assign Faculty) |
| `actions/faculty.php` | Get list, add, update, delete faculty | Admin (faculty, courses) |
| `actions/students.php` | Get list, add, update, delete students | Admin (students) |
| `actions/rooms.php` | Get list, add, update, delete rooms | Admin (rooms, generate move) |
| `actions/sessions.php` | Get list (all roles); add/update/delete (admin only) | Admin, Faculty, Student (session dropdowns) |
| `actions/time_slots.php` | Get list of time slots (Mon-1, Tue-2, …) | Admin (generate – move) |
| `actions/schedule.php` | Get schedule (by session; for admin all, for faculty only their classes, for student only their semester/section) | Admin, Faculty, Student |
| `actions/generate.php` | Generate timetable: place courses in slots avoiding teacher and room conflicts; optional “only these courses” | Admin (generate) |
| `actions/schedule_move.php` | Move one class to another room/slot; check conflicts before saving | Admin (generate – move) |
| `actions/substitution.php` | Faculty: get my requests, create new request. Admin: get all requests, approve/reject | Faculty, Admin |
| `actions/faculty_availability.php` | Get or save faculty availability notes | Faculty (availability) |
| `actions/enrollment.php` | Get courses available for enrollment (for current session); enroll (with prerequisite check) | Student (enrollment) |
| `actions/export.php` | Export timetable as CSV, PDF, or .ics (calendar) | Faculty, Student |

**Code type:** All are **PHP**. They use **JSON** to send data back.

---

### 6.6 Config Module

**What it does:**  
Central place for database connection and shared helpers so the rest of the code stays simple.

| File | What it does | Code type |
|------|--------------|-----------|
| `config/db.php` | Connects to MySQL (database name, user, password). Required by almost all PHP that uses the database. | PHP |
| `config/db.example.php` | Example of how to set database settings (for production or another environment). | PHP |
| `config/constants.php` | Maximum lengths for names, codes, etc. (used by validation). | PHP |
| `config/validation.php` | Reusable functions to check input (e.g. “name too long”). | PHP |
| `config/api_response.php` | Helper functions: send a JSON error or success message in a standard format. | PHP |

---

### 6.7 Database Module

**What it does:**  
Defines the **structure** of the database (tables and columns) and provides scripts to fill it with sample data or reset it.

| File | What it does | Code type |
|------|--------------|-----------|
| `database/schema.sql` | Creates the database and all tables (department, course, faculty, student, room, time_slot, academic_session, schedule, enrollment, substitution_request, etc.) and inserts default time slots (Mon-1 to Fri-5). | SQL |
| `database/seed.php` | Inserts sample data: departments, students, faculty, courses, course–faculty links, rooms, session. Run after install to have data to test with. | PHP + SQL logic |
| `database/refresh_db.php` | Drops and recreates the database (reset). Used by the refresh script (e.g. `window_refresh_db.bat`). | PHP |
| `install.php` (in root) | One-time: runs `schema.sql`, creates default admin user. | PHP |

---

## 7. Database — What Is Stored (Simple Idea)

The **database** is like a set of **tables** (like Excel sheets). Each table stores one kind of thing.

| Table | Stores |
|-------|--------|
| **department** | Department name and code (e.g. Computer Science, CS). |
| **academic_session** | Session name and start/end date (e.g. Fall 2026, 2026-09-01 to 2027-01-31). |
| **admin** | Admin users (email, hashed password, name). |
| **faculty** | Teachers (email, hashed password, name, department, availability notes). |
| **student** | Students (email, hashed password, name, department, semester, section). |
| **course** | Courses (code, name, credit hours, semester, department, sessions per week, optional prerequisite). |
| **room** | Rooms (room number, capacity, type: classroom/lab/hall). |
| **time_slot** | Time slots (e.g. Mon-1 = Monday 08:00–08:50). |
| **course_faculty** | Which faculty can teach which course (for timetable generation). |
| **schedule** | The generated timetable: which course, which faculty, which room, which slot, for which semester and section, in which session. |
| **enrollment** | Which student is enrolled in which course for which session. |
| **substitution_request** | Faculty requests for a substitute (which class, date, reason, status: pending/approved/rejected). |

**Relations:**  
Tables are linked by IDs (e.g. a course has `department_id`, a schedule row has `course_id`, `faculty_id`, `room_id`, `time_slot_id`). This way the system knows “this row in schedule is Programming I, Dr. Ahmed, Room R101, Mon-1.”

---

## 8. What Each User Role Can Do (Summary)

| Role | Main actions |
|------|----------------|
| **Admin** | Manage departments, sessions, courses, faculty, students, rooms. Assign faculty to courses. Generate timetable (with optional choice of which courses). View schedule. Move a class to another room/slot (with conflict check). Approve or reject substitution requests. |
| **Faculty** | View own timetable. Set availability notes. Request a substitute for a class. Export timetable to calendar. |
| **Student** | View timetable (by session; filtered by semester/section). Enroll in courses (with prerequisite check). Export timetable to Excel (CSV), PDF, or Calendar (.ics). |

---

## 9. Shared Frontend Code (Used by Several Pages)

| File | What it does |
|------|--------------|
| **assets/css/style.css** | Global styles: colors, header, cards, buttons, login box, toasts. |
| **assets/js/app.js** | `showToast()` to show success/error messages; `escapeHtml()`; AJAX setup; **password eye toggle** (show/hide) for any input with class `.pw-toggle-btn`. |
| **assets/js/validation.js** | Client-side checks (e.g. max length for course code/name) used on admin forms. |

Admin and faculty/student **headers** include: jQuery, Bootstrap CSS and JS, Bootstrap Icons (admin), app.js, and a variable `base` used for API paths (e.g. `base + 'actions/courses.php'`).

---

## 10. Security (Simple Idea)

- **Passwords** are not stored as plain text; they are **hashed** (one-way) in the database.
- **Login** creates a **session** (server remembers who is logged in and their role).
- **Each protected page** includes `check_role.php`: if the user is not logged in or has the wrong role, they are redirected to the login page.
- **Actions** (e.g. `actions/courses.php`) check the session and role before doing anything (e.g. only admin can add a course).
- **Database** access uses **prepared statements** (parameters are bound) to avoid injection.

---

## 11. Glossary (For Non-Programmers)

| Term | Meaning |
|------|--------|
| **Frontend** | The part that runs in your browser: pages, forms, buttons, styles, and scripts that react to clicks. |
| **Backend** | The part that runs on the server: PHP scripts that handle requests and talk to the database. |
| **Database** | A structured store of data (tables with rows and columns). Here: MySQL. |
| **Session** | How the server “remembers” who is logged in (role and user id) until they log out or close the browser. |
| **API** | A “service” the frontend calls (e.g. “give me list of courses”). Here, the **actions** folder contains these APIs; they return JSON. |
| **JSON** | A text format for sending data (e.g. `{"success": true, "list": [...]}`). |
| **AJAX** | Sending a request and getting a response without reloading the whole page (e.g. loading the course list into a table). |
| **CRUD** | Create, Read, Update, Delete (basic operations on data). |
| **Toast** | A small pop-up message (e.g. “Saved” or “Error”) that appears for a few seconds. |
| **Modal** | A pop-up box on the page (e.g. “Add Course” form) that overlays the rest of the page. |

---

## 12. Other Documents You Can Use

| Document | Use |
|----------|-----|
| **[INSTRUCTIONS.md](INSTRUCTIONS.md)** | How to run the project (which script to double-click, login details). |
| **[README.md](README.md)** | Short overview, quick start, requirements. |
| **[PROJECT_STRUCTURE_AND_SRS.md](PROJECT_STRUCTURE_AND_SRS.md)** | Folder structure and how the project meets the SRS (requirements). |
| **[SRS_SUMMARY_AND_WORKFLOWS.md](SRS_SUMMARY_AND_WORKFLOWS.md)** | What each role can do (workflows). |
| **[DEPLOYMENT.md](DEPLOYMENT.md)** | How to put the project on a real server (production). |

---

This document gives you a **complete picture** of the project: what it does, how it is split into frontend and backend, what each folder and module is for, what type of code is used where, and how the database fits in — all in a way that does not assume you already know programming.
