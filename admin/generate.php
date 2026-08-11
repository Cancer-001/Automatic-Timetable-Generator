<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Generate Timetable';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Generate Timetable</h2>
    <div class="alert alert-info small mb-3">
        <strong>How it works:</strong> An <strong>Academic Session</strong> (e.g. Fall 2026) is a time period — set its Start/End date in <a href="sessions.php">Sessions</a>. Choose session, semester and section then click <strong>Generate</strong>. Select specific courses below or leave all unchecked to schedule every course in that semester.
        <span class="d-block mt-1">Session names do not restrict semester generation. Pick the semester you need for that session.</span>
        <span class="d-block mt-1">Each active course is given <strong>three</strong> distinct instructors in the database; generating <strong>section A</strong>, <strong>B</strong>, or <strong>C</strong> prefers a different lead instructor so parallel sections are less likely to fight for the same person at the same time.</span>
        <span class="d-block mt-1">Optional <strong>Assign Faculty</strong> entries (same academic session + section as above) can narrow <strong>preferred times</strong> and <strong>rooms</strong> per instructor; if you leave them empty, the system still finds conflict-free slots automatically.</span>
        <span class="d-block mt-1">Each successful <strong>Generate</strong> (with default faculty sync) also writes default Session, Section (A/B/C per instructor), first lecture time window, first room, and degree (if configured) into <strong>Assign Faculty</strong> so those columns are not blank in Courses.</span>
        <span class="d-block mt-1"><strong>Merged lecture:</strong> One teacher, one room, one time slot, multiple semester/section cohorts together. Room capacity is checked against the sum of students (enrolled in the course per cohort, or cohort size). The timetable generator treats all listed cohorts as busy in that slot.</span>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Academic Session</label>
                    <input type="hidden" id="genSession" value="">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle w-100 filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="genSessionBtn">-- Select --</button>
                        <div class="dropdown-menu p-2 w-100">
                            <input type="text" id="genSessionSearch" class="form-control form-control-sm mb-2" placeholder="Search session">
                            <div id="genSessionList" style="max-height:220px;overflow:auto;"></div>
                        </div>
                    </div>
                    <small class="text-muted">Start/End dates are set in <a href="sessions.php">Sessions</a></small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Degree</label>
                    <select id="genDegree" class="form-select">
                        <option value="">-- Select degree --</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Semester</label>
                    <input type="number" id="genSemester" class="form-control" value="1" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Section</label>
                    <input type="text" id="genSection" class="form-control" value="A" placeholder="A">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" id="genClear" class="form-check-input" checked>
                        <label class="form-check-label" for="genClear">Clear existing first</label>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary" id="genBtn">Generate</button>
                </div>
                <div class="col-md-12">
                    <div class="form-check mt-2">
                        <input type="checkbox" id="genAutoAssignFaculty" class="form-check-input">
                        <label class="form-check-label small" for="genAutoAssignFaculty">Auto-assign missing faculty links during generate (optional)</label>
                    </div>
                </div>
            </div>
            <div class="mt-3" id="genCourseChoiceWrap">
                <label class="form-label">Courses to include (for the semester above)</label>
                <p class="text-muted small mb-1">Select which courses to schedule. If none selected, all courses for this semester are used.</p>
                <div id="genCourseList" class="border rounded p-2 bg-light" style="max-height:220px;overflow-y:auto;"></div>
            </div>
            <div id="genResult" class="mt-2"></div>
        </div>
    </div>

    <div class="card mb-4 border-primary border-opacity-25">
        <div class="card-body">
            <h5 class="card-title">Merged lecture (shared slot)</h5>
            <p class="text-muted small">Uses the same <strong>Academic Session</strong> as above. Add at least two cohorts (e.g. Sec A + Sec B). Primary row on the timetable is the first cohort after sorting (semester, then section). Choose <strong>day</strong> (Mon–Sun) and <strong>start / end time</strong> so they match a row in your <code>time_slot</code> table. Optional <strong>date</strong> updates the day when you pick a calendar day.</p>
            <div class="row g-2 align-items-end">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">Course</label>
                    <select id="mergeCourse" class="form-select"><option value="">— Select course —</option></select>
                </div>
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">Faculty</label>
                    <select id="mergeFaculty" class="form-select"><option value="">— Select faculty —</option></select>
                </div>
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">Room</label>
                    <select id="mergeRoom" class="form-select"><option value="">— Select room —</option></select>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="form-label">Day</label>
                    <select id="mergeDayOfWeek" class="form-select">
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                        <option value="7">Sunday</option>
                    </select>
                    <small class="text-muted">Same as DB: 1=Mon … 7=Sun.</small>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="form-label">Date <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" id="mergeDate" class="form-control">
                    <small class="text-muted">Changing this sets Day to that weekday.</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Time slot</label>
                    <select id="mergeSlotPick" class="form-select" style="max-width:720px">
                        <option value="">— Load slots or choose day —</option>
                    </select>
                    <small class="text-muted d-block">Pick a defined slot for this day (08:00–15:00 only). Matches your <strong>Time slots</strong> table — no free-typed times.</small>
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label">Cohorts (semester + section)</label>
                <table class="table table-sm table-bordered align-middle mb-2" style="max-width:520px">
                    <thead class="table-light"><tr><th>Semester</th><th>Section</th><th style="width:90px"></th></tr></thead>
                    <tbody id="mergeCohortsBody"></tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="mergeAddCohort">+ Add cohort</button>
                <button type="button" class="btn btn-sm btn-primary" id="mergeSubmit">Create merged lecture</button>
            </div>
            <div id="mergeResult" class="mt-2 small"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Generated Timetable</h5>
                    <p class="text-muted small mb-0">View the schedule below. Use <strong>Move</strong> to manually adjust a class. Export using the buttons on the right.</p>
                    <p class="text-muted small mb-0" id="viewGeneratedMeta"></p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="hidden" id="viewSession" value="">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="viewSessionBtn" style="min-width:220px">-- Select session --</button>
                        <div class="dropdown-menu p-2" style="min-width:280px;">
                            <input type="text" id="viewSessionSearch" class="form-control form-control-sm mb-2" placeholder="Search session">
                            <div id="viewSessionList" style="max-height:220px;overflow:auto;"></div>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="viewBatchBtn" style="min-width:250px">-- Select generated batch --</button>
                        <div class="dropdown-menu p-2" style="min-width:320px;">
                            <input type="text" id="viewBatchSearch" class="form-control form-control-sm mb-2" placeholder="Search semester/section">
                            <div id="viewBatchList" style="max-height:220px;overflow:auto;"></div>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="viewProgramBtn" style="min-width:170px">All Programs</button>
                        <div class="dropdown-menu p-2" style="min-width:220px;">
                            <div id="viewProgramList" style="max-height:220px;overflow:auto;"></div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-success" id="adminExportCsv">Export CSV</button>
                    <button class="btn btn-sm btn-danger" id="adminExportPdf">Print / PDF</button>
                </div>
            </div>

            <div class="table-responsive" id="scheduleTableWrap">
                <p class="text-muted">Select a session to view the timetable.</p>
            </div>

            <!-- Move modal -->
            <div id="moveModal" class="modal fade" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Move / Swap Class (conflict check)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <input type="hidden" id="moveScheduleId">
                            <input type="hidden" id="moveCourseId">
                            <div id="moveConflict" class="alert alert-danger d-none"></div>
                            <div class="mb-2"><label class="form-label">Swap Faculty (optional)</label><select id="moveFaculty" class="form-select"><option value="">-- Keep current --</option></select></div>
                            <div class="mb-2"><label class="form-label">New Room</label><select id="moveRoom" class="form-select"><option value="">-- Keep current room --</option></select></div>
                            <div class="mb-2"><label class="form-label">Custom date <span class="text-muted fw-normal">(optional)</span></label><input type="date" id="moveCustomDate" class="form-control"></div>
                            <div class="mb-2"><label class="form-label">Custom day</label><select id="moveCustomDay" class="form-select">
                                <option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="7">Sunday</option>
                            </select><small class="text-muted">Used when date is empty; if date is set, weekday comes from the date.</small></div>
                            <div class="mb-2"><label class="form-label">Custom time</label><div class="row g-2"><div class="col-6"><input type="time" id="moveTimeStart" class="form-control" step="60" placeholder="Start"></div><div class="col-6"><input type="time" id="moveTimeEnd" class="form-control" step="60" placeholder="End"></div></div><small class="text-muted">Start and end must match a row in <strong>Time slots</strong> for that day (including seconds in DB).</small></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="moveConfirm">Confirm Move</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var mergeAllSlots = [];
