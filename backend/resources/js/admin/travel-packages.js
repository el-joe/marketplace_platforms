/**
 * Admin Travel Packages JS
 * Handles: packages DataTable, approve/reject/expire modals.
 */

import DataTable from 'datatables.net';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function postJson(url, data = {}) {
    return $.ajax({
        url,
        type: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
}

/* ─── Packages DataTable ──────────────────────────────────────────────────── */

let packagesTable = null;

function initPackagesTable() {
    const el = document.getElementById('packages-table');
    if (!el) return;

    packagesTable = new DataTable('#packages-table', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes?.packagesDatatable ?? '',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.status = document.getElementById('filter-status')?.value ?? '';
                d.destination_travel_country_id = document.getElementById('filter-country')?.value ?? '';
                d.agency_id = document.getElementById('filter-agency')?.value ?? '';
                d.departure_from = document.getElementById('filter-departure-from')?.value ?? '';
                d.departure_to = document.getElementById('filter-departure-to')?.value ?? '';
                d.search = { value: document.getElementById('search-input')?.value ?? '' };
            },
        },
        columns: [
            { data: 'title' },
            { data: 'agency' },
            { data: 'destination', orderable: false },
            { data: 'price', orderable: false },
            { data: 'departure' },
            { data: 'seats', orderable: false },
            { data: 'status', orderable: false },
            { data: 'actions', title: '', orderable: false },
        ],
        order: [[6, 'asc']], // sort by status (pending first via backend default)
        pageLength: 25,
    });
}

/* ─── Filter controls ─────────────────────────────────────────────────────── */

function initFilters() {
    const inputs = ['filter-status', 'filter-country', 'filter-agency', 'filter-departure-from', 'filter-departure-to'];
    inputs.forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => packagesTable?.ajax.reload());
    });

    document.getElementById('search-input')?.addEventListener('input', function () {
        clearTimeout(this._debounce);
        this._debounce = setTimeout(() => packagesTable?.ajax.reload(), 400);
    });

    document.getElementById('clear-filters')?.addEventListener('click', () => {
        inputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const search = document.getElementById('search-input');
        if (search) search.value = '';
        packagesTable?.ajax.reload();
    });
}

/* ─── Approve modal ───────────────────────────────────────────────────────── */

let pendingApproveUrl = '';

function initApproveModal() {
    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-approve-btn');
        if (!btn) return;
        pendingApproveUrl = btn.dataset.url;
        document.getElementById('approve-package-name').textContent = btn.dataset.name;
        $('#approve-modal').modal('open');
    });

    document.getElementById('confirm-approve-btn')?.addEventListener('click', () => {
        postJson(pendingApproveUrl)
            .done(res => {
                $('#approve-modal').modal('close');
                packagesTable?.ajax.reload(null, false);
                showToast(res.message ?? (t('admin.travel_packages.approved_label')), 'success');
            })
            .fail(xhr => {
                showToast(xhr.responseJSON?.message ?? (t('admin.travel_packages.failed_approve')), 'error');
            });
    });
}

/* ─── Reject modal ────────────────────────────────────────────────────────── */

let pendingRejectUrl = '';

function initRejectModal() {
    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-reject-btn');
        if (!btn) return;
        pendingRejectUrl = btn.dataset.url;
        document.getElementById('reject-package-name').textContent = btn.dataset.name;
        document.getElementById('reject-reason-input').value = '';
        document.getElementById('reject-reason-error').classList.add('hidden');
        $('#reject-modal').modal('open');
    });

    document.getElementById('confirm-reject-btn')?.addEventListener('click', () => {
        const reason = document.getElementById('reject-reason-input').value.trim();
        if (!reason) {
            document.getElementById('reject-reason-error').classList.remove('hidden');
            return;
        }
        postJson(pendingRejectUrl, { rejection_reason: reason })
            .done(res => {
                $('#reject-modal').modal('close');
                packagesTable?.ajax.reload(null, false);
                showToast(res.message ?? (t('admin.travel_packages.rejected_label')), 'success');
            })
            .fail(xhr => {
                showToast(xhr.responseJSON?.message ?? (t('admin.travel_packages.failed_reject')), 'error');
            });
    });
}

/* ─── Expire modal ────────────────────────────────────────────────────────── */

function initExpireModal() {
    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-expire-btn');
        if (!btn) return;
        const url = btn.dataset.url;
        if (!confirm(t('admin.travel_packages.mark_package_expired_confirm'))) return;
        postJson(url)
            .done(res => {
                packagesTable?.ajax.reload(null, false);
                showToast(res.message ?? (t('admin.travel_packages.expired_label')), 'success');
            })
            .fail(xhr => {
                showToast(xhr.responseJSON?.message ?? (t('shared.failed_generic')), 'error');
            });
    });
}

function showToast(message, type = 'success') {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
}

/* ─── Boot ────────────────────────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {
    initPackagesTable();
    initFilters();
    initApproveModal();
    initRejectModal();
    initExpireModal();
});
