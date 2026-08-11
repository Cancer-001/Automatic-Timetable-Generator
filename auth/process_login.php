<?php
session_start();
require_once __DIR__ . '/../config/db.php';
// Ensure is_frozen column exists
$col = $conn->query("SHOW COLUMNS FROM student LIKE 'is_frozen'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE student ADD COLUMN is_frozen TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
}

header('Content-Type: application/json');

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

function app_redirect_path($role) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $base = dirname(dirname($script));
    $base = str_replace('\\', '/', $base);
    $base = rtrim($base, '/');
    if ($base === '' || $base === '.') {
        $base = '';
    }
    return $base . '/' . $role . '/';
}

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password required.']);
    exit;
}

// Check admin
$stmt = $conn->prepare('SELECT id, password_hash, full_name FROM admin WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows) {
    $row = $res->fetch_assoc();
    if (password_verify($password, $row['password_hash'])) {
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['role']    = 'admin';
        $_SESSION['name']    = $row['full_name'];
        echo json_encode(['success' => true, 'redirect' => app_redirect_path('admin')]);
        exit;
    }
}

// Check faculty
$stmt = $conn->prepare('SELECT id, password_hash, full_name FROM faculty WHERE email = ? AND is_active = 1 LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows) {
    $row = $res->fetch_assoc();
    if (password_verify($password, $row['password_hash'])) {
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['role']    = 'faculty';
        $_SESSION['name']    = $row['full_name'];
        echo json_encode(['success' => true, 'redirect' => app_redirect_path('faculty')]);
        exit;
    }
}

// Check student — also check is_frozen
$stmt = $conn->prepare('SELECT id, password_hash, full_name, COALESCE(is_frozen,0) AS is_frozen FROM student WHERE email = ? AND is_active = 1 LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows) {
    $row = $res->fetch_assoc();
    if (password_verify($password, $row['password_hash'])) {
        if ((int)($row['is_frozen'] ?? 0) === 1) {
            echo json_encode(['success' => false, 'message' => 'Your account has been frozen. Please contact the admin.']);
            exit;
        }
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['role']    = 'student';
        $_SESSION['name']    = $row['full_name'];
        echo json_encode(['success' => true, 'redirect' => app_redirect_path('student')]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
