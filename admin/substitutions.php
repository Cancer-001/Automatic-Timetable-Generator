<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Substitution Requests';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Faculty Substitution Requests</h2>
    <div class="card">
        <div class="card-body">
            <table class="table table-striped table-hover" id="subTable">
                <thead>
                    <tr>
                        <th>Faculty</th>
                        <th>Course</th>
                        <th>Slot</th>
                        <th>Requested Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Admin Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
function loadSubstitutions() {
    $.get(base + 'actions/substitution.php')
        .done(function(list) {
            var $tb = $('#subTable tbody').empty();
            if (!list || list.length === 0) {
                $tb.append('<tr><td colspan="8" class="text-muted">No substitution requests.</td></tr>');
                return;
            }
            list.forEach(function(r) {
                var statusBadge = r.status === 'pending' ? 'bg-warning' : r.status === 'approved' ? 'bg-success' : 'bg-danger';
                var actions = '';
                if (r.status === 'pending') {
                    actions = '<button class="btn btn-sm btn-success me-1 sub-approve" data-id="' + r.id + '">Approve</button>' +
                              '<button class="btn btn-sm btn-danger sub-reject" data-id="' + r.id + '">Reject</button>';
                } else {
                    actions = '<span class="text-muted">—</span>';
                }
                $tb.append($('<tr>').append(
                    $('<td>').text(r.faculty_name || '—'),
                    $('<td>').text((r.course_code || '') + ' ' + (r.course_name || '—')),
                    $('<td>').text(r.slot_label || '—'),
                    $('<td>').text(r.requested_date || '—'),
                    $('<td>').text((r.reason || '').substring(0, 80) + ((r.reason || '').length > 80 ? '…' : '')),
                    $('<td>').html('<span class="badge ' + statusBadge + '">' + (r.status || '') + '</span>'),
                    $('<td>').text((r.admin_notes || '—').substring(0, 60) + ((r.admin_notes || '').length > 60 ? '…' : '')),
                    $('<td>').html(actions)
                ));
            });
        })
        .fail(function(x) {
            $('#subTable tbody').html('<tr><td colspan="8" class="text-danger">Failed to load requests.</td></tr>');
            showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.');
        });
}

function setStatus(id, status, adminNotes) {
    var data = { id: id, status: status };
    if (adminNotes) data.admin_notes = adminNotes;
    $.ajax({
        url: base + 'actions/substitution.php',
        method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify(data)
    })
        .done(function(r) {
            if (r.success) {
                loadSubstitutions();
                showToast('success', r.message || (status === 'approved' ? 'Request approved.' : 'Request rejected.'));
            } else {
                showToast('error', r.message || 'Update failed.');
            }
        })
        .fail(function(x) {
            showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.');
        });
}

$(document).on('click', '.sub-approve', function() {
    var id = $(this).data('id');
    var notes = window.prompt('Optional admin notes:');
    if (notes === null) return;
    setStatus(id, 'approved', notes || '');
});

$(document).on('click', '.sub-reject', function() {
    var id = $(this).data('id');
    var notes = window.prompt('Optional admin notes (e.g. reason for rejection):');
    if (notes === null) return;
    setStatus(id, 'rejected', notes || '');
});

$(function() { loadSubstitutions(); });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
