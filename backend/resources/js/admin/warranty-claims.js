import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

// ─── Locale helpers ────────────────────────────────────────────────────────────

const isAr = document.documentElement.lang === 'ar';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function sendJson(url, method = 'POST', data = {}) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(data),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw json;
    return json;
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─── Status color map ─────────────────────────────────────────────────────────

const statusClass = {
    submitted: 'bg-blue-100 text-blue-700',
    under_review: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    resolved: 'bg-gray-100 text-gray-500',
};

// ─── Warranty Claims DataTable (index) ─────────────────────────────────────────

function initWarrantyClaimsTable() {
    const tableEl = document.getElementById('warranty-claims-table');
    if (!tableEl) return;

    let activeStatus = '';

    const dt = new DataTable('#warranty-claims-table', {
        processing: true,
        serverSide: true,
        language: dtLanguage,
        order: [[6, 'desc']],
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.search.value = document.getElementById('search-input')?.value ?? '';
                d.listing_type = document.getElementById('filter-listing-type')?.value ?? '';
                d.date_from = document.getElementById('filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('filter-date-to')?.value ?? '';
                if (activeStatus) {
                    d.status = activeStatus;
                }
            },
        },
        columns: [
            { data: 'claim_number' },
            { data: 'customer', orderable: false },
            { data: 'product', orderable: false },
            { data: 'vendor', orderable: false },
            { data: 'listing_type', orderable: false },
            { data: 'status' },
            { data: 'created_at' },
            { data: 'actions', orderable: false },
        ],
        pageLength: 25,
    });

    // Status tabs
    document.querySelectorAll('.status-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.status-tab').forEach(b => {
                b.classList.remove('border-primary-600', 'text-primary-600');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            btn.classList.remove('border-transparent', 'text-gray-500');
            btn.classList.add('border-primary-600', 'text-primary-600');

            activeStatus = btn.dataset.statusFilter;
            dt.draw();
        });
    });

    ['filter-listing-type', 'filter-date-from', 'filter-date-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.draw());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.draw(), 350);
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-listing-type', 'filter-date-from', 'filter-date-to'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const s = document.getElementById('search-input');
        if (s) s.value = '';
        activeStatus = '';
        document.querySelectorAll('.status-tab').forEach((b, i) => {
            if (i === 0) {
                b.classList.add('border-primary-600', 'text-primary-600');
                b.classList.remove('border-transparent', 'text-gray-500');
            } else {
                b.classList.remove('border-primary-600', 'text-primary-600');
                b.classList.add('border-transparent', 'text-gray-500');
            }
        });
        dt.draw();
    });

    return dt;
}

// ─── Warranty Claim Show Page ──────────────────────────────────────────────────

function initWarrantyClaimShowPage() {
    const replyForm = document.getElementById('reply-form');
    if (!replyForm) return;

    const thread = document.getElementById('message-thread');
    const internalChk = document.getElementById('is-internal-note');
    const replyBg = document.getElementById('reply-form-bg');

    if (thread) {
        thread.scrollIntoView({ block: 'end', behavior: 'smooth' });
    }

    internalChk?.addEventListener('change', () => {
        if (replyBg) {
            replyBg.classList.toggle('border-yellow-300', internalChk.checked);
            replyBg.classList.toggle('bg-yellow-50', internalChk.checked);
        }
    });

    // Reply submit
    replyForm.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('btn-reply');
        if (btn) btn.disabled = true;

        try {
            const message = document.getElementById('reply-message').value;
            const internal = internalChk?.checked ? 1 : 0;

            const res = await sendJson(replyForm.dataset.url, 'POST', {
                message,
                is_internal_note: internal,
            });

            window.Toast?.success(res.message ?? t('admin.warranty_claims.reply_sent'));
            appendMessage(message, internal, res.sender_name ?? t('admin.warranty_claims.support_agent_label'));
            document.getElementById('reply-message').value = '';
            if (internalChk) internalChk.checked = false;
            if (replyBg) replyBg.classList.remove('border-yellow-300', 'bg-yellow-50');

            if (res.status) updateStatusBadge(res.status);
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.warranty_claims.failed_send_reply'));
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    // Status select
    document.getElementById('status-select')?.addEventListener('change', async function () {
        try {
            const res = await sendJson(this.dataset.url, 'POST', { status: this.value });
            window.Toast?.success(res.message ?? t('admin.warranty_claims.status_updated'));
            updateStatusBadge(this.value);
            if (this.value === 'approved' || this.value === 'resolved') {
                setTimeout(() => location.reload(), 600);
            }
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.warranty_claims.failed_update_status'));
        }
    });

    // Resolve form
    const resolveForm = document.getElementById('resolve-form');
    resolveForm?.addEventListener('submit', async e => {
        e.preventDefault();
        const confirmed = window.confirmDialog
            ? await window.confirmDialog({
                title: t('admin.warranty_claims.resolve_claim_title'),
                text: t('admin.warranty_claims.resolve_claim_text'),
                confirmButtonText: t('admin.warranty_claims.resolve_confirm_button'),
            })
            : confirm(t('admin.warranty_claims.resolve_claim_title'));
        if (!confirmed) return;

        const btn = document.getElementById('btn-resolve');
        if (btn) btn.disabled = true;

        try {
            const fd = new FormData(resolveForm);
            const payload = {
                resolution: fd.get('resolution'),
                resolution_notes: fd.get('resolution_notes') ?? '',
            };
            const res = await sendJson(resolveForm.dataset.url, 'POST', payload);
            window.Toast?.success(res.message ?? t('admin.warranty_claims.claim_resolved'));
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            window.Toast?.error(err.message ?? t('admin.warranty_claims.failed_resolve_claim'));
            if (btn) btn.disabled = false;
        }
    });
}

function appendMessage(text, isInternal, senderName) {
    const thread = document.getElementById('message-thread');
    if (!thread) return;

    const bgClass = isInternal
        ? 'bg-yellow-50 border border-yellow-200'
        : 'bg-blue-50 border border-blue-200';

    const now = new Date().toLocaleString(isAr ? 'ar-EG' : 'en-US', {
        month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });

    const el = document.createElement('div');
    el.className = 'message-bubble ml-8';
    el.innerHTML = `<div class="rounded-xl p-4 ${bgClass}">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-xs font-semibold text-gray-700">${escapeHtml(senderName)}</span>
            ${isInternal ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-200 text-yellow-800">${escapeHtml(t('admin.warranty_claims.internal_note_label'))}</span>` : ''}
            <span class="text-xs text-gray-400 ml-auto">${now}</span>
        </div>
        <div class="text-sm text-gray-800 whitespace-pre-wrap">${escapeHtml(text)}</div>
    </div>`;

    thread.appendChild(el);
    el.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

function updateStatusBadge(status) {
    const badge = document.querySelector('.js-status-badge');
    if (!badge) return;
    badge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium js-status-badge';
    const cls = statusClass[status] ?? 'bg-gray-100 text-gray-500';
    badge.classList.add(...cls.split(' '));
    badge.textContent = t(`admin.warranty_claims.status_labels.${status}`) || status.replace(/_/g, ' ');

    const sel = document.getElementById('status-select');
    if (sel) sel.value = status;
}

// ─── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initWarrantyClaimsTable();
    initWarrantyClaimShowPage();
});
