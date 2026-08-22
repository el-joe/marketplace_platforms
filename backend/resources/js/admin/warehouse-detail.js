import DataTable from 'datatables.net';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ─── Inventory DataTable ──────────────────────────────────────────────────────

function initInventoryTable() {
    const tableEl = document.getElementById('inventory-table');
    if (!tableEl) return;

    const dt = new DataTable('#inventory-table', {
        processing: true,
        serverSide: true,
        order: [[0, 'asc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.filter_status = document.getElementById('inv-filter-status')?.value ?? '';
            },
        },
        columns: [
            { data: 'product' },
            { data: 'vendor', orderable: false },
            { data: 'on_hand', className: 'text-right tabular-nums' },
            { data: 'available', className: 'text-right tabular-nums' },
            { data: 'reserved', className: 'text-right tabular-nums' },
            { data: 'damaged', className: 'text-right tabular-nums' },
            { data: 'bin', orderable: false },
            { data: 'actions', orderable: false },
        ],
        pageLength: 25,
    });

    document.getElementById('inv-filter-status')?.addEventListener('change', () => dt.draw());

    return dt;
}

// ─── Daily Overage Fees DataTable ───────────────────────────────────────────────

function initOverageFeesTable() {
    const tableEl = document.getElementById('overage-fees-table');
    if (!tableEl) return;

    return new DataTable('#overage-fees-table', {
        processing: true,
        serverSide: true,
        order: [[0, 'desc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        },
        columns: [
            { data: 'fee_date' },
            { data: 'vendor', orderable: false },
            { data: 'sku', orderable: false },
            { data: 'units', className: 'text-right tabular-nums' },
            { data: 'fee_per_unit', className: 'text-right tabular-nums' },
            { data: 'total_fee', className: 'text-right tabular-nums' },
            { data: 'currency' },
            { data: 'status' },
        ],
        pageLength: 25,
    });
}

// ─── Toggle Active ────────────────────────────────────────────────────────────

function initToggleActive() {
    document.querySelector('.js-toggle-active')?.addEventListener('click', async function () {
        const btn = this;
        const isActive = parseInt(btn.dataset.active, 10);

        if (!confirm(isActive ? (window.TRANSLATIONS?.deactivateWarehouseConfirm || 'Deactivate this warehouse?') : (window.TRANSLATIONS?.activateWarehouseConfirm || 'Activate this warehouse?'))) return;

        try {
            const res = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({}),
            });
            const json = await res.json();
            if (!res.ok) throw json;
            window.Toast?.success(json.message);
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            window.Toast?.error(err.message ?? (window.TRANSLATIONS?.requestFailed || t('shared.request_failed')));
        }
    });
}

// ─── Adjust Inventory Modal ───────────────────────────────────────────────────

function initAdjustModal() {
    const modal = document.getElementById('adjust-modal');
    const adjustForm = document.getElementById('adjust-form');
    const adjustUrlEl = document.getElementById('adjust-url');
    const errorEl = document.getElementById('adjust-error');

    if (!modal) return;

    // Open modal on row button click
    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-adjust-inventory');
        if (!btn) return;

        adjustUrlEl.value = btn.dataset.url;
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        adjustForm.reset();
        adjustUrlEl.value = btn.dataset.url; // re-set after reset
        modal.classList.remove('hidden');
    });

    // Close
    const closeModal = () => modal.classList.add('hidden');
    document.getElementById('close-adjust-modal')?.addEventListener('click', closeModal);
    document.getElementById('cancel-adjust')?.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // Submit
    adjustForm?.addEventListener('submit', async e => {
        e.preventDefault();

        const url = adjustUrlEl.value;
        const formData = new FormData(adjustForm);
        const payload = {
            delta: parseInt(formData.get('delta'), 10),
            movement_type: formData.get('movement_type'),
            reason: formData.get('reason'),
        };

        errorEl.classList.add('hidden');

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json();

            if (!res.ok) {
                errorEl.textContent = json.message ?? t('admin.warehouse_detail.adjustment_failed');
                errorEl.classList.remove('hidden');
                return;
            }

            window.Toast?.success(json.message ?? t('admin.warehouse_detail.inventory_adjusted'));
            closeModal();

            // Refresh the DataTable
            const dt = DataTable.instances.find(i => i.table().node().id === 'inventory-table');
            dt?.draw(false);
        } catch (err) {
            errorEl.textContent = err.message ?? t('admin.warehouse_detail.network_error');
            errorEl.classList.remove('hidden');
        }
    });
}

// ─── Inventory Movements Modal ─────────────────────────────────────────────────

