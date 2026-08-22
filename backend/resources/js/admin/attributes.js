/**
 * resources/js/admin/attributes.js
 *
 * Attribute module:
 *  - Datatable delete handler
 *  - Inline attribute value add/edit/delete (edit page)
 *  - Dynamic value rows on create page
 *  - AJAX PUT submit for edit mode (same pattern as products.js)
 */

import $ from 'jquery';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function T() {
    return window.TRANSLATIONS || {};
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function isEditMode() {
    return $('#form-mode').val() === 'edit';
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    initDatatableDelete();

    if (document.getElementById('attribute-form')) {
        initFormSubmit();
        initCreateValueRows();
        initEditValueActions();
    }
});

// ─── Datatable: delete button ─────────────────────────────────────────────────

function initDatatableDelete() {
    $(document).on('click', '[data-action="delete"]', async function () {
        const id = $(this).data('id');
        const url = (window.ROUTES_ATTR?.destroy ?? '').replace(':id', id);

        if (!url) return;
        const confirmMessage = t('admin.attributes.delete_attribute_confirm_text');
        const confirmed = window.confirmDelete
            ? await window.confirmDelete(confirmMessage, { title: t('admin.attributes.delete_attribute_title') })
            : window.confirm(confirmMessage);
        if (!confirmed) return;

        $.ajax({
            url: url,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            window.Toast?.success(res.message || t('admin.attributes.attribute_deleted'));
            window.reloadDataTable?.('attributes-table');
        }).fail(function (xhr) {
            window.Toast?.error(xhr.responseJSON?.message || t('admin.attributes.delete_attribute_failed'));
        });
    });
}

// ─── Create page: dynamic value rows ─────────────────────────────────────────

function initCreateValueRows() {
    const addBtn = document.getElementById('add-value-row');
    const rowsEl = document.getElementById('value-rows');
    if (!addBtn || !rowsEl) return;

    let rowCount = 1;

    addBtn.addEventListener('click', function () {
        const idx = rowCount++;
        const isColor = document.querySelector('[name="type"]')?.value === 'color';
        const html = `
            <div class="value-row grid grid-cols-12 gap-2 items-end">
                <div class="col-span-4">
                    <label class="text-xs font-medium text-gray-600">${esc(t('admin.attributes.value_en_label'))}</label>
                    <input type="text" name="values[${idx}][value_en]" class="input w-full mt-1" placeholder="${esc(T().valueEnPlaceholder || 'English')}" />
                </div>
                <div class="col-span-4">
                    <label class="text-xs font-medium text-gray-600">${esc(t('admin.attributes.value_ar_label'))}</label>
                    <input type="text" name="values[${idx}][value_ar]" class="input w-full mt-1" placeholder="${esc(T().valueArPlaceholder || 'العربية')}" dir="rtl" />
                </div>
                <div class="col-span-3" ${isColor ? '' : 'style="display:none"'}>
                    <label class="text-xs font-medium text-gray-600">${esc(t('admin.attributes.hex_color_label'))}</label>
                    <input type="color" name="values[${idx}][color_hex]" class="w-full h-9 mt-1 rounded border border-gray-300 cursor-pointer" value="#000000" />
                </div>
                <input type="hidden" name="values[${idx}][sort_order]" value="${idx}" />
                <div class="col-span-1 flex items-end pb-0.5">
                    <button type="button" class="remove-value-row text-gray-300 hover:text-red-500 transition-colors" title="${esc(T().remove || 'Remove')}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>`;
        rowsEl.insertAdjacentHTML('beforeend', html);
    });

    rowsEl.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-value-row');
        if (!btn) return;
        btn.closest('.value-row')?.remove();
    });
}

// ─── Edit page: AJAX value add / edit / delete ────────────────────────────────

