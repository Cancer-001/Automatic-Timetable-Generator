<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Students';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Student Management</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="studentFormReset()">Add Student</button>
    <div class="card">
        <div class="card-body">
            <div class="border rounded p-2 mb-3 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="small text-muted">Session</label>
                        <select id="bulkSession" class="form-select form-select-sm">
                            <option value="">All Sessions</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted">Program (Degree)</label>
                        <select id="bulkDegree" class="form-select form-select-sm">
                            <option value="">All Programs</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted">Department</label>
                        <select id="bulkDepartment" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted">Section</label>
                        <input type="text" id="bulkSection" class="form-control form-control-sm" placeholder="Any" value="" autocomplete="off">
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted">Current Semester</label>
                        <input type="number" id="bulkSemester" class="form-control form-control-sm" min="1" placeholder="Any" value="">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="applyBulkFilter">Load</button>
                        <button type="button" class="btn btn-sm btn-success w-100" id="promoteSelectedBtn">Promote Selected</button>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="dropdown">
                    <label class="small text-muted d-block mb-1">Students Filter</label>
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="studentFilterBtn" style="min-width:360px;">All students</button>
                    <div class="dropdown-menu p-2" style="min-width:420px;">
                        <input type="text" id="studentFilterSearch" class="form-control form-control-sm mb-2" placeholder="Search students">
                        <div id="studentFilterList" style="max-height:260px;overflow:auto;"></div>
                        <div class="d-flex justify-content-between mt-2">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="studentFilterSelectAll">Select all</button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="studentFilterClear">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-management" id="studentTable">
                    <thead>
                        <tr>
                            <th style="width:40px"><input type="checkbox" id="selectAllStudents"></th>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Degree</th>
                            <th>Dept</th>
                            <th>Semester</th>
                            <th>Section</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2" id="studentPager">
                <div>
                    <label class="small text-muted me-1">Show</label>
                    <select id="studentPageSize" class="form-select form-select-sm d-inline-block" style="width:auto">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="75">75</option>
                        <option value="100">100</option>
                    </select>
                    <span class="small text-muted ms-1">rows</span>
                </div>
                <div class="small text-muted" id="studentPageInfo"></div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="studentPrev">Previous</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="studentNext">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Student Modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentModalTitle">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="studentId">

                <div class="mb-2">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="studentName" class="form-control" maxlength="128" required>
                    <div class="field-error text-danger small" style="display:none"></div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Email</label>
                    <input type="email" id="studentEmail" class="form-control" maxlength="128" required>
                    <div class="field-error text-danger small" style="display:none"></div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Password <small>(leave blank to keep)</small></label>
                    <div class="input-group">
                        <input type="password" id="studentPassword" class="form-control">
                        <button class="btn btn-outline-secondary pw-toggle-btn" type="button" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Department & Degree Program <span class="text-danger">*</span></label>
                    <div class="field-error text-danger small" style="display:none"></div>
                    
                    <!-- Two-Panel Selector -->
                    <div class="dept-degree-selector d-flex gap-2" style="border: 1px solid #dee2e6; border-radius: 4px; height: 280px;">
                        <!-- Left Panel: Departments -->
                        <div class="dept-panel" style="flex: 0 0 45%; border-right: 1px solid #dee2e6; overflow-y: auto; background: #f9f9f9;">
                            <div class="list-group list-group-flush" id="departmentList" style="border-radius: 0;">
                                <!-- Departments will be populated here -->
                            </div>
                        </div>
                        
                        <!-- Right Panel: Degree Programs -->
                        <div class="degree-panel" style="flex: 0 0 55%; overflow-y: auto; padding: 8px;">
                            <div id="degreeListContainer" class="d-flex flex-wrap gap-2 align-content-start">
                                <div class="text-muted small w-100" id="selectDeptMessage">Select a department to view programs</div>
                                <!-- Degrees will be populated here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hidden inputs to store selected values -->
                    <input type="hidden" id="studentDepartmentId" value="">
                    <input type="hidden" id="studentDegree" value="">
                    
                    <!-- Display selected values -->
                    <div id="selectedValuesDisplay" class="mt-2 small text-muted" style="display: none;">
                        <strong>Selected:</strong> <span id="selectedDeptName"></span> → <span id="selectedDegreeName"></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Academic Session</label>
                        <select id="studentSession" class="form-select">
                            <option value="">-- Select Session --</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Semester</label>
                        <input type="number" id="studentSemester" class="form-control" value="1" min="1">
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Section</label>
                        <input type="text" id="studentSection" class="form-control" value="A" maxlength="32">
                        <div class="field-error text-danger small" style="display:none"></div>
                    </div>
                </div>

                <!-- Roll No display (auto-generated, shown on edit) -->
                <div class="mb-2" id="rollNoWrap" style="display:none">
                    <label class="form-label text-muted">Roll No <small>(auto-generated)</small></label>
                    <input type="text" id="studentRollNo" class="form-control bg-light" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="studentSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
