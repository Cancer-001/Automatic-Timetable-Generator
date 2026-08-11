<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Courses';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Course Management</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="courseFormReset()">Add Course</button>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="dropdown" id="courseFilterDropdownWrap">
                    <label class="small text-muted d-block mb-1">Courses Filter</label>
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="courseFilterBtn" style="min-width:320px;">
                        All courses
                    </button>
                    <div class="dropdown-menu p-2" style="min-width:360px;">
                        <input type="text" id="courseFilterSearch" class="form-control form-control-sm mb-2" placeholder="Search courses">
                        <div id="courseFilterList" style="max-height:260px; overflow:auto;"></div>
                        <div class="d-flex justify-content-between mt-2">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="courseFilterSelectAll">Select all</button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="courseFilterClear">Clear</button>
                        </div>
                    </div>
                </div>
                <div style="min-width:220px">
                    <label class="small text-muted d-block mb-1">Faculty Assignment</label>
                    <select id="courseAssignmentStatus" class="form-select form-select-sm">
                        <option value="">All courses</option>
                        <option value="assigned">Assigned only</option>
                        <option value="unassigned">Unassigned only</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-management" id="coursesTable">
                    <thead><tr><th>Code</th><th>Name</th><th>CHT</th><th>CHL</th><th>Semester</th><th>Dept</th><th>Sessions/Week</th><th>Assigned Faculty</th><th>Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2" id="coursePager">
                <div>
                    <label class="small text-muted me-1">Show</label>
                    <select id="coursePageSize" class="form-select form-select-sm d-inline-block" style="width:auto">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="75">75</option>
                        <option value="100">100</option>
                    </select>
                    <span class="small text-muted ms-1">rows</span>
                </div>
                <div class="small text-muted" id="coursePageInfo"></div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="coursePrev">Previous</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="courseNext">Next</button>
                </div>
            </div>
            <p class="small text-muted mt-2">Assign faculty from the popup: click <strong>Assign Faculty</strong> on a row to set session, degree, section, time preference, and room.</p>
        </div>
    </div>
</div>

<div class="modal fade" id="assignedFacultyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assigned Faculty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul id="assignedFacultyList" class="mb-0"></ul>
            </div>
        </div>
    </div>
</div>

