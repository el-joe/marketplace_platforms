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

async function deleteJson(url) {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw json;
    return json;
}

// ─── Index Page ───────────────────────────────────────────────────────────────

function initIndexPage() {
    const tableEl = document.getElementById('reviews-table');
    if (!tableEl) return;

    // ── DataTable init ─────────────────────────────────────────────────────────
    const dt = new DataTable('#reviews-table', {
        processing: true,
        serverSide: true,
        order: [[9, 'desc']], // created_at desc
        ajax: {
            url: '/reviews/datatable',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.status = document.getElementById('filter-status')?.value ?? '';
                d.country_id = document.getElementById('filter-country')?.value ?? '';
                d.listing_type = document.getElementById('filter-listing-type')?.value ?? '';
                d.date_from = document.getElementById('filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('filter-date-to')?.value ?? '';
                d.verified_only = document.getElementById('filter-verified')?.checked ? 1 : 0;
                d.ai_flagged = document.getElementById('filter-ai-flagged')?.checked ? 1 : 0;
                d.rating = getSelectedRatings();
                d.search = document.getElementById('search-input')?.value ?? '';
            },
        },
        columns: [
            { data: 'checkbox', orderable: false, searchable: false, width: '32px' },
            { data: 'product', orderable: true },
            { data: 'customer', orderable: true },
            { data: 'rating', orderable: true },
            { data: 'status', orderable: true },
            { data: 'ai_flag', orderable: false },
            { data: 'verified', orderable: false },
            { data: 'helpful', orderable: true },
            { data: 'not_helpful', orderable: true },
            { data: 'created_at', orderable: true },
            { data: 'actions', orderable: false, searchable: false },
        ],
        pageLength: 25,
        rowId: 'DT_RowId',
    });

    // ── Filter wiring ──────────────────────────────────────────────────────────
    ['filter-status', 'filter-country', 'filter-listing-type', 'filter-date-from', 'filter-date-to', 'filter-verified', 'filter-ai-flagged'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.ajax.reload());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.ajax.reload(), 350);
    });

    document.getElementById('clear-filters')?.addEventListener('click', () => {
        ['filter-status', 'filter-country', 'filter-listing-type', 'filter-date-from', 'filter-date-to'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        ['filter-verified', 'filter-ai-flagged'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.checked = false;
        });
        document.getElementById('search-input').value = '';
        document.querySelectorAll('.rating-checkbox').forEach(cb => { cb.checked = false; });
        document.querySelectorAll('.rating-star-btn').forEach(b => b.classList.remove('border-yellow-400', 'text-yellow-500', 'bg-yellow-50'));
        dt.ajax.reload();
    });

    // ── Rating checkboxes (star buttons) ──────────────────────────────────────
    document.querySelectorAll('.rating-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            const btn = cb.closest('label')?.querySelector('.rating-star-btn');
            if (cb.checked) {
                btn?.classList.add('border-yellow-400', 'text-yellow-500', 'bg-yellow-50');
            } else {
                btn?.classList.remove('border-yellow-400', 'text-yellow-500', 'bg-yellow-50');
            }
            dt.ajax.reload();
        });
    });

    // ── Quick action tabs ──────────────────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('border-primary-600', 'text-primary-600');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            this.classList.remove('border-transparent', 'text-gray-500');
            this.classList.add('border-primary-600', 'text-primary-600');

            const status = this.dataset.status ?? '';
            const filterEl = document.getElementById('filter-status');
            if (filterEl) filterEl.value = status;
            dt.ajax.reload();
        });
    });

    // AI flagged tab trigger from alert
    document.getElementById('tab-ai-flagged-trigger')?.addEventListener('click', () => {
        document.querySelector('.tab-btn[data-tab="ai_flagged"]')?.click();
    });

    // ── Bulk selection ─────────────────────────────────────────────────────────
    const bulkBar = document.getElementById('bulk-action-bar');
    const selectedCount = document.getElementById('selected-count');
    const selectAllCb = document.getElementById('select-all-checkbox');

    function getSelectedIds() {
        return [...document.querySelectorAll('.row-checkbox:checked')].map(c => c.value);
    }

    function updateBulkBar() {
        const ids = getSelectedIds();
        if (ids.length > 0) {
            bulkBar?.classList.remove('hidden');
            if (selectedCount) selectedCount.textContent = ids.length;
        } else {
            bulkBar?.classList.add('hidden');
        }
        if (selectAllCb) {
            const total = document.querySelectorAll('.row-checkbox').length;
            selectAllCb.checked = ids.length === total && total > 0;
        }
    }

    tableEl.addEventListener('change', e => {
        if (e.target.classList.contains('row-checkbox')) updateBulkBar();
    });

    selectAllCb?.addEventListener('change', function () {
        document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = this.checked; });
        updateBulkBar();
    });

    document.getElementById('deselect-all-btn')?.addEventListener('click', () => {
        document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
        if (selectAllCb) selectAllCb.checked = false;
        updateBulkBar();
    });

    // Reset checkboxes on DataTable redraw
    dt.on('draw', () => {
        if (selectAllCb) selectAllCb.checked = false;
        updateBulkBar();
    });

    // ── Bulk actions ───────────────────────────────────────────────────────────
    async function doBulkAction(action) {
        const ids = getSelectedIds();
        if (!ids.length) return;

        const labels = {
            approve: t('admin.reviews.action_approve_label'),
            reject: t('admin.reviews.action_reject_label'),
            delete: t('admin.reviews.action_delete_label'),
        };
        const label = labels[action] || (action.charAt(0).toUpperCase() + action.slice(1));
        const confirmMsg = t('admin.reviews.bulk_action_confirm', { label, count: ids.length });
        if (!confirm(confirmMsg)) return;

        try {
            const res = await postJson('/admin/reviews/bulk-action', { action, ids });
            window.Toast?.success(res.message ?? t('admin.reviews.bulk_action_done', { action: label }));
            dt.ajax.reload();
        } catch (e) {
            window.Toast?.error(e.message ?? t('admin.reviews.bulk_action_failed', { action: label }));
        }
    }

    document.getElementById('bulk-approve-btn')?.addEventListener('click', () => doBulkAction('approve'));
    document.getElementById('bulk-reject-btn')?.addEventListener('click', () => doBulkAction('reject'));
    document.getElementById('bulk-delete-btn')?.addEventListener('click', () => doBulkAction('delete'));

    // ── Row-level actions (event delegation on table body) ─────────────────────
    let pendingRejectUrl = null;

    tableEl.addEventListener('click', async e => {
        const btn = e.target.closest('button');
        if (!btn) return;

        if (btn.classList.contains('js-approve-btn')) {
            const url = btn.dataset.url;
            if (!confirm(t('admin.reviews.approve_publish_confirm'))) return;
            try {
                const res = await postJson(url, {});
                window.Toast?.success(res.message ?? (t('admin.reviews.review_approved')));
                dt.ajax.reload();
            } catch (er) {
                window.Toast?.error(er.message ?? (t('admin.reviews.approval_failed')));
            }
        }

        if (btn.classList.contains('js-reject-btn')) {
            pendingRejectUrl = btn.dataset.url;
            document.getElementById('reject-reason-input').value = '';
            $('#reject-modal').modal('open');
        }

        if (btn.classList.contains('js-delete-btn')) {
            const url = btn.dataset.url;
            if (!confirm(t('admin.reviews.delete_permanently_confirm'))) return;
            try {
                const res = await deleteJson(url);
                window.Toast?.success(res.message ?? (t('admin.reviews.review_deleted')));
                dt.ajax.reload();
            } catch (er) {
                window.Toast?.error(er.message ?? (t('admin.reviews.delete_failed')));
            }
        }
    });

    // ── Reject modal confirm ────────────────────────────────────────────────────
    document.getElementById('confirm-reject-btn')?.addEventListener('click', async () => {
        const url = pendingRejectUrl;
        if (!url) return;

        const reason = document.getElementById('reject-reason-input')?.value.trim() ?? '';
        const btn = document.getElementById('confirm-reject-btn');
        btn.disabled = true;
        btn.textContent = t('admin.reviews.rejecting_label');

        try {
            const res = await postJson(url, { reason: reason || null });
            window.Toast?.success(res.message ?? (t('admin.reviews.review_rejected')));
            $('#reject-modal').modal('close');
            dt.ajax.reload();
        } catch (e) {
            window.Toast?.error(e.message ?? (t('admin.reviews.rejection_failed')));
        } finally {
            btn.disabled = false;
            btn.textContent = t('admin.reviews.reject_review_label');
        }
    });
}

