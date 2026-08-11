<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('faculty');
$pageTitle = 'Substitution Request';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Request Substitution</h2>
    <div class="card mb-4">
        <div class="card-body">
            <form id="subForm">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">Session</label>
                        <select id="subSession" class="form-select"><option value="">-- Session --</option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">My scheduled class</label>
                        <select id="subSchedule" class="form-select" required><option value="">-- Select class --</option></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date for substitution</label>
                        <input type="date" id="subDate" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Reason</label>
                        <input type="text" id="subReason" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </div>
            </form>
            <div id="subResult" class="mt-2"></div>
        </div>
    </div>
    <h5>My requests</h5>
    <div class="card">
        <div class="card-body">
            <table class="table" id="subTable">
                <thead><tr><th>Course</th><th>Slot</th><th>Date</th><th>Reason</th><th>Status</th><th>Admin notes</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<script>
var base = '../';
function loadMySchedules() {
    $.get(base + 'actions/sessions.php').done(function(sessions) {
        var $sess = $('#subSession');
        $sess.find('option:not(:first)').remove();
        var arr = Array.isArray(sessions) ? sessions : [];
        arr.forEach(function(s) { $sess.append($('<option>').val(s.id).text(s.name)); });
    });
}
function loadScheduleForSession() {
    var sid = $('#subSession').val();
    var $sel = $('#subSchedule').find('option:first').nextAll().remove().end();
    if (!sid) return;
    $.get(base + 'actions/schedule.php?academic_session_id=' + encodeURIComponent(sid) + '&strict_faculty=1').done(function(data) {
        var list = data.list || data;
        list.forEach(function(r) {
            $sel.after($('<option>').val(r.id).text(r.course_name + ' - ' + r.slot_label + ' (Sem ' + r.semester + '/' + r.section + ')'));
        });
    });
}
function loadRequests() {
    $.get(base + 'actions/substitution.php').done(function(list) {
        var $tb = $('#subTable tbody').empty();
        (list || []).forEach(function(r) {
            var statusClass = (r.status === 'approved' ? 'success' : r.status === 'rejected' ? 'danger' : 'warning');
            var adminNotes = r.admin_notes || '';
            var adminCell = adminNotes ? $('<span>').text(adminNotes) : $('<span class="text-muted">—</span>');
            $tb.append($('<tr>').append(
                $('<td>').text(r.course_name),
                $('<td>').text(r.slot_label),
                $('<td>').text(r.requested_date),
                $('<td>').text(r.reason || '-'),
                $('<td>').html('<span class="badge bg-' + statusClass + '">' + r.status + '</span>'),
                $('<td>').append(adminCell)
            ));
        });
    });
}
$('#subForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: base + 'actions/substitution.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            schedule_id: $('#subSchedule').val(),
            requested_date: $('#subDate').val(),
            reason: $('#subReason').val()
        })
    }).done(function(r) {
        $('#subResult').html(r.success ? '<span class="text-success">Request submitted.</span>' : '<span class="text-danger">' + (r.message || 'Error') + '</span>');
        if (typeof showToast === 'function') showToast(r.success ? 'success' : 'error', r.message || (r.success ? 'Request submitted.' : 'Error'));
        if (r.success) { loadRequests(); loadScheduleForSession(); }
    }).fail(function(x) {
        var msg = (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.';
        $('#subResult').html('<span class="text-danger">' + msg + '</span>');
        if (typeof showToast === 'function') showToast('error', msg);
    });
});
$('#subSession').on('change', loadScheduleForSession);
$(function() { loadMySchedules(); loadRequests(); });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
