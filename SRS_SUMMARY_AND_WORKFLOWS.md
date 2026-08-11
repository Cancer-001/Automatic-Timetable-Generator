# SRS Summary and User Workflows

This document summarizes the **Software Requirements Specification (SRS)** for the Automatic Timetable Management System and describes **what Admin and Student (and Faculty) can do** in the application.

---

## 1. SRS Overview

**Product:** Automatic Timetable Generator — a web-based system to automate academic timetable scheduling.

**Tech stack (per SRS):** PHP backend, MySQLi, HTML5, CSS3, JavaScript, jQuery, AJAX, Bootstrap. XAMPP; Windows or Linux.

**User roles:** Admin, Faculty, Student.

---

## 2. SRS Requirements Summary

### 2.1 Admin (SRS)

- **Resource management:** Add / Edit / Delete Room, Faculty, Department.
- **Course/Subject:** Add (name, code, semester, sessions per week, instructor), Edit, Delete.
- **Instructor:** Add (name, department, subjects, availability), Edit, Delete.
- **Classroom:** Add (room number, capacity), Edit, Delete.
- **Timetable:** Select academic session, pull constraints, run optimization → schedule generated.
- **Conflict resolution:** Manual move class, real-time conflict check, alert, confirm move.
- **Substitution:** Approve or reject faculty substitution requests.

### 2.2 Faculty (SRS)

- Login, view personal timetable.
- Submit availability or preferences.
- Request substitution → notify admin for approval.

### 2.3 Student (SRS)

- Login, view filtered timetable (by semester, department, section).
- Export schedule: Download PDF, Export (e.g. Excel).
- View courses, check prerequisites, enroll (course enrollment).

### 2.4 Reporting & Export (SRS)

- View timetable by class, semester, or instructor.
- Export in PDF or Excel (plus calendar .ics in this project).
- Optional notifications for changes/conflicts.

### 2.5 Non-Functional (SRS)

- **Security:** Passwords hashed, role-based access.
- **Performance:** e.g. generation &lt; 30s, queries &lt; 3s.
- **Compatibility:** Modern browsers, mobile-friendly.

---

## 3. SRS vs This Project

| SRS requirement | This project |
|------------------------------------|-------------|
| Admin: rooms, faculty, departments | ✅ |
| Admin: courses (add/edit/delete) | ✅ |
| Admin: timetable generation | ✅ |
| Admin: manual move + conflict check | ✅ |
| Admin: substitution approval | ✅ |
| Faculty: view timetable | ✅ |
| Faculty: availability/preferences | ✅ |
| Faculty: substitution request | ✅ |
| Student: filtered timetable | ✅ |
| Student: PDF/Excel export | ✅ |
| Calendar integration (.ics) | ✅ |
| Student: view courses & enroll | ✅ |

---

## 4. Workflows: What Each Role Can Do

### 4.1 Admin workflow

1. **Login**  
   - Admin logs in via `auth/login.php`; session stores role and user id.

2. **Manage master data**  
   - **Departments:** Add, edit, delete departments (name, code).  
   - **Courses:** Add, edit, delete courses (code, name, credit hours, semester, sessions per week, department). Assign faculty to courses.  
   - **Faculty:** Add, edit, delete faculty (name, email, department, availability notes).  
   - **Students:** Add, edit, delete students (name, email, department, semester, section).  
   - **Rooms:** Add, edit, delete rooms (room number, capacity, type).  
   - **Academic sessions:** Add, edit, delete sessions (name, start/end date, active).

3. **Generate timetable**  
   - Go to **Generate** page.  
   - Select **academic session**, **semester**, **section**.  
   - Optionally clear existing schedule for that session/semester/section.  
   - Click **Generate**. System creates schedule using courses, faculty, rooms, and time slots; avoids double-booking faculty and rooms.

4. **Adjust schedule manually**  
   - On the generate/schedule view, **move** a class to another slot (different time/room).  
   - System runs **conflict check** (same faculty or room in same slot).  
   - If conflict: show alert; admin resolves (e.g. choose another slot).  
   - Confirm move to save.

5. **Substitution requests**  
   - Open **Substitutions** page.  
   - See all faculty substitution requests (pending, approved, rejected).  
   - For **pending** requests: **Approve** or **Reject**, optionally add admin notes.  
   - Changes are saved via API; faculty can see status.

6. **Navigation**  
   - Dashboard, Departments, Courses, Faculty, Students, Rooms, Sessions, Generate, Substitutions, Logout.

---

### 4.2 Student workflow

1. **Login**  
   - Student logs in; session stores role and student id.

2. **View timetable**  
   - Go to **My Timetable**.  
   - Select **academic session** from dropdown.  
   - Timetable is **filtered** by student’s department, semester, and section.  
   - Table shows: course, faculty, room, slot, time.

3. **Export schedule**  
   - **Export Excel (CSV):** Download CSV of current timetable.  
   - **Export / Print PDF:** Open PDF in new window for print or save.  
   - **Export to Calendar:** Download .ics file for calendar apps (e.g. Google Calendar, Outlook).

4. **Course enrollment**  
   - Go to **Enrollment**.  
   - See **available courses** for the student’s department/semester and current academic session.  
   - Table shows: code, name, credits, semester, **prerequisite** (if any).  
   - **Enroll** button per course.  
   - If course has a prerequisite, system checks that the student has completed or is enrolled in it; otherwise shows error.  
   - Success/error shown as toast; list refreshes (e.g. “Enrolled” or disabled button).

5. **Navigation**  
   - My Timetable, Enrollment, Logout.

---

### 4.3 Faculty workflow (brief)

1. **Login**  
   - Faculty logs in; session stores role and faculty id.

2. **View timetable**  
   - **My Timetable:** View personal schedule for selected academic session.

3. **Availability**  
   - **Availability** page: Enter or edit **availability notes** (preferred days/times off).  
   - **Save preferences**; stored in `faculty.availability_notes`.

4. **Substitution**  
   - **Substitution** page: Submit a substitution request (e.g. date, schedule slot, reason).  
   - Admin approves or rejects; faculty can see status.

5. **Export**  
   - Export timetable (e.g. PDF/Excel/.ics) if the same export actions are offered on faculty timetable page.

6. **Navigation**  
   - My Timetable, Availability, Substitution, Logout.

---

## 5. Related docs

- **INSTRUCTIONS.md** — How to run the project (setup, run, seed, refresh DB).  
- **PROJECT_STRUCTURE_AND_SRS.md** — Folder structure and SRS achievement table.  
- **DEPLOYMENT.md** — Production deployment.  
- **README.md** — Project overview.
