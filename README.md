# Timetable Management System

Web-based timetable management system (SRS-based). PHP, MySQLi, Bootstrap, jQuery, AJAX.

**Supports:** Windows, Linux, macOS

## Quick Start

**For students:** see **[INSTRUCTIONS.md](INSTRUCTIONS.md)** for which files to run.

| Platform | Setup (first time) | Start app | Seed data | Refresh DB |
|----------|--------------------|-----------|-----------|------------|
| **Windows** | `window_setup.bat` | `window_run_project.bat` | `window_runseed.bat` | `window_refresh_db.bat` |
| **Linux/Mac** | `./linux_mac_setup.sh` | `./linux_mac_run_project.sh` | `./linux_mac_runseed.sh` | `./linux_mac_refresh_db.sh` |

**Default login:** `admin@isp.edu.pk` / `admin123`

- **Full project documentation (backend + frontend, for students):** [PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md)
- **Project structure & SRS:** [PROJECT_STRUCTURE_AND_SRS.md](PROJECT_STRUCTURE_AND_SRS.md)
- **Deployment:** [DEPLOYMENT.md](DEPLOYMENT.md)

## Requirements

- PHP 7.4+ with MySQLi
- MySQL 5.7+ or MariaDB
- Apache (or nginx with PHP-FPM)

**Development:** XAMPP, MAMP, or LAMP

## Setup (manual)

1. **Copy project** to your web root (e.g. `htdocs/assigment` or `/var/www/html/assigment`).

2. **Install database** – in browser open:  
   `http://localhost/assigment/install.php`  
   Creates database `assignmentupdated`, tables, time slots, and default admin.

3. **Default login (Admin)**  
   - Email: `admin@isp.edu.pk`  
   - Password: `admin123`

4. **Optional: run schema manually**  
   Create database `assignmentupdated`, import `database/schema.sql`. Add admin:
   ```sql
   INSERT INTO admin (email, password_hash, full_name) VALUES
   ('admin@isp.edu.pk', '$2y$10$...', 'System Admin');
   ```
   Use `password_hash('admin123', PASSWORD_DEFAULT)` in PHP for the hash.

## Directory structure

```
/assets       CSS, JS, images
/config       db.php (database connection)
/auth         login.php, logout.php, process_login.php, check_role.php
/admin        Admin dashboard (courses, faculty, students, rooms, departments, sessions, generate)
/faculty      Faculty dashboard (timetable, substitution requests)
/student      Student dashboard (timetable, export PDF/CSV)
/actions      PHP APIs for CRUD, generate, schedule, export, substitution, schedule_move
/database     schema.sql
```

## Features

- **Admin:** CRUD for Departments, Academic Sessions, Courses, Faculty, Students, Rooms. Assign faculty to courses (for generation). Generate timetable (with conflict checks). Manual move with real-time conflict check.
- **Faculty:** View personal timetable. Submit substitution requests.
- **Student:** View timetable (filtered by semester/section). Export to CSV (Excel) and print/PDF.
- **Conflict resolution:** Generator ensures no teacher double-booked, no room double-booked. Manual move API checks conflicts before saving.

## Config

Edit `config/db.php` if your MySQL user/password differ:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'assignmentupdated');
```

## Tech stack

- Backend: PHP (MySQLi)
- Frontend: HTML5, CSS3, JavaScript, jQuery, AJAX, Bootstrap 5
- Server: XAMPP (Apache + MySQL)
