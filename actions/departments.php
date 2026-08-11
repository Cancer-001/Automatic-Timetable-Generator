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
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($method) {
    case 'GET':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare('SELECT * FROM department WHERE id = ?');
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

            $where = 'WHERE 1=1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND (LOWER(name) LIKE ? OR LOWER(code) LIKE ?)';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $params[] = $like;
                $types .= 'ss';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND id IN ($ph)";
                foreach ($ids as $did) {
                    $params[] = $did;
                    $types .= 'i';
                }
            }
            $countSql = "SELECT COUNT(*) AS cnt FROM department $where";
            $stmt = $conn->prepare($countSql);
            if ($types !== '') $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

            $sql = "SELECT * FROM department $where ORDER BY name LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sql);
            $params2 = array_merge($params, [$pageSize, $offset]);
            $types2 = $types . 'ii';
            $stmt->bind_param($types2, ...$params2);
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
            // Backwards-compatible: simple list for dropdowns and older callers
            $where = 'WHERE 1=1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND (LOWER(name) LIKE ? OR LOWER(code) LIKE ?)';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $params[] = $like;
                $types .= 'ss';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND id IN ($ph)";
                foreach ($ids as $did) {
                    $params[] = $did;
                    $types .= 'i';
                }
            }
            $sql = "SELECT * FROM department $where ORDER BY name";
            $stmt = $conn->prepare($sql);
            if ($types !== '') $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            echo json_encode($rows);
        }
        break;
    case 'POST':
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? '');
        if (!$name || !$code) {
            api_error('Please enter both department name and code.');
        }
        $err = validate_max_length($name, MAX_DEPARTMENT_NAME, 'Department name');
        if ($err) api_error($err);
        $err = validate_max_length($code, MAX_DEPARTMENT_CODE, 'Department code');
        if ($err) api_error($err);
        // Check duplicate name
        $check = $conn->prepare('SELECT id FROM department WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1');
        $check->bind_param('s', $name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            api_error('There is already a department with this name. A department with the same name cannot be created.');
        }
        // Check duplicate code
        $check = $conn->prepare('SELECT id FROM department WHERE code = ? LIMIT 1');
        $check->bind_param('s', $code);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            api_error('There is already a department with this code. The same code cannot be used for another department.');
        }
        $stmt = $conn->prepare('INSERT INTO department (name, code) VALUES (?, ?)');
        $stmt->bind_param('ss', $name, $code);
        if (!$stmt->execute()) {
            api_error(db_duplicate_message($conn, 'A department with this name or code already exists.'));
        }
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Department created.']);
        break;
    case 'PUT':
        $id = (int)($input['id'] ?? 0);
        if (!$id) api_error('Invalid department.');
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? '');
        if (!$name || !$code) api_error('Please enter both name and code.');
        $err = validate_max_length($name, MAX_DEPARTMENT_NAME, 'Department name');
        if ($err) api_error($err);
        $err = validate_max_length($code, MAX_DEPARTMENT_CODE, 'Department code');
        if ($err) api_error($err);
        // Check duplicate name (another department with same name, excluding current)
        $check = $conn->prepare('SELECT id FROM department WHERE LOWER(TRIM(name)) = LOWER(?) AND id != ? LIMIT 1');
        $check->bind_param('si', $name, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            api_error('There is already a department with this name. A department with the same name cannot be created.');
        }
        // Check duplicate code (another department with same code, excluding current)
        $check = $conn->prepare('SELECT id FROM department WHERE code = ? AND id != ? LIMIT 1');
        $check->bind_param('si', $code, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            api_error('There is already a department with this code. The same code cannot be used for another department.');
        }
        $stmt = $conn->prepare('UPDATE department SET name=?, code=? WHERE id=?');
        $stmt->bind_param('ssi', $name, $code, $id);
        if (!$stmt->execute()) {
            api_error(db_duplicate_message($conn, 'Another department already uses this code.'));
        }
        echo json_encode(['success' => true, 'message' => 'Department updated.']);
        break;
    case 'DELETE':
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) api_error('Invalid department.');
        $stmt = $conn->prepare('DELETE FROM department WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) api_error('Could not delete department. It may be in use by courses or users.');
        echo json_encode(['success' => true, 'message' => 'Department deleted.']);
        break;
    default:
        api_error('Method not allowed.', 405);
}
