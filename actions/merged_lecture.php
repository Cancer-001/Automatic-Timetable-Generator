<?php
/**
 * Admin API: create merged lectures (multiple semester/section cohorts, one slot).
 */
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api_response.php';
require_once __DIR__ . '/../config/merged_lecture.php';

merged_lecture_ensure_schema($conn);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($method === 'POST') {
    $session_id = (int) ($input['academic_session_id'] ?? 0);
    $course_id = (int) ($input['course_id'] ?? 0);
    $faculty_id = (int) ($input['faculty_id'] ?? 0);
    $room_id = (int) ($input['room_id'] ?? 0);
    $slot_id = (int) ($input['time_slot_id'] ?? 0);
    $groups = $input['section_groups'] ?? $input['cohorts'] ?? [];
    if (!is_array($groups)) {
        $groups = [];
    }

    if (!$session_id || !$course_id || !$faculty_id || !$room_id || !$slot_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'academic_session_id, course_id, faculty_id, room_id, and time_slot_id are required.']);
        exit;
    }

    $result = create_merged_lecture($conn, $session_id, $course_id, $faculty_id, $room_id, $slot_id, $groups);
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
    exit;
}

ob_end_clean();
api_error('Method not allowed.', 405);
