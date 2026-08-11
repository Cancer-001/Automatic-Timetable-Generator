<?php
$pageTitle = $pageTitle ?? 'Admin';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo $base; ?>assets/css/style.css?v=<?php echo $styleVersion; ?>" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base; ?>assets/js/app.js"></script>
    <script src="<?php echo $base; ?>assets/js/validation.js"></script>
    <script>var base = '<?php echo addslashes($base); ?>';</script>
</head>
<body>
<!-- Toast container for success/error messages -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index: 9999;"></div>

<div class="app-wrapper">
<nav class="navbar navbar-expand-lg navbar-dark app-header">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">📅 Timetable System <span class="badge bg-light text-primary ms-1">Admin</span></a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link<?php echo $currentPath === 'courses.php' ? ' active' : ''; ?>" href="courses.php">Courses</a>
            <a class="nav-link<?php echo $currentPath === 'faculty.php' ? ' active' : ''; ?>" href="faculty.php">Faculty</a>
            <a class="nav-link<?php echo $currentPath === 'students.php' ? ' active' : ''; ?>" href="students.php">Students</a>
            <a class="nav-link<?php echo $currentPath === 'rooms.php' ? ' active' : ''; ?>" href="rooms.php">Rooms</a>
            <a class="nav-link<?php echo $currentPath === 'departments.php' ? ' active' : ''; ?>" href="departments.php">Departments</a>
            <a class="nav-link<?php echo $currentPath === 'degrees.php' ? ' active' : ''; ?>" href="degrees.php">Program</a>
            <a class="nav-link<?php echo $currentPath === 'sessions.php' ? ' active' : ''; ?>" href="sessions.php">Sessions</a>
            <a class="nav-link<?php echo $currentPath === 'calendar.php' ? ' active' : ''; ?>" href="calendar.php">Calendar</a>
            <a class="nav-link<?php echo $currentPath === 'generate.php' ? ' active' : ''; ?>" href="generate.php">Generate</a>
            <a class="nav-link<?php echo $currentPath === 'substitutions.php' ? ' active' : ''; ?>" href="substitutions.php">Substitutions</a>
            <a class="nav-link" href="<?php echo $base; ?>auth/logout.php">Logout</a>
        </div>
    </div>
</nav>
<div class="app-main">
