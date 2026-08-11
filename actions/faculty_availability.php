<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api_response.php';
require_once __DIR__ . '/../config/validation.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    api_error('Not logged in.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($method) {
    case 'GET':
        $stmt = $conn->prepare('SELECT availability_notes FROM faculty WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $notes = $row ? ($row['availability_notes'] ?? '') : '';
        echo json_encode(['availability_notes' => $notes]);
        break;

    case 'POST':
        $notes = isset($input['availability_notes']) ? trim((string)$input['availability_notes']) : '';
        if (!is_string($input['availability_notes'] ?? null)) $notes = '';
        $err = validate_max_length($notes, MAX_NOTES, 'Availability notes');
        if ($err) api_error($err);
        $stmt = $conn->prepare('UPDATE faculty SET availability_notes = ? WHERE id = ?');
        $stmt->bind_param('si', $notes, $user_id);
        if (!$stmt->execute()) {
            api_error('Could not save preferences.');
        }
        api_success([], 'Preferences saved.');
        break;

    default:
        api_error('Method not allowed.', 405);
}
