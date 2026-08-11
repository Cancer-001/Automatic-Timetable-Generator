<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('faculty');
$pageTitle = 'Availability & Preferences';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Availability & Preferences</h2>
    <p class="text-muted">Describe your preferred days or times off. This helps when building the timetable.</p>
    <div class="card">
        <div class="card-body">
            <form id="availabilityForm">
                <div class="mb-3">
                    <label class="form-label" for="availabilityNotes">Availability notes / preferred days or times off</label>
                    <textarea id="availabilityNotes" class="form-control" rows="5" placeholder="e.g. No classes on Fridays; prefer morning slots only; off on Wednesdays after 12:00"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" id="saveBtn">Save Preferences</button>
            </form>
        </div>
    </div>
</div>
<script>
var base = '../';
$.get(base + 'actions/faculty_availability.php')
    .done(function(data) {
        $('#availabilityNotes').val(data.availability_notes != null ? data.availability_notes : '');
    })
    .fail(function(x) {
        showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Could not load preferences.');
    });

$('#availabilityForm').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#saveBtn');
    $btn.prop('disabled', true).text('Saving...');
    $.ajax({
        url: base + 'actions/faculty_availability.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ availability_notes: $('#availabilityNotes').val() })
    })
        .done(function(r) {
            if (r.success) {
                showToast('success', r.message || 'Preferences saved.');
            } else {
                showToast('error', r.message || 'Save failed.');
            }
        })
        .fail(function(x) {
            showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.');
        })
        .always(function() {
            $btn.prop('disabled', false).text('Save Preferences');
        });
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
