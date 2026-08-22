/**
 * partner/warehouses.js
 * Index page + Transfers index & show pages
 */

import './app.js';
import { createPartnerTable, postJson, showModal, hideModal, toast } from './datatable.js';

// ─── Warehouses Index DataTables ────────────────────────────────────────────

function initWarehousesIndexDatatables() {
    const cfg = window.WAREHOUSES_INDEX_CFG;
    if (!cfg) return;

    if (document.getElementById('seller-warehouses-table')) {
        createPartnerTable('seller-warehouses-table', {
            url: cfg.sellerDtUrl,
            ajaxData: (d) => {
                const searchEl = document.getElementById('seller-warehouses-search');
                d.search = { value: searchEl ? searchEl.value : '' };
            },
            searchInputId: 'seller-warehouses-search',
            order: [[1, 'asc']],
            columns: [
                { data: null, orderable: false, searchable: false, render: (d, t, r, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'code', orderable: false, searchable: false },
                { data: 'name', orderable: false, searchable: false },
                { data: 'country', orderable: false, searchable: false },
                { data: 'total_units', orderable: false, searchable: false, className: 'text-right' },
                { data: 'sku_count', orderable: false, searchable: false, className: 'text-right' },
                { data: 'status', orderable: false, searchable: false, className: 'text-center' },
                { data: 'actions', orderable: false, searchable: false, className: 'text-right' },
            ],
            language: { emptyTable: t('partner.warehouses.no_warehouse_yet') },
        });
    }

    if (document.getElementById('fbn-warehouses-table')) {
        createPartnerTable('fbn-warehouses-table', {
            url: cfg.fbnDtUrl,
            ajaxData: (d) => {
                const searchEl = document.getElementById('fbn-warehouses-search');
                d.search = { value: searchEl ? searchEl.value : '' };
            },
            searchInputId: 'fbn-warehouses-search',
            order: [[1, 'asc']],
            columns: [
                { data: null, orderable: false, searchable: false, render: (d, t, r, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'name', orderable: false, searchable: false },
                { data: 'country', orderable: false, searchable: false },
                { data: 'my_units', orderable: false, searchable: false, className: 'text-right' },
                { data: 'my_sku_count', orderable: false, searchable: false, className: 'text-right' },
                { data: 'actions', orderable: false, searchable: false, className: 'text-right' },
            ],
            language: { emptyTable: t('partner.warehouses.no_platform_stock') },
        });
    }
}

// ─── Transfers DataTable ──────────────────────────────────────────────────────

function initTransfersDatatable() {
    const cfg = window.TRANSFERS_CFG;
    if (!cfg || !document.getElementById('transfers-table')) return;

    createPartnerTable('transfers-table', {
        url: cfg.datatableUrl,
        columns: [
            { data: 'number' },
            { data: 'source' },
            { data: 'destination' },
            { data: 'status' },
            { data: 'date' },
            { data: 'actions', orderable: false, searchable: false },
        ],
        order: [[4, 'desc']],
        searchInputId: 'transfers-search',
        language: {
            emptyTable:  t('partner.warehouses.no_transfers_found'),
            info:        t('partner.warehouses.showing_range_transfers'),
            infoEmpty:   t('partner.warehouses.no_transfers'),
            zeroRecords: t('partner.warehouses.no_transfers_match_search'),
            processing:  '<div class="flex justify-center py-8"><div class="w-6 h-6 border-2 border-primary-400 border-t-transparent rounded-full animate-spin"></div></div>',
        },
    });
}

// ─── Transfer Show ────────────────────────────────────────────────────────────

function initTransferShow() {
    const cfg = window.TRANSFER_SHOW_CFG;
    if (!cfg) return;

    const shipBtn   = document.getElementById('ship-transfer-btn');
    const cancelBtn = document.getElementById('cancel-transfer-btn');

    if (shipBtn) {
        shipBtn.addEventListener('click', () => showModal('ship-modal'));
    }

    const confirmShipBtn = document.getElementById('confirm-ship-btn');
    if (confirmShipBtn) {
        confirmShipBtn.addEventListener('click', async () => {
            confirmShipBtn.disabled = true;
            const errEl = document.getElementById('ship-error');
            errEl.classList.add('hidden');

            const { ok, data } = await postJson(cfg.shipUrl, {
                carrier:          document.getElementById('ship-carrier')?.value || null,
                tracking_number:  document.getElementById('ship-tracking')?.value || null,
            });

            if (ok && data.success) {
                toast(t('partner.warehouses.transfer_shipped'));
                setTimeout(() => location.reload(), 800);
            } else {
                errEl.textContent = data.message || t('partner.warehouses.failed_update_transfer');
                errEl.classList.remove('hidden');
                confirmShipBtn.disabled = false;
            }
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', async () => {
            if (!confirm(t('partner.warehouses.cancel_transfer_confirm'))) return;
            cancelBtn.disabled = true;

            const { ok, data } = await postJson(cfg.cancelUrl);
            if (ok && data.success) {
                toast(t('partner.warehouses.transfer_cancelled'));
                setTimeout(() => location.reload(), 800);
            } else {
                toast(data.message || t('partner.warehouses.failed_cancel_transfer'), 'error');
                cancelBtn.disabled = false;
            }
        });
    }
}

// ─── Boot ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initWarehousesIndexDatatables();
    initTransfersDatatable();
    initTransferShow();
});