<!-- Assign faculty (modal with selection table) -->
<div class="modal fade" id="assignFacultyModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign faculty — <span id="afCourseLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="afCourseId">
                <p class="text-muted small">Opening this window <strong>auto-fills</strong> Session, Section (A/B/C per instructor), <strong>preferred weekday</strong> (Mon–Fri rotated per instructor), time window, <strong>rooms spread across active rooms</strong>, and degree (when configured) for pool-only links. <a href="generate.php">Generate Timetable</a> refreshes defaults for <em>all</em> courses. Use <strong>Edit</strong> on a row to fix a line without removing it.</p>
                <input type="hidden" id="afEditingAssignmentId" value="">
                <div class="row g-2 mb-3 border rounded p-3 bg-light">
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Subject (course)</label>
                        <input type="text" id="afSubjectReadonly" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Academic session <span class="text-danger">*</span></label>
                        <select id="afSession" class="form-select"><option value="">— Select session —</option></select>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Degree (program) <span class="text-danger">*</span></label>
                        <select id="afDegree" class="form-select"><option value="">— Select degree —</option></select>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Section <span class="text-danger">*</span></label>
                        <input type="text" id="afSection" class="form-control" maxlength="32" placeholder="e.g. A">
                        <small class="text-muted">Required (e.g. A, B, C).</small>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Preferred day <span class="text-danger">*</span></label>
                        <select id="afDay" class="form-select">
                            <option value="">— Select day —</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                        </select>
                        <small class="text-muted">Required.</small>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Faculty <span class="text-danger">*</span></label>
                        <select id="afFaculty" class="form-select"><option value="">— Select faculty —</option></select>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Preferred start <span class="text-danger">*</span></label>
                        <input type="time" id="afTimeStart" class="form-control">
                        <small class="text-muted">Required.</small>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Preferred end <span class="text-danger">*</span></label>
                        <input type="time" id="afTimeEnd" class="form-control">
                        <small class="text-muted">Required.</small>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Room <span class="text-danger">*</span></label>
                        <select id="afRoom" class="form-select"><option value="">— Select room —</option></select>
                        <small class="text-muted">Required.</small>
                    </div>
                    <div class="col-12 d-flex align-items-end flex-wrap gap-2">
                        <button type="button" class="btn btn-primary" id="afAddBtn">Add assignment</button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="afCancelEditBtn">Cancel edit</button>
                    </div>
                </div>
                <h6 class="mb-2">Current assignments</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover" id="afAssignmentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Session</th>
                                <th>Degree</th>
                                <th>Section</th>
                                <th>Day</th>
                                <th>Faculty</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th style="min-width:140px">Action</th>
                            </tr>
                        </thead>
                        <tbody id="afAssignmentsBody"></tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0 d-none" id="afEmptyHint">No assignments yet. Fill the form above and click Add assignment.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="courseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="courseModalTitle">Add Course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="courseId">
                <div class="mb-2"><label class="form-label">Code</label><input type="text" id="courseCode" class="form-control" maxlength="32" required><div class="field-error text-danger small" style="display:none"></div></div>
                <div class="mb-2"><label class="form-label">Name</label><input type="text" id="courseName" class="form-control" maxlength="128" required><div class="field-error text-danger small" style="display:none"></div></div>
                <div class="row"><div class="col mb-2"><label class="form-label">Credit Hours Theory (CHT)</label><input type="number" id="courseCreditHours" class="form-control" value="3" min="0"></div><div class="col mb-2"><label class="form-label">Credit Hours Lab (CHL)</label><input type="number" id="courseCreditHoursLab" class="form-control" value="0" min="0"></div></div>
                <div class="mb-2"><label class="form-label">Semester</label><input type="number" id="courseSemester" class="form-control" value="1" min="1"></div>
                <div class="mb-2"><label class="form-label">Department</label><select id="courseDepartmentId" class="form-select"><option value="">-- None --</option></select></div>
                <div class="mb-2"><label class="form-label">Sessions per week</label><input type="number" id="courseSessionsPerWeek" class="form-control" value="1" min="1"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="courseSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
if (typeof base === 'undefined' || !base) base = '../';
function loadDepartments() {
    $.get(base + 'actions/departments.php').done(function(data) {
        var list = Array.isArray(data) ? data : [];
        var $sel = $('#courseDepartmentId');
        $sel.find('option:not(:first)').remove();
        list.forEach(function(d) {
            $sel.append($('<option>').val(d.id).text(d.name || d.code || ''));
        });
        if (!Array.isArray(data) && data && data.success === false) {
            showToast('error', data.message || 'Could not load departments.');
        }
    }).fail(function(xhr) {
        showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load departments.');
        console.error('Departments request failed', xhr.status, xhr.responseText);
    });
}
var coursePage = 1;
var coursePageSize = 25;
var courseTotal = 0;
var courseSearchTerm = '';
var courseAssignmentStatus = '';
var courseSearchTimer = null;
var selectedCourseIds = [];
var allCourseFilterItems = [];
var courseFilterFetchTimer = null;

function updateCourseFilterButtonLabel() {
    var $btn = $('#courseFilterBtn');
    if (!selectedCourseIds.length) {
        $btn.text('All courses');
        return;
    }
    if (selectedCourseIds.length === 1) {
        var id = selectedCourseIds[0];
        var hit = allCourseFilterItems.find(function(x){ return parseInt(x.id,10) === parseInt(id,10); });
        $btn.text(hit ? (hit.code + ' - ' + hit.name) : '1 selected');
        return;
    }
    $btn.text(selectedCourseIds.length + ' courses selected');
}

function renderCourseFilterList() {
    var $list = $('#courseFilterList').empty();
    var items = allCourseFilterItems;
    if (!items.length) {
        $list.html('<div class="text-muted small">No courses found.</div>');
        return;
    }
    items.forEach(function(c){
        var cid = String(c.id);
        var checked = selectedCourseIds.indexOf(cid) !== -1;
        var $row = $('<label class="form-check d-flex align-items-start mb-1">\
            <input class="form-check-input me-2 mt-1 course-filter-item" type="checkbox">\
            <span class="small"></span>\
        </label>');
        $row.find('input').attr('value', cid).prop('checked', checked);
        $row.find('span').text((c.code || '') + ' - ' + (c.name || ''));
        $list.append($row);
    });
}