function loadDepts() {
    $.get(base + 'actions/departments.php').done(function(list) {
        var $s = $('#studentDepartmentId');
        var $bulk = $('#bulkDepartment');
        $s.find('option:not(:first)').remove();
        $bulk.find('option:not(:first)').remove();
        (list || []).forEach(function(d) { $s.append($('<option>').val(d.id).text(d.name)); });
        (list || []).forEach(function(d) { $bulk.append($('<option>').val(d.id).text(d.name)); });
    });
}

function loadDegrees() {
    $.get(base + 'actions/degrees.php').done(function(list) {
        var $s = $('#studentDegree');
        var $bulk = $('#bulkDegree');
        var current = $s.val();
        $s.empty().append('<option value="">-- Select Degree --</option>');
        $bulk.find('option:not(:first)').remove();
        (list || []).forEach(function(d) {
            $s.append($('<option>').val(d.id).text((d.code || '') + ' - ' + (d.name || '')));
            $bulk.append($('<option>').val(d.id).text((d.code || '') + ' - ' + (d.name || '')));
        });
        if (current) $s.val(current);
    });
}

function loadSessions() {
    $.get(base + 'actions/sessions.php').done(function(list) {
        var $s = $('#bulkSession');
        var $studentSession = $('#studentSession');
        $s.find('option:not(:first)').remove();
        $studentSession.find('option:not(:first)').remove();
        (list || []).forEach(function(sess) {
            $s.append($('<option>').val(sess.id).text(sess.name));
            $studentSession.append($('<option>').val(sess.id).text(sess.name));
        });
    });
}

var studentPage = 1, studentPageSize = 25, studentTotal = 0;
var selectedStudentIds = [], studentFilterItems = [], studentFilterTimer = null;
function updateStudentFilterLabel() {
    if (!selectedStudentIds.length) return $('#studentFilterBtn').text('All students');
    if (selectedStudentIds.length === 1) {
        var hit = studentFilterItems.find(function(x){ return String(x.id) === String(selectedStudentIds[0]); });
        return $('#studentFilterBtn').text(hit ? (hit.full_name + (hit.roll_no ? (' (' + hit.roll_no + ')') : '')) : '1 selected');
    }
    $('#studentFilterBtn').text(selectedStudentIds.length + ' students selected');
}
function renderStudentFilterList() {
    var $list = $('#studentFilterList').empty();
    if (!studentFilterItems.length) { $list.html('<div class="text-muted small">No students found.</div>'); return; }
    studentFilterItems.forEach(function(s){
        var id = String(s.id), checked = selectedStudentIds.indexOf(id) !== -1;
        var $row = $('<label class="form-check d-flex align-items-start mb-1"><input class="form-check-input me-2 mt-1 student-filter-item" type="checkbox"><span class="small"></span></label>');
        $row.find('input').val(id).prop('checked', checked);
        $row.find('span').text((s.full_name || '') + (s.roll_no ? (' - ' + s.roll_no) : '') + (s.email ? (' (' + s.email + ')') : ''));
        $list.append($row);
    });
}
function loadStudentFilterOptions() {
    var q = ($('#studentFilterSearch').val() || '').trim();
    $.get(base + 'actions/students.php', { q: q }).done(function(list){
        studentFilterItems = Array.isArray(list) ? list : [];
        renderStudentFilterList();
        updateStudentFilterLabel();
    });
}

function refreshStudentPager() {
    if (!studentTotal) {
        $('#studentPageInfo').text('No records found');
        $('#studentPrev, #studentNext').prop('disabled', true);
        return;
    }
    var start = (studentPage - 1) * studentPageSize + 1;
    var end   = Math.min(studentTotal, studentPage * studentPageSize);
    var pages = Math.max(1, Math.ceil(studentTotal / studentPageSize));
    $('#studentPageInfo').text('Showing ' + start + '–' + end + ' of ' + studentTotal + ' (Page ' + studentPage + ' of ' + pages + ')');
    $('#studentPrev').prop('disabled', studentPage <= 1);
    $('#studentNext').prop('disabled', studentPage >= pages);
}

