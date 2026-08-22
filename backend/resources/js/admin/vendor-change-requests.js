import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function initVendorChangeRequestsTable() {
    const tableEl = document.getElementById('vendor-change-requests-table');
    if (!tableEl) return;

    let activeStatus = '';

    const dt = new DataTable('#vendor-change-requests-table', {
        processing: true,
        serverSide: true,
        language: dtLanguage,
        order: [[4, 'desc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.search.value = document.getElementById('search-input')?.value ?? '';
                d.section = document.getElementById('filter-section')?.value ?? '';
                d.vendor_id = document.getElementById('filter-vendor_id')?.value ?? '';
                if (activeStatus) {
                    d.status = activeStatus;
                }
            },
        },
        columns: [
            { data: 'vendor', orderable: false },
            { data: 'section', orderable: false },
            { data: 'type', orderable: false },
            { data: 'requested_by', orderable: false },
            { data: 'requested_at' },
            { data: 'status' },
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

    ['filter-section', 'filter-vendor_id'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.draw());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.draw(), 350);
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-section', 'filter-vendor_id'].forEach(id => {
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
    initVendorChangeRequestsTable();
});
