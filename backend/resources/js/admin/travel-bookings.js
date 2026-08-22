/**
 * Admin Travel Bookings JS
 * Handles: bookings DataTable and filters.
 */

import DataTable from 'datatables.net';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/* ─── Bookings DataTable ──────────────────────────────────────────────────── */

let bookingsTable = null;

function initBookingsTable() {
    const el = document.getElementById('bookings-table');
    if (!el) return;

    bookingsTable = new DataTable('#bookings-table', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes?.bookingsDatatable ?? '',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.status = document.getElementById('filter-status')?.value ?? '';
                d.date_from = document.getElementById('filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('filter-date-to')?.value ?? '';
                d.search = { value: document.getElementById('search-input')?.value ?? '' };
            },
        },
        columns: [
            { data: 'booking_number' },
            { data: 'package' },
            { data: 'customer', orderable: false },
            { data: 'travel_dates', orderable: false },
            { data: 'amount', orderable: false },
            { data: 'status', orderable: false },
            { data: 'actions', title: '', orderable: false },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
    });
}

function initFilters() {
    const inputs = ['filter-status', 'filter-date-from', 'filter-date-to'];
    inputs.forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => bookingsTable?.ajax.reload());
    });

    document.getElementById('search-input')?.addEventListener('input', function () {
        clearTimeout(this._debounce);
        this._debounce = setTimeout(() => bookingsTable?.ajax.reload(), 400);
    });

    document.getElementById('clear-filters')?.addEventListener('click', () => {
        inputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const search = document.getElementById('search-input');
        if (search) search.value = '';
        bookingsTable?.ajax.reload();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initBookingsTable();
    initFilters();
});
