<?php
/**
 * Reset database to fresh state: drop all tables, recreate from schema, add default admin.
 * Run via REFRESH_DB.bat or: php database/refresh_db.php
 */
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/db.php';

echo "Refreshing database to fresh state...\n";

$conn->close();
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '');
if ($conn->connect_error) {
    die('MySQL connection failed: ' . $conn->connect_error . "\n");
}

$schemaPath = __DIR__ . '/schema.sql';
if (!is_file($schemaPath)) {
    die('Schema file not found: ' . $schemaPath . "\n");
}

$conn->query('SET FOREIGN_KEY_CHECKS = 0');

$sql = file_get_contents($schemaPath);
if (!$conn->multi_query($sql)) {
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');
    die('Schema error: ' . $conn->error . "\n");
}
while ($conn->more_results() && $conn->next_result()) {;}

$conn->query('SET FOREIGN_KEY_CHECKS = 1');
$conn->select_db(DB_NAME);

// Run migrations: create any tables missing from an old schema (e.g. course_faculty on friend's laptop).
// Some migration steps are intentionally idempotent and may raise duplicate warnings on fresh schema.
$migrationsPath = __DIR__ . '/migrations.sql';
if (is_file($migrationsPath)) {
    $migrationSql = file_get_contents($migrationsPath);
    // Remove USE statement so we don't switch DB; we're already on DB_NAME.
    $migrationSql = preg_replace('/^\s*USE\s+\S+;\s*/mi', '', $migrationSql);
    if (trim($migrationSql) !== '') {
        mysqli_report(MYSQLI_REPORT_OFF);
        if (!$conn->multi_query($migrationSql)) {
            echo "Migration warning: " . $conn->error . "\n";
        } else {
            do {
                if ($res = $conn->store_result()) {
                    $res->free();
                }
                $stepErr = $conn->error;
                if ($stepErr !== '') {
                    echo "Migration warning: " . $stepErr . "\n";
                }
            } while ($conn->more_results() && $conn->next_result());
            if ($conn->error !== '') {
                echo "Migration warning: " . $conn->error . "\n";
            }
            echo "Migrations applied (missing tables created if any).\n";
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
}

$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO admin (email, password_hash, full_name) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $email, $hash, $name);
$email = 'admin@isp.edu.pk';
$name = 'System Admin';
$stmt->execute();
$conn->close();

echo "Database refreshed. All data cleared, tables recreated, default admin restored.\n";
echo "Login: admin@isp.edu.pk / admin123\n";
echo "Run RUN_SEEDS.bat to add sample data (optional).\n";
