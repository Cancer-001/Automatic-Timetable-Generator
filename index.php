<?php
/**
 * Timetable Management System - Entry redirect to login
 */
session_start();
$base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
if ($base === '' || $base === '/') $base = '';
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') header('Location: ' . $base . '/admin/');
    elseif ($role === 'faculty') header('Location: ' . $base . '/faculty/');
    elseif ($role === 'student') header('Location: ' . $base . '/student/');
}
header('Location: ' . $base . '/auth/login.php');
exit;
