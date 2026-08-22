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

function initImagePreviews() {
    // Desktop preview
    $('#desktop-image-input').on('change', function () {
        previewFile(this, '#desktop-preview-img', '#desktop-upload-placeholder');
    });

    // Mobile preview
    $('#mobile-image-input').on('change', function () {
        previewFile(this, '#mobile-preview-img', '#mobile-upload-placeholder');
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
                if (slot === 'desktop') {
                    $('#desktop-preview-img').attr('src', '').addClass('hidden');
                    $('#desktop-upload-placeholder').removeClass('hidden');
                } else {
                    $('#mobile-preview-img').attr('src', '').addClass('hidden');
                    $('#mobile-upload-placeholder').removeClass('hidden');
                }
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

// ─── Form AJAX submit ─────────────────────────────────────────────────────────

function initFormSubmit() {
    $('#banner-form').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        const btn = $('#save-btn');
        const origTxt = btn.text();

        // Check date validity
        const $endsAt = $('[name="ends_at"]');
        if ($endsAt[0] && $endsAt[0].validationMessage) {
            window.Toast.error(t('admin.banners.fix_date_errors'));
            return;
        }

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
                    const firstErr = Object.values(body.errors)[0];
                    window.Toast.error(Array.isArray(firstErr) ? firstErr[0] : firstErr);
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
