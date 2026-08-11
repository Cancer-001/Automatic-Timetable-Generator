<?php
/**
 * Student self-enrollment is disabled — courses are assigned by administration.
 */
session_start();
header('Content-Type: application/json');
http_response_code(403);
echo json_encode([
    'success' => false,
    'message' => 'Self-enrollment is not available. Contact your administrator for course allocation.',
]);
exit;
