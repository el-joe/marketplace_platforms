import $ from 'jquery';
import DataTable from 'datatables.net';

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

function toAmount(value) {
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed < 0) return 0;
    return Math.round(parsed);
}

// ─── Rates DataTable ──────────────────────────────────────────────────────────

let ratesTable = null;

function initRatesTable() {
    const tableEl = document.getElementById('shipping-rates-table');
    if (!tableEl) return;

    ratesTable = new DataTable('#shipping-rates-table', {
        processing: true,
        serverSide: true,
        order: [[0, 'asc']],
        ajax: {
            url: '/shipping-settings/rates/datatable',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            data(d) {
                d.shipping_method_id = document.getElementById('filter-method')?.value ?? '';
                d.carrier_id = document.getElementById('filter-carrier')?.value ?? '';
                d.zone_id = document.getElementById('filter-zone')?.value ?? '';
            },
        },
        columns: [
            { data: 'zone_name' },
            { data: 'method_name' },
            { data: 'carrier_name' },
            { data: 'base_fee_formatted', className: 'text-right' },
            { data: 'rate_per_kg_formatted', className: 'text-right' },
            { data: 'free_threshold_formatted', className: 'text-right' },
            { data: 'cod_fee_formatted', className: 'text-right' },
            { data: 'active_badge', className: 'text-center', orderable: false },
            {
                data: 'id',
                className: 'text-center',
                orderable: false,
                render(id, type, row) {
                    return `<div class="flex items-center justify-center gap-1">
                        <button type="button" class="btn-edit-rate p-1 rounded text-gray-400 hover:text-primary-600"
                                data-id="${id}" data-row='${JSON.stringify(row)}'>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button type="button" class="btn-toggle-rate p-1 rounded text-gray-400 hover:text-yellow-600"
                                data-id="${id}" data-active="${row.is_active ? '1' : '0'}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                            </svg>
                        </button>
                        <button type="button" class="btn-delete-rate p-1 rounded text-gray-400 hover:text-danger-600"
                                data-id="${id}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>`;
                },
            },
        ],
        pageLength: 25,
    });

    // Apply filters button
    document.getElementById('btn-apply-rate-filters')?.addEventListener('click', () => {
        ratesTable.draw();
    });

    // Auto-apply on select change
    ['filter-method', 'filter-carrier', 'filter-zone'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => ratesTable.draw());
    });
}

// ─── Rate Modal ───────────────────────────────────────────────────────────────

function initRateModal() {
    const $modal = $('#rate-modal');
    const $form = $('#rate-form');

    $('#btn-add-rate').on('click', function () {
        $form[0].reset();
        $('#rate-id').val('');
        $('#rate-http').val('POST');
        // Clear hidden amount fields
        $('#rate-base-fee, #rate-per-kg, #rate-free-threshold, #rate-cod-fee').val('');
        $modal.modal('open');
    });

    $(document).on('click', '.btn-edit-rate', function () {
        const row = $(this).data('row');

        $form[0].reset();
        $('#rate-id').val(row.id);
        $('#rate-http').val('PUT');

        $form.find('[name="shipping_method_id"]').val(row.shipping_method_id);
        $form.find('[name="destination_zone_id"]').val(row.destination_zone_id);
        $form.find('[name="carrier_id"]').val(row.carrier_id ?? '');
        $form.find('[name="origin_zone_id"]').val(row.origin_zone_id ?? '');
        $form.find('[name="min_weight_grams"]').val(row.min_weight_grams ?? '');
        $form.find('[name="volumetric_divisor"]').val(row.volumetric_divisor ?? '');

        // Money amount → display
        $('#rate-base-fee-display').val(row.base_fee ?? '');
        $('#rate-base-fee').val(row.base_fee ?? 0);
        $('#rate-per-kg-display').val(row.rate_per_kg ?? '');
        $('#rate-per-kg').val(row.rate_per_kg ?? 0);
        $('#rate-free-threshold-display').val(row.free_shipping_threshold ?? '');
        $('#rate-free-threshold').val(row.free_shipping_threshold ?? '');
        $('#rate-cod-fee-display').val(row.cod_extra_fee ?? '');
        $('#rate-cod-fee').val(row.cod_extra_fee ?? 0);

        const $toggle = $form.find('[name="is_active"]');
        $toggle.prop('checked', !!row.is_active).trigger('change');

        $modal.modal('open');
    });

    // Sync display → hidden amount fields
    const rateMoneyFields = [
        { display: '#rate-base-fee-display', hidden: '#rate-base-fee' },
        { display: '#rate-per-kg-display', hidden: '#rate-per-kg' },
        { display: '#rate-free-threshold-display', hidden: '#rate-free-threshold' },
        { display: '#rate-cod-fee-display', hidden: '#rate-cod-fee' },
    ];
    rateMoneyFields.forEach(({ display, hidden }) => {
        $(display).on('input', function () {
            $(hidden).val(toAmount($(this).val()));
        });
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#rate-id').val();
        const method = $('#rate-http').val();
        const url = id ? `/shipping-settings/rates/${id}` : '/shipping-settings/rates';

        const payload = {
            shipping_method_id: $form.find('[name="shipping_method_id"]').val(),
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
            toast(id ? (t('admin.shipping_settings.shipping_rate_updated')) : (t('admin.shipping_settings.shipping_rate_created')));
            $modal.modal('close');
            ratesTable?.draw();
        } catch (err) {
            if (err.errors) {
                window.injectValidationErrors?.($form, err.errors);
            } else {
                toast(err.message ?? (t('admin.shipping_settings.save_failed')), 'error');
            }
        }
    });
}

