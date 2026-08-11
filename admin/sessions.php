<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$sessionProgramOptions = [];
$sessionProgramSource = 'static';
$sessionProgramColumnExists = false;
$sessionProgramColumnName = '';

if (isset($conn) && $conn instanceof mysqli) {
    // Use Program CRUD source (degree table behind actions/degrees.php).
    $hasDegreeTable = false;
    $chkDegrees = @$conn->query("SHOW TABLES LIKE 'degree'");
    if ($chkDegrees && $chkDegrees->num_rows > 0) {
        $hasDegreeTable = true;
    }
    if ($hasDegreeTable) {
        $rows = @$conn->query("SELECT code, name FROM degree WHERE is_active = 1 ORDER BY name ASC");
        if ($rows) {
            while ($r = $rows->fetch_assoc()) {
                $code = trim((string)($r['code'] ?? ''));
                $name = trim((string)($r['name'] ?? ''));
                $label = trim($code . ($name !== '' ? (' - ' . $name) : ''));
                if ($label !== '') {
                    $sessionProgramOptions[] = $label;
                }
            }
        }
    }
    if (!empty($sessionProgramOptions)) {
        $sessionProgramSource = 'program-crud';
    } else {
        $sessionProgramOptions = ['BSCS', 'BBA', 'BSIT', 'MBA'];
    }

    $colSession = @$conn->query("SHOW COLUMNS FROM academic_session LIKE 'program'");
    if ($colSession && $colSession->num_rows > 0) {
        $sessionProgramColumnExists = true;
        $sessionProgramColumnName = 'program';
    } else {
        $colProgramName = @$conn->query("SHOW COLUMNS FROM academic_session LIKE 'program_name'");
        if ($colProgramName && $colProgramName->num_rows > 0) {
            $sessionProgramColumnExists = true;
            $sessionProgramColumnName = 'program_name';
        }
    }
}