function initMovementsModal() {
    const modal = document.getElementById('movements-modal');
    const body = document.getElementById('movements-modal-body');

    if (!modal) return;

    const closeModal = () => modal.classList.add('hidden');
    document.getElementById('close-movements-modal')?.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    document.addEventListener('click', async e => {
        const btn = e.target.closest('.js-view-movements');
        if (!btn) return;

        body.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-sm text-gray-400">${t('shared.loading')}</td></tr>`;
        modal.classList.remove('hidden');

        try {
            const res = await fetch(btn.dataset.url, {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            });
            const json = await res.json();
            if (!res.ok) throw json;

            const movements = json.data ?? [];
            if (!movements.length) {
                body.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-sm text-gray-400">${window.TRANSLATIONS?.noMovements ?? ''}</td></tr>`;
                return;
            }

            body.innerHTML = movements.map(mv => {
                const deltaClass = mv.quantity_delta > 0 ? 'text-green-600' : 'text-red-600';
                const deltaSign = mv.quantity_delta > 0 ? '+' : '';
                return `<tr>
                    <td class="py-2.5 pr-4 text-xs text-gray-400 whitespace-nowrap">${new Date(mv.created_at).toLocaleString()}</td>
                    <td class="py-2.5 pr-4 text-xs font-mono">${mv.movement_type ?? ''}</td>
                    <td class="py-2.5 pr-4 text-end tabular-nums font-medium ${deltaClass}">${deltaSign}${mv.quantity_delta}</td>
                    <td class="py-2.5 pr-4 text-end tabular-nums text-gray-600">${mv.quantity_after}</td>
                    <td class="py-2.5 pr-4 text-xs text-gray-500 max-w-xs truncate">${mv.reason ?? '—'}</td>
                    <td class="py-2.5 text-xs text-gray-400">${mv.created_by?.name ?? '—'}</td>
                </tr>`;
            }).join('');
        } catch (err) {
            body.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-sm text-red-500">${window.TRANSLATIONS?.requestFailed ?? ''}</td></tr>`;
        }
    });
}

// ─── Vendor Limits Modal ──────────────────────────────────────────────────────

function initVendorLimitModal() {
    const modal = document.getElementById('vendor-limit-modal');
    const form = document.getElementById('vendor-limit-form');
    const errorEl = document.getElementById('vendor-limit-error');

    if (!modal || !form) return;

    const limitTypeInput = form.querySelector('input[name="limit_type"]');
    const qtyField = document.getElementById('vendor-limit-qty-field');
    const capacityField = document.getElementById('vendor-limit-capacity-field');

    const setLimitType = value => {
        limitTypeInput.value = value;
        form.querySelectorAll('.js-limit-type-btn').forEach(btn => {
            const active = btn.dataset.value === value;
            btn.classList.toggle('bg-white', active);
            btn.classList.toggle('shadow-sm', active);
            btn.classList.toggle('text-gray-800', active);
            btn.classList.toggle('text-gray-500', !active);
        });
        qtyField.classList.toggle('hidden', value !== 'quantity');
        capacityField.classList.toggle('hidden', value !== 'capacity');
    };

    form.querySelectorAll('.js-limit-type-btn').forEach(btn => {
        btn.addEventListener('click', () => setLimitType(btn.dataset.value));
    });

    const openModal = () => {
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        form.reset();
        setLimitType('quantity');
        modal.classList.remove('hidden');
    };
    const closeModal = () => modal.classList.add('hidden');

    document.getElementById('btn-add-vendor-limit')?.addEventListener('click', openModal);
    document.getElementById('close-vendor-limit-modal')?.addEventListener('click', closeModal);
    document.getElementById('cancel-vendor-limit')?.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    form.addEventListener('submit', async e => {
        e.preventDefault();

        const formData = new FormData(form);
        const payload = {
            vendor_id: formData.get('vendor_id'),
            limit_type: formData.get('limit_type'),
            max_quantity: formData.get('limit_type') === 'quantity' ? parseInt(formData.get('max_quantity'), 10) : null,
            max_capacity_m3: formData.get('limit_type') === 'capacity' ? parseFloat(formData.get('max_capacity_m3')) : null,
        };

        errorEl.classList.add('hidden');

        try {
            const res = await fetch(form.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json();

            if (!res.ok) {
                const firstError = json.errors ? Object.values(json.errors)[0]?.[0] : null;
                errorEl.textContent = firstError ?? json.message ?? t('admin.warehouse_detail.save_failed');
                errorEl.classList.remove('hidden');
                return;
            }

            window.Toast?.success(json.message ?? t('admin.warehouse_detail.vendor_limit_saved'));
            closeModal();
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            errorEl.textContent = err.message ?? t('admin.warehouse_detail.network_error');
            errorEl.classList.remove('hidden');
        }
    });

    document.addEventListener('click', async e => {
        const btn = e.target.closest('.js-delete-vendor-limit');
        if (!btn) return;

        if (!confirm(t('admin.warehouse_detail.remove_vendor_limit_confirm'))) return;

        try {
            const res = await fetch(btn.dataset.url, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });
            const json = await res.json();
            if (!res.ok) throw json;
            window.Toast?.success(json.message ?? t('admin.warehouse_detail.vendor_limit_removed'));
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            window.Toast?.error(err.message ?? t('shared.request_failed'));
        }
    });
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initInventoryTable();
    initOverageFeesTable();
    initToggleActive();
    initAdjustModal();
    initMovementsModal();
    initVendorLimitModal();
});
