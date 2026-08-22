/**
 * resources/js/admin/countries.js
 *
 * Handles:
 *  - Launch / Deactivate / Reactivate country actions
 *  - Delete country confirmation
 *  - Shipping settings AJAX save
 *  - Category override modal
 */

$(function () {
    // ─────────────────────────────────────────────────────────────────────────
    // Launch / Deactivate / Reactivate
    // ─────────────────────────────────────────────────────────────────────────

    $(document).on('click', '#btn-launch', function () {
        const name = $(this).data('country-name');
        const url = $(this).data('url');
        const message = t('admin.countries.launch_country_confirm', { name });
        if (!confirm(message)) return;

        $(this).prop('disabled', true).text(t('admin.countries.launching_label'));

        $.ajax({ url, method: 'POST', headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } })
            .done(function (res) {
                window.Toast && window.Toast.success(res.message);
                setTimeout(() => location.reload(), 1000);
            })
            .fail(function (xhr) {
                window.Toast && window.Toast.error(xhr.responseJSON?.message || t('admin.countries.launch_failed'));
                $('#btn-launch').prop('disabled', false).html('<svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>' + (t('admin.countries.launch_label')));
            });
    });

    $(document).on('click', '#btn-deactivate', function () {
        const url = $(this).data('url');
        if (!confirm(t('admin.countries.deactivate_country_confirm'))) return;

        $.ajax({ url, method: 'POST', headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } })
            .done(function (res) {
                window.Toast && window.Toast.success(res.message);
                setTimeout(() => location.reload(), 800);
            })
            .fail(function (xhr) {
                window.Toast && window.Toast.error(xhr.responseJSON?.message || t('admin.countries.deactivate_failed'));
            });
    });

    $(document).on('click', '#btn-reactivate', function () {
        const url = $(this).data('url');
        $.ajax({ url, method: 'POST', headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } })
            .done(function (res) {
                window.Toast && window.Toast.success(res.message);
                setTimeout(() => location.reload(), 800);
            })
            .fail(function (xhr) {
                window.Toast && window.Toast.error(xhr.responseJSON?.message || t('admin.countries.reactivate_failed'));
            });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Delete country
    // ─────────────────────────────────────────────────────────────────────────

    $(document).on('click', '#btn-delete-country', async function () {
        const name = $(this).data('country-name');
        const url = $(this).data('url');
        const message = t('admin.countries.delete_country_confirm', { name });
        const confirmed = window.confirmDelete
            ? await window.confirmDelete(message, { title: t('admin.countries.delete_country_title') })
            : confirm(message);
        if (!confirmed) return;

        $.ajax({ url, method: 'DELETE', headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } })
            .done(function (res) {
                window.Toast && window.Toast.success(res.message);
                setTimeout(() => window.location.href = '/countries/', 900);
            })
            .fail(function (xhr) {
                window.Toast && window.Toast.error(xhr.responseJSON?.message || t('admin.countries.delete_failed'));
            });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Shipping Settings Save
    // ─────────────────────────────────────────────────────────────────────────

    $(document).on('click', '#btn-save-shipping', function () {
        const url = $(this).data('url');
        const $btn = $(this);

        const formData = {};
        $('#shipping-settings-form').find('[name]').each(function () {
            const name = $(this).attr('name');
            const val = $(this).is('[type=checkbox]') ? (this.checked ? 1 : 0) : $(this).val();
            // Parse settings[0][field] structure
            const match = name.match(/settings\[(\d+)]\[(.+)]/);
            if (match) {
                const idx = match[1];
                const field = match[2];
                if (!formData[idx]) formData[idx] = {};
                formData[idx][field] = val;
            }
        });

        $btn.prop('disabled', true).text(t('shared.saving'));

        $.ajax({
            url,
            method: 'POST',
            data: JSON.stringify({ settings: Object.values(formData) }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        })
            .done(function (res) {
                window.Toast && window.Toast.success(res.message);
            })
            .fail(function (xhr) {
                window.Toast && window.Toast.error(xhr.responseJSON?.message || t('admin.countries.save_failed'));
            })
            .always(function () {
                $btn.prop('disabled', false).html('<svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' + (t('admin.countries.save_shipping_settings_label')));
            });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Category Override Modal
    // ─────────────────────────────────────────────────────────────────────────

    window.tableActions = window.tableActions || {};

    window.tableActions.editCatOverride = function (id, row) {
        const $form = $('#cat-override-form');
        $('#cat-category-id').val(row.id);
        $('#cat-name-display').text(row.name_en + ' / ' + row.name_ar);
        $form.find('[name="overrides[0][is_available]"][type="checkbox"]').prop('checked', !!row.is_available).trigger('change');
        $form.find('[name="overrides[0][commission_fbp_pct]"]').val(row.override_commission_fbp_pct ?? '');
        $form.find('[name="overrides[0][commission_fbp_fixed]"]').val(row.override_commission_fbp_fixed ?? '');
        $form.find('[name="overrides[0][commission_fbn_pct]"]').val(row.override_commission_fbn_pct ?? '');
        $form.find('[name="overrides[0][commission_fbn_fixed]"]').val(row.override_commission_fbn_fixed ?? '');
        $form.find('[name="overrides[0][unavailable_reason]"]').val(row.unavailable_reason ?? '');
        $form.find('[name="overrides[0][notes]"]').val(row.notes ?? '');
        openModal('cat-override-modal');
    };

    $('#cat-override-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const url = $('#cat-override-submit').data('url');
        const data = {
            overrides: [{
                category_id: $('#cat-category-id').val(),
                is_available: $form.find('[name="overrides[0][is_available]"][type="checkbox"]').is(':checked') ? 1 : 0,
                commission_fbp_pct: $form.find('[name="overrides[0][commission_fbp_pct]"]').val() || null,
                commission_fbp_fixed: $form.find('[name="overrides[0][commission_fbp_fixed]"]').val() || null,
                commission_fbn_pct: $form.find('[name="overrides[0][commission_fbn_pct]"]').val() || null,
                commission_fbn_fixed: $form.find('[name="overrides[0][commission_fbn_fixed]"]').val() || null,
                unavailable_reason: $form.find('[name="overrides[0][unavailable_reason]"]').val() || null,
                notes: $form.find('[name="overrides[0][notes]"]').val() || null,
            }],
        };

        $('#cat-override-submit').prop('disabled', true).text(t('shared.saving'));

        $.ajax({
            url,
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        })
            .done(function (res) {
                window.Toast && window.Toast.success(res.message);
                closeModal('cat-override-modal');
                window.reloadDataTable && window.reloadDataTable('categories-override-table');
            })
            .fail(function (xhr) {
                window.Toast && window.Toast.error(xhr.responseJSON?.message || t('admin.countries.save_failed'));
            })
            .always(function () {
                $('#cat-override-submit').prop('disabled', false).text(t('admin.countries.save_btn_label'));
            });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers — thin wrappers around whatever modal system is in use
    // ─────────────────────────────────────────────────────────────────────────

    function openModal(id) { $(`#${id}`).modal('open'); }
    function closeModal(id) { $(`#${id}`).modal('close'); }
});
