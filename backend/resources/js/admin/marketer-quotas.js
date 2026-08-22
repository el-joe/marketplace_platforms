import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function req(url, method, body) {
    const res = await fetch(url, {
        method,
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, data };
}

function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

function initQuotasTable() {
    const tableEl = document.getElementById('marketer-quotas-table');
    if (!tableEl) return;

    const base = tableEl.dataset.url.replace(/\/datatable$/, '');

    const dt = new DataTable('#marketer-quotas-table', {
        processing: true,
        serverSide: true,
        language: dtLanguage,
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.marketer_id = document.getElementById('filter-marketer_id')?.value ?? '';
                d.promotion_category = document.getElementById('filter-promotion_category')?.value ?? '';
                d.is_active = document.getElementById('filter-is_active')?.value ?? '';
            },
        },
        columns: [
            { data: 'marketer' },
            { data: 'category' },
            { data: 'min_per_month' },
            { data: 'penalty' },
            { data: 'active', orderable: false },
            { data: 'actions', orderable: false },
        ],
        pageLength: 25,
    });

    ['filter-marketer_id', 'filter-promotion_category', 'filter-is_active'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.draw());
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-marketer_id', 'filter-promotion_category', 'filter-is_active'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        dt.draw();
    });

    document.querySelectorAll('[data-modal-close]').forEach(btn =>
        btn.addEventListener('click', () => btn.closest('[id]')?.classList.add('hidden'))
    );

    function resetForm() {
        document.getElementById('form-quota-id').value = '';
        document.getElementById('f-marketer-id').value = '';
        document.getElementById('f-promotion-category').selectedIndex = 0;
        document.getElementById('f-min-per-month').value = '';
        document.getElementById('f-penalty').value = '0';
        document.getElementById('f-penalty-currency').value = '';
        document.getElementById('f-is-active').checked = true;
        document.getElementById('form-error').classList.add('hidden');
    }

    document.getElementById('btn-new-quota')?.addEventListener('click', () => {
        resetForm();
        openModal('quota-modal');
    });

    tableEl.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.btn-edit-quota');
        if (editBtn) {
            const quota = JSON.parse(editBtn.dataset.quota);
            document.getElementById('form-quota-id').value = quota.id;
            document.getElementById('f-marketer-id').value = quota.marketer_id ?? '';
            document.getElementById('f-promotion-category').value = quota.promotion_category;
            document.getElementById('f-min-per-month').value = quota.min_promotions_per_month;
            document.getElementById('f-penalty').value = quota.penalty_per_missing ?? 0;
            document.getElementById('f-penalty-currency').value = quota.penalty_currency ?? '';
            document.getElementById('f-is-active').checked = !!quota.is_active;
            document.getElementById('form-error').classList.add('hidden');
            openModal('quota-modal');
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-quota');
        if (deleteBtn) {
            document.getElementById('delete-quota-id').value = deleteBtn.dataset.id;
            document.getElementById('delete-quota-error').classList.add('hidden');
            openModal('delete-quota-modal');
            return;
        }

        const toggleBtn = e.target.closest('.btn-toggle-active');
        if (toggleBtn) {
            req(`${base}/${toggleBtn.dataset.id}/toggle`, 'POST').then(({ ok, data }) => {
                if (ok) {
                    const active = data.is_active;
                    toggleBtn.classList.toggle('bg-emerald-500', active);
                    toggleBtn.classList.toggle('bg-gray-200', !active);
                    const dot = toggleBtn.querySelector('span');
                    dot.classList.toggle('translate-x-4', active);
                    dot.classList.toggle('translate-x-0.5', !active);
                }
            });
        }
    });

    document.getElementById('btn-save-quota')?.addEventListener('click', async () => {
        const id = document.getElementById('form-quota-id').value;
        const payload = {
            marketer_id: document.getElementById('f-marketer-id').value || null,
            promotion_category: parseInt(document.getElementById('f-promotion-category').value, 10),
            min_promotions_per_month: parseInt(document.getElementById('f-min-per-month').value, 10) || 0,
            penalty_per_missing: parseInt(document.getElementById('f-penalty').value, 10) || 0,
            penalty_currency: document.getElementById('f-penalty-currency').value.toUpperCase() || null,
            is_active: document.getElementById('f-is-active').checked ? 1 : 0,
        };

        const url = id ? `${base}/${id}` : base;
        const method = id ? 'PUT' : 'POST';
        const errEl = document.getElementById('form-error');

        const { ok, data } = await req(url, method, payload);
        if (ok) {
            closeModal('quota-modal');
            dt.draw(false);
            window.Toast?.success(data.message);
        } else {
            errEl.textContent = data.message ?? Object.values(data.errors ?? {}).flat().join(' ');
            errEl.classList.remove('hidden');
        }
    });

    document.getElementById('btn-confirm-delete-quota')?.addEventListener('click', async () => {
        const id = document.getElementById('delete-quota-id').value;
        const { ok, data } = await req(`${base}/${id}`, 'DELETE');
        if (ok) {
            closeModal('delete-quota-modal');
            dt.draw(false);
            window.Toast?.success(data.message);
        } else {
            const errEl = document.getElementById('delete-quota-error');
            errEl.textContent = data.message ?? 'Could not delete.';
            errEl.classList.remove('hidden');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initQuotasTable();
});
