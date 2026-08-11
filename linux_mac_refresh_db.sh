#!/usr/bin/env bash
# Timetable System - Linux/Mac: Reset database to fresh state
# Students: ./linux_mac_refresh_db.sh (type YES to confirm)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_CMD="php"
[[ -x "/opt/lampp/bin/php" ]] && PHP_CMD="/opt/lampp/bin/php"
[[ -x "/Applications/XAMPP/xamppfiles/bin/php" ]] && PHP_CMD="/Applications/XAMPP/xamppfiles/bin/php"

echo "========================================="
echo "Timetable System - Refresh Database"
echo "========================================="
read -p "Type YES to delete all data: " confirm
[[ "$confirm" != "YES" ]] && { echo "Cancelled."; exit 0; }
"$PHP_CMD" "$SCRIPT_DIR/database/refresh_db.php"
echo "Optional: ./linux_mac_runseed.sh to add sample data again."
