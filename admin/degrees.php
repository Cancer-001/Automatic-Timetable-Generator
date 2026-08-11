<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Program';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Program Management</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#degreeModal" onclick="degreeFormReset()">Add Degree</button>
    <div class="card">
        <div class="card-body">
            <table class="table table-striped" id="degreeTable">
                <thead><tr><th>Code</th><th>Name</th><th>Actions</th></tr></thead>
                <tbody></tbody>
            </table>
            <div class="d-flex justify-content-between align-items-center mt-2" id="degreePager">
                <div>
                    <label class="small text-muted me-1">Show</label>
                    <select id="degreePageSize" class="form-select form-select-sm d-inline-block" style="width:auto">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="75">75</option>
                        <option value="100">100</option>
                    </select>
                    <span class="small text-muted ms-1">rows</span>
                </div>
                <div class="small text-muted" id="degreePageInfo"></div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="degreePrev">Previous</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="degreeNext">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="degreeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="degreeModalTitle">Add Degree</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="degreeId">
                <div class="mb-2"><label class="form-label">Code</label><input type="text" id="degreeCode" class="form-control" maxlength="32" required><div class="field-error text-danger small" style="display:none"></div></div>
                <div class="mb-2"><label class="form-label">Name</label><input type="text" id="degreeName" class="form-control" maxlength="128" required><div class="field-error text-danger small" style="display:none"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="degreeSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
var degreePage = 1;
var degreePageSize = 25;
var degreeTotal = 0;

function refreshDegreePager() {
    var $info = $('#degreePageInfo');
    if (!degreeTotal) {
        $info.text('No records found (Rows: 0 / 0)');
        $('#degreePrev').prop('disabled', true);
        $('#degreeNext').prop('disabled', true);
        return;
    }
    var start = (degreePage - 1) * degreePageSize + 1;
    var end = Math.min(degreeTotal, degreePage * degreePageSize);
    var totalPages = Math.max(1, Math.ceil(degreeTotal / degreePageSize));
    var shown = end - start + 1;
    $info.text('Showing ' + start + '–' + end + ' of ' + degreeTotal + ' (Page ' + degreePage + ' of ' + totalPages + ', Rows: ' + shown + ' / ' + degreeTotal + ')');
    $('#degreePrev').prop('disabled', degreePage <= 1);
    $('#degreeNext').prop('disabled', degreePage >= totalPages);
}

function loadDegrees() {
    $.get(base + 'actions/degrees.php', {
        paged: 1,
        page: degreePage,
        page_size: degreePageSize
    }).done(function(resp) {
        var $tb = $('#degreeTable tbody').empty();
        if (!resp || resp.success === false) {
            $tb.append($('<tr><td colspan="3" class="text-danger"></td></tr>').find('td').text((resp && resp.message) || 'Could not load degrees.').end());
            degreeTotal = 0;
            refreshDegreePager();
            return;
        }
        var list = resp.items || [];
        degreeTotal = resp.total || 0;
        if (!list.length) {
            $tb.append($('<tr><td colspan="3" class="text-muted">No records found.</td></tr>'));
        } else {
            list.forEach(function(d) {
                $tb.append($('<tr>').append(
                    $('<td>').text(d.code),
                    $('<td>').text(d.name),
                    $('<td>').html('<button class="btn btn-sm btn-warning me-1 edit-degree" data-id="'+d.id+'">Edit</button><button class="btn btn-sm btn-danger delete-degree" data-id="'+d.id+'">Delete</button>')
                ));
            });
        }
        refreshDegreePager();
    }).fail(function() {
        $('#degreeTable tbody').html('<tr><td colspan="3" class="text-danger">Failed to load degrees.</td></tr>');
        degreeTotal = 0;
        refreshDegreePager();
    });
}

$('#degreePageSize').on('change', function() {
    degreePageSize = parseInt($(this).val(), 10) || 25;
    degreePage = 1;
    loadDegrees();
});
$('#degreePrev').on('click', function() { if (degreePage > 1) { degreePage--; loadDegrees(); } });
$('#degreeNext').on('click', function() { degreePage++; loadDegrees(); });

function degreeFormReset() {
    $('#degreeModalTitle').text('Add Degree');
    $('#degreeId').val('');
    $('#degreeCode').val('');
    $('#degreeName').val('');
}

$('#degreeSaveBtn').on('click', function() {
    var $btn = $(this);
    var id = $('#degreeId').val();
    var code = ($('#degreeCode').val() || '').trim().toUpperCase();
    var name = ($('#degreeName').val() || '').trim();
    Validation.clearFieldErrors('#degreeModal');
    if (!code || !name) { showToast('error', 'Please enter both degree code and degree name.'); return; }
    var err = Validation.validateMaxLength(code, 32, 'Degree code');
    if (err) { Validation.showFieldError('#degreeCode', err); showToast('error', err); return; }
    err = Validation.validateMaxLength(name, 128, 'Degree name');
    if (err) { Validation.showFieldError('#degreeName', err); showToast('error', err); return; }

    var data = { code: code, name: name };
    var url = base + 'actions/degrees.php';
    $btn.prop('disabled', true).text('Saving...');
    function done() { $btn.prop('disabled', false).text('Save'); }

    if (id) {
        data.id = id;
        $.ajax({ url: url, method: 'PUT', contentType: 'application/json', data: JSON.stringify(data) })
            .done(function(r) { if (r.success) { $('#degreeModal').modal('hide'); loadDegrees(); showToast('success', r.message || 'Degree updated.'); } else showToast('error', r.message || 'Error'); })
            .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); })
            .always(done);
    } else {
        $.post(url, data)
            .done(function(r) { if (r.success) { $('#degreeModal').modal('hide'); loadDegrees(); showToast('success', r.message || 'Degree created.'); } else showToast('error', r.message || 'Error'); })
            .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); })
            .always(done);
    }
});

$(document).on('click', '.edit-degree', function() {
    var id = $(this).data('id');
    $.get(base + 'actions/degrees.php', { id: id }).done(function(d) {
        if (!d || !d.id) return;
        $('#degreeModalTitle').text('Edit Degree');
        $('#degreeId').val(d.id);
        $('#degreeCode').val(d.code);
        $('#degreeName').val(d.name);
        $('#degreeModal').modal('show');
    });
});

$(document).on('click', '.delete-degree', function() {
    if (!confirm('Delete this degree?')) return;
    $.ajax({ url: base + 'actions/degrees.php', method: 'DELETE', contentType: 'application/json', data: JSON.stringify({ id: $(this).data('id') }) })
        .done(function(r) {
            if (r.success) {
                if (degreePage > 1 && (degreePage - 1) * degreePageSize >= (degreeTotal - 1)) degreePage--;
                loadDegrees();
                showToast('success', r.message || 'Degree deleted.');
            } else showToast('error', r.message || 'Could not delete.');
        })
        .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});

$(function() { loadDegrees(); });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
