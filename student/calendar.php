<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('student');
$pageTitle = 'Calendar';
require_once __DIR__ . '/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<div class="container-fluid py-4">
    <h2>My Calendar</h2>
    <div class="card mb-3">
        <div class="card-body d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label">Academic Session</label>
                <select id="calSession" class="form-select"><option value="">All Sessions</option></select>
            </div>
            <button id="calReload" class="btn btn-outline-primary">Reload</button>
        </div>
    </div>
    <div class="card"><div class="card-body"><div id="studentCalendar"></div></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
let studentCalendar;
function loadSessions() {
    $.get(base + 'actions/sessions.php').done(function(list){
        let $s = $('#calSession'); $s.find('option:not(:first)').remove();
        (list || []).forEach(function(x){ $s.append($('<option>').val(x.id).text(x.name)); });
    });
}
function fetchEvents(info, success, failure) {
    const sid = $('#calSession').val() || '';
    $.get(base + 'actions/calendar_events.php', { academic_session_id: sid })
      .done(r => {
          let rawEvents = (r && r.events) ? r.events : [];
          let expandedEvents = [];

          rawEvents.forEach(ev => {
              if (ev.daysOfWeek && ev.daysOfWeek.length > 0 && ev.startRecur && ev.endRecur) {
                  let dow = ev.daysOfWeek[0];
                  let current = new Date(info.start.valueOf());
                  
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
$(function(){
    loadSessions();
    studentCalendar = new FullCalendar.Calendar(document.getElementById('studentCalendar'), {
        initialView: 'timeGridWeek',
        firstDay: 1,
        weekends: false,
        slotMinTime: '08:00:00',
        slotMaxTime: '15:00:00',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridWeek,timeGridDay,listWeek' },
        eventSources: [{ events: fetchEvents }]
    });
    studentCalendar.render();
    $('#calReload,#calSession').on('click change', function(){ studentCalendar.refetchEvents(); });
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>

