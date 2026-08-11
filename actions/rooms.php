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
            $stmt = $conn->prepare('SELECT * FROM room WHERE id = ?');
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
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = (int)($_GET['page_size'] ?? 25);
            $allowedPageSizes = [25, 50, 75, 100];
            if (!in_array($pageSize, $allowedPageSizes, true)) {
                $pageSize = 25;
            }
            $offset = ($page - 1) * $pageSize;
            $q = trim($_GET['q'] ?? '');

            $where = 'WHERE is_active = 1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND LOWER(room_number) LIKE ?';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $types .= 's';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND id IN ($ph)";
                foreach ($ids as $rid) {
                    $params[] = $rid;
                    $types .= 'i';
                }
            }

            $countSql = "SELECT COUNT(*) AS cnt FROM room $where";
            $stmt = $conn->prepare($countSql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $total = (int)($row['cnt'] ?? 0);

            $sql = "SELECT * FROM room
                    $where
                    ORDER BY room_number
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
            $q = trim($_GET['q'] ?? '');
            $where = 'WHERE is_active = 1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND LOWER(room_number) LIKE ?';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $types .= 's';
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND id IN ($ph)";
                foreach ($ids as $rid) {
                    $params[] = $rid;
                    $types .= 'i';
                }
            }
            $sql = "SELECT * FROM room $where ORDER BY room_number";
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
        $room_number = trim($input['room_number'] ?? '');
        $capacity = (int)($input['capacity'] ?? 30);
        $room_type = in_array($input['room_type'] ?? '', ['classroom', 'lab', 'hall']) ? $input['room_type'] : 'classroom';
        if (!$room_number) api_error('Please enter room number.');
        $err = validate_max_length($room_number, MAX_ROOM_NUMBER, 'Room number');
        if ($err) api_error($err);
        $stmt = $conn->prepare('INSERT INTO room (room_number, capacity, room_type) VALUES (?, ?, ?)');
        $stmt->bind_param('sis', $room_number, $capacity, $room_type);
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'This room number already exists.'));
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Room added.']);
        break;
    case 'PUT':
        $id = (int)($input['id'] ?? 0);
        if (!$id) api_error('Invalid room.');
        $room_number = trim($input['room_number'] ?? '');
        $capacity = (int)($input['capacity'] ?? 30);
        $room_type = in_array($input['room_type'] ?? '', ['classroom', 'lab', 'hall']) ? $input['room_type'] : 'classroom';
        $err = validate_max_length($room_number, MAX_ROOM_NUMBER, 'Room number');
        if ($err) api_error($err);
        $stmt = $conn->prepare('UPDATE room SET room_number=?, capacity=?, room_type=? WHERE id=?');
        $stmt->bind_param('sisi', $room_number, $capacity, $room_type, $id);
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'This room number is already in use.'));
        echo json_encode(['success' => true, 'message' => 'Room updated.']);
        break;
    case 'DELETE':
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) api_error('Invalid room.');
        $stmt = $conn->prepare('UPDATE room SET is_active = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) api_error('Could not remove room.');
        echo json_encode(['success' => true, 'message' => 'Room removed.']);
        break;
    default:
        api_error('Method not allowed.', 405);
}
