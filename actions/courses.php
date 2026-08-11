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
require_once __DIR__ . '/../config/timetable_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($method) {
    case 'GET':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare('SELECT c.*, d.name AS department_name, d.code AS department_code,
                    COALESCE(af.assigned_faculty_count, 0) AS assigned_faculty_count,
                    COALESCE(af.assigned_faculty_names, "") AS assigned_faculty_names
                FROM course c
                LEFT JOIN department d ON c.department_id = d.id
                LEFT JOIN (
                    SELECT cf.course_id,
                           COUNT(DISTINCT cf.faculty_id) AS assigned_faculty_count,
                           GROUP_CONCAT(DISTINCT f.full_name ORDER BY f.full_name SEPARATOR "||") AS assigned_faculty_names
                    FROM course_faculty cf
                    INNER JOIN faculty f ON f.id = cf.faculty_id AND f.is_active = 1
                    GROUP BY cf.course_id
                ) af ON af.course_id = c.id
                WHERE c.id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            echo json_encode($row ?: ['success' => false]);
            break;
        }

        $paged = isset($_GET['paged']) && $_GET['paged'] === '1';
        $q = trim($_GET['q'] ?? '');
        $assignmentStatus = trim((string)($_GET['assignment_status'] ?? ''));
        $degreeId = (int)($_GET['degree_id'] ?? 0);
        $semesterFilter = (int)($_GET['semester'] ?? 0);
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
            $where = 'WHERE c.is_active = 1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND (LOWER(c.name) LIKE ? OR LOWER(c.code) LIKE ?)';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $params[] = $like;
                $types .= 'ss';
            }
            if ($assignmentStatus === 'assigned') {
                $where .= ' AND EXISTS (SELECT 1 FROM course_faculty cfx WHERE cfx.course_id = c.id)';
            } elseif ($assignmentStatus === 'unassigned') {
                $where .= ' AND NOT EXISTS (SELECT 1 FROM course_faculty cfx WHERE cfx.course_id = c.id)';
            }
            if ($semesterFilter > 0) {
                $where .= ' AND c.semester = ?';
                $params[] = $semesterFilter;
                $types .= 'i';
            }
            tt_apply_degree_course_filter($conn, $where, $params, $types, $degreeId, 'c');
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND c.id IN ($ph)";
                foreach ($ids as $cid) {
                    $params[] = $cid;
                    $types .= 'i';
                }
            }

            $countSql = "SELECT COUNT(*) AS cnt FROM course c $where";
            $stmt = $conn->prepare($countSql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $total = (int)($row['cnt'] ?? 0);

            $sql = "SELECT c.*, d.name AS department_name, d.code AS department_code,
                    COALESCE(af.assigned_faculty_count, 0) AS assigned_faculty_count,
                    COALESCE(af.assigned_faculty_names, '') AS assigned_faculty_names
                    FROM course c
                    LEFT JOIN department d ON c.department_id = d.id
                    LEFT JOIN (
                        SELECT cf.course_id,
                               COUNT(DISTINCT cf.faculty_id) AS assigned_faculty_count,
                               GROUP_CONCAT(DISTINCT f.full_name ORDER BY f.full_name SEPARATOR '||') AS assigned_faculty_names
                        FROM course_faculty cf
                        INNER JOIN faculty f ON f.id = cf.faculty_id AND f.is_active = 1
                        GROUP BY cf.course_id
                    ) af ON af.course_id = c.id
                    $where
                    ORDER BY c.semester, c.code
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
            $where = 'WHERE c.is_active = 1';
            $params = [];
            $types = '';
            if ($q !== '') {
                $where .= ' AND (LOWER(c.name) LIKE ? OR LOWER(c.code) LIKE ?)';
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $params[] = $like;
                $params[] = $like;
                $types .= 'ss';
            }
            if ($assignmentStatus === 'assigned') {
                $where .= ' AND EXISTS (SELECT 1 FROM course_faculty cfx WHERE cfx.course_id = c.id)';
            } elseif ($assignmentStatus === 'unassigned') {
                $where .= ' AND NOT EXISTS (SELECT 1 FROM course_faculty cfx WHERE cfx.course_id = c.id)';
            }
            if ($semesterFilter > 0) {
                $where .= ' AND c.semester = ?';
                $params[] = $semesterFilter;
                $types .= 'i';
            }
            tt_apply_degree_course_filter($conn, $where, $params, $types, $degreeId, 'c');
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND c.id IN ($ph)";
                foreach ($ids as $cid) {
                    $params[] = $cid;
                    $types .= 'i';
                }
            }
            $sql = "SELECT c.*, d.name AS department_name, d.code AS department_code,
                    COALESCE(af.assigned_faculty_count, 0) AS assigned_faculty_count,
                    COALESCE(af.assigned_faculty_names, '') AS assigned_faculty_names
                    FROM course c
                    LEFT JOIN department d ON c.department_id = d.id
                    LEFT JOIN (
                        SELECT cf.course_id,
                               COUNT(DISTINCT cf.faculty_id) AS assigned_faculty_count,
                               GROUP_CONCAT(DISTINCT f.full_name ORDER BY f.full_name SEPARATOR '||') AS assigned_faculty_names
                        FROM course_faculty cf
                        INNER JOIN faculty f ON f.id = cf.faculty_id AND f.is_active = 1
                        GROUP BY cf.course_id
                    ) af ON af.course_id = c.id
                    $where
                    ORDER BY c.semester, c.code";
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
        $code = trim($input['code'] ?? '');
        $name = trim($input['name'] ?? '');
        $credit_hours = (int)($input['credit_hours'] ?? 3);
        $credit_hours_lab = (int)($input['credit_hours_lab'] ?? 0);
        $semester = (int)($input['semester'] ?? 1);
        $department_id = !empty($input['department_id']) ? (int)$input['department_id'] : null;
        $sessions_per_week = max(1, (int)($input['sessions_per_week'] ?? 1));
        if (!$code || !$name) api_error('Please enter course code and name.');
        $err = validate_max_length($code, MAX_COURSE_CODE, 'Course code');
        if ($err) api_error($err);
        $err = validate_max_length($name, MAX_COURSE_NAME, 'Course name');
        if ($err) api_error($err);
        $stmt = $conn->prepare('INSERT INTO course (code, name, credit_hours, credit_hours_lab, semester, department_id, sessions_per_week) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssiiiii', $code, $name, $credit_hours, $credit_hours_lab, $semester, $department_id, $sessions_per_week);
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'A course with this code already exists.'));
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Course created.']);
        break;
    case 'PUT':
        $id = (int)($input['id'] ?? 0);
        if (!$id) api_error('Invalid course.');
        $code = trim($input['code'] ?? '');
        $name = trim($input['name'] ?? '');
        $credit_hours = (int)($input['credit_hours'] ?? 3);
        $credit_hours_lab = (int)($input['credit_hours_lab'] ?? 0);
        $semester = (int)($input['semester'] ?? 1);
        $department_id = !empty($input['department_id']) ? (int)$input['department_id'] : null;
        $sessions_per_week = max(1, (int)($input['sessions_per_week'] ?? 1));
        $err = validate_max_length($code, MAX_COURSE_CODE, 'Course code');
        if ($err) api_error($err);
        $err = validate_max_length($name, MAX_COURSE_NAME, 'Course name');
        if ($err) api_error($err);
        $stmt = $conn->prepare('UPDATE course SET code=?, name=?, credit_hours=?, credit_hours_lab=?, semester=?, department_id=?, sessions_per_week=? WHERE id=?');
        $stmt->bind_param('ssiiiiii', $code, $name, $credit_hours, $credit_hours_lab, $semester, $department_id, $sessions_per_week, $id);
        if (!$stmt->execute()) api_error(db_duplicate_message($conn, 'Another course already uses this code.'));
        echo json_encode(['success' => true, 'message' => 'Course updated.']);
        break;
    case 'DELETE':
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) api_error('Invalid course.');
        $stmt = $conn->prepare('UPDATE course SET is_active = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) api_error('Could not delete course.');
        echo json_encode(['success' => true, 'message' => 'Course removed.']);
        break;
    default:
        api_error('Method not allowed.', 405);
}
