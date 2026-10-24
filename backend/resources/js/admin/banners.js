/**
 * Admin – Banners Management JS
 *
 * Covers:
 *  - DataTable with server-side processing
 *  - Status-tab filtering
 *  - Filter form → DataTable params
 *  - Image file-input previews (desktop + mobile)
 *  - Form AJAX submit (create / edit)
 *  - Image removal (AJAX)
 *  - Delete & Duplicate confirmation modals
 *  - Date validation + duration preview
 */

import $ from 'jquery';
import DataTable from 'datatables.net-dt';
import { activateLangTab } from '../components/lang-tabs.js';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function openModalById(id) {
    const jq = window.jQuery || window.$;
    if (jq && typeof jq.fn?.modal === 'function') {
        jq(`#${id}`).modal('open');
        return;
    }

    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('hidden');
    el.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

function closeModalById(id) {
    const jq = window.jQuery || window.$;
    if (jq && typeof jq.fn?.modal === 'function') {
        jq(`#${id}`).modal('close');
        return;
    }

    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('flex');
    el.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

// ─── DataTable setup ──────────────────────────────────────────────────────────

let bannersTable;
let activeStatusFilter = '';

function initDataTable() {
    bannersTable = new DataTable('#banners-table', {
        processing: true,
        serverSide: true,
        ajax: {
            url: '/banners/datatable',
            type: 'POST',
            data(d) {
                d._token = csrfToken();
                d.status = activeStatusFilter;
                d.placement_code = $('#filter-placement').val();
                d.country_id = $('#filter-country').val();
                d.device_target = $('#filter-device').val();
                d.date_from = $('#filter-date-from').val();
                d.date_to = $('#filter-date-to').val();
                d.search_value = $('#search-input').val();
            },
        },
        columns: [
            {
                data: 'image',
                orderable: false,
                searchable: false,
                render(data) {
                    if (!data) return '<div class="w-14 h-9 rounded bg-gray-100 flex items-center justify-center text-gray-300 text-xs">—</div>';
                    return `<img src="${data}" class="w-14 h-9 rounded object-cover border border-gray-200" alt="">`;
                },
            },
            { data: 'name', orderable: true },
            { data: 'placement_code', orderable: true },
            { data: 'country', orderable: false },
            { data: 'status', orderable: true },
            { data: 'device_target', orderable: true },
            { data: 'audience', orderable: true },
            { data: 'date_range', orderable: false },
            { data: 'impressions', orderable: true },
            { data: 'clicks', orderable: true },
            { data: 'ctr', orderable: false },
            { data: 'priority', orderable: true },
            { data: 'actions', orderable: false, searchable: false },
        ],
        order: [[11, 'desc']], // priority desc
        pageLength: 25,
        language: { processing: `<div class="text-sm text-gray-500 py-4">${t('shared.loading')}</div>` },
    });
}

// ─── Status tabs ──────────────────────────────────────────────────────────────

function initStatusTabs() {
    $(document).on('click', '.status-tab', function () {
        $('.status-tab').removeClass('border-primary-500 text-primary-600')
            .addClass('border-transparent text-gray-500');
        $(this).removeClass('border-transparent text-gray-500')
            .addClass('border-primary-500 text-primary-600');
        activeStatusFilter = $(this).data('status');
        bannersTable.ajax.reload();
    });
}

// ─── Filter form ──────────────────────────────────────────────────────────────

function initFilters() {
    $('#search-input').on('input', debounce(() => bannersTable.ajax.reload(), 400));
    $('#filter-placement, #filter-country, #filter-device, #filter-date-from, #filter-date-to')
        .on('change', () => bannersTable.ajax.reload());

    $('#clear-filters').on('click', () => {
        $('#filter-placement, #filter-country, #filter-device, #filter-date-from, #filter-date-to').val('');
        $('#search-input').val('');
        bannersTable.ajax.reload();
    });
}

// ─── Delete modal ─────────────────────────────────────────────────────────────

let pendingDeleteUrl = null;

function initDeleteModal() {
    $(document).on('click', '.js-delete-btn', function () {
        pendingDeleteUrl = $(this).data('url');
        $('#delete-banner-name').text($(this).data('name'));
        openModalById('delete-modal');
    });

    $('#confirm-delete-btn').on('click', function () {
        if (!pendingDeleteUrl) return;
        const btn = $(this);
        btn.prop('disabled', true).text(t('admin.banners.deleting'));

        $.ajax({
            url: pendingDeleteUrl,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        })
            .done((res) => {
                window.Toast.success(res.message || t('admin.banners.deleted'));
                closeModalById('delete-modal');
                bannersTable.ajax.reload(null, false);
            })
            .fail(() => window.Toast.error(t('admin.banners.delete_failed')))
            .always(() => btn.prop('disabled', false).text(t('admin.banners.delete_label')));
    });
}

// ─── Duplicate modal ──────────────────────────────────────────────────────────

let pendingDuplicateUrl = null;

function initDuplicateModal() {
    $(document).on('click', '.js-duplicate-btn', function () {
        pendingDuplicateUrl = $(this).data('url');
        $('#duplicate-banner-name').text($(this).data('name'));
        openModalById('duplicate-modal');
    });

    $('#confirm-duplicate-btn').on('click', function () {
        if (!pendingDuplicateUrl) return;
        const btn = $(this);
        btn.prop('disabled', true).text(t('admin.banners.duplicating'));

        $.ajax({ url: pendingDuplicateUrl, method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken() } })
            .done((res) => {
                window.Toast.success(res.message || t('admin.banners.duplicated'));
                if (res.redirect) window.location.href = res.redirect;
                else {
                    closeModalById('duplicate-modal');
                    bannersTable.ajax.reload(null, false);
                }
            })
            .fail(() => window.Toast.error(t('admin.banners.duplicate_failed')))
            .always(() => btn.prop('disabled', false).text(t('admin.banners.duplicate_label')));
    });
}

// ─── Image preview ────────────────────────────────────────────────────────────

const IMAGE_SLOTS = ['desktop_en', 'desktop_ar', 'mobile_en', 'mobile_ar'];

function initImagePreviews() {
    IMAGE_SLOTS.forEach((slot) => {
        $(`#${slot}-input`).on('change', function () {
            previewFile(this, `#${slot}-preview-img`, `#${slot}-upload-placeholder`);
        });
    });
}

function previewFile(input, imgSelector, placeholderSelector) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        $(imgSelector).attr('src', e.target.result).removeClass('hidden');
        $(placeholderSelector).addClass('hidden');
    };
    reader.readAsDataURL(file);
}

