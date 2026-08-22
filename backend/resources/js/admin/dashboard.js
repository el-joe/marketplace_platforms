import {
    Chart,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    ArcElement,
    Tooltip,
    Legend,
    Filler,
    LineController,
    DoughnutController,
} from 'chart.js';

Chart.register(
    LineElement, PointElement, LinearScale, CategoryScale,
    ArcElement, Tooltip, Legend, Filler,
    LineController, DoughnutController,
);

// ── Status badge colour map ───────────────────────────────────────────────────
const STATUS_CLASSES = {
    pending: 'bg-warning-100 text-warning-700',
    processing: 'bg-primary-100 text-primary-700',
    shipped: 'bg-purple-100 text-purple-700',
    delivered: 'bg-success-100 text-success-700',
    cancelled: 'bg-danger-100 text-danger-700',
    refunded: 'bg-gray-100 text-gray-700',
};

// ── Chart instances (kept so we can destroy on re-render) ────────────────────
let revenueChart = null;
let donutChart = null;

// ── Shared filter state ───────────────────────────────────────────────────────
let currentPeriod = 'week';
let currentCountry = '';

$(function () {

    // Pick up the admin's default country if pre-selected in the dropdown
    currentCountry = $('#dashboard-country-filter').val() || '';

    // ── Stat cards ────────────────────────────────────────────────────────────
    loadStats(currentPeriod, currentCountry);

    // Period toggle — active state + reload
    $(document).on('click', '.stat-period-btn', function () {
        currentPeriod = $(this).data('period');
        $('.stat-period-btn')
            .removeClass('bg-white shadow-sm text-gray-900 active')
            .addClass('text-gray-600');
        $(this)
            .addClass('bg-white shadow-sm text-gray-900 active')
            .removeClass('text-gray-600');
        loadStats(currentPeriod, currentCountry);
        loadOrdersDonut(currentPeriod, currentCountry);
    });

    // Country filter change — refresh everything that is country-sensitive
    $('#dashboard-country-filter').on('change', function () {
        currentCountry = $(this).val();
        loadStats(currentPeriod, currentCountry);
        loadOrdersDonut(currentPeriod, currentCountry);
        loadRevenueChart($('.chart-range-btn.active').data('range') || 30, currentCountry);
        loadRecentOrders(currentCountry);
    });

    // ── Revenue chart ─────────────────────────────────────────────────────────
    loadRevenueChart(30, currentCountry);

    $(document).on('click', '.chart-range-btn', function () {
        $('.chart-range-btn')
            .removeClass('bg-white shadow-sm text-gray-900 active')
            .addClass('text-gray-600');
        $(this)
            .addClass('bg-white shadow-sm text-gray-900 active')
            .removeClass('text-gray-600');
        loadRevenueChart($(this).data('range'), currentCountry);
    });

    // ── Orders donut ──────────────────────────────────────────────────────────
    loadOrdersDonut(currentPeriod, currentCountry);

    // ── Recent orders (poll every 30 s) ───────────────────────────────────────
    loadRecentOrders(currentCountry);
    setInterval(() => loadRecentOrders(currentCountry), 30_000);

    // ── Top sellers ───────────────────────────────────────────────────────────
    loadTopSellers();

    // ── Pending approvals ────────────────────────────────────────────────────
    loadPendingItems();

    // ── Low stock ─────────────────────────────────────────────────────────────
    loadLowStock();
});

// ─────────────────────────────────────────────────────────────────────────────
// STAT CARDS
// ─────────────────────────────────────────────────────────────────────────────
function loadStats(period, countryId = '') {
    // Skeleton while loading
    $('.stat-value').each(function () {
        $(this).html('<span class="inline-block h-8 w-24 bg-gray-200 rounded animate-pulse"></span>');
    });
    $('.stat-change').html('');

    $.ajax({
        url: '/dashboard/stats',
        method: 'GET',
        data: { period, country_id: countryId },
        success(response) {
            const d = response.data;

            $('#stat-gmv').text(d.gmv);
            $('#stat-orders').text(d.orders);
            $('#stat-revenue').text(d.revenue);
            $('#stat-sellers').text(d.sellers);

            renderChange('#stat-gmv-change', d.changes.gmv);
            renderChange('#stat-orders-change', d.changes.orders);
            renderChange('#stat-revenue-change', d.changes.revenue);
            renderChange('#stat-sellers-change', d.changes.sellers);
        },
        error() {
            $('.stat-value').text('—');
        },
    });
}

