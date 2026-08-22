/**
 * form.js — Master form component initialiser + AJAX form handler.
 *
 * Import this in any page that has forms needing:
 *   - Price input cents conversion
 *   - Textarea auto-resize
 *   - AJAX form submission (data-ajax-form)
 *   - 422 validation error injection
 */
import $ from 'jquery';

/* =========================================================
   PRICE INPUT — display value ↔ hidden cents input
   ========================================================= */
function initPriceInputs($scope) {
    $scope.find('[data-price-display]').off('input.price').on('input.price', function () {
        const fieldName = $(this).data('price-display');
        const display = parseFloat($(this).val()) || 0;
        const cents = Math.round(display * 100);
        $(`[data-price-cents="${fieldName}"]`).val(cents);
    });
}

/* =========================================================
   TEXTAREA AUTO-RESIZE
   ========================================================= */
function initAutoResize($scope) {
    $scope.find('[data-auto-resize]').each(function () {
        const el = this;
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    });

    $scope.find('[data-auto-resize]').off('input.autoresize').on('input.autoresize', function () {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
}

/* =========================================================
   FORM ERROR HELPERS
   ========================================================= */

/**
 * Clear all validation errors on a form.
 */
function clearFormErrors($form) {
    $form.find('.form-error-message').remove();
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('[aria-invalid]').attr('aria-invalid', 'false');
}

/**
 * Inject 422 validation errors under each field.
 */
function handleFormErrors(errors, $form) {
    clearFormErrors($form || $('body'));

    $.each(errors, function (field, messages) {
        // Support dot-notation fields (e.g. "address.line1" → name="address[line1]")
        const nameAttr = field.replace(/\.(\w+)/g, '[$1]');
        const $input = ($form || $('body')).find(
            `[name="${nameAttr}"], [name="${field}"]`
        ).first();

        if (!$input.length) return;

        $input.addClass('is-invalid').attr('aria-invalid', 'true');

        const msg = Array.isArray(messages) ? messages[0] : messages;
        const $errMsg = $(`
            <p class="form-error-message flex items-center gap-1 text-xs text-danger-600 mt-1">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
                ${msg}
            </p>
        `);

        // Append under the closest .space-y-1 wrapper, or directly after the input
        const $wrapper = $input.closest('.space-y-1');
        ($wrapper.length ? $wrapper : $input).append($errMsg);
    });

    // Scroll to first error
    const $first = ($form || $('body')).find('.is-invalid').first();
    if ($first.length) {
        $('html, body').animate({ scrollTop: $first.offset().top - 100 }, 300);
        $first.trigger('focus');
    }
}

// Expose globally so inline page scripts can reuse
window.handleFormErrors = handleFormErrors;
window.clearFormErrors = clearFormErrors;

/* =========================================================
   SUBMIT BUTTON LOADING STATE
   ========================================================= */
function setButtonLoading($btn, loading) {
    if (loading) {
        $btn.data('original-text', $btn.html())
            .prop('disabled', true)
            .html('<svg class="w-4 h-4 animate-spin mr-1 inline-block" fill="none" viewBox="0 0 24 24">'
                + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>'
                + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>'
                + `</svg> ${t('shared.loading')}`);
    } else {
        $btn.prop('disabled', false)
            .html($btn.data('original-text') || $btn.html());
    }
}

window.setButtonLoading = setButtonLoading;

/* =========================================================
   AJAX FORM SUBMISSION  (data-ajax-form attribute)

   Usage:
     <form data-ajax-form
           data-success-url="/products"
           data-success-message="Product saved!"
           data-reload="true">
   ========================================================= */
function initAjaxForms($scope) {
    $scope.find('[data-ajax-form]').off('submit.ajaxform').on('submit.ajaxform', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $submitBtn = $form.find('[type="submit"]').first();
        const method = ($form.attr('method') || 'POST').toUpperCase();
        const action = $form.attr('action') || window.location.href;
        const formData = new FormData(this);

        clearFormErrors($form);
        setButtonLoading($submitBtn, true);

        $.ajax({
            url: action,
            method: method === 'GET' ? 'GET' : 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-HTTP-Method-Override': method },
        })
            .done(function (response) {
                if (response.message) {
                    window.Toast && window.Toast.success(response.message);
                }

                const successUrl = $form.data('success-url');
                const reload = $form.data('reload');

                if (successUrl) {
                    window.location.href = successUrl;
                } else if (reload) {
                    window.location.reload();
                } else {
                    // Trigger custom event for parent to handle
                    $form.trigger('ajax-form:success', [response]);
                    // Close parent modal if any
                    $form.closest('.modal-backdrop').modal('close');
                }
            })
            .fail(function (xhr) {
                xhr.handled = true;
                setButtonLoading($submitBtn, false);

                if (xhr.status === 422) {
                    const resp = xhr.responseJSON || {};
                    handleFormErrors(resp.errors || {}, $form);
                    if (resp.message) {
                        window.Toast && window.Toast.error(resp.message);
                    }
                } else if (xhr.status === 403) {
                    window.Toast && window.Toast.error(t('shared.form.not_authorised'));
                } else {
                    const msg = xhr.responseJSON?.message || t('shared.something_went_wrong');
                    window.Toast && window.Toast.error(msg);
                }
            })
            .always(function () {
                setButtonLoading($submitBtn, false);
            });
    });
}

/* =========================================================
   PUBLIC INIT — call with a jQuery scope to init all
   form components in that scope (default: document body).
   ========================================================= */
function initForms($scope) {
    $scope = $scope || $('body');
    initPriceInputs($scope);
    initAutoResize($scope);
    initAjaxForms($scope);
}

window.initForms = initForms;

/* =========================================================
   AUTO-INIT on DOM ready
   ========================================================= */
$(function () {
    initForms($('body'));
});