// ─── Remove existing image (AJAX) ─────────────────────────────────────────────

function initImageRemoval() {
    $(document).on('click', '.js-remove-img-btn', function () {
        const btn = $(this);
        const fileId = btn.data('file-id');
        const deleteUrl = btn.data('delete-url');
        const slot = btn.data('slot');

        if (!confirm(t('admin.banners.remove_image_confirm'))) return;

        $.ajax({
            url: deleteUrl,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: { file_id: fileId },
        })
            .done(() => {
                window.Toast.success(t('admin.banners.image_removed'));
                $(`#${slot}-preview-img`).attr('src', '').addClass('hidden');
                $(`#${slot}-upload-placeholder`).removeClass('hidden');
                btn.remove();
            })
            .fail(() => window.Toast.error(t('admin.banners.image_remove_failed')));
    });
}

// ─── Date validation + duration preview ───────────────────────────────────────

function initDates() {
    const $startsAt = $('[name="starts_at"]');
    const $endsAt = $('[name="ends_at"]');

    function validate() {
        const s = $startsAt.val();
        const e = $endsAt.val();
        if (s && e) {
            const valid = new Date(e) > new Date(s);
            $('#date-error').toggleClass('hidden', valid);
            $endsAt[0]?.setCustomValidity(valid ? '' : t('admin.banners.end_after_start'));

            if (valid) {
                const diffMs = new Date(e) - new Date(s);
                const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
                $('#duration-preview').text(t('admin.banners.duration_label', { days: diffDays }));
            } else {
                $('#duration-preview').text('—');
            }
        }
    }

    $startsAt.add($endsAt).on('change input', validate);
    validate();
}


// ─── Field-level error display ────────────────────────────────────────────────

const ERR_CLASS = 'banner-field-error';

function clearFieldErrors($form) {
    $form.find('.' + ERR_CLASS).remove();
    $form.find('.is-invalid').removeClass('is-invalid ring-2 ring-red-500 border-red-500')
        .css({ borderColor: '', boxShadow: '' });
    $form.find('.select2-selection').css({ borderColor: '', boxShadow: '' });
    $form.find('[data-lang-tab]').removeClass('text-red-600').find('.lang-tab-dot').remove();
    $form.find('#date-error').addClass('hidden');
}

function fieldElements($form, field) {
    // "marketer_ids.0" → base "marketer_ids"; also supports name="field[]".
    const base = field.split('.')[0];
    const names = [field, base, base + '[]'];
    let $els = $();
    names.forEach((n) => { $els = $els.add($form.find('[name="' + n + '"]')); });
    return $els;
}

