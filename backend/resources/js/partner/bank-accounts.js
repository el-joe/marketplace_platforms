/**
 * partner/bank-accounts.js
 * Bank account management: add, set primary, delete
 */

import './app.js';
import { csrfToken, showModal, hideModal, showError, hideError, toast } from './datatable.js';

const cfg = () => window.BANK_ACCOUNTS ?? {};

async function sendRequest(method, url, body = null) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(url, opts);
    const data = await res.json();
    return { ok: res.ok, data };
}

// ─────────────────────────────────────────────────────────────────────────────
// Render a bank account card
// ─────────────────────────────────────────────────────────────────────────────

function maskIban(iban) {
    const clean = iban.replace(/\s+/g, '');
    if (clean.length <= 4) return clean;
    return '*'.repeat(clean.length - 4) + clean.slice(-4);
}

function verifyBadge(status) {
    const map = {
        pending: ['bg-yellow-100 text-yellow-700', 'قيد المراجعة'],
        verified: ['bg-green-100 text-green-700', 'معتمد'],
        rejected: ['bg-red-100 text-red-700', 'مرفوض'],
    };
    const [cls, label] = map[status] ?? ['bg-gray-100 text-gray-500', status];
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${label}</span>`;
}

function buildCard(acc) {
    const primaryBorder = acc.is_primary ? 'border-yellow-400' : 'border-gray-200';
    const primaryBadge = acc.is_primary
        ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">رئيسي ★</span>`
        : '';
    const setPrimaryBtn = (!acc.is_primary && acc.verification_status === 'verified')
        ? `<button type="button" class="btn-set-primary flex-1 text-xs bg-yellow-50 hover:bg-yellow-100 text-yellow-700 font-medium py-2 rounded-lg transition-colors" data-id="${acc.id}">تعيين كرئيسي</button>`
        : acc.is_primary
            ? `<span class="flex-1 text-xs text-center text-gray-400 py-2">الحساب الرئيسي الحالي</span>`
            : `<span class="flex-1 text-xs text-center text-gray-300 py-2">—</span>`;

    return `<div class="bank-account-card bg-white rounded-2xl border ${primaryBorder} p-5"
                 data-id="${acc.id}" data-status="${acc.verification_status}" data-primary="${acc.is_primary ? '1' : '0'}">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l9-3 9 3M3 6v14l9 3 9-3V6M3 6l9 3 9-3"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-900 text-sm">${acc.bank_name}</p>
                    ${acc.branch ? `<p class="text-xs text-gray-400">${acc.branch}</p>` : ''}
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                ${primaryBadge}
                ${verifyBadge(acc.verification_status)}
            </div>
        </div>
        <div class="space-y-1.5 text-sm mb-4">
            <div class="flex justify-between">
                <span class="text-gray-500">اسم صاحب الحساب</span>
                <span class="font-medium text-gray-800">${acc.account_holder_name}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">IBAN</span>
                <span class="font-mono text-xs text-gray-700 tracking-widest">${maskIban(acc.iban_masked ?? acc.iban ?? '')}</span>
            </div>
            ${acc.swift_code ? `<div class="flex justify-between">
                <span class="text-gray-500">SWIFT</span>
                <span class="font-mono text-xs text-gray-700">${acc.swift_code}</span>
            </div>` : ''}
            <div class="flex justify-between">
                <span class="text-gray-500">العملة</span>
                <span class="font-medium text-gray-800">${acc.currency}</span>
            </div>
        </div>
        <div class="flex items-center gap-2 pt-3 border-t border-gray-50">
            ${setPrimaryBtn}
            <button type="button" class="btn-delete-account text-xs bg-red-50 hover:bg-red-100 text-red-600 font-medium px-3 py-2 rounded-lg transition-colors" data-id="${acc.id}">حذف</button>
        </div>
    </div>`;
}

function refreshGrid(accounts) {
    const grid = document.getElementById('accounts-grid');
    const empty = document.getElementById('empty-state');
    const container = document.getElementById('accounts-container');

    if (!accounts.length) {
        if (grid) grid.remove();
        if (empty) { empty.classList.remove('hidden'); }
        return;
    }

    if (empty) empty.classList.add('hidden');

    if (!grid) {
        const newGrid = document.createElement('div');
        newGrid.id = 'accounts-grid';
        newGrid.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
        container?.appendChild(newGrid);
    }

    const g = document.getElementById('accounts-grid');
    if (g) g.innerHTML = accounts.map(buildCard).join('');
}

