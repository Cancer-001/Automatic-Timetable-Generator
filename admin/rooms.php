<?php
require_once __DIR__ . '/../auth/check_role.php';
require_role('admin');
$pageTitle = 'Rooms';
require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h2>Room Management</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="roomFormReset()">Add Room</button>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="dropdown">
                    <label class="small text-muted d-block mb-1">Rooms Filter</label>
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle filter-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="roomFilterBtn" style="min-width:320px;">All rooms</button>
                    <div class="dropdown-menu p-2" style="min-width:360px;">
                        <input type="text" id="roomFilterSearch" class="form-control form-control-sm mb-2" placeholder="Search rooms">
                        <div id="roomFilterList" style="max-height:260px;overflow:auto;"></div>
                        <div class="d-flex justify-content-between mt-2">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="roomFilterSelectAll">Select all</button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="roomFilterClear">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-management" id="roomsTable">
                    <thead><tr><th>Room #</th><th>Capacity</th><th>Type</th><th>Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2" id="roomPager">
                <div>
                    <label class="small text-muted me-1">Show</label>
                    <select id="roomPageSize" class="form-select form-select-sm d-inline-block" style="width:auto">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="75">75</option>
                        <option value="100">100</option>
                    </select>
                    <span class="small text-muted ms-1">rows</span>
                </div>
                <div class="small text-muted" id="roomPageInfo"></div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="roomPrev">Previous</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="roomNext">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="roomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="roomModalTitle">Add Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="roomId">
                <div class="mb-2"><label class="form-label">Room Number</label><input type="text" id="roomNumber" class="form-control" maxlength="32" required><div class="field-error text-danger small" style="display:none"></div></div>
                <div class="mb-2"><label class="form-label">Capacity</label><input type="number" id="roomCapacity" class="form-control" value="30" min="1"></div>
                <div class="mb-2"><label class="form-label">Type</label><select id="roomType" class="form-select"><option value="classroom">Classroom</option><option value="lab">Lab</option><option value="hall">Hall</option></select></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="roomSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