function initEditValueActions() {
    if (!window.ROUTES_ATTR_EDIT) return;

    // Save new value
    document.getElementById('save-new-value')?.addEventListener('click', function () {
        const valueEn = document.getElementById('new-value-en')?.value.trim();
        const valueAr = document.getElementById('new-value-ar')?.value.trim();
        const slug = document.getElementById('new-value-slug')?.value.trim();
        const colorHex = document.getElementById('new-color-hex-text')?.value.trim()
            || document.getElementById('new-color-hex')?.value || null;

        if (!valueEn) {
            window.Toast?.error(t('admin.attributes.english_value_required'));
            return;
        }

        $.ajax({
            url: window.ROUTES_ATTR_EDIT.storeValue,
            method: 'POST',
            data: JSON.stringify({ value_en: valueEn, value_ar: valueAr || null, slug: slug || null, color_hex: colorHex || null }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            const v = res.value;
            const html = buildValueRow(v);
            const list = document.getElementById('values-list');
            const emptyMsg = list?.querySelector('p');
            if (emptyMsg) emptyMsg.remove();
            list?.insertAdjacentHTML('beforeend', html);
            // Clear inputs
            if (document.getElementById('new-value-en')) document.getElementById('new-value-en').value = '';
            if (document.getElementById('new-value-ar')) document.getElementById('new-value-ar').value = '';
            if (document.getElementById('new-value-slug')) document.getElementById('new-value-slug').value = '';
            window.Toast?.success(t('admin.attributes.value_added'));
        }).fail(function (xhr) {
            const errors = xhr.responseJSON?.errors ?? {};
            const msg = Object.values(errors).flat()[0];
            window.Toast?.error(msg || xhr.responseJSON?.message || t('admin.attributes.add_value_failed'));
        });
    });

    // Sync color text <-> picker
    document.getElementById('new-color-hex')?.addEventListener('input', function () {
        const textInput = document.getElementById('new-color-hex-text');
        if (textInput) textInput.value = this.value;
    });
    document.getElementById('new-color-hex-text')?.addEventListener('input', function () {
        const picker = document.getElementById('new-color-hex');
        if (picker && /^#[0-9A-Fa-f]{6}$/.test(this.value)) picker.value = this.value;
    });

    // Delete value
    $(document).on('click', '.delete-value-btn', async function () {
        const id = $(this).data('id');
        const url = $(this).data('url');
        const confirmMessage = t('admin.attributes.delete_value_confirm');
        const confirmed = window.confirmDelete
            ? await window.confirmDelete(confirmMessage, { title: t('admin.attributes.delete_value_title') })
            : window.confirm(confirmMessage);
        if (!confirmed) return;

        $.ajax({
            url: url,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            $(`[data-id="${id}"].value-item`).remove();
            window.Toast?.success(res.message || t('admin.attributes.value_deleted'));
        }).fail(function (xhr) {
            window.Toast?.error(xhr.responseJSON?.message || t('admin.attributes.delete_value_failed'));
        });
    });

    // Edit value (inline quick-edit via prompt for simplicity; a modal would be overkill)
    $(document).on('click', '.edit-value-btn', function () {
        const id = $(this).data('id');
        const valueEn = $(this).data('value-en');
        const newEn = window.prompt(t('admin.attributes.prompt_english_value'), valueEn);
        if (newEn === null || newEn.trim() === '') return;

        const valueAr = $(this).data('value-ar');
        const newAr = window.prompt(t('admin.attributes.prompt_arabic_value'), valueAr ?? '');

        const currentSlug = $(this).data('slug');
        const newSlug = window.prompt(t('admin.attributes.prompt_slug'), currentSlug ?? '');
        if (newSlug === null) return;

        const colorHex = $(this).data('color-hex');
        const regenerateUrl = $(this).data('regenerate-url');

        const url = (window.ROUTES_ATTR_EDIT.updateValue || '').replace(':value_id', id);

        $.ajax({
            url: url,
            method: 'PUT',
            data: JSON.stringify({
                value_en: newEn.trim(),
                value_ar: newAr?.trim() || null,
                slug: newSlug.trim() || null,
                color_hex: colorHex || null,
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            const item = document.querySelector(`.value-item[data-id="${id}"]`);
            if (item) {
                item.querySelector('span.flex-1').textContent = newEn.trim();
                const slugEl = item.querySelector('.value-slug');
                if (slugEl && res.slug) slugEl.textContent = res.slug;
            }
            $(`.edit-value-btn[data-id="${id}"]`).data('value-en', newEn.trim()).data('value-ar', newAr?.trim()).data('slug', res.slug);
            window.Toast?.success(t('admin.attributes.value_updated'));

            if (res.slug_changed && res.affected_variants > 0) {
                showSlugWarning(id, res.affected_variants, regenerateUrl);
            }
        }).fail(function (xhr) {
            const errors = xhr.responseJSON?.errors ?? {};
            const msg = Object.values(errors).flat()[0];
            window.Toast?.error(msg || xhr.responseJSON?.message || t('admin.attributes.update_value_failed'));
        });
    });

    // Regenerate variant slugs
    $(document).on('click', '.regenerate-slugs-btn', function () {
        const id = $(this).data('id');
        const url = $(`.edit-value-btn[data-id="${id}"]`).data('regenerate-url');
        if (!url) return;

        const $btn = $(this).prop('disabled', true);

        $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            window.Toast?.success(res.message || t('admin.attributes.variant_slugs_regenerated'));
            document.querySelector(`.value-slug-warning[data-id="${id}"]`)?.classList.add('hidden');
        }).fail(function (xhr) {
            window.Toast?.error(xhr.responseJSON?.message || t('admin.attributes.regenerate_slugs_failed'));
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
}

function showSlugWarning(id, count, regenerateUrl) {
    const banner = document.querySelector(`.value-slug-warning[data-id="${id}"]`);
    if (!banner) return;
    const textEl = banner.querySelector('.warning-text');
    if (textEl) {
        textEl.textContent = t('admin.attributes.slug_change_impact_warning', { count }).replace(':count', count);
    }
    banner.classList.remove('hidden');
}

function buildValueRow(v) {
    const destroyUrl = (window.ROUTES_ATTR_EDIT?.destroyValue ?? '').replace(':value_id', v.id);
    const regenerateUrl = (window.ROUTES_ATTR_EDIT?.regenerateVariantSlugs ?? '').replace(':value_id', v.id);
    return `
        <div class="flex items-center gap-3 px-4 py-2.5 bg-white value-item" data-id="${v.id}">
            <span class="flex-1 text-sm text-gray-800">${esc(v.value_en)}</span>
            <span class="text-sm text-gray-400" dir="rtl">${esc(v.value_ar ?? '')}</span>
            <span class="text-xs font-mono text-gray-400 value-slug">${esc(v.slug ?? '')}</span>
            ${v.color_hex ? `<span class="w-5 h-5 rounded-full border border-gray-200 flex-shrink-0" style="background:${esc(v.color_hex)}"></span>` : ''}
            <button type="button"
                class="edit-value-btn text-xs text-primary-600 hover:underline"
                data-id="${esc(v.id)}"
                data-value-en="${esc(v.value_en)}"
                data-value-ar="${esc(v.value_ar ?? '')}"
                data-slug="${esc(v.slug ?? '')}"
                data-color-hex="${esc(v.color_hex ?? '')}"
                data-regenerate-url="${esc(regenerateUrl)}">${esc(T().edit || 'Edit')}</button>
            <button type="button"
                class="delete-value-btn text-xs text-red-500 hover:underline"
                data-id="${esc(v.id)}"
                data-url="${esc(destroyUrl)}">${esc(T().delete || 'Delete')}</button>
        </div>
        <div class="value-slug-warning hidden px-4 py-2 bg-amber-50 border-t border-amber-200 text-xs text-amber-800 flex items-center justify-between gap-3" data-id="${esc(v.id)}">
            <span class="warning-text"></span>
            <button type="button" class="regenerate-slugs-btn btn btn-xs btn-outline text-amber-800 border-amber-300 hover:bg-amber-100 whitespace-nowrap" data-id="${esc(v.id)}">
                ${esc(t('admin.attributes.regenerate_variant_slugs_label'))}
            </button>
        </div>`;
}

function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str ?? '')));
    return d.innerHTML;
}

// ─── Form submit (AJAX PUT for edit — same pattern as products.js) ────────────

function initFormSubmit() {
    if (!isEditMode()) return; // Create uses standard HTML POST

    $('#attribute-form').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('#submit-btn').prop('disabled', true).text(T().savingEllipsis || 'Saving…');
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
                window.Toast?.success(res.message || t('admin.attributes.attribute_saved'));
            })
            .fail(function (xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON?.errors ?? {};
                    const msgs = Object.values(errors).flat();
                    window.Toast?.error(msgs[0] || t('admin.attributes.validation_error'));
                } else {
                    window.Toast?.error(xhr.responseJSON?.message || t('admin.attributes.save_failed_retry'));
                }
            })
            .always(function () {
                $btn.prop('disabled', false).text(t('admin.attributes.save_changes_label'));
            });
    });
}
