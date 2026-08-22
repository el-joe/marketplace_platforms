/**
 * Travel Agency → Performance JS
 * Fetches stats.json from the Performance controller and renders KPI cards + charts.
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
    Tooltip,
    Legend,
    LineController,
    BarController,
} from 'chart.js';

Chart.register(
    LineElement, BarElement, PointElement, LinearScale, CategoryScale,
    Tooltip, Legend, LineController, BarController,
);

const PALETTE = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

let bookingsChart = null;
let revenueChart = null;
let topPackagesChart = null;

function renderBookingsChart(points) {
    const canvas = document.getElementById('bookings-chart');
    if (!canvas) return;
    if (bookingsChart) bookingsChart.destroy();

    bookingsChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map((p) => p.day),
            datasets: [{
                label: window.PERFORMANCE.i18n.bookings,
                data: points.map((p) => p.total),
                borderColor: PALETTE[0],
                backgroundColor: PALETTE[0],
                tension: 0.3,
            }],
        },
        options: { responsive: true, plugins: { legend: { display: false } } },
    });
}

function renderRevenueChart(dataset) {
    const canvas = document.getElementById('revenue-chart');
    if (!canvas) return;
    if (revenueChart) revenueChart.destroy();

    const labels = [...new Set(Object.values(dataset).flatMap((rows) => rows.map((r) => r.day)))].sort();
    const datasets = Object.entries(dataset).map(([currency, rows], i) => {
        const byDay = Object.fromEntries(rows.map((r) => [r.day, r.total]));
        return {
            label: currency,
            data: labels.map((day) => byDay[day] ?? 0),
            borderColor: PALETTE[i % PALETTE.length],
            backgroundColor: PALETTE[i % PALETTE.length],
            tension: 0.3,
        };
    });

    revenueChart = new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        options: { responsive: true },
    });
}

function renderTopPackagesChart(topPackages) {
    const canvas = document.getElementById('top-packages-chart');
    if (!canvas) return;
    if (topPackagesChart) topPackagesChart.destroy();

    topPackagesChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: topPackages.map((p) => p.title),
            datasets: [{
                label: window.PERFORMANCE.i18n.revenue,
                data: topPackages.map((p) => p.revenue),
                backgroundColor: PALETTE[1],
            }],
        },
        options: { responsive: true, plugins: { legend: { display: false } } },
    });
}

function renderCards(stats) {
    document.getElementById('kpi-total-bookings').textContent = stats.total_bookings;
    document.getElementById('kpi-completed-bookings').textContent = stats.completed_bookings;
    document.getElementById('kpi-cancelled-bookings').textContent = stats.cancelled_bookings;
    document.getElementById('kpi-cancellation-rate').textContent = `${stats.cancellation_rate}%`;

    const avgValueEl = document.getElementById('kpi-avg-booking-value');
    avgValueEl.innerHTML = stats.avg_booking_value.length
        ? stats.avg_booking_value.map((v) => `${v.avg_value.toLocaleString()} ${v.currency}`).join('<br>')
        : '—';

    const revenueEl = document.getElementById('kpi-total-revenue');
    revenueEl.innerHTML = stats.avg_booking_value.length
        ? stats.avg_booking_value.map((v) => `${v.total_revenue.toLocaleString()} ${v.currency}`).join('<br>')
        : '—';

    document.getElementById('kpi-avg-response-time').textContent = stats.avg_response_minutes !== null
        ? `${stats.avg_response_minutes} ${window.PERFORMANCE.i18n.minutes}`
        : '—';
}

function renderTable(packagePerformance) {
    const tbody = document.getElementById('package-performance-body');
    if (!tbody) return;

    if (!packagePerformance.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">${window.PERFORMANCE.i18n.noData}</td></tr>`;
        return;
    }

    tbody.innerHTML = packagePerformance.map((row) => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-900 font-medium">${row.title}</td>
            <td class="px-4 py-3 text-gray-700">${row.inquiries}</td>
            <td class="px-4 py-3 text-gray-700">${row.bookings}</td>
            <td class="px-4 py-3 text-gray-700">${row.conversion_rate}%</td>
            <td class="px-4 py-3 font-medium text-gray-900">${row.revenue.toLocaleString()} ${row.currency}</td>
        </tr>
    `).join('');
}

function loadStats(params = {}) {
    const url = new URL(window.PERFORMANCE.statsUrl, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value) url.searchParams.set(key, value);
    });

    fetch(url, { headers: { Accept: 'application/json' } })
        .then((res) => res.json())
        .then((stats) => {
            renderCards(stats);
            renderBookingsChart(stats.bookings_over_time);
            renderRevenueChart(stats.revenue_over_time);
            renderTopPackagesChart(stats.top_packages);
            renderTable(stats.package_performance);
        });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.PERFORMANCE) return;

    const rangeInput = document.getElementById('performance-date-range');
    const fromInput = document.getElementById('date_from');
    const toInput = document.getElementById('date_to');

    if (rangeInput) {
        flatpickr(rangeInput, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: [rangeInput.dataset.from, rangeInput.dataset.to].filter(Boolean),
            onClose(selectedDates) {
                if (selectedDates.length !== 2) return;
                const from = selectedDates[0].toISOString().slice(0, 10);
                const to = selectedDates[1].toISOString().slice(0, 10);
                if (fromInput) fromInput.value = from;
                if (toInput) toInput.value = to;
                loadStats({ date_from: from, date_to: to });
            },
        });
    }

    loadStats({ date_from: fromInput?.value, date_to: toInput?.value });
});
