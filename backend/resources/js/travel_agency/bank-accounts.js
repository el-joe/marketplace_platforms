/**
 * Travel Agency Portal → Bank Accounts JS
 * Mirrors resources/js/partner/bank-accounts.js for the travel-agency guard.
 */

const cfg = () => window.BANK_ACCOUNTS ?? {};

function toast(message, ok = true) {
    window.Toastify?.({
        text: message,
        backgroundColor: ok ? '#22c55e' : '#ef4444',
        duration: 3500,
        gravity: 'top',
        position: 'center',
    }).showToast();
}

function showModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
}

function hideModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
}

function showError(id, message) {
    const el = document.getElementById(id);
    if (el) { el.textContent = message; el.classList.remove('hidden'); }
}

function hideError(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}

async function sendRequest(method, url, body = null) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': cfg().csrf,
            'Accept': 'application/json',
        },
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(url, opts);
    const data = await res.json();
    return { ok: res.ok, data };
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

        const formData = new FormData(e.target);
        const payload = Object.fromEntries(formData.entries());

        const { ok, data } = await sendRequest('POST', cfg().storeUrl, payload);
        if (ok && data.success) {
            hideModal('add-account-modal');
            toast(data.message ?? t('travel_agency.bank_accounts.submitted'));
            if (data.pending_review) {
                setTimeout(() => window.location.reload(), 1200);
                return;
            }
            window.location.reload();
        } else {
            showError('account-form-error', data.message ?? t('travel_agency.bank_accounts.generic_form_error'));
        }
        submitBtn.disabled = false;
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
            toast(data.message ?? t('travel_agency.bank_accounts.submitted'));
            window.location.reload();
        } else {
            toast(data.message ?? t('shared.something_went_wrong'), false);
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

        if (!window.confirm(t('travel_agency.bank_accounts.delete_confirm'))) return;

        const id = btn.dataset.id;
        const url = `${cfg().deleteUrl}/${id}`;
        btn.disabled = true;

        const { ok, data } = await sendRequest('DELETE', url);
        if (ok && data.success) {
            toast(data.message ?? t('travel_agency.bank_accounts.submitted'));
            window.location.reload();
        } else {
            toast(data.message ?? t('travel_agency.bank_accounts.cannot_delete'), false);
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
