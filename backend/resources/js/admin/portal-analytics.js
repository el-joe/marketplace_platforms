import {
    Chart,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Tooltip,
    Legend,
    Filler,
    LineController,
} from 'chart.js';

Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler, LineController);

const cfg = window.PORTAL_ANALYTICS;
const $ = (id) => document.getElementById(id);
const nf = new Intl.NumberFormat(cfg.locale === 'ar' ? 'ar-EG' : 'en');

const KPI_ORDER = [
    'visits',
    'page_views',
    'unique_visitors',
    'returning_visitors',
    'registration_starts',
    'registration_attempts',
    'registrations_completed',
    'completion_rate',
];

let chart = null;
let requestSeq = 0;

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

const fmt = (n) => nf.format(n ?? 0);
const pct = (n) => `${nf.format(n ?? 0)}%`;

// ── Filters ──────────────────────────────────────────────────────────────────

function readFilters() {
    return {
        date_from: $('pa-from').value,
        date_to: $('pa-to').value,
        granularity: $('pa-granularity').value,
        country: $('pa-country').value,
        flow: $('pa-flow').value,
        compare: $('pa-compare').checked ? '1' : '0',
    };
}

function query(filters, extra = {}) {
    const params = new URLSearchParams();
    Object.entries({ ...filters, ...extra }).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) params.set(k, v);
    });
    return params.toString();
}

function isoDate(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function applyQuickRange(days) {
    const to = new Date();
    const from = new Date();
    if (days !== 'today') from.setDate(from.getDate() - (parseInt(days, 10) - 1));
    $('pa-from').value = isoDate(from);
    $('pa-to').value = isoDate(to);
    load();
}

function highlightQuickRange() {
    const from = $('pa-from').value;
    const to = $('pa-to').value;
    const today = isoDate(new Date());
    document.querySelectorAll('.pa-range-btn').forEach((btn) => {
        const days = btn.dataset.days;
        const start = new Date();
        if (days !== 'today') start.setDate(start.getDate() - (parseInt(days, 10) - 1));
        const active = to === today && from === isoDate(start);
        btn.classList.toggle('bg-primary-600', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('bg-white', !active);
        btn.classList.toggle('text-gray-700', !active);
    });
}

function syncUrlAndExport(filters) {
    history.replaceState(null, '', `${cfg.pageUrl}?${query(filters)}`);
    const section = $('pa-export-section').value;
    document.querySelectorAll('[data-pa-export]').forEach((a) => {
        a.href = `${cfg.pageUrl}?${query(filters, { export: a.dataset.paExport, section })}`;
    });
}

// ── Rendering ────────────────────────────────────────────────────────────────

function deltaHtml(kpi) {
    if (kpi.previous === null || kpi.previous === undefined) return '';
    if (kpi.change === null || kpi.change === undefined) {
        return `<p class="mt-2 text-xs text-gray-400">${esc(cfg.i18n.noBaseline)}</p>`;
    }
    const up = kpi.change >= 0;
    const cls = kpi.change === 0 ? 'text-gray-500' : (up ? 'text-success-600' : 'text-danger-600');
    const arrow = kpi.change === 0 ? '' : (up ? '↑' : '↓');
    const sign = up && kpi.change > 0 ? '+' : '';
    const unit = kpi.change_type === 'points' ? '' : '%';
    const label = kpi.change_type === 'points' ? cfg.i18n.vsPrevPoints : cfg.i18n.vsPrev;
    return `<p class="mt-2 text-xs font-medium ${cls}" dir="ltr" style="text-align:start">${arrow} ${sign}${nf.format(kpi.change)}${unit}
        <span class="text-gray-400 font-normal" dir="auto">${esc(label)}</span></p>`;
}

function renderKpis(kpis) {
    $('pa-kpis').innerHTML = KPI_ORDER.map((key) => {
        const kpi = kpis[key];
        const value = key === 'completion_rate' ? pct(kpi.value) : fmt(kpi.value);
        return `<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5" title="${esc(cfg.i18n.kpiHint[key])}">
            <p class="text-sm font-medium text-gray-500 truncate">${esc(cfg.i18n.kpi[key])}</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 truncate">${value}</p>
            ${deltaHtml(kpi)}
            <span class="sr-only">${esc(cfg.i18n.kpiHint[key])}</span>
        </div>`;
    }).join('');
}

function renderFunnel(funnel) {
    $('pa-funnel-body').innerHTML = funnel.steps.map((s) => {
        const width = Math.max(s.reached > 0 ? 2 : 0, s.reached_of_start);
        const doneShare = s.reached > 0 ? (s.completed / s.reached) * 100 : 0;
        return `<tr>
            <td class="py-3 pe-4 font-medium text-gray-900 whitespace-nowrap"><span class="text-gray-400 me-1">${s.step}.</span>${esc(s.label)}</td>
            <td class="py-3 px-4">
                <div class="h-5 rounded bg-gray-100 overflow-hidden" role="img" aria-label="${esc(s.label)}: ${pct(s.reached_of_start)}">
                    <div class="h-full flex" style="width:${width}%">
                        <div class="h-full bg-primary-500" style="width:${doneShare}%"></div>
                        <div class="h-full bg-amber-400" style="width:${100 - doneShare}%"></div>
                    </div>
                </div>
                <div class="mt-0.5 text-xs text-gray-400">${pct(s.reached_of_start)}</div>
            </td>
            <td class="py-3 px-3 text-end tabular-nums">${fmt(s.reached)}</td>
            <td class="py-3 px-3 text-end tabular-nums text-success-700">${fmt(s.completed)}</td>
            <td class="py-3 px-3 text-end tabular-nums text-amber-700">${fmt(s.abandoned)}</td>
            <td class="py-3 px-3 text-end tabular-nums font-semibold">${pct(s.conversion_rate)}</td>
            <td class="py-3 px-3 text-end tabular-nums text-danger-600">${pct(s.dropoff_rate)}</td>
            <td class="py-3 ps-3 text-end tabular-nums text-gray-500">${s.failed_registrations > 0 ? fmt(s.failed_registrations) : '—'}</td>
        </tr>`;
    }).join('');
}

function renderTimeline(timeline) {
    const t = cfg.i18n.timeline;
    const ctx = $('pa-timeline');
    if (chart) chart.destroy();
    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: timeline.labels,
            datasets: [
                { label: t.page_views, data: timeline.page_views, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.08)', fill: true, tension: 0.3, yAxisID: 'y' },
                { label: t.unique_visitors, data: timeline.unique_visitors, borderColor: '#6366f1', backgroundColor: 'transparent', tension: 0.3, yAxisID: 'y' },
                { label: t.registrations_started, data: timeline.registrations_started, borderColor: '#f59e0b', backgroundColor: 'transparent', tension: 0.3, yAxisID: 'y1' },
                { label: t.registrations_completed, data: timeline.registrations_completed, borderColor: '#22c55e', backgroundColor: 'transparent', tension: 0.3, yAxisID: 'y1' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true, position: 'left', ticks: { precision: 0 } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } },
            },
        },
    });
}

