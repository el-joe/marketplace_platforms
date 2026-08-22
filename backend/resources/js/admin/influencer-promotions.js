import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function postAction(url, onSuccess) {
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({}),
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) throw new Error(data.message || 'Failed.');
            window.Toast?.success(data.message);
            onSuccess();
        })
        .catch(err => window.Toast?.error(err.message || 'Failed.'));
}

function initInfluencerPromotionsTable() {
    const tableEl = document.getElementById('influencer-promotions-table');
    if (!tableEl) return;

    const dt = new DataTable('#influencer-promotions-table', {
        processing: true,
        serverSide: true,
        language: dtLanguage,
        order: [[8, 'desc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.search.value = document.getElementById('search-input')?.value ?? '';
                d.status = document.getElementById('filter-status')?.value ?? '';
                d.vendor_id = document.getElementById('filter-vendor_id')?.value ?? '';
                d.listing_fulfillment_model = document.getElementById('filter-listing_fulfillment_model')?.value ?? '';
                d.warehouse_receipt_confirmed = document.getElementById('filter-warehouse_receipt_confirmed')?.value ?? '';
            },
        },
        columns: [
            { data: 'request_no', orderable: false },
            { data: 'vendor', orderable: false },
            { data: 'listing', orderable: false },
            { data: 'total_slots', orderable: false },
            { data: 'status' },
            { data: 'fee' },
            { data: 'fulfillment_model' },
            { data: 'warehouse_required', orderable: false },
            { data: 'created_at' },
            { data: 'actions', orderable: false },
        ],
        pageLength: 25,
    });

    tableEl.addEventListener('click', (e) => {
        const cancelBtn = e.target.closest('.btn-cancel-promotion');
        if (cancelBtn) {
            if (!confirm(t('admin.influencer_promotions.cancel_promotion_confirm'))) return;
            postAction(`/influencer-promotions/${cancelBtn.dataset.id}/cancel`, () => dt.draw(false));
            return;
        }

        const confirmBtn = e.target.closest('.btn-confirm-warehouse');
        if (confirmBtn) {
            if (!confirm(t('admin.influencer_promotions.confirm_stock_received'))) return;
            postAction(`/influencer-promotions/${confirmBtn.dataset.id}/confirm-warehouse-receipt`, () => dt.draw(false));
        }
    });

    ['filter-status', 'filter-vendor_id', 'filter-listing_fulfillment_model', 'filter-warehouse_receipt_confirmed'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.draw());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.draw(), 350);
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-status', 'filter-vendor_id', 'filter-listing_fulfillment_model', 'filter-warehouse_receipt_confirmed'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const s = document.getElementById('search-input');
        if (s) s.value = '';
        dt.draw();
    });

    return dt;
}

document.addEventListener('DOMContentLoaded', () => {
    initInfluencerPromotionsTable();
});