/** Time slots loaded when opening Move modal — used to map custom day/time → time_slot_id */
var moveModalSlots = [];
/** Minutes from midnight; window for merged lectures (08:00–15:00 inclusive end boundary). */
var MERGE_SLOT_WINDOW_START_MIN = 8 * 60;
var MERGE_SLOT_WINDOW_END_MIN = 15 * 60;

function mergeSlotMinutesFromDb(t) {
    var parts = String(t || '00:00:00').split(':');
    var h = parseInt(parts[0], 10) || 0;
    var m = parseInt(parts[1], 10) || 0;
    return h * 60 + m;
}

function mergeSlotWithinBusinessWindow(startTime, endTime) {
    var sm = mergeSlotMinutesFromDb(startTime);
    var em = mergeSlotMinutesFromDb(endTime);
    return sm >= MERGE_SLOT_WINDOW_START_MIN && em <= MERGE_SLOT_WINDOW_END_MIN;
}

function mergeRefreshSlotPickOptions() {
    var dow = parseInt($('#mergeDayOfWeek').val(), 10) || 1;
    var $sel = $('#mergeSlotPick');
    var prev = $sel.val();
    $sel.empty().append($('<option>').val('').text('— Select time slot —'));
    var arr = (mergeAllSlots || []).filter(function(x) {
        return parseInt(x.day_of_week, 10) === dow && mergeSlotWithinBusinessWindow(x.start_time, x.end_time);
    });
    arr.sort(function(a, b) {
        var c = String(a.start_time || '').localeCompare(String(b.start_time || ''));
        if (c) return c;
        return String(a.slot_type || '').localeCompare(String(b.slot_type || ''));
    });
    arr.forEach(function(x) {
        var st = mergeNormalizeDbTime(x.start_time).substring(0, 5);
        var en = mergeNormalizeDbTime(x.end_time).substring(0, 5);
        var lab = (x.slot_type || '') === 'lab' ? ' (lab)' : '';
        var lbl = (x.slot_label ? x.slot_label + ' — ' : '') + st + '–' + en + lab;
        $sel.append($('<option>').val(String(x.id)).text(lbl));
    });
    if (prev) {
        var hasPrev = false;
        $sel.find('option').each(function () {
            if ($(this).val() === String(prev)) { hasPrev = true; return false; }
        });
        if (hasPrev) $sel.val(prev);
    }
}

function mergeFormatDateLocal(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
}

/** JS Date Sunday=0..Saturday=6 → DB time_slot.day_of_week (1=Mon .. 7=Sun) */
function mergeJsDayToDbDow(jsDay) {
    return jsDay === 0 ? 7 : jsDay;
}

/** 'YYYY-MM-DD' → DB day_of_week or null */
function mergeDateStrToDbDow(dateStr) {
    if (!dateStr) return null;
    var d = new Date(dateStr + 'T12:00:00');
    if (isNaN(d.getTime())) return null;
    return mergeJsDayToDbDow(d.getDay());
}

/** Normalize DB time string to HH:MM:SS for comparison */
function mergeNormalizeDbTime(t) {
    if (!t) return '';
    var parts = String(t).split(':');
    var h = parseInt(parts[0], 10) || 0;
    var m = parseInt(parts[1], 10) || 0;
    var s = parseInt(parts[2], 10) || 0;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
}

function moveTimeInputToHms(val) {
    if (!val) return '';
    var p = String(val).split(':');
    var h = parseInt(p[0], 10) || 0;
    var m = parseInt(p[1], 10) || 0;
    var sec = parseInt(p[2], 10) || 0;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
}

