import './app.js';
import Chart from 'chart.js/auto';

// ─────────────────────────────────────────────────────────────────────────────
// State
// ─────────────────────────────────────────────────────────────────────────────

let revenueChart = null;
let reviewsChart = null;
let activePeriod = 'month';

// ─────────────────────────────────────────────────────────────────────────────
// Period tab management
// ─────────────────────────────────────────────────────────────────────────────

function initPeriodTabs() {
    const tabs = document.querySelectorAll('.period-tab');
    const customRow = document.getElementById('custom-range-row');
    const applyBtn = document.getElementById('btn-apply-custom');
    const customStartEl = document.getElementById('custom-start');
    const customEndEl = document.getElementById('custom-end');

    // Set today as max for both inputs
    const today = new Date().toISOString().split('T')[0];
    if (customStartEl) customStartEl.max = today;
    if (customEndEl) customEndEl.max = today;

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const period = tab.dataset.period;
            setActiveTab(tab, tabs);
            activePeriod = period;

            if (period === 'custom') {
                customRow?.classList.remove('hidden');
            } else {
                customRow?.classList.add('hidden');
                fetchStats({ period });
            }
        });
    });

    applyBtn?.addEventListener('click', () => {
        const start = customStartEl?.value;
        const end = customEndEl?.value;
        if (!start || !end) {
            window.Toastify?.({ text: 'يرجى تحديد تاريخ البداية والنهاية', backgroundColor: '#ef4444', duration: 3000 }).showToast();
            return;
        }
        if (start > end) {
            window.Toastify?.({ text: 'تاريخ البداية يجب أن يكون قبل تاريخ النهاية', backgroundColor: '#ef4444', duration: 3000 }).showToast();
            return;
        }
        fetchStats({ period: 'custom', start_date: start, end_date: end });
    });
}

function setActiveTab(activeEl, allTabs) {
    allTabs.forEach(t => {
        t.classList.remove('bg-primary-600', 'text-white', 'shadow-sm', 'hover:bg-primary-600');
        t.classList.add('text-gray-600', 'hover:bg-gray-100');
    });
    activeEl.classList.remove('text-gray-600', 'hover:bg-gray-100');
    activeEl.classList.add('bg-primary-600', 'text-white', 'shadow-sm', 'hover:bg-primary-600');
}

// ─────────────────────────────────────────────────────────────────────────────
// Fetch & populate
// ─────────────────────────────────────────────────────────────────────────────

