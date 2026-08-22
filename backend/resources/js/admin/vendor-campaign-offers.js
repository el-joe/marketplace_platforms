/**
 * resources/js/admin/vendor-campaign-offers.js
 *
 * Index page — DataTable, filters, approve/reject actions.
 * Globals injected by the Blade view via an inline <script> block:
 *   VENDOR_CAMPAIGN_OFFERS_DATATABLE_URL, VENDOR_CAMPAIGN_OFFERS_CSRF_TOKEN
 */

document.addEventListener('DOMContentLoaded', () => {
    const headers = {
        'X-CSRF-TOKEN': VENDOR_CAMPAIGN_OFFERS_CSRF_TOKEN,
        'Accept': 'application/json',
    };

    const table = window.initDataTable('offers-table', {
        url: VENDOR_CAMPAIGN_OFFERS_DATATABLE_URL,
        serverSideFilters: {
            status:    () => document.getElementById('filter-status').value,
            vendor_id: () => document.getElementById('filter-vendor').value,
            date_from: () => document.getElementById('filter-date-from').value,
            date_to:   () => document.getElementById('filter-date-to').value,
        },
        columns: [
            { data: 'vendor' },
            { data: 'name' },
            { data: 'type' },
            { data: 'commission' },
            { data: 'products' },
            { data: 'date_range' },
            { data: 'invited' },
            { data: 'status' },
            { data: 'actions', orderable: false },
        ],
        order: [[7, 'desc']],
        pageLength: 25,
    });

    // Filters
    document.getElementById('search-input').addEventListener('input', () => table.search(document.getElementById('search-input').value).draw());
    ['filter-status', 'filter-vendor', 'filter-date-from', 'filter-date-to'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => table.draw())
    );
    document.getElementById('filter-reset').addEventListener('click', () => {
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-vendor').value = '';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';
        document.getElementById('search-input').value = '';
        table.search('').draw();
    });

    // Approve
    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-approve-btn');
        if (!btn) return;
        if (!confirm(t('admin.vendor_campaign_offers.approve_offer_confirm', { name: btn.dataset.name }))) return;
        fetch(btn.dataset.url, { method: 'POST', headers })
            .then(r => r.json())
            .then(d => { alert(d.message); table.draw(); });
    });

    // Reject modal
    let rejectUrl = '';
    const modal    = document.getElementById('reject-modal');
    const reasonEl = document.getElementById('reject-reason');

    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-reject-btn');
        if (!btn) return;
        rejectUrl = btn.dataset.url;
        reasonEl.value = '';
        modal.classList.remove('hidden');
    });

    document.getElementById('reject-cancel').addEventListener('click', () => modal.classList.add('hidden'));

    document.getElementById('reject-confirm').addEventListener('click', () => {
        const reason = reasonEl.value.trim();
        if (!reason) { alert(t('admin.vendor_campaign_offers.rejection_reason_required')); return; }
        fetch(rejectUrl, {
            method: 'POST',
            headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ rejection_reason: reason }),
        })
            .then(r => r.json())
            .then(d => { alert(d.message); modal.classList.add('hidden'); table.draw(); });
    });
});
