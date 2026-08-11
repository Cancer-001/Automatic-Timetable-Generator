<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Departments';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Department Management</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="deptFormReset()">Add Department</button>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="dropdown">
                    <label class="small text-muted d-block mb-1">Departments Filter</label>
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="deptFilterBtn" style="min-width:320px;">All departments</button>
                    <div class="dropdown-menu p-2" style="min-width:360px;">
                        <input type="text" id="deptFilterSearch" class="form-control form-control-sm mb-2" placeholder="Search departments">
                        <div id="deptFilterList" style="max-height:260px;overflow:auto;"></div>
                        <div class="d-flex justify-content-between mt-2">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="deptFilterSelectAll">Select all</button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="deptFilterClear">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
            <table class="table table-striped" id="deptTable">
                <thead><tr><th>Code</th><th>Name</th><th>Actions</th></tr></thead>
                <tbody></tbody>
            </table>
            <div class="d-flex justify-content-between align-items-center mt-2" id="deptPager">
                <div>
                    <label class="small text-muted me-1">Show</label>
                    <select id="deptPageSize" class="form-select form-select-sm d-inline-block" style="width:auto">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="75">75</option>
                        <option value="100">100</option>
                    </select>
                    <span class="small text-muted ms-1">rows</span>
                </div>
                <div class="small text-muted" id="deptPageInfo"></div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="deptPrev">Previous</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deptNext">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="deptModalTitle">Add Department</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="deptId">
                <div class="mb-2"><label class="form-label">Code</label><input type="text" id="deptCode" class="form-control" maxlength="200" required><div class="field-error text-danger small" style="display:none"></div></div>
                <div class="mb-2"><label class="form-label">Name</label><input type="text" id="deptName" class="form-control" maxlength="200" required><div class="field-error text-danger small" style="display:none"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="deptSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
