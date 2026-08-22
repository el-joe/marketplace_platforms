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
    } else if (type === 'error') {
        window.Toast ? window.Toast.error(message) : alert(message);
    } else {
        window.Toast ? window.Toast.success(message) : alert(message);
    }
}

function toCents(value) {
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed < 0) return 0;
    return Math.round(parsed * 100);
}

function toGrams(kg) {
    const parsed = parseFloat(kg);
    if (isNaN(parsed) || parsed < 0) return null;
    return Math.round(parsed * 1000);
}

function updateUrl(template, id) {
    return template.replace('__ID__', id);
}

// ─── DataTable ──────────────────────────────────────────────────────────────

let slabsTable = null;

function initSlabsTable() {
    const routes = window.SHIPPING_WEIGHT_SLABS_ROUTES;

    slabsTable = window.initDataTable('weight-slabs-table', {
        url: routes.datatable,
        order: [[0, 'asc']],
        columns: [
            { data: 'method_name' },
            { data: 'country_name' },
            { data: 'weight_range' },
            {
                data: 'extra_fee_formatted',
                className: 'text-right',
                render(value, type, row) {
                    return `<span class="editable-fee cursor-pointer hover:underline" data-id="${row.id}" data-extra-fee="${row.extra_fee}" title="Click to edit">${value}</span>`;
                },
            },
            {
                data: 'active_badge',
                className: 'text-center',
                orderable: false,
            },
            {
                data: 'id',
                className: 'text-center',
                orderable: false,
                render(id, type, row) {
                    return `<div class="flex items-center justify-center gap-1">
                        <button type="button" class="btn-edit-slab p-1 rounded text-gray-400 hover:text-primary-600"
                                data-id="${id}" data-row='${JSON.stringify(row)}'>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button type="button" class="btn-delete-slab p-1 rounded text-gray-400 hover:text-danger-600"
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

    slabsTable.on('draw', loadReferenceTable);
}

// ─── Inline Fee Edit ────────────────────────────────────────────────────────

function initInlineFeeEdit() {
    $(document).on('click', '.editable-fee', function () {
        const $span = $(this);
        if ($span.data('editing')) return;

        const id = $span.data('id');
        const cents = $span.data('extra-fee');
        $span.data('editing', true);

        const $input = $(`<input type="number" step="0.01" min="0" class="w-24 text-sm rounded border border-primary-300 px-1.5 py-0.5" />`)
            .val((cents / 100).toFixed(2));

        $span.replaceWith($input);
        $input.trigger('focus').trigger('select');

        const commit = async () => {
            const newCents = toCents($input.val());
            try {
                await sendJson(updateUrl(window.SHIPPING_WEIGHT_SLABS_ROUTES.update, id), 'PUT', { extra_fee: newCents });
                toast(t('admin.shipping_weight_slabs.extra_fee_updated'));
            } catch (err) {
                toast(err.message ?? err.errors?.extra_fee?.[0] ?? 'Failed to update fee.', 'error');
            } finally {
                window.reloadDataTable('weight-slabs-table');
            }
        };

        $input.on('blur', commit);
        $input.on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $input.trigger('blur'); }
            if (e.key === 'Escape') { window.reloadDataTable('weight-slabs-table'); }
        });
    });
}

// ─── Reference Table ────────────────────────────────────────────────────────

async function loadReferenceTable() {
    const $body = $('#weight-slabs-reference-body');
    const methodId = $('#filter-shipping-method').val();
    const countryId = $('#filter-country').val();

    try {
        const params = new URLSearchParams();
        if (methodId) params.set('shipping_method_id', methodId);
        if (countryId) params.set('country_id', countryId);
        params.set('length', '1000');
        params.set('start', '0');
        params.set('draw', '1');

        const res = await fetch(`${window.SHIPPING_WEIGHT_SLABS_ROUTES.datatable}?${params.toString()}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString(),
        });
        const json = await res.json();
        const rows = json.data ?? [];

        if (!rows.length) {
            $body.html('<tr><td colspan="4" class="text-center text-sm text-gray-400 py-6">No slabs configured.</td></tr>');
            return;
        }

        $body.html(rows.map(row => `
            <tr>
                <td>${row.method_name}</td>
                <td>${row.country_name}</td>
                <td>${row.weight_range}</td>
                <td class="text-right">${row.extra_fee_formatted}</td>
            </tr>
        `).join(''));
    } catch {
        $body.html('<tr><td colspan="4" class="text-center text-sm text-danger-500 py-6">Failed to load reference table.</td></tr>');
    }
}

// ─── Filters ────────────────────────────────────────────────────────────────

function initFilters() {
    $('#weight-slabs-table-filter-form').on('submit', function (e) {
        e.preventDefault();
        window.reloadDataTable('weight-slabs-table');
        loadReferenceTable();
    });

    $('#filter-shipping-method, #filter-country').on('change', function () {
        window.reloadDataTable('weight-slabs-table');
        loadReferenceTable();
    });
}

// ─── Slab Modal ─────────────────────────────────────────────────────────────

function currencyForCountry(countryId) {
    const $opt = $(`#slab-country option[value="${countryId}"]`);
    return $opt.data('currency') || '—';
}

function initSlabModal() {
    const $modal = $('#slab-modal');
    const $form = $('#slab-form');

    $('#btn-add-slab').on('click', function () {
        $form[0].reset();
        $('#slab-id').val('');
        $('#slab-http').val('POST');
        $('#slab-min-weight, #slab-max-weight, #slab-fee-input').val('');
        $('#slab-open-ended').prop('checked', false).trigger('change');
        $('#slab-currency-label').text('—');
        $form.find('[name="shipping_method_id"], [name="country_id"]').val('').trigger('change');
        $modal.modal('open');
    });

    $(document).on('click', '.btn-edit-slab', function () {
        const row = $(this).data('row');

        $form[0].reset();
        $('#slab-id').val(row.id);
        $('#slab-http').val('PUT');

        $form.find('[name="shipping_method_id"]').val(row.shipping_method_id).trigger('change');
        $form.find('[name="country_id"]').val(row.country_id).trigger('change');

        $('#slab-min-weight-kg').val((row.min_weight_grams / 1000).toFixed(3));
        $('#slab-min-weight').val(row.min_weight_grams);

        const openEnded = row.max_weight_grams === null;
        $('#slab-open-ended').prop('checked', openEnded).trigger('change');
        if (!openEnded) {
            $('#slab-max-weight-kg').val((row.max_weight_grams / 1000).toFixed(3));
            $('#slab-max-weight').val(row.max_weight_grams);
        } else {
            $('#slab-max-weight-kg').val('');
            $('#slab-max-weight').val('');
        }

        $('#slab-fee-display').val((row.extra_fee / 100).toFixed(2));
        $('#slab-fee-input').val(row.extra_fee);
        $('#slab-currency-label').text(currencyForCountry(row.country_id));

        const $toggle = $form.find('[name="is_active"]');
        $toggle.prop('checked', !!row.is_active).trigger('change');

        $modal.modal('open');
    });

    // Country → currency label
    $form.on('change', '[name="country_id"]', function () {
        $('#slab-currency-label').text(currencyForCountry($(this).val()));
    });

    // Open-ended checkbox clears max weight
    $('#slab-open-ended').on('change', function () {
        if (this.checked) {
            $('#slab-max-weight-kg').val('').prop('disabled', true);
            $('#slab-max-weight').val('');
        } else {
            $('#slab-max-weight-kg').prop('disabled', false);
        }
    });

    // Sync display → hidden fields
    $('#slab-min-weight-kg').on('input', function () {
        $('#slab-min-weight').val(toGrams($(this).val()));
    });
    $('#slab-max-weight-kg').on('input', function () {
        $('#slab-max-weight').val(toGrams($(this).val()));
    });
    $('#slab-fee-display').on('input', function () {
        $('#slab-fee-input').val(toCents($(this).val()));
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#slab-id').val();
        const method = $('#slab-http').val();
        const routes = window.SHIPPING_WEIGHT_SLABS_ROUTES;
        const url = id ? updateUrl(routes.update, id) : routes.store;

        const payload = {
            shipping_method_id: $form.find('[name="shipping_method_id"]').val(),
            country_id: $form.find('[name="country_id"]').val(),
            min_weight_grams: toGrams($('#slab-min-weight-kg').val()),
            max_weight_grams: $('#slab-open-ended').is(':checked') ? null : toGrams($('#slab-max-weight-kg').val()),
            extra_fee: toCents($('#slab-fee-display').val()),
            is_active: $form.find('[name="is_active"]').is(':checked') ? 1 : 0,
        };

        try {
            await sendJson(url, method, payload);
            toast(id ? 'Weight slab updated.' : 'Weight slab created.');
            $modal.modal('close');
            window.reloadDataTable('weight-slabs-table');
            loadReferenceTable();
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

// ─── Delete Slab ────────────────────────────────────────────────────────────

function initDeleteSlab() {
    const $deleteModal = $('#delete-slab-modal');

    $(document).on('click', '.btn-delete-slab', function () {
        $('#delete-slab-id').val($(this).data('id'));
        $deleteModal.modal('open');
    });

    $('#btn-confirm-delete-slab').on('click', async function () {
        const id = $('#delete-slab-id').val();

        try {
            await sendJson(updateUrl(window.SHIPPING_WEIGHT_SLABS_ROUTES.destroy, id), 'DELETE');
            toast(t('admin.shipping_weight_slabs.weight_slab_deleted'));
            $deleteModal.modal('close');
            window.reloadDataTable('weight-slabs-table');
            loadReferenceTable();
        } catch {
            toast(t('admin.shipping_weight_slabs.delete_failed'), 'error');
        }
    });
}

// ─── Init ───────────────────────────────────────────────────────────────────

$(function () {
    initSlabsTable();
    initInlineFeeEdit();
    initFilters();
    initSlabModal();
    initDeleteSlab();
    loadReferenceTable();
});
