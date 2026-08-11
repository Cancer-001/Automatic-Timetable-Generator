<?php
/**
 * Manual move: change room or time_slot for a schedule entry.
 * Conflict checks:
 *   1. Room not already booked at the new slot.
 *   2. Faculty not already teaching at the new slot.
 *   3. No student (same semester+section) already has a class at the new slot.
 */
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/merged_lecture.php';
require_once __DIR__ . '/../config/timetable_helpers.php';

merged_lecture_ensure_schema($conn);

$input       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$schedule_id = (int)($input['schedule_id'] ?? 0);
$new_room_id = !empty($input['room_id'])      ? (int)$input['room_id']      : null;
$new_slot_id = !empty($input['time_slot_id']) ? (int)$input['time_slot_id'] : null;
$new_faculty_id = !empty($input['faculty_id']) ? (int)$input['faculty_id'] : null;

if (!$schedule_id || (!$new_room_id && !$new_slot_id && !$new_faculty_id)) {
    echo json_encode(['success' => false, 'message' => 'Schedule ID and at least one of faculty_id, room_id or time_slot_id is required.']);
    exit;
}

// Fetch current schedule row
$row = $conn->query("SELECT id, academic_session_id, course_id, faculty_id, room_id, time_slot_id, semester, section FROM schedule WHERE id = $schedule_id")->fetch_assoc();
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Schedule entry not found.']);
    exit;
}

$room_id    = $new_room_id    !== null ? $new_room_id    : (int)$row['room_id'];
$slot_id    = $new_slot_id    !== null ? $new_slot_id    : (int)$row['time_slot_id'];
$session_id = (int)$row['academic_session_id'];
$faculty_id = $new_faculty_id !== null ? $new_faculty_id : (int)$row['faculty_id'];
$semester   = (int)$row['semester'];
$section    = trim($row['section']);

// Keep component integrity when moving: a lecture entry must remain on lecture slots,
// and a lab entry must remain on lab slots.
$slotMetaStmt = $conn->prepare('SELECT id, day_of_week, start_time, end_time, COALESCE(slot_type, "lecture") AS slot_type FROM time_slot WHERE id = ? LIMIT 1');
$oldSlotId = (int)$row['time_slot_id'];
$slotMetaStmt->bind_param('i', $oldSlotId);
$slotMetaStmt->execute();
$oldSlotMeta = $slotMetaStmt->get_result()->fetch_assoc();
if (!$oldSlotMeta) {
    echo json_encode(['success' => false, 'message' => 'Current time slot metadata not found.']);
    exit;
}
$slotMetaStmt->bind_param('i', $slot_id);
$slotMetaStmt->execute();
$newSlotMeta = $slotMetaStmt->get_result()->fetch_assoc();
if (!$newSlotMeta) {
    echo json_encode(['success' => false, 'message' => 'Selected time slot not found.']);
    exit;
}
$oldSlotType = strtolower((string)($oldSlotMeta['slot_type'] ?? 'lecture'));
$newSlotType = strtolower((string)($newSlotMeta['slot_type'] ?? 'lecture'));
if ($oldSlotType !== $newSlotType) {
    echo json_encode([
        'success' => false,
        'message' => 'Slot type mismatch: this class is a ' . $oldSlotType . ' entry and can only be moved to ' . $oldSlotType . ' slots.',
        'conflict' => 'slot_type'
    ]);
    exit;
}
$newSlotDay = (int)($newSlotMeta['day_of_week'] ?? 0);
$newSlotStart = tt_norm_time($newSlotMeta['start_time'] ?? '00:00:00');
$newSlotEnd = tt_norm_time($newSlotMeta['end_time'] ?? '00:00:00');

// Room type compatibility for selected slot type.
$roomMetaStmt = $conn->prepare('SELECT id, LOWER(TRIM(COALESCE(room_type, ""))) AS room_type FROM room WHERE id = ? AND is_active = 1 LIMIT 1');
$roomMetaStmt->bind_param('i', $room_id);
$roomMetaStmt->execute();
$roomMeta = $roomMetaStmt->get_result()->fetch_assoc();
if (!$roomMeta) {
    echo json_encode(['success' => false, 'message' => 'Selected room is not available.', 'conflict' => 'room']);
    exit;
}
$roomType = (string)($roomMeta['room_type'] ?? '');
$isLabRoom = ($roomType === 'lab');
if ($newSlotType === 'lab' && !$isLabRoom) {
    echo json_encode(['success' => false, 'message' => 'Room type mismatch: lab slots require a lab room.', 'conflict' => 'room_type']);
    exit;
}
if ($newSlotType === 'lecture' && $isLabRoom) {
    echo json_encode(['success' => false, 'message' => 'Room type mismatch: lecture slots require a non-lab room.', 'conflict' => 'room_type']);
    exit;
}

