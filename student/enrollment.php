<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('student');
header('Location: index.php');
exit;
