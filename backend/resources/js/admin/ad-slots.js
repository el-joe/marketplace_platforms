/**
 * Admin – Ad Slots Management JS
 *
 * Covers:
 *  - Form AJAX submit (create / edit)
 */

import $ from 'jquery';

// ─── Form AJAX submit ─────────────────────────────────────────────────────────

function initFormSubmit() {
    $('#ad-slot-form').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        const $btn = $(form).find('button[type="submit"]').first();
        const origTxt = $btn.text();

        $btn.prop('disabled', true).text(t('shared.saving'));

        const fd = new FormData(form);

        $.ajax({
            url: $(form).attr('action'),
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
        })
            .done((res) => {
                window.Toast && window.Toast.success(res.message || t('shared.saved'));
                if (res.redirect) window.location.href = res.redirect;
            })
            .fail((xhr) => {
                const body = xhr.responseJSON;
                if (body?.errors) {
                    window.handleFormErrors && window.handleFormErrors(body.errors, $(form));
                    const firstErr = Object.values(body.errors)[0];
                    window.Toast && window.Toast.error(Array.isArray(firstErr) ? firstErr[0] : firstErr);
                } else {
                    window.Toast && window.Toast.error(body?.message || t('shared.something_went_wrong'));
                }
            })
            .always(() => $btn.prop('disabled', false).text(origTxt));
    });
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ad-slot-form')) {
        initFormSubmit();
    }
});
