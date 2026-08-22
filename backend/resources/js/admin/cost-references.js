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

function money(cents) {
    return cents === null || cents === undefined ? '—' : (cents / 100).toFixed(2);
}

function initCostReferencesTable() {
    const routes = window.COST_REFERENCE_ROUTES;

    window.initDataTable('cost-references-table', {
        url: routes.datatable,
        order: [[0, 'asc']],
        columns: [
            { data: 'manufacturer_name' },
            { data: 'manufacturer_cost', className: 'text-end', render: money },
            { data: 'shipping_cost', className: 'text-end', render: money },
            { data: 'landed_cost', className: 'text-end', render: money },
            {
                data: 'platform_margin_pct',
                className: 'text-end',
                render: (value) => (value === null ? '—' : `${value}%`),
            },
            { data: 'competitor_count', className: 'text-center' },
            {
                data: 'last_checked',
                render: (value) => (value ? new Date(value).toLocaleString() : '—'),
            },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render(row) {
                    return `
                        <div class="flex items-center justify-center gap-1">
                            <button type="button" class="btn-edit-cost-reference p-1 rounded text-gray-400 hover:text-primary-600" data-row='${JSON.stringify(row)}' title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button type="button" class="btn-delete-cost-reference p-1 rounded text-gray-400 hover:text-danger-600" data-id="${row.id}" title="Delete">
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

function addCompetitorLinkRow(link = { name: '', url: '', price: null }) {
    const $row = $(`
        <div class="grid grid-cols-12 gap-2 items-center competitor-link-row">
            <input type="text" class="col-span-3 form-input text-xs" placeholder="Platform" value="${link.name ?? ''}" data-field="name">
            <input type="url" class="col-span-6 form-input text-xs" placeholder="URL" value="${link.url ?? ''}" data-field="url">
            <input type="number" class="col-span-2 form-input text-xs" placeholder="Price (cents)" value="${link.price ?? ''}" data-field="price">
            <button type="button" class="col-span-1 text-danger-500 hover:text-danger-700 text-xs btn-remove-competitor-link">✕</button>
        </div>
    `);
    $('#competitor-links-rows').append($row);
}

function collectCompetitorLinks() {
    const links = [];
    $('#competitor-links-rows .competitor-link-row').each(function () {
        const name = $(this).find('[data-field="name"]').val();
        const url = $(this).find('[data-field="url"]').val();
        const price = $(this).find('[data-field="price"]').val();
        if (name && url) {
            links.push({ name, url, price: price === '' ? null : parseInt(price, 10) });
        }
    });
    return links;
}

function initCostReferenceModal() {
    const $modal = $('#cost-reference-modal');
    const $form = $('#cost-reference-form');

    $('#btn-add-cost-reference').on('click', function () {
        $form[0].reset();
        $('#cost-reference-id').val('');
        $('#competitor-links-rows').empty();
        $modal.modal('open');
    });

    $('#btn-add-competitor-link').on('click', function () {
        addCompetitorLinkRow();
    });

    $(document).on('click', '.btn-remove-competitor-link', function () {
        $(this).closest('.competitor-link-row').remove();
    });

    $(document).on('click', '.btn-edit-cost-reference', function () {
        const data = $(this).data('row');

        $form[0].reset();
        $('#cost-reference-id').val(data.id);
        $form.find('[name="manufacturer_name"]').val(data.manufacturer_name);
        $form.find('[name="manufacturer_sku"]').val(data.manufacturer_sku);
        $form.find('[name="manufacturer_url"]').val(data.manufacturer_url);
        $form.find('[name="manufacturer_cost"]').val(data.manufacturer_cost);
        $form.find('[name="shipping_cost"]').val(data.shipping_cost);
        $form.find('[name="landed_cost"]').val(data.landed_cost);
        $form.find('[name="platform_margin_pct"]').val(data.platform_margin_pct);
        $form.find('[name="notes"]').val(data.notes);

        $('#competitor-links-rows').empty();
        (data.competitor_links || []).forEach((link) => addCompetitorLinkRow(link));

        $modal.modal('open');
    });

    $(document).on('click', '.btn-delete-cost-reference', async function () {
        const id = $(this).data('id');
        if (!confirm(t('admin.cost_references.delete_cost_reference_confirm'))) return;

        try {
            await sendJson(updateUrl(window.COST_REFERENCE_ROUTES.destroy, id), 'DELETE');
            toast(t('admin.cost_references.cost_reference_deleted'));
            window.reloadDataTable('cost-references-table');
        } catch (err) {
            toast(err.message ?? t('admin.cost_references.delete_cost_reference_failed'), 'error');
        }
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#cost-reference-id').val();
        const routes = window.COST_REFERENCE_ROUTES;
        const method = id ? 'PUT' : 'POST';
        const url = id ? updateUrl(routes.update, id) : routes.store;

        const payload = {
            manufacturer_name: $form.find('[name="manufacturer_name"]').val() || null,
            manufacturer_sku: $form.find('[name="manufacturer_sku"]').val() || null,
            manufacturer_url: $form.find('[name="manufacturer_url"]').val() || null,
            manufacturer_cost: $form.find('[name="manufacturer_cost"]').val() || null,
            shipping_cost: $form.find('[name="shipping_cost"]').val() || null,
            landed_cost: $form.find('[name="landed_cost"]').val() || null,
            platform_margin_pct: $form.find('[name="platform_margin_pct"]').val() || null,
            notes: $form.find('[name="notes"]').val() || null,
            competitor_links: collectCompetitorLinks(),
        };

        try {
            await sendJson(url, method, payload);
            toast(id ? 'Cost reference updated.' : 'Cost reference created.');
            $modal.modal('close');
            window.reloadDataTable('cost-references-table');
        } catch (err) {
            if (err.errors) {
                window.injectValidationErrors?.($form, err.errors);
                const firstError = Object.values(err.errors)[0]?.[0];
                if (firstError) toast(firstError, 'error');
            } else {
                toast(err.message ?? 'Failed to save cost reference.', 'error');
            }
        }
    });
}

$(function () {
    if (!document.getElementById('cost-references-table')) return;
    initCostReferencesTable();
    initCostReferenceModal();
});
