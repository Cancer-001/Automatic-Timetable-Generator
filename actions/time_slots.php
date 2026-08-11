<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/schema_helpers.php';
if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false]);
    exit;
}
// Ensure slot_type column exists
db_add_column_if_missing($conn, 'time_slot', 'slot_type', "ENUM('lecture','lab') NOT NULL DEFAULT 'lecture' AFTER slot_label");

$res  = $conn->query("SELECT id, day_of_week, start_time, end_time, slot_label, COALESCE(slot_type,'lecture') AS slot_type FROM time_slot ORDER BY slot_type, day_of_week, start_time");
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
echo json_encode($rows);