function markInvalid($el, message) {
    const redStyle = { borderColor: '#dc2626', boxShadow: '0 0 0 2px rgba(220,38,38,.25)' };
    $el.addClass('is-invalid ring-2 ring-red-500 border-red-500').css(redStyle);

    // select2 / async select render a sibling container
    const $s2 = $el.next('.select2-container').find('.select2-selection');
    if ($s2.length) $s2.css(redStyle);

    const $msg = $('<p></p>').addClass(ERR_CLASS + ' text-xs text-red-600 mt-1').text(message);
    const $anchor = $s2.length ? $el.next('.select2-container') : $el;
    $anchor.last().after($msg);

    // Reveal the EN/AR tab that holds the field and flag the tab button.
    const $panel = $el.closest('[data-lang-panel]');
    if ($panel.length) {
        const tabsId = $panel.data('tabs-id');
        const lang = $panel.data('lang-panel');
        const $btn = $('[data-lang-tab="' + lang + '"][data-tabs-id="' + tabsId + '"]');
        if (!$btn.find('.lang-tab-dot').length) {
            $btn.addClass('text-red-600').append('<span class="lang-tab-dot inline-block w-2 h-2 rounded-full bg-red-500 ms-1 align-middle"></span>');
        }
        return { tabsId, lang };
    }
    return null;
}

/**
 * Highlights every invalid input, shows its own message under it and returns
 * a summary the caller can toast. `errors` is Laravel's {field: [messages]}.
 */
function showFieldErrors($form, errors) {
    clearFieldErrors($form);
    const flat = [];
    let firstEl = null;
    let firstTab = null;

    Object.entries(errors || {}).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : messages;
        flat.push(message);
        const $els = fieldElements($form, field);
        if (!$els.length) return;
        const tab = markInvalid($els.first(), message);
        if (!firstEl) { firstEl = $els.first(); firstTab = tab; }
    });

    if (firstTab) activateLangTab(firstTab.tabsId, firstTab.lang);
    if (firstEl && firstEl.length) {
        const el = firstEl[0];
        if (el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        try { el.focus({ preventScroll: true }); } catch (_) { /* hidden/file input */ }
    }
    return flat;
}

function toastErrors(messages) {
    if (!messages.length) return;
    const extra = messages.length > 1 ? ' (+' + (messages.length - 1) + ')' : '';
    window.Toast.error(messages[0] + extra);
}

// Remove a field's error as soon as the user edits it.
$(document).on('input change', '#banner-form .is-invalid', function () {
    $(this).removeClass('is-invalid ring-2 ring-red-500 border-red-500').css({ borderColor: '', boxShadow: '' });
    $(this).next('.' + ERR_CLASS).remove();
});

// ─── Form AJAX submit ─────────────────────────────────────────────────────────

function initFormSubmit() {
    $('#banner-form').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        const btn = $('#save-btn');
        const origTxt = btn.text();

        // Check date validity — report the real message and highlight the dates.
        const $endsAt = $('[name="ends_at"]');
        if ($endsAt[0] && $endsAt[0].validationMessage) {
            const msgs = showFieldErrors($(form), { ends_at: [$endsAt[0].validationMessage] });
            $('[name="starts_at"]').addClass('is-invalid ring-2 ring-red-500 border-red-500')
                .css({ borderColor: '#dc2626', boxShadow: '0 0 0 2px rgba(220,38,38,.25)' });
            toastErrors(msgs);
            return;
        }
        clearFieldErrors($(form));

        btn.prop('disabled', true).text(t('shared.saving'));

        const fd = new FormData(form);

        $.ajax({
            url: $(form).attr('action'),
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
        })
            .done((res) => {
                window.Toast.success(res.message || t('shared.saved'));
                if (res.redirect) window.location.href = res.redirect;
            })
            .fail((xhr) => {
                const body = xhr.responseJSON;
                if (body?.errors) {
                    toastErrors(showFieldErrors($(form), body.errors));
                } else {
                    window.Toast.error(body?.message || t('shared.something_went_wrong'));
                }
            })
            .always(() => btn.prop('disabled', false).text(origTxt));
    });
}

// ─── Utilities ────────────────────────────────────────────────────────────────

function debounce(fn, delay) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
    };
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('banners-table')) {
        initDataTable();
        initStatusTabs();
        initFilters();
        initDeleteModal();
        initDuplicateModal();
    }

    if (document.getElementById('banner-form')) {
        initImagePreviews();
        initImageRemoval();
        initDates();
        initFormSubmit();
    }
});
