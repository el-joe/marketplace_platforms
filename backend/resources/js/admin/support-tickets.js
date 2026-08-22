import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

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

// ─── Status/Priority badge color maps ─────────────────────────────────────────

const statusClass = {
    open: 'bg-yellow-100 text-yellow-700',
    in_progress: 'bg-blue-100 text-blue-700',
    waiting_customer: 'bg-indigo-100 text-indigo-700',
    resolved: 'bg-green-100 text-green-700',
    closed: 'bg-gray-100 text-gray-500',
};

const priorityClass = {
    urgent: 'bg-red-100 text-red-700 border border-red-200',
    high: 'bg-orange-100 text-orange-700',
    normal: 'bg-blue-100 text-blue-700',
    low: 'bg-gray-100 text-gray-500',
};

// ─── Support Tickets DataTable (index page) ────────────────────────────────────

function initSupportTicketsTable() {
    const tableEl = document.getElementById('support-tickets-table');
    if (!tableEl) return;

    let activeStatus = '';
    let activeUnassigned = false;

    const dt = new DataTable('#support-tickets-table', {
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
                d.category = document.getElementById('filter-category')?.value ?? '';
                d.priority = document.getElementById('filter-priority')?.value ?? '';
                d.requester_role = document.getElementById('filter-requester-role')?.value ?? '';
                d.assigned_to_admin_id = document.getElementById('filter-assigned')?.value ?? '';
                d.date_from = document.getElementById('filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('filter-date-to')?.value ?? '';
                d.unassigned = activeUnassigned ? '1' : '';
                // Status tab: '__unassigned' is handled above, others as status filter
                if (activeStatus && activeStatus !== '__unassigned') {
                    d.status = activeStatus;
                } else if (!activeStatus) {
                    d.status = document.getElementById('filter-status')?.value ?? '';
                }
            },
        },
        columns: [
            { data: 'ticket_number' },
            { data: 'requester', orderable: false },
            { data: 'category' },
            { data: 'priority' },
            { data: 'status' },
            { data: 'subject', orderable: false },
            { data: 'assigned_to', orderable: false },
            { data: 'response_time', orderable: false },
            { data: 'created_at' },
            { data: 'actions', orderable: false },
        ],
        createdRow(row, data) {
            if (data.DT_RowClass) {
                row.classList.add(...data.DT_RowClass.split(' ').filter(Boolean));
            }
        },
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

            const val = btn.dataset.statusFilter;
            activeUnassigned = val === '__unassigned';
            activeStatus = activeUnassigned ? '' : val;
            dt.draw();
        });
    });

    // Other filter bindings
    ['filter-category', 'filter-priority', 'filter-requester-role', 'filter-assigned',
        'filter-date-from', 'filter-date-to'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => dt.draw());
        });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.draw(), 350);
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        ['filter-category', 'filter-priority', 'filter-requester-role',
            'filter-assigned', 'filter-date-from', 'filter-date-to'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        const s = document.getElementById('search-input');
        if (s) s.value = '';
        activeStatus = '';
        activeUnassigned = false;
        // Reset tabs
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

// ─── Support Ticket Show Page ─────────────────────────────────────────────────

function initTicketShowPage() {
    const replyForm = document.getElementById('reply-form');
    if (!replyForm) return;

    const thread = document.getElementById('message-thread');
    const internalChk = document.getElementById('is-internal-note');
    const replyBg = document.getElementById('reply-form-bg');

    // Auto-scroll to bottom of thread
    if (thread) {
        thread.scrollIntoView({ block: 'end', behavior: 'smooth' });
    }

    // Internal note background toggle
    internalChk?.addEventListener('change', () => {
        if (replyBg) {
            replyBg.classList.toggle('border-yellow-300', internalChk.checked);
            replyBg.classList.toggle('bg-yellow-50', internalChk.checked);
        }
    });

    // Reply form submit
    replyForm.addEventListener('submit', async e => {
        e.preventDefault();
        const submitBtn = e.submitter;
        const closeAfter = submitBtn?.value === '1';
        const btn1 = document.getElementById('btn-reply');
        const btn2 = document.getElementById('btn-reply-close');

        if (btn1) { btn1.disabled = true; }
        if (btn2) { btn2.disabled = true; }

        try {
            const message = document.getElementById('reply-message').value;
            const internal = internalChk?.checked ? 1 : 0;

            const res = await sendJson(replyForm.dataset.url, 'POST', {
                message,
                is_internal_note: internal,
            });

            window.Toast?.success(res.message ?? window.TRANSLATIONS?.replySent);

            // Append message to thread
            appendMessage(message, internal);
            document.getElementById('reply-message').value = '';
            if (internalChk) internalChk.checked = false;
            if (replyBg) {
                replyBg.classList.remove('border-yellow-300', 'bg-yellow-50');
            }

            // Update status badge if changed
            if (res.status) {
                updateStatusBadge(res.status);
            }

            if (closeAfter) {
                await sendJson(
                    document.getElementById('status-select')?.dataset.url ?? '#',
                    'POST',
                    { status: 'closed' }
                ).catch(() => { });
                setTimeout(() => location.reload(), 600);
            }
        } catch (err) {
            window.Toast?.error(err.message ?? window.TRANSLATIONS?.failedSendReply);
        } finally {
            if (btn1) btn1.disabled = false;
            if (btn2) btn2.disabled = false;
        }
    });

    // Status dropdown
    document.getElementById('status-select')?.addEventListener('change', async function () {
        try {
            await sendJson(this.dataset.url, 'POST', { status: this.value });
            window.Toast?.success(window.TRANSLATIONS?.statusUpdated);
            updateStatusBadge(this.value);
        } catch (err) {
            window.Toast?.error(err.message ?? window.TRANSLATIONS?.failedUpdateStatus);
        }
    });

    // Priority dropdown
    document.getElementById('priority-select')?.addEventListener('change', async function () {
        try {
            await sendJson(this.dataset.url, 'POST', { priority: this.value });
            window.Toast?.success(window.TRANSLATIONS?.priorityUpdated);
        } catch (err) {
            window.Toast?.error(err.message ?? window.TRANSLATIONS?.failedUpdatePriority);
        }
    });

    // Assign to me
    document.getElementById('btn-assign-me')?.addEventListener('click', async function () {
        try {
            const res = await sendJson(this.dataset.url, 'POST');
            window.Toast?.success(res.message);
            const nameEl = document.getElementById('assignee-name');
            if (nameEl) nameEl.textContent = res.assignee_name ?? this.dataset.myName;
        } catch (err) {
            window.Toast?.error(err.message ?? window.TRANSLATIONS?.failedAssign);
        }
    });

    // Reassign
    document.getElementById('btn-reassign')?.addEventListener('click', async function () {
        const adminId = document.getElementById('reassign-select')?.value;
        try {
            const res = await sendJson(this.dataset.url, 'POST', { admin_id: adminId || null });
            window.Toast?.success(res.message ?? window.TRANSLATIONS?.reassigned);
            const nameEl = document.getElementById('assignee-name');
            if (nameEl) nameEl.textContent = res.assignee_name ?? window.TRANSLATIONS?.unassigned;
        } catch (err) {
            window.Toast?.error(err.message ?? window.TRANSLATIONS?.failedReassign);
        }
    });

    // Real-time polling: check for new messages every 30s
    let lastMsgId = getLastMessageId();
    setInterval(async () => {
        // Simple reload approach for new messages
        const msgs = document.querySelectorAll('.message-bubble');
        const newLastId = msgs.length > 0 ? msgs[msgs.length - 1]?.dataset.messageId : null;
        if (newLastId && newLastId !== lastMsgId) {
            lastMsgId = newLastId;
        }
        // We can't easily poll without a dedicated endpoint; just refresh silently
        // This is a placeholder — replace with a dedicated /messages endpoint if needed
    }, 30_000);
}

function getLastMessageId() {
    const msgs = document.querySelectorAll('.message-bubble');
    return msgs.length > 0 ? msgs[msgs.length - 1]?.dataset.messageId : null;
}

function appendMessage(text, isInternal) {
    const thread = document.getElementById('message-thread');
    if (!thread) return;

    const bgClass = isInternal
        ? 'bg-yellow-50 border border-yellow-200'
        : 'bg-blue-50 border border-blue-200';

    const now = new Date().toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    const el = document.createElement('div');
    el.className = 'message-bubble ml-8';
    const supportAgentLabel = escapeHtml(window.TRANSLATIONS?.supportAgent ?? '');
    const internalNoteLabel = escapeHtml(window.TRANSLATIONS?.internalNoteBadge ?? '');

    el.innerHTML = `<div class="rounded-xl p-4 ${bgClass}">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-xs font-semibold text-gray-700">${supportAgentLabel}</span>
            ${isInternal ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-200 text-yellow-800">${internalNoteLabel}</span>` : ''}
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
    // Remove all color classes
    badge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium js-status-badge';
    const cls = statusClass[status] ?? 'bg-gray-100 text-gray-500';
    badge.classList.add(...cls.split(' '));
    badge.textContent = window.TRANSLATIONS?.statusLabels?.[status] ?? status.replace(/_/g, ' ');
}

function escapeHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initSupportTicketsTable();
    initTicketShowPage();
});
