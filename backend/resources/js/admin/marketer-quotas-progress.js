import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function currentPeriod(tableEl) {
    const picker = document.getElementById('month-picker');
    const raw = picker?.value ?? '';
    const match = /^(\d{4})-(\d{2})/.exec(raw);
    if (match) {
        return { year: parseInt(match[1], 10), month: parseInt(match[2], 10) };
    }
    return { year: parseInt(tableEl.dataset.year, 10), month: parseInt(tableEl.dataset.month, 10) };
}

function initProgressTable() {
    const tableEl = document.getElementById('marketer-quotas-progress-table');
    if (!tableEl) return;

    const columns = [
        { data: 'marketer' },
        { data: 'cat_1', orderable: false },
        { data: 'cat_2', orderable: false },
        { data: 'cat_3', orderable: false },
        { data: 'cat_4', orderable: false },
        { data: 'status', orderable: false },
    ];

    const dt = new DataTable('#marketer-quotas-progress-table', {
        processing: true,
        serverSide: true,
        language: dtLanguage,
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                const { year, month } = currentPeriod(tableEl);
                d.year = year;
                d.month = month;
            },
        },
        columns,
        pageLength: 25,
    });

    document.getElementById('month-picker')?.addEventListener('change', () => dt.draw());

    document.getElementById('btn-send-warnings')?.addEventListener('click', async () => {
        const { year, month } = currentPeriod(tableEl);
        if (!confirm(t('admin.marketer_quotas_progress.send_warnings_confirm'))) return;

        const res = await fetch(tableEl.dataset.warningsUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ year, month }),
        });
        const data = await res.json().catch(() => ({}));

        if (res.ok) {
            window.Toast?.success(data.message);
            dt.draw(false);
        } else {
            window.Toast?.error(data.message || 'Failed to send warnings.');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initProgressTable();
});