function loadStudents() {
    var hasStudentIdFilter = selectedStudentIds.length > 0;
    $.get(base + 'actions/students.php', {
        paged: 1,
        page: studentPage,
        page_size: studentPageSize,
        ids: selectedStudentIds.join(','),
        academic_session_id: hasStudentIdFilter ? '' : ($('#bulkSession').val() || ''),
        degree_id: hasStudentIdFilter ? '' : ($('#bulkDegree').val() || ''),
        department_id: hasStudentIdFilter ? '' : ($('#bulkDepartment').val() || ''),
        section: hasStudentIdFilter ? '' : (($('#bulkSection').val() || '').trim()),
        semester: hasStudentIdFilter ? '' : (parseInt($('#bulkSemester').val(), 10) || '')
    }).done(function(resp) {
        var $tb = $('#studentTable tbody').empty();
        if (!resp || resp.success === false) {
            $tb.append('<tr><td colspan="10" class="text-danger">' + escapeHtml((resp && resp.message) || 'Could not load students.') + '</td></tr>');
            studentTotal = 0; refreshStudentPager(); return;
        }
        var list = resp.items || [];
        studentTotal = resp.total || 0;
        if (!list.length) {
            $tb.append('<tr><td colspan="10" class="text-muted">No records found.</td></tr>');
        } else {
            list.forEach(function(s) {
                var frozen    = parseInt(s.is_frozen) === 1;
                var statusBadge = frozen
                    ? '<span class="badge bg-secondary">Frozen</span>'
                    : '<span class="badge bg-success">Active</span>';
                var freezeBtn = frozen
                    ? '<button class="btn btn-sm btn-outline-success px-2 py-0 me-1 unfreeze-student" data-id="' + s.id + '" title="Unfreeze">Unfreeze</button>'
                    : '<button class="btn btn-sm btn-outline-secondary px-2 py-0 me-1 freeze-student" data-id="' + s.id + '" title="Freeze">Freeze</button>';
                $tb.append($('<tr>').append(
                    $('<td>').html('<input type="checkbox" class="student-select" data-id="' + s.id + '">'),
                    $('<td>').text(s.roll_no || '—'),
                    $('<td>').text(s.full_name),
                    $('<td>').text(s.email),
                    $('<td>').html('<span class="badge bg-primary">' + escapeHtml(s.degree_name || s.degree || '—') + '</span>'),
                    $('<td>').text(s.department_name || '—'),
                    $('<td>').text(s.semester),
                    $('<td>').text(s.section),
                    $('<td>').html(statusBadge),
                    $('<td class="text-nowrap">').html(
                        '<button class="btn btn-sm btn-warning px-2 py-0 me-1 edit-student" data-id="' + s.id + '">Edit</button>' +
                        '<button class="btn btn-sm btn-info px-2 py-0 me-1 promote-student" data-id="' + s.id + '" data-sem="' + s.semester + '" title="Promote to next semester">+Sem</button>' +
                        freezeBtn +
                        '<button class="btn btn-sm btn-danger px-2 py-0 delete-student" data-id="' + s.id + '">Delete</button>'
                    )
                ));
            });
        }
        $('#selectAllStudents').prop('checked', false);
        refreshStudentPager();
    }).fail(function() {
        $('#studentTable tbody').html('<tr><td colspan="10" class="text-danger">Failed to load students.</td></tr>');
        studentTotal = 0; refreshStudentPager();
    });
}

