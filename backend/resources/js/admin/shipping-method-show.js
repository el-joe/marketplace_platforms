import $ from 'jquery';

// ─── Utilities ────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function sendJson(url, method, data = {}) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(data),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw json;
    return json;
}

function toast(message, type = 'success') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}

function toCents(value) {
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed < 0) return 0;
    return Math.round(parsed * 100);
}

// ─── Method Detail Form ─────────────────────────────────────────────────────

function initMethodDetailForm() {
    const $form = $('#method-detail-form');
    if (!$form.length) return;

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#method-id').val();

        const payload = {
            name: $form.find('[name="name"]').val(),
            description: $form.find('[name="description"]').val() || null,
            min_delivery_days: parseInt($form.find('[name="min_delivery_days"]').val()),
            max_delivery_days: parseInt($form.find('[name="max_delivery_days"]').val()),
            order_cutoff_time: $form.find('[name="order_cutoff_time"]').val() || null,
            handling_time_hours: $form.find('[name="handling_time_hours"]').val() || null,
            display_priority: $form.find('[name="display_priority"]').val() || null,
            badge_label_en: $form.find('[name="badge_label_en"]').val() || null,
            badge_label_ar: $form.find('[name="badge_label_ar"]').val() || null,
            badge_color_hex: $form.find('[name="badge_color_hex"]').val() || null,
            badge_text_color_hex: $form.find('[name="badge_text_color_hex"]').val() || null,
            delivery_label_en: $form.find('[name="delivery_label_en"]').val() || null,
            delivery_label_ar: $form.find('[name="delivery_label_ar"]').val() || null,
            is_express_type: $form.find('[name="is_express_type"]').is(':checked') ? 1 : 0,
            show_estimated_price: $form.find('[name="show_estimated_price"]').is(':checked') ? 1 : 0,
            is_active: $form.find('[name="is_active"]').is(':checked') ? 1 : 0,
        };

        try {
            await sendJson(`/shipping-methods/methods/${id}`, 'PUT', payload);
            toast(window.TRANSLATIONS?.shippingMethodUpdated || 'Shipping method updated.');
        } catch (err) {
            if (err.errors) {
                window.injectValidationErrors?.($form, err.errors);
            } else {
                toast(err.message ?? (window.TRANSLATIONS?.saveFailed || 'Save failed.'), 'error');
            }
        }
    });
}

function initToggleMethod() {
    $(document).on('click', '.btn-toggle-method', async function () {
        const $btn = $(this);
        const id = $btn.data('id');

        try {
            const res = await sendJson(`/shipping-methods/methods/${id}/toggle`, 'POST');
            const active = res.is_active;

            $btn
                .data('active', active ? '1' : '0')
                .text(active ? (window.TRANSLATIONS?.active || 'Active') : (window.TRANSLATIONS?.inactive || 'Inactive'))
                .toggleClass('bg-success-50 text-success-700 hover:bg-success-100', active)
                .toggleClass('bg-gray-100 text-gray-500 hover:bg-gray-200', !active);
        } catch {
            toast(window.TRANSLATIONS?.failedToUpdateStatus || 'Failed to update status.', 'error');
        }
    });
}

// ─── Rate Modal ───────────────────────────────────────────────────────────────

