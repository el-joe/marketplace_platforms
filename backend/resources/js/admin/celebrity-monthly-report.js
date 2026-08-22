import DataTable from 'datatables.net';
import { dtLanguage } from '../components/datatable.js';

function currentPeriod(tableEl) {
    const picker = document.getElementById('month-picker');
    const raw = picker?.value ?? '';
    const match = /^(\d{4})-(\d{2})/.exec(raw);
    if (match) {
        return { year: parseInt(match[1], 10), month: parseInt(match[2], 10) };
    }
    return { year: parseInt(tableEl.dataset.year, 10), month: parseInt(tableEl.dataset.month, 10) };
}

function updateExportLink(tableEl) {
    const link = document.getElementById('btn-export-csv');
    if (!link) return;

    const { year, month } = currentPeriod(tableEl);
    const url = new URL(tableEl.dataset.exportUrl, window.location.origin);
    url.searchParams.set('year', year);
    url.searchParams.set('month', month);
    link.href = url.toString();
}

function initReportTable() {
    const tableEl = document.getElementById('celebrity-monthly-report-table');
    if (!tableEl) return;

    const columns = [
        { data: 'marketer' },
        { data: 'tier' },
        { data: 'period' },
        { data: 'completed' },
        { data: 'minimum' },
        { data: 'progress', orderable: false },
        { data: 'status', orderable: false },
        { data: 'penalty' },
    ];

    const dt = new DataTable('#celebrity-monthly-report-table', {
        processing: true,
        serverSide: true,
        language: dtLanguage,
        ajax: {
            url: tableEl.dataset.url,
            type: 'POST',
            data(d) {
                const { year, month } = currentPeriod(tableEl);
                d.year = year;
                d.month = month;
                d.tier = document.getElementById('filter-tier')?.value ?? '';
                d.status = document.getElementById('filter-status')?.value ?? '';
            },
        },
        columns,
        pageLength: 25,
    });

    updateExportLink(tableEl);

    document.getElementById('month-picker')?.addEventListener('change', () => {
        updateExportLink(tableEl);
        dt.draw();
    });
    document.getElementById('filter-tier')?.addEventListener('change', () => dt.draw());
    document.getElementById('filter-status')?.addEventListener('change', () => dt.draw());
}

document.addEventListener('DOMContentLoaded', () => {
    initReportTable();
});