$('#studentPageSize').on('change', function() { studentPageSize = parseInt($(this).val(), 10) || 25; studentPage = 1; loadStudents(); });
$('#studentPrev').on('click', function() { if (studentPage > 1) { studentPage--; loadStudents(); } });
$('#studentNext').on('click', function() { studentPage++; loadStudents(); });
$('#studentFilterSearch').on('input', function() {
    if (studentFilterTimer) clearTimeout(studentFilterTimer);
    studentFilterTimer = setTimeout(loadStudentFilterOptions, 250);
});
$(document).on('change', '.student-filter-item', function() {
    var v = String($(this).val());
    if (this.checked) { if (selectedStudentIds.indexOf(v) === -1) selectedStudentIds.push(v); }
    else { selectedStudentIds = selectedStudentIds.filter(function(x){ return x !== v; }); }
    updateStudentFilterLabel();
    studentPage = 1; loadStudents();
});
$('#studentFilterSelectAll').on('click', function() {
    selectedStudentIds = studentFilterItems.map(function(s){ return String(s.id); });
    renderStudentFilterList(); updateStudentFilterLabel(); studentPage = 1; loadStudents();
});
$('#studentFilterClear').on('click', function() {
    selectedStudentIds = [];
    renderStudentFilterList(); updateStudentFilterLabel(); studentPage = 1; loadStudents();
});
$('#applyBulkFilter').on('click', function() {
    studentPage = 1;
    loadStudents();
});
$('#selectAllStudents').on('change', function() {
    $('.student-select').prop('checked', $(this).is(':checked'));
});
$(document).on('change', '.student-select', function() {
    var all = $('.student-select').length && $('.student-select:checked').length === $('.student-select').length;
    $('#selectAllStudents').prop('checked', !!all);
});
$('#promoteSelectedBtn').on('click', function() {
    var ids = $('.student-select:checked').map(function() { return parseInt($(this).data('id'), 10); }).get();
    if (!ids.length) { showToast('warning', 'Select at least one student to promote.'); return; }
    var payload = {
        action: 'promote_bulk',
        student_ids: ids,
        academic_session_id: $('#bulkSession').val() || null,
        degree_id: $('#bulkDegree').val() || null,
        department_id: $('#bulkDepartment').val() || null,
        section: ($('#bulkSection').val() || '').trim(),
        semester: parseInt($('#bulkSemester').val(), 10) || null
    };
    if (!confirm('Promote ' + ids.length + ' selected students to next semester?')) return;

    // #region agent log
    fetch('http://127.0.0.1:7754/ingest/91cba0a2-0439-4759-95d0-b5739de6397c', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': 'ab35e3' }, body: JSON.stringify({ sessionId: 'ab35e3', hypothesisId: 'H3', location: 'admin/students.php:promoteSelectedBtn', message: 'bulk promote click', data: { idsLen: ids.length, idsSample: ids.slice(0, 5), payloadKeys: Object.keys(payload) }, timestamp: Date.now() }) }).catch(function () {});
    // #endregion

    $.ajax({
        url: base + 'actions/students.php',
        method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify(payload)
    }).done(function(r) {
        // #region agent log
        fetch('http://127.0.0.1:7754/ingest/91cba0a2-0439-4759-95d0-b5739de6397c', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': 'ab35e3' }, body: JSON.stringify({ sessionId: 'ab35e3', hypothesisId: 'H4', location: 'admin/students.php:promoteAjax:done', message: 'bulk promote response', data: { success: !!(r && r.success), message: (r && r.message) ? String(r.message).slice(0, 120) : '', updated_count: r && r.updated_count }, timestamp: Date.now() }) }).catch(function () {});
        // #endregion
        if (r.success) {
            showToast('success', r.message || 'Students promoted.');
            loadStudents();
        } else {
            showToast('error', r.message || 'Bulk promotion failed.');
        }
    }).fail(function(x) {
        // #region agent log
        fetch('http://127.0.0.1:7754/ingest/91cba0a2-0439-4759-95d0-b5739de6397c', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': 'ab35e3' }, body: JSON.stringify({ sessionId: 'ab35e3', hypothesisId: 'H4', location: 'admin/students.php:promoteAjax:fail', message: 'bulk promote HTTP fail', data: { status: x.status, message: (x.responseJSON && x.responseJSON.message) ? String(x.responseJSON.message).slice(0, 120) : '' }, timestamp: Date.now() }) }).catch(function () {});
        // #endregion
        showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.');
    });
});

function studentFormReset() {
    $('#studentModalTitle').text('Add Student');
    $('#studentId').val('');
    $('#studentName').val('');
    $('#studentEmail').val('');
    $('#studentPassword').val('');
    $('#studentDegree').val('');
    $('#studentSession').val('');
    $('#studentDepartmentId').val('');
    $('#studentSemester').val(1);
    $('#studentSection').val('A');
    $('#rollNoWrap').hide();
    $('#studentRollNo').val('');
    Validation.clearFieldErrors('#studentModal');
}

$('#studentSaveBtn').on('click', function() {
    var id          = $('#studentId').val();
    var email       = ($('#studentEmail').val() || '').trim();
    var full_name   = ($('#studentName').val() || '').trim();
    var degree_id   = $('#studentDegree').val() || null;
    var section     = ($('#studentSection').val() || '').trim();
    Validation.clearFieldErrors('#studentModal');
    if (!email || !full_name) { showToast('error', 'Please enter email and full name.'); return; }
    var err = Validation.validateMaxLength(email, Validation.LIMITS.email, 'Email');
    if (err) { Validation.showFieldError('#studentEmail', err); showToast('error', err); return; }
    err = Validation.validateMaxLength(full_name, Validation.LIMITS.fullName, 'Full name');
    if (err) { Validation.showFieldError('#studentName', err); showToast('error', err); return; }
    err = Validation.validateMaxLength(section, Validation.LIMITS.section, 'Section');
    if (err) { Validation.showFieldError('#studentSection', err); showToast('error', err); return; }

    var data = {
        email: email, full_name: full_name,
        degree_id: degree_id,
        academic_session_id: $('#studentSession').val() || null,
        department_id: $('#studentDepartmentId').val() || null,
        semester: $('#studentSemester').val(),
        section: section || 'A'
    };
    if (!id) {
        data.password = $('#studentPassword').val() || 'student123';
    } else if ($('#studentPassword').val()) {
        data.password = $('#studentPassword').val();
    }
    data.id = id;

    var url = base + 'actions/students.php';
    if (id) {
        $.ajax({ url: url, method: 'PUT', contentType: 'application/json', data: JSON.stringify(data) })
            .done(function(r) {
                if (r.success) {
                    $('#studentModal').modal('hide');
                    loadStudents();
                    var msg = r.message || 'Student updated.';
                    if (r.roll_no) msg += ' Roll No: ' + r.roll_no;
                    showToast('success', msg);
                } else showToast('error', r.message);
            })
            .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
    } else {
        $.post(url, data)
            .done(function(r) {
                if (r.success) {
                    $('#studentModal').modal('hide');
                    loadStudents();
                    var msg = r.message || 'Student added.';
                    showToast('success', msg);
                } else showToast('error', r.message);
            })
            .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
    }
});

// Edit
$(document).on('click', '.edit-student', function() {
    var id = $(this).data('id');
    $.get(base + 'actions/students.php?id=' + id).done(function(s) {
        if (!s.id) return;
        $('#studentModalTitle').text('Edit Student');
        $('#studentId').val(s.id);
        $('#studentName').val(s.full_name);
        $('#studentEmail').val(s.email);
        $('#studentDegree').val(s.degree_id || '');
        $('#studentSession').val(s.academic_session_id || '');
        $('#studentDepartmentId').val(s.department_id || '');
        $('#studentSemester').val(s.semester);
        $('#studentSection').val(s.section);
        $('#studentPassword').val('');
        if (s.roll_no) {
            $('#studentRollNo').val(s.roll_no);
            $('#rollNoWrap').show();
        } else {
            $('#rollNoWrap').hide();
        }
        Validation.clearFieldErrors('#studentModal');
        $('#studentModal').modal('show');
    });
});

// Promote semester (+1)
$(document).on('click', '.promote-student', function() {
    var id  = $(this).data('id');
    var sem = parseInt($(this).data('sem'), 10) || 1;
    if (!confirm('Promote this student from Semester ' + sem + ' to Semester ' + (sem + 1) + '?')) return;
    $.ajax({
        url: base + 'actions/students.php', method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({ id: id, action: 'promote' })
    }).done(function(r) {
        if (r.success) { loadStudents(); showToast('success', r.message || 'Student promoted.'); }
        else showToast('error', r.message);
    }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});

// Freeze
$(document).on('click', '.freeze-student', function() {
    var id = $(this).data('id');
    if (!confirm('Freeze this student? They will not be able to log in.')) return;
    $.ajax({
        url: base + 'actions/students.php', method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({ id: id, action: 'freeze', is_frozen: 1 })
    }).done(function(r) {
        if (r.success) { loadStudents(); showToast('success', r.message || 'Student frozen.'); }
        else showToast('error', r.message);
    }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});

// Unfreeze
$(document).on('click', '.unfreeze-student', function() {
    var id = $(this).data('id');
    if (!confirm('Unfreeze this student?')) return;
    $.ajax({
        url: base + 'actions/students.php', method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({ id: id, action: 'freeze', is_frozen: 0 })
    }).done(function(r) {
        if (r.success) { loadStudents(); showToast('success', r.message || 'Student unfrozen.'); }
        else showToast('error', r.message);
    }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});

// Delete
$(document).on('click', '.delete-student', function() {
    if (!confirm('Deactivate this student?')) return;
    $.ajax({ url: base + 'actions/students.php', method: 'DELETE', contentType: 'application/json', data: JSON.stringify({ id: $(this).data('id') }) })
        .done(function(r) {
            if (r.success) {
                if (studentPage > 1 && (studentPage - 1) * studentPageSize >= (studentTotal - 1)) studentPage--;
                loadStudents(); showToast('success', r.message || 'Student removed.');
            } else showToast('error', r.message);
        })
        .fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});

$(function() { loadDepts(); loadDegrees(); loadSessions(); loadStudentFilterOptions(); loadStudents(); });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
