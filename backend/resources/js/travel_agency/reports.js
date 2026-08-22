/**
 * Travel Agency → Reports JS
 * Handles: Flatpickr date-range filter, revenue line chart (per currency),
 * bookings donut/bar charts on the Bookings Report page.
 */
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import {
    Chart,
    LineElement,
    BarElement,
    PointElement,
    LinearScale,
    CategoryScale,
    ArcElement,
    Tooltip,
    Legend,
    LineController,
    BarController,
    DoughnutController,
} from 'chart.js';

Chart.register(
    LineElement, BarElement, PointElement, LinearScale, CategoryScale,
    ArcElement, Tooltip, Legend,
    LineController, BarController, DoughnutController,
);

const PALETTE = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

document.addEventListener('DOMContentLoaded', () => {
    // ── Date range picker ────────────────────────────────────────────────────
    const rangeInput = document.getElementById('report-date-range');
    if (rangeInput) {
        flatpickr(rangeInput, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: [rangeInput.dataset.from, rangeInput.dataset.to].filter(Boolean),
            onClose(selectedDates) {
                if (selectedDates.length !== 2) return;
                const fromInput = document.getElementById('date_from');
                const toInput = document.getElementById('date_to');
                if (fromInput) fromInput.value = selectedDates[0].toISOString().slice(0, 10);
                if (toInput) toInput.value = selectedDates[1].toISOString().slice(0, 10);
            },
        });
    }

    // ── Revenue line chart (one line per currency) ──────────────────────────
    const revenueCanvas = document.getElementById('revenue-chart');
    if (revenueCanvas) {
        const dataset = window.REVENUE_CHART_DATA || {};
        const labels = [...new Set(Object.values(dataset).flatMap((rows) => rows.map((r) => r.day)))].sort();

        const datasets = Object.entries(dataset).map(([currency, rows], i) => {
            const byDay = Object.fromEntries(rows.map((r) => [r.day, r.total]));
            return {
                label: currency,
                data: labels.map((day) => byDay[day] ?? 0),
                borderColor: PALETTE[i % PALETTE.length],
                backgroundColor: PALETTE[i % PALETTE.length],
                tension: 0.3,
                fill: false,
            };
        });

        new Chart(revenueCanvas, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } },
            },
        });
    }

    // ── Bookings by status donut ─────────────────────────────────────────────
    const statusCanvas = document.getElementById('bookings-status-chart');
    if (statusCanvas) {
        const data = window.BOOKINGS_STATUS_DATA || {};
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data),
                datasets: [{ data: Object.values(data), backgroundColor: PALETTE }],
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
        });
    }

    // ── Bookings over time bar chart ─────────────────────────────────────────
    const overTimeCanvas = document.getElementById('bookings-over-time-chart');
    if (overTimeCanvas) {
        const rows = window.BOOKINGS_OVER_TIME_DATA || [];
        new Chart(overTimeCanvas, {
            type: 'bar',
            data: {
                labels: rows.map((r) => r.day),
                datasets: [{ label: t('travel_agency.reports.bookings_label'), data: rows.map((r) => r.cnt), backgroundColor: PALETTE[0] }],
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });
    }
});
