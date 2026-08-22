/**
 * vendor/coupons.js
 * Vendor Coupons module: DataTable, create/edit form, product multi-select
 */

import '../partner/app.js';
import { createPartnerTable, csrfToken, postJson, toast } from '../partner/datatable.js';

function scopeBadge(scope) {
    const map = {
        vendor: ['bg-blue-100 text-blue-700', t('partner.coupons.scope_store_wide')],
        product: ['bg-purple-100 text-purple-700', t('partner.coupons.scope_product')],
    };
    const [cls, label] = map[scope] ?? ['bg-gray-100 text-gray-600', scope];
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${label}</span>`;
}

function activeBadge(isActive, isExpired) {
    if (isExpired) return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">${t('partner.coupons.status_expired')}</span>`;
    return isActive
        ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">${t('partner.coupons.status_active')}</span>`
        : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">${t('partner.coupons.status_inactive')}</span>`;
}

function initCouponsDataTable() {
    const tableEl = document.getElementById('coupons-table');
    const cfg = window.COUPONS;
    if (!tableEl || !cfg) return;

    createPartnerTable('coupons-table', {
        url: cfg.datatableUrl,
        ajaxData: (d) => {
            d.type = document.getElementById('coupon-type-filter')?.value || '';
            d.is_active = document.getElementById('coupon-active-filter')?.value || '';
            d.search_term = document.getElementById('coupon-search')?.value || '';
        },
        searchInputId: 'coupon-search',
        order: [[7, 'desc']],
        columns: [
            { data: 'code', render: (d) => `<span class="font-mono font-medium">${d}</span>` },
            { data: 'name' },
            { data: 'type' },
            { data: 'scope', render: (d) => scopeBadge(d) },
            { data: null, render: (row) => row.type === 'percentage' ? `${row.value}%` : row.value },
            { data: null, render: (row) => row.usage_limit_total ? `${row.times_used} / ${row.usage_limit_total}` : `${row.times_used}` },
            { data: null, render: (row) => activeBadge(row.is_active, row.is_expired) },
            { data: 'valid_until' },
            {
                data: null,
                orderable: false,
                render: (row) => `<a href="${row.show_url}" class="text-blue-600 hover:underline text-xs font-medium">${t('partner.coupons.view')}</a>
                    <a href="${row.edit_url}" class="ms-3 text-gray-600 hover:underline text-xs font-medium">${t('partner.coupons.edit')}</a>`,
            },
        ],
    });

    document.getElementById('coupon-type-filter')?.addEventListener('change', () => window.location.reload());
    document.getElementById('coupon-active-filter')?.addEventListener('change', () => window.location.reload());
}

// ─────────────────────────────────────────────────────────────────────────────
// Create / Edit form
// ─────────────────────────────────────────────────────────────────────────────

function initCouponForm() {
    const form = document.getElementById('coupon-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const errorBox = document.getElementById('coupon-form-error');
        errorBox?.classList.add('hidden');

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        payload.is_active = formData.has('is_active');
        payload.is_stackable = formData.has('is_stackable');
        payload.product_ids = formData.getAll('product_ids[]');

        const url = form.dataset.url;
        const method = form.dataset.method || 'POST';

        const { ok, data } = await postJson(url, { ...payload, _method: method });

        if (ok && data.success) {
            toast(form.dataset.successMessage || t('partner.coupons.saved'));
            if (data.redirect) window.location.href = data.redirect;
            return;
        }

        const message = data?.message || t('partner.coupons.save_failed');
        if (errorBox) { errorBox.textContent = message; errorBox.classList.remove('hidden'); }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initCouponsDataTable();
    initCouponForm();
});
