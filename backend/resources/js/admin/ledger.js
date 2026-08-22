import DataTable from 'datatables.net';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ─── Ledger Index ─────────────────────────────────────────────────────────────

function initLedgerIndex() {
    const tableEl = document.getElementById('ledger-table');
    if (!tableEl) return;

    const dt = new DataTable('#ledger-table', {
        processing: true,
        serverSide: true,
        order: [[0, 'desc']], // created_at desc
        ajax: {
            url: '/ledger/datatable',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.account_type = document.getElementById('ledger-filter-account-type')?.value ?? '';
                d.currency = document.getElementById('ledger-filter-currency')?.value ?? '';
                d.date_from = document.getElementById('ledger-filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('ledger-filter-date-to')?.value ?? '';
                d.transaction_group_id = document.getElementById('ledger-filter-group-id')?.value ?? '';
                d.reference_type = document.getElementById('ledger-filter-reference-type')?.value ?? '';
                d.search = { value: document.getElementById('ledger-search-input')?.value ?? '' };
            },
        },
        columns: [
            { data: 'created_at', orderable: true },
            { data: 'transaction_group_id', orderable: true },
            { data: 'account_type', orderable: true },
            { data: 'account_holder', orderable: false },
            { data: 'debit', orderable: true },
            { data: 'credit', orderable: true },
            { data: 'currency', orderable: false },
            { data: 'reference', orderable: false },
            { data: 'description', orderable: false },
        ],
        rowId: 'DT_RowId',
        pageLength: 50,
        columnDefs: [
            { width: '130px', targets: 0 },
            { width: '100px', targets: 1 },
            { width: '140px', targets: 2 },
        ],
    });

    // ── Filters ────────────────────────────────────────────────────────────────
    ['ledger-filter-account-type', 'ledger-filter-date-from', 'ledger-filter-date-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.ajax.reload());
    });

    let searchTimer;
    document.getElementById('ledger-search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.ajax.reload(), 350);
    });

    let filterTimer;
    ['ledger-filter-currency', 'ledger-filter-group-id', 'ledger-filter-reference-type'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(() => dt.ajax.reload(), 500);
        });
    });

    document.getElementById('ledger-clear-filters')?.addEventListener('click', () => {
        ['ledger-filter-account-type', 'ledger-filter-currency', 'ledger-filter-date-from',
            'ledger-filter-date-to', 'ledger-filter-group-id', 'ledger-filter-reference-type', 'ledger-search-input'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        dt.ajax.reload();
    });

    // ── Transaction Group modal ────────────────────────────────────────────────
    tableEl.addEventListener('click', async e => {
        const btn = e.target.closest('.js-view-group');
        if (!btn) return;

        const groupId = btn.dataset.groupId;
        if (!groupId) return;

        const contentEl = document.getElementById('ledger-group-content');
        if (contentEl) contentEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">Loading…</p>';

        $('#ledger-group-modal').modal('open');

        try {
            const res = await fetch('/admin/ledger/transaction-group/' + encodeURIComponent(groupId), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();

            if (!res.ok) throw data;

            renderGroupModal(data, contentEl);
        } catch (err) {
            if (contentEl) {
                contentEl.innerHTML = '<p class="text-sm text-red-600 text-center py-4">' + (err.message ?? t('admin.ledger.failed_load_group')) + '</p>';
            }
        }
    });
}

function renderGroupModal(data, container) {
    const balancedClass = data.balanced
        ? 'bg-green-50 border-green-200 text-green-800'
        : 'bg-red-50 border-red-200 text-red-800';

    const balancedText = data.balanced
        ? '✓ Balanced — Σ Debit = Σ Credit'
        : `⚠ Imbalance — Difference: ${data.difference_f}`;

    let rows = data.entries.map(e => {
        const debitCell = e.debit > 0 ? `<span class="text-green-600 font-semibold tabular-nums">${e.debit_f}</span>` : '<span class="text-gray-300">—</span>';
        const creditCell = e.credit > 0 ? `<span class="text-blue-600 font-semibold tabular-nums">${e.credit_f}</span>` : '<span class="text-gray-300">—</span>';
        const holder = e.account_holder_id
            ? `<span class="font-mono text-xs text-gray-600">${escHtml(e.account_holder_type ?? '')} #${e.account_holder_id.substring(0, 8)}</span>`
            : '<span class="text-gray-300">—</span>';

        return `<tr class="border-b border-gray-50 text-sm">
            <td class="py-2 pr-3 text-xs font-mono text-gray-600">${escHtml(e.created_at)}</td>
            <td class="py-2 pr-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                    ${escHtml(e.account_type.replace(/_/g, ' '))}
                </span>
            </td>
            <td class="py-2 pr-3">${holder}</td>
            <td class="py-2 pr-3 text-right">${debitCell}</td>
            <td class="py-2 pr-3 text-right">${creditCell}</td>
            <td class="py-2 pr-3 text-xs font-mono text-gray-600">${escHtml(e.currency)}</td>
            <td class="py-2 text-xs text-gray-500 max-w-xs truncate" title="${escHtml(e.description ?? '')}">${escHtml((e.description ?? '').substring(0, 60))}</td>
        </tr>`;
    }).join('');

    container.innerHTML = `
        <div class="space-y-4">
            <div class="rounded border px-4 py-2.5 text-sm font-medium ${balancedClass}">${balancedText}</div>

            <div class="flex gap-6 text-sm">
                <div>
                    <span class="text-xs font-medium text-gray-400 uppercase">Group ID</span>
                    <p class="font-mono text-xs text-gray-700 mt-0.5">${escHtml(data.group_id)}</p>
                </div>
                <div>
                    <span class="text-xs font-medium text-gray-400 uppercase">Total Debit</span>
                    <p class="text-green-600 font-semibold mt-0.5">${escHtml(data.total_debit_f)}</p>
                </div>
                <div>
                    <span class="text-xs font-medium text-gray-400 uppercase">Total Credit</span>
                    <p class="text-blue-600 font-semibold mt-0.5">${escHtml(data.total_credit_f)}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-xs text-gray-500 uppercase">
                            <th class="pb-2 pr-3 text-left font-medium">Created</th>
                            <th class="pb-2 pr-3 text-left font-medium">Account Type</th>
                            <th class="pb-2 pr-3 text-left font-medium">Holder</th>
                            <th class="pb-2 pr-3 text-right font-medium text-green-600">Debit</th>
                            <th class="pb-2 pr-3 text-right font-medium text-blue-600">Credit</th>
                            <th class="pb-2 pr-3 text-left font-medium">Currency</th>
                            <th class="pb-2 text-left font-medium">Description</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    `;
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initLedgerIndex();
});
