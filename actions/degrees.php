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

// Safe, additive schema setup for new module
$conn->query("CREATE TABLE IF NOT EXISTS degree (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(128) NOT NULL UNIQUE,
    department_id INT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure department_id column exists if upgrading from old version
$result = $conn->query("SHOW COLUMNS FROM degree LIKE 'department_id'");
if ($result && $result->num_rows === 0) {
    $conn->query("ALTER TABLE degree ADD COLUMN department_id INT UNSIGNED DEFAULT NULL AFTER name");
    $conn->query("ALTER TABLE degree ADD CONSTRAINT fk_degree_dept FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL");
}
// Support duplicate codes (e.g. BS) for different program names.
// Drop any UNIQUE index that includes `code` (older schemas/seeds may create it).
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
db_add_column_if_missing($conn, 'student', 'degree_id', 'INT UNSIGNED DEFAULT NULL AFTER department_id');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($method) {
    case 'GET':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare('SELECT * FROM degree WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            echo json_encode($row ?: ['success' => false]);
            break;
        }

        // New: Return grouped degrees by department for two-panel selector
        $grouped = isset($_GET['grouped']) && $_GET['grouped'] === '1';
        if ($grouped) {
            // Fetch all departments
            $deptRes = $conn->query('SELECT id, name FROM department ORDER BY name');
            $departments = [];
            $degreesMap = [];
            while ($d = $deptRes->fetch_assoc()) {
                $deptId = (int)$d['id'];
                $departments[] = ['id' => $deptId, 'name' => $d['name']];
                $degreesMap[$deptId] = [];
            }
            
            // Fetch all degrees with their departments
            $degRes = $conn->query('SELECT id, name, code, department_id FROM degree WHERE is_active = 1 ORDER BY name');
            while ($deg = $degRes->fetch_assoc()) {
                $deptId = (int)($deg['department_id'] ?? 0);
                if ($deptId === 0 || !isset($degreesMap[$deptId])) {
                    $deptId = 0; // Unassigned
                }
                if ($deptId === 0 && !isset($degreesMap[0])) {
                    $degreesMap[0] = [];
                }
                $degreesMap[$deptId][] = [
                    'id' => (int)$deg['id'],
                    'name' => $deg['name'],
                    'code' => $deg['code']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'departments' => $departments,
                'degrees' => $degreesMap
            ]);
            break;
        }

        $paged = isset($_GET['paged']) && $_GET['paged'] === '1';
        if ($paged) {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = (int)($_GET['page_size'] ?? 25);
            if (!in_array($pageSize, [25, 50, 75, 100], true)) $pageSize = 25;
            $offset = ($page - 1) * $pageSize;

            $countRes = $conn->query('SELECT COUNT(*) AS cnt FROM degree WHERE is_active = 1');
            $total = $countRes ? (int)($countRes->fetch_assoc()['cnt'] ?? 0) : 0;

            $stmt = $conn->prepare('SELECT * FROM degree WHERE is_active = 1 ORDER BY name LIMIT ? OFFSET ?');
            $stmt->bind_param('ii', $pageSize, $offset);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;

            echo json_encode([
                'success' => true,
                'items' => $rows,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize
            ]);
            break;
        }

        $res = $conn->query('SELECT * FROM degree WHERE is_active = 1 ORDER BY name');
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        break;

    case 'POST':
        $code = strtoupper(trim($input['code'] ?? ''));
        $name = trim($input['name'] ?? '');
        $department_id = isset($input['department_id']) ? (int)$input['department_id'] : null;
        if (!$code || !$name) api_error('Please enter both degree code and degree name.');
        $err = validate_max_length($code, MAX_DEGREE_CODE, 'Degree code');
        if ($err) api_error($err);
        $err = validate_max_length($name, MAX_DEGREE_NAME, 'Degree name');
        if ($err) api_error($err);

        $chk = $conn->prepare('SELECT id FROM degree WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1');
        $chk->bind_param('s', $name);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) api_error('Degree name already exists.');

        $stmt = $conn->prepare('INSERT INTO degree (code, name, department_id) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $code, $name, $department_id);
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'Could not create degree.'));
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Degree created.']);
        break;

    case 'PUT':
        $id = (int)($input['id'] ?? 0);
        $code = strtoupper(trim($input['code'] ?? ''));
        $name = trim($input['name'] ?? '');
        $department_id = isset($input['department_id']) ? (int)$input['department_id'] : null;
        if (!$id) api_error('Invalid degree.');
        if (!$code || !$name) api_error('Please enter both degree code and degree name.');
        $err = validate_max_length($code, MAX_DEGREE_CODE, 'Degree code');
        if ($err) api_error($err);
        $err = validate_max_length($name, MAX_DEGREE_NAME, 'Degree name');
        if ($err) api_error($err);

        $chk = $conn->prepare('SELECT id FROM degree WHERE LOWER(TRIM(name)) = LOWER(?) AND id != ? LIMIT 1');
        $chk->bind_param('si', $name, $id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) api_error('Degree name already exists.');

        $stmt = $conn->prepare('UPDATE degree SET code = ?, name = ?, department_id = ? WHERE id = ?');
        $stmt->bind_param('ssii', $code, $name, $department_id, $id);
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'Could not update degree.'));
        echo json_encode(['success' => true, 'message' => 'Degree updated.']);
        break;

    case 'DELETE':
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) api_error('Invalid degree.');

        $inUseFaculty = $conn->prepare('SELECT id FROM faculty WHERE degree_id = ? LIMIT 1');
        $inUseFaculty->bind_param('i', $id);
        $inUseFaculty->execute();
        if ($inUseFaculty->get_result()->num_rows > 0) {
            api_error('Cannot delete: degree is assigned to faculty.');
        }

        $inUseStudent = $conn->prepare('SELECT id FROM student WHERE degree_id = ? LIMIT 1');
        $inUseStudent->bind_param('i', $id);
        $inUseStudent->execute();
        if ($inUseStudent->get_result()->num_rows > 0) {
            api_error('Cannot delete: degree is assigned to students.');
        }

        $stmt = $conn->prepare('UPDATE degree SET is_active = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) api_error('Could not delete degree.');
        echo json_encode(['success' => true, 'message' => 'Degree deleted.']);
        break;

    default:
        api_error('Method not allowed.', 405);
}
