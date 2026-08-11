<?php
session_start();
require_once __DIR__ . '/../config/db.php';
// Full login form and validation in Phase 3
header('Content-Type: text/html; charset=utf-8');

// Determine which role card (if any) is selected
$role = isset($_GET['role']) ? strtolower($_GET['role']) : '';
$roleTitles = [
    'admin' => 'Admin Login',
    'faculty' => 'Faculty Login',
    'student' => 'Student Login',
];
$portalTitles = [
    'admin' => 'Admin Portal',
    'faculty' => 'Faculty Portal',
    'student' => 'Student Portal',
];
$portalSubtitles = [
    'admin' => 'Secure access for system administrators',
    'faculty' => 'Access for teaching faculty',
    'student' => 'Access for enrolled students',
];
$currentTitle = $roleTitles[$role] ?? 'Timetable System Login';
$currentPortalTitle = $portalTitles[$role] ?? '';
$currentPortalSubtitle = $portalSubtitles[$role] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentTitle); ?> - Timetable System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .login-full-height {
            min-height: 100vh;
        }
        .login-hero-title {
            font-weight: 700;
            font-size: 2.25rem;
            letter-spacing: 0.03em;
        }
        @media (min-width: 768px) {
            .login-hero-title {
                font-size: 2.75rem;
            }
        }
        .login-hero-subtitle {
            font-size: 0.95rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .role-card {
            border-radius: 18px;
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.18);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }
        .role-card .card-body .small {
            font-weight: 400;
            opacity: 0.9;
        }
        .role-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.25);
            filter: brightness(1.03);
        }
        .role-card.role-admin {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        .role-card.role-faculty {
            background: linear-gradient(135deg, #f97316, #f59e0b);
        }
        .role-card.role-student {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }
        .role-card-muted {
            opacity: 0.9;
        }
        .login-panel {
            border-radius: 24px;
            border: none;
            box-shadow: none;
            overflow: hidden;
        }
        .login-panel-header {
            padding: 1.75rem 1.5rem 1.5rem;
            color: #f9fafb;
            text-align: center;
        }
        .login-panel-header.admin {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        .login-panel-header.faculty {
            background: linear-gradient(135deg, #f97316, #f59e0b);
        }
        .login-panel-header.student {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }
        .login-panel-icon {
            width: 60px;
            height: 60px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.12);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.9rem;
            font-size: 1.6rem;
        }
        .login-panel-header h5 {
            margin: 0 0 0.25rem;
            font-weight: 600;
        }
        .login-panel-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        .login-panel-body {
            background: #f9fafb;
            padding: 1.75rem 2rem 2rem;
        }
        .login-panel-body .form-label {
            font-size: 0.85rem;
            color: #4b5563;
        }
        .login-panel-body .form-control {
            border-radius: 999px;
            background-color: #e5edff;
            border-color: transparent;
        }
        .login-panel-body .form-control:focus {
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.35);
            border-color: #2563eb;
            background-color: #ffffff;
        }
        .login-panel-body .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .login-panel-body .input-group .btn-outline-secondary {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            border-color: transparent;
            background-color: #e5edff;
            box-shadow: none;
        }
        .login-panel-body .input-group .btn-outline-secondary:hover {
            background-color: #dbeafe;
        }
        .login-panel-body .btn-primary {
            border-radius: 999px;
            font-weight: 600;
            border: none;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: none;
        }
        .login-panel-body .btn-primary:hover {
            filter: brightness(1.05);
            box-shadow: none;
        }
        .login-panel-body .btn-outline-secondary {
            border-radius: 999px;
            font-weight: 500;
            background-color: #ffffff;
            border-color: #cbd5f5;
            box-shadow: none;
            color: #111827;
        }
        .login-panel-body .btn-outline-secondary:hover {
            background-color: #f1f5f9;
            color: #111827;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="container login-full-height d-flex flex-column justify-content-center">
        <div class="row justify-content-center text-center mb-5">
            <div class="col-12">
                <h1 class="login-hero-title">  Automatic Timetable Management System With Calendar Integration </h1>
                <!-- <div class="text-muted login-hero-subtitle mt-1">
                    Automatic Timetable Management System
                </div> -->
            </div>
        </div>
        <?php if ($role === ''): ?>
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="row g-3 justify-content-center">
                        <div class="col-12 col-md-4">
                            <a href="login.php?role=admin" class="text-decoration-none">
                                <div class="card h-100 role-card role-admin role-card-muted">
                                    <div class="card-body text-center">
                                        <h2 class="h5 fw-bold mb-1">ADMIN DASHBOARD</h2>
                                        <p class="small mb-0">Manage data & generate timetable</p>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="login.php?role=student" class="text-decoration-none">
                                <div class="card h-100 role-card role-student role-card-muted">
                                    <div class="card-body text-center">
                                        <h2 class="h5 fw-bold mb-1">STUDENT DASHBOARD</h2>
                                        <p class="small mb-0">View timetable & enroll in courses</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-md-4">
                            <a href="login.php?role=faculty" class="text-decoration-none">
                                <div class="card h-100 role-card role-faculty role-card-muted">
                                    <div class="card-body text-center">
                                        <h2 class="h5 fw-bold mb-1">FACULTY DASHBOARD</h2>
                                        <p class="small mb-0">View timetable & manage classes</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                       
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($role === 'admin' || $role === 'faculty' || $role === 'student'): ?>
            <div class="row justify-content-center">
                <div class="col-md-4 login-wrapper">
                    <div class="card login-panel mx-auto">
                        <div class="login-panel-header <?php echo htmlspecialchars($role); ?>">
                            <div class="login-panel-icon">
                                <?php if ($role === 'admin'): ?>
                                    <i class="bi bi-shield-lock-fill"></i>
                                <?php elseif ($role === 'faculty'): ?>
                                    <i class="bi bi-person-badge-fill"></i>
                                <?php else: ?>
                                    <i class="bi bi-mortarboard-fill"></i>
                                <?php endif; ?>
                            </div>
                            <h5><?php echo htmlspecialchars($currentPortalTitle); ?></h5>
                            <div class="login-panel-subtitle">
                                <?php echo htmlspecialchars($currentPortalSubtitle); ?>
                            </div>
                        </div>
                        <div class="login-panel-body">
                            <form id="loginForm">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" required placeholder="Enter email">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" name="password" id="password" class="form-control" required placeholder="Enter password">
                                        <button class="btn btn-outline-secondary pw-toggle-btn" type="button" aria-label="Show password" title="Show password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="loginError" class="alert alert-danger d-none mb-2"></div>
                                <button type="submit" class="btn btn-primary w-100 mb-2">Login to Dashboard</button>
                                <a href="login.php" class="btn btn-outline-secondary w-100">Go back</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).on('click', '.pw-toggle-btn', function() {
        var $btn = $(this), $input = $btn.closest('.input-group').find('input');
        if ($input.attr('type') === 'password') { $input.attr('type', 'text'); $btn.attr('aria-label', 'Hide password').attr('title', 'Hide password').find('i').removeClass('bi-eye').addClass('bi-eye-slash'); }
        else { $input.attr('type', 'password'); $btn.attr('aria-label', 'Show password').attr('title', 'Show password').find('i').removeClass('bi-eye-slash').addClass('bi-eye'); }
    });
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        var $err = $('#loginError').addClass('d-none');
        $.post('process_login.php', { email: $('#email').val(), password: $('#password').val() })
            .done(function(r) {
                if (r.success && r.redirect) {
                    var redirect = String(r.redirect).replace(/\\/g, '/');
                    if (redirect.charAt(0) !== '/') redirect = '/' + redirect.replace(/^\/+/, '');
                    window.location.href = redirect;
                }
                else { $err.removeClass('d-none').text(r.message || 'Login failed'); }
            })
            .fail(function(x) {
                var m = (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed';
                $err.removeClass('d-none').text(m);
            });
    });
    </script>
</body>
</html>
