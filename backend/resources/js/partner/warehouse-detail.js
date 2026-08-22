/**
 * partner/warehouse-detail.js
 * Warehouse show page: inventory table + adjust stock modal + edit warehouse
 */

import './app.js';
import { csrfToken, postJson, showModal, hideModal, toast } from './datatable.js';

async function putJson(url, data = {}) {
    const res = await fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify(data),
    });
    return { ok: res.ok, status: res.status, data: await res.json() };
}

// ─── Inventory Table ──────────────────────────────────────────────────────────

let inventoryPage = 1;
let inventoryMeta = { last_page: 1, total: 0 };

async function loadInventory(page = 1) {
    const cfg      = window.WAREHOUSE_CFG;
    const warehouseId = window.WAREHOUSE_ID;
    if (!cfg || !warehouseId) return;

    const search    = document.getElementById('inventory-search')?.value ?? '';
    const statusVal = document.getElementById('inventory-status-filter')?.value ?? '';
    const tbody     = document.getElementById('inventory-tbody');
    const loading   = document.getElementById('inventory-loading');
    const emptyEl   = document.getElementById('inventory-empty');

    loading?.classList.remove('hidden');
    tbody.innerHTML = '';
    emptyEl?.classList.add('hidden');

    const params = new URLSearchParams({ page, per_page: 20 });
    if (search)              params.set('search', search);
    if (statusVal === 'low_stock')   params.set('low_stock', '1');
    if (statusVal === 'out_of_stock') params.set('out_of_stock', '1');

    try {
        const res  = await fetch(`${cfg.inventoryUrl}?${params}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        });
        const json = await res.json();

        loading?.classList.add('hidden');
        inventoryPage = json.current_page;
        inventoryMeta = { last_page: json.last_page, total: json.total };

        if (!json.data.length) {
            emptyEl?.classList.remove('hidden');
            renderPagination();
            return;
        }

        json.data.forEach(row => tbody.appendChild(buildInventoryRow(row)));
        renderPagination();
    } catch {
        loading?.classList.add('hidden');
        if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-red-500 text-sm">${t('partner.warehouse.failed_to_load_inventory')}</td></tr>`;
    }
}

function buildInventoryRow(row) {
    const isOwned  = window.IS_OWNED;
    const imgHtml  = row.image_url
        ? `<img src="${row.image_url}" class="w-8 h-8 rounded-lg object-cover flex-shrink-0" />`
        : `<div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-300 text-xs flex-shrink-0">📦</div>`;

    let statusBadge = '';
    if (row.is_out_of_stock) {
        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">${t('partner.warehouse.out_of_stock')}</span>`;
    } else if (row.is_low_stock) {
        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">${t('partner.warehouse.low_stock')}</span>`;
    } else {
        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${t('partner.warehouse.in_stock_ok')}</span>`;
    }

    const adjustCell = isOwned
        ? `<td class="px-4 py-3">
            <button class="open-adjust-btn text-xs text-primary-600 hover:text-primary-800 font-medium border border-primary-200 rounded-lg px-2 py-1"
                data-row='${JSON.stringify(row).replace(/'/g, "&#39;")}'>
                ±
            </button>
           </td>`
        : '';

    const tr = document.createElement('tr');
    tr.className = 'hover:bg-gray-50';
    tr.innerHTML = `
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                ${imgHtml}
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate max-w-[180px]">${escHtml(row.product_name)}</p>
                    <p class="text-xs text-gray-500">${escHtml(row.variant_name)}</p>
                    <p class="text-xs font-mono text-gray-400">${escHtml(row.vendor_sku)}</p>
                </div>
            </div>
        </td>
        <td class="px-4 py-3 text-xs text-gray-500">${escHtml(row.bin_location ?? '—')}</td>
        <td class="px-4 py-3 text-right font-semibold">${row.quantity_on_hand}</td>
        <td class="px-4 py-3 text-right text-gray-500">${row.quantity_reserved}</td>
        <td class="px-4 py-3 text-right font-bold ${row.quantity_available > 0 ? 'text-green-700' : 'text-red-600'}">${row.quantity_available}</td>
        <td class="px-4 py-3 text-right text-blue-600">${row.quantity_inbound}</td>
        <td class="px-4 py-3 text-right text-red-500">${row.quantity_damaged}</td>
        <td class="px-4 py-3 text-center">${statusBadge}</td>
        ${adjustCell}`;

    if (isOwned) {
        tr.querySelector('.open-adjust-btn')?.addEventListener('click', (e) => {
            const data = JSON.parse(e.currentTarget.dataset.row);
            openAdjustModal(data);
        });
    }

    return tr;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str ?? '')));
    return d.innerHTML;
}

function renderPagination() {
    const el = document.getElementById('inventory-pagination');
    if (!el) return;

    const from = (inventoryPage - 1) * 20 + 1;
    const to   = Math.min(inventoryPage * 20, inventoryMeta.total);

    el.innerHTML = `
        <span class="text-xs text-gray-500">${inventoryMeta.total > 0 ? t('partner.warehouse.showing_range', { from, to, total: inventoryMeta.total }) : t('partner.warehouse.no_results')}</span>
        <div class="flex gap-2">
            <button id="prev-page-btn" ${inventoryPage <= 1 ? 'disabled' : ''}
                class="px-3 py-1 text-xs border border-gray-200 rounded-lg disabled:opacity-40 hover:bg-gray-50">
                ${t('partner.warehouse.prev')}
            </button>
            <button id="next-page-btn" ${inventoryPage >= inventoryMeta.last_page ? 'disabled' : ''}
                class="px-3 py-1 text-xs border border-gray-200 rounded-lg disabled:opacity-40 hover:bg-gray-50">
                ${t('partner.warehouse.next')}
            </button>
        </div>`;

    el.querySelector('#prev-page-btn')?.addEventListener('click', () => loadInventory(inventoryPage - 1));
    el.querySelector('#next-page-btn')?.addEventListener('click', () => loadInventory(inventoryPage + 1));
}

// ─── Adjust Stock Modal ───────────────────────────────────────────────────────

let adjustCurrentOnHand = 0;

function openAdjustModal(row) {
    adjustCurrentOnHand = row.quantity_on_hand;

    document.getElementById('adjust-inventory-id').value     = row.id;
    document.getElementById('adjust-product-name').textContent  = row.product_name;
    document.getElementById('adjust-product-variant').textContent = row.variant_name;
    document.getElementById('adjust-product-img').src        = row.image_url || '';
    document.getElementById('current-on-hand').textContent   = row.quantity_on_hand;
    document.getElementById('current-reserved').textContent  = row.quantity_reserved;
    document.getElementById('current-available').textContent = row.quantity_available;
    document.getElementById('adjust-amount').value           = 0;
    document.getElementById('adjust-reason').value           = '';
    document.getElementById('adjust-error')?.classList.add('hidden');
    updateAdjustPreview();

    showModal('adjust-modal');
}

function updateAdjustPreview() {
    const amount = parseInt(document.getElementById('adjust-amount')?.value, 10) || 0;
    const newVal = adjustCurrentOnHand + amount;
    const previewEl = document.getElementById('adjust-new-value');
    if (previewEl) {
        previewEl.textContent = newVal;
        previewEl.className = `font-bold ${newVal < 0 ? 'text-red-600' : 'text-primary-900'}`;
    }
}

function initAdjustModal() {
    if (!window.IS_OWNED) {
        // FBN warehouses — remove all adjust/count buttons (safety net, server already hides them)
        document.querySelectorAll('.open-adjust-btn, .stock-count-btn').forEach(el => el.remove());
        return;
    }

    const amountInput = document.getElementById('adjust-amount');
    amountInput?.addEventListener('input', updateAdjustPreview);

    document.getElementById('adjust-minus-btn')?.addEventListener('click', () => {
        const cur = parseInt(amountInput?.value, 10) || 0;
        if (amountInput) amountInput.value = cur - 1;
        updateAdjustPreview();
    });

    document.getElementById('adjust-plus-btn')?.addEventListener('click', () => {
        const cur = parseInt(amountInput?.value, 10) || 0;
        if (amountInput) amountInput.value = cur + 1;
        updateAdjustPreview();
    });

    document.getElementById('confirm-adjust-btn')?.addEventListener('click', async () => {
        const btn     = document.getElementById('confirm-adjust-btn');
        const invId   = document.getElementById('adjust-inventory-id').value;
        const amount  = parseInt(amountInput?.value, 10) || 0;
        const reason  = document.getElementById('adjust-reason')?.value?.trim();
        const type    = document.getElementById('adjust-type-select')?.value;
        const errEl   = document.getElementById('adjust-error');

        errEl?.classList.add('hidden');

        if (!reason) {
            errEl.textContent = t('partner.warehouse.provide_reason');
            errEl.classList.remove('hidden');
            return;
        }

        btn.disabled = true;
        const cfg = window.WAREHOUSE_CFG;
        const { ok, data } = await postJson(`${cfg.adjustBaseUrl}/${invId}/adjust`, {
            adjustment:    amount,
            reason:        reason,
            movement_type: type,
        });

        if (ok && data.success) {
            toast(t('partner.warehouse.stock_adjusted'));
            hideModal('adjust-modal');
            loadInventory(inventoryPage);
        } else {
            errEl.textContent = data.message || t('partner.warehouse.adjust_failed');
            errEl.classList.remove('hidden');
        }

        btn.disabled = false;
    });
}

// ─── Edit Warehouse Modal ─────────────────────────────────────────────────────

function initEditWarehouse() {
    const editBtn = document.getElementById('edit-warehouse-btn');
    if (!editBtn) return;

    editBtn.addEventListener('click', () => showModal('edit-warehouse-modal'));

    document.getElementById('edit-warehouse-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn   = document.getElementById('save-warehouse-btn');
        const errEl = document.getElementById('edit-wh-error');
        errEl.classList.add('hidden');
        btn.disabled = true;

        const payload = {
            name:              document.getElementById('edit-wh-name')?.value,
            street_address:    document.getElementById('edit-wh-street')?.value,
            area:              document.getElementById('edit-wh-area')?.value || null,
            building:          document.getElementById('edit-wh-building')?.value || null,
            total_capacity_m3: document.getElementById('edit-wh-capacity')?.value || null,
        };

        const { ok, data } = await putJson(`/partner/warehouses/${window.WAREHOUSE_ID}`, payload);

        if (ok && data.success) {
            toast(t('partner.warehouse.warehouse_updated'));
            setTimeout(() => location.reload(), 600);
        } else {
            errEl.textContent = data.message || t('partner.warehouse.update_failed');
            errEl.classList.remove('hidden');
            btn.disabled = false;
        }
    });

    document.getElementById('deactivate-btn')?.addEventListener('click', async () => {
        if (!confirm(t('shared.confirm_title'))) return;

        const { ok, data } = await postJson(`/partner/warehouses/${window.WAREHOUSE_ID}/deactivate`);

        if (ok && data.success) {
            toast(t('partner.warehouse.status_updated'));
            setTimeout(() => location.reload(), 600);
        } else {
            toast(data.message || t('partner.warehouse.operation_failed'), 'error');
        }
    });
}

// ─── Filters ──────────────────────────────────────────────────────────────────

function initFilters() {
    let searchTimer;

    document.getElementById('inventory-search')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadInventory(1), 350);
    });

    document.getElementById('inventory-status-filter')?.addEventListener('change', () => loadInventory(1));
}

// ─── Boot ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    loadInventory(1);
    initAdjustModal();
    initEditWarehouse();
    initFilters();
});
