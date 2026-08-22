import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function sendJson(url, method = 'POST', data = {}) {
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

// ─── Returns DataTable (index) ─────────────────────────────────────────────────

function initReturnsTable() {
    const tableEl = document.getElementById('returns-table');
    if (!tableEl) return;

    let activeStatus = '';

    const dt = new DataTable('#returns-table', {
        processing: true,
        serverSide: true,
        language: dtLanguage,
        order: [[7, 'desc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.search.value = document.getElementById('search-input')?.value ?? '';
                d.vendor_id = document.getElementById('filter-vendor')?.value ?? '';
                d.date_from = document.getElementById('filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('filter-date-to')?.value ?? '';
                if (activeStatus) {
                    d.status = activeStatus;
                }
            },
        },
        columns: [
            { data: 'return_number' },
            { data: 'order', orderable: false },
            { data: 'customer', orderable: false },
            { data: 'vendor', orderable: false },
            { data: 'reason' },
            { data: 'items_count', orderable: false },
            { data: 'status' },
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

    ['filter-vendor', 'filter-date-from', 'filter-date-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.draw());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.draw(), 350);
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-vendor', 'filter-date-from', 'filter-date-to'].forEach(id => {
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
}

// ─── Return Show Page ──────────────────────────────────────────────────────────

function initReturnShowPage() {
    const hasShowPage = document.getElementById('btn-approve')
        || document.getElementById('reject-form')
        || document.getElementById('schedule-pickup-form')
        || document.getElementById('btn-mark-received')
        || document.getElementById('inspect-form');
    if (!hasShowPage) return;

    document.getElementById('btn-approve')?.addEventListener('click', async function () {
        this.disabled = true;
        try {
            const res = await sendJson(this.dataset.url, 'POST');
            window.Toast?.success(res.message ?? t('admin.returns.return_approved'));
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.returns.action_failed'));
            this.disabled = false;
        }
    });

    document.getElementById('reject-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-reject');
        if (btn) btn.disabled = true;
        try {
            const fd = new FormData(this);
            const res = await sendJson(this.dataset.url, 'POST', {
                rejection_reason: fd.get('rejection_reason'),
            });
            window.Toast?.success(res.message ?? t('admin.returns.return_rejected'));
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.returns.action_failed'));
            if (btn) btn.disabled = false;
        }
    });

    document.getElementById('schedule-pickup-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-schedule-pickup');
        if (btn) btn.disabled = true;
        try {
            const fd = new FormData(this);
            const res = await sendJson(this.dataset.url, 'POST', {
                scheduled_date: fd.get('scheduled_date') || null,
            });
            window.Toast?.success(res.message ?? t('admin.returns.pickup_scheduled'));
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.returns.action_failed'));
            if (btn) btn.disabled = false;
        }
    });

    document.getElementById('btn-mark-received')?.addEventListener('click', async function () {
        this.disabled = true;
        try {
            const res = await sendJson(this.dataset.url, 'POST');
            window.Toast?.success(res.message ?? t('admin.returns.return_received'));
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.returns.action_failed'));
            this.disabled = false;
        }
    });

    document.getElementById('inspect-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-inspect');
        if (btn) btn.disabled = true;
        try {
            const fd = new FormData(this);
            const res = await sendJson(this.dataset.url, 'POST', {
                inspection_outcome: fd.get('inspection_outcome'),
                inspection_notes: fd.get('inspection_notes') ?? '',
            });
            window.Toast?.success(res.message ?? t('admin.returns.inspection_recorded'));
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.returns.action_failed'));
            if (btn) btn.disabled = false;
        }
    });
}

// ─── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initReturnsTable();
    initReturnShowPage();
});
