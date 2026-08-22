/**
 * Admin / Vendor dashboard entrypoint.
 *
 * jQuery is exposed globally (window.$ / window.jQuery) so individual
 * page scripts and Blade-stack scripts can rely on it.
 */
import $ from 'jquery';
import Alpine from 'alpinejs';
import Toastify from 'toastify-js';
import Swal from 'sweetalert2';
import 'toastify-js/src/toastify.css';
import 'sweetalert2/dist/sweetalert2.min.css';

window.$ = window.jQuery = $;
window.Toastify = Toastify;
window.Alpine = Alpine;
window.Swal = Swal;

/* ---------- i18n bridge ----------
 * window.trans is populated per-layout from lang/{en,ar}/js.php via
 * `window.trans = @json(__('js'));` (see resources/views/layouts/*.blade.php),
 * which also defines window.t inline (so it's available even before this
 * module executes). Defined again here only as a safety net for any page
 * that somehow loads app.js without going through one of those layouts.
 */
window.t = window.t || function (path, vars) {
    const parts = String(path).split('.');
    let cur = window.trans || {};
    for (const part of parts) {
        cur = cur == null ? undefined : cur[part];
        if (cur === undefined) return path;
    }
    if (typeof cur === 'string' && vars) {
        return cur.replace(/\{(\w+)\}/g, (_, key) => (vars[key] ?? ''));
    }
    return cur;
};

/* ---------- Global AJAX setup ---------- */
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

/* ---------- Global AJAX error handler ---------- */
$(document).ajaxError(function (event, xhr) {
    if (!xhr || xhr.handled) return;

    if (xhr.status === 401) {
        window.location.href = '/login';
        return;
    }
    if (xhr.status === 403) {
        window.Toast && window.Toast.error(t('shared.not_allowed_action'));
        return;
    }
    if (xhr.status === 419) {
        window.Toast && window.Toast.error(t('shared.session_expired'));
        return;
    }
    if (xhr.status === 422) {
        return;
    }
    if (xhr.status >= 500) {
        window.Toast && window.Toast.error(t('shared.something_went_wrong'));
    }
});

/* ---------- Toast system (Toastify.js wrapper) ---------- */
const baseToastOpts = {
    duration: 4000,
    gravity: 'top',
    position: 'right',
    stopOnFocus: true,
    close: true,
    style: { borderRadius: '0.5rem', fontWeight: '500' },
};

window.Toast = {
    success: (msg) => Toastify({ ...baseToastOpts, text: msg, style: { ...baseToastOpts.style, background: '#16a34a' } }).showToast(),
    error: (msg) => Toastify({ ...baseToastOpts, text: msg, style: { ...baseToastOpts.style, background: '#dc2626' } }).showToast(),
    warning: (msg) => Toastify({ ...baseToastOpts, text: msg, style: { ...baseToastOpts.style, background: '#d97706' } }).showToast(),
    info: (msg) => Toastify({ ...baseToastOpts, text: msg, style: { ...baseToastOpts.style, background: '#0284c7' } }).showToast(),
};

/* ---------- Clipboard helper ----------
 * Wraps navigator.clipboard.writeText with a document.execCommand fallback
 * (needed on non-secure contexts / older browsers where the async API is
 * unavailable) and always surfaces a success/error toast.
 */
window.copyToClipboard = function (text, successMsg, errorMsg) {
    const onSuccess = () => window.Toast && window.Toast.success(successMsg || t('shared.copied'));
    const onError = () => window.Toast && window.Toast.error(errorMsg || t('shared.copy_failed'));

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(onSuccess).catch(onError);
        return;
    }

    // Fallback for non-secure contexts / browsers without the async Clipboard API.
    try {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(textarea);
        ok ? onSuccess() : onError();
    } catch (e) {
        onError();
    }
};

