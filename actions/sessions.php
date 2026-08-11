<?php
session_start();
header('Content-Type: application/json');
$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !$role) {
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
        // All logged-in users (admin, faculty, student) can read session list
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare('SELECT * FROM academic_session WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            echo json_encode($row ?: ['success' => false]);
            break;
        }

        $paged = isset($_GET['paged']) && $_GET['paged'] === '1';
        $q = trim($_GET['q'] ?? '');
        $includePast = isset($_GET['include_past']) && $_GET['include_past'] === '1';
        $idsRaw = trim((string)($_GET['ids'] ?? ''));
        $ids = [];
        if ($idsRaw !== '') {
            foreach (explode(',', $idsRaw) as $part) {
                $v = (int)trim($part);
                if ($v > 0) $ids[] = $v;
            }
            $ids = array_values(array_unique($ids));
        }
        if ($paged && $role === 'admin') {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = (int)($_GET['page_size'] ?? 25);
            $allowedPageSizes = [25, 50, 75, 100];
            if (!in_array($pageSize, $allowedPageSizes, true)) {
                $pageSize = 25;
            }
            $offset = ($page - 1) * $pageSize;
            $where = '';
            $params = [];
            $types = '';
            if (!$includePast && empty($ids)) {
                $where = 'WHERE end_date >= CURDATE()';
            }
            if ($q !== '') {
                $where = ($where === '' ? 'WHERE ' : $where . ' AND ') . 'LOWER(name) LIKE ?';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $types .= 's';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where = ($where === '' ? 'WHERE ' : $where . ' AND ') . "id IN ($ph)";
                foreach ($ids as $sid) {
                    $params[] = $sid;
                    $types .= 'i';
                }
            }

            $countSql = "SELECT COUNT(*) AS cnt FROM academic_session $where";
            $stmt = $conn->prepare($countSql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $total = (int)($row['cnt'] ?? 0);

            $sql = "SELECT * FROM academic_session
                    $where
                    ORDER BY start_date ASC
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
            // Non-admin & legacy callers still get full list
            $where = '';
            $params = [];
            $types = '';
            if (!$includePast && empty($ids)) {
                $where = 'WHERE end_date >= CURDATE()';
            }
            if ($q !== '') {
                $where = ($where === '' ? 'WHERE ' : $where . ' AND ') . 'LOWER(name) LIKE ?';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $types .= 's';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where = ($where === '' ? 'WHERE ' : $where . ' AND ') . "id IN ($ph)";
                foreach ($ids as $sid) {
                    $params[] = $sid;
                    $types .= 'i';
                }
            }
            $sql = "SELECT * FROM academic_session $where ORDER BY start_date ASC";
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
        if ($role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $name = trim($input['name'] ?? '');
        $start_date = $input['start_date'] ?? '';
        $end_date = $input['end_date'] ?? '';
        if (!$name || !$start_date || !$end_date) api_error('Please enter session name, start date and end date.');
        $err = validate_max_length($name, MAX_SESSION_NAME, 'Session name');
        if ($err) api_error($err);
        $stmt = $conn->prepare('INSERT INTO academic_session (name, start_date, end_date) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $name, $start_date, $end_date);
        if (!$stmt->execute()) api_error('Could not create session. Check dates are valid.');
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Session created.']);
        break;
    case 'PUT':
        if ($role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $id = (int)($input['id'] ?? 0);
        if (!$id) api_error('Invalid session.');
        $name = trim($input['name'] ?? '');
        $start_date = $input['start_date'] ?? '';
        $end_date = $input['end_date'] ?? '';
        $err = validate_max_length($name, MAX_SESSION_NAME, 'Session name');
        if ($err) api_error($err);
        $stmt = $conn->prepare('UPDATE academic_session SET name=?, start_date=?, end_date=? WHERE id=?');
        $stmt->bind_param('sssi', $name, $start_date, $end_date, $id);
        if (!$stmt->execute()) api_error('Could not update session.');
        echo json_encode(['success' => true, 'message' => 'Session updated.']);
        break;
    case 'DELETE':
        if ($role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) api_error('Invalid session.');
        $stmt = $conn->prepare('DELETE FROM academic_session WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) api_error('Could not delete session. It may have schedules.');
        echo json_encode(['success' => true, 'message' => 'Session deleted.']);
        break;
    default:
        api_error('Method not allowed.', 405);
}
