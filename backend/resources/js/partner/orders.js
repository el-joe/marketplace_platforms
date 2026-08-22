/**
 * partner/orders.js
 * Orders management: DataTable init + action modals (confirm / ship / cancel)
 */

import './app.js';
import { csrfToken, createPartnerTable, postJson, showModal, hideModal, showError, hideError, toast } from './datatable.js';

// ─────────────────────────────────────────────────────────────────────────────
// DataTable  (orders index page)
// ─────────────────────────────────────────────────────────────────────────────

const ORDERS = window.ORDERS;

function statusBadgeHtml(status) {
    const map = {
        placed: ['bg-yellow-100 text-yellow-700', 'معلق'],
        confirmed: ['bg-blue-100 text-blue-700', 'مؤكد'],
        processing: ['bg-purple-100 text-purple-700', 'جارٍ التجهيز'],
        packed: ['bg-indigo-100 text-indigo-700', 'جاهز للشحن'],
        shipped: ['bg-cyan-100 text-cyan-700', 'تم الشحن'],
        out_for_delivery: ['bg-teal-100 text-teal-700', 'في التوصيل'],
        delivered: ['bg-green-100 text-green-700', 'تم التسليم'],
        completed: ['bg-green-200 text-green-800', 'مكتمل'],
        cancelled: ['bg-red-100 text-red-700', 'ملغى'],
        return_requested: ['bg-orange-100 text-orange-700', 'طلب إرجاع'],
        returned: ['bg-gray-100 text-gray-700', 'مرتجع'],
    };
    const [cls, label] = map[status] ?? ['bg-gray-100 text-gray-600', status];
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${label}</span>`;
}

let ordersTable = null;

function initOrdersDataTable() {
    if (!document.getElementById('orders-table') || !ORDERS) return;

    ordersTable = createPartnerTable('orders-table', {
        url: ORDERS.datatableUrl,
        ajaxData: (d) => {
            d.status = currentStatusFilter();
            d.date_from = document.getElementById('filter-date-from')?.value ?? '';
            d.date_to = document.getElementById('filter-date-to')?.value ?? '';
        },
        order: [[6, 'desc']],
        language: {
            emptyTable:  '<div class="py-16 text-center"><div class="text-4xl mb-3">📦</div><p class="text-gray-500 font-medium">لا توجد طلبات</p></div>',
            zeroRecords: '<div class="py-16 text-center"><div class="text-4xl mb-3">🔍</div><p class="text-gray-500 font-medium">لا توجد نتائج مطابقة</p></div>',
            processing:  '<div class="flex justify-center py-12"><div class="w-7 h-7 border-2 border-yellow-400 border-t-transparent rounded-full animate-spin"></div></div>',
        },
        columns: [
            {
                data: 'sub_order_number',
                className: 'px-4 py-3',
                render: (data, type, row) =>
                    `<a href="${row.show_url}" class="font-mono text-sm text-blue-600 hover:text-blue-800 hover:underline">${data}</a>`,
            },
            {
                data: 'status',
                className: 'px-4 py-3',
                render: (data) => statusBadgeHtml(data),
            },
            {
                data: 'vendor_payout',
                className: 'px-4 py-3',
                render: (data) => `<span class="font-semibold text-gray-800">${data}</span>`,
            },
            {
                data: 'location',
                className: 'px-4 py-3 text-sm text-gray-700',
            },
            {
                data: 'item_count',
                className: 'px-4 py-3 text-center text-sm text-gray-600',
            },
            {
                data: 'sla_countdown',
                className: 'px-4 py-3',
                render: (data) => data,
                orderable: true,
            },
            {
                data: 'placed_at',
                className: 'px-4 py-3 text-sm text-gray-600',
            },
        ],
    });
}

function currentStatusFilter() {
    const url = new URL(window.location.href);
    return url.searchParams.get('status') ?? 'all';
}

// ─────────────────────────────────────────────────────────────────────────────
// Date filter
// ─────────────────────────────────────────────────────────────────────────────

function initDateFilter() {
    const applyBtn = document.getElementById('apply-date-filter');
    const clearBtn = document.getElementById('clear-date-filter');
    if (!applyBtn) return;

    applyBtn.addEventListener('click', () => {
        if (ordersTable) ordersTable.ajax.reload();
    });

    clearBtn?.addEventListener('click', () => {
        const fromEl = document.getElementById('filter-date-from');
        const toEl = document.getElementById('filter-date-to');
        if (fromEl) fromEl.value = '';
        if (toEl) toEl.value = '';
        if (ordersTable) ordersTable.ajax.reload();
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Order detail actions  (show page)
// ─────────────────────────────────────────────────────────────────────────────

const ORDER_DETAIL = window.ORDER_DETAIL;

// ── Confirm ──────────────────────────────────────────────────────────────────
function initConfirmAction() {
    const btn = document.getElementById('btn-confirm');
    if (!btn || !ORDER_DETAIL) return;

    btn.addEventListener('click', async () => {
        const ok = window.Swal
            ? await Swal.fire({
                title: 'تأكيد الطلب؟',
                text: 'هل تريد تأكيد هذا الطلب؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، تأكيد',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#2563eb',
            }).then(r => r.isConfirmed)
            : confirm(window.PARTNER_TRANSLATIONS?.confirmOrderConfirm ?? 'Confirm this order?');

        if (!ok) return;

        btn.disabled = true;
        btn.textContent = 'جاري التأكيد...';

        const { ok: success, data } = await postJson(ORDER_DETAIL.confirmUrl, {});
        if (success) {
            toast(data.message ?? 'تم تأكيد الطلب بنجاح.');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            toast(data.message ?? 'حدث خطأ. يرجى المحاولة مرة أخرى.', 'error');
            btn.disabled = false;
            btn.textContent = 'تأكيد الطلب';
        }
    });
}

// ── Ship modal ────────────────────────────────────────────────────────────────
function initShipModal() {
    const btn = document.getElementById('btn-ship');
    const form = document.getElementById('ship-form');
    const modal = document.getElementById('ship-modal');
    if (!btn || !form || !modal || !ORDER_DETAIL) return;

    btn.addEventListener('click', async () => {
        const estimateBox = document.getElementById('ship-delivery-estimate');
        const noMethodBox = document.getElementById('ship-no-method');
        const labelEl = document.getElementById('ship-delivery-label');

        estimateBox?.classList.add('hidden');
        noMethodBox?.classList.add('hidden');

        showModal('ship-modal');

        try {
            const res = await fetch(ORDER_DETAIL.shipPreviewUrl);
            const data = await res.json();

            if (data.has_estimate) {
                if (labelEl) labelEl.textContent = data.label;
                estimateBox?.classList.remove('hidden');
            } else {
                noMethodBox?.classList.remove('hidden');
            }
        } catch {
            // Silently skip if preview fails — ship still works
        }
    });

    document.getElementById('ship-modal-close')?.addEventListener('click', () => hideModal('ship-modal'));
    document.getElementById('ship-cancel-btn')?.addEventListener('click', () => hideModal('ship-modal'));

    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideModal('ship-modal');
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('ship-error');

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const submitBtn = form.querySelector('[type=submit]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'جاري الإرسال...';

        const { ok: success, data } = await postJson(ORDER_DETAIL.shipUrl, payload);
        if (success) {
            hideModal('ship-modal');
            toast(data.message ?? 'تم تأكيد الشحن بنجاح.');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showError('ship-error', data.message ?? 'حدث خطأ. يرجى التحقق من البيانات والمحاولة مرة أخرى.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'تأكيد الشحن';
        }
    });
}

// ── Cancel modal ──────────────────────────────────────────────────────────────
function initCancelModal() {
    const btn = document.getElementById('btn-cancel');
    const form = document.getElementById('cancel-form');
    const modal = document.getElementById('cancel-modal');
    if (!btn || !form || !modal || !ORDER_DETAIL) return;

    btn.addEventListener('click', () => showModal('cancel-modal'));

    document.getElementById('cancel-modal-close')?.addEventListener('click', () => hideModal('cancel-modal'));
    document.getElementById('cancel-cancel-btn')?.addEventListener('click', () => hideModal('cancel-modal'));

    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideModal('cancel-modal');
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('cancel-error');

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const submitBtn = form.querySelector('[type=submit]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'جاري الإلغاء...';

        const { ok: success, data } = await postJson(ORDER_DETAIL.cancelUrl, payload);
        if (success) {
            hideModal('cancel-modal');
            toast(data.message ?? 'تم إلغاء الطلب بنجاح.');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showError('cancel-error', data.message ?? 'حدث خطأ. يرجى التحقق من البيانات والمحاولة مرة أخرى.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'تأكيد الإلغاء';
        }
    });
}

// ── Out for Delivery ──────────────────────────────────────────────────────────
function initOutForDeliveryAction() {
    const btn = document.getElementById('btn-out-for-delivery');
    if (!btn || !ORDER_DETAIL) return;

    btn.addEventListener('click', async () => {
        const ok = window.Swal
            ? await Swal.fire({
                title: 'تحديث الحالة؟',
                text: 'هل تريد تحديث الحالة إلى "في التوصيل"؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، تحديث',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#2563eb',
            }).then(r => r.isConfirmed)
            : confirm(window.PARTNER_TRANSLATIONS?.confirmOutForDelivery ?? "Update status to Out for Delivery?");

        if (!ok) return;

        btn.disabled = true;
        btn.textContent = 'جاري التحديث...';

        const { ok: success, data } = await postJson(ORDER_DETAIL.outForDeliveryUrl, {});
        if (success) {
            toast(data.message ?? 'تم التحديث بنجاح.');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            toast(data.message ?? 'حدث خطأ. يرجى المحاولة مرة أخرى.', 'error');
            btn.disabled = false;
            btn.textContent = 'تحديث: في التوصيل';
        }
    });
}

// ── Deliver ───────────────────────────────────────────────────────────────────
function initDeliverAction() {
    const btn = document.getElementById('btn-deliver');
    if (!btn || !ORDER_DETAIL) return;

    btn.addEventListener('click', async () => {
        const ok = window.Swal
            ? await Swal.fire({
                title: 'تأكيد التسليم؟',
                text: 'هل تريد تأكيد تسليم هذا الطلب للعميل؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، تأكيد التسليم',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#15803d',
            }).then(r => r.isConfirmed)
            : confirm(window.PARTNER_TRANSLATIONS?.confirmDeliveryConfirm ?? 'Confirm delivery to customer?');

        if (!ok) return;

        btn.disabled = true;
        btn.textContent = 'جاري التأكيد...';

        const { ok: success, data } = await postJson(ORDER_DETAIL.deliverUrl, {});
        if (success) {
            toast(data.message ?? 'تم تأكيد التسليم بنجاح.');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            toast(data.message ?? 'حدث خطأ. يرجى المحاولة مرة أخرى.', 'error');
            btn.disabled = false;
            btn.textContent = 'تأكيد التسليم';
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initOrdersDataTable();
    initDateFilter();
    initConfirmAction();
    initShipModal();
    initOutForDeliveryAction();
    initDeliverAction();
    initCancelModal();
});