// ─── Delete Rate ──────────────────────────────────────────────────────────────

function initDeleteRate() {
    const $deleteModal = $('#delete-rate-modal');

    $(document).on('click', '.btn-delete-rate', function () {
        $('#delete-rate-id').val($(this).data('id'));
        $deleteModal.modal('open');
    });

    $('#btn-confirm-delete-rate').on('click', async function () {
        const id = $('#delete-rate-id').val();

        try {
            await sendJson(`/shipping-settings/rates/${id}`, 'DELETE');
            toast(t('admin.shipping_settings.shipping_rate_deleted'));
            $deleteModal.modal('close');
            ratesTable?.draw();
        } catch {
            toast(t('admin.shipping_settings.delete_failed'), 'error');
        }
    });
}

// ─── Toggle Rate ──────────────────────────────────────────────────────────────

function initToggleRate() {
    $(document).on('click', '.btn-toggle-rate', async function () {
        const id = $(this).data('id');

        try {
            await sendJson(`/shipping-settings/rates/${id}/toggle`, 'POST');
            ratesTable?.draw();
        } catch {
            toast(t('admin.shipping_settings.failed_update_rate_status'), 'error');
        }
    });
}

// ─── Carrier Modal ────────────────────────────────────────────────────────────

function initCarrierModal() {
    const $modal = $('#carrier-modal');
    const $form = $('#carrier-form');

    $('#btn-add-carrier').on('click', function () {
        $form[0].reset();
        $('#carrier-id').val('');
        $('#carrier-http').val('POST');
        $modal.modal('open');
    });

    $(document).on('click', '.btn-edit-carrier', function () {
        const row = $(this).data('row');

        $form[0].reset();
        $('#carrier-id').val(row.id);
        $('#carrier-http').val('PUT');

        $form.find('[name="name"]').val(row.name);
        $form.find('[name="code"]').val(row.code);
        $form.find('[name="shipping_company_id"]').val(row.shipping_company_id ?? '');
        $form.find('[name="country_id"]').val(row.country_id ?? '');
        $form.find('[name="api_endpoint"]').val(row.api_endpoint ?? '');
        $form.find('[name="tracking_url_pattern"]').val(row.tracking_url_pattern ?? '');
        $('#carrier-credentials').val('');  // Never pre-fill credentials

        const toggleCod = $form.find('[name="supports_cod"]');
        const toggleReturns = $form.find('[name="supports_returns"]');
        const toggleActive = $form.find('[name="is_active"]');

        toggleCod.prop('checked', !!row.supports_cod).trigger('change');
        toggleReturns.prop('checked', !!row.supports_returns).trigger('change');
        toggleActive.prop('checked', !!row.is_active).trigger('change');

        $modal.modal('open');
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#carrier-id').val();
        const method = $('#carrier-http').val();
        const url = id ? `/shipping-settings/carriers/${id}` : '/shipping-settings/carriers';

        const credentials = $('#carrier-credentials').val().trim();
        const payload = {
            name: $form.find('[name="name"]').val(),
            shipping_company_id: $form.find('[name="shipping_company_id"]').val() || null,
            country_id: $form.find('[name="country_id"]').val() || null,
            api_endpoint: $form.find('[name="api_endpoint"]').val() || null,
            tracking_url_pattern: $form.find('[name="tracking_url_pattern"]').val() || null,
            supports_cod: $form.find('[name="supports_cod"]').is(':checked') ? 1 : 0,
            supports_returns: $form.find('[name="supports_returns"]').is(':checked') ? 1 : 0,
            is_active: $form.find('[name="is_active"]').is(':checked') ? 1 : 0,
        };

        if (!id) {
            payload.code = $form.find('[name="code"]').val();
        }

        if (credentials) {
            payload.credentials = credentials;
        }

        try {
            await sendJson(url, method, payload);
            toast(id ? (t('admin.shipping_settings.carrier_updated')) : (t('admin.shipping_settings.carrier_created')));
            $modal.modal('close');
            setTimeout(() => window.location.reload(), 500);
        } catch (err) {
            if (err.errors) {
                window.injectValidationErrors?.($form, err.errors);
            } else {
                toast(err.message ?? (t('admin.shipping_settings.save_failed')), 'error');
            }
        }
    });
}

