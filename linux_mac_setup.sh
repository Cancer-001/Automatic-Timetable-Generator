#!/usr/bin/env bash
# Timetable System - Linux/Mac: Setup project (after XAMPP/MAMP is installed)
# Students: chmod +x linux_mac_setup.sh then ./linux_mac_setup.sh

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="assigment"

detect_stack() {
    if [[ "$OSTYPE" == "darwin"* ]]; then
        if [[ -d "/Applications/XAMPP/xamppfiles" ]]; then
            XAMPP="/Applications/XAMPP"
            PHP_BIN="$XAMPP/xamppfiles/bin/php"
            HTDOCS="$XAMPP/xamppfiles/htdocs"
        elif [[ -d "/Applications/MAMP" ]]; then
            XAMPP="/Applications/MAMP"
            MAMP_PHP=$(ls -d /Applications/MAMP/bin/php/php* 2>/dev/null | tail -1)
            PHP_BIN="$MAMP_PHP/bin/php"
            HTDOCS="$XAMPP/htdocs"
        else
            echo "Install XAMPP or MAMP first. Then run this script."
            exit 1
        fi
    else
        if [[ -d "/opt/lampp" ]]; then
            XAMPP="/opt/lampp"
            PHP_BIN="$XAMPP/bin/php"
            HTDOCS="$XAMPP/htdocs"
        elif command -v php &>/dev/null; then
            PHP_BIN="$(command -v php)"
            HTDOCS="/var/www/html"
            [[ ! -d "$HTDOCS" ]] && HTDOCS="/var/www"
        else
            echo "Install XAMPP or LAMP first."
            exit 1
        fi
    fi
}

echo "========================================="
echo "Timetable System - Linux/Mac Setup"
echo "========================================="
detect_stack

[[ -z "$PHP_BIN" ]] && PHP_BIN="$(command -v php 2>/dev/null)" || true
[[ -z "$PHP_BIN" ]] && { echo "PHP not found."; exit 1; }

echo "Copying project to $HTDOCS/$PROJECT_NAME ..."
mkdir -p "$HTDOCS"
cp -r "$SCRIPT_DIR" "$HTDOCS/$PROJECT_NAME"

[[ -d "/opt/lampp" ]] && { sudo /opt/lampp/lampp start 2>/dev/null || true; sleep 3; }
[[ -d "/Applications/MAMP" ]] && { open -a MAMP 2>/dev/null; echo "Start Apache/MySQL in MAMP, then press Enter..."; read -r; }

"$PHP_BIN" "$HTDOCS/$PROJECT_NAME/install.php"

echo ""
echo "Setup complete. Run ./linux_mac_run_project.sh to start. Login: admin@isp.edu.pk / admin123"
