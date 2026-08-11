/**
 * Reusable client-side validation (mirrors config/constants.php limits).
 * Use validateMaxLength() before submit; use showFieldError/clearFieldErrors for red messages.
 */
var Validation = (function() {
    var LIMITS = {
        departmentName: 200,
        departmentCode: 200,
        sessionName: 64,
        email: 128,
        fullName: 128,
        section: 32,
        courseCode: 32,
        courseName: 128,
        roomNumber: 32,
        notes: 300
    };

    function validateMaxLength(value, max, fieldLabel) {
        if (value == null) value = '';
        var s = String(value).trim();
        if (s.length === 0) return null;
        if (s.length <= max) return null;
        return (fieldLabel ? fieldLabel + ': ' : '') + 'Limit ' + max + ' characters.';
    }

    /** Show red error under input (input must have sibling .field-error) */
    function showFieldError(inputSelector, message) {
        var $input = $(inputSelector);
        var $err = $input.siblings('.field-error');
        if (!$err.length) $err = $input.closest('.mb-2').find('.field-error');
        if ($err.length) {
            $err.text(message).addClass('text-danger').show();
        }
        $input.addClass('is-invalid');
    }

    function clearFieldError(inputSelector) {
        var $input = $(inputSelector);
        $input.removeClass('is-invalid');
        var $err = $input.siblings('.field-error').add($input.closest('.mb-2').find('.field-error'));
        $err.text('').hide();
    }

    function clearFieldErrors(containerSelector) {
        var $c = containerSelector ? $(containerSelector) : $(document.body);
        $c.find('.field-error').text('').hide();
        $c.find('.is-invalid').removeClass('is-invalid');
    }

    return {
        LIMITS: LIMITS,
        validateMaxLength: validateMaxLength,
        showFieldError: showFieldError,
        clearFieldError: clearFieldError,
        clearFieldErrors: clearFieldErrors
    };
})();
