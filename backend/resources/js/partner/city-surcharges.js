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

function toCents(value) {
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed < 0) return 0;
    return Math.round(parsed * 100);
}

function updateUrl(template, id) {
    return template.replace('__ID__', id);
}

const activeBadge = {
    true: '<span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700">Active</span>',
    false: '<span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Inactive</span>',
};

function initSurchargesTable() {
    const routes = window.CITY_SURCHARGE_ROUTES;

    window.initDataTable('city-surcharges-table', {
        url: routes.index,
        ajaxMethod: 'GET',
        order: [[0, 'asc']],
        columns: [
            { data: 'warehouse' },
            { data: 'warehouse_code' },
            { data: 'extra_amount', className: 'text-end' },
            {
                data: 'is_active',
                className: 'text-center',
                orderable: false,
                render: (value) => activeBadge[value] ?? activeBadge.false,
            },
            {
                data: 'actions',
                className: 'text-center',
                orderable: false,
                render(actions, type, row) {
                    return `
                        <div class="flex items-center justify-center gap-1">
                            <button type="button" class="btn-edit-surcharge p-1 rounded text-gray-400 hover:text-primary-600" data-id="${actions.id}" data-row='${JSON.stringify(row)}' title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button type="button" class="btn-toggle-surcharge p-1 rounded text-gray-400 hover:text-danger-600" data-id="${actions.id}" title="Toggle active">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.36 6.64a9 9 0 11-12.73 0M12 3v9"/>
                                </svg>
                            </button>
                        </div>`;
                },
            },
        ],
        pageLength: 25,
    });
}

function initSurchargeModal() {
    const $modal = $('#city-surcharge-modal');
    const $form = $('#city-surcharge-form');

    $('#btn-add-surcharge').on('click', function () {
        $form[0].reset();
        $('#surcharge-id').val('');
        $form.find('[name="warehouse_id"]').val('').trigger('change');
        $('#surcharge-amount-display').val('');
        $modal.modal('open');
    });

    $(document).on('click', '.btn-edit-surcharge', function () {
        const data = $(this).data('row').actions;

        $form[0].reset();
        $('#surcharge-id').val(data.id);
        $form.find('[name="warehouse_id"]').val(data.warehouse_id).trigger('change');
        $('#surcharge-amount-display').val((data.extra_amount_cents / 100).toFixed(2));

        $modal.modal('open');
    });

    $(document).on('click', '.btn-toggle-surcharge', async function () {
        const id = $(this).data('id');

        try {
            await sendJson(updateUrl(window.CITY_SURCHARGE_ROUTES.toggleActive, id), 'POST');
            toast('Surcharge updated.');
            window.reloadDataTable('city-surcharges-table');
        } catch (err) {
            toast(err.message ?? 'Failed to update.', 'error');
        }
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#surcharge-id').val();
        const routes = window.CITY_SURCHARGE_ROUTES;
        const method = id ? 'PUT' : 'POST';
        const url = id ? updateUrl(routes.update, id) : routes.store;

        const payload = {
            warehouse_id: $form.find('[name="warehouse_id"]').val(),
            extra_amount_cents: toCents($('#surcharge-amount-display').val()),
        };

        try {
            await sendJson(url, method, payload);
            toast(id ? 'Surcharge updated.' : 'Surcharge added.');
            $modal.modal('close');
            window.reloadDataTable('city-surcharges-table');
        } catch (err) {
            if (err.errors) {
                window.injectValidationErrors?.($form, err.errors);
                const firstError = Object.values(err.errors)[0]?.[0];
                if (firstError) toast(firstError, 'error');
            } else {
                toast(err.message ?? 'Save failed.', 'error');
            }
        }
    });
}

$(function () {
    if (!document.getElementById('city-surcharges-table')) return;
    initSurchargesTable();
    initSurchargeModal();
});
