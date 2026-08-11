/**
 * Timetable Management System - Global JS
 * All API errors should return { success: false, message: "..." } - show as toast
 */
function showToast(type, message) {
    type = type || 'info';
    var bg = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : type === 'warning' ? 'bg-warning' : 'bg-secondary';
    var icon = type === 'success' ? '✓' : type === 'error' ? '!' : type === 'warning' ? '⚠' : 'ℹ';
    var $container = $('#toastContainer');
    if (!$container.length) $container = $('<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index: 9999;"></div>').appendTo('body');
    var $toast = $('<div class="toast align-items-center text-white border-0 ' + bg + '" role="alert"><div class="d-flex"><div class="toast-body">' + icon + ' ' + escapeHtml(message) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
    $container.append($toast);
    var toast = new bootstrap.Toast($toast[0], { delay: 4500 });
    $toast.on('hidden.bs.toast', function() { $toast.remove(); });
    toast.show();
}
function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

$(document).ready(function() {
    $.ajaxSetup({
        dataType: 'json',
        error: function(xhr, status, err) {
            console.error('AJAX Error:', status, err);
            // Don't show toast here - let each request's .fail() show it to avoid duplicate toasts
        }
    });
    initSearchableSelects();
});

$(document).on('click', '.pw-toggle-btn', function() {
    var $btn = $(this), $input = $btn.closest('.input-group').find('input');
    if (!$input.length) return;
    if ($input.attr('type') === 'password') {
        $input.attr('type', 'text');
        $btn.attr('aria-label', 'Hide password').attr('title', 'Hide password').find('i').removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
        $input.attr('type', 'password');
        $btn.attr('aria-label', 'Show password').attr('title', 'Show password').find('i').removeClass('bi-eye-slash').addClass('bi-eye');
    }
});

function initSearchableSelects(scope) {
    var $root = scope ? $(scope) : $(document);
    // #region agent log
    fetch('http://127.0.0.1:7918/ingest/6f147695-a994-408c-88de-9c0032c3f0cd',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'1e6561'},body:JSON.stringify({sessionId:'1e6561',runId:'dup-search',hypothesisId:'H1',location:'assets/js/app.js:initSearchableSelects:start',message:'init searchable selects start',data:{path:window.location.pathname,searchableSelects:$root.find('select[data-searchable="1"]').length,existingSearchInputs:$root.find('.select-search-input').length},timestamp:Date.now()})}).catch(()=>{});
    // #endregion
    $root.find('select[data-searchable="1"]').each(function() {
        var $select = $(this);
        // Hard de-duplication: keep only one contiguous injected search input above each searchable select.
        var $searchStack = $select.prevUntil(':not(.select-search-input)').filter('.select-search-input');
        if ($searchStack.length > 1) {
            $searchStack.slice(1).remove();
        }
        if ($select.data('searchableInit')) return;
        $select.data('searchableInit', 1);

        var placeholder = $select.attr('data-search-placeholder') || 'Search...';
        var $search = $('<input type="text" class="form-control form-control-sm mb-1 select-search-input" autocomplete="off">')
            .attr('placeholder', placeholder);
        $search.insertBefore($select);
        // #region agent log
        fetch('http://127.0.0.1:7918/ingest/6f147695-a994-408c-88de-9c0032c3f0cd',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'1e6561'},body:JSON.stringify({sessionId:'1e6561',runId:'dup-search',hypothesisId:'H2',location:'assets/js/app.js:initSearchableSelects:insert',message:'inserted search input before select',data:{path:window.location.pathname,selectId:$select.attr('id')||null,placeholder:placeholder},timestamp:Date.now()})}).catch(()=>{});
        // #endregion

        $search.on('input', function() {
            var q = ($(this).val() || '').toLowerCase().trim();
            $select.find('option').each(function() {
                var $opt = $(this);
                if (!$opt.val()) {
                    $opt.prop('hidden', false);
                    return;
                }
                var txt = ($opt.text() || '').toLowerCase();
                $opt.prop('hidden', q !== '' && txt.indexOf(q) === -1);
            });
        });
    });
    // #region agent log
    fetch('http://127.0.0.1:7918/ingest/6f147695-a994-408c-88de-9c0032c3f0cd',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'1e6561'},body:JSON.stringify({sessionId:'1e6561',runId:'dup-search',hypothesisId:'H3',location:'assets/js/app.js:initSearchableSelects:end',message:'init searchable selects end',data:{path:window.location.pathname,totalSearchInputs:$root.find('.select-search-input').length},timestamp:Date.now()})}).catch(()=>{});
    // #endregion
}