if (isset($_GET['sessions_api']) && $_GET['sessions_api'] === '1') {
    header('Content-Type: application/json');
    $method = $_SERVER['REQUEST_METHOD'];
    $rawInput = json_decode(file_get_contents('php://input'), true);
    $input = is_array($rawInput) ? $rawInput : $_POST;
    if (!isset($_SESSION['session_program_map']) || !is_array($_SESSION['session_program_map'])) {
        $_SESSION['session_program_map'] = [];
    }

    if ($method === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $programSql = $sessionProgramColumnExists ? ("s.`" . $sessionProgramColumnName . "`") : "NULL";
            $sql = "SELECT s.*, $programSql AS program_value FROM academic_session s WHERE s.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && !$sessionProgramColumnExists) {
                $row['program_value'] = $_SESSION['session_program_map'][(string)$id] ?? null;
            }
            echo json_encode($row ?: ['success' => false]);
            exit;
        }

        $paged = isset($_GET['paged']) && $_GET['paged'] === '1';
        $q = trim((string)($_GET['q'] ?? ''));
        $idsRaw = trim((string)($_GET['ids'] ?? ''));
        $ids = [];
        if ($idsRaw !== '') {
            foreach (explode(',', $idsRaw) as $part) {
                $v = (int)trim($part);
                if ($v > 0) $ids[] = $v;
            }
            $ids = array_values(array_unique($ids));
        }
        $where = '';
        $params = [];
        $types = '';
        if ($q !== '') {
            $where = 'WHERE LOWER(s.name) LIKE ?';
            $params[] = '%' . mb_strtolower($q, 'UTF-8') . '%';
            $types .= 's';
        }
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $where .= ($where === '' ? 'WHERE ' : ' AND ') . "s.id IN ($ph)";
            foreach ($ids as $sid) {
                $params[] = $sid;
                $types .= 'i';
            }
        }
        $programSql = $sessionProgramColumnExists ? ("s.`" . $sessionProgramColumnName . "`") : "NULL";
        if ($paged) {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = (int)($_GET['page_size'] ?? 25);
            if (!in_array($pageSize, [25, 50, 75, 100], true)) $pageSize = 25;
            $offset = ($page - 1) * $pageSize;

            $countSql = "SELECT COUNT(*) AS cnt FROM academic_session s $where";
            $stmt = $conn->prepare($countSql);
            if ($types !== '') $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $total = (int)(($stmt->get_result()->fetch_assoc()['cnt'] ?? 0));

            $sql = "SELECT s.*, $programSql AS program_value FROM academic_session s $where ORDER BY s.start_date ASC LIMIT ? OFFSET ?";
            $paramsWithLimit = $params;
            $typesWithLimit = $types . 'ii';
            $paramsWithLimit[] = $pageSize;
            $paramsWithLimit[] = $offset;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) {
                if (!$sessionProgramColumnExists) {
                    $sid = (string)($r['id'] ?? '');
                    $r['program_value'] = ($sid !== '') ? ($_SESSION['session_program_map'][$sid] ?? null) : null;
                }
                $rows[] = $r;
            }
            echo json_encode(['success' => true, 'items' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            exit;
        }

        $sql = "SELECT s.*, $programSql AS program_value FROM academic_session s $where ORDER BY s.start_date ASC";
        $stmt = $conn->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            if (!$sessionProgramColumnExists) {
                $sid = (string)($r['id'] ?? '');
                $r['program_value'] = ($sid !== '') ? ($_SESSION['session_program_map'][$sid] ?? null) : null;
            }
            $rows[] = $r;
        }
        echo json_encode($rows);
        exit;
    }

    if ($method === 'POST' || $method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        $program = trim((string)($input['program'] ?? $input['program_name'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $startDate = trim((string)($input['start_date'] ?? ''));
        $endDate = trim((string)($input['end_date'] ?? ''));
        if ($name === '' || $startDate === '' || $endDate === '') {
            echo json_encode(['success' => false, 'message' => 'Please enter session name, start date and end date.']);
            exit;
        }

        if ($method === 'POST') {
            if ($sessionProgramColumnExists) {
                $sql = "INSERT INTO academic_session (`$sessionProgramColumnName`, name, start_date, end_date) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssss', $program, $name, $startDate, $endDate);
            } else {
                $stmt = $conn->prepare('INSERT INTO academic_session (name, start_date, end_date) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $name, $startDate, $endDate);
            }
            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'message' => 'Could not create session.']);
                exit;
            }
            $newId = (int)$conn->insert_id;
            if (!$sessionProgramColumnExists && $program !== '' && $newId > 0) {
                $_SESSION['session_program_map'][(string)$newId] = $program;
            }
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Session created.']);
            exit;
        }

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid session.']);
            exit;
        }
        if ($sessionProgramColumnExists) {
            $sql = "UPDATE academic_session SET `$sessionProgramColumnName` = ?, name = ?, start_date = ?, end_date = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssi', $program, $name, $startDate, $endDate, $id);
        } else {
            $stmt = $conn->prepare('UPDATE academic_session SET name = ?, start_date = ?, end_date = ? WHERE id = ?');
            $stmt->bind_param('sssi', $name, $startDate, $endDate, $id);
        }
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Could not update session.']);
            exit;
        }
        if (!$sessionProgramColumnExists && $program !== '' && $id > 0) {
            $_SESSION['session_program_map'][(string)$id] = $program;
        }
        echo json_encode(['success' => true, 'message' => 'Session updated.']);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid session.']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM academic_session WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Could not delete session.']);
            exit;
        }
        if (!$sessionProgramColumnExists && isset($_SESSION['session_program_map'][(string)$id])) {
            unset($_SESSION['session_program_map'][(string)$id]);
        }
        echo json_encode(['success' => true, 'message' => 'Session deleted.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$pageTitle = 'Academic Sessions';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Academic Session Management</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#sessionModal" onclick="sessionFormReset()">Add Session</button>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="dropdown">
                    <label class="small text-muted d-block mb-1">Sessions Filter</label>
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="sessionFilterBtn" style="min-width:320px;">All sessions</button>
                    <div class="dropdown-menu p-2" style="min-width:360px;">
                        <input type="text" id="sessionFilterSearch" class="form-control form-control-sm mb-2" placeholder="Search sessions">
                        <div id="sessionFilterList" style="max-height:260px;overflow:auto;"></div>
                        <div class="d-flex justify-content-between mt-2">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="sessionFilterSelectAll">Select all</button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="sessionFilterClear">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-management" id="sessionTable">
                    <thead><tr><th>Program</th><th>Name</th><th>Start</th><th>End</th><th>Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2" id="sessionPager">
                <div>
                    <label class="small text-muted me-1">Show</label>
                    <select id="sessionPageSize" class="form-select form-select-sm d-inline-block" style="width:auto">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="75">75</option>
                        <option value="100">100</option>
                    </select>
                    <span class="small text-muted ms-1">rows</span>
                </div>
                <div class="small text-muted" id="sessionPageInfo"></div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="sessionPrev">Previous</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="sessionNext">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="sessionModalTitle">Add Session</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="sessionId">
                <div class="mb-2">
                    <label class="form-label">Program</label>
                    <select id="sessionProgram" class="form-select" required>
                        <option value="">-- Select Program --</option>
                        <?php foreach ($sessionProgramOptions as $programLabel): ?>
                            <option value="<?= htmlspecialchars($programLabel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($programLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label">Name</label><input type="text" id="sessionName" class="form-control" maxlength="64" required><div class="field-error text-danger small" style="display:none"></div></div>
                <div class="mb-2"><label class="form-label">Start Date</label><input type="date" id="sessionStart" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">End Date</label><input type="date" id="sessionEnd" class="form-control" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sessionSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
if (typeof base === 'undefined' || !base) base = '../';
var sessionProgramColumnExists = <?php echo $sessionProgramColumnExists ? 'true' : 'false'; ?>;
var sessionApiUrl = 'sessions.php?sessions_api=1';
var sessionPage = 1;
var sessionPageSize = 25;
var sessionTotal = 0;
var selectedSessionIds = [];
var sessionFilterItems = [];
var sessionFilterTimer = null;
function updateSessionFilterLabel() {
    if (!selectedSessionIds.length) return $('#sessionFilterBtn').text('All sessions');
    if (selectedSessionIds.length === 1) {
        var hit = sessionFilterItems.find(function(x){ return String(x.id) === String(selectedSessionIds[0]); });
        return $('#sessionFilterBtn').text(hit ? (hit.name || '1 selected') : '1 selected');
    }
    $('#sessionFilterBtn').text(selectedSessionIds.length + ' sessions selected');
}
function renderSessionFilterList() {
    var $list = $('#sessionFilterList').empty();
    if (!sessionFilterItems.length) { $list.html('<div class="text-muted small">No sessions found.</div>'); return; }
    sessionFilterItems.forEach(function(s){
        var id = String(s.id), checked = selectedSessionIds.indexOf(id) !== -1;
        var $row = $('<label class="form-check d-flex align-items-start mb-1"><input class="form-check-input me-2 mt-1 session-filter-item" type="checkbox"><span class="small"></span></label>');
        $row.find('input').val(id).prop('checked', checked);
        $row.find('span').text(s.name || ('Session #' + id));
        $list.append($row);
    });
}
function loadSessionFilterOptions() {
    var q = ($('#sessionFilterSearch').val() || '').trim();
    $.get(sessionApiUrl, { q: q }).done(function(list){
        sessionFilterItems = Array.isArray(list) ? list : [];
        renderSessionFilterList();
        updateSessionFilterLabel();
    });
}
function refreshSessionPager() {
    var $info = $('#sessionPageInfo');
    if (!sessionTotal) {
        $info.text('No records found (Rows: 0 / 0)');
        $('#sessionPrev').prop('disabled', true);
        $('#sessionNext').prop('disabled', true);
        return;
    }
    var start = (sessionPage - 1) * sessionPageSize + 1;
    var end = Math.min(sessionTotal, sessionPage * sessionPageSize);
    var totalPages = Math.max(1, Math.ceil(sessionTotal / sessionPageSize));
    var shown = end - start + 1;
    $info.text('Showing ' + start + '–' + end + ' of ' + sessionTotal + ' (Page ' + sessionPage + ' of ' + totalPages + ', Rows: ' + shown + ' / ' + sessionTotal + ')');
    $('#sessionPrev').prop('disabled', sessionPage <= 1);
    $('#sessionNext').prop('disabled', sessionPage >= totalPages);
}
function loadSessions() {
    $.get(sessionApiUrl, {
        paged: 1,
        page: sessionPage,
        page_size: sessionPageSize,
        ids: selectedSessionIds.join(',')
    }).done(function(data) {
        var $tb = $('#sessionTable tbody').empty();
        if (!data || data.success === false) {
            var msg = (data && data.message) || 'Could not load sessions.';
            $tb.append($('<tr><td colspan="5" class="text-danger"></td></tr>').find('td').text(msg).end());
            sessionTotal = 0;
            refreshSessionPager();
            return;
        }
        var list = Array.isArray(data.items) ? data.items : [];
        sessionTotal = data.total || 0;
        if (list.length === 0) {
            $tb.append($('<tr><td colspan="5" class="text-muted">No records found.</td></tr>'));
        } else {
            list.forEach(function(s) {
                var programText = (s.program_value || s.program_name || s.program || 'N/A');
                $tb.append($('<tr>').append(
                    $('<td>').text(programText),
                    $('<td>').text(s.name),
                    $('<td>').text(s.start_date),
                    $('<td>').text(s.end_date),
                    $('<td>').html('<button class="btn btn-sm btn-warning me-1 edit-session" data-id="'+s.id+'">Edit</button><button class="btn btn-sm btn-danger delete-session" data-id="'+s.id+'">Delete</button>')
                ));
            });
        }
        refreshSessionPager();
    }).fail(function(xhr) {
        $('#sessionTable tbody').html('<tr><td colspan="5" class="text-danger">Failed to load sessions. Check console. Log in as admin if needed.</td></tr>');
        showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load sessions.');
        sessionTotal = 0;
        refreshSessionPager();
    });
}
$('#sessionPageSize').on('change', function() {
    sessionPageSize = parseInt($(this).val(), 10) || 25;
    sessionPage = 1;
    loadSessions();
});
$('#sessionPrev').on('click', function() {
    if (sessionPage > 1) {
        sessionPage--;
        loadSessions();
    }
});
$('#sessionNext').on('click', function() {
    sessionPage++;
    loadSessions();
});
$('#sessionFilterSearch').on('input', function() {
    if (sessionFilterTimer) clearTimeout(sessionFilterTimer);
    sessionFilterTimer = setTimeout(loadSessionFilterOptions, 250);
});
$(document).on('change', '.session-filter-item', function() {
    var v = String($(this).val());
    if (this.checked) { if (selectedSessionIds.indexOf(v) === -1) selectedSessionIds.push(v); }
    else { selectedSessionIds = selectedSessionIds.filter(function(x){ return x !== v; }); }
    updateSessionFilterLabel();
    sessionPage = 1; loadSessions();
});
$('#sessionFilterSelectAll').on('click', function() {
    selectedSessionIds = sessionFilterItems.map(function(s){ return String(s.id); });
    renderSessionFilterList(); updateSessionFilterLabel(); sessionPage = 1; loadSessions();
});
$('#sessionFilterClear').on('click', function() {
    selectedSessionIds = [];
    renderSessionFilterList(); updateSessionFilterLabel(); sessionPage = 1; loadSessions();
});
function sessionFormReset() {
    $('#sessionModalTitle').text('Add Session');
    $('#sessionId').val(''); $('#sessionProgram').val(''); $('#sessionName').val(''); $('#sessionStart').val(''); $('#sessionEnd').val('');
}
$('#sessionSaveBtn').on('click', function() {
    var id = $('#sessionId').val();
    var program = ($('#sessionProgram').val() || '').trim();
    var name = ($('#sessionName').val() || '').trim();
    Validation.clearFieldErrors('#sessionModal');
    if ((!id && !program) || !name || !$('#sessionStart').val() || !$('#sessionEnd').val()) { showToast('error', 'Please enter program, session name, start date and end date.'); return; }
    var err = Validation.validateMaxLength(name, Validation.LIMITS.sessionName, 'Session name');
    if (err) { Validation.showFieldError('#sessionName', err); showToast('error', err); return; }
    var data = { program: program, name: name, start_date: $('#sessionStart').val(), end_date: $('#sessionEnd').val() };
    if (sessionProgramColumnExists) {
        data.program_name = program;
    }
    var url = sessionApiUrl;
    if (id) { data.id = id; $.ajax({ url: url, method: 'PUT', contentType: 'application/json', data: JSON.stringify(data) }).done(function(r) { if (r.success) { $('#sessionModal').modal('hide'); loadSessions(); showToast('success', r.message || 'Saved.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); }); }
    else { $.post(url, data).done(function(r) { if (r.success) { $('#sessionModal').modal('hide'); loadSessions(); showToast('success', r.message || 'Session created.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); }); }
});
$(document).on('click', '.edit-session', function() {
    var id = $(this).data('id');
    $.get(sessionApiUrl, { id: id }).done(function(s) {
        if (!s || !s.id) return;
        $('#sessionModalTitle').text('Edit Session');
        $('#sessionId').val(s.id); $('#sessionProgram').val((s.program_value || s.program_name || s.program || '')); $('#sessionName').val(s.name); $('#sessionStart').val(s.start_date); $('#sessionEnd').val(s.end_date);
        $('#sessionModal').modal('show');
    });
});
$(document).on('click', '.delete-session', function() {
    if (!confirm('Delete this session?')) return;
    $.ajax({ url: sessionApiUrl, method: 'DELETE', contentType: 'application/json', data: JSON.stringify({ id: $(this).data('id') }) }).done(function(r) { if (r.success) { if (sessionPage > 1 && (sessionPage - 1) * sessionPageSize >= (sessionTotal - 1)) { sessionPage--; } loadSessions(); showToast('success', r.message || 'Session deleted.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});
$(function() { loadSessionFilterOptions(); loadSessions(); });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
