import $ from 'jquery';

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
    if (window.Toast) {
        type === 'error' ? window.Toast.error(message) : window.Toast.success(message);
    } else {
        alert(message);
    }
}

function updateUrl(template, id) {
    return template.replace('__ID__', id);
}

const activeBadge = {
    true: `<span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700">${t('admin.shipping_subsidies.status_active')}</span>`,
    false: `<span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">${t('admin.shipping_subsidies.status_inactive')}</span>`,
};

function initSubsidiesTable() {
    const routes = window.SHIPPING_SUBSIDY_ROUTES;

    window.initDataTable('shipping-subsidies-table', {
        url: routes.index,
        ajaxMethod: 'GET',
        order: [[0, 'asc']],
        columns: [
            { data: 'warehouse_name' },
            { data: 'zone_name' },
            { data: 'country_name' },
            { data: 'method_name' },
            { data: 'subsidy_cap', className: 'text-end' },
            {
                data: 'max_subsidy_weight_grams',
                className: 'text-end',
                render: (value) => (value === null ? '—' : `${value.toLocaleString()} g`),
            },
            { data: 'currency' },
            {
                data: null,
                orderable: false,
                render(row) {
                    if (row.split_type === 'fixed') {
                        return `${row.vendor_fixed_amount ?? 0} ${t('admin.shipping_subsidies.split_vendor_label')} / ${row.admin_fixed_amount ?? 0} ${t('admin.shipping_subsidies.split_admin_label')}`;
                    }
                    const vendorPct = row.vendor_share_pct ?? 0;
                    const adminPct = 100 - vendorPct;
                    return `${vendorPct}% ${t('admin.shipping_subsidies.split_vendor_label')} / ${adminPct}% ${t('admin.shipping_subsidies.split_admin_label')}`;
                },
            },
            {
                data: 'is_active',
                className: 'text-center',
                orderable: false,
                render: (value) => activeBadge[value] ?? activeBadge.false,
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render(row) {
                    return `
                        <div class="flex items-center justify-center gap-1">
                            <button type="button" class="btn-edit-subsidy p-1 rounded text-gray-400 hover:text-primary-600" data-row='${JSON.stringify(row)}' title="${t('admin.shipping_subsidies.edit_label')}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button type="button" class="btn-delete-subsidy p-1 rounded text-gray-400 hover:text-danger-600" data-id="${row.id}" title="${t('admin.shipping_subsidies.deactivate_label')}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>`;
                },
            },
        ],
        pageLength: 25,
    });
}

function updateCapHint() {
    const $form = $('#shipping-subsidy-form');
    const currency = $form.find('[name="currency"]').val();
    const symbol = window.CURRENCY_SYMBOLS?.[currency] ?? currency ?? '';
    $('#subsidy-cap-hint').text(
        symbol ? t('admin.shipping_subsidies.cap_hint_with_currency', { symbol })
               : t('admin.shipping_subsidies.cap_hint_generic')
    );
}

