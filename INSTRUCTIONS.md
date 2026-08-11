# Timetable System – Instructions for Students

## Which files to run

Use **only** the scripts below. Other `.bat` or `.sh` files in the project are legacy; ignore them.

---

## New PC / Nothing installed (Windows)

If the project is on a **new laptop with nothing installed** (no XAMPP, no PHP):

1. **Unzip** the project to a folder (e.g. `Desktop\assigment`).
2. **Check what is missing**  
   Double-click **`window_checklist.bat`**. It shows [OK] or [MISSING] for each requirement (XAMPP, PHP, Apache, MySQL, services running, project in htdocs, database).
3. **Install and setup**  
   Right-click **`window_setup.bat`** → **Run as administrator**.
   - If XAMPP is not installed, the script downloads and installs it. **When the installer finishes, run `window_setup.bat` again** to complete the rest.
   - Each step is checked; if something is missing, it is installed or done.
4. Run **`window_checklist.bat`** again to verify all [OK]. Then run **`window_run_project.bat`**. Login: **admin@isp.edu.pk** / **admin123**

---

## Windows

| What you want to do        | File to run                |
|----------------------------|----------------------------|
| **All-in-one menu**        | `window_menu.bat`          |
| **One-click start**        | `window_quick_start.bat`   |
| **Check what is installed**| `window_checklist.bat`     |
| **First-time install**     | `window_setup.bat`         |
| **Start the app**          | `window_run_project.bat`   |
| **Add sample data**        | `window_runseed.bat`       |
| **Reset database**        | `window_refresh_db.bat`    |

### Steps (Windows)

1. **First time**
   - Run **`window_checklist.bat`** first to see what is missing (optional).
   - Right-click **`window_setup.bat`** → **Run as administrator**. If XAMPP was not installed, run **`window_setup.bat`** again after the installer finishes.
   - When XAMPP Control Panel opens, click **Start** for Apache and MySQL.
   - Wait until it says “Setup complete”.

2. **Optional: sample data**
   - Double-click **`window_runseed.bat`** to add departments, students, courses, etc.

3. **Every time you want to use the app**
   - **Option A:** Double-click **`window_quick_start.bat`** (one-click start).
   - **Option B:** Double-click **`window_menu.bat`** and choose 1. Start project.
   - **Option C:** Double-click **`window_run_project.bat`**.
   - The browser will open at `http://localhost/assigment/`.

4. **Login**
   - Email: **admin@isp.edu.pk**  
   - Password: **admin123**

5. **Reset everything (optional)**
   - Double-click **`window_refresh_db.bat`**, type **YES** when asked.
   - Then run **`window_runseed.bat`** again if you want sample data.

---

## Linux / Mac

| What you want to do        | File to run                    |
|----------------------------|--------------------------------|
| **First-time setup**       | `linux_mac_setup.sh`           |
| **Start the app**          | `linux_mac_run_project.sh`     |
| **Add sample data**        | `linux_mac_runseed.sh`         |
| **Reset database**         | `linux_mac_refresh_db.sh`      |

### Steps (Linux / Mac)

1. **Make scripts runnable (once)**
   ```bash
   chmod +x linux_mac_setup.sh linux_mac_run_project.sh linux_mac_runseed.sh linux_mac_refresh_db.sh
   ```

2. **First time**
   - Install XAMPP or MAMP from the official site if you don’t have it.
   - Run: **`./linux_mac_setup.sh`**
   - Follow any prompts (e.g. start Apache/MySQL in MAMP).

3. **Optional: sample data**
   - Run: **`./linux_mac_runseed.sh`**

4. **Every time you want to use the app**
   - Run: **`./linux_mac_run_project.sh`**
   - Browser will open at the app URL.

5. **Login**
   - Email: **admin@isp.edu.pk**  
   - Password: **admin123**

6. **Reset everything (optional)**
   - Run: **`./linux_mac_refresh_db.sh`**, type **YES** when asked.
   - Then run **`./linux_mac_runseed.sh`** again if you want sample data.

---

## Quick reference

| Platform   | Install (first time)   | Start app              | Add data        | Reset DB           |
|-----------|-------------------------|------------------------|-----------------|--------------------|
| **Windows** | `window_checklist.bat` then `window_setup.bat` | `window_run_project.bat` | `window_runseed.bat` | `window_refresh_db.bat` |
| **Linux/Mac** | `./linux_mac_setup.sh` | `./linux_mac_run_project.sh` | `./linux_mac_runseed.sh` | `./linux_mac_refresh_db.sh` |

**Default login:** admin@isp.edu.pk / admin123

For project structure and how the project matches the SRS, see **PROJECT_STRUCTURE_AND_SRS.md**.
