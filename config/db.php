<?php
/**
 * Database connection - MySQLi
 * XAMPP: Apache + MySQL must be running.
 * Auto-creates the database, all tables, and default admin on first run.
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'assignmentupdated');

// ── 1. Connect (no DB selected yet) ─────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die('MySQL connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ── 2. Create database if missing ───────────────────────────────────────────
$conn->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$conn->select_db(DB_NAME);

// ── 3. Create tables if missing (runs schema + migrations) ──────────────────
$tableCheck = $conn->query("SHOW TABLES LIKE 'admin'");
if ($tableCheck && $tableCheck->num_rows === 0) {
    $schemaPath = __DIR__ . '/../database/schema.sql';
    if (is_file($schemaPath)) {
        $sql = file_get_contents($schemaPath);
        $sql = preg_replace('/^\s*CREATE\s+DATABASE[^;]+;\s*/mi', '', $sql);
        $sql = preg_replace('/^\s*USE\s+\S+;\s*/mi', '', $sql);
        $conn->multi_query($sql);
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
    $migPath = __DIR__ . '/../database/migrations.sql';
    if (is_file($migPath)) {
        $mig = file_get_contents($migPath);
        $mig = preg_replace('/^\s*USE\s+\S+;\s*/mi', '', $mig);
        $conn->multi_query($mig);
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
}

// ── 4. Always ensure admin row exists with correct credentials ───────────────
// This runs on every request but is fast (single SELECT + conditional INSERT/UPDATE)
$adminEmail = 'admin@isp.edu.pk';
$adminPass  = 'admin123';
$adminName  = 'System Admin';

$chk = $conn->prepare('SELECT id FROM admin WHERE email = ? LIMIT 1');
if ($chk) {
    $chk->bind_param('s', $adminEmail);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    if (!$row) {
        // Admin row missing — insert it
        $ins = $conn->prepare('INSERT INTO admin (email, password_hash, full_name) VALUES (?, ?, ?)');
        if ($ins) {
            $ins->bind_param('sss', $adminEmail, $hash, $adminName);
            $ins->execute();
        }
    } else {
        // Admin row exists — update password to ensure it is correct
        $upd = $conn->prepare('UPDATE admin SET password_hash = ?, full_name = ? WHERE email = ?');
        if ($upd) {
            $upd->bind_param('sss', $hash, $adminName, $adminEmail);
            $upd->execute();
        }
    }
}
