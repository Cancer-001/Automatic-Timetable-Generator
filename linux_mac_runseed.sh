#!/usr/bin/env bash
# Timetable System - Linux/Mac: Add sample data
# Students: ./linux_mac_runseed.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_CMD="php"
[[ -x "/opt/lampp/bin/php" ]] && PHP_CMD="/opt/lampp/bin/php"
[[ -x "/Applications/XAMPP/xamppfiles/bin/php" ]] && PHP_CMD="/Applications/XAMPP/xamppfiles/bin/php"

echo "========================================="
echo "Timetable System - Run Seeds"
echo "========================================="
"$PHP_CMD" "$SCRIPT_DIR/database/seed.php"
echo "Done. Run ./linux_mac_run_project.sh to open the app."
