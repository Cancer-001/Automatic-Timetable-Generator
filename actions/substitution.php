<?php
session_start();
header('Content-Type: application/json');
$role = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!isset($_SESSION['user_id']) || !$role) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api_response.php';
require_once __DIR__ . '/../config/validation.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        $sql = "SELECT sr.id, sr.faculty_id, sr.schedule_id, sr.requested_date, sr.reason, sr.status, sr.admin_notes, sr.created_at,
                f.full_name AS faculty_name,
                c.name AS course_name, c.code AS course_code,
                ts.slot_label
                FROM substitution_request sr
                JOIN faculty f ON f.id = sr.faculty_id
                JOIN schedule s ON s.id = sr.schedule_id
                JOIN course c ON c.id = s.course_id
                JOIN time_slot ts ON ts.id = s.time_slot_id
                WHERE 1=1";
        if ($role === 'faculty') {
            $sql .= " AND sr.faculty_id = ?";
        }
        $sql .= " ORDER BY sr.created_at DESC";
        if ($role === 'faculty') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        if ($res === false) {
            api_error('Could not load substitution requests.');
        }
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        break;

    case 'POST':
        if ($role !== 'faculty') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $schedule_id = (int)($input['schedule_id'] ?? 0);
        $requested_date = trim($input['requested_date'] ?? '');
        $reason = isset($input['reason']) ? trim((string)$input['reason']) : '';
        if (!$schedule_id || !$requested_date) {
            api_error('Schedule and date are required.');
        }
        if ($reason !== '' && strlen($reason) > MAX_NOTES) {
            api_error('Reason is too long.');
        }
        // Ensure the schedule belongs to this faculty
        $chk = $conn->prepare('SELECT id FROM schedule WHERE id = ? AND faculty_id = ? LIMIT 1');
        $chk->bind_param('ii', $schedule_id, $user_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            api_error('You can only request substitution for your own scheduled class.');
        }
        $stmt = $conn->prepare('INSERT INTO substitution_request (faculty_id, schedule_id, requested_date, reason) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiss', $user_id, $schedule_id, $requested_date, $reason);
        if (!$stmt->execute()) {
            api_error('Could not submit request.');
        }
        api_success([], 'Substitution request submitted.');
        break;

    case 'PUT':
        if ($role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $id = (int)($input['id'] ?? 0);
        $status = trim(strtolower($input['status'] ?? ''));
        $admin_notes = isset($input['admin_notes']) ? trim($input['admin_notes']) : null;

        if (!$id) {
            api_error('Invalid request id.');
        }
        if (!in_array($status, ['approved', 'rejected'], true)) {
            api_error('Status must be "approved" or "rejected".');
        }
        if ($admin_notes !== null && $admin_notes !== '') {
            $err = validate_max_length($admin_notes, MAX_NOTES, 'Admin notes');
            if ($err) api_error($err);
        }

        $stmt = $conn->prepare('UPDATE substitution_request SET status = ?, admin_notes = COALESCE(?, admin_notes) WHERE id = ?');
        $stmt->bind_param('ssi', $status, $admin_notes, $id);
        if (!$stmt->execute()) {
            api_error('Could not update the request.');
        }
        if ($stmt->affected_rows === 0) {
            api_error('Request not found or already processed.');
        }

        api_success([], $status === 'approved' ? 'Substitution request approved.' : 'Substitution request rejected.');
        break;

    default:
        api_error('Method not allowed.', 405);
}
