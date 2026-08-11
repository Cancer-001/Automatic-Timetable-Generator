<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Faculty';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Faculty Management</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#facultyModal" onclick="facultyFormReset()">Add Faculty</button>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="dropdown">
                    <label class="small text-muted d-block mb-1">Faculty Filter</label>
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="facultyFilterBtn" style="min-width:320px;">All faculty</button>
                    <div class="dropdown-menu p-2" style="min-width:360px;">
                        <input type="text" id="facultyFilterSearch" class="form-control form-control-sm mb-2" placeholder="Search faculty">
                        <div id="facultyFilterList" style="max-height:260px;overflow:auto;"></div>
                        <div class="d-flex justify-content-between mt-2">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="facultyFilterSelectAll">Select all</button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="facultyFilterClear">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-management" id="facultyTable">
                    <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Degree</th><th>Department</th><th>Assignment Status</th><th>Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2" id="facultyPager">
                <div>
                    <label class="small text-muted me-1">Show</label>
                    <select id="facultyPageSize" class="form-select form-select-sm d-inline-block" style="width:auto">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="75">75</option>
                        <option value="100">100</option>
                    </select>
                    <span class="small text-muted ms-1">rows</span>
                </div>
                <div class="small text-muted" id="facultyPageInfo"></div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="facultyPrev">Previous</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="facultyNext">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="facultyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="facultyModalTitle">Add Faculty</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="facultyId">
                <div class="mb-2"><label class="form-label">Full Name</label><input type="text" id="facultyName" class="form-control" maxlength="128" required><div class="field-error text-danger small" style="display:none"></div></div>
                <div class="mb-2"><label class="form-label">Email</label><input type="email" id="facultyEmail" class="form-control" maxlength="128" required><div class="field-error text-danger small" style="display:none"></div></div>
                <div class="mb-2"><label class="form-label">Password <small class="text-muted">(leave blank to keep)</small></label><div class="input-group"><input type="password" id="facultyPassword" class="form-control"><button class="btn btn-outline-secondary pw-toggle-btn" type="button" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button></div></div>
                <div class="mb-2"><label class="form-label">Faculty Type</label><select id="facultyType" class="form-select"><option value="permanent">Permanent</option><option value="visiting">Visiting</option></select></div>
                <div id="visitingTimeWrap" class="border rounded p-2 mb-2 d-none">
                    <div class="small fw-semibold mb-2">Visiting Availability (required)</div>
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label mb-1">Day</label><select id="visitingDay" class="form-select form-select-sm"><option value="">-- Select day --</option><option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option></select></div>
                        <div class="col-md-4"><label class="form-label mb-1">Start</label><input type="time" id="visitingStart" class="form-control form-control-sm"></div>
                        <div class="col-md-4"><label class="form-label mb-1">End</label><input type="time" id="visitingEnd" class="form-control form-control-sm"></div>
                    </div>
                    <div class="small text-muted mt-1">Visiting faculty can only be assigned and scheduled inside this window.</div>
                </div>
                <div class="mb-2"><label class="form-label">Degree</label><select id="facultyDegreeId" class="form-select"><option value="">-- None --</option></select></div>
                <div class="mb-2"><label class="form-label">Department</label><select id="facultyDepartmentId" class="form-select"><option value="">-- None --</option></select></div>
                <div class="mb-2"><label class="form-label">Availability notes</label><textarea id="facultyAvailability" class="form-control" rows="2" maxlength="300"></textarea><div class="field-error text-danger small" style="display:none"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="facultySaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assignedCoursesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assigned Courses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul id="assignedCoursesList" class="mb-0"></ul>
            </div>
        </div>
    </div>
</div>

