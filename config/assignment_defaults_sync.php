<?php
/**
 * Sync course_faculty → course_faculty_assignment with Session, Section (A/B/C), time window, room, degree.
 * Used by timetable Generate and by Assign Faculty GET so modals stay filled without running Generate first.
 */

function assignment_defaults_ensure_table(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS course_faculty_assignment (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_id INT UNSIGNED NOT NULL,
        faculty_id INT UNSIGNED NOT NULL,
        academic_session_id INT UNSIGNED DEFAULT NULL,
        degree_id INT UNSIGNED DEFAULT NULL,
        section VARCHAR(32) DEFAULT NULL,
        preferred_day_of_week TINYINT UNSIGNED DEFAULT NULL COMMENT '1=Mon .. 5=Fri (matches time_slot.day_of_week)',
        preferred_start_time TIME DEFAULT NULL,
        preferred_end_time TIME DEFAULT NULL,
        room_id INT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cfa_course (course_id),
        INDEX idx_cfa_faculty (faculty_id),
        FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
        FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
        FOREIGN KEY (academic_session_id) REFERENCES academic_session(id) ON DELETE SET NULL,
        FOREIGN KEY (room_id) REFERENCES room(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $chk = @$conn->query("SHOW COLUMNS FROM course_faculty_assignment LIKE 'preferred_day_of_week'");
    if ($chk && $chk->num_rows === 0) {
        @$conn->query("ALTER TABLE course_faculty_assignment ADD COLUMN preferred_day_of_week TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '1=Mon .. 5=Fri' AFTER section");
    }
}

/**
 * Defaults for modal sync: latest active session, first lecture slot, ordered active room ids, first degree.
 *
 * @return array{session_id:int,pref_start:string,pref_end:string,room_id:int,room_ids:list<int>,degree_id:?int}|null
 */
function assignment_defaults_resolve_context(mysqli $conn): ?array {
    $sessionId = null;
    $rs = $conn->query('SELECT id FROM academic_session WHERE is_active = 1 ORDER BY start_date DESC, id DESC LIMIT 1');
    if ($rs && ($x = $rs->fetch_assoc())) {
        $sessionId = (int) $x['id'];
    }
    if ($sessionId === null) {
        $rs = $conn->query('SELECT id FROM academic_session ORDER BY start_date DESC, id DESC LIMIT 1');
        if ($rs && ($x = $rs->fetch_assoc())) {
            $sessionId = (int) $x['id'];
        }
    }
    if ($sessionId === null || $sessionId <= 0) {
        return null;
    }

    $prefSt = '08:00:00';
    $prefEn = '09:30:00';
    $rs = $conn->query('SELECT start_time, end_time FROM time_slot WHERE COALESCE(slot_type,\'lecture\') = \'lecture\' AND day_of_week BETWEEN 1 AND 5 ORDER BY day_of_week, start_time LIMIT 1');
    if ($rs && ($x = $rs->fetch_assoc())) {
        $prefSt = substr((string) $x['start_time'], 0, 8);
        $prefEn = substr((string) $x['end_time'], 0, 8);
    }

    $roomIds = [];
    $rs = $conn->query('SELECT id FROM room WHERE is_active = 1 ORDER BY id ASC');
    if ($rs) {
        while ($x = $rs->fetch_assoc()) {
            $roomIds[] = (int) $x['id'];
        }
    }
    if ($roomIds === []) {
        return null;
    }
    $roomId = $roomIds[0];

    $degreeId = null;
    $degRes = @$conn->query('SELECT id FROM degree ORDER BY id ASC LIMIT 1');
    if ($degRes && ($d = $degRes->fetch_assoc())) {
        $degreeId = (int) $d['id'];
    }

    return [
        'session_id' => $sessionId,
        'pref_start' => $prefSt,
        'pref_end'   => $prefEn,
        'room_id'    => $roomId,
        'room_ids'   => $roomIds,
        'degree_id'  => $degreeId,
    ];
}

/**
 * @param array<int, list<int>> $byCourse course_id => sorted faculty ids (caller may sort)
 */
function assignment_defaults_sync_pairs(
    mysqli $conn,
    array $byCourse,
    int $defaultSessionId,
    string $prefStart,
    string $prefEnd,
    array $defaultRoomIds,
    ?int $defaultDegreeId
): int {
    assignment_defaults_ensure_table($conn);

    $defaultRoomIds = array_values(array_map('intval', $defaultRoomIds));
    $nRooms = count($defaultRoomIds);
    if ($nRooms === 0) {
        return 0;
    }
    $firstRoomStubId = (int) $defaultRoomIds[0];

    $ps = substr($prefStart, 0, 8);
    $pe = substr($prefEnd, 0, 8);
    $sections = ['A', 'B', 'C'];

    $sel = $conn->prepare(
        'SELECT id, academic_session_id, degree_id, section, preferred_day_of_week, preferred_start_time, preferred_end_time, room_id
         FROM course_faculty_assignment WHERE course_id = ? AND faculty_id = ? ORDER BY id ASC LIMIT 1'
    );
    $insNoDeg = $conn->prepare(
        'INSERT INTO course_faculty_assignment
        (course_id, faculty_id, academic_session_id, section, preferred_day_of_week, preferred_start_time, preferred_end_time, room_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insDeg = $conn->prepare(
        'INSERT INTO course_faculty_assignment
        (course_id, faculty_id, academic_session_id, degree_id, section, preferred_day_of_week, preferred_start_time, preferred_end_time, room_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $updNoDeg = $conn->prepare(
        'UPDATE course_faculty_assignment SET
            academic_session_id = COALESCE(academic_session_id, ?),
            preferred_day_of_week = COALESCE(preferred_day_of_week, ?),
            section = CASE WHEN section IS NULL OR TRIM(section) = \'\' THEN ? ELSE section END,
            preferred_start_time = COALESCE(preferred_start_time, ?),
            preferred_end_time = COALESCE(preferred_end_time, ?),
            room_id = IF(room_id IS NULL OR room_id = ?, ?, room_id)
         WHERE id = ?'
    );
    $updDeg = $conn->prepare(
        'UPDATE course_faculty_assignment SET
            academic_session_id = COALESCE(academic_session_id, ?),
            degree_id = COALESCE(degree_id, ?),
            preferred_day_of_week = COALESCE(preferred_day_of_week, ?),
            section = CASE WHEN section IS NULL OR TRIM(section) = \'\' THEN ? ELSE section END,
            preferred_start_time = COALESCE(preferred_start_time, ?),
            preferred_end_time = COALESCE(preferred_end_time, ?),
            room_id = IF(room_id IS NULL OR room_id = ?, ?, room_id)
         WHERE id = ?'
    );

    if (!$sel || !$insNoDeg || !$updNoDeg) {
        return 0;
    }
    $useDeg = ($defaultDegreeId !== null && $insDeg && $updDeg);

    $n = 0;
    foreach ($byCourse as $cid => $fids) {
        $cid = (int) $cid;
        sort($fids, SORT_NUMERIC);
        foreach ($fids as $i => $fid) {
            $fid = (int) $fid;
            $sec = $sections[$i % 3];
            $prefDow = ((($cid % 5) + $i) % 5) + 1;
            $pairRoomId = $defaultRoomIds[(($cid + $fid + $i) % $nRooms)];
            $sel->bind_param('ii', $cid, $fid);
            $sel->execute();
            $ex = $sel->get_result()->fetch_assoc();

            if (!$ex) {
                if ($useDeg) {
                    $insDeg->bind_param(
                        'iiiisissi',
                        $cid,
                        $fid,
                        $defaultSessionId,
                        $defaultDegreeId,
                        $sec,
                        $prefDow,
                        $ps,
                        $pe,
                        $pairRoomId
                    );
                    if ($insDeg->execute()) {
                        $n++;
                    }
                } else {
                    $insNoDeg->bind_param(
                        'iiisissi',
                        $cid,
                        $fid,
                        $defaultSessionId,
                        $sec,
                        $prefDow,
                        $ps,
                        $pe,
                        $pairRoomId
                    );
                    if ($insNoDeg->execute()) {
                        $n++;
                    }
                }
                continue;
            }

            $aid = (int) $ex['id'];
            if ($useDeg) {
                $updDeg->bind_param(
                    'iiisssiii',
                    $defaultSessionId,
                    $defaultDegreeId,
                    $prefDow,
                    $sec,
                    $ps,
                    $pe,
                    $firstRoomStubId,
                    $pairRoomId,
                    $aid
                );
                if ($updDeg->execute() && $updDeg->affected_rows > 0) {
                    $n++;
                }
            } else {
                $updNoDeg->bind_param(
                    'iisssiii',
                    $defaultSessionId,
                    $prefDow,
                    $sec,
                    $ps,
                    $pe,
                    $firstRoomStubId,
                    $pairRoomId,
                    $aid
                );
                if ($updNoDeg->execute() && $updNoDeg->affected_rows > 0) {
                    $n++;
                }
            }
        }
    }

    return $n;
}

function assignment_defaults_sync_all_active_courses(
    mysqli $conn,
    int $defaultSessionId,
    string $prefStart,
    string $prefEnd,
    array $defaultRoomIds,
    ?int $defaultDegreeId
): int {
    $res = $conn->query(
        'SELECT cf.course_id, cf.faculty_id FROM course_faculty cf
         INNER JOIN course c ON c.id = cf.course_id AND c.is_active = 1
         ORDER BY cf.course_id, cf.faculty_id'
    );
    if (!$res) {
        return 0;
    }
    $byCourse = [];
    while ($row = $res->fetch_assoc()) {
        $cid = (int) $row['course_id'];
        $byCourse[$cid][] = (int) $row['faculty_id'];
    }

    return assignment_defaults_sync_pairs(
        $conn,
        $byCourse,
        $defaultSessionId,
        $prefStart,
        $prefEnd,
        $defaultRoomIds,
        $defaultDegreeId
    );
}

function assignment_defaults_sync_one_course(
    mysqli $conn,
    int $courseId,
    int $defaultSessionId,
    string $prefStart,
    string $prefEnd,
    array $defaultRoomIds,
    ?int $defaultDegreeId
): int {
    $st = $conn->prepare('SELECT faculty_id FROM course_faculty WHERE course_id = ? ORDER BY faculty_id');
    if (!$st) {
        return 0;
    }
    $st->bind_param('i', $courseId);
    $st->execute();
    $rf = $st->get_result();
    $fids = [];
    while ($row = $rf->fetch_assoc()) {
        $fids[] = (int) $row['faculty_id'];
    }
    if ($fids === []) {
        return 0;
    }

    return assignment_defaults_sync_pairs(
        $conn,
        [$courseId => $fids],
        $defaultSessionId,
        $prefStart,
        $prefEnd,
        $defaultRoomIds,
        $defaultDegreeId
    );
}