if ($new_faculty_id !== null) {
    $chkF = $conn->prepare('SELECT id FROM faculty WHERE id = ? AND is_active = 1 LIMIT 1');
    $chkF->bind_param('i', $faculty_id);
    $chkF->execute();
    if (!$chkF->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Selected faculty is not available (inactive or missing).', 'conflict' => 'faculty']);
        exit;
    }
}

// ── 1. Room conflict ─────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT s.id
     FROM schedule s
     JOIN time_slot t ON t.id = s.time_slot_id
     WHERE s.academic_session_id = ?
       AND s.room_id = ?
       AND s.id != ?
       AND t.day_of_week = ?
       AND ? < t.end_time
       AND ? > t.start_time
     LIMIT 1'
);
$stmt->bind_param('iiiiss', $session_id, $room_id, $schedule_id, $newSlotDay, $newSlotStart, $newSlotEnd);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => '⚠ Conflict: Room is already booked during this time.', 'conflict' => 'room']);
    exit;
}

// ── 2. Faculty conflict ──────────────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT s.id
     FROM schedule s
     JOIN time_slot t ON t.id = s.time_slot_id
     WHERE s.academic_session_id = ?
       AND s.faculty_id = ?
       AND s.id != ?
       AND t.day_of_week = ?
       AND ? < t.end_time
       AND ? > t.start_time
     LIMIT 1'
);
$stmt->bind_param('iiiiss', $session_id, $faculty_id, $schedule_id, $newSlotDay, $newSlotStart, $newSlotEnd);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => '⚠ Conflict: Teacher is already assigned during this time.', 'conflict' => 'faculty']);
    exit;
}

// ── 3. Student / cohort conflict (includes merged-lecture members) ───────────
$cohorts = merged_lecture_schedule_cohorts($conn, $schedule_id);
if ($cohorts === []) {
    $cohorts = [['semester' => $semester, 'section' => $section]];
}
foreach ($cohorts as $ch) {
    $hitStmt = $conn->prepare(
        'SELECT s.id, c.name AS course_name
         FROM schedule s
         JOIN time_slot t ON t.id = s.time_slot_id
         JOIN course c ON c.id = s.course_id
         WHERE s.academic_session_id = ?
           AND s.id != ?
           AND t.day_of_week = ?
           AND ? < t.end_time
           AND ? > t.start_time
           AND (
                (s.semester = ? AND LOWER(TRIM(s.section)) = LOWER(?))
                OR EXISTS (
                    SELECT 1 FROM schedule_merge_member m
                    WHERE m.schedule_id = s.id
                      AND m.semester = ?
                      AND LOWER(TRIM(m.section)) = LOWER(?)
                )
           )
         LIMIT 1'
    );
    $chSem = (int)$ch['semester'];
    $chSec = trim((string)$ch['section']);
    $hitStmt->bind_param('iiissisis', $session_id, $schedule_id, $newSlotDay, $newSlotStart, $newSlotEnd, $chSem, $chSec, $chSem, $chSec);
    $hitStmt->execute();
    $hit = $hitStmt->get_result()->fetch_assoc();
    if ($hit !== null) {
        $cn = htmlspecialchars($hit['course_name']);
        echo json_encode([
            'success'  => false,
            'message'  => '⚠ Student conflict: "' . $cn . '" already overlaps this time for Sem '
                . $ch['semester'] . ' / Sec ' . htmlspecialchars($ch['section']) . '.',
            'conflict' => 'student',
        ]);
        exit;
    }
}

// ── 4. Room capacity (merged / single cohort) when room changes ──────────────
if ($new_room_id !== null && $room_id !== (int) $row['room_id']) {
    $course_id_row = (int) $row['course_id'];
    $rc = $conn->prepare('SELECT capacity FROM room WHERE id = ? AND is_active = 1 LIMIT 1');
    $rc->bind_param('i', $room_id);
    $rc->execute();
    $capRow = $rc->get_result()->fetch_assoc();
    $cap = $capRow ? (int) $capRow['capacity'] : 0;
    $total = 0;
    foreach ($cohorts as $ch) {
        $total += merged_lecture_cohort_headcount($conn, $course_id_row, $session_id, $ch['semester'], $ch['section']);
    }
    if ($total > $cap) {
        echo json_encode([
            'success'  => false,
            'message'  => '⚠ Room capacity (' . $cap . ') is less than combined cohort size (' . $total . ').',
            'conflict' => 'capacity',
        ]);
        exit;
    }
}

// ── All clear — apply move/swap ──────────────────────────────────────────────
$stmt = $conn->prepare('UPDATE schedule SET faculty_id = ?, room_id = ?, time_slot_id = ? WHERE id = ?');
$stmt->bind_param('iiii', $faculty_id, $room_id, $slot_id, $schedule_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error: could not update schedule.']);
    exit;
}
echo json_encode(['success' => true, 'message' => 'Schedule updated successfully (faculty/room/slot).']);