/** Resolve custom weekday + HTML time inputs to time_slot.id (lab vs lecture tie-break when duplicated). */
function moveFindTimeSlotId(dow, startInput, endInput, preferLab) {
    var ns = moveTimeInputToHms(startInput);
    var ne = moveTimeInputToHms(endInput);
    if (!ns || !ne) {
        return { id: 0, message: 'Enter both start and end time under Custom time.' };
    }
    var candidates = (moveModalSlots || []).filter(function (x) {
        return parseInt(x.day_of_week, 10) === dow
            && mergeNormalizeDbTime(x.start_time) === ns
            && mergeNormalizeDbTime(x.end_time) === ne;
    });
    if (!candidates.length) {
        return {
            id: 0,
            message: 'No time slot matches this weekday and time. Use the exact start/end from your Time slots for that day.'
        };
    }
    if (candidates.length === 1) {
        return { id: parseInt(candidates[0].id, 10) };
    }
    var lect = candidates.filter(function (c) { return (c.slot_type || '') !== 'lab'; });
    var labs = candidates.filter(function (c) { return (c.slot_type || '') === 'lab'; });
    if (preferLab && labs.length) {
        return { id: parseInt(labs[0].id, 10) };
    }
    if (!preferLab && lect.length) {
        return { id: parseInt(lect[0].id, 10) };
    }
    return { id: parseInt(candidates[0].id, 10) };
}

/* ── shared timetable renderer with student conflict detection ── */
function detectStudentConflicts(list) {
    var bad = {};
    function mins(t) {
        var p = String(t || '00:00:00').split(':');
        return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
    }
    list.forEach(function(a, i) {
        for (var j = i + 1; j < list.length; j++) {
            var b = list[j];
            if (parseInt(a.semester, 10) !== parseInt(b.semester, 10)) continue;
            if (String(a.section || '').toLowerCase() !== String(b.section || '').toLowerCase()) continue;
            if (parseInt(a.day_of_week, 10) !== parseInt(b.day_of_week, 10)) continue;
            if (mins(a.start_time) < mins(b.end_time) && mins(a.end_time) > mins(b.start_time)) {
                bad[i] = true;
                bad[j] = true;
            }
        }
    });
    return bad;
}

function renderTimetableTable(list, showMoveBtn) {
    if (!list || !list.length) return '<p class="text-muted py-2">No records found.</p>';
    var listFiltered = list;
    if (viewProgramFilter) {
        listFiltered = list.filter(function(r) {
            var program = String(r.program_name || '').trim();
            return program === viewProgramFilter;
        });
    }
    if (!listFiltered.length) return '<p class="text-muted py-2">No records found for selected program.</p>';
    listFiltered.sort(function(a,b){
        var sd = (a.semester||0)-(b.semester||0); if(sd) return sd;
        var sc = (a.section||'').localeCompare(b.section||''); if(sc) return sc;
        var dd = (a.day_of_week||0)-(b.day_of_week||0); if(dd) return dd;
        return (a.start_time||'').localeCompare(b.start_time||'');
    });
    var conflicts = detectStudentConflicts(listFiltered);
    var conflictCount = Object.keys(conflicts).length;
    var banner = '';
    if (conflictCount > 0) {
        banner = '<div class="alert alert-danger mb-2"><strong>⚠ ' + conflictCount + ' Student Conflict(s) Detected</strong> — highlighted rows below show students with two classes at the same time. Use <strong>Move</strong> to fix.</div>';
    }
    var html = banner + '<table class="table table-bordered table-hover table-sm tt-table" style="min-width:1100px">';
    html += '<thead class="tt-thead"><tr>'
        + '<th>Program</th><th>Department</th><th>Semester</th><th>Section</th><th>Session</th>'
        + '<th>CHT</th><th>CHL</th><th>Course Title</th>'
        + '<th>Teacher Name</th><th>Duration</th><th>Day</th><th>Time</th><th>Room</th><th>Remarks</th>'
        + (showMoveBtn ? '<th>Action</th>' : '')
        + '</tr></thead><tbody>';
    listFiltered.forEach(function(r, idx) {
        var deptCode = (r.department_code || '').toUpperCase();
        var program  = String(r.program_name || '').trim() || 'N/A';
        var deptName = r.department_name || (deptCode || '—');
        var isConflict = !!conflicts[idx];
        var rowStyle = isConflict ? ' style="background:#fff1f1;border-left:4px solid #ef4444"' : '';
        var conflictBadge = isConflict ? '<span style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;margin-left:6px">CONFLICT</span>' : '';
        var mergedBadge = (parseInt(r.is_merged_lecture, 10) === 1) ? '<span class="badge bg-info text-dark ms-1" title="Multiple sections share this slot">Merged</span>' : '';
        var extraCohorts = (r.merge_extra_cohorts || '').trim();
        var sectionExtras = extraCohorts
            ? '<br><small class="text-muted fw-normal" title="Additional merged cohorts">+ ' + escapeHtml(extraCohorts) + '</small>'
            : '';
        html += '<tr' + rowStyle + '>'
            + '<td><span class="badge bg-primary">' + escapeHtml(program) + '</span></td>'
            + '<td>' + escapeHtml(deptName) + '</td>'
            + '<td class="text-center">' + (r.semester || '') + '</td>'
            + '<td class="text-center"><span class="badge bg-success">' + escapeHtml(r.section || '') + '</span>' + sectionExtras + '</td>'
            + '<td>' + escapeHtml(r.session_name || '') + '</td>'
            + '<td class="text-center">' + (r.cht != null ? r.cht : '') + '</td>'
            + '<td class="text-center">' + (r.chl != null ? r.chl : '') + '</td>'
            + '<td>' + escapeHtml(r.course_name || '') + conflictBadge + mergedBadge + '</td>'
            + '<td>' + escapeHtml(r.faculty_name || '') + '</td>'
            + '<td class="text-center">' + (r.duration != null ? r.duration : '') + '</td>'
            + '<td>' + escapeHtml(r.day_name || '') + '</td>'
            + '<td style="white-space:nowrap">' + escapeHtml(r.time_range || '') + '</td>'
            + '<td>' + escapeHtml(r.room_number || '') + '</td>'
            + '<td></td>'
            + (showMoveBtn ? '<td><button class="btn btn-xs btn-outline-primary move-row" style="font-size:11px;padding:2px 8px" data-id="'+r.id+'" data-course="'+r.course_id+'" data-faculty="'+r.faculty_id+'" data-room="'+r.room_id+'" data-slot="'+r.time_slot_id+'" data-chl="'+(r.chl != null ? r.chl : 0)+'">Move / Swap</button></td>' : '')
            + '</tr>';
    });
    html += '</tbody></table>';
    return html;
}
/** Label format matches schedule.php / Program CRUD: "CODE - Name" */
function crudDegreeProgramLabel(d) {
    if (!d) return '';
    var code = String(d.code || '').trim();
    var name = String(d.name || '').trim();
    if (!code && !name) return '';
    return (code + (name ? (' - ' + name) : '')).trim();
}
var viewDegreeProgramLabels = [];
var lastScheduleListForView = [];
function loadViewDegreePrograms(done) {
    $.get(base + 'actions/degrees.php').done(function(data) {
        var rows = Array.isArray(data) ? data : (data && Array.isArray(data.items) ? data.items : []);
        var map = {};
        rows.forEach(function(d) {
            var label = crudDegreeProgramLabel(d);
            if (label) map[label] = true;
        });
        viewDegreeProgramLabels = Object.keys(map).sort();
        if (typeof done === 'function') done();
        refreshProgramFilterOptions(lastScheduleListForView);
    }).fail(function() {
        viewDegreeProgramLabels = [];
        if (typeof done === 'function') done();
        refreshProgramFilterOptions(lastScheduleListForView);
    });
}
function setProgramSelection(program, triggerLoad) {
    viewProgramFilter = String(program || '').trim();
    $('#viewProgramBtn').text(viewProgramFilter || 'All Programs');
    if (triggerLoad) {
        loadScheduleView();
    }
}
function renderProgramList(programs) {
    var $list = $('#viewProgramList').empty();
    var arr = Array.isArray(programs) ? programs : [];
    var allActive = !viewProgramFilter;
    var $allBtn = $('<button type="button" class="dropdown-item program-option"></button>');
    $allBtn.toggleClass('active', allActive);
    $allBtn.attr('data-program', '');
    $allBtn.text('All Programs');
    $list.append($allBtn);
    if (!arr.length) {
        return;
    }
    arr.forEach(function(p) {
        var active = String(viewProgramFilter).toUpperCase() === String(p).toUpperCase();
        var $btn = $('<button type="button" class="dropdown-item program-option"></button>');
        $btn.toggleClass('active', active);
        $btn.attr('data-program', p);
        $btn.text(p);
        $list.append($btn);
    });
}
function refreshProgramFilterOptions(list) {
    var keep = String(viewProgramFilter || '');
    var map = {};
    viewDegreeProgramLabels.forEach(function(p) {
        if (p) map[p] = true;
    });
    (list || []).forEach(function(r) {
        var program = String(r.program_name || '').trim();
        if (!program) return;
        map[program] = true;
    });
    var programs = Object.keys(map).sort();
    if (keep && programs.indexOf(keep) !== -1) {
        viewProgramFilter = keep;
    } else {
        viewProgramFilter = '';
    }
    $('#viewProgramBtn').text(viewProgramFilter || 'All Programs');
    renderProgramList(programs);
}