async function fetchStats(params = { period: 'month' }) {
    const cfg = window.PERFORMANCE;
    if (!cfg?.statsUrl) return;

    showSkeletons();
    const chartLoadingEl = document.getElementById('chart-loading');
    if (chartLoadingEl) chartLoadingEl.classList.remove('hidden');

    const url = new URL(cfg.statsUrl, location.origin);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

    try {
        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();

        renderKPIs(data);
        renderPerformanceIndicators(data);
        renderRevenueChart(data.revenue_chart ?? []);
        renderTopProducts(data.top_products ?? []);
        renderReviewsChart(data.reviews_breakdown ?? {}, data.total_reviews ?? 0);
        renderFlashSummary(data);
        renderPeriodLabel(data.period);

    } catch (err) {
        console.error('Performance stats error', err);
        window.Toastify?.({ text: 'تعذَّر تحميل البيانات، يرجى المحاولة مجدداً', backgroundColor: '#ef4444', duration: 4000 }).showToast();
    } finally {
        if (chartLoadingEl) chartLoadingEl.classList.add('hidden');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// KPI cards
// ─────────────────────────────────────────────────────────────────────────────

function renderKPIs(data) {
    const fmt = (n, dec = 2) => Number(n ?? 0).toLocaleString('ar-SA', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const fmtInt = n => Number(n ?? 0).toLocaleString('ar-SA');

    setText('kpi-gmv', fmt(data.gmv));
    setText('kpi-orders', fmtInt(data.orders_count));
    setText('kpi-aov', fmt(data.avg_order_value));

    const slaEl = document.getElementById('kpi-sla');
    if (slaEl) {
        if (data.sla_compliance == null) {
            slaEl.textContent = '—';
            slaEl.className = 'text-2xl font-bold tabular-nums text-gray-400';
        } else {
            slaEl.textContent = fmt(data.sla_compliance, 1) + '%';
            slaEl.className = 'text-2xl font-bold tabular-nums ' + thresholdColor(data.sla_compliance, 95, 90, true);
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Performance indicators
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns a Tailwind text color class based on a value and two thresholds.
 * higherIsBetter=true: value > good threshold → green, < warn threshold → red
 * higherIsBetter=false: value < good threshold → green, > warn threshold → red
 */
function thresholdColor(value, goodThreshold, warnThreshold, higherIsBetter = true) {
    if (higherIsBetter) {
        if (value >= goodThreshold) return 'text-green-600';
        if (value < warnThreshold) return 'text-red-600';
        return 'text-amber-600';
    } else {
        if (value <= goodThreshold) return 'text-green-600';
        if (value > warnThreshold) return 'text-red-600';
        return 'text-amber-600';
    }
}

function thresholdBarColor(value, goodThreshold, warnThreshold, higherIsBetter = true) {
    const color = thresholdColor(value, goodThreshold, warnThreshold, higherIsBetter);
    return color.replace('text-', 'bg-');
}

function renderPerformanceIndicators(data) {
    const fmt1 = n => Number(n ?? 0).toLocaleString('ar-SA', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    // SLA compliance: > 95% green, 90-95% amber, < 90% red
    if (data.sla_compliance != null) {
        const slaColor = thresholdColor(data.sla_compliance, 95, 90, true);
        const slaBarColor = thresholdBarColor(data.sla_compliance, 95, 90, true);
        setTextClass('ind-sla-value', fmt1(data.sla_compliance) + '%', slaColor + ' text-sm font-bold');
        setBarWidth('ind-sla-bar', Math.min(100, data.sla_compliance), slaBarColor);
    } else {
        setText('ind-sla-value', '—');
        setBarWidth('ind-sla-bar', 0, 'bg-gray-300');
    }

    // Return rate: < 5% green, 5-10% amber, > 10% red
    const retColor = thresholdColor(data.return_rate ?? 0, 5, 10, false);
    const retBarColor = thresholdBarColor(data.return_rate ?? 0, 5, 10, false);
    setTextClass('ind-return-value', fmt1(data.return_rate ?? 0) + '%', retColor + ' text-sm font-bold');
    setBarWidth('ind-return-bar', Math.min(100, ((data.return_rate ?? 0) / 10) * 100), retBarColor);

    // Cancellation rate: < 5% green, 5-10% amber, > 10% red
    const canColor = thresholdColor(data.cancellation_rate ?? 0, 5, 10, false);
    const canBarColor = thresholdBarColor(data.cancellation_rate ?? 0, 5, 10, false);
    setTextClass('ind-cancel-value', fmt1(data.cancellation_rate ?? 0) + '%', canColor + ' text-sm font-bold');
    setBarWidth('ind-cancel-bar', Math.min(100, ((data.cancellation_rate ?? 0) / 10) * 100), canBarColor);

    // Rating: > 4.0 green, 3.5-4.0 amber, < 3.5 red
    if (data.rating_avg != null) {
        const ratColor = thresholdColor(data.rating_avg, 4.0, 3.5, true);
        const ratBarColor = thresholdBarColor(data.rating_avg, 4.0, 3.5, true);
        const label = Number(data.rating_avg).toFixed(1) + ' ★  (' + Number(data.rating_count ?? 0).toLocaleString('ar-SA') + ')';
        setTextClass('ind-rating-value', label, ratColor + ' text-sm font-bold');
        setBarWidth('ind-rating-bar', (data.rating_avg / 5) * 100, ratBarColor);
    } else {
        setText('ind-rating-value', '—');
        setBarWidth('ind-rating-bar', 0, 'bg-gray-300');
    }

    // Active strikes
    const strikesEl = document.getElementById('ind-strikes');
    if (strikesEl) {
        const strikes = data.active_strikes ?? 0;
        strikesEl.textContent = strikes;
        strikesEl.className = 'text-sm font-bold ' + (strikes > 0 ? 'text-red-600' : 'text-green-600');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Revenue chart (Chart.js line)
// ─────────────────────────────────────────────────────────────────────────────

function renderRevenueChart(points) {
    const canvas = document.getElementById('revenue-chart');
    const emptyEl = document.getElementById('revenue-chart-empty');
    if (!canvas) return;

    const hasData = points.some(p => p.revenue > 0);
    if (emptyEl) { emptyEl.classList.toggle('hidden', hasData); emptyEl.classList.toggle('flex', !hasData); }
    canvas.style.display = hasData ? 'block' : 'none';
    if (!hasData) return;

    const labels = points.map(p => formatDateLabel(p.date));
    const values = points.map(p => p.revenue);

    if (revenueChart) {
        revenueChart.data.labels = labels;
        revenueChart.data.datasets[0].data = values;
        revenueChart.update('active');
    } else {
        revenueChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'الإيرادات',
                    data: values,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    borderWidth: 2,
                    pointRadius: points.length <= 14 ? 4 : 0,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl',
                        callbacks: {
                            label: ctx => ' ' + Number(ctx.parsed.y).toLocaleString('ar-SA', { minimumFractionDigits: 2 }),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 7, maxRotation: 0, font: { size: 11 } },
                    },
                    y: {
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 11 }, callback: v => Number(v).toLocaleString('ar-SA') },
                        beginAtZero: true,
                    },
                },
            },
        });
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Top products table
// ─────────────────────────────────────────────────────────────────────────────

function renderTopProducts(products) {
    const tbody = document.getElementById('top-products-body');
    if (!tbody) return;

    if (products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">لا توجد مبيعات في هذه الفترة</td></tr>`;
        return;
    }

    const maxRevenue = Math.max(...products.map(p => p.revenue), 1);

    tbody.innerHTML = products.map((p, i) => {
        const barPct = Math.round((p.revenue / maxRevenue) * 100);
        const revenue = Number(p.revenue).toLocaleString('ar-SA', { minimumFractionDigits: 2 });
        const units = Number(p.units_sold).toLocaleString('ar-SA');

        return `<tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm font-medium text-gray-400">${i + 1}</td>
            <td class="px-4 py-3">
                <p class="text-sm font-medium text-gray-900 truncate max-w-[160px]">${escHtml(p.product_name)}</p>
                <div class="mt-1 h-1 w-full rounded-full bg-gray-100">
                    <div class="h-1 rounded-full bg-primary-400 transition-all duration-500" style="width:${barPct}%"></div>
                </div>
            </td>
            <td class="px-4 py-3 text-sm font-semibold text-gray-800 tabular-nums">${revenue}</td>
            <td class="px-4 py-3 text-sm text-gray-600 tabular-nums">${units}</td>
        </tr>`;
    }).join('');
}

// ─────────────────────────────────────────────────────────────────────────────
// Reviews distribution chart (Chart.js bar)
// ─────────────────────────────────────────────────────────────────────────────

function renderReviewsChart(breakdown, totalReviews) {
    const canvas = document.getElementById('reviews-chart');
    const emptyEl = document.getElementById('reviews-chart-empty');
    const totalEl = document.getElementById('reviews-total');
    if (!canvas) return;

    if (totalEl) {
        totalEl.textContent = totalReviews > 0
            ? `${Number(totalReviews).toLocaleString('ar-SA')} تقييم`
            : '—';
    }

    const hasData = totalReviews > 0;
    if (emptyEl) { emptyEl.classList.toggle('hidden', hasData); emptyEl.classList.toggle('flex', !hasData); }
    canvas.style.display = hasData ? 'block' : 'none';
    if (!hasData) return;

    const labels = ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'];
    const values = ['1', '2', '3', '4', '5'].map(k => breakdown[k] ?? 0);
    const colors = ['#ef4444', '#f97316', '#eab308', '#84cc16', '#22c55e'];

    if (reviewsChart) {
        reviewsChart.data.datasets[0].data = values;
        reviewsChart.update('active');
    } else {
        reviewsChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'عدد التقييمات',
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 6,
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + Number(ctx.parsed.y).toLocaleString('ar-SA') + ' تقييم',
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 12 } } },
                    y: {
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 11 }, stepSize: 1 },
                        beginAtZero: true,
                    },
                },
            },
        });
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Flash sale summary row
// ─────────────────────────────────────────────────────────────────────────────

