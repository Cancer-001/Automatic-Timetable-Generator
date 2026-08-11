<?php
$pageTitle = $pageTitle ?? 'Student';
$base = '../';
$currentPath = basename($_SERVER['SCRIPT_NAME'] ?? '');
$styleVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Timetable System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $base; ?>assets/css/style.css?v=<?php echo $styleVersion; ?>" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base; ?>assets/js/app.js"></script>
    <script>var base = '<?php echo addslashes($base); ?>';</script>
</head>
<body>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index: 9999;"></div>
<div class="app-wrapper">
<nav class="navbar navbar-expand-lg navbar-dark app-header">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">📅 Timetable System <span class="badge bg-light text-primary ms-1">Student</span></a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link<?php echo $currentPath === 'index.php' ? ' active' : ''; ?>" href="index.php">My Timetable</a>
            <a class="nav-link<?php echo $currentPath === 'calendar.php' ? ' active' : ''; ?>" href="calendar.php">Calendar</a>
            <a class="nav-link" href="<?php echo $base; ?>auth/logout.php">Logout</a>
        </div>
    </div>
</nav>
<div class="app-main">
