<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('student');
$pageTitle = 'My Timetable';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>My Timetable</h2>
    <p class="text-muted small">Your allocated classes across all academic sessions (semester &amp; section from your profile, including merged cohorts).</p>

    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success" id="exportExcel">Export CSV</button>
            <button type="button" class="btn btn-sm btn-danger"  id="exportPdf">Print / PDF</button>
            <button type="button" class="btn btn-sm btn-primary" id="exportIcs">Export Calendar (.ics)</button>
        </div>
    </div>

    <!-- Conflict alert banner (shown only when conflicts exist) -->
    <div id="conflictBanner" class="alert alert-danger d-none mb-3" role="alert">
        <strong>⚠ Schedule Conflict Detected:</strong>
        <span id="conflictBannerText"></span>
        <div class="mt-1 small">Highlighted rows below show the conflicting classes. Please contact your admin to resolve.</div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div id="timetableWrap" class="table-responsive p-3">
                <p class="text-muted">Loading your schedule…</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Conflict row highlight */
.tt-conflict td {
    background: #fff1f1 !important;
    border-left: 4px solid #ef4444 !important;
}
.tt-conflict td:first-child::before {
    content: '⚠ ';
    color: #ef4444;
    font-weight: 700;
}
.conflict-badge {
    display: inline-block;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    margin-left: 6px;
    vertical-align: middle;
    letter-spacing: 0.04em;
}
</style>

<script>
var base = '../';
/** Exports: session 0 = all sessions (student-only; see export.php). */
var EXPORT_ALL_SESSIONS = 0;

function detectConflicts(list) {
    var conflictIdxs = {};
    function mins(t) {
        var p = String(t || '00:00:00').split(':');
        return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
    }
    list.forEach(function(a, i) {
        for (var j = i + 1; j < list.length; j++) {
            var b = list[j];
            if (String(a.academic_session_id || '') !== String(b.academic_session_id || '')) continue;
            if (parseInt(a.day_of_week, 10) !== parseInt(b.day_of_week, 10)) continue;
            if (mins(a.start_time) < mins(b.end_time) && mins(a.end_time) > mins(b.start_time)) {
                conflictIdxs[i] = true;
                conflictIdxs[j] = true;
            }
        }
    });
    return conflictIdxs;
}

function renderTimetableTable(list) {
    if (!list || !list.length) {
        return '<p class="text-muted py-2">No classes scheduled for your semester/section.</p>';
    }

    list.sort(function (a, b) {
        var s = (b.session_start || '').localeCompare(a.session_start || '');
        if (s) return s;
        var d = (a.day_of_week || 0) - (b.day_of_week || 0);
        if (d) return d;
        return (a.start_time || '').localeCompare(b.start_time || '');
    });

    // Detect conflicts
    var conflictIdxs = detectConflicts(list);
    var hasConflicts = Object.keys(conflictIdxs).length > 0;

    // Show or hide conflict banner
    if (hasConflicts) {
        var conflictCourses = [];
        Object.keys(conflictIdxs).forEach(function(i) {
            conflictCourses.push(list[i].course_name);
        });
        // deduplicate
        conflictCourses = conflictCourses.filter(function(v, i, a) { return a.indexOf(v) === i; });
        $('#conflictBanner').removeClass('d-none');
        $('#conflictBannerText').text(' ' + conflictCourses.join(', ') + ' share the same time slot.');
    } else {
        $('#conflictBanner').addClass('d-none');
        $('#conflictBannerText').text('');
    }

    var html = '<table class="table table-bordered table-hover table-sm tt-table" style="min-width:1100px">';
    html += '<thead class="tt-thead"><tr>'
        + '<th>Program</th><th>Semester</th><th>Section</th><th>Session</th>'
        + '<th>CHT</th><th>CHL</th><th>Course Title</th>'
        + '<th>Teacher Name</th><th>Duration</th><th>Day</th><th>Time</th><th>Room</th><th>Remarks</th>'
        + '</tr></thead><tbody>';

    list.forEach(function(r, idx) {
        var deptCode = (r.department_code || '').toUpperCase();
        var program  = String(r.program_name || '').trim() || (deptCode ? 'BS' + deptCode : '—');
        var isConflict = !!conflictIdxs[idx];
        var conflictBadge = isConflict ? '<span class="conflict-badge">CONFLICT</span>' : '';
        var rowClass = isConflict ? ' class="tt-conflict"' : '';
        var mergedBadge = (parseInt(r.is_merged_lecture, 10) === 1)
            ? '<span class="badge bg-info text-dark ms-1" title="Shared lecture with other sections">Merged</span>' : '';
        var extraCohorts = (r.merge_extra_cohorts || '').trim();
        var sectionExtras = extraCohorts
            ? '<br><small class="text-muted fw-normal" title="Other cohorts in this class">+ ' + escapeHtml(extraCohorts) + '</small>'
            : '';

        html += '<tr' + rowClass + '>'
            + '<td><span class="badge bg-primary">' + escapeHtml(program) + '</span></td>'
            + '<td class="text-center">' + (r.semester || '') + '</td>'
            + '<td class="text-center"><span class="badge bg-success">' + escapeHtml(r.section || '') + '</span>' + sectionExtras + '</td>'
            + '<td>' + escapeHtml(r.session_name || '') + '</td>'
            + '<td class="text-center">' + (r.cht != null ? r.cht : '') + '</td>'
            + '<td class="text-center">' + (r.chl != null ? r.chl : '') + '</td>'
            + '<td>' + escapeHtml(r.course_name || '') + mergedBadge + conflictBadge + '</td>'
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

function loadMyTimetable() {
    $('#conflictBanner').addClass('d-none');
    $('#timetableWrap').html('<p class="text-muted">Loading…</p>');
    $.get(base + 'actions/schedule.php')
        .done(function(data) {
            var list = (data && data.list) ? data.list : [];
            $('#timetableWrap').html(renderTimetableTable(list));
        })
        .fail(function() {
            $('#timetableWrap').html('<p class="text-danger">Failed to load schedule.</p>');
        });
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