var deptPage = 1;
var deptPageSize = 25;
var deptTotal = 0;
var selectedDeptIds = [];
var deptFilterItems = [];
var deptFilterTimer = null;
function updateDeptFilterLabel() {
    if (!selectedDeptIds.length) return $('#deptFilterBtn').text('All departments');
    if (selectedDeptIds.length === 1) {
        var hit = deptFilterItems.find(function(x){ return String(x.id) === String(selectedDeptIds[0]); });
        return $('#deptFilterBtn').text(hit ? ((hit.code || '') + ' - ' + (hit.name || '')) : '1 selected');
    }
    $('#deptFilterBtn').text(selectedDeptIds.length + ' departments selected');
}
function renderDeptFilterList() {
    var $list = $('#deptFilterList').empty();
    if (!deptFilterItems.length) { $list.html('<div class="text-muted small">No departments found.</div>'); return; }
    deptFilterItems.forEach(function(d){
        var id = String(d.id), checked = selectedDeptIds.indexOf(id) !== -1;
        var $row = $('<label class="form-check d-flex align-items-start mb-1"><input class="form-check-input me-2 mt-1 dept-filter-item" type="checkbox"><span class="small"></span></label>');
        $row.find('input').val(id).prop('checked', checked);
        $row.find('span').text((d.code || '') + ' - ' + (d.name || ''));
        $list.append($row);
    });
}
function loadDeptFilterOptions() {
    var q = ($('#deptFilterSearch').val() || '').trim();
    $.get(base + 'actions/departments.php', { q: q }).done(function(list){
        deptFilterItems = Array.isArray(list) ? list : [];
        renderDeptFilterList();
        updateDeptFilterLabel();
    });
}
function refreshDeptPager() {
    var $info = $('#deptPageInfo');
    if (!deptTotal) {
        $info.text('No records found (Rows: 0 / 0)');
        $('#deptPrev').prop('disabled', true);
        $('#deptNext').prop('disabled', true);
        return;
    }
    var start = (deptPage - 1) * deptPageSize + 1;
    var end = Math.min(deptTotal, deptPage * deptPageSize);
    var totalPages = Math.max(1, Math.ceil(deptTotal / deptPageSize));
    var shown = end - start + 1;
    $info.text('Showing ' + start + '–' + end + ' of ' + deptTotal + ' (Page ' + deptPage + ' of ' + totalPages + ', Rows: ' + shown + ' / ' + deptTotal + ')');
    $('#deptPrev').prop('disabled', deptPage <= 1);
    $('#deptNext').prop('disabled', deptPage >= totalPages);
}
function loadDepts() {
    $.get(base + 'actions/departments.php', {
        paged: 1,
        page: deptPage,
        page_size: deptPageSize,
        ids: selectedDeptIds.join(',')
    }).done(function(resp) {
        var $tb = $('#deptTable tbody').empty();
        if (!resp || resp.success === false) {
            var msg = (resp && resp.message) || 'Could not load departments.';
            $tb.append($('<tr><td colspan="3" class="text-danger"></td></tr>').find('td').text(msg).end());
            deptTotal = 0;
            refreshDeptPager();
            return;
        }
        var list = resp.items || [];
        deptTotal = resp.total || 0;
        if (!list.length) {
            $tb.append($('<tr><td colspan="3" class="text-muted">No records found.</td></tr>'));
        } else {
            list.forEach(function(d) {
                $tb.append($('<tr>').append(
                    $('<td>').text(d.code),
                    $('<td>').text(d.name),
                    $('<td>').html('<button class="btn btn-sm btn-warning me-1 edit-dept" data-id="'+d.id+'">Edit</button><button class="btn btn-sm btn-danger delete-dept" data-id="'+d.id+'">Delete</button>')
                ));
            });
        }
        refreshDeptPager();
    }).fail(function() {
        var $tb = $('#deptTable tbody').empty();
        $tb.append($('<tr><td colspan="3" class="text-danger">Failed to load departments.</td></tr>'));
        deptTotal = 0;
        refreshDeptPager();
    });
}
$('#deptFilterSearch').on('input', function() {
    if (deptFilterTimer) clearTimeout(deptFilterTimer);
    deptFilterTimer = setTimeout(loadDeptFilterOptions, 250);
});
$(document).on('change', '.dept-filter-item', function() {
    var v = String($(this).val());
    if (this.checked) { if (selectedDeptIds.indexOf(v) === -1) selectedDeptIds.push(v); }
    else { selectedDeptIds = selectedDeptIds.filter(function(x){ return x !== v; }); }
    updateDeptFilterLabel();
    deptPage = 1; loadDepts();
});
$('#deptFilterSelectAll').on('click', function() {
    selectedDeptIds = deptFilterItems.map(function(d){ return String(d.id); });
    renderDeptFilterList(); updateDeptFilterLabel(); deptPage = 1; loadDepts();
});
$('#deptFilterClear').on('click', function() {
    selectedDeptIds = [];
    renderDeptFilterList(); updateDeptFilterLabel(); deptPage = 1; loadDepts();
});
$('#deptPageSize').on('change', function() {
    deptPageSize = parseInt($(this).val(), 10) || 25;
    deptPage = 1;
    loadDepts();
});
$('#deptPrev').on('click', function() {
    if (deptPage > 1) {
        deptPage--;
        loadDepts();
    }
});
$('#deptNext').on('click', function() {
    deptPage++;
    loadDepts();
});
function deptFormReset() {
    $('#deptModalTitle').text('Add Department');
    $('#deptId').val(''); $('#deptCode').val(''); $('#deptName').val('');
}
$('#deptSaveBtn').on('click', function() {
    var $btn = $(this);
    var id = $('#deptId').val();
    var code = $('#deptCode').val().trim(), name = $('#deptName').val().trim();
    Validation.clearFieldErrors('#deptModal');
    if (!name || !code) { showToast('error', 'Please enter both department name and code.'); return; }
    var err = Validation.validateMaxLength(name, Validation.LIMITS.departmentName, 'Department name');
    if (err) { Validation.showFieldError('#deptName', err); showToast('error', err); return; }
    err = Validation.validateMaxLength(code, Validation.LIMITS.departmentCode, 'Department code');
    if (err) { Validation.showFieldError('#deptCode', err); showToast('error', err); return; }
    var data = { code: code, name: name };
    var url = base + 'actions/departments.php';
    $btn.prop('disabled', true).text('Saving...');
    function done() { $btn.prop('disabled', false).text('Save'); }
    if (id) {
        data.id = id;
        $.ajax({ url: url, method: 'PUT', contentType: 'application/json', data: JSON.stringify(data) })
            .done(function(r) { if (r.success) { $('#deptModal').modal('hide'); loadDepts(); showToast('success', r.message || 'Saved.'); } else showToast('error', r.message || 'Error'); })
            .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : (x.statusText || 'Request failed.')); })
            .always(done);
    } else {
        $.post(url, data)
            .done(function(r) { if (r.success) { $('#deptModal').modal('hide'); loadDepts(); showToast('success', r.message || 'Department created.'); } else showToast('error', r.message || 'Error'); })
            .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : (x.statusText || 'Request failed.')); })
            .always(done);
    }
});
$(document).on('click', '.edit-dept', function() {
    var id = $(this).data('id');
    $.get(base + 'actions/departments.php', { id: id }).done(function(d) {
        if (!d) return;
        $('#deptModalTitle').text('Edit Department');
        $('#deptId').val(d.id); $('#deptCode').val(d.code); $('#deptName').val(d.name);
        $('#deptModal').modal('show');
    });
});
$(document).on('click', '.delete-dept', function() {
    if (!confirm('Delete this department?')) return;
    $.ajax({ url: base + 'actions/departments.php', method: 'DELETE', contentType: 'application/json', data: JSON.stringify({ id: $(this).data('id') }) })
        .done(function(r) { if (r.success) { if (deptPage > 1 && (deptPage - 1) * deptPageSize >= (deptTotal - 1)) { deptPage--; } loadDepts(); showToast('success', r.message || 'Department deleted.'); } else showToast('error', r.message || 'Could not delete.'); })
        .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});
$(function() { loadDeptFilterOptions(); loadDepts(); });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
