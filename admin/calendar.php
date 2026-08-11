<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Calendar';
require_once __DIR__ . '/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<style>
/* Keep calendar header controls light (not dark). */
#adminCalendar .fc-button-primary {
    background-color: #ffffff !important;
    border-color: #d1d5db !important;
    color: #111827 !important;
    box-shadow: none !important;
}
#adminCalendar .fc-button-primary:hover,
#adminCalendar .fc-button-primary:focus,
#adminCalendar .fc-button-primary:active {
    background-color: #f9fafb !important;
    border-color: #9ca3af !important;
    color: #111827 !important;
}
#adminCalendar .fc-button-primary:not(:disabled).fc-button-active {
    background-color: #eef2ff !important;
    border-color: #6366f1 !important;
    color: #312e81 !important;
}

/* White tooltip style for calendar event hover details. */
.tooltip.fc-white-tooltip .tooltip-inner {
    background: #ffffff;
    background-color: #ffffff !important;
    opacity: 1 !important;
    color: #111827;
    border: 1px solid #e5e7eb;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
    text-align: left;
    max-width: 260px;
}
.tooltip.fc-white-tooltip {
    opacity: 1 !important;
}
.tooltip.fc-white-tooltip.bs-tooltip-top .tooltip-arrow::before {
    border-top-color: #ffffff;
}
.tooltip.fc-white-tooltip.bs-tooltip-bottom .tooltip-arrow::before {
    border-bottom-color: #ffffff;
}
.tooltip.fc-white-tooltip.bs-tooltip-start .tooltip-arrow::before {
    border-left-color: #ffffff;
}
.tooltip.fc-white-tooltip.bs-tooltip-end .tooltip-arrow::before {
    border-right-color: #ffffff;
}
</style>
<div class="container-fluid py-4">
    <h2>Calendar</h2>
    <div class="card mb-3">
        <div class="card-body d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label">Academic Session</label>
                <select id="calSession" class="form-select">
                    <option value="">-- Select Generated Session --</option>
                </select>
            </div>
            <div>
                <label class="form-label">Department</label>
                <select id="calDepartment" class="form-select">
                    <option value="">All Departments</option>
                </select>
            </div>
            <div>
                <label class="form-label">Semester</label>
                <select id="calSemester" class="form-select">
                    <option value="">All Semesters</option>
                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option>
                    <option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option>
                </select>
            </div>
            <button id="calReload" class="btn btn-outline-primary">Reload</button>
            <button id="addEventBtn" class="btn btn-primary">Add Event</button>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div id="adminCalendar"></div>
        </div>
    </div>
</div>

<div id="eventModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="eventModalTitle">Add Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="eventId">
                <div class="mb-2"><label class="form-label">Title</label><input id="eventTitle" class="form-control"></div>
                <div class="mb-2"><label class="form-label">Type</label>
                    <select id="eventType" class="form-select" data-searchable="1" data-search-placeholder="Search event type">
                        <option value="custom">Custom</option>
                        <option value="exam">Exam</option>
                        <option value="holiday">Holiday</option>
                        <option value="lecture">Lecture</option>
                        <option value="lab">Lab</option>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label">Start</label><input id="eventStart" type="datetime-local" class="form-control"></div>
                <div class="mb-2"><label class="form-label">End</label><input id="eventEnd" type="datetime-local" class="form-control"></div>
                <div class="mb-2"><label class="form-label">Semester (optional)</label><input id="eventSemester" type="number" min="1" class="form-control"></div>
                <div class="mb-2"><label class="form-label">Section (optional)</label><input id="eventSection" class="form-control"></div>
                <div class="mb-2"><label class="form-label">Notes</label><textarea id="eventNotes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger me-auto d-none" id="deleteEventBtn">Delete</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEventBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<div id="scheduleInfoModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">Class Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-2">
                <div id="scheduleInfoBody" class="small text-dark"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
