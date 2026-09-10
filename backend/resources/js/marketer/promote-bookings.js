import './app.js';
import DataTable from 'datatables.net-dt';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function el(id) { return document.getElementById(id); }

function buildPaginationDrawCallback(tableId) {
    return function () {
        const api = this.api();
        const info = api.page.info();

        const infoEl = el(`${tableId}-info`);
        if (infoEl) {
            infoEl.textContent = info.recordsTotal === 0 ? '' : `Showing ${info.start + 1}-${info.end} of ${info.recordsTotal}`;
        }

        const pagEl = el(`${tableId}-pagination`);
        if (!pagEl) return;
        pagEl.innerHTML = '';

        const page = info.page;
        const pages = info.pages;
        if (pages <= 1) return;

        const btn = (label, pg, disabled, active) => {
            const b = document.createElement('button');
            b.innerHTML = label;
            b.className = [
                'min-w-[32px] h-8 px-2 rounded-lg text-xs font-medium transition-colors',
                active ? 'bg-primary-500 text-white font-bold' : 'text-gray-600 hover:bg-gray-100',
                disabled ? 'opacity-30 cursor-not-allowed pointer-events-none' : '',
            ].join(' ');
            if (!disabled && !active) b.addEventListener('click', () => api.page(pg).draw(false));
            return b;
        };

        pagEl.appendChild(btn('«', 0, page === 0, false));
        pagEl.appendChild(btn('‹', page - 1, page === 0, false));
        const startPg = Math.max(0, page - 2);
        const endPg = Math.min(pages - 1, page + 2);
        for (let i = startPg; i <= endPg; i++) pagEl.appendChild(btn(i + 1, i, false, i === page));
        pagEl.appendChild(btn('›', page + 1, page >= pages - 1, false));
        pagEl.appendChild(btn('»', pages - 1, page >= pages - 1, false));
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const tableEl = el('ad-bookings-table');
    if (!tableEl) return;

    let currentStatus = '';

    const table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: false,
        dom: 't',
        pageLength: 25,
        order: [],
        ajax: {
            url: window.AD_BOOKINGS_CONFIG.datatableUrl,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: (d) => { if (currentStatus) d.status = currentStatus; return d; },
        },
        columns: [
            { data: 'reference' },
            { data: 'slot' },
            { data: 'dates' },
            { data: 'amount' },
            { data: 'payment' },
            { data: 'status' },
            { data: 'creative_status' },
            { data: 'actions', orderable: false, searchable: false },
        ],
        createdRow: (row) => row.classList.add('hover:bg-gray-50', 'transition-colors'),
        drawCallback: buildPaginationDrawCallback('ad-bookings-table'),
    });

    document.querySelectorAll('.ad-booking-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.ad-booking-tab').forEach((t) => t.classList.remove('bg-primary-50', 'border-primary-300', 'text-primary-700'));
            tab.classList.add('bg-primary-50', 'border-primary-300', 'text-primary-700');
            currentStatus = tab.dataset.status;
            table.ajax.reload();
        });
    });
});