function initSubsidyModal() {
    const $modal = $('#shipping-subsidy-modal');
    const $form = $('#shipping-subsidy-form');

    $('#btn-add-subsidy').on('click', function () {
        $form[0].reset();
        $('#subsidy-id').val('');
        $form.find('[name="warehouse_id"]').val('').trigger('change');
        $form.find('[name="shipping_zone_id"]').val('').trigger('change');
        $form.find('[name="shipping_method_id"]').val('').trigger('change');
        $form.find('[name="currency"]').val('').trigger('change');
        $form.find('[name="is_active"]').prop('checked', true);
        $form.find('[name="split_type"][value="percentage"]').prop('checked', true).trigger('change');
        $form.find('[name="vendor_share_pct"]').val(50).trigger('input');
        $form.find('[name="vendor_fixed_amount"]').val(0).trigger('input');
        $form.find('[name="admin_fixed_amount"]').val(0).trigger('input');
        updateCapHint();
        $modal.modal('open');
    });

    $form.find('[name="currency"]').on('change', updateCapHint);

    $(document).on('click', '.btn-edit-subsidy', function () {
        const data = $(this).data('row');

        $form[0].reset();
        $('#subsidy-id').val(data.id);
        $form.find('[name="warehouse_id"]').val(data.warehouse_id).trigger('change');
        $form.find('[name="shipping_zone_id"]').val(data.shipping_zone_id).trigger('change');
        $form.find('[name="shipping_method_id"]').val(data.shipping_method_id).trigger('change');
        $form.find('[name="subsidy_cap"]').val(data.subsidy_cap);
        $form.find('[name="max_subsidy_weight_grams"]').val(data.max_subsidy_weight_grams);
        $form.find('[name="currency"]').val(data.currency).trigger('change');
        $form.find('[name="is_active"]').prop('checked', !!data.is_active);

        const splitType = data.split_type || 'percentage';
        $form.find(`[name="split_type"][value="${splitType}"]`).prop('checked', true).trigger('change');
        $form.find('[name="vendor_share_pct"]').val(data.vendor_share_pct ?? 50).trigger('input');
        $form.find('[name="vendor_fixed_amount"]').val(data.vendor_fixed_amount ?? 0).trigger('input');
        $form.find('[name="admin_fixed_amount"]').val(data.admin_fixed_amount ?? 0).trigger('input');

        updateCapHint();

        $modal.modal('open');
    });

    $(document).on('click', '.btn-delete-subsidy', async function () {
        const id = $(this).data('id');
        if (!confirm(t('admin.shipping_subsidies.deactivate_confirm'))) return;

        try {
            await sendJson(updateUrl(window.SHIPPING_SUBSIDY_ROUTES.destroy, id), 'DELETE');
            toast(t('admin.shipping_subsidies.deactivated_success'));
            window.reloadDataTable('shipping-subsidies-table');
        } catch (err) {
            toast(err.message ?? t('admin.shipping_subsidies.deactivate_failed'), 'error');
        }
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#subsidy-id').val();
        const routes = window.SHIPPING_SUBSIDY_ROUTES;
        const method = id ? 'PUT' : 'POST';
        const url = id ? updateUrl(routes.update, id) : routes.store;

        const maxWeight = $form.find('[name="max_subsidy_weight_grams"]').val();

        const splitType = $form.find('[name="split_type"]:checked').val() || 'percentage';

        const payload = {
            warehouse_id: $form.find('[name="warehouse_id"]').val(),
            shipping_zone_id: $form.find('[name="shipping_zone_id"]').val(),
            shipping_method_id: $form.find('[name="shipping_method_id"]').val(),
            subsidy_cap: parseInt($form.find('[name="subsidy_cap"]').val(), 10) || 0,
            max_subsidy_weight_grams: maxWeight === '' ? null : parseInt(maxWeight, 10),
            currency: $form.find('[name="currency"]').val(),
            is_active: $form.find('[name="is_active"]').is(':checked'),
            split_type: splitType,
            vendor_share_pct: splitType === 'percentage'
                ? (parseInt($form.find('[name="vendor_share_pct"]').val(), 10) || 0)
                : null,
            vendor_fixed_amount: splitType === 'fixed'
                ? (parseInt($form.find('[name="vendor_fixed_amount"]').val(), 10) || 0)
                : null,
            admin_fixed_amount: splitType === 'fixed'
                ? (parseInt($form.find('[name="admin_fixed_amount"]').val(), 10) || 0)
                : null,
        };

        try {
            await sendJson(url, method, payload);
            toast(id ? t('admin.shipping_subsidies.updated_success') : t('admin.shipping_subsidies.created_success'));
            $modal.modal('close');
            window.reloadDataTable('shipping-subsidies-table');
        } catch (err) {
            if (err.errors) {
                window.injectValidationErrors?.($form, err.errors);
                const firstError = Object.values(err.errors)[0]?.[0];
                if (firstError) toast(firstError, 'error');
            } else {
                toast(err.message ?? t('admin.shipping_subsidies.save_failed'), 'error');
            }
        }
    });
}

$(function () {
    if (!document.getElementById('shipping-subsidies-table')) return;
    initSubsidiesTable();
    initSubsidyModal();
});
