<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';

$stats = [];
$r = $conn->query('SELECT COUNT(*) AS n FROM department');
$stats['departments'] = $r ? (int)$r->fetch_assoc()['n'] : 0;
$r = $conn->query('SELECT COUNT(*) AS n FROM course WHERE is_active = 1');
$stats['courses'] = $r ? (int)$r->fetch_assoc()['n'] : 0;
$r = $conn->query('SELECT COUNT(*) AS n FROM faculty WHERE is_active = 1');
$stats['faculty'] = $r ? (int)$r->fetch_assoc()['n'] : 0;
$r = $conn->query('SELECT COUNT(*) AS n FROM student WHERE is_active = 1');
$stats['students'] = $r ? (int)$r->fetch_assoc()['n'] : 0;
$r = $conn->query('SELECT COUNT(*) AS n FROM room WHERE is_active = 1');
$stats['rooms'] = $r ? (int)$r->fetch_assoc()['n'] : 0;
$r = $conn->query('SELECT COUNT(*) AS n FROM academic_session');
$stats['sessions'] = $r ? (int)$r->fetch_assoc()['n'] : 0;
$conn->close();
?>
<div class="container-fluid py-4">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">Manage courses, faculty, rooms, and generate timetables.</p>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-4 col-lg-2">
            <a href="departments.php" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold text-primary"><?php echo $stats['departments']; ?></div>
                        <div class="text-muted small">Departments</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-2">
            <a href="courses.php" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold text-primary"><?php echo $stats['courses']; ?></div>
                        <div class="text-muted small">Courses</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-2">
            <a href="faculty.php" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold text-primary"><?php echo $stats['faculty']; ?></div>
                        <div class="text-muted small">Faculty</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-2">
            <a href="students.php" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold text-primary"><?php echo $stats['students']; ?></div>
                        <div class="text-muted small">Students</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-2">
            <a href="rooms.php" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold text-primary"><?php echo $stats['rooms']; ?></div>
                        <div class="text-muted small">Rooms</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-2">
            <a href="sessions.php" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold text-primary"><?php echo $stats['sessions']; ?></div>
                        <div class="text-muted small">Sessions</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body">
            <h5 class="card-title">Quick actions</h5>
            <p class="card-text text-muted mb-0">Use the navigation bar to open <a href="departments.php">Departments</a>, <a href="courses.php">Courses</a>, <a href="faculty.php">Faculty</a>, <a href="students.php">Students</a>, <a href="rooms.php">Rooms</a>, <a href="sessions.php">Sessions</a>, <a href="generate.php">Generate</a> timetable, or <a href="substitutions.php">Substitutions</a>.</p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