function getSelectedRatings() {
    return [...document.querySelectorAll('.rating-checkbox:checked')].map(c => c.value);
}

// ─── Show Page ────────────────────────────────────────────────────────────────

function initShowPage() {
    if (!window.reviewShow) return;

    // Approve button
    document.getElementById('approve-btn')?.addEventListener('click', async function () {
        if (!confirm(t('admin.reviews.approve_publish_confirm'))) return;
        this.disabled = true;
        this.textContent = t('admin.reviews.approving_label');
        try {
            const res = await postJson(window.reviewShow.approveUrl, {});
            window.Toast?.success(res.message ?? (t('admin.reviews.review_approved')));
            setTimeout(() => window.location.reload(), 1000);
        } catch (e) {
            window.Toast?.error(e.message ?? (t('admin.reviews.approval_failed')));
            this.disabled = false;
            this.textContent = t('admin.reviews.approve_and_publish_short');
        }
    });

    // Open reject modal
    document.getElementById('open-reject-modal-btn')?.addEventListener('click', () => {
        document.getElementById('reject-reason-input').value = '';
        $('#reject-modal').modal('open');
    });

    // Confirm reject
    document.getElementById('confirm-reject-btn')?.addEventListener('click', async function () {
        const reason = document.getElementById('reject-reason-input')?.value.trim() ?? '';
        this.disabled = true;
        this.textContent = t('admin.reviews.rejecting_label');
        try {
            const res = await postJson(window.reviewShow.rejectUrl, { reason: reason || null });
            window.Toast?.success(res.message ?? (t('admin.reviews.review_rejected')));
            $('#reject-modal').modal('close');
            setTimeout(() => window.location.href = window.reviewShow.indexUrl, 800);
        } catch (e) {
            window.Toast?.error(e.message ?? (t('admin.reviews.rejection_failed')));
            this.disabled = false;
            this.textContent = t('admin.reviews.confirm_reject_label');
        }
    });

    // Delete button
    document.getElementById('delete-btn')?.addEventListener('click', async function () {
        if (!confirm(t('admin.reviews.delete_permanently_confirm'))) return;
        this.disabled = true;
        this.textContent = t('admin.reviews.deleting_label');
        try {
            const res = await deleteJson(window.reviewShow.deleteUrl);
            window.Toast?.success(res.message ?? (t('admin.reviews.review_deleted')));
            setTimeout(() => window.location.href = window.reviewShow.indexUrl, 800);
        } catch (e) {
            window.Toast?.error(e.message ?? (t('admin.reviews.delete_failed')));
            this.disabled = false;
            this.textContent = t('admin.reviews.delete_permanently_label');
        }
    });

    // Vendor reply toggle buttons
    document.querySelectorAll('.js-reply-toggle-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const url = this.dataset.url;
            const action = this.dataset.action;
            try {
                const res = await postJson(url, {});
                window.Toast?.success(res.message ?? (action === 'hide' ? t('admin.reviews.reply_hidden') : t('admin.reviews.reply_shown')));
                setTimeout(() => window.location.reload(), 800);
            } catch (e) {
                window.Toast?.error(e.message ?? (t('admin.reviews.action_failed')));
            }
        });
    });
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initIndexPage();
    initShowPage();
});
