<?php
/**
 * Reusable server-side validation helpers.
 * Use with config/constants.php for field limits.
 * Returns user-facing error message or null if valid.
 */

require_once __DIR__ . '/constants.php';

/**
 * Validate string length. Returns error message "Limit N characters." or null.
 * @param string $value
 * @param int $max
 * @param string|null $fieldLabel Optional label for message (e.g. "Department name").
 * @return string|null Error message or null if valid
 */
function validate_max_length($value, $max, $fieldLabel = null) {
    $len = strlen(trim($value));
    if ($len === 0) return null; // empty is handled by required checks elsewhere
    if ($len <= $max) return null;
    return ($fieldLabel ? $fieldLabel . ': ' : '') . 'Limit ' . $max . ' characters.';
}

/**
 * Validate and return first error from multiple checks, or null.
 * @param array $checks [ [ value, max, label ], ... ]
 * @return string|null
 */
function validate_max_lengths($checks) {
    foreach ($checks as $c) {
        $val = $c[0];
        $max = $c[1];
        $label = $c[2] ?? null;
        $err = validate_max_length($val, $max, $label);
        if ($err !== null) return $err;
    }
    return null;
}