function renderTopPages(pages) {
    $('pa-top-pages').innerHTML = pages.length
        ? pages.map((p) => `<tr>
            <td class="py-2.5 pe-4 font-mono text-xs text-gray-700 break-all" dir="ltr" style="text-align:start">${esc(p.path)}</td>
            <td class="py-2.5 px-4 text-end tabular-nums">${fmt(p.views)}</td>
            <td class="py-2.5 ps-4 text-end tabular-nums">${fmt(p.unique_visitors)}</td>
        </tr>`).join('')
        : `<tr><td colspan="3" class="py-6 text-center text-gray-400">—</td></tr>`;
}

function renderPeriodNote(data, requestedGranularity) {
    const note = $('pa-period-note');
    const parts = [];
    if (data.period.previous_from) {
        parts.push(cfg.i18n.comparedTo.replace(':from', data.period.previous_from).replace(':to', data.period.previous_to));
    }
    if (data.timeline.granularity !== requestedGranularity) {
        parts.push(cfg.i18n.roll);
    }
    note.textContent = parts.join(' · ');
    note.classList.toggle('hidden', parts.length === 0);
}

function setLoading(loading) {
    const content = $('pa-content');
    content.setAttribute('aria-busy', loading ? 'true' : 'false');
    content.classList.toggle('opacity-60', loading);
    content.classList.toggle('pointer-events-none', loading);
    $('pa-apply').disabled = loading;
}

function showError(show) {
    $('pa-error').classList.toggle('hidden', !show);
    $('pa-error').classList.toggle('flex', show);
}

// ── Loading ──────────────────────────────────────────────────────────────────

async function load() {
    const filters = readFilters();
    const seq = ++requestSeq;

    highlightQuickRange();
    syncUrlAndExport(filters);
    setLoading(true);
    showError(false);

    try {
        const response = await fetch(`${cfg.dataUrl}?${query(filters)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (seq !== requestSeq) return;

        if (!response.ok) {
            let message = cfg.i18n.errorDetail;
            if (response.status === 422) {
                const body = await response.json().catch(() => null);
                const first = body && body.errors ? Object.values(body.errors).flat()[0] : null;
                if (first) message = first;
            }
            throw new Error(message);
        }

        const { data } = await response.json();
        if (seq !== requestSeq) return;

        // Keep the selector in step with what the server actually applied (e.g. long ranges roll up to weeks).
        $('pa-granularity').value = data.timeline.granularity;

        $('pa-empty').classList.toggle('hidden', data.has_data);
        renderKpis(data.kpis);
        renderFunnel(data.funnel);
        renderTimeline(data.timeline);
        renderTopPages(data.top_pages);
        renderPeriodNote(data, filters.granularity);
    } catch (error) {
        if (seq !== requestSeq) return;
        $('pa-error-text').textContent = error.message || cfg.i18n.errorDetail;
        showError(true);
    } finally {
        if (seq === requestSeq) setLoading(false);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.pa-range-btn').forEach((btn) => btn.addEventListener('click', () => applyQuickRange(btn.dataset.days)));
    $('pa-apply').addEventListener('click', load);
    $('pa-retry').addEventListener('click', load);
    ['pa-granularity', 'pa-country', 'pa-flow', 'pa-compare'].forEach((id) => $(id).addEventListener('change', load));
    $('pa-export-section').addEventListener('change', () => syncUrlAndExport(readFilters()));
    load();
});