function loadCourseFilterOptions() {
    var q = ($('#courseFilterSearch').val() || '').trim();
    $.get(base + 'actions/courses.php', { q: q }).done(function(list) {
        allCourseFilterItems = Array.isArray(list) ? list : [];
        renderCourseFilterList();
        updateCourseFilterButtonLabel();
    });
}
function refreshCoursePager() {
    var $info = $('#coursePageInfo');
    if (!courseTotal) {
        $info.text('No records found (Rows: 0 / 0)');
        $('#coursePrev').prop('disabled', true);
        $('#courseNext').prop('disabled', true);
        return;
    }
    var start = (coursePage - 1) * coursePageSize + 1;
    var end = Math.min(courseTotal, coursePage * coursePageSize);
    var totalPages = Math.max(1, Math.ceil(courseTotal / coursePageSize));
    var shown = end - start + 1;
    $info.text('Showing ' + start + '–' + end + ' of ' + courseTotal + ' (Page ' + coursePage + ' of ' + totalPages + ', Rows: ' + shown + ' / ' + courseTotal + ')');
    $('#coursePrev').prop('disabled', coursePage <= 1);
    $('#courseNext').prop('disabled', coursePage >= totalPages);
}
function loadCourses() {
    $.get(base + 'actions/courses.php', {
        paged: 1,
        page: coursePage,
        page_size: coursePageSize,
        q: courseSearchTerm,
        ids: selectedCourseIds.join(','),
        assignment_status: courseAssignmentStatus
    }).done(function(data) {
        var $tb = $('#coursesTable tbody').empty();
        if (!data || data.success === false) {
            var msg = (data && data.message) || 'Could not load courses.';
            $tb.append($('<tr><td colspan="9" class="text-danger"></td></tr>').find('td').text(msg).end());
            courseTotal = 0;
            refreshCoursePager();
            return;
        }
        var list = Array.isArray(data.items) ? data.items : [];
        courseTotal = data.total || 0;
        if (list.length === 0) {
            $tb.append($('<tr><td colspan="9" class="text-muted">No records found.</td></tr>'));
        } else {
            list.forEach(function(c) {
                var cnt = parseInt(c.assigned_faculty_count || 0, 10);
                var names = ((c.assigned_faculty_names || '') + '').split('||').filter(function(x){ return (x || '').trim() !== ''; });
                var assignedHtml = '';
                if (cnt <= 0 || !names.length) {
                    assignedHtml = '<span class="badge bg-info text-dark">Unassigned</span>';
                } else if (cnt <= 3) {
                    assignedHtml = '<span class="small">' + escapeHtml(names.join('\n')).replace(/\n/g, '<br>') + '</span>';
                } else {
                    var firstThree = names.slice(0, 3);
                    assignedHtml = '<span class="small">' + escapeHtml(firstThree.join('\n')).replace(/\n/g, '<br>') + '</span> ' +
                        '<button type="button" class="btn btn-sm btn-outline-primary ms-1 show-all-faculty" data-names="' + encodeURIComponent(JSON.stringify(names)) + '">3+</button>';
                }
                $tb.append($('<tr>').append(
                    $('<td>').text(c.code),
                    $('<td>').text(c.name),
                    $('<td class="text-center">').text(c.credit_hours),
                    $('<td class="text-center">').text(c.credit_hours_lab != null ? c.credit_hours_lab : 0),
                    $('<td class="text-center">').text(c.semester),
                    $('<td>').text(c.department_name || '-'),
                    $('<td class="text-center">').text(c.sessions_per_week),
                    $('<td>').html(assignedHtml),
                    $('<td>').html('<button class="btn btn-sm btn-info me-1 assign-faculty" data-id="'+c.id+'">Assign Faculty</button><button class="btn btn-sm btn-warning me-1 edit-course" data-id="'+c.id+'">Edit</button><button class="btn btn-sm btn-danger delete-course" data-id="'+c.id+'">Delete</button>')
                ));
            });
        }
        refreshCoursePager();
    }).fail(function(xhr) {
        $('#coursesTable tbody').html('<tr><td colspan="9" class="text-danger">Failed to load courses. Check console. Log in as admin if needed.</td></tr>');
        courseTotal = 0;
        refreshCoursePager();
    });
}
$(document).on('click', '.show-all-faculty', function() {
    var raw = $(this).attr('data-names') || '';
    var names = [];
    try { names = JSON.parse(decodeURIComponent(raw)); } catch (e) { names = []; }
    var $list = $('#assignedFacultyList').empty();
    if (!Array.isArray(names) || !names.length) {
        $list.append('<li>No records found.</li>');
    } else {
        names.forEach(function(n) {
            $list.append($('<li>').text(n));
        });
    }
    $('#assignedFacultyModal').modal('show');
});
$('#coursePageSize').on('change', function() {
    coursePageSize = parseInt($(this).val(), 10) || 25;
    coursePage = 1;
    loadCourses();
});
$('#courseAssignmentStatus').on('change', function() {
    courseAssignmentStatus = String($(this).val() || '').trim();
    coursePage = 1;
    loadCourses();
});
$('#coursePrev').on('click', function() {
    if (coursePage > 1) {
        coursePage--;
        loadCourses();
    }
});
$('#courseNext').on('click', function() {
    coursePage++;
    loadCourses();
});
$('#courseFilterSearch').on('input', function() {
    if (courseFilterFetchTimer) clearTimeout(courseFilterFetchTimer);
    courseFilterFetchTimer = setTimeout(function() {
        loadCourseFilterOptions();
    }, 250);
});
$(document).on('change', '.course-filter-item', function() {
    var val = String($(this).val());
    if (this.checked) {
        if (selectedCourseIds.indexOf(val) === -1) selectedCourseIds.push(val);
    } else {
        selectedCourseIds = selectedCourseIds.filter(function(x){ return x !== val; });
    }
    updateCourseFilterButtonLabel();
    coursePage = 1;
    loadCourses();
});
$('#courseFilterSelectAll').on('click', function() {
    selectedCourseIds = allCourseFilterItems.map(function(c){ return String(c.id); });
    renderCourseFilterList();
    updateCourseFilterButtonLabel();
    coursePage = 1;
    loadCourses();
});
$('#courseFilterClear').on('click', function() {
    selectedCourseIds = [];
    renderCourseFilterList();
    updateCourseFilterButtonLabel();
    coursePage = 1;
    loadCourses();
});
function courseFormReset() {
    $('#courseModalTitle').text('Add Course');
    $('#courseId').val('');
    $('#courseCode, #courseName, #courseCreditHours, #courseSemester, #courseSessionsPerWeek').val('');
    $('#courseCreditHours').val(3); $('#courseCreditHoursLab').val(0); $('#courseSemester').val(1); $('#courseSessionsPerWeek').val(3);
    $('#courseDepartmentId').val('');
}
$('#courseSaveBtn').on('click', function() {
    var id = $('#courseId').val();
    var code = ($('#courseCode').val() || '').trim(), name = ($('#courseName').val() || '').trim();
    Validation.clearFieldErrors('#courseModal');
    if (!code || !name) { showToast('error', 'Please enter course code and name.'); return; }
    var err = Validation.validateMaxLength(code, Validation.LIMITS.courseCode, 'Course code');
    if (err) { Validation.showFieldError('#courseCode', err); showToast('error', err); return; }
    err = Validation.validateMaxLength(name, Validation.LIMITS.courseName, 'Course name');
    if (err) { Validation.showFieldError('#courseName', err); showToast('error', err); return; }
    var data = {
        code: code,
        name: name,
        credit_hours: $('#courseCreditHours').val(),
            credit_hours_lab: $('#courseCreditHoursLab').val() || 0,
        semester: $('#courseSemester').val(),
        department_id: $('#courseDepartmentId').val() || null,
        sessions_per_week: $('#courseSessionsPerWeek').val()
    };
    var url = base + 'actions/courses.php';
    if (id) { data.id = id; $.ajax({ url: url, method: 'PUT', contentType: 'application/json', data: JSON.stringify(data) }).done(function(r) { if (r.success) { $('#courseModal').modal('hide'); loadCourses(); showToast('success', r.message || 'Saved.'); } else showToast('error', r.message || 'Error'); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); }); }
    else { $.post(url, data).done(function(r) { if (r.success) { $('#courseModal').modal('hide'); loadCourses(); showToast('success', r.message || 'Course created.'); } else showToast('error', r.message || 'Error'); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); }); }
});
$(document).on('click', '.edit-course', function() {
    var id = $(this).data('id');
    $.get(base + 'actions/courses.php?id=' + id).done(function(c) {
        if (!c.id) return;
        $('#courseModalTitle').text('Edit Course');
        $('#courseId').val(c.id); $('#courseCode').val(c.code); $('#courseName').val(c.name);
        $('#courseCreditHours').val(c.credit_hours); $('#courseSemester').val(c.semester);
        $('#courseSessionsPerWeek').val(c.sessions_per_week); $('#courseDepartmentId').val(c.department_id || '');
        $('#courseModal').modal('show');
    });
});
$(document).on('click', '.delete-course', function() {
    if (!confirm('Delete this course?')) return;
    $.ajax({ url: base + 'actions/courses.php', method: 'DELETE', contentType: 'application/json', data: JSON.stringify({ id: $(this).data('id') }) }).done(function(r) { if (r.success) { if (coursePage > 1 && (coursePage - 1) * coursePageSize >= (courseTotal - 1)) { coursePage--; } loadCourses(); showToast('success', r.message || 'Deleted.'); } else showToast('error', r.message || 'Could not delete.'); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});
var currentCourseId = 0;

function loadAfDropdowns(callback) {
    $.when(
        $.get(base + 'actions/sessions.php'),
        $.get(base + 'actions/degrees.php'),
        $.get(base + 'actions/rooms.php'),
        $.get(base + 'actions/faculty.php')
    ).always(function(sRes, dRes, rRes, fRes) {
        var listS = (sRes && Array.isArray(sRes[0])) ? sRes[0] : [];
        var listD = (dRes && Array.isArray(dRes[0])) ? dRes[0] : [];
        var listR = (rRes && Array.isArray(rRes[0])) ? rRes[0] : [];
        var listF = (fRes && Array.isArray(fRes[0])) ? fRes[0] : [];
        var $s = $('#afSession');
        $s.find('option:not(:first)').remove();
        listS.forEach(function(x) {
            $s.append($('<option>').val(x.id).text(x.name || ('Session #' + x.id)));
        });
        var $d = $('#afDegree');
        $d.find('option:not(:first)').remove();
        listD.forEach(function(x) {
            $d.append($('<option>').val(x.id).text((x.code || '') + ' — ' + (x.name || '')));
        });
        var $r = $('#afRoom');
        $r.find('option:not(:first)').remove();
        listR.forEach(function(x) {
            if (x.is_active === 0 || x.is_active === '0') return;
            $r.append($('<option>').val(x.id).text(x.room_number || ('Room #' + x.id)));
        });
        var $f = $('#afFaculty');
        $f.find('option:not(:first)').remove();
        listF.forEach(function(x) {
            var label = x.full_name || x.name || ('#' + x.id);
            if ((x.faculty_type || 'permanent') === 'visiting') {
                var dayTxt = formatDayCell(x.visiting_day_of_week);
                var timeTxt = formatTimeCell(x.visiting_start_time, x.visiting_end_time);
                if (dayTxt !== '—' && timeTxt !== '—') {
                    label += ' (Visiting: ' + dayTxt + ' ' + timeTxt + ')';
                } else {
                    label += ' (Visiting)';
                }
            } else {
                label += ' (Permanent)';
            }
            $f.append($('<option>').val(x.id).text(label));
        });
        if (typeof callback === 'function') {
            callback();
        }
    });
}

function formatDayCell(v) {
    var n = parseInt(v, 10);
    if (!n || n < 1 || n > 5) return '—';
    var names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    return names[n - 1];
}

function formatTimeCell(start, end) {
    function t(v) {
        if (!v) return '';
        var s = String(v);
        var m = s.match(/^(\d{1,2}):(\d{2})/);
        if (!m) return s.length >= 5 ? s.slice(0, 5) : s;
        var hh = parseInt(m[1], 10);
        var mm = m[2];
        if (isNaN(hh)) return s.length >= 5 ? s.slice(0, 5) : s;
        var suffix = hh >= 12 ? 'PM' : 'AM';
        var h12 = hh % 12;
        if (h12 === 0) h12 = 12;
        var hTxt = (h12 < 10 ? '0' : '') + h12;
        return hTxt + ':' + mm + ' ' + suffix;
    }
    var a = t(start), b = t(end);
    if (!a && !b) return '—';
    if (a && b) return a + ' – ' + b;
    return a || b;
}

function timeValueForInput(v) {
    if (!v) return '';
    var s = String(v);
    return s.length >= 5 ? s.slice(0, 5) : s;
}

function clearAfEditMode() {
    $('#afEditingAssignmentId').val('');
    $('#afAddBtn').text('Add assignment');
    $('#afCancelEditBtn').addClass('d-none');
}

function refreshAssignFacultyModal() {
    var cid = $('#afCourseId').val();
    if (!cid) return;
    $.get(base + 'actions/course_faculty.php', { course_id: cid }).done(function(resp) {
        var rows = (resp && resp.assignments) ? resp.assignments : [];
        var $tb = $('#afAssignmentsBody').empty();
        if (!rows.length) {
            $('#afEmptyHint').removeClass('d-none');
        } else {
            $('#afEmptyHint').addClass('d-none');
            rows.forEach(function(r) {
                var subj = escapeHtml((r.course_code || '') + ' — ' + (r.course_name || ''));
                var poolOnly = !!r.from_course_faculty_pool;
                var $rm = $('<button type="button" class="btn btn-sm btn-outline-danger">Remove</button>');
                $rm.on('click', function() {
                    var delPayload = r.assignment_id
                        ? { assignment_id: r.assignment_id }
                        : { course_id: parseInt(r.course_id, 10), faculty_id: parseInt(r.faculty_id, 10) };
                    if (!delPayload.assignment_id && (!delPayload.course_id || !delPayload.faculty_id)) {
                        showToast('error', 'Cannot remove: missing ids.');
                        return;
                    }
                    $.ajax({
                        url: base + 'actions/course_faculty.php',
                        method: 'DELETE',
                        contentType: 'application/json',
                        data: JSON.stringify(delPayload)
                    }).done(function(x) {
                        if (x.success) { clearAfEditMode(); refreshAssignFacultyModal(); loadCourses(); showToast('success', x.message || 'Removed.'); }
                        else showToast('error', x.message || 'Error');
                    }).fail(function(x) {
                        showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.');
                    });
                });
                var $edit = $('<button type="button" class="btn btn-sm btn-outline-primary">Edit</button>');
                $edit.on('click', function() {
                    if (!r.assignment_id || poolOnly) return;
                    loadAfDropdowns(function() {
                        $('#afEditingAssignmentId').val(String(r.assignment_id));
                        $('#afAddBtn').text('Save changes');
                        $('#afCancelEditBtn').removeClass('d-none');
                        $('#afSession').val(r.academic_session_id ? String(r.academic_session_id) : '');
                        $('#afDegree').val(r.degree_id ? String(r.degree_id) : '');
                        $('#afSection').val(r.section || '');
                        $('#afDay').val(r.preferred_day_of_week ? String(r.preferred_day_of_week) : '');
                        $('#afFaculty').val(String(r.faculty_id));
                        $('#afTimeStart').val(timeValueForInput(r.preferred_start_time));
                        $('#afTimeEnd').val(timeValueForInput(r.preferred_end_time));
                        $('#afRoom').val(r.room_id ? String(r.room_id) : '');
                        $('#assignFacultyModal .modal-body').animate({ scrollTop: 0 }, 200);
                    });
                });
                var sessCell = r.session_name || '—';
                if (poolOnly) {
                    sessCell = '—';
                }
                $tb.append($('<tr>').append(
                    $('<td>').html(subj + (poolOnly ? ' <span class="badge bg-secondary">Timetable pool</span>' : '')),
                    $('<td>').text(sessCell),
                    $('<td>').text(r.degree_name || '—'),
                    $('<td>').text(r.section || '—'),
                    $('<td>').text(formatDayCell(r.preferred_day_of_week)),
                    $('<td>').text(r.faculty_name || '—'),
                    $('<td>').text(formatTimeCell(r.preferred_start_time, r.preferred_end_time)),
                    $('<td>').text(r.room_number || '—'),
                    $('<td>').append((function() {
                        var $act = $('<div>').addClass('d-flex flex-column gap-1');
                        if (r.assignment_id && !poolOnly) {
                            $act.append($edit);
                        }
                        $act.append($rm);
                        return $act;
                    })())
                ));
            });
        }
    }).fail(function() {
        showToast('error', 'Could not load assignments.');
    });
}

$(document).on('click', '.assign-faculty', function() {
    currentCourseId = $(this).data('id');
    var $tr = $(this).closest('tr');
    var code = $tr.find('td:eq(0)').text();
    var name = $tr.find('td:eq(1)').text();
    $('#afCourseId').val(currentCourseId);
    $('#afCourseLabel').text(code + ' — ' + name);
    $('#afSubjectReadonly').val(code + ' — ' + name);
    clearAfEditMode();
    $('#afSession, #afDegree, #afRoom').val('');
    $('#afSection').val('');
    $('#afDay').val('');
    $('#afFaculty').val('');
    $('#afTimeStart, #afTimeEnd').val('');
    loadAfDropdowns();
    refreshAssignFacultyModal();
    $('#assignFacultyModal').modal('show');
});

$('#afAddBtn').on('click', function() {
    var course_id = parseInt($('#afCourseId').val(), 10);
    var faculty_id = parseInt($('#afFaculty').val(), 10);
    var sessionVal = ($('#afSession').val() || '').trim();
    var degreeVal = ($('#afDegree').val() || '').trim();
    var sectionVal = ($('#afSection').val() || '').trim();
    var dayVal = ($('#afDay').val() || '').trim();
    var startVal = ($('#afTimeStart').val() || '').trim();
    var endVal = ($('#afTimeEnd').val() || '').trim();
    var roomVal = ($('#afRoom').val() || '').trim();
    var missing = [];
    if (!course_id) missing.push('Course');
    if (!sessionVal) missing.push('Academic session');
    if (!degreeVal) missing.push('Degree');
    if (!sectionVal) missing.push('Section');
    if (!dayVal) missing.push('Preferred day');
    if (!faculty_id) missing.push('Faculty');
    if (!startVal) missing.push('Preferred start');
    if (!endVal) missing.push('Preferred end');
    if (!roomVal) missing.push('Room');
    if (missing.length) {
        showToast('warning', 'Please fill required fields: ' + missing.join(', ') + '.');
        return;
    }
    if (startVal >= endVal) {
        showToast('warning', 'Preferred end time must be after preferred start time.');
        return;
    }
    var payload = {
        course_id: course_id,
        faculty_id: faculty_id,
        academic_session_id: parseInt(sessionVal, 10),
        degree_id: parseInt(degreeVal, 10),
        section: sectionVal,
        preferred_day_of_week: parseInt(dayVal, 10),
        preferred_start_time: startVal,
        preferred_end_time: endVal,
        room_id: parseInt(roomVal, 10)
    };
    var editId = ($('#afEditingAssignmentId').val() || '').trim();
    var method = editId ? 'PUT' : 'POST';
    if (editId) {
        payload.assignment_id = parseInt(editId, 10);
    }
    $.ajax({
        url: base + 'actions/course_faculty.php',
        method: method,
        contentType: 'application/json',
        data: JSON.stringify(payload)
    }).done(function(r) {
        if (r.success) {
            clearAfEditMode();
            refreshAssignFacultyModal();
            loadCourses();
            showToast('success', r.message || (editId ? 'Saved.' : 'Faculty assigned.'));
        } else showToast('error', r.message || 'Error');
    }).fail(function(x) {
        showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.');
    });
});

$('#afCancelEditBtn').on('click', function() {
    clearAfEditMode();
    $('#afSession, #afDegree, #afRoom').val('');
    $('#afSection').val('');
    $('#afDay').val('');
    $('#afFaculty').val('');
    $('#afTimeStart, #afTimeEnd').val('');
});

$(function() {
    loadDepartments();
    loadCourseFilterOptions();
    loadCourses();
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
