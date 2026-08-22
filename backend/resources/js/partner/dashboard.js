import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('revenue-chart');
    if (!canvas || !window.DASHBOARD?.revenueChart) return;

    const data = window.DASHBOARD.revenueChart;
    const labels = Object.keys(data).map(date => {
        const d = new Date(date);
        return d.toLocaleDateString('ar-SA', { month: 'short', day: 'numeric' });
    });
    const values = Object.values(data);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'الإيرادات',
                data: values,
                backgroundColor: 'rgba(250, 204, 21, 0.7)', // yellow-400
                borderColor: 'rgba(234, 179, 8, 1)',         // yellow-500
                borderWidth: 1.5,
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    rtl: true,
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y.toLocaleString('ar-SA')}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { family: 'Cairo', size: 11 },
                        color: '#9ca3af',
                    },
                },
                y: {
                    grid: { color: '#f3f4f6' },
                    ticks: {
                        font: { family: 'Cairo', size: 11 },
                        color: '#9ca3af',
                        callback: v => v.toLocaleString('ar-SA'),
                    },
                    beginAtZero: true,
                },
            },
        },
    });
});