<script>
function loadDepts() { $.get(base + 'actions/departments.php').done(function(list) { var $s = $('#facultyDepartmentId'); $s.find('option:not(:first)').remove(); (list||[]).forEach(function(d){ $s.append($('<option>').val(d.id).text(d.name)); }); }); }
function loadDegrees() { $.get(base + 'actions/degrees.php').done(function(list) { var $s = $('#facultyDegreeId'); $s.find('option:not(:first)').remove(); (list||[]).forEach(function(d){ $s.append($('<option>').val(d.id).text((d.code || '') + ' - ' + (d.name || ''))); }); }); }
var facultyPage = 1;
var facultyPageSize = 25;
var facultyTotal = 0;
var selectedFacultyIds = [];
var facultyFilterItems = [];
var facultyFilterTimer = null;
function updateFacultyFilterLabel() {
    if (!selectedFacultyIds.length) return $('#facultyFilterBtn').text('All faculty');
    if (selectedFacultyIds.length === 1) {
        var hit = facultyFilterItems.find(function(x){ return String(x.id) === String(selectedFacultyIds[0]); });
        return $('#facultyFilterBtn').text(hit ? (hit.full_name || hit.email || '1 selected') : '1 selected');
    }
    $('#facultyFilterBtn').text(selectedFacultyIds.length + ' faculty selected');
}
function renderFacultyFilterList() {
    var $list = $('#facultyFilterList').empty();
    if (!facultyFilterItems.length) { $list.html('<div class="text-muted small">No faculty found.</div>'); return; }
    facultyFilterItems.forEach(function(f){
        var id = String(f.id), checked = selectedFacultyIds.indexOf(id) !== -1;
        var $row = $('<label class="form-check d-flex align-items-start mb-1"><input class="form-check-input me-2 mt-1 faculty-filter-item" type="checkbox"><span class="small"></span></label>');
        $row.find('input').val(id).prop('checked', checked);
        $row.find('span').text((f.full_name || '') + (f.email ? (' (' + f.email + ')') : ''));
        $list.append($row);
    });
}
function loadFacultyFilterOptions() {
    var q = ($('#facultyFilterSearch').val() || '').trim();
    $.get(base + 'actions/faculty.php', { q: q }).done(function(list){
        facultyFilterItems = Array.isArray(list) ? list : [];
        renderFacultyFilterList();
        updateFacultyFilterLabel();
    });
}
function refreshFacultyPager() {
    var $info = $('#facultyPageInfo');
    if (!facultyTotal) {
        $info.text('No records found (Rows: 0 / 0)');
        $('#facultyPrev').prop('disabled', true);
        $('#facultyNext').prop('disabled', true);
        return;
    }
    var start = (facultyPage - 1) * facultyPageSize + 1;
    var end = Math.min(facultyTotal, facultyPage * facultyPageSize);
    var totalPages = Math.max(1, Math.ceil(facultyTotal / facultyPageSize));
    var shown = end - start + 1;
    $info.text('Showing ' + start + '–' + end + ' of ' + facultyTotal + ' (Page ' + facultyPage + ' of ' + totalPages + ', Rows: ' + shown + ' / ' + facultyTotal + ')');
    $('#facultyPrev').prop('disabled', facultyPage <= 1);
    $('#facultyNext').prop('disabled', facultyPage >= totalPages);
}
function loadFaculty() {
    $.get(base + 'actions/faculty.php', {
        paged: 1,
        page: facultyPage,
        page_size: facultyPageSize,
        ids: selectedFacultyIds.join(',')
    }).done(function(resp) {
        var $tb = $('#facultyTable tbody').empty();
        if (!resp || resp.success === false) {
            var msg = (resp && resp.message) || 'Could not load faculty.';
            $tb.append($('<tr><td colspan="7" class="text-danger"></td></tr>').find('td').text(msg).end());
            facultyTotal = 0;
            refreshFacultyPager();
            return;
        }
        var list = resp.items || [];
        facultyTotal = resp.total || 0;
        if (!list.length) {
            $tb.append($('<tr><td colspan="7" class="text-muted">No records found.</td></tr>'));
        } else {
            (list || []).forEach(function(f) {
                var count = parseInt(f.assigned_courses_count || 0, 10);
                var courseNames = ((f.assigned_course_names || '') + '').split('||').filter(function(x){ return (x || '').trim() !== ''; });
                var statusHtml = '';
                if (count <= 0 || !courseNames.length) {
                    statusHtml = '<span class="badge bg-info text-dark">Unassigned</span>';
                } else if (count <= 3) {
                    statusHtml = '<span class="small">' + escapeHtml(courseNames.join('\n')).replace(/\n/g, '<br>') + '</span>';
                } else {
                    var firstThree = courseNames.slice(0, 3);
                    statusHtml = '<span class="small">' + escapeHtml(firstThree.join('\n')).replace(/\n/g, '<br>') + '</span> ' +
                        '<button type="button" class="btn btn-sm btn-outline-primary ms-1 show-all-courses" data-courses="' + encodeURIComponent(JSON.stringify(courseNames)) + '">3+</button>';
                }
                $tb.append($('<tr>').append(
                    $('<td>').text(f.full_name),
                    $('<td>').text(f.email),
                    $('<td>').text((f.faculty_type || 'permanent') === 'visiting' ? 'Visiting' : 'Permanent'),
                    $('<td>').text(f.degree_name || '-'),
                    $('<td>').text(f.department_name || '-'),
                    $('<td>').html(statusHtml),
                    $('<td>').html('<button class="btn btn-sm btn-warning me-1 edit-faculty" data-id="'+f.id+'">Edit</button><button class="btn btn-sm btn-danger delete-faculty" data-id="'+f.id+'">Delete</button>')
                ));
            });
        }
        refreshFacultyPager();
    }).fail(function() {
        var $tb = $('#facultyTable tbody').empty();
        $tb.append($('<tr><td colspan="7" class="text-danger">Failed to load faculty.</td></tr>'));
        facultyTotal = 0;
        refreshFacultyPager();
    });
}
$(document).on('click', '.show-all-courses', function() {
    var raw = $(this).attr('data-courses') || '';
    var courses = [];
    try { courses = JSON.parse(decodeURIComponent(raw)); } catch (e) { courses = []; }
    var $list = $('#assignedCoursesList').empty();
    if (!Array.isArray(courses) || !courses.length) {
        $list.append('<li>No records found.</li>');
    } else {
        courses.forEach(function(c) { $list.append($('<li>').text(c)); });
    }
    $('#assignedCoursesModal').modal('show');
});
$('#facultyPageSize').on('change', function() {
    facultyPageSize = parseInt($(this).val(), 10) || 25;
    facultyPage = 1;
    loadFaculty();
});
$('#facultyPrev').on('click', function() {
    if (facultyPage > 1) {
        facultyPage--;
        loadFaculty();
    }
});
$('#facultyNext').on('click', function() {
    facultyPage++;
    loadFaculty();
});
$('#facultyFilterSearch').on('input', function() {
    if (facultyFilterTimer) clearTimeout(facultyFilterTimer);
    facultyFilterTimer = setTimeout(loadFacultyFilterOptions, 250);
});
$(document).on('change', '.faculty-filter-item', function() {
    var v = String($(this).val());
    if (this.checked) { if (selectedFacultyIds.indexOf(v) === -1) selectedFacultyIds.push(v); }
    else { selectedFacultyIds = selectedFacultyIds.filter(function(x){ return x !== v; }); }
    updateFacultyFilterLabel();
    facultyPage = 1; loadFaculty();
});
$('#facultyFilterSelectAll').on('click', function() {
    selectedFacultyIds = facultyFilterItems.map(function(f){ return String(f.id); });
    renderFacultyFilterList(); updateFacultyFilterLabel(); facultyPage = 1; loadFaculty();
});
$('#facultyFilterClear').on('click', function() {
    selectedFacultyIds = [];
    renderFacultyFilterList(); updateFacultyFilterLabel(); facultyPage = 1; loadFaculty();
});
function facultyFormReset() {
    $('#facultyModalTitle').text('Add Faculty');
    $('#facultyId').val(''); $('#facultyName').val(''); $('#facultyEmail').val(''); $('#facultyPassword').val(''); $('#facultyAvailability').val(''); $('#facultyDepartmentId').val(''); $('#facultyDegreeId').val('');
    $('#facultyType').val('permanent'); $('#visitingDay').val(''); $('#visitingStart').val(''); $('#visitingEnd').val('');
    toggleVisitingFields();
}
function toggleVisitingFields() {
    var isVisiting = ($('#facultyType').val() || 'permanent') === 'visiting';
    $('#visitingTimeWrap').toggleClass('d-none', !isVisiting);
}
$('#facultyType').on('change', toggleVisitingFields);
$('#facultySaveBtn').on('click', function() {
    var id = $('#facultyId').val();
    var email = ($('#facultyEmail').val() || '').trim(), full_name = ($('#facultyName').val() || '').trim(), availability_notes = ($('#facultyAvailability').val() || '').trim();
    var faculty_type = ($('#facultyType').val() || 'permanent');
    var visiting_day_of_week = $('#visitingDay').val() || null;
    var visiting_start_time = $('#visitingStart').val() || null;
    var visiting_end_time = $('#visitingEnd').val() || null;
    Validation.clearFieldErrors('#facultyModal');
    if (!email || !full_name) { showToast('error', 'Please enter email and full name.'); return; }
    if (faculty_type === 'visiting' && (!visiting_day_of_week || !visiting_start_time || !visiting_end_time)) {
        showToast('error', 'Visiting faculty requires day, start time, and end time.');
        return;
    }
    var err = Validation.validateMaxLength(email, Validation.LIMITS.email, 'Email');
    if (err) { Validation.showFieldError('#facultyEmail', err); showToast('error', err); return; }
    err = Validation.validateMaxLength(full_name, Validation.LIMITS.fullName, 'Full name');
    if (err) { Validation.showFieldError('#facultyName', err); showToast('error', err); return; }
    err = Validation.validateMaxLength(availability_notes, Validation.LIMITS.notes, 'Availability notes');
    if (err) { Validation.showFieldError('#facultyAvailability', err); showToast('error', err); return; }
    var data = {
        email: email,
        full_name: full_name,
        degree_id: $('#facultyDegreeId').val() || null,
        department_id: $('#facultyDepartmentId').val() || null,
        availability_notes: availability_notes,
        faculty_type: faculty_type,
        visiting_day_of_week: faculty_type === 'visiting' ? visiting_day_of_week : null,
        visiting_start_time: faculty_type === 'visiting' ? visiting_start_time : null,
        visiting_end_time: faculty_type === 'visiting' ? visiting_end_time : null
    };
    if (!id) data.password = $('#facultyPassword').val() || 'faculty123';
    else if ($('#facultyPassword').val()) data.password = $('#facultyPassword').val();
    data.id = id;
    var url = base + 'actions/faculty.php';
    if (id) {
        $.ajax({ url: url, method: 'PUT', contentType: 'application/json', data: JSON.stringify(data) }).done(function(r) { if (r.success) { $('#facultyModal').modal('hide'); loadFaculty(); showToast('success', r.message || 'Saved.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
    } else {
        if (!data.password) data.password = 'faculty123';
        $.post(url, data).done(function(r) { if (r.success) { $('#facultyModal').modal('hide'); loadFaculty(); showToast('success', r.message || 'Faculty added.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
    }
});
$(document).on('click', '.edit-faculty', function() {
    var id = $(this).data('id');
    $.get(base + 'actions/faculty.php?id=' + id).done(function(f) {
        if (!f.id) return;
        $('#facultyModalTitle').text('Edit Faculty');
        $('#facultyId').val(f.id); $('#facultyName').val(f.full_name); $('#facultyEmail').val(f.email); $('#facultyDegreeId').val(f.degree_id || ''); $('#facultyDepartmentId').val(f.department_id || ''); $('#facultyAvailability').val(f.availability_notes || '');
        $('#facultyType').val((f.faculty_type || 'permanent'));
        $('#visitingDay').val(f.visiting_day_of_week || '');
        $('#visitingStart').val((f.visiting_start_time || '').toString().slice(0,5));
        $('#visitingEnd').val((f.visiting_end_time || '').toString().slice(0,5));
        toggleVisitingFields();
        $('#facultyPassword').val('');
        $('#facultyModal').modal('show');
    });
});
$(document).on('click', '.delete-faculty', function() {
    if (!confirm('Deactivate this faculty?')) return;
    $.ajax({ url: base + 'actions/faculty.php', method: 'DELETE', contentType: 'application/json', data: JSON.stringify({ id: $(this).data('id') }) }).done(function(r) { if (r.success) { if (facultyPage > 1 && (facultyPage - 1) * facultyPageSize >= (facultyTotal - 1)) { facultyPage--; } loadFaculty(); showToast('success', r.message || 'Faculty removed.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});
$(function() { loadDepts(); loadDegrees(); loadFacultyFilterOptions(); loadFaculty(); toggleVisitingFields(); });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