// ─── Toggle Carrier ───────────────────────────────────────────────────────────

function initToggleCarrier() {
    $(document).on('click', '.btn-toggle-carrier', async function () {
        const $btn = $(this);
        const id = $btn.data('id');

        try {
            const res = await sendJson(`/shipping-settings/carriers/${id}/toggle`, 'POST');
            const active = res.is_active;

            $btn
                .data('active', active ? '1' : '0')
                .text(active ? (t('admin.shipping_settings.active_label')) : (t('admin.shipping_settings.inactive_label')))
                .toggleClass('bg-success-50 text-success-700 hover:bg-success-100', active)
                .toggleClass('bg-gray-100 text-gray-500 hover:bg-gray-200', !active);
        } catch {
            toast(t('admin.shipping_settings.failed_update_carrier_status'), 'error');
        }
    });
}

// ─── Test Carrier ─────────────────────────────────────────────────────────────

function initTestCarrier() {
    $(document).on('click', '.btn-test-carrier', async function () {
        const $btn = $(this);
        const code = $btn.data('code');
        const id = $btn.data('id');
        const $status = $(`#carrier-status-${id}`);

        $btn.prop('disabled', true).text(t('admin.shipping_settings.testing_label'));

        try {
            const res = await sendJson('/shipping-settings/carriers/test', 'POST', { code });
            const data = res.data ?? {};

            $status.text(data.success
                ? `✓ ${data.latency_ms ?? 0}ms`
                : `✗ ${data.message ?? (t('admin.shipping_settings.test_failed_label'))}`
            ).addClass(data.success ? 'text-success-600' : 'text-danger-600');
        } catch {
            $status.text('✗ ' + (t('admin.shipping_settings.error_label'))).addClass('text-danger-600');
        } finally {
            $btn.prop('disabled', false).text(t('admin.shipping_settings.test_label'));
        }
    });
}

// ─── Country Settings ─────────────────────────────────────────────────────────

function initCountrySettings() {
    // Toggle shipping for country + method
    $(document).on('click', '.btn-toggle-shipping', async function () {
        const $btn = $(this);
        const countryId = $btn.data('country-id');
        const methodId = $btn.data('method-id');
        const active = $btn.data('active') === '1' || $btn.data('active') === 1;

        try {
            await sendJson('/shipping-settings/country-settings', 'POST', {
                country_id: countryId,
                shipping_method_id: methodId,
                is_active: active ? 0 : 1,
            });

            const newActive = !active;
            $btn
                .data('active', newActive ? '1' : '0')
                .text(newActive ? '✓' : '—')
                .toggleClass('bg-success-50 text-success-700 hover:bg-success-100', newActive)
                .toggleClass('bg-gray-100 text-gray-500 hover:bg-gray-200', !newActive);
        } catch {
            toast(t('admin.shipping_settings.failed_update_shipping_setting'), 'error');
        }
    });

    // Free shipping threshold save on blur
    let thresholdTimeout = null;
    $(document).on('change blur', '.input-free-threshold', async function () {
        const $input = $(this);
        const countryId = $input.data('country-id');
        const methodId = $input.data('method-id');
        const cents = toCents($input.val());

        clearTimeout(thresholdTimeout);
        thresholdTimeout = setTimeout(async () => {
            try {
                await sendJson('/shipping-settings/country-settings', 'POST', {
                    country_id: countryId,
                    shipping_method_id: methodId,
                    free_shipping_threshold: cents || null,
                });
                $input.addClass('border-success-400');
                setTimeout(() => $input.removeClass('border-success-400'), 1500);
            } catch {
                toast(t('admin.shipping_settings.failed_save_threshold'), 'error');
            }
        }, 600);
    });
}

// ─── Init ─────────────────────────────────────────────────────────────────────

$(function () {
    initRatesTable();
    initRateModal();
    initDeleteRate();
    initToggleRate();
    initCarrierModal();
    initToggleCarrier();
    initTestCarrier();
    initCountrySettings();
});
