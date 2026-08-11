<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('faculty');
$pageTitle = 'My Timetable';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>My Timetable</h2>
    <p class="text-muted small">Your teaching timetable: sessions where you are the <strong>scheduled</strong> instructor, plus every generated slot for courses you are linked to in <strong>Assign Faculty</strong> (same idea as the student cohort view, so team members still see when classes meet).</p>

    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success" id="exportExcel">Export CSV</button>
            <button type="button" class="btn btn-sm btn-danger"  id="exportPdf">Print / PDF</button>
            <button type="button" class="btn btn-sm btn-primary" id="exportIcs">Export Calendar (.ics)</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div id="timetableWrap" class="table-responsive p-3">
                <p class="text-muted">Loading your schedule…</p>
            </div>
        </div>
    </div>
</div>

<script>
window.FACULTY_SELF_ID = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
/** Exports use session id 0 = all sessions (faculty-only; see export.php). */
var EXPORT_ALL_SESSIONS = 0;

function renderLinkedCoursesTable(linked) {
    var rows = Array.isArray(linked) ? linked : [];
    if (!rows.length) return '';
    var html = '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0" style="max-width:920px">';
    html += '<thead class="table-light"><tr><th>Semester</th><th>Code</th><th>Course</th><th>Department</th></tr></thead><tbody>';
    rows.forEach(function (r) {
        var dept = r.department_name || (r.department_code ? String(r.department_code).toUpperCase() : '—');
        html += '<tr>'
            + '<td class="text-center">' + escapeHtml(String(r.semester || '')) + '</td>'
            + '<td>' + escapeHtml(r.course_code || '') + '</td>'
            + '<td>' + escapeHtml(r.course_name || '') + '</td>'
            + '<td>' + escapeHtml(dept) + '</td>'
            + '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
}

/* shared timetable renderer — faculty view */
function renderTimetableTable(list) {
    if (!list || !list.length) return '';
    // Session (newest first) → weekday → time — keeps semesters/sections from mixing across sessions
    list.sort(function (a, b) {
        var s = (b.session_start || '').localeCompare(a.session_start || '');
        if (s) return s;
        var d = (a.day_of_week || 0) - (b.day_of_week || 0);
        if (d) return d;
        return (a.start_time || '').localeCompare(b.start_time || '');
    });
    var html = '<table class="table table-bordered table-hover table-sm tt-table" style="min-width:1100px">';
    html += '<thead class="tt-thead"><tr>'
        + '<th>Program</th><th>Semester</th><th>Section</th><th>Session</th>'
        + '<th>CHT</th><th>CHL</th><th>Course Title</th>'
        + '<th>Teacher Name</th><th>Duration</th><th>Day</th><th>Time</th><th>Room</th><th>Remarks</th>'
        + '</tr></thead><tbody>';
    var selfId = parseInt(window.FACULTY_SELF_ID, 10) || 0;
    list.forEach(function(r) {
        var deptCode = (r.department_code || '').toUpperCase();
        var program = String(r.program_name || '').trim();
        if (!program) {
            program = deptCode ? 'BS' + deptCode : '—';
        }
        var isLeadInstructor = selfId > 0 && parseInt(r.faculty_id, 10) === selfId;
        var teamBadge = (selfId > 0 && !isLeadInstructor)
            ? '<span class="badge bg-secondary-subtle text-secondary-emphasis border ms-1" title="Another instructor is scheduled for this slot; you are on the course teaching list">Team</span>'
            : '';
        var mergedBadge = (parseInt(r.is_merged_lecture, 10) === 1)
            ? '<span class="badge bg-info text-dark ms-1" title="Multiple sections in this slot">Merged</span>' : '';
        var extraCohorts = (r.merge_extra_cohorts || '').trim();
        var sectionExtras = extraCohorts
            ? '<br><small class="text-muted fw-normal" title="Additional cohorts in this class">+ ' + escapeHtml(extraCohorts) + '</small>'
            : '';
        html += '<tr>'
            + '<td><span class="badge bg-primary">' + escapeHtml(program) + '</span></td>'
            + '<td class="text-center">' + (r.semester || '') + '</td>'
            + '<td class="text-center"><span class="badge bg-success">' + escapeHtml(r.section || '') + '</span>' + sectionExtras + '</td>'
            + '<td>' + escapeHtml(r.session_name || '') + '</td>'
            + '<td class="text-center">' + (r.cht != null ? r.cht : '') + '</td>'
            + '<td class="text-center">' + (r.chl != null ? r.chl : '') + '</td>'
            + '<td>' + escapeHtml(r.course_name || '') + mergedBadge + teamBadge + '</td>'
            + '<td>' + escapeHtml(r.faculty_name || '') + '</td>'
            + '<td class="text-center">' + (r.duration != null ? r.duration : '') + '</td>'
            + '<td>' + escapeHtml(r.day_name || '') + '</td>'
            + '<td style="white-space:nowrap">' + escapeHtml(r.time_range || '') + '</td>'
            + '<td>' + escapeHtml(r.room_number || '') + '</td>'
            + '<td></td>'
            + '</tr>';
    });
    html += '</tbody></table>';
    return html;
}

function renderFacultyScheduleView(data) {
    var list = (data && data.list) ? data.list : [];
    var linked = (data && data.linked_courses) ? data.linked_courses : [];
    if (!list.length && !linked.length) {
        return '<p class="text-muted py-2">No course links or timetable slots were found for your account. If this is wrong, confirm you are using your faculty login and that the timetable has been generated.</p>';
    }
    if (list.length) {
        return renderTimetableTable(list);
    }
    var html = '<p class="text-muted py-2 mb-0">No generated timetable slots list you as the instructor yet.</p>';
    if (linked.length) {
        html += '<div class="alert alert-info small mt-3 mb-0">'
            + 'You are linked to the courses below (admin <strong>Courses → Assign Faculty</strong> / teaching pool). '
            + 'The timetable generator assigns <strong>one</strong> instructor per class meeting; if several faculty share a course, another instructor may hold the scheduled slots for a section. '
            + 'Ask your administrator to run <strong>Generate Timetable</strong> for the right session and semester if nothing appears after linking.'
            + '</div>';
        html += renderLinkedCoursesTable(linked);
    }
    return html;
}

function loadMyTimetable() {
    $('#timetableWrap').html('<p class="text-muted">Loading…</p>');
    $.ajax({ url: base + 'actions/schedule.php', dataType: 'json' })
        .done(function(data) {
            if (!data || data.success === false) {
                var msg = (data && data.message) ? data.message : 'Could not load your schedule.';
                $('#timetableWrap').html('<p class="text-danger">' + escapeHtml(msg) + '</p>');
                return;
            }
            $('#timetableWrap').html(renderFacultyScheduleView(data));
        })
        .fail(function() { $('#timetableWrap').html('<p class="text-danger">Failed to load schedule.</p>'); });
}

$(function() { loadMyTimetable(); });

$('#exportExcel').on('click', function() {
    window.location.href = base + 'actions/export.php?format=csv&academic_session_id=' + EXPORT_ALL_SESSIONS;
});
$('#exportPdf').on('click', function() {
    window.open(base + 'actions/export.php?format=pdf&academic_session_id=' + EXPORT_ALL_SESSIONS, '_blank', 'width=1100,height=700');
});
$('#exportIcs').on('click', function() {
    window.location.href = base + 'actions/export.php?format=ics&academic_session_id=' + EXPORT_ALL_SESSIONS;
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
