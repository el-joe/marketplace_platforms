import DataTable from 'datatables.net';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, data = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(data),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw json;
    return json;
}

// ─── Copy to clipboard helper ─────────────────────────────────────────────────

function initCopyButtons(root = document) {
    root.addEventListener('click', e => {
        const btn = e.target.closest('.js-copy');
        if (!btn) return;
        const val = btn.dataset.value ?? btn.textContent.trim();
        navigator.clipboard?.writeText(val).then(() => {
            window.Toast?.success(t('admin.transactions.copied_to_clipboard'));
        }).catch(() => {
            window.Toast?.error(t('admin.transactions.copy_failed'));
        });
    });
}

// ─── Transactions Index ───────────────────────────────────────────────────────

function initTransactionsIndex() {
    const tableEl = document.getElementById('transactions-table');
    if (!tableEl) return;

    const dt = new DataTable('#transactions-table', {
        processing: true,
        serverSide: true,
        order: [[9, 'desc']],
        ajax: {
            url: '/transactions/datatable',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.type = document.getElementById('filter-type')?.value ?? '';
                d.status = document.getElementById('filter-status')?.value ?? '';
                d.gateway = document.getElementById('filter-gateway')?.value ?? '';
                d.payment_method = document.getElementById('filter-payment-method')?.value ?? '';
                d.date_from = document.getElementById('filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('filter-date-to')?.value ?? '';
                d.amount_min = document.getElementById('filter-amount-min')?.value ?? '';
                d.amount_max = document.getElementById('filter-amount-max')?.value ?? '';
                d.search = { value: document.getElementById('search-input')?.value ?? '' };
            },
        },
        columns: [
            { data: 'gateway_transaction_id', orderable: true },
            { data: 'order', orderable: true },
            { data: 'customer', orderable: true },
            { data: 'type', orderable: true },
            { data: 'gateway', orderable: true },
            { data: 'amount', orderable: true },
            { data: 'status', orderable: true },
            { data: 'failure_code', orderable: false },
            { data: 'processed_at', orderable: true },
            { data: 'created_at', orderable: true },
            { data: 'actions', orderable: false, searchable: false },
        ],
        rowId: 'DT_RowId',
        pageLength: 25,
    });

    // Filters
    ['filter-type', 'filter-status', 'filter-gateway', 'filter-payment-method', 'filter-date-from', 'filter-date-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.ajax.reload());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.ajax.reload(), 350);
    });

    let amountTimer;
    ['filter-amount-min', 'filter-amount-max'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            clearTimeout(amountTimer);
            amountTimer = setTimeout(() => dt.ajax.reload(), 500);
        });
    });

    document.getElementById('clear-filters')?.addEventListener('click', () => {
        ['filter-type', 'filter-status', 'filter-gateway', 'filter-payment-method', 'filter-date-from', 'filter-date-to', 'filter-amount-min', 'filter-amount-max'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('search-input').value = '';
        dt.ajax.reload();
    });
}

// ─── Transactions Show ────────────────────────────────────────────────────────

function initTransactionsShow() {
    // Nothing extra needed — Alpine handles collapsible JSON, copy handled globally
}

// ─── Refunds Page ─────────────────────────────────────────────────────────────

function initRefundsPage() {
    const tableEl = document.getElementById('refunds-table');
    if (!tableEl) return;

    const dt = new DataTable('#refunds-table', {
        processing: true,
        serverSide: true,
        order: [[6, 'asc']], // status asc so pending comes first
        ajax: {
            url: '/transactions/refunds/datatable',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.status = document.getElementById('refund-filter-status')?.value ?? 'pending';
                d.date_from = document.getElementById('refund-filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('refund-filter-date-to')?.value ?? '';
                d.search = { value: document.getElementById('refund-search-input')?.value ?? '' };
            },
        },
        columns: [
            { data: 'order', orderable: true },
            { data: 'customer', orderable: true },
            { data: 'amount', orderable: true },
            { data: 'reason', orderable: false },
            { data: 'refund_type', orderable: false },
            { data: 'vendor_charged_back', orderable: false },
            { data: 'status', orderable: true },
            { data: 'actions', orderable: false, searchable: false },
        ],
        rowId: 'DT_RowId',
        pageLength: 25,
    });

    // Filters
    ['refund-filter-status', 'refund-filter-date-from', 'refund-filter-date-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.ajax.reload());
    });

    let searchTimer;
    document.getElementById('refund-search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.ajax.reload(), 350);
    });

    document.getElementById('refund-clear-filters')?.addEventListener('click', () => {
        const statusEl = document.getElementById('refund-filter-status');
        if (statusEl) statusEl.value = '';
        ['refund-filter-date-from', 'refund-filter-date-to'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const search = document.getElementById('refund-search-input');
        if (search) search.value = '';
        dt.ajax.reload();
    });

    // Row-level actions (event delegation)
    let pendingRejectUrl = null;
    let pendingRejectAmount = '';

    tableEl.addEventListener('click', async e => {
        const btn = e.target.closest('button');
        if (!btn) return;

        if (btn.classList.contains('js-approve-refund')) {
            const url = btn.dataset.url;
            if (!confirm(t('admin.transactions.approve_refund_confirm'))) return;
            btn.disabled = true;
            try {
                const res = await postJson(url, {});
                window.Toast?.success(res.message ?? t('admin.transactions.refund_approved'));
                dt.ajax.reload();
            } catch (er) {
                window.Toast?.error(er.message ?? t('admin.transactions.approval_failed'));
                btn.disabled = false;
            }
        }

        if (btn.classList.contains('js-reject-refund')) {
            pendingRejectUrl = btn.dataset.url;
            pendingRejectAmount = btn.dataset.amount ?? '';
            const amountEl = document.getElementById('refund-reject-amount');
            if (amountEl) amountEl.textContent = pendingRejectAmount ? (window.TRANSLATIONS?.refundAmountLabel || 'Refund amount:') + ' ' + pendingRejectAmount : '';
            document.getElementById('refund-reject-reason').value = '';
            $('#refund-reject-modal').modal('open');
        }
    });

    document.getElementById('confirm-refund-reject-btn')?.addEventListener('click', async function () {
        if (!pendingRejectUrl) return;
        const reason = document.getElementById('refund-reject-reason')?.value.trim() ?? '';
        this.disabled = true;
        this.textContent = window.TRANSLATIONS?.rejecting || 'Rejecting…';
        try {
            const res = await postJson(pendingRejectUrl, { reason: reason || null });
            window.Toast?.success(res.message ?? (window.TRANSLATIONS?.refundRejected || 'Refund rejected.'));
            $('#refund-reject-modal').modal('close');
            dt.ajax.reload();
        } catch (e) {
            window.Toast?.error(e.message ?? (window.TRANSLATIONS?.rejectionFailed || 'Rejection failed.'));
        } finally {
            this.disabled = false;
            this.textContent = window.TRANSLATIONS?.confirmReject || 'Confirm Reject';
        }
    });
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initCopyButtons();
    initTransactionsIndex();
    initTransactionsShow();
    initRefundsPage();
});