// ─────────────────────────────────────────────────────────────────────────────
// Add account form
// ─────────────────────────────────────────────────────────────────────────────

function initAddModal() {
    const openModal = () => {
        document.getElementById('add-account-form')?.reset();
        hideError('account-form-error');
        showModal('add-account-modal');
    };

    document.getElementById('btn-add-account')?.addEventListener('click', openModal);
    document.getElementById('btn-add-account-empty')?.addEventListener('click', openModal);
    document.getElementById('modal-close-btn')?.addEventListener('click', () => hideModal('add-account-modal'));
    document.getElementById('modal-cancel-btn')?.addEventListener('click', () => hideModal('add-account-modal'));

    document.getElementById('add-account-modal')?.addEventListener('click', (e) => {
        if (e.target === document.getElementById('add-account-modal')) hideModal('add-account-modal');
    });

    document.getElementById('add-account-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('account-form-error');
        const submitBtn = document.getElementById('account-submit-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'جاري الإضافة...';

        const formData = new FormData(e.target);
        const payload = Object.fromEntries(formData.entries());

        const { ok, data } = await sendRequest('POST', cfg().storeUrl, payload);
        if (ok && data.success) {
            hideModal('add-account-modal');
            toast(data.message ?? 'تم إضافة الحساب.');
            if (data.pending_review) {
                // No account was created yet — it's pending admin review. Just refresh
                // the page so the "pending request" notice picks it up.
                setTimeout(() => window.location.reload(), 1200);
                return;
            }
            // Re-render grid with new account appended
            const grid = document.getElementById('accounts-grid');
            if (grid) {
                const div = document.createElement('div');
                div.innerHTML = buildCard(data.account);
                grid.appendChild(div.firstElementChild);
                // hide empty state if shown
                document.getElementById('empty-state')?.classList.add('hidden');
            } else {
                // First account — rebuild grid
                const empty = document.getElementById('empty-state');
                if (empty) {
                    const newGrid = document.createElement('div');
                    newGrid.id = 'accounts-grid';
                    newGrid.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
                    newGrid.innerHTML = buildCard(data.account);
                    empty.classList.add('hidden');
                    document.getElementById('accounts-container')?.appendChild(newGrid);
                }
            }
        } else {
            showError('account-form-error', data.message ?? 'حدث خطأ. يرجى التحقق من البيانات.');
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'إضافة الحساب';
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Set Primary (delegated)
// ─────────────────────────────────────────────────────────────────────────────

function initSetPrimary() {
    document.getElementById('accounts-container')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-set-primary');
        if (!btn) return;

        const id = btn.dataset.id;
        const url = `${cfg().setPrimaryUrl}/${id}/set-primary`;
        btn.disabled = true;

        const { ok, data } = await sendRequest('POST', url);
        if (ok && data.success) {
            toast(data.message ?? 'تم تعيين الحساب الرئيسي.');
            // Refresh page to reflect all card border changes
            window.location.reload();
        } else {
            toast(data.message ?? 'حدث خطأ.', 'error');
            btn.disabled = false;
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Delete (delegated)
// ─────────────────────────────────────────────────────────────────────────────

function initDelete() {
    document.getElementById('accounts-container')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-delete-account');
        if (!btn) return;

        const confirmed = window.confirmDelete
            ? await window.confirmDelete('هل تريد حذف هذا الحساب البنكي؟')
            : window.confirm(window.PARTNER_TRANSLATIONS?.confirmDeleteBankAccount ?? 'Delete this bank account?');

        if (!confirmed) return;

        const id = btn.dataset.id;
        const url = `${cfg().deleteUrl}/${id}`;
        btn.disabled = true;

        const { ok, data } = await sendRequest('DELETE', url);
        if (ok && data.success) {
            toast(data.message ?? 'تم حذف الحساب.');
            const card = btn.closest('.bank-account-card');
            card?.remove();
            // Check if grid is now empty
            const grid = document.getElementById('accounts-grid');
            if (grid && !grid.children.length) {
                grid.remove();
                const empty = document.getElementById('empty-state');
                if (empty) empty.classList.remove('hidden');
            }
        } else {
            toast(data.message ?? 'لا يمكن حذف هذا الحساب.', 'error');
            btn.disabled = false;
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initAddModal();
    initSetPrimary();
    initDelete();
});