if (typeof base === 'undefined' || !base) base = '../';
var roomPage = 1;
var roomPageSize = 25;
var roomTotal = 0;
var selectedRoomIds = [];
var roomFilterItems = [];
var roomFilterTimer = null;
function updateRoomFilterLabel() {
    if (!selectedRoomIds.length) return $('#roomFilterBtn').text('All rooms');
    if (selectedRoomIds.length === 1) {
        var hit = roomFilterItems.find(function(x){ return String(x.id) === String(selectedRoomIds[0]); });
        return $('#roomFilterBtn').text(hit ? (hit.room_number || '1 selected') : '1 selected');
    }
    $('#roomFilterBtn').text(selectedRoomIds.length + ' rooms selected');
}
function renderRoomFilterList() {
    var $list = $('#roomFilterList').empty();
    if (!roomFilterItems.length) { $list.html('<div class="text-muted small">No rooms found.</div>'); return; }
    roomFilterItems.forEach(function(r){
        var id = String(r.id), checked = selectedRoomIds.indexOf(id) !== -1;
        var $row = $('<label class="form-check d-flex align-items-start mb-1"><input class="form-check-input me-2 mt-1 room-filter-item" type="checkbox"><span class="small"></span></label>');
        $row.find('input').val(id).prop('checked', checked);
        $row.find('span').text((r.room_number || '') + (r.room_type ? (' (' + r.room_type + ')') : ''));
        $list.append($row);
    });
}
function loadRoomFilterOptions() {
    var q = ($('#roomFilterSearch').val() || '').trim();
    $.get(base + 'actions/rooms.php', { q: q }).done(function(list){
        roomFilterItems = Array.isArray(list) ? list : [];
        renderRoomFilterList();
        updateRoomFilterLabel();
    });
}
function refreshRoomPager() {
    var $info = $('#roomPageInfo');
    if (!roomTotal) {
        $info.text('No records found (Rows: 0 / 0)');
        $('#roomPrev').prop('disabled', true);
        $('#roomNext').prop('disabled', true);
        return;
    }
    var start = (roomPage - 1) * roomPageSize + 1;
    var end = Math.min(roomTotal, roomPage * roomPageSize);
    var totalPages = Math.max(1, Math.ceil(roomTotal / roomPageSize));
    var shown = end - start + 1;
    $info.text('Showing ' + start + '–' + end + ' of ' + roomTotal + ' (Page ' + roomPage + ' of ' + totalPages + ', Rows: ' + shown + ' / ' + roomTotal + ')');
    $('#roomPrev').prop('disabled', roomPage <= 1);
    $('#roomNext').prop('disabled', roomPage >= totalPages);
}
function loadRooms() {
    $.get(base + 'actions/rooms.php', {
        paged: 1,
        page: roomPage,
        page_size: roomPageSize,
        ids: selectedRoomIds.join(',')
    }).done(function(data) {
        var $tb = $('#roomsTable tbody').empty();
        if (!data || data.success === false) {
            var msg = (data && data.message) || 'Could not load rooms.';
            $tb.append($('<tr><td colspan="4" class="text-danger"></td></tr>').find('td').text(msg).end());
            roomTotal = 0;
            refreshRoomPager();
            return;
        }
        var list = Array.isArray(data.items) ? data.items : [];
        roomTotal = data.total || 0;
        if (list.length === 0) {
            $tb.append($('<tr><td colspan="4" class="text-muted">No records found.</td></tr>'));
        } else {
            list.forEach(function(r) {
                $tb.append($('<tr>').append(
                    $('<td>').text(r.room_number),
                    $('<td>').text(r.capacity),
                    $('<td>').text(r.room_type),
                    $('<td>').html('<button class="btn btn-sm btn-warning me-1 edit-room" data-id="'+r.id+'">Edit</button><button class="btn btn-sm btn-danger delete-room" data-id="'+r.id+'">Delete</button>')
                ));
            });
        }
        refreshRoomPager();
    }).fail(function(xhr) {
        $('#roomsTable tbody').html('<tr><td colspan="4" class="text-danger">Failed to load rooms. Check console. Log in as admin if needed.</td></tr>');
        showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load rooms.');
        roomTotal = 0;
        refreshRoomPager();
    });
}
$('#roomPageSize').on('change', function() {
    roomPageSize = parseInt($(this).val(), 10) || 25;
    roomPage = 1;
    loadRooms();
});
$('#roomPrev').on('click', function() {
    if (roomPage > 1) {
        roomPage--;
        loadRooms();
    }
});
$('#roomNext').on('click', function() {
    roomPage++;
    loadRooms();
});
$('#roomFilterSearch').on('input', function() {
    if (roomFilterTimer) clearTimeout(roomFilterTimer);
    roomFilterTimer = setTimeout(loadRoomFilterOptions, 250);
});
$(document).on('change', '.room-filter-item', function() {
    var v = String($(this).val());
    if (this.checked) { if (selectedRoomIds.indexOf(v) === -1) selectedRoomIds.push(v); }
    else { selectedRoomIds = selectedRoomIds.filter(function(x){ return x !== v; }); }
    updateRoomFilterLabel();
    roomPage = 1; loadRooms();
});
$('#roomFilterSelectAll').on('click', function() {
    selectedRoomIds = roomFilterItems.map(function(r){ return String(r.id); });
    renderRoomFilterList(); updateRoomFilterLabel(); roomPage = 1; loadRooms();
});
$('#roomFilterClear').on('click', function() {
    selectedRoomIds = [];
    renderRoomFilterList(); updateRoomFilterLabel(); roomPage = 1; loadRooms();
});
function roomFormReset() {
    $('#roomModalTitle').text('Add Room');
    $('#roomId').val(''); $('#roomNumber').val(''); $('#roomCapacity').val(30); $('#roomType').val('classroom');
}
$('#roomSaveBtn').on('click', function() {
    var id = $('#roomId').val();
    var room_number = ($('#roomNumber').val() || '').trim();
    Validation.clearFieldErrors('#roomModal');
    if (!room_number) { showToast('error', 'Please enter room number.'); return; }
    var err = Validation.validateMaxLength(room_number, Validation.LIMITS.roomNumber, 'Room number');
    if (err) { Validation.showFieldError('#roomNumber', err); showToast('error', err); return; }
    var data = { room_number: room_number, capacity: $('#roomCapacity').val(), room_type: $('#roomType').val() };
    var url = base + 'actions/rooms.php';
    if (id) { data.id = id; $.ajax({ url: url, method: 'PUT', contentType: 'application/json', data: JSON.stringify(data) }).done(function(r) { if (r.success) { $('#roomModal').modal('hide'); loadRooms(); showToast('success', r.message || 'Saved.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); }); }
    else { $.post(url, data).done(function(r) { if (r.success) { $('#roomModal').modal('hide'); loadRooms(); showToast('success', r.message || 'Room added.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); }); }
});
$(document).on('click', '.edit-room', function() {
    var id = $(this).data('id');
    $.get(base + 'actions/rooms.php?id=' + id).done(function(r) {
        if (!r.id) return;
        $('#roomModalTitle').text('Edit Room');
        $('#roomId').val(r.id); $('#roomNumber').val(r.room_number); $('#roomCapacity').val(r.capacity); $('#roomType').val(r.room_type);
        $('#roomModal').modal('show');
    });
});
$(document).on('click', '.delete-room', function() {
    if (!confirm('Deactivate this room?')) return;
    $.ajax({ url: base + 'actions/rooms.php', method: 'DELETE', contentType: 'application/json', data: JSON.stringify({ id: $(this).data('id') }) }).done(function(r) { if (r.success) { if (roomPage > 1 && (roomPage - 1) * roomPageSize >= (roomTotal - 1)) { roomPage--; } loadRooms(); showToast('success', r.message || 'Room removed.'); } else showToast('error', r.message); }).fail(function(x) { showToast('error', (x.responseJSON && x.responseJSON.message) ? x.responseJSON.message : 'Request failed.'); });
});
$(function() { loadRoomFilterOptions(); loadRooms(); });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
