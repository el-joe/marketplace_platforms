import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function initWarrantyPurchasesTable() {
    const tableEl = document.getElementById('warranty-purchases-table');
    if (!tableEl) return;

    let activeStatus = '';

    const dt = new DataTable('#warranty-purchases-table', {
        processing: true,
        serverSide: true,
        language: dtLanguage,
        order: [[9, 'desc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.search.value = document.getElementById('search-input')?.value ?? '';
                d.plan_id = document.getElementById('filter-plan')?.value ?? '';
                d.category_id = document.getElementById('filter-category')?.value ?? '';
                d.date_from = document.getElementById('filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('filter-date-to')?.value ?? '';
                if (activeStatus) {
                    d.status = activeStatus;
                }
            },
        },
        columns: [
            { data: 'purchase_id' },
            { data: 'customer', orderable: false },
            { data: 'product', orderable: false },
            { data: 'plan' },
            { data: 'duration', orderable: false },
            { data: 'price' },
            { data: 'status' },
            { data: 'coverage_starts_at' },
            { data: 'coverage_ends_at' },
            { data: 'created_at' },
            { data: 'actions', orderable: false },
        ],
        pageLength: 25,
    });

    document.querySelectorAll('.status-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.status-tab').forEach(b => {
                b.classList.remove('border-primary-600', 'text-primary-600');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            btn.classList.remove('border-transparent', 'text-gray-500');
            btn.classList.add('border-primary-600', 'text-primary-600');

            activeStatus = btn.dataset.statusFilter;
            dt.draw();
        });
    });

    ['filter-plan', 'filter-category', 'filter-date-from', 'filter-date-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.draw());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.draw(), 350);
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-plan', 'filter-category', 'filter-date-from', 'filter-date-to'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const s = document.getElementById('search-input');
        if (s) s.value = '';
        activeStatus = '';
        document.querySelectorAll('.status-tab').forEach((b, i) => {
            if (i === 0) {
                b.classList.add('border-primary-600', 'text-primary-600');
                b.classList.remove('border-transparent', 'text-gray-500');
            } else {
                b.classList.remove('border-primary-600', 'text-primary-600');
                b.classList.add('border-transparent', 'text-gray-500');
            }
        });
        dt.draw();
    });

    return dt;
}

document.addEventListener('DOMContentLoaded', () => {
    initWarrantyPurchasesTable();
});
