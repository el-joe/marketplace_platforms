/**
 * resources/js/marketer/dashboard.js
 * Chart.js dual-axis revenue/clicks chart + copy-referral-btn
 */
import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    // ── Revenue Chart ─────────────────────────────────────────────────────────
    const canvas = document.getElementById('revenue-chart');
    if (canvas && window.__dashboardChart) {
        const { labels, revenueData, clicksData } = window.__dashboardChart;

        new Chart(canvas, {
            data: {
                labels,
                datasets: [
                    {
                        type: 'bar',
                        label: t('marketer.dashboard.clicks_label'),
                        data: clicksData ?? [],
                        backgroundColor: 'rgba(59,130,246,0.35)',
                        borderRadius: 4,
                        yAxisID: 'yClicks',
                        order: 2,
                    },
                    {
                        type: 'line',
                        label: t('marketer.dashboard.earnings_label'),
                        data: revenueData ?? [],
                        borderColor: '#facc15',
                        backgroundColor: 'rgba(250,204,21,0.08)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        yAxisID: 'yRevenue',
                        order: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        ticks: { maxTicksLimit: 8, font: { size: 10 } },
                        grid: { display: false },
                    },
                    yRevenue: {
                        position: 'left',
                        ticks: { font: { size: 10 } },
                        grid: { color: '#f1f5f9' },
                        title: { display: true, text: t('marketer.dashboard.earnings_label'), font: { size: 10 } },
                    },
                    yClicks: {
                        position: 'right',
                        ticks: { font: { size: 10 } },
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: t('marketer.dashboard.clicks_label'), font: { size: 10 } },
                    },
                },
                plugins: {
                    legend: { labels: { font: { size: 11 }, usePointStyle: true } },
                    tooltip: { callbacks: {
                        label: (ctx) => {
                            const v = ctx.raw ?? 0;
                            return ctx.dataset.label + ': ' + (ctx.datasetIndex === 1 ? v.toFixed(2) + ' SAR' : v);
                        },
                    }},
                },
            },
        });
    }

    // ── Copy referral link button ─────────────────────────────────────────────
    document.querySelectorAll('[data-copy-referral]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const url = document.getElementById('global-ref-url')?.textContent?.trim();
            if (!url) return;
            navigator.clipboard.writeText(url).then(() => {
                const original = btn.textContent;
                btn.textContent = t('marketer.dashboard.copied');
                setTimeout(() => { btn.textContent = original; }, 2000);
            });
        });
    });
});
