/**
 * resources/js/admin/custom-pages.js
 *
 * Custom Pages module:
 *  - Category picker (search -> append to list -> remove), mirrors the
 *    page-builder category_pills picker UX but is self-contained in the
 *    form (no per-click AJAX persistence — selections are submitted with
 *    the rest of the form as category_ids[]).
 *  - AJAX PUT submit for edit mode (same pattern as categories.js)
 *  - Index page: toggle-active / delete (AJAX)
 */

import $ from 'jquery';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function isEditMode() {
    return $('#form-mode').val() === 'edit';
}

function t(key) {
    return window.TRANSLATIONS?.[key] || key;
}

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('custom-page-form')) {
        initCategoryPicker();
        initFormSubmit();
    }

    if (document.getElementById('custom-pages-table')) {
        initToggleActive();
        initDelete();
    }
});

// ─── Category picker ───────────────────────────────────────────────────────

let selectedCategories = [];

function initCategoryPicker() {
    const $hidden = $('#selected-category-ids');
    try {
        selectedCategories = JSON.parse($hidden.attr('data-initial') || '[]');
    } catch (e) {
        selectedCategories = [];
    }
    renderSelectedCategories();

    const $search = $('#custom-page-category-search');
    const $results = $('#custom-page-category-results');

    $search.on('input', function () {
        const q = $(this).val().trim();
        clearTimeout($search.data('searchTimer'));
        if (q.length < 2) {
            $results.addClass('hidden').empty();
            return;
        }
        $search.data('searchTimer', setTimeout(() => {
            $.ajax({
                url: window.ROUTES_CUSTOM_PAGE.searchCategories,
                method: 'GET',
                data: { q },
            }).done((res) => {
                const rows = (res.results || []).filter((r) => !selectedCategories.some((c) => c.id === r.id));
                if (!rows.length) {
                    $results.removeClass('hidden').html(`<div class="px-3 py-2 text-gray-400">${t('admin.page_builder.no_results')}</div>`);
                    return;
                }
                $results.removeClass('hidden').html(rows.map((row) => `
                    <button type="button" class="w-full text-left px-3 py-2 hover:bg-gray-50" data-add-category-id="${row.id}" data-add-category-text="${escapeHtml(row.text || '')}">
                        ${escapeHtml(row.text || '')}
                    </button>
                `).join(''));
            });
        }, 300));
    });

    $results.on('click', '[data-add-category-id]', function () {
        const id = $(this).data('add-category-id');
        const text = $(this).data('add-category-text');
        if (!selectedCategories.some((c) => c.id === id)) {
            selectedCategories.push({ id, text });
            renderSelectedCategories();
        }
        $results.addClass('hidden').empty();
        $search.val('');
    });

    $('#custom-page-category-list').on('click', '[data-remove-category-id]', function () {
        const id = $(this).data('remove-category-id');
        selectedCategories = selectedCategories.filter((c) => c.id !== id);
        renderSelectedCategories();
    });
}

function renderSelectedCategories() {
    const $list = $('#custom-page-category-list');
    $('#category-ids-inputs').remove();

    if (!selectedCategories.length) {
        $list.html(`<div class="text-xs text-gray-400 px-2 py-3 text-center">${t('admin.custom_pages.no_categories_yet')}</div>`);
    } else {
        $list.html(selectedCategories.map((c) => `
            <div class="flex items-center gap-2 px-2 py-1.5 border border-gray-100 rounded hover:bg-gray-50">
                <span class="flex-1 truncate text-sm text-gray-700">${escapeHtml(c.text || '')}</span>
                <button type="button" class="text-xs text-rose-500 hover:text-rose-700" data-remove-category-id="${c.id}">${window.TRANSLATIONS?.['admin.remove'] || 'Remove'}</button>
            </div>
        `).join(''));
    }

    const inputs = selectedCategories.map((c) => `<input type="hidden" name="category_ids[]" value="${c.id}">`).join('');
    $list.after(`<div id="category-ids-inputs">${inputs}</div>`);
}

function escapeHtml(str) {
    return $('<div>').text(str).html();
}

// ─── Form submit (edit mode via AJAX, create mode via standard POST) ───────

function initFormSubmit() {
    if (!isEditMode()) return;

    $('#custom-page-form').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('#submit-btn').prop('disabled', true);
        const formData = new FormData(this);
        formData.set('_method', 'PUT');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        })
            .done(function (res) {
                window.Toast?.success(res.message || 'Saved.');
            })
            .fail(function (xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON?.errors ?? {};
                    const msgs = Object.values(errors).flat();
                    window.Toast?.error(msgs[0] || 'Validation error.');
                } else {
                    window.Toast?.error(xhr.responseJSON?.message || 'Save failed, please retry.');
                }
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });
}

// ─── Index page actions ─────────────────────────────────────────────────────

function initToggleActive() {
    $('#custom-pages-table').on('click', '.js-toggle-active', function () {
        const id = $(this).data('id');
        $.ajax({
            url: `/admin/custom-pages/${id}/toggle-active`,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(() => window.location.reload());
    });
}

function initDelete() {
    $('#custom-pages-table').on('click', '.js-delete-custom-page', function () {
        const id = $(this).data('id');
        if (!window.confirm(window.TRANSLATIONS?.['admin.custom_pages.confirm_delete'] || 'Delete this custom page?')) return;
        $.ajax({
            url: `/admin/custom-pages/${id}`,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(() => window.location.reload())
            .fail((xhr) => window.Toast?.error(xhr.responseJSON?.message || 'Delete failed.'));
    });
}