/* ---------- SweetAlert confirm helpers ---------- */
const swalBaseConfirm = {
    showCancelButton: true,
    reverseButtons: true,
    focusCancel: true,
    allowOutsideClick: false,
    heightAuto: false,
    buttonsStyling: false,
    customClass: {
        popup: 'rounded-xl',
        title: 'text-lg font-semibold text-gray-900',
        htmlContainer: 'text-sm text-gray-600',
        actions: 'gap-2',
        confirmButton: 'inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors',
        cancelButton: 'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors',
    },
};

window.confirmDialog = async function (options = {}) {
    const merged = {
        ...swalBaseConfirm,
        title: t('shared.confirm_title'),
        text: '',
        icon: 'question',
        confirmButtonText: t('shared.confirm_button'),
        cancelButtonText: t('shared.cancel_button'),
        ...options,
        customClass: {
            ...swalBaseConfirm.customClass,
            ...(options.customClass || {}),
        },
    };

    if (window.Swal && typeof window.Swal.fire === 'function') {
        const result = await window.Swal.fire(merged);
        return !!result.isConfirmed;
    }

    const fallbackText = [merged.title, merged.text].filter(Boolean).join('\n\n');
    return window.confirm(fallbackText || t('shared.confirm_title'));
};

window.confirmDelete = function (message, options = {}) {
    return window.confirmDialog({
        title: options.title || t('shared.delete_item_title'),
        text: message || t('shared.delete_item_text'),
        icon: 'warning',
        confirmButtonText: options.confirmButtonText || t('shared.yes_delete'),
        cancelButtonText: options.cancelButtonText || t('shared.cancel_button'),
        customClass: {
            confirmButton: 'inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition-colors',
        },
    });
};

window.confirmBulkAction = function (message, count, options = {}) {
    const destructive = !!options.destructive;
    const title = options.title || (destructive ? t('shared.delete_selected_title') : t('shared.confirm_bulk_action_title'));
    const actionText = t('shared.bulk_action_impact', { message, count });

    return window.confirmDialog({
        title,
        text: actionText,
        icon: destructive ? 'warning' : 'question',
        confirmButtonText: destructive ? t('shared.yes_continue') : t('shared.continue_action'),
        cancelButtonText: t('shared.cancel_button'),
        customClass: destructive
            ? {
                confirmButton: 'inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition-colors',
            }
            : {},
    });
};

/* ---------- Modal system (jQuery plugin) ---------- */
$.fn.modal = function (action) {
    const $el = this;

    if (action === 'open') {
        $el.removeClass('hidden').addClass('flex');
        $('body').addClass('overflow-hidden');
        $el.find('input, select, textarea, button').filter(':visible').first().trigger('focus');
        $el.trigger('modal:opened');
    } else if (action === 'close') {
        $el.removeClass('flex').addClass('hidden');
        $('body').removeClass('overflow-hidden');
        $el.trigger('modal:closed');
    }
    return this;
};

/* ---------- Form validation error injector (422 helper) ---------- */
window.injectValidationErrors = function ($form, errors) {
    $form.find('.form-error').remove();
    $form.find('.is-invalid').removeClass('is-invalid');

    let $firstField = null;
    Object.entries(errors || {}).forEach(([field, messages]) => {
        const name = field.replace(/\.(\w+)/g, '[$1]');
        const $field = $form.find(`[name="${name}"], [name="${field}"]`).first();
        if (!$field.length) return;

        $field.addClass('is-invalid');
        const message = Array.isArray(messages) ? messages[0] : messages;
        $field.after(`<p class="form-error">${message}</p>`);

        if (!$firstField) $firstField = $field;
    });

    if ($firstField) {
        $firstField[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        $firstField.trigger('focus');
    }
};

/* ---------- Alpine ---------- */
// Defer start so page-level scripts (ads.js, etc.) can register their
// window.* component factories before Alpine walks the DOM.
queueMicrotask(() => Alpine.start());

/* ---------- Reverb / Echo ---------- */
import './shared/echo-setup.js';

