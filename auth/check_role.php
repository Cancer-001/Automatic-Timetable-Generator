<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF'], 2) . '/auth/login.php');
    exit;
}

function require_role($allowed) {
    $role = $_SESSION['role'] ?? '';
    if ($role !== $allowed) {
        header('Location: ' . dirname($_SERVER['PHP_SELF'], 2) . '/auth/login.php');
        exit;
    }
}
