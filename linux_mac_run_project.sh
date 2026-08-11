#!/usr/bin/env bash
# Timetable System - Linux/Mac: Start project and open in browser
# Students: ./linux_mac_run_project.sh

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="assigment"

if [[ -d "/opt/lampp" ]]; then
    sudo /opt/lampp/lampp start 2>/dev/null || true
    sleep 2
    URL="http://localhost/$PROJECT_NAME/"
    HTDOCS="/opt/lampp/htdocs"
elif [[ -d "/Applications/XAMPP/xamppfiles" ]]; then
    /Applications/XAMPP/xamppfiles/xampp start 2>/dev/null || true
    sleep 2
    URL="http://localhost/$PROJECT_NAME/"
    HTDOCS="/Applications/XAMPP/xamppfiles/htdocs"
elif [[ -d "/Applications/MAMP" ]]; then
    open -a MAMP 2>/dev/null || true
    echo "Start Apache/MySQL in MAMP, then press Enter..."
    read -r
    URL="http://localhost:8888/$PROJECT_NAME/"
    HTDOCS="/Applications/MAMP/htdocs"
else
    URL="http://localhost/$PROJECT_NAME/"
fi

[[ -n "$HTDOCS" ]] && [[ -d "$HTDOCS" ]] && { mkdir -p "$HTDOCS"; cp -r "$SCRIPT_DIR" "$HTDOCS/$PROJECT_NAME"; }

echo "Opening: $URL  (Login: admin@isp.edu.pk / admin123)"
command -v xdg-open &>/dev/null && xdg-open "$URL" || { [[ "$OSTYPE" == "darwin"* ]] && open "$URL" || echo "Open: $URL"; }