function renderFlashSummary(data) {
    const row = document.getElementById('flash-summary-row');
    if (!row) return;

    const units = data.flash_units_sold ?? 0;
    const revenue = data.flash_revenue ?? 0;

    if (units > 0 || revenue > 0) {
        row.classList.remove('hidden');
        setText('flash-units', Number(units).toLocaleString('ar-SA'));
        setText('flash-revenue', Number(revenue).toLocaleString('ar-SA', { minimumFractionDigits: 2 }));
    } else {
        row.classList.add('hidden');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Period label
// ─────────────────────────────────────────────────────────────────────────────

function renderPeriodLabel(period) {
    const el = document.getElementById('period-label');
    if (!el || !period) return;
    el.textContent = `${formatDateLabel(period.start)} — ${formatDateLabel(period.end)}`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Skeleton loader
// ─────────────────────────────────────────────────────────────────────────────

function showSkeletons() {
    ['kpi-gmv', 'kpi-orders', 'kpi-aov'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<span class="block h-7 w-24 animate-pulse rounded bg-gray-200"></span>';
    });

    const slaEl = document.getElementById('kpi-sla');
    if (slaEl) slaEl.innerHTML = '<span class="block h-7 w-16 animate-pulse rounded bg-gray-200"></span>';

    const strikesEl = document.getElementById('ind-strikes');
    if (strikesEl) strikesEl.innerHTML = '<span class="block h-4 w-6 animate-pulse rounded bg-gray-200"></span>';
}

// ─────────────────────────────────────────────────────────────────────────────
// DOM helpers
// ─────────────────────────────────────────────────────────────────────────────

function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

function setTextClass(id, text, cls) {
    const el = document.getElementById(id);
    if (el) { el.textContent = text; el.className = cls; }
}

function setBarWidth(id, pct, colorClass) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.width = `${Math.max(0, Math.min(100, pct))}%`;
    // Replace only bg-* color classes
    el.className = el.className.replace(/bg-\S+/g, '').trim() + ' ' + colorClass + ' h-2 rounded-full transition-all duration-500';
}

function formatDateLabel(dateStr) {
    if (!dateStr) return '—';
    const [y, m, d] = dateStr.split('-');
    return `${parseInt(d, 10)}/${parseInt(m, 10)}/${y}`;
}

function escHtml(str) {
    return String(str ?? '—')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initPeriodTabs();
    fetchStats({ period: 'month' });
});
