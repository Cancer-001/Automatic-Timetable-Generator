# Timetable Management System – Deployment Guide

This guide covers local development setup and production deployment on **Windows**, **Linux**, and **macOS**.

---

## Quick Start (All Platforms)

| Platform | One-command install | Setup (if stack exists) |
|----------|---------------------|--------------------------|
| **Windows** | `INSTALL_ALL.bat` (Run as Administrator) | `RUN_SETUP.bat` |
| **Linux** | `./install_all.sh` | `./setup.sh` |
| **macOS** | Install XAMPP/MAMP, then `./setup.sh` | `./setup.sh` |

**Default login:** `admin@isp.edu.pk` / `admin123`

---

## Windows

### Option A: Full install (recommended)

1. Right-click `INSTALL_ALL.bat` → **Run as administrator**
2. Approve UAC if prompted
3. When XAMPP Control Panel opens, click **Start** for Apache and MySQL
4. Open: http://localhost/assigment/

### Option B: XAMPP already installed

1. Double-click `RUN_SETUP.bat`
2. Start Apache and MySQL in XAMPP Control Panel when prompted
3. Open: http://localhost/assigment/

### Manual setup

1. Install [XAMPP](https://www.apachefriends.org/download.html) to `C:\xampp`
2. Copy this project to `C:\xampp\htdocs\assigment\`
3. Start Apache and MySQL in XAMPP Control Panel
4. Visit: http://localhost/assigment/install.php
5. Then: http://localhost/assigment/

---

## Linux

### Option A: Full install (downloads XAMPP)

```bash
chmod +x install_all.sh
./install_all.sh
```

Requires `curl` and `sudo`. XAMPP installs to `/opt/lampp`.

### Option B: XAMPP or LAMP already installed

```bash
chmod +x setup.sh
./setup.sh
```

Detects:
- **XAMPP** at `/opt/lampp`
- **LAMP** (system Apache + MySQL/MariaDB + PHP)

### Manual setup

**With XAMPP:**
```bash
sudo cp -r /path/to/assigment /opt/lampp/htdocs/
sudo /opt/lampp/lampp start
php /opt/lampp/htdocs/assigment/install.php
```

**With system LAMP (Ubuntu/Debian):**
```bash
sudo apt install apache2 mysql-server php php-mysqli
sudo cp -r /path/to/assigment /var/www/html/
sudo systemctl start apache2 mysql
cd /var/www/html/assigment && php install.php
```

---

## macOS

### Option A: XAMPP

1. Install [XAMPP for Mac](https://www.apachefriends.org/download.html) to `/Applications/XAMPP`
2. Run:
   ```bash
   chmod +x setup.sh
   ./setup.sh
   ```
3. Open: http://localhost/assigment/

### Option B: MAMP

1. Install [MAMP](https://www.mamp.info)
2. Run `./setup.sh` (MAMP will open; start Apache and MySQL)
3. Open: http://localhost/assigment/

---

## Production Deployment

### Requirements

- PHP 7.4+ with MySQLi
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ (or nginx with PHP-FPM)

### 1. Upload files

Upload the project to your web root, e.g.:

- Shared hosting: `public_html/timetable` or `www/timetable`
- VPS: `/var/www/html/timetable`

### 2. Configure database

Copy the example config and edit:

```bash
cp config/db.example.php config/db.php
```

Edit `config/db.php`:

```php
define('DB_HOST', 'localhost');      // or your DB host
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');
```

### 3. Create database and run install

**Option A – via browser (first time only):**

Visit: `https://yourdomain.com/timetable/install.php`

**Option B – via CLI:**

```bash
mysql -u user -p -e "CREATE DATABASE assignmentupdated CHARACTER SET utf8mb4;"
php install.php
```

**Option C – manual import:**

```bash
mysql -u user -p your_db < database/schema.sql
# Then add admin user (see README)
```

### 4. Secure install.php

After installation, restrict or remove `install.php`:

```apache
# In .htaccess or Apache config
<Files "install.php">
    Require ip 127.0.0.1
    # Require ip YOUR_OFFICE_IP
</Files>
```

Or rename/delete it:

```bash
mv install.php install.php.disabled
```

### 5. Apache configuration

**Virtual host (recommended):**

```apache
<VirtualHost *:80>
    ServerName timetable.yourdomain.com
    DocumentRoot /var/www/html/timetable
    
    <Directory /var/www/html/timetable>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/timetable_error.log
    CustomLog ${APACHE_LOG_DIR}/timetable_access.log combined
</VirtualHost>
```

**Subdirectory (e.g. /timetable):**

Ensure `AllowOverride All` is set so `.htaccess` works. No extra config needed.

### 6. Nginx configuration

```nginx
server {
    listen 80;
    server_name timetable.yourdomain.com;
    root /var/www/html/timetable;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 7. File permissions (Linux)

```bash
# Web server must read files
chown -R www-data:www-data /var/www/html/timetable

# Sessions and uploads (if any) need write access
chmod -R 755 /var/www/html/timetable
```

### 8. HTTPS (recommended)

Use Let’s Encrypt (Certbot):

```bash
sudo certbot --apache -d timetable.yourdomain.com
```

### 9. Change default admin password

Log in as admin and change the password, or update directly in the database.

---

## Environment checklist

| Item | Development | Production |
|------|-------------|------------|
| `config/db.php` | localhost, root, empty pass | Production DB credentials |
| `install.php` | Accessible for setup | Disabled or IP-restricted |
| HTTPS | Optional | **Required** |
| Error display | On (for debugging) | Off (`display_errors = 0`) |
| Session security | Default | Consider `session.cookie_secure` |

---

## Troubleshooting

**Database connection failed**
- Check `config/db.php` host, user, password, database name
- Ensure MySQL/MariaDB is running
- Confirm DB user has access to the database

**404 on subdirectory**
- Check Apache `AllowOverride All`
- Verify `mod_rewrite` is enabled (if used)

**Blank page / 500 error**
- Enable PHP error logging
- Check file permissions
- Confirm PHP MySQLi extension is installed: `php -m | grep mysqli`
