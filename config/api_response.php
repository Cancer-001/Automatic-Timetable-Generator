<?php
/**
 * Standard API response helpers - all APIs should return same shape for errors
 * Success: ['success' => true, 'message' => '...', ...]
 * Error:   ['success' => false, 'message' => 'User-friendly message']
 */

function api_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function api_success($data = [], $message = null) {
    $out = array_merge(['success' => true], $data);
    if ($message !== null) $out['message'] = $message;
    echo json_encode($out);
    exit;
}

/** Return user-friendly message for MySQL duplicate key (errno 1062) */
function db_duplicate_message($conn, $default = 'A record with this value already exists.') {
    $err = $conn->error ?? '';
    $errno = $conn->errno ?? 0;
    if ($errno !== 1062) return $default;
    if (stripos($err, 'code') !== false) return 'This code is already in use. Please choose a different one.';
    if (stripos($err, 'email') !== false) return 'This email is already registered.';
    if (stripos($err, 'room_number') !== false) return 'This room number already exists.';
    if (stripos($err, 'name') !== false) return 'This name is already in use.';
    return $default;
}