let adminCalendar;
let calendarGeneratedSessions = [];
let scheduleInfoModal;
function toLocalInput(dt) { if (!dt) return ''; return dt.slice(0,16); }
function buildScheduleInfoHtml(info) {
    var ep = (info && info.event && info.event.extendedProps) ? info.event.extendedProps : {};
    var title = (info && info.event && info.event.title) ? info.event.title : '';
    var timeText = '';
    if (info && info.event && info.event.start) {
        var st = info.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        var en = info.event.end ? info.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        timeText = en ? (st + ' - ' + en) : st;
    }
    var semesterText = '';
    if (Array.isArray(ep.semester_labels) && ep.semester_labels.length) {
        semesterText = ep.semester_labels.join(', ');
    } else if (ep.semester) {
        semesterText = String(ep.semester) + (ep.section ? ('-' + ep.section) : '');
    }
    var rows = [];
    rows.push('<div class="fw-semibold mb-2">' + escapeHtml(title) + '</div>');
    if (ep.department_name) rows.push('<div class="mb-1"><span class="text-muted">Department:</span> ' + escapeHtml(ep.department_name) + '</div>');
    if (ep.faculty_name) rows.push('<div class="mb-1"><span class="text-muted">Teacher:</span> ' + escapeHtml(ep.faculty_name) + '</div>');
    if (semesterText) rows.push('<div class="mb-1"><span class="text-muted">Semester:</span> ' + escapeHtml(semesterText) + '</div>');
    if (ep.total_students != null && ep.total_students !== '') rows.push('<div class="mb-1"><span class="text-muted">Total Students:</span> ' + escapeHtml(String(ep.total_students)) + '</div>');
    if (ep.room_number) rows.push('<div class="mb-1"><span class="text-muted">Room:</span> ' + escapeHtml(ep.room_number) + '</div>');
    if (ep.program_name) rows.push('<div class="mb-1"><span class="text-muted">Program:</span> ' + escapeHtml(ep.program_name) + '</div>');
    if (timeText) rows.push('<div class="pt-1 border-top mt-2"><span class="text-muted">Time:</span> ' + escapeHtml(timeText) + '</div>');
    return rows.join('');
}
function loadSessions() {
    $.get(base + 'actions/schedule.php', { generated_sessions: 1 }).done(function(resp){
        let list = (resp && resp.list) ? resp.list : [];
        calendarGeneratedSessions = Array.isArray(list) ? list : [];
        let $s = $('#calSession');
        $s.find('option:not(:first)').remove();
        calendarGeneratedSessions.forEach(function(x){
            let label = (x.name || ('Session #' + x.id)) + ' (' + (x.generated_rows || 0) + ' rows)';
            let $opt = $('<option>').val(x.id).text(label);
            if (x.start_date) $opt.attr('data-start', x.start_date);
            $s.append($opt);
        });
        if (calendarGeneratedSessions.length > 0) {
            let first = calendarGeneratedSessions[0];
            $s.val(String(first.id));
            if (adminCalendar && first.start_date) {
                adminCalendar.gotoDate(first.start_date);
            }
            if (adminCalendar) {
                adminCalendar.refetchEvents();
            }
        } else {
            if (adminCalendar) {
                adminCalendar.removeAllEvents();
            }
        }
    });
}
function loadDepartments() {
    $.get(base + 'actions/departments.php').done(function(list){
        let $d = $('#calDepartment');
        $d.find('option:not(:first)').remove();
        (list || []).forEach(function(x){ $d.append($('<option>').val(x.id).text(x.name)); });
    });
}
function fetchEvents(info, success, failure) {
    const sid = $('#calSession').val() || '';
    if (!sid) {
        success([]);
        return;
    }
    const did = $('#calDepartment').val() || '';
    const sem = $('#calSemester').val() || '';
    $.get(base + 'actions/calendar_events.php', { academic_session_id: sid, department_id: did, semester: sem })
      .done(r => {
          let rawEvents = (r && r.events) ? r.events : [];
          let expandedEvents = [];

          rawEvents.forEach(ev => {
              if (ev.daysOfWeek && ev.daysOfWeek.length > 0 && ev.startRecur && ev.endRecur) {
                  let dow = ev.daysOfWeek[0];
                  
                  let current = new Date(info.start.valueOf());
                  
                  // Map assigned weekday dynamically against the current active calendar view
                  while (current <= info.end) {
                      if (current.getDay() === dow) {
                          let y = current.getFullYear();
                          let m = String(current.getMonth() + 1).padStart(2, '0');
                          let d = String(current.getDate()).padStart(2, '0');
                          let dateStr = `${y}-${m}-${d}`;
                          
                          let sTime = ev.startTime.length === 5 ? ev.startTime + ':00' : ev.startTime;
                          let eTime = ev.endTime ? (ev.endTime.length === 5 ? ev.endTime + ':00' : ev.endTime) : '';

                          expandedEvents.push({
                              ...ev,
                              id: ev.id + '_' + dateStr,
                              start: dateStr + 'T' + sTime,
                              end: eTime ? (dateStr + 'T' + eTime) : undefined,
                              allDay: false,
                              daysOfWeek: undefined,
                              startRecur: undefined,
                              endRecur: undefined,
                              startTime: undefined,
                              endTime: undefined
                          });
                      }
                      current.setDate(current.getDate() + 1);
                  }
              } else {
                  expandedEvents.push(ev);
              }
          });
          
          success(expandedEvents);
      })
      .fail(failure);
}
function openEventModal(e) {
    $('#eventModalTitle').text(e && e.id ? 'Edit Event' : 'Add Event');
    $('#eventId').val(e && e.id ? e.id : '');
    $('#eventTitle').val(e && e.title ? e.title : '');
    $('#eventType').val(e && e.event_type ? e.event_type : 'custom');
    $('#eventStart').val(e && e.start_datetime ? toLocalInput(e.start_datetime) : '');
    $('#eventEnd').val(e && e.end_datetime ? toLocalInput(e.end_datetime) : '');
    $('#eventSemester').val(e && e.semester ? e.semester : '');
    $('#eventSection').val(e && e.section ? e.section : '');
    $('#eventNotes').val(e && e.notes ? e.notes : '');
    $('#deleteEventBtn').toggleClass('d-none', !(e && e.id));
    $('#eventModal').modal('show');
}
$(function(){
    scheduleInfoModal = new bootstrap.Modal(document.getElementById('scheduleInfoModal'));
    loadSessions();
    loadDepartments();
    adminCalendar = new FullCalendar.Calendar(document.getElementById('adminCalendar'), {
        initialView: 'dayGridMonth',
        firstDay: 1,
        weekends: false,
        slotMinTime: '08:00:00',
        slotMaxTime: '15:00:00',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
        selectable: true,
        editable: false,
        eventSources: [{ events: fetchEvents }],
        dateClick: function(info){ openEventModal({ start_datetime: info.dateStr + 'T09:00', end_datetime: info.dateStr + 'T10:00' }); },
        eventClick: function(info){
            const ep = info.event.extendedProps || {};
            if (ep.source !== 'custom') {
                $('#scheduleInfoBody').html(buildScheduleInfoHtml(info));
                scheduleInfoModal.show();
                return;
            }
            openEventModal({
                id: ep.event_id,
                title: info.event.title,
                event_type: ep.event_type || 'custom',
                start_datetime: info.event.start ? info.event.start.toISOString() : '',
                end_datetime: info.event.end ? info.event.end.toISOString() : '',
                notes: ep.notes || ''
            });
        },
        eventDidMount: function(arg) {
            var ep = arg.event.extendedProps || {};
            if (!ep || ep.source !== 'schedule') return;
            var parts = [];
            if (ep.department_name) parts.push('Department: ' + ep.department_name);
            if (ep.faculty_name) parts.push('Teacher: ' + ep.faculty_name);
            if (Array.isArray(ep.semester_labels) && ep.semester_labels.length) {
                parts.push('Semester: ' + ep.semester_labels.join(', '));
            } else if (ep.semester) {
                parts.push('Semester: ' + ep.semester + (ep.section ? ('-' + ep.section) : ''));
            }
            if (ep.total_students != null && ep.total_students !== '') {
                parts.push('Total Students: ' + ep.total_students);
            }
            if (ep.room_number) parts.push('Room: ' + ep.room_number);
            if (ep.program_name) parts.push('Program: ' + ep.program_name);
            if (parts.length) {
                var htmlTitle = parts.map(function(x){ return escapeHtml(String(x)); }).join('<br>');
                if (window.bootstrap && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(arg.el, {
                        title: htmlTitle,
                        html: true,
                        container: 'body',
                        trigger: 'hover',
                        customClass: 'fc-white-tooltip'
                    });
                } else {
                    arg.el.setAttribute('title', parts.join(' | '));
                }
            }
        }
    });
    adminCalendar.render();
    $('#calReload,#calDepartment,#calSemester').on('click change', function(){ adminCalendar.refetchEvents(); });
    $('#calSession').on('change', function() {
        const sid = String($(this).val() || '');
        const sess = calendarGeneratedSessions.find(function(x){ return String(x.id) === sid; });
        if (sess && sess.start_date) {
            adminCalendar.gotoDate(sess.start_date);
        }
        adminCalendar.refetchEvents();
    });
    $('#addEventBtn').on('click', function(){ openEventModal(null); });
    $('#saveEventBtn').on('click', function(){
        const id = $('#eventId').val();
        const payload = {
            id: id || undefined,
            title: $('#eventTitle').val().trim(),
            event_type: $('#eventType').val(),
            academic_session_id: $('#calSession').val() || null,
            start_datetime: $('#eventStart').val().replace('T',' '),
            end_datetime: $('#eventEnd').val().replace('T',' '),
            semester: $('#eventSemester').val() || null,
            section: $('#eventSection').val().trim() || null,
            notes: $('#eventNotes').val().trim() || null
        };
        if (!payload.title || !payload.start_datetime || !payload.end_datetime) { showToast('error','Title, start and end required.'); return; }
        const req = id
          ? $.ajax({ url: base + 'actions/calendar_events.php', method: 'PUT', contentType: 'application/json', data: JSON.stringify(payload) })
          : $.ajax({ url: base + 'actions/calendar_events.php', method: 'POST', contentType: 'application/json', data: JSON.stringify(payload) });
        req.done(function(r){ if (r.success) { $('#eventModal').modal('hide'); adminCalendar.refetchEvents(); showToast('success', r.message || 'Saved.'); } else showToast('error', r.message || 'Error'); })
           .fail(function(x){ showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
    });
    $('#deleteEventBtn').on('click', function(){
        const id = $('#eventId').val();
        if (!id) return;
        $.ajax({ url: base + 'actions/calendar_events.php', method: 'DELETE', contentType: 'application/json', data: JSON.stringify({ id: id }) })
          .done(function(r){ if (r.success) { $('#eventModal').modal('hide'); adminCalendar.refetchEvents(); showToast('success', r.message || 'Deleted.'); } else showToast('error', r.message || 'Error'); })
          .fail(function(x){ showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
    });
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>