function initRateModal() {
    const $modal = $('#rate-modal');
    const $form = $('#rate-form');
    if (!$form.length) return;

    $('#btn-add-rate').on('click', function () {
        $form[0].reset();
        $('#rate-id').val('');
        $('#rate-http').val('POST');
        $('#rate-base-fee, #rate-per-kg, #rate-free-threshold, #rate-cod-fee').val('');
        $modal.modal('open');
    });

    $(document).on('click', '.btn-edit-rate', function () {
        const row = $(this).data('row');

        $form[0].reset();
        $('#rate-id').val(row.id);
        $('#rate-http').val('PUT');

        $form.find('[name="destination_zone_id"]').val(row.destination_zone_id);
        $form.find('[name="carrier_id"]').val(row.carrier_id ?? '');
        $form.find('[name="origin_zone_id"]').val(row.origin_zone_id ?? '');
        $form.find('[name="min_weight_grams"]').val(row.min_weight_grams ?? '');
        $form.find('[name="volumetric_divisor"]').val(row.volumetric_divisor ?? '');

        $('#rate-base-fee-display').val(row.base_fee ? (row.base_fee / 100).toFixed(2) : '');
        $('#rate-base-fee').val(row.base_fee ?? 0);
        $('#rate-per-kg-display').val(row.rate_per_kg ? (row.rate_per_kg / 100).toFixed(2) : '');
        $('#rate-per-kg').val(row.rate_per_kg ?? 0);
        $('#rate-free-threshold-display').val(row.free_shipping_threshold ? (row.free_shipping_threshold / 100).toFixed(2) : '');
        $('#rate-free-threshold').val(row.free_shipping_threshold ?? '');
        $('#rate-cod-fee-display').val(row.cod_extra_fee ? (row.cod_extra_fee / 100).toFixed(2) : '');
        $('#rate-cod-fee').val(row.cod_extra_fee ?? 0);

        const $toggle = $form.find('[name="is_active"]');
        $toggle.prop('checked', !!row.is_active).trigger('change');

        $modal.modal('open');
    });

    const rateMoneyFields = [
        { display: '#rate-base-fee-display', hidden: '#rate-base-fee' },
        { display: '#rate-per-kg-display', hidden: '#rate-per-kg' },
        { display: '#rate-free-threshold-display', hidden: '#rate-free-threshold' },
        { display: '#rate-cod-fee-display', hidden: '#rate-cod-fee' },
    ];
    rateMoneyFields.forEach(({ display, hidden }) => {
        $(display).on('input', function () {
            $(hidden).val(toCents($(this).val()));
        });
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#rate-id').val();
        const method = $('#rate-http').val();
        const url = id ? `/shipping-methods/rates/${id}` : '/shipping-methods/rates';

        const payload = {
            shipping_method_id: window.METHOD_ID,
            destination_zone_id: $form.find('[name="destination_zone_id"]').val(),
            carrier_id: $form.find('[name="carrier_id"]').val() || null,
            origin_zone_id: $form.find('[name="origin_zone_id"]').val() || null,
            base_fee: parseInt($('#rate-base-fee').val()) || 0,
            rate_per_kg: parseInt($('#rate-per-kg').val()) || 0,
            min_weight_grams: $form.find('[name="min_weight_grams"]').val() || null,
            volumetric_divisor: $form.find('[name="volumetric_divisor"]').val() || null,
            free_shipping_threshold: parseInt($('#rate-free-threshold').val()) || null,
            cod_extra_fee: parseInt($('#rate-cod-fee').val()) || 0,
            is_active: $form.find('[name="is_active"]').is(':checked') ? 1 : 0,
        };

        try {
            await sendJson(url, method, payload);
            toast(id ? (window.TRANSLATIONS?.shippingRateUpdated || 'Shipping rate updated.') : (window.TRANSLATIONS?.shippingRateCreated || 'Shipping rate created.'));
            $modal.modal('close');
            setTimeout(() => window.location.reload(), 500);
        } catch (err) {
            if (err.errors) {
                window.injectValidationErrors?.($form, err.errors);
            } else {
                toast(err.message ?? (window.TRANSLATIONS?.saveFailed || 'Save failed.'), 'error');
            }
        }
    });
}

// ─── Delete / Toggle Rate ──────────────────────────────────────────────────────

function initDeleteRate() {
    const $deleteModal = $('#delete-rate-modal');
    if (!$deleteModal.length) return;

    $(document).on('click', '.btn-delete-rate', function () {
        $('#delete-rate-id').val($(this).data('id'));
        $deleteModal.modal('open');
    });

    $('#btn-confirm-delete-rate').on('click', async function () {
        const id = $('#delete-rate-id').val();

        try {
            await sendJson(`/shipping-methods/rates/${id}`, 'DELETE');
            toast(window.TRANSLATIONS?.shippingRateDeleted || 'Shipping rate deleted.');
            $deleteModal.modal('close');
            $(`tr[data-rate-id="${id}"]`).remove();
        } catch {
            toast(window.TRANSLATIONS?.deleteFailed || 'Delete failed.', 'error');
        }
    });
}

function initToggleRate() {
    $(document).on('click', '.btn-toggle-rate', async function () {
        const $btn = $(this);
        const id = $btn.data('id');

        try {
            const res = await sendJson(`/shipping-methods/rates/${id}/toggle`, 'POST');
            const active = res.is_active;

            $btn
                .data('active', active ? '1' : '0')
                .text(active ? (window.TRANSLATIONS?.active || 'Active') : (window.TRANSLATIONS?.inactive || 'Inactive'))
                .toggleClass('bg-success-50 text-success-700 hover:bg-success-100', active)
                .toggleClass('bg-gray-100 text-gray-500 hover:bg-gray-200', !active);
        } catch {
            toast(window.TRANSLATIONS?.failedToUpdateRateStatus || 'Failed to update rate status.', 'error');
        }
    });
}

// ─── Init ─────────────────────────────────────────────────────────────────────

$(function () {
    initMethodDetailForm();
    initToggleMethod();
    initRateModal();
    initDeleteRate();
    initToggleRate();
});