var sessionSearchTimers = { gen: null, view: null };
var batchSearchTimer = null;
var viewBatchItems = [];
var viewGeneratedSessions = [];
var viewSemesterFilter = 0;
var viewSectionFilter = '';
var viewProgramFilter = '';

function loadGenerateDegrees() {
    $.get(base + 'actions/degrees.php').done(function(data) {
        var rows = Array.isArray(data) ? data : (data && Array.isArray(data.items) ? data.items : []);
        var $sel = $('#genDegree').empty().append('<option value="">-- Select degree --</option>');
        rows.forEach(function(d) {
            var label = crudDegreeProgramLabel(d);
            if (!label) return;
            $sel.append($('<option>').val(d.id).text(label));
        });
        if (rows.length === 1) {
            $sel.val(String(rows[0].id));
        }
        loadCoursesForSemester();
    });
}

function setSessionSelection(kind, id, name, triggerLoad) {
    if (kind === 'gen') {
        $('#genSession').val(id ? String(id) : '');
        $('#genSessionBtn').text(name || '-- Select --');
        return;
    }
    $('#viewSession').val(id ? String(id) : '');
    $('#viewSessionBtn').text(name || '-- Select session --');
    if (triggerLoad) {
        // For generated timetable view, user selects a recent generated batch (sem/sec) from dropdown.
        viewSemesterFilter = 0;
        viewSectionFilter = '';
        loadRecentBatchesForSession(true);
    }
}
function renderSessionList(kind, list) {
    var listId = kind === 'gen' ? '#genSessionList' : '#viewSessionList';
    var selected = kind === 'gen' ? ($('#genSession').val() || '') : ($('#viewSession').val() || '');
    var $list = $(listId).empty();
    var arr = Array.isArray(list) ? list : [];
    if (!arr.length) {
        $list.html('<div class="text-muted small">No records found.</div>');
        return;
    }
    arr.forEach(function(s) {
        var sid = String(s.id || '');
        var active = selected && sid === String(selected);
        var $btn = $('<button type="button" class="dropdown-item session-option"></button>');
        $btn.toggleClass('active', !!active);
        $btn.text(s.name || ('Session #' + sid));
        $btn.attr('data-kind', kind).attr('data-id', sid).attr('data-name', s.name || ('Session #' + sid));
        $list.append($btn);
    });
}
function formatGeneratedAt12h(ts) {
    if (!ts) return '';
    var d = new Date(String(ts).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(ts);
    var mo = d.toLocaleString('en-US', { month: 'short' });
    var day = d.getDate();
    var yr = d.getFullYear();
    var hrs = d.getHours();
    var mins = String(d.getMinutes()).padStart(2, '0');
    var ap = hrs >= 12 ? 'PM' : 'AM';
    var h12 = hrs % 12; if (h12 === 0) h12 = 12;
    return mo + ' ' + day + ', ' + yr + ' ' + h12 + ':' + mins + ' ' + ap;
}
function renderBatchList(items, q) {
    var $list = $('#viewBatchList').empty();
    var query = String(q || '').trim().toLowerCase();
    var arr = (Array.isArray(items) ? items : []).filter(function(x) {
        var txt = 'sem ' + (x.semester || '') + ' sec ' + (x.section || '');
        return !query || txt.toLowerCase().indexOf(query) !== -1;
    });
    if (!arr.length) {
        $list.html('<div class="text-muted small">No generated batches found.</div>');
        return;
    }
    arr.forEach(function(x, idx) {
        var generated = formatGeneratedAt12h(x.generated_at || '');
        var label = 'Sem ' + x.semester + ' / Sec ' + x.section + ' \u2014 ' + (x.total_rows || 0) + ' rows';
        var sub = generated ? 'Generated: ' + generated : '';
        var active = (viewSemesterFilter === parseInt(x.semester, 10) && String(viewSectionFilter).toLowerCase() === String(x.section || '').toLowerCase());
        var $btn = $('<button type="button" class="dropdown-item cohort-option"></button>');
        $btn.toggleClass('active', !!active);
        $btn.attr('data-sem', String(x.semester || ''));
        $btn.attr('data-sec', String(x.section || ''));
        $btn.attr('data-generated', String(x.generated_at || ''));
        $btn.attr('data-rows', String(x.total_rows || 0));
        var subClass = active ? 'text-white-50' : 'text-muted';
        $btn.html('<div>' + escapeHtml(label) + '</div>' + (sub ? '<small class="' + subClass + '">' + escapeHtml(sub) + '</small>' : ''));
        $list.append($btn);
        if (idx === 0 && !viewSemesterFilter && !viewSectionFilter) {
            // keep first option visually discoverable when no selection exists
            $('#viewBatchBtn').text('-- Select generated batch --');
        }
    });
}
function setBatchSelection(sem, sec, generatedAt, rows, triggerLoad) {
    viewSemesterFilter = parseInt(sem, 10) || 0;
    viewSectionFilter = String(sec || '').trim();
    var label = viewSemesterFilter > 0 ? ('Sem ' + viewSemesterFilter + ' / Sec ' + viewSectionFilter) : '-- Select generated batch --';
    $('#viewBatchBtn').text(label);
    if (generatedAt) {
        $('#viewGeneratedMeta').text('Generated: ' + formatGeneratedAt12h(generatedAt) + (rows ? (' \u2022 Rows: ' + rows) : ''));
    } else {
        $('#viewGeneratedMeta').text('');
    }
    if (triggerLoad && $('#viewSession').val() && viewSemesterFilter > 0 && viewSectionFilter) {
        loadScheduleView();
    }
}
function loadRecentBatchesForSession(autoSelectFirst) {
    var sid = $('#viewSession').val();
    viewBatchItems = [];
    $('#viewBatchList').empty();
    $('#viewBatchBtn').text('-- Select generated batch --');
    if (!sid) return;
    $.get(base + 'actions/schedule.php', { academic_session_id: sid, batch_list: 1 }).done(function(resp) {
        var list = (resp && resp.list) ? resp.list : [];
        viewBatchItems = Array.isArray(list) ? list : [];
        renderBatchList(viewBatchItems, $('#viewBatchSearch').val() || '');
        if (!viewBatchItems.length) {
            $('#viewGeneratedMeta').text('');
            $('#scheduleTableWrap').html('<p class="text-muted">No generated timetable found for this session.</p>');
            return;
        }
        if (autoSelectFirst) {
            var top = viewBatchItems[0];
            setBatchSelection(top.semester, top.section, top.generated_at, top.total_rows, true);
            renderBatchList(viewBatchItems, $('#viewBatchSearch').val() || '');
        }
    });
}
function getSessionDisplayNameById(sessionId) {
    var sid = String(sessionId || '');
    var name = '';
    $('#genSessionList .session-option').each(function() {
        var $opt = $(this);
        if (String($opt.data('id')) === sid) {
            name = String($opt.data('name') || '');
            return false;
        }
    });
    if (!name) {
        var btnTxt = String($('#genSessionBtn').text() || '').trim();
        if (btnTxt && btnTxt !== '-- Select --') name = btnTxt;
    }
    return name;
}
function loadSessionDropdown(kind, q) {
    var query = String(q || '').trim().toLowerCase();
    if (kind === 'view') {
        $.get(base + 'actions/schedule.php', { generated_sessions: 1 }).done(function(resp) {
            var list = (resp && resp.list) ? resp.list : [];
            viewGeneratedSessions = Array.isArray(list) ? list : [];
            var filtered = viewGeneratedSessions;
            if (query) {
                filtered = viewGeneratedSessions.filter(function(x) {
                    var nm = String(x.name || '').toLowerCase();
                    return nm.indexOf(query) !== -1;
                });
            }
            renderSessionList(kind, filtered);
        });
        return;
    }
    $.get(base + 'actions/sessions.php', { q: (q || '').trim() }).done(function(list) {
        renderSessionList(kind, list);
    });
}
function loadCoursesForSemester() {
    var sem = parseInt($('#genSemester').val(), 10) || 1;
    var degreeId = parseInt($('#genDegree').val(), 10) || 0;
    var degreeText = ($('#genDegree option:selected').text() || '').trim();
    var params = { semester: sem };
    if (degreeId > 0) params.degree_id = degreeId;
    $.get(base + 'actions/courses.php', params).done(function(list) {
        var arr = Array.isArray(list) ? list : [];
        var forSem = arr.filter(function(c) { return parseInt(c.semester, 10) === sem; });
        var $wrap = $('#genCourseList').empty();
        if (!forSem.length) {
            var label = degreeId > 0 ? (' for ' + escapeHtml(degreeText)) : '';
            $wrap.html('<span class="text-muted">No courses' + label + ' in semester ' + sem + '. Add courses in <a href="courses.php">Courses</a> or add matching Assign Faculty rows.</span>');
            return;
        }
        var html = '';
        html += '<table class="table table-sm table-hover align-middle mb-0">';
        html += '<thead class="table-light"><tr>';
        html += '<th style="width:40px"><input type="checkbox" id="genCourseSelectAll"></th>';
        html += '<th>Subject</th><th>Department</th><th>Program</th>';
        html += '</tr></thead><tbody>';
        forSem.forEach(function(c) {
            var deptName = c.department_name || (c.department_code ? String(c.department_code).toUpperCase() : '—');
            var deptCode = (c.department_code || '').toUpperCase();
            var program = degreeId > 0 && degreeText ? degreeText : (deptCode ? ('BS' + deptCode) : '—');
            html += '<tr>';
            html += '<td><input type="checkbox" class="form-check-input gen-course-cb" value="' + c.id + '"></td>';
            html += '<td><strong>' + escapeHtml(c.code || '') + ' – ' + escapeHtml(c.name || '') + '</strong></td>';
            html += '<td>' + escapeHtml(deptName) + '</td>';
            html += '<td><span class="badge bg-primary-subtle text-primary-emphasis border">' + escapeHtml(program) + '</span></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        $wrap.html(html);
    });
}
$('#genSemester, #genDegree').on('change', loadCoursesForSemester);

$(document).on('change', '#genCourseSelectAll', function() {
    var checked = $(this).is(':checked');
    $('#genCourseList input.gen-course-cb').prop('checked', checked);
});
$(document).on('change', '#genCourseList input.gen-course-cb', function() {
    var $all = $('#genCourseList input.gen-course-cb');
    if (!$all.length) return;
    var checkedCount = $('#genCourseList input.gen-course-cb:checked').length;
    $('#genCourseSelectAll').prop('checked', checkedCount === $all.length);
});

$('#genBtn').on('click', function() {
    var session_id = $('#genSession').val();
    if (!session_id) { $('#genResult').html('<span class="text-danger">Select a session.</span>'); return; }
    var degree_id = parseInt($('#genDegree').val(), 10) || 0;
    if (!degree_id) { $('#genResult').html('<span class="text-danger">Select a degree.</span>'); return; }
    var semNum = parseInt($('#genSemester').val(), 10) || 1;
    var course_ids = [];
    $('#genCourseList input.gen-course-cb:checked').each(function() { course_ids.push(parseInt($(this).val(), 10)); });
    $('#genResult').html('<span class="text-info">Generating…</span>');
    var payload = {
        academic_session_id: parseInt(session_id, 10),
        degree_id: degree_id,
        semester: semNum,
        section: $('#genSection').val() || 'A',
        clear_first: $('#genClear').is(':checked'),
        auto_assign_missing_faculty: $('#genAutoAssignFaculty').is(':checked')
    };
    if (course_ids.length > 0) payload.course_ids = course_ids;
    $.ajax({ url: base + 'actions/generate.php', method: 'POST', dataType: 'json', contentType: 'application/json', data: JSON.stringify(payload) })
        .done(function(r) {
            if (r.success) {
                var cls = r.status === 'partial' ? 'text-warning' : 'text-success';
                var details = '';
                if (Array.isArray(r.unscheduled) && r.unscheduled.length) {
                    details = '<ul class="mb-0 mt-1">' + r.unscheduled.slice(0, 12).map(function(x) {
                        return '<li>' + escapeHtml((x.course_code || 'Course') + ' (' + (x.component || 'item') + '): ' + (x.reason || 'Could not schedule.')) + '</li>';
                    }).join('') + (r.unscheduled.length > 12 ? '<li>+' + (r.unscheduled.length - 12) + ' more...</li>' : '') + '</ul>';
                }
                $('#genResult').html('<div class="' + cls + '">' + escapeHtml(r.message) + details + '</div>');
                showToast(r.status === 'partial' ? 'warning' : 'success', r.message);
                // Auto-set view session and reload
                setSessionSelection('view', session_id, $('#genSessionBtn').text(), true);
                // Prefer opening the exact newly generated batch in the view dropdown.
                setBatchSelection(payload.semester, payload.section, '', 0, false);
                loadRecentBatchesForSession(false);
                loadScheduleView();
            } else {
                $('#genResult').html('<span class="text-danger">' + escapeHtml(r.message || 'Error') + '</span>');
                showToast('error', r.message || 'Error');
            }
        })
        .fail(function(x) {
            var msg = '';
            if (x.responseJSON && x.responseJSON.message) {
                msg = x.responseJSON.message;
            } else if (x.status === 200 && x.responseText) {
                msg = 'Server returned HTTP 200 with invalid JSON. Please check PHP warnings/output in actions/generate.php.';
            } else {
                msg = 'Request failed. HTTP ' + x.status;
            }
            $('#genResult').html('<span class="text-danger">' + escapeHtml(msg) + '</span>');
            showToast('error', msg);
        });
});

function loadScheduleView() {
    var sid = $('#viewSession').val();
    if (!sid) { $('#scheduleTableWrap').html('<p class="text-muted">Select a session to view the timetable.</p>'); return; }
    var sem = parseInt(viewSemesterFilter, 10) || 0;
    var sec = (viewSectionFilter || '').trim();
    if (sem <= 0 || sec === '') {
        $('#scheduleTableWrap').html('<p class="text-muted">Select a generated batch (semester/section).</p>');
        return;
    }
    $('#scheduleTableWrap').html('<p class="text-muted">Loading…</p>');
    var qs = '?academic_session_id=' + encodeURIComponent(sid);
    if (sem > 0) qs += '&semester=' + encodeURIComponent(sem);
    if (sec !== '') qs += '&section=' + encodeURIComponent(sec);
    $.get(base + 'actions/schedule.php' + qs).done(function(data) {
        var list = (data && data.list) ? data.list : (Array.isArray(data) ? data : []);
        lastScheduleListForView = list;
        refreshProgramFilterOptions(list);
        $('#scheduleTableWrap').html('<div class="table-responsive">' + renderTimetableTable(list, true) + '</div>');
    }).fail(function() {
        $('#scheduleTableWrap').html('<p class="text-danger">Failed to load schedule.</p>');
    });
}
$(document).on('click', '.program-option', function() {
    var p = String($(this).data('program') || '');
    setProgramSelection(p, true);
    $('#viewProgramList .program-option').removeClass('active');
    $(this).addClass('active');
});
$(document).on('click', '.session-option', function() {
    var kind = $(this).data('kind');
    var id = $(this).data('id');
    var name = $(this).data('name');
    setSessionSelection(kind, id, name, kind === 'view');
    loadSessionDropdown(kind, kind === 'gen' ? ($('#genSessionSearch').val() || '') : ($('#viewSessionSearch').val() || ''));
});
$(document).on('click', '.cohort-option', function() {
    var sem = $(this).data('sem');
    var sec = $(this).data('sec');
    var generated = $(this).data('generated');
    var rows = $(this).data('rows');
    setBatchSelection(sem, sec, generated, rows, true);
    renderBatchList(viewBatchItems, $('#viewBatchSearch').val() || '');
});

// Export buttons
$('#adminExportCsv').on('click', function() {
    var sid = $('#viewSession').val();
    if (!sid) { showToast('error', 'Select a session first.'); return; }
    window.location.href = base + 'actions/export.php?format=csv&academic_session_id=' + sid;
});
$('#adminExportPdf').on('click', function() {
    var sid = $('#viewSession').val();
    if (!sid) { showToast('error', 'Select a session first.'); return; }
    var w = window.open(base + 'actions/export.php?format=pdf&academic_session_id=' + sid, '_blank', 'width=1100,height=700');
});

function loadRoomsAndSlotsForMove(doneFn) {
    var left = 2;
    function tick() {
        left--;
        if (left === 0 && typeof doneFn === 'function') doneFn();
    }
    $.get(base + 'actions/rooms.php').done(function(list) {
        var $r = $('#moveRoom').find('option:first').nextAll().remove().end();
        (list || []).forEach(function(x) {
            if (x.is_active === 0 || x.is_active === '0') return;
            $r.after($('<option>').val(x.id).text(x.room_number));
        });
    }).always(tick);
    $.get(base + 'actions/time_slots.php').done(function(list) {
        moveModalSlots = Array.isArray(list) ? list : [];
    }).always(tick);
}
function loadFacultyForMove(courseId, currentFacultyId) {
    var $f = $('#moveFaculty').empty().append('<option value="">-- Keep current --</option>');
    var addList = function(list) {
        var seen = {};
        (list || []).forEach(function(x) {
            if (!x || !x.id || seen[x.id]) return;
            seen[x.id] = true;
            var opt = $('<option>').val(x.id).text((x.full_name || x.name || ('Faculty #' + x.id)));
            $f.append(opt);
        });
        if (currentFacultyId) $f.val(String(currentFacultyId));
    };
    $.get(base + 'actions/course_faculty.php', { course_id: courseId, simple: 1 }).done(function(list) {
        if (Array.isArray(list) && list.length) { addList(list); return; }
        $.get(base + 'actions/faculty.php').done(function(all) { addList(all); });
    }).fail(function() {
        $.get(base + 'actions/faculty.php').done(function(all) { addList(all); });
    });
}
$(document).on('click', '.move-row', function() {
    var $btn = $(this);
    var id = $btn.data('id'), courseId = $btn.data('course'), facultyId = $btn.data('faculty'), roomId = $btn.data('room'), slotId = $btn.data('slot');
    $('#moveScheduleId').val(id);
    $('#moveCourseId').val(courseId);
    $('#moveModal').data('preferLab', parseInt($btn.attr('data-chl') || '0', 10) > 0);
    $('#moveConflict').addClass('d-none').text('');
    loadFacultyForMove(courseId, facultyId);
    loadRoomsAndSlotsForMove(function () {
        $('#moveCustomDate').val('');
        var slot = (moveModalSlots || []).filter(function (x) { return String(x.id) === String(slotId); })[0];
        if (slot) {
            $('#moveCustomDay').val(String(slot.day_of_week));
            $('#moveTimeStart').val(String(slot.start_time || '').substring(0, 5));
            $('#moveTimeEnd').val(String(slot.end_time || '').substring(0, 5));
        } else {
            $('#moveCustomDay').val('1');
            $('#moveTimeStart').val('');
            $('#moveTimeEnd').val('');
        }
        $('#moveRoom').val(String(roomId));
        $('#moveModal').modal('show');
    });
});
$('#moveCustomDate').on('change', function () {
    var d = mergeDateStrToDbDow($('#moveCustomDate').val());
    if (d) $('#moveCustomDay').val(String(d));
});
$('#moveConfirm').on('click', function() {
    var sid = $('#moveScheduleId').val(), roomId = $('#moveRoom').val(), facultyId = $('#moveFaculty').val();
    var dateStr = ($('#moveCustomDate').val() || '').trim();
    var dow = dateStr ? mergeDateStrToDbDow(dateStr) : parseInt($('#moveCustomDay').val(), 10);
    if (!dow || dow < 1 || dow > 7) {
        $('#moveConflict').removeClass('d-none').text('Choose a valid custom day, or pick a custom date.');
        return;
    }
    var preferLab = !!$('#moveModal').data('preferLab');
    var slotPick = moveFindTimeSlotId(dow, $('#moveTimeStart').val(), $('#moveTimeEnd').val(), preferLab);
    var slotId = slotPick.id;
    if (!slotId) {
        $('#moveConflict').removeClass('d-none').text(slotPick.message || 'Invalid time slot.');
        return;
    }
    $.ajax({ url: base + 'actions/schedule_move.php', method: 'POST', contentType: 'application/json', data: JSON.stringify({ schedule_id: sid, faculty_id: facultyId || null, room_id: roomId || null, time_slot_id: slotId || null }) })
        .done(function(r) {
            if (r.success) { $('#moveModal').modal('hide'); loadScheduleView(); showToast('success', r.message || 'Schedule updated.'); }
            else { $('#moveConflict').removeClass('d-none').text(r.message || 'Conflict or error.'); showToast('error', r.message || 'Conflict or error.'); }
        })
        .fail(function(x) { var msg = (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'; $('#moveConflict').removeClass('d-none').text(msg); showToast('error', msg); });
});

function mergeAddCohortRow(sem, sec) {
    var $tr = $('<tr>');
    var $inSem = $('<input type="number" class="form-control form-control-sm merge-cohort-sem" min="1">');
    if (sem != null && sem !== '') {
        $inSem.val(parseInt(sem, 10) || 1);
    }
    var $inSec = $('<input type="text" class="form-control form-control-sm merge-cohort-sec" maxlength="32">');
    if (sec != null && sec !== '') {
        $inSec.val(String(sec));
    }
    $tr.append($('<td>').append($inSem));
    $tr.append($('<td>').append($inSec));
    var $rm = $('<button type="button" class="btn btn-sm btn-outline-danger">Remove</button>');
    $rm.on('click', function() {
        if ($('#mergeCohortsBody tr').length <= 2) { showToast('warning', 'Keep at least two cohorts.'); return; }
        $tr.remove();
    });
    $tr.append($('<td>').append($rm));
    $('#mergeCohortsBody').append($tr);
}

function loadMergeDropdowns() {
    $.get(base + 'actions/courses.php').done(function(list) {
        var arr = Array.isArray(list) ? list : [];
        var $c = $('#mergeCourse').empty().append('<option value="">— Select course —</option>');
        arr.forEach(function(x) {
            var chl = x.credit_hours_lab != null ? parseInt(x.credit_hours_lab, 10) : 0;
            $c.append($('<option>').val(x.id).attr('data-chl', chl).text((x.code || '') + ' — ' + (x.name || '') + ' (Sem ' + (x.semester || '') + ')'));
        });
    });
    $.get(base + 'actions/faculty.php').done(function(list) {
        var $f = $('#mergeFaculty').empty().append('<option value="">— Select faculty —</option>');
        (list || []).forEach(function(x) {
            if (x.is_active === 0 || x.is_active === '0') return;
            $f.append($('<option>').val(x.id).text(x.full_name || x.name || ('#' + x.id)));
        });
    });
    $.get(base + 'actions/rooms.php').done(function(list) {
        var $r = $('#mergeRoom').empty().append('<option value="">— Select room —</option>');
        (list || []).forEach(function(x) {
            if (x.is_active === 0 || x.is_active === '0') return;
            $r.append($('<option>').val(x.id).text((x.room_number || '') + ' (cap ' + (x.capacity || '') + ')'));
        });
    });
    $.get(base + 'actions/time_slots.php').done(function(list) {
        mergeAllSlots = Array.isArray(list) ? list : [];
        if (!$('#mergeDate').val()) {
            $('#mergeDate').val(mergeFormatDateLocal(new Date()));
        }
        var dFromDate = mergeDateStrToDbDow($('#mergeDate').val());
        if (dFromDate) {
            $('#mergeDayOfWeek').val(String(dFromDate));
        }
        mergeRefreshSlotPickOptions();
    });
}

function mergePrefillCohortsFromGen() {
    var sem = parseInt($('#genSemester').val(), 10) || 1;
    var sec = ($('#genSection').val() || 'A').trim() || 'A';
    var sec2 = sec === 'A' ? 'B' : 'A';
    $('#mergeCohortsBody').empty();
    mergeAddCohortRow(sem, sec);
    mergeAddCohortRow(sem, sec2);
}

$('#mergeAddCohort').on('click', function() {
    var sem = parseInt($('#genSemester').val(), 10) || 1;
    mergeAddCohortRow(sem, '');
});

$('#mergeDate').on('change', function() {
    var d = mergeDateStrToDbDow($('#mergeDate').val());
    if (d) {
        $('#mergeDayOfWeek').val(String(d));
    }
    mergeRefreshSlotPickOptions();
});
$('#mergeDayOfWeek').on('change', mergeRefreshSlotPickOptions);

$('#mergeSubmit').on('click', function() {
    var session_id = $('#genSession').val();
    if (!session_id) { $('#mergeResult').html('<span class="text-danger">Select an Academic Session in the form above first.</span>'); return; }
    var course_id = parseInt($('#mergeCourse').val(), 10);
    var faculty_id = parseInt($('#mergeFaculty').val(), 10);
    var room_id = parseInt($('#mergeRoom').val(), 10);
    var dow = parseInt($('#mergeDayOfWeek').val(), 10);
    if (!dow || dow < 1 || dow > 7) {
        $('#mergeResult').html('<span class="text-danger">Select a day (Monday–Sunday).</span>');
        return;
    }
    var slot_id = parseInt($('#mergeSlotPick').val(), 10);
    if (!slot_id) {
        $('#mergeResult').html('<span class="text-danger">Select a time slot from the list (08:00–15:00 for this day).</span>');
        return;
    }
    if (!course_id || !faculty_id || !room_id) {
        $('#mergeResult').html('<span class="text-danger">Select course, faculty, and room.</span>');
        return;
    }
    var groups = [];
    $('#mergeCohortsBody tr').each(function() {
        var $tr = $(this);
        var s = parseInt($tr.find('.merge-cohort-sem').val(), 10);
        var sc = ($tr.find('.merge-cohort-sec').val() || '').trim();
        if (s >= 1 && sc !== '') groups.push({ semester: s, section: sc });
    });
    if (groups.length < 2) {
        $('#mergeResult').html('<span class="text-danger">Add at least two cohorts with semester and section.</span>');
        return;
    }
    $('#mergeResult').html('<span class="text-muted">Saving…</span>');
    $.ajax({
        url: base + 'actions/merged_lecture.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            academic_session_id: parseInt(session_id, 10),
            course_id: course_id,
            faculty_id: faculty_id,
            room_id: room_id,
            time_slot_id: slot_id,
            section_groups: groups
        })
    }).done(function(r) {
        if (r.success) {
            $('#mergeResult').html('<span class="text-success">' + escapeHtml(r.message || 'Created.') + '</span>');
            showToast('success', r.message || 'Merged lecture created.');
            setSessionSelection('view', session_id, $('#genSessionBtn').text(), true);
        } else {
            $('#mergeResult').html('<span class="text-danger">' + escapeHtml(r.message || 'Error') + '</span>');
            showToast('error', r.message || 'Error');
        }
    }).fail(function(x) {
        var msg = (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.';
        $('#mergeResult').html('<span class="text-danger">' + escapeHtml(msg) + '</span>');
        showToast('error', msg);
    });
});

$(function() {
    loadViewDegreePrograms();
    loadGenerateDegrees();
    loadSessionDropdown('gen', '');
    loadSessionDropdown('view', '');
    $('#genSessionSearch').on('input', function() {
        if (sessionSearchTimers.gen) clearTimeout(sessionSearchTimers.gen);
        var q = $(this).val() || '';
        sessionSearchTimers.gen = setTimeout(function() { loadSessionDropdown('gen', q); }, 250);
    });
    $('#viewSessionSearch').on('input', function() {
        if (sessionSearchTimers.view) clearTimeout(sessionSearchTimers.view);
        var q = $(this).val() || '';
        sessionSearchTimers.view = setTimeout(function() { loadSessionDropdown('view', q); }, 250);
    });
    $('#viewBatchSearch').on('input', function() {
        if (batchSearchTimer) clearTimeout(batchSearchTimer);
        var q = $(this).val() || '';
        batchSearchTimer = setTimeout(function() { renderBatchList(viewBatchItems, q); }, 200);
    });
    loadCoursesForSemester();
    loadMergeDropdowns();
    mergePrefillCohortsFromGen();
    $('#genSemester, #genSection').on('change', function() {
        if ($('#mergeCohortsBody tr').length === 2) mergePrefillCohortsFromGen();
    });
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
