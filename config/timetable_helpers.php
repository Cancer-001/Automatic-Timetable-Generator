<?php

function tt_norm_time($time): string {
    $raw = trim((string)$time);
    if ($raw === '') {
        return '00:00:00';
    }
    $parts = explode(':', $raw);
    $h = max(0, min(23, (int)($parts[0] ?? 0)));
    $m = max(0, min(59, (int)($parts[1] ?? 0)));
    $s = max(0, min(59, (int)($parts[2] ?? 0)));
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

function tt_minutes_from_time($time): int {
    $t = tt_norm_time($time);
    $parts = explode(':', $t);
    return ((int)$parts[0] * 60) + (int)$parts[1] + ((int)$parts[2] > 0 ? 1 : 0);
}

function tt_duration_hours($start, $end): float {
    $sm = tt_minutes_from_time($start);
    $em = tt_minutes_from_time($end);
    if ($em <= $sm) {
        return 0.0;
    }
    return round(($em - $sm) / 60, 2);
}

function tt_duration_label($start, $end): string {
    $duration = tt_duration_hours($start, $end);
    if ($duration <= 0) {
        return '0';
    }
    $label = number_format($duration, 2, '.', '');
    return rtrim(rtrim($label, '0'), '.');
}

function tt_normalized_token($value): string {
    return preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$value));
}

function tt_degree_department_ids(mysqli $conn, int $degree_id): array {
    if ($degree_id <= 0) {
        return [];
    }
    $degreeTable = $conn->query("SHOW TABLES LIKE 'degree'");
    if (!$degreeTable || $degreeTable->num_rows === 0) {
        return [];
    }
    $st = $conn->prepare('SELECT code, name FROM degree WHERE id = ? AND is_active = 1 LIMIT 1');
    if (!$st) {
        return [];
    }
    $st->bind_param('i', $degree_id);
    $st->execute();
    $degree = $st->get_result()->fetch_assoc();
    if (!$degree) {
        return [];
    }

    $degreeToken = tt_normalized_token(($degree['code'] ?? '') . ' ' . ($degree['name'] ?? ''));
    if ($degreeToken === '') {
        return [];
    }

    $ids = [];
    $res = $conn->query('SELECT id, code, name FROM department ORDER BY id');
    while ($res && ($dept = $res->fetch_assoc())) {
        $codeToken = tt_normalized_token($dept['code'] ?? '');
        $nameToken = tt_normalized_token($dept['name'] ?? '');
        if ($codeToken !== '' && strpos($degreeToken, $codeToken) !== false) {
            $ids[] = (int)$dept['id'];
            continue;
        }
        if ($nameToken !== '' && strpos($degreeToken, $nameToken) !== false) {
            $ids[] = (int)$dept['id'];
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

function tt_apply_degree_course_filter(mysqli $conn, string &$where, array &$params, string &$types, int $degree_id, string $courseAlias = 'c'): void {
    if ($degree_id <= 0) {
        return;
    }

    $clauses = [];
    $cfaExists = $conn->query("SHOW TABLES LIKE 'course_faculty_assignment'");
    if ($cfaExists && $cfaExists->num_rows > 0) {
        $clauses[] = "EXISTS (
            SELECT 1 FROM course_faculty_assignment cfa_degree
            WHERE cfa_degree.course_id = {$courseAlias}.id
              AND cfa_degree.degree_id = ?
        )";
        $params[] = $degree_id;
        $types .= 'i';
    }

    $deptIds = tt_degree_department_ids($conn, $degree_id);
    if (!empty($deptIds)) {
        $ph = implode(',', array_fill(0, count($deptIds), '?'));
        $clauses[] = "{$courseAlias}.department_id IN ($ph)";
        foreach ($deptIds as $deptId) {
            $params[] = (int)$deptId;
            $types .= 'i';
        }
    }

    if (!empty($clauses)) {
        $where .= ' AND (' . implode(' OR ', $clauses) . ')';
    }
}

function tt_ensure_flexible_time_slot_index(mysqli $conn): void {
    $res = @$conn->query("SHOW INDEX FROM time_slot WHERE Key_name = 'uq_slot'");
    $cols = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $seq = (int)($row['Seq_in_index'] ?? 0);
        $cols[$seq] = (string)($row['Column_name'] ?? '');
    }
    ksort($cols);
    $cols = array_values($cols);
    if ($cols === ['day_of_week', 'start_time', 'end_time', 'slot_type']) {
        return;
    }

    @$conn->query('ALTER TABLE time_slot DROP INDEX uq_slot');
    @$conn->query('ALTER TABLE time_slot ADD UNIQUE KEY uq_slot (day_of_week, start_time, end_time, slot_type)');
}

function tt_find_or_create_time_slot(mysqli $conn, int $dayOfWeek, string $start, string $end, string $slotType = 'lecture'): ?array {
    $dayOfWeek = max(1, min(7, $dayOfWeek));
    $start = tt_norm_time($start);
    $end = tt_norm_time($end);
    $slotType = strtolower(trim($slotType)) === 'lab' ? 'lab' : 'lecture';

    if (tt_minutes_from_time($end) <= tt_minutes_from_time($start)) {
        return null;
    }

    $st = $conn->prepare(
        'SELECT id, day_of_week, start_time, end_time, slot_label, COALESCE(slot_type, "lecture") AS slot_type
         FROM time_slot
         WHERE day_of_week = ? AND start_time = ? AND end_time = ? AND COALESCE(slot_type, "lecture") = ?
         LIMIT 1'
    );
    if ($st) {
        $st->bind_param('isss', $dayOfWeek, $start, $end, $slotType);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row) {
            $row['start_time'] = tt_norm_time($row['start_time']);
            $row['end_time'] = tt_norm_time($row['end_time']);
            return $row;
        }
    }

    tt_ensure_flexible_time_slot_index($conn);

    $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $label = sprintf('%s-%s-%s', $dayNames[$dayOfWeek] ?? 'Day', substr($start, 0, 5), substr($end, 0, 5));
    $ins = $conn->prepare(
        'INSERT INTO time_slot (day_of_week, start_time, end_time, slot_label, slot_type)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$ins) {
        return null;
    }
    $ins->bind_param('issss', $dayOfWeek, $start, $end, $label, $slotType);
    if (!$ins->execute()) {
        $st2 = $conn->prepare(
            'SELECT id, day_of_week, start_time, end_time, slot_label, COALESCE(slot_type, "lecture") AS slot_type
             FROM time_slot
             WHERE day_of_week = ? AND start_time = ? AND end_time = ? AND COALESCE(slot_type, "lecture") = ?
             LIMIT 1'
        );
        if (!$st2) {
            return null;
        }
        $st2->bind_param('isss', $dayOfWeek, $start, $end, $slotType);
        $st2->execute();
        $row = $st2->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }
        $row['start_time'] = tt_norm_time($row['start_time']);
        $row['end_time'] = tt_norm_time($row['end_time']);
        return $row;
    }

    return [
        'id' => (int)$conn->insert_id,
        'day_of_week' => $dayOfWeek,
        'start_time' => $start,
        'end_time' => $end,
        'slot_label' => $label,
        'slot_type' => $slotType,
    ];
}
