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
require_once __DIR__ . '/../config/schema_helpers.php';

// Safe, additive schema setup for degrees module
$conn->query("CREATE TABLE IF NOT EXISTS degree (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(128) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Keep degree.code non-unique (multiple programs can share code like BS).
$idxCode = @$conn->query("SHOW INDEX FROM degree WHERE Non_unique = 0");
if ($idxCode && $idxCode->num_rows > 0) {
    $uniqueKeys = [];
    while ($ix = $idxCode->fetch_assoc()) {
        $k = (string)($ix['Key_name'] ?? '');
        if ($k === '' || strtolower($k) === 'primary') {
            continue;
        }
        $col = strtolower((string)($ix['Column_name'] ?? ''));
        if (!isset($uniqueKeys[$k])) {
            $uniqueKeys[$k] = [];
        }
        $uniqueKeys[$k][] = $col;
    }
    foreach ($uniqueKeys as $k => $cols) {
        if (in_array('code', $cols, true)) {
            @$conn->query("ALTER TABLE degree DROP INDEX `$k`");
        }
    }
}
db_add_column_if_missing($conn, 'faculty', 'degree_id', 'INT UNSIGNED DEFAULT NULL AFTER department_id');
db_add_column_if_missing($conn, 'faculty', 'faculty_type', "ENUM('permanent','visiting') NOT NULL DEFAULT 'permanent' AFTER full_name");
db_add_column_if_missing($conn, 'faculty', 'visiting_day_of_week', 'TINYINT UNSIGNED DEFAULT NULL AFTER faculty_type');
db_add_column_if_missing($conn, 'faculty', 'visiting_start_time', 'TIME DEFAULT NULL AFTER visiting_day_of_week');
db_add_column_if_missing($conn, 'faculty', 'visiting_end_time', 'TIME DEFAULT NULL AFTER visiting_start_time');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($method) {
    case 'GET':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare('SELECT f.*, d.name AS department_name, dg.name AS degree_name FROM faculty f LEFT JOIN department d ON f.department_id = d.id LEFT JOIN degree dg ON f.degree_id = dg.id WHERE f.id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            echo json_encode($row ?: ['success' => false]);
            break;
        }

        $paged = isset($_GET['paged']) && $_GET['paged'] === '1';
        $q = trim($_GET['q'] ?? '');
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
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = (int)($_GET['page_size'] ?? 25);
            $allowedPageSizes = [25, 50, 75, 100];
            if (!in_array($pageSize, $allowedPageSizes, true)) {
                $pageSize = 25;
            }
            $offset = ($page - 1) * $pageSize;
            $where = 'WHERE f.is_active = 1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND (LOWER(f.full_name) LIKE ? OR LOWER(f.email) LIKE ?)';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $params[] = $like;
                $types .= 'ss';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND f.id IN ($ph)";
                foreach ($ids as $fid) {
                    $params[] = $fid;
                    $types .= 'i';
                }
            }

            $countSql = "SELECT COUNT(*) AS cnt FROM faculty f $where";
            $stmt = $conn->prepare($countSql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $total = (int)($row['cnt'] ?? 0);

            $sql = "SELECT f.*, d.name AS department_name, dg.name AS degree_name,
                    COALESCE(af.assigned_courses_count, 0) AS assigned_courses_count,
                    COALESCE(af.assigned_course_names, '') AS assigned_course_names
                    FROM faculty f
                    LEFT JOIN department d ON f.department_id = d.id
                    LEFT JOIN degree dg ON f.degree_id = dg.id
                    LEFT JOIN (
                        SELECT cf.faculty_id,
                               COUNT(DISTINCT cf.course_id) AS assigned_courses_count,
                               GROUP_CONCAT(DISTINCT CONCAT(c.code, ' - ', c.name) ORDER BY c.code SEPARATOR '||') AS assigned_course_names
                        FROM course_faculty cf
                        INNER JOIN course c ON c.id = cf.course_id AND c.is_active = 1
                        GROUP BY cf.faculty_id
                    ) af ON af.faculty_id = f.id
                    $where
                    ORDER BY f.full_name
                    LIMIT ? OFFSET ?";
            $paramsWithLimit = $params;
            $typesWithLimit = $types . 'ii';
            $paramsWithLimit[] = $pageSize;
            $paramsWithLimit[] = $offset;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;

            echo json_encode([
                'success' => true,
                'items' => $rows,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ]);
        } else {
            $where = 'WHERE f.is_active = 1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND (LOWER(f.full_name) LIKE ? OR LOWER(f.email) LIKE ?)';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $params[] = $like;
                $types .= 'ss';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND f.id IN ($ph)";
                foreach ($ids as $fid) {
                    $params[] = $fid;
                    $types .= 'i';
                }
            }
            $sql = "SELECT f.*, d.name AS department_name, dg.name AS degree_name,
                COALESCE(af.assigned_courses_count, 0) AS assigned_courses_count,
                COALESCE(af.assigned_course_names, '') AS assigned_course_names
                FROM faculty f
                LEFT JOIN department d ON f.department_id = d.id
                LEFT JOIN degree dg ON f.degree_id = dg.id
                LEFT JOIN (
                    SELECT cf.faculty_id,
                           COUNT(DISTINCT cf.course_id) AS assigned_courses_count,
                           GROUP_CONCAT(DISTINCT CONCAT(c.code, ' - ', c.name) ORDER BY c.code SEPARATOR '||') AS assigned_course_names
                    FROM course_faculty cf
                    INNER JOIN course c ON c.id = cf.course_id AND c.is_active = 1
                    GROUP BY cf.faculty_id
                ) af ON af.faculty_id = f.id
                $where
                ORDER BY f.full_name";
            $stmt = $conn->prepare($sql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            echo json_encode($rows);
        }
        break;
    case 'POST':
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $full_name = trim($input['full_name'] ?? '');
        $degree_id = !empty($input['degree_id']) ? (int)$input['degree_id'] : null;
        $department_id = !empty($input['department_id']) ? (int)$input['department_id'] : null;
        $faculty_type = strtolower(trim((string)($input['faculty_type'] ?? 'permanent')));
        if ($faculty_type !== 'visiting') {
            $faculty_type = 'permanent';
        }
        $visiting_day_of_week = null;
        if (isset($input['visiting_day_of_week']) && $input['visiting_day_of_week'] !== '') {
            $dow = (int)$input['visiting_day_of_week'];
            if ($dow >= 1 && $dow <= 5) {
                $visiting_day_of_week = $dow;
            }
        }
        $visiting_start_time = isset($input['visiting_start_time']) ? trim((string)$input['visiting_start_time']) : null;
        if ($visiting_start_time === '') $visiting_start_time = null;
        $visiting_end_time = isset($input['visiting_end_time']) ? trim((string)$input['visiting_end_time']) : null;
        if ($visiting_end_time === '') $visiting_end_time = null;
        if ($faculty_type === 'visiting') {
            if ($visiting_day_of_week === null || $visiting_start_time === null || $visiting_end_time === null) {
                api_error('Visiting faculty requires day, start time, and end time.');
            }
        } else {
            $visiting_day_of_week = null;
            $visiting_start_time = null;
            $visiting_end_time = null;
        }
        $availability_notes = trim($input['availability_notes'] ?? '');
        if (!$email || !$full_name || !$password) api_error('Please enter email, full name and password.');
        $err = validate_max_length($email, MAX_EMAIL, 'Email');
        if ($err) api_error($err);
        $err = validate_max_length($full_name, MAX_FULL_NAME, 'Full name');
        if ($err) api_error($err);
        $err = validate_max_length($availability_notes, MAX_NOTES, 'Availability notes');
        if ($err) api_error($err);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO faculty (email, password_hash, full_name, faculty_type, visiting_day_of_week, visiting_start_time, visiting_end_time, degree_id, department_id, availability_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssissiis', $email, $hash, $full_name, $faculty_type, $visiting_day_of_week, $visiting_start_time, $visiting_end_time, $degree_id, $department_id, $availability_notes);
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'A faculty member with this email already exists.'));
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Faculty added.']);
        break;
    case 'PUT':
        $id = (int)($input['id'] ?? 0);
        if (!$id) api_error('Invalid faculty.');
        $email = trim($input['email'] ?? '');
        $full_name = trim($input['full_name'] ?? '');
        $degree_id = !empty($input['degree_id']) ? (int)$input['degree_id'] : null;
        $department_id = !empty($input['department_id']) ? (int)$input['department_id'] : null;
        $faculty_type = strtolower(trim((string)($input['faculty_type'] ?? 'permanent')));
        if ($faculty_type !== 'visiting') {
            $faculty_type = 'permanent';
        }
        $visiting_day_of_week = null;
        if (isset($input['visiting_day_of_week']) && $input['visiting_day_of_week'] !== '') {
            $dow = (int)$input['visiting_day_of_week'];
            if ($dow >= 1 && $dow <= 5) {
                $visiting_day_of_week = $dow;
            }
        }
        $visiting_start_time = isset($input['visiting_start_time']) ? trim((string)$input['visiting_start_time']) : null;
        if ($visiting_start_time === '') $visiting_start_time = null;
        $visiting_end_time = isset($input['visiting_end_time']) ? trim((string)$input['visiting_end_time']) : null;
        if ($visiting_end_time === '') $visiting_end_time = null;
        if ($faculty_type === 'visiting') {
            if ($visiting_day_of_week === null || $visiting_start_time === null || $visiting_end_time === null) {
                api_error('Visiting faculty requires day, start time, and end time.');
            }
        } else {
            $visiting_day_of_week = null;
            $visiting_start_time = null;
            $visiting_end_time = null;
        }
        $availability_notes = trim($input['availability_notes'] ?? '');
        $err = validate_max_length($email, MAX_EMAIL, 'Email');
        if ($err) api_error($err);
        $err = validate_max_length($full_name, MAX_FULL_NAME, 'Full name');
        if ($err) api_error($err);
        $err = validate_max_length($availability_notes, MAX_NOTES, 'Availability notes');
        if ($err) api_error($err);
        if (!empty($input['password'])) {
            $hash = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE faculty SET email=?, password_hash=?, full_name=?, faculty_type=?, visiting_day_of_week=?, visiting_start_time=?, visiting_end_time=?, degree_id=?, department_id=?, availability_notes=? WHERE id=?');
            $stmt->bind_param('ssssissiisi', $email, $hash, $full_name, $faculty_type, $visiting_day_of_week, $visiting_start_time, $visiting_end_time, $degree_id, $department_id, $availability_notes, $id);
        } else {
            $stmt = $conn->prepare('UPDATE faculty SET email=?, full_name=?, faculty_type=?, visiting_day_of_week=?, visiting_start_time=?, visiting_end_time=?, degree_id=?, department_id=?, availability_notes=? WHERE id=?');
            $stmt->bind_param('sssissiisi', $email, $full_name, $faculty_type, $visiting_day_of_week, $visiting_start_time, $visiting_end_time, $degree_id, $department_id, $availability_notes, $id);
        }
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'This email is already used by another faculty member.'));
        echo json_encode(['success' => true, 'message' => 'Faculty updated.']);
        break;
    case 'DELETE':
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) api_error('Invalid faculty.');
        $stmt = $conn->prepare('UPDATE faculty SET is_active = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) api_error('Could not remove faculty.');
        echo json_encode(['success' => true, 'message' => 'Faculty removed.']);
        break;
    default:
        api_error('Method not allowed.', 405);
}
