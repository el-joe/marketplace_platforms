/**
 * Marketer → Reports JS
 * Monthly earnings bar chart on the "My Reports" page.
 */
import {
    Chart,
    BarElement,
    LinearScale,
    CategoryScale,
    Tooltip,
    Legend,
    BarController,
} from 'chart.js';

Chart.register(BarElement, LinearScale, CategoryScale, Tooltip, Legend, BarController);

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('monthly-earnings-chart');
    if (!canvas) return;

    const rows = window.MONTHLY_EARNINGS_DATA || [];

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.month),
            datasets: [{
                label: t('partner.marketer_reports.chart.earnings_label'),
                data: rows.map((r) => r.total),
                backgroundColor: '#3b82f6',
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
});
