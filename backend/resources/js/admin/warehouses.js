import DataTable from 'datatables.net';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ─── Warehouses DataTable ─────────────────────────────────────────────────────

function initWarehousesTable() {
    const tableEl = document.getElementById('warehouses-table');
    if (!tableEl) return;

    const dt = new DataTable('#warehouses-table', {
        processing: true,
        serverSide: true,
        order: [[0, 'asc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.type = document.getElementById('filter-type')?.value ?? '';
                d.is_active = document.getElementById('filter-status')?.value ?? '';
                d.search.value = document.getElementById('search-input')?.value ?? '';
            },
        },
        columns: [
            { data: 'name' },
            { data: 'code' },
            { data: 'type' },
            { data: 'country' },
            { data: 'vendor', orderable: false },
            { data: 'manager', orderable: false },
            { data: 'capacity', orderable: false },
            { data: 'is_active' },
            { data: 'actions', orderable: false },
        ],
        pageLength: 25,
    });

    // Filter bindings
    ['filter-type', 'filter-status'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.draw());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.draw(), 350);
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-type', 'filter-status'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const searchEl = document.getElementById('search-input');
        if (searchEl) searchEl.value = '';
        dt.draw();
    });

    // Toggle active via row button
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.js-toggle-active');
        if (!btn) return;

        const isActive = parseInt(btn.dataset.active, 10);
        if (!confirm(isActive ? t('admin.warehouses.deactivate_warehouse_confirm') : t('admin.warehouses.activate_warehouse_confirm'))) return;

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
            dt?.draw(false);
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.warehouses.request_failed'));
        }
    });

    return dt;
}

// ─── Transfers DataTable ──────────────────────────────────────────────────────

function initTransfersTable() {
    const tableEl = document.getElementById('transfers-table');
    if (!tableEl) return;

    const dt = new DataTable('#transfers-table', {
        processing: true,
        serverSide: true,
        order: [[5, 'desc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.status = document.getElementById('filter-status')?.value ?? '';
                d.source_warehouse_id = document.getElementById('filter-source')?.value ?? '';
                d.dest_warehouse_id = document.getElementById('filter-dest')?.value ?? '';
            },
        },
        columns: [
            { data: 'number' },
            { data: 'source', orderable: false },
            { data: 'destination', orderable: false },
            { data: 'vendor', orderable: false },
            { data: 'status' },
            { data: 'created_at' },
            { data: 'actions', orderable: false },
        ],
        pageLength: 25,
    });

    ['filter-status', 'filter-source', 'filter-dest'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.draw());
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-status', 'filter-source', 'filter-dest'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        dt.draw();
    });

    return dt;
}

// ─── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initWarehousesTable();
    initTransfersTable();
});
