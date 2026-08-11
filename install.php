<?php
/**
 * One-time install: create DB, run schema + migrations, seed default admin.
 * Run from browser: http://localhost/assigment/install.php
 * Delete or protect this file after use.
 */
$conn = new mysqli('localhost', 'root', '', '');
if ($conn->connect_error) {
    die('MySQL connection failed: ' . $conn->connect_error);
}

// Run schema (creates DB + all tables)
$sql = file_get_contents(__DIR__ . '/database/schema.sql');
if (!$conn->multi_query($sql)) {
    die('Schema error: ' . $conn->error);
}
while ($conn->more_results() && $conn->next_result()) { ; }

$conn->select_db('assignmentupdated');

// Run migrations (adds any missing columns to existing tables)
$migSql = file_get_contents(__DIR__ . '/database/migrations.sql');
// Strip USE statement (already on correct DB)
$migSql = preg_replace('/^\s*USE\s+\S+;\s*/mi', '', $migSql);
if (trim($migSql)) {
    if (!$conn->multi_query($migSql)) {
        // Non-fatal: some ALTERs may warn if columns already exist
        echo 'Migration note: ' . $conn->error . '<br>';
    }
    while ($conn->more_results() && $conn->next_result()) { ; }
}

// Insert default admin (correct mysqli API)
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT IGNORE INTO admin (email, password_hash, full_name) VALUES (?, ?, ?)');
$email = 'admin@isp.edu.pk';
$name  = 'System Admin';
$stmt->bind_param('sss', $email, $hash, $name);
$stmt->execute();

echo '<b>Install complete.</b><br>';
echo 'Default login: <b>admin@isp.edu.pk</b> / <b>admin123</b><br>';
echo '<a href="auth/login.php">Go to Login</a>';
$conn->close();
