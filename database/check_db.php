<?php
/**
 * Check if database exists and is accessible. Used by window_checklist.bat
 * Exit 0 = OK, exit 1 = not found or error
 */
$conn = @new mysqli('localhost', 'root', '', 'assignmentupdated');
if ($conn && !$conn->connect_error) {
    $conn->close();
    exit(0);
}
exit(1);