/** Renders an up/down percentage change badge. */
function renderChange(selector, pct) {
    if (pct === null || pct === undefined) return;
    const isUp = pct >= 0;
    const color = isUp ? 'text-success-600' : 'text-danger-600';
    const arrow = isUp
        ? '<svg class="inline w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>'
        : '<svg class="inline w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>';
    const sign = isUp ? '+' : '';
    const vsLastPeriod = window.TRANSLATIONS?.vsLastPeriod || 'vs last period';
    $(selector).html(
        `<span class="${color} font-semibold">${arrow} ${sign}${pct.toFixed(1)}%</span>
         <span class="text-gray-400 font-normal ml-1">${vsLastPeriod}</span>`
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// REVENUE LINE CHART
// ─────────────────────────────────────────────────────────────────────────────
function loadRevenueChart(days, countryId = '') {
    $.ajax({
        url: '/dashboard/revenue-chart',
        method: 'GET',
        data: { range: days, country_id: countryId },
        success(response) {
            const d = response.data;

            if (revenueChart) {
                revenueChart.destroy();
                revenueChart = null;
            }

            revenueChart = new Chart(
                document.getElementById('revenue-chart'),
                {
                    type: 'line',
                    data: {
                        labels: d.labels,
                        datasets: [
                            {
                                label: window.TRANSLATIONS?.gmv || 'GMV',
                                data: d.gmv,
                                borderColor: '#0284c7',
                                backgroundColor: 'rgba(2,132,199,0.08)',
                                fill: true,
                                tension: 0.35,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                            },
                            {
                                label: window.TRANSLATIONS?.commission || 'Commission',
                                data: d.commission,
                                borderColor: '#16a34a',
                                backgroundColor: 'transparent',
                                tension: 0.35,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                borderDash: [4, 4],
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6 } },
                            tooltip: {
                                callbacks: {
                                    label: ctx =>
                                        ` ${ctx.dataset.label}: EGP ${new Intl.NumberFormat().format(ctx.raw / 100)
                                        }`,
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: v => {
                                        if (v === 0) return '0';
                                        const k = v / 100 / 1000;
                                        return k >= 1 ? k.toFixed(0) + 'K' : (v / 100).toFixed(0);
                                    },
                                },
                            },
                        },
                    },
                }
            );
        },
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// ORDERS DONUT CHART
// ─────────────────────────────────────────────────────────────────────────────
function loadOrdersDonut(period = 'week', countryId = '') {
    $.ajax({
        url: '/dashboard/orders-by-status',
        method: 'GET',
        data: { period, country_id: countryId },
        success(response) {
            const d = response.data;

            if (donutChart) {
                donutChart.destroy();
                donutChart = null;
            }

            donutChart = new Chart(
                document.getElementById('orders-donut'),
                {
                    type: 'doughnut',
                    data: {
                        labels: d.labels,
                        datasets: [{
                            data: d.values,
                            backgroundColor: d.colors,
                            borderWidth: 2,
                            borderColor: '#fff',
                            hoverBorderColor: '#fff',
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ` ${ctx.label}: ${ctx.raw}`,
                                },
                            },
                        },
                    },
                }
            );

            // Build custom legend
            const total = d.values.reduce((a, b) => a + b, 0);
            const legend = d.labels.map((label, i) => {
                const pct = total > 0 ? ((d.values[i] / total) * 100).toFixed(0) : 0;
                return `<li class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:${d.colors[i]}"></span>
                        <span>${label}</span>
                    </span>
                    <span class="font-semibold text-gray-700">${d.values[i]} <span class="font-normal text-gray-400">(${pct}%)</span></span>
                </li>`;
            }).join('');
            $('#donut-legend').html(legend);
        },
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// RECENT ORDERS
// ─────────────────────────────────────────────────────────────────────────────
function loadRecentOrders(countryId = '') {
    $.ajax({
        url: '/dashboard/recent-orders',
        method: 'GET',
        data: { country_id: countryId },
        success(response) {
            const rows = response.data.map(order => {
                const statusClass = STATUS_CLASSES[order.status] || 'bg-gray-100 text-gray-700';
                return `<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 font-mono text-xs text-gray-700 whitespace-nowrap">${escapeHtml(order.order_number)}</td>
                    <td class="px-5 py-3 text-gray-900 whitespace-nowrap">${escapeHtml(order.customer_name)}</td>
                    <td class="px-5 py-3 font-medium text-gray-900 whitespace-nowrap">${escapeHtml(order.total)}</td>
                    <td class="px-5 py-3 whitespace-nowrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                            ${escapeHtml(order.status_label || order.status)}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-500 text-xs whitespace-nowrap">${escapeHtml(order.created_at)}</td>
                </tr>`;
            }).join('');

            const noOrdersYet = window.TRANSLATIONS?.noOrdersYet || 'No orders yet';
            $('#recent-orders-tbody').html(
                rows || `<tr><td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">${noOrdersYet}</td></tr>`
            );
        },
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// TOP SELLERS
// ─────────────────────────────────────────────────────────────────────────────
function loadTopSellers() {
    $.ajax({
        url: '/dashboard/top-sellers',
        method: 'GET',
        success(response) {
            const rows = response.data.map((seller, idx) =>
                `<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 text-xs font-medium text-gray-400">${idx + 1}</td>
                    <td class="px-5 py-3 font-medium text-gray-900">${escapeHtml(seller.business_name)}</td>
                    <td class="px-5 py-3 text-right font-medium text-gray-900">${escapeHtml(seller.gmv)}</td>
                    <td class="px-5 py-3 text-right text-gray-700">${seller.order_count}</td>
                </tr>`
            ).join('');

            const noSellersData = window.TRANSLATIONS?.noSellersData || 'No data yet';
            $('#top-sellers-tbody').html(
                rows || `<tr><td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">${noSellersData}</td></tr>`
            );
        },
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// PENDING APPROVALS
// ─────────────────────────────────────────────────────────────────────────────
function loadPendingItems() {
    $.ajax({
        url: '/dashboard/pending-items',
        method: 'GET',
        success(response) {
            const d = response.data;
            $('#pending-products').text(d.products ?? 0);
            $('#pending-vendors').text(d.vendors ?? 0);
            $('#pending-disputes').text(d.disputes ?? 0);
            $('#pending-withdrawals').text(d.withdrawals ?? 0);
            $('#pending-returns').text(d.returns ?? 0);

            // Highlight non-zero counts
            $('.pending-badge').each(function () {
                const n = parseInt($(this).text(), 10);
                $(this).toggleClass('text-danger-600', n > 0).toggleClass('text-gray-900', n === 0);
            });
        },
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// LOW STOCK
// ─────────────────────────────────────────────────────────────────────────────
function loadLowStock() {
    $.ajax({
        url: '/dashboard/low-stock',
        method: 'GET',
        success(response) {
            const rows = response.data.map(p => {
                const urgency = p.stock <= 0
                    ? 'bg-danger-100 text-danger-700'
                    : p.stock <= 2
                        ? 'bg-warning-100 text-warning-700'
                        : 'bg-gray-100 text-gray-700';
                return `<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 text-gray-900 max-w-0 truncate" style="max-width:180px"
                        title="${escapeAttr(p.name)}">${escapeHtml(p.name)}</td>
                    <td class="px-5 py-3 text-gray-500 text-xs">${escapeHtml(p.vendor)}</td>
                    <td class="px-5 py-3 text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold ${urgency}">
                            ${p.stock}
                        </span>
                    </td>
                </tr>`;
            }).join('');

            const noLowStockItems = window.TRANSLATIONS?.noLowStockItems || 'No low-stock items';
            $('#low-stock-tbody').html(
                rows || `<tr><td colspan="3" class="px-5 py-8 text-center text-gray-400 text-sm">${noLowStockItems}</td></tr>`
            );
        },
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// UTILS
// ─────────────────────────────────────────────────────────────────────────────
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, '&#39;');
}
