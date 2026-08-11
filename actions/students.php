<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api_response.php';
require_once __DIR__ . '/../config/validation.php';

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── Detect which new columns exist (safe for old installs) ──────────────────
function column_exists($conn, $table, $col) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $r && $r->num_rows > 0;
}
$has_degree   = column_exists($conn, 'student', 'degree');
$has_degree_id = column_exists($conn, 'student', 'degree_id');
$has_roll_no  = column_exists($conn, 'student', 'roll_no');
$has_frozen   = column_exists($conn, 'student', 'is_frozen');
$has_academic_session_id = column_exists($conn, 'student', 'academic_session_id');

// Auto-add missing columns so future queries work (safe ALTER)
if (!$has_degree) {
    $conn->query("ALTER TABLE student ADD COLUMN degree VARCHAR(32) NOT NULL DEFAULT 'BS' AFTER department_id");
    $has_degree = true;
}
if (!$has_degree_id) {
    $conn->query("ALTER TABLE student ADD COLUMN degree_id INT UNSIGNED DEFAULT NULL AFTER department_id");
    $has_degree_id = true;
}
if (!$has_roll_no) {
    $conn->query("ALTER TABLE student ADD COLUMN roll_no VARCHAR(64) DEFAULT NULL AFTER degree");
    $has_roll_no = true;
}
if (!$has_frozen) {
    $conn->query("ALTER TABLE student ADD COLUMN is_frozen TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
    $has_frozen = true;
}
if (!$has_academic_session_id) {
    $conn->query("ALTER TABLE student ADD COLUMN academic_session_id INT UNSIGNED DEFAULT NULL AFTER department_id");
    $has_academic_session_id = true;
}
$conn->query("CREATE TABLE IF NOT EXISTS degree (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(128) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Allow same degree code across multiple disciplines/program names.
$idxCode = @$conn->query("SHOW INDEX FROM degree WHERE Column_name = 'code' AND Non_unique = 0");
if ($idxCode && $idxCode->num_rows > 0) {
    while ($ix = $idxCode->fetch_assoc()) {
        $k = $ix['Key_name'] ?? '';
        if ($k && strtolower($k) !== 'primary') {
            @$conn->query("ALTER TABLE degree DROP INDEX `$k`");
        }
    }
}

/**
 * Generate roll number: DEGREE-YEAR-DEPTCODE-NNNN  e.g. BS-2025-CS-0001
 */
function generate_roll_no($conn, $degree, $dept_id) {
    $deg  = strtoupper(trim($degree ?: 'BS'));
    $year = date('Y');
    $code = 'GEN';
    if ($dept_id) {
        $ds = $conn->prepare('SELECT code FROM department WHERE id = ?');
        $ds->bind_param('i', $dept_id);
        $ds->execute();
        $dr = $ds->get_result()->fetch_assoc();
        if (!empty($dr['code'])) $code = strtoupper($dr['code']);
    }
    $prefix = $deg . '-' . $year . '-' . $code . '-';
    $like   = $conn->real_escape_string($prefix) . '%';
    $res    = $conn->query("SELECT roll_no FROM student WHERE roll_no LIKE '$like' ORDER BY roll_no DESC LIMIT 1");
    $serial = 1;
    if ($res && ($row = $res->fetch_assoc())) {
        $parts  = explode('-', $row['roll_no']);
        $serial = (int)end($parts) + 1;
    }
    return $prefix . str_pad($serial, 4, '0', STR_PAD_LEFT);
}

function resolve_degree_code($conn, $degree_id) {
    if (!$degree_id) return 'BS';
    $stmt = $conn->prepare('SELECT code FROM degree WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('i', $degree_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return strtoupper(trim($row['code'] ?? 'BS'));
}

// ── SELECT helper: builds field list safe for current schema ────────────────
function student_select_fields() {
    return "s.id, s.full_name, s.email,
            COALESCE(s.degree,'BS')    AS degree,
            s.roll_no                  AS roll_no,
            s.semester, s.section,
            COALESCE(s.is_frozen,0)    AS is_frozen,
            s.academic_session_id,
            s.degree_id,
            s.department_id,
            dg.name AS degree_name,
            acs.name AS academic_session_name,
            d.name AS department_name";
}

switch ($method) {

    // ── GET ─────────────────────────────────────────────────────────────────
    case 'GET':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $fields = student_select_fields();
            $stmt = $conn->prepare("SELECT $fields FROM student s LEFT JOIN degree dg ON s.degree_id = dg.id LEFT JOIN department d ON s.department_id = d.id LEFT JOIN academic_session acs ON s.academic_session_id = acs.id WHERE s.id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            echo json_encode($row ?: ['success' => false]);
            break;
        }

        $paged = isset($_GET['paged']) && $_GET['paged'] === '1';
        $idsRaw = trim((string)($_GET['ids'] ?? ''));
        $ids = [];
        if ($idsRaw !== '') {
            foreach (explode(',', $idsRaw) as $part) {
                $v = (int)trim($part);
                if ($v > 0) $ids[] = $v;
            }
            $ids = array_values(array_unique($ids));
        }
        if ($paged) {
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = (int)($_GET['page_size'] ?? 25);
            if (!in_array($pageSize, [25, 50, 75, 100], true)) $pageSize = 25;
            $offset   = ($page - 1) * $pageSize;
            $q        = trim($_GET['q'] ?? '');
            $degreeId = (int)($_GET['degree_id'] ?? 0);
            $deptId   = (int)($_GET['department_id'] ?? 0);
            $semester = (int)($_GET['semester'] ?? 0);
            $section  = trim((string)($_GET['section'] ?? ''));
            $sessionId = (int)($_GET['academic_session_id'] ?? 0);

            $where  = 'WHERE s.is_active = 1';
            $params = [];
            $types  = '';
            if ($q !== '') {
                $where .= ' AND (LOWER(s.full_name) LIKE ? OR LOWER(s.email) LIKE ? OR LOWER(COALESCE(s.roll_no,"")) LIKE ?)';
                $like   = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params = [$like, $like, $like];
                $types  = 'sss';
            }
            if ($degreeId > 0) {
                $where .= ' AND s.degree_id = ?';
                $params[] = $degreeId;
                $types .= 'i';
            }
            if ($deptId > 0) {
                $where .= ' AND s.department_id = ?';
                $params[] = $deptId;
                $types .= 'i';
            }
            if ($semester > 0) {
                $where .= ' AND s.semester = ?';
                $params[] = $semester;
                $types .= 'i';
            }
            if ($section !== '') {
                $where .= ' AND s.section = ?';
                $params[] = $section;
                $types .= 's';
            }
            if ($sessionId > 0) {
                $where .= ' AND s.academic_session_id = ?';
                $params[] = $sessionId;
                $types .= 'i';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND s.id IN ($ph)";
                foreach ($ids as $sid) {
                    $params[] = $sid;
                    $types .= 'i';
                }
            }

            $countSql = "SELECT COUNT(*) AS cnt FROM student s $where";
            $stmt = $conn->prepare($countSql);
            if ($types !== '') $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

            $fields = student_select_fields();
            $sql = "SELECT $fields
                    FROM student s
                    LEFT JOIN degree dg ON s.degree_id = dg.id
                    LEFT JOIN department d ON s.department_id = d.id
                    LEFT JOIN academic_session acs ON s.academic_session_id = acs.id
                    $where
                    ORDER BY s.full_name
                    LIMIT ? OFFSET ?";
            $p2 = array_merge($params, [$pageSize, $offset]);
            $t2 = $types . 'ii';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($t2, ...$p2);
            $stmt->execute();
            $rows = [];
            $res  = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $rows[] = $r;

            echo json_encode(['success' => true, 'items' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
        } else {
            $q = trim($_GET['q'] ?? '');
            $fields = student_select_fields();
            $where = 'WHERE s.is_active = 1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND (LOWER(s.full_name) LIKE ? OR LOWER(s.email) LIKE ? OR LOWER(COALESCE(s.roll_no,"")) LIKE ?)';
                $like   = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params = [$like, $like, $like];
                $types  = 'sss';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND s.id IN ($ph)";
                foreach ($ids as $sid) {
                    $params[] = $sid;
                    $types .= 'i';
                }
            }
            $sql = "SELECT $fields FROM student s
                LEFT JOIN degree dg ON s.degree_id = dg.id
                LEFT JOIN department d ON s.department_id = d.id
                LEFT JOIN academic_session acs ON s.academic_session_id = acs.id
                $where
                ORDER BY s.full_name";
            $stmt = $conn->prepare($sql);
            if ($types !== '') $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            echo json_encode($rows);
        }
        break;

    // ── POST (create) ───────────────────────────────────────────────────────
    case 'POST':
        $email         = trim($input['email'] ?? '');
        $password      = $input['password'] ?? 'student123';
        $full_name     = trim($input['full_name'] ?? '');
        $department_id = !empty($input['department_id']) ? (int)$input['department_id'] : null;
        $degree_id     = !empty($input['degree_id']) ? (int)$input['degree_id'] : null;
        $academic_session_id = !empty($input['academic_session_id']) ? (int)$input['academic_session_id'] : null;
        $semester      = (int)($input['semester'] ?? 1);
        $section       = trim($input['section'] ?? 'A');

        if (!$email || !$full_name) api_error('Please enter email and full name.');
        $err = validate_max_length($email,     MAX_EMAIL,     'Email');     if ($err) api_error($err);
        $err = validate_max_length($full_name, MAX_FULL_NAME, 'Full name'); if ($err) api_error($err);
        $err = validate_max_length($section,   MAX_SECTION,   'Section');   if ($err) api_error($err);

        $degree_code = resolve_degree_code($conn, $degree_id);
        $roll_no = generate_roll_no($conn, $degree_code, $department_id);
        $hash    = password_hash($password, PASSWORD_DEFAULT);

        // INSERT — keep degree string for backward compatibility
        $stmt = $conn->prepare('INSERT INTO student (email, password_hash, full_name, academic_session_id, department_id, degree_id, degree, roll_no, semester, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssiiissis', $email, $hash, $full_name, $academic_session_id, $department_id, $degree_id, $degree_code, $roll_no, $semester, $section);
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'A student with this email already exists.'));
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'roll_no' => $roll_no, 'message' => "Student added. Roll No: $roll_no"]);
        break;

    // ── PUT (update) ────────────────────────────────────────────────────────
    case 'PUT':
        if (isset($input['action']) && $input['action'] === 'promote_bulk') {
            // #region agent log
            $__sid = isset($input['student_ids']) && is_array($input['student_ids']) ? count($input['student_ids']) : -1;
            @file_put_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'debug-ab35e3.log', json_encode(['sessionId' => 'ab35e3', 'runId' => 'post-fix', 'hypothesisId' => 'H1', 'location' => 'actions/students.php:promote_bulk:enter', 'message' => 'promote_bulk handling (no single id required)', 'data' => ['student_ids_count' => $__sid], 'timestamp' => (int) round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
            // #endregion
            $studentIds = $input['student_ids'] ?? [];
            if (!is_array($studentIds) || empty($studentIds)) api_error('No students selected for promotion.');

            $ids = array_values(array_unique(array_map('intval', $studentIds)));
            $ids = array_filter($ids, function($v) { return $v > 0; });
            if (empty($ids)) api_error('No valid students selected.');

            $degreeId = (int)($input['degree_id'] ?? 0);
            $deptId   = (int)($input['department_id'] ?? 0);
            $semester = (int)($input['semester'] ?? 0);
            $section  = trim((string)($input['section'] ?? ''));
            $sessionId = (int)($input['academic_session_id'] ?? 0);

            $where = 'WHERE is_active = 1 AND COALESCE(is_frozen,0) = 0 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $types = str_repeat('i', count($ids));
            $params = $ids;

            if ($degreeId > 0) {
                $where .= ' AND degree_id = ?';
                $types .= 'i';
                $params[] = $degreeId;
            }
            if ($deptId > 0) {
                $where .= ' AND department_id = ?';
                $types .= 'i';
                $params[] = $deptId;
            }
            if ($semester > 0) {
                $where .= ' AND semester = ?';
                $types .= 'i';
                $params[] = $semester;
            }
            if ($section !== '') {
                $where .= ' AND section = ?';
                $types .= 's';
                $params[] = $section;
            }
            if ($sessionId > 0) {
                $where .= ' AND academic_session_id = ?';
                $types .= 'i';
                $params[] = $sessionId;
            }

            $sql = 'UPDATE student SET semester = semester + 1 ' . $where;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) api_error('Bulk promotion failed.');
            $updated = (int)$stmt->affected_rows;
            // #region agent log
            @file_put_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'debug-ab35e3.log', json_encode(['sessionId' => 'ab35e3', 'runId' => 'post-fix', 'hypothesisId' => 'H2', 'location' => 'actions/students.php:promote_bulk:done', 'message' => 'bulk UPDATE executed', 'data' => ['ids_sent' => count($ids), 'affected_rows' => $updated, 'filters' => ['degreeId' => $degreeId, 'deptId' => $deptId, 'semester' => $semester, 'section' => $section, 'sessionId' => $sessionId]], 'timestamp' => (int) round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
            // #endregion
            echo json_encode([
                'success' => true,
                'updated_count' => $updated,
                'message' => $updated > 0
                    ? "Promoted $updated student(s) to next semester."
                    : 'No students matched the selected filters.'
            ]);
            break;
        }

        $id = (int)($input['id'] ?? 0);
        if (!$id) api_error('Invalid student.');

        // Freeze/unfreeze
        if (isset($input['action']) && $input['action'] === 'freeze') {
            $frozen = (int)(!empty($input['is_frozen']));
            $stmt   = $conn->prepare('UPDATE student SET is_frozen = ? WHERE id = ?');
            $stmt->bind_param('ii', $frozen, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => $frozen ? 'Student frozen.' : 'Student unfrozen.']);
            break;
        }

        // Promote semester
        if (isset($input['action']) && $input['action'] === 'promote') {
            $stmt = $conn->prepare('UPDATE student SET semester = semester + 1 WHERE id = ? AND COALESCE(is_frozen,0) = 0');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if ((int)$stmt->affected_rows < 1) {
                echo json_encode(['success' => false, 'message' => 'Student is frozen and cannot be promoted.']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Student promoted to next semester.']);
            }
            break;
        }

        $email         = trim($input['email'] ?? '');
        $full_name     = trim($input['full_name'] ?? '');
        $department_id = !empty($input['department_id']) ? (int)$input['department_id'] : null;
        $degree_id     = !empty($input['degree_id']) ? (int)$input['degree_id'] : null;
        $academic_session_id = !empty($input['academic_session_id']) ? (int)$input['academic_session_id'] : null;
        $semester      = (int)($input['semester'] ?? 1);
        $section       = trim($input['section'] ?? 'A');

        $err = validate_max_length($email,     MAX_EMAIL,     'Email');     if ($err) api_error($err);
        $err = validate_max_length($full_name, MAX_FULL_NAME, 'Full name'); if ($err) api_error($err);
        $err = validate_max_length($section,   MAX_SECTION,   'Section');   if ($err) api_error($err);

        // Keep or regenerate roll_no
        $existing = $conn->prepare('SELECT degree, degree_id, department_id, roll_no FROM student WHERE id = ?');
        $existing->bind_param('i', $id);
        $existing->execute();
        $old = $existing->get_result()->fetch_assoc();
        $degree_code = resolve_degree_code($conn, $degree_id);
        $roll_no = $old['roll_no'] ?? null;
        if (
            !$roll_no ||
            strtoupper($old['degree'] ?? '') !== $degree_code ||
            (int)($old['degree_id'] ?? 0) !== (int)($degree_id ?? 0) ||
            (int)($old['department_id'] ?? 0) !== (int)($department_id ?? 0)
        ) {
            $roll_no = generate_roll_no($conn, $degree_code, $department_id);
        }

        if (!empty($input['password'])) {
            $hash = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE student SET email=?, password_hash=?, full_name=?, academic_session_id=?, department_id=?, degree_id=?, degree=?, roll_no=?, semester=?, section=? WHERE id=?');
            $stmt->bind_param('sssiiissisi', $email, $hash, $full_name, $academic_session_id, $department_id, $degree_id, $degree_code, $roll_no, $semester, $section, $id);
        } else {
            $stmt = $conn->prepare('UPDATE student SET email=?, full_name=?, academic_session_id=?, department_id=?, degree_id=?, degree=?, roll_no=?, semester=?, section=? WHERE id=?');
            $stmt->bind_param('ssiiissisi', $email, $full_name, $academic_session_id, $department_id, $degree_id, $degree_code, $roll_no, $semester, $section, $id);
        }
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'This email is already used by another student.'));
        echo json_encode(['success' => true, 'roll_no' => $roll_no, 'message' => 'Student updated.']);
        break;

    // ── DELETE ──────────────────────────────────────────────────────────────
    case 'DELETE':
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) api_error('Invalid student.');
        $stmt = $conn->prepare('UPDATE student SET is_active = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) api_error('Could not remove student.');
        echo json_encode(['success' => true, 'message' => 'Student removed.']);
        break;

    default:
        api_error('Method not allowed.', 405);
}
