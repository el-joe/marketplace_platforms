@extends('layouts.admin')

@section('title', __('admin.finance.title'))

@section('content')

{{-- ─── Page Header ──────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.finance.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.finance.per_country_subtitle') }}</p>
    </div>

    {{-- ─── Actions ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 flex-shrink-0">
        <button id="btn-export" type="button"
            class="btn btn-secondary btn-sm flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            {{ __('admin.finance.export_csv') }}
        </button>
        <button id="btn-export-excel" type="button" class="btn btn-secondary btn-sm">{{ __('common.export_excel') }}</button>
        <button id="btn-export-word" type="button" class="btn btn-secondary btn-sm">{{ __('common.export_word') }}</button>
    </div>
</div>

{{-- ─── Filters bar ──────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-5 flex flex-col lg:flex-row lg:flex-wrap lg:items-center gap-3">

    {{-- Date range --}}
    <div class="flex flex-wrap items-center gap-2">
        <label class="text-sm font-medium text-gray-600 whitespace-nowrap">{{ __('common.from') }}</label>
        <input type="date" id="filter-from" class="form-input py-1.5 text-sm w-full xs:w-36 sm:w-36" dir="ltr" />
        <label class="text-sm font-medium text-gray-600">{{ __('common.to') }}</label>
        <input type="date" id="filter-to" class="form-input py-1.5 text-sm w-full xs:w-36 sm:w-36" dir="ltr" />
        <button id="btn-apply" type="button" class="btn btn-primary btn-sm w-full xs:w-auto sm:w-auto">{{ __('admin.finance.apply') }}</button>
    </div>

    <div class="hidden lg:block h-5 border-l border-gray-200"></div>

    {{-- Quick periods --}}
    <div class="inline-flex flex-wrap rounded-lg shadow-sm border border-gray-300 overflow-hidden w-full sm:w-auto" role="group">
        @foreach ([
            'this_month'  => __('admin.finance.this_month'),
            'last_month'  => __('admin.finance.last_month'),
            'this_quarter'=> __('admin.finance.this_quarter'),
            'this_year'   => __('admin.finance.this_year'),
        ] as $key => $label)
            <button type="button" data-period="{{ $key }}"
                class="period-btn flex-1 sm:flex-none px-3 py-1.5 text-sm font-medium border-r border-gray-300 last:border-r-0 transition-colors
                       {{ $key === 'this_month' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="hidden lg:block h-5 border-l border-gray-200"></div>

    {{-- Include unlaunched toggle --}}
    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
        <input type="checkbox" id="filter-include-unlaunched" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
        {{ __('admin.finance.include_unlaunched_countries') }}
    </label>
</div>

{{-- ─── View toggle tabs ─────────────────────────────────────────────────────── --}}
<div class="flex items-center gap-1 mb-4">
    <button id="tab-native" type="button"
        class="view-tab px-4 py-2 rounded-lg text-sm font-medium bg-primary-600 text-white transition-colors">
        {{ __('admin.finance.native_currencies') }}
    </button>
    <button id="tab-usd" type="button"
        class="view-tab px-4 py-2 rounded-lg text-sm font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
        {{ __('admin.finance.consolidated_usd_estimate') }}
    </button>
</div>

{{-- USD caveat banner (hidden until USD view active) --}}
<div id="usd-caveat" class="hidden mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd"
            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
            clip-rule="evenodd"/>
    </svg>
    <span><strong>{{ __('admin.finance.usd_estimate_banner_title') }}</strong> {{ __('admin.finance.usd_estimate_banner_text') }}</span>
</div>

{{-- ─── Main table ───────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div id="table-loading" class="flex items-center justify-center py-16 text-gray-400 gap-2">
        <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        {{ __('common.loading') }}
    </div>

    <div id="table-wrap" class="hidden overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-4 py-3 text-start font-semibold text-gray-600 whitespace-nowrap">{{ __('admin.finance.country') }}</th>
                    <th class="px-4 py-3 text-end font-semibold text-gray-600 whitespace-nowrap">{{ __('admin.finance.orders') }}</th>
                    <th class="px-4 py-3 text-end font-semibold text-gray-600 whitespace-nowrap">{{ __('admin.finance.revenue') }}</th>
                    <th class="px-4 py-3 text-end font-semibold text-gray-600 whitespace-nowrap">{{ __('admin.finance.commission') }}</th>
                    <th class="px-4 py-3 text-end font-semibold text-gray-600 whitespace-nowrap" title="{{ __('admin.finance.gateway_fees_tooltip') }}">{{ __('admin.finance.gateway_fees') }}</th>
                    <th class="px-4 py-3 text-end font-semibold text-gray-600 whitespace-nowrap">{{ __('admin.finance.vat_collected') }}</th>
                    <th class="px-4 py-3 text-end font-semibold text-gray-600 whitespace-nowrap">{{ __('admin.finance.marketer_payouts_col') }}</th>
                    <th class="px-4 py-3 text-end font-semibold text-gray-600 whitespace-nowrap">{{ __('admin.finance.ad_revenue') }}</th>
                    <th id="col-usd-header" class="hidden px-4 py-3 text-end font-semibold text-blue-700 whitespace-nowrap bg-blue-50">{{ __('admin.finance.revenue_usd_est') }}</th>
                </tr>
            </thead>
            <tbody id="report-tbody" class="divide-y divide-gray-100"></tbody>
            <tfoot id="report-tfoot" class="hidden border-t-2 border-gray-300 bg-gray-50">
                <tr>
                    <td class="px-4 py-3 font-bold text-gray-800" colspan="7">{{ __('admin.finance.total_usd_estimate_only') }}</td>
                    <td id="total-usd-cell" class="px-4 py-3 text-end font-bold text-blue-700 bg-blue-50"></td>
                </tr>
            </tfoot>
        </table>

        <div id="table-empty" class="hidden py-12 text-center text-sm text-gray-400">
            {{ __('admin.finance.no_data_for_period') }}
        </div>
    </div>
</div>

{{-- ─── Exceptional Zone Subsidies panel ───────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">{{ __('admin.finance.exceptional_zone_subsidies') }}</h2>
        <span class="text-xs text-gray-400">{{ __('admin.finance.exceptional_zone_subsidies_desc') }}</span>
    </div>

    <div id="exceptional-zone-empty" class="hidden py-10 text-center text-sm text-gray-400">
        {{ __('admin.finance.no_data_for_period') }}
    </div>

    <div id="exceptional-zone-content">
        <div id="exceptional-currency-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4"></div>

        <div class="px-4 pb-2">
            <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ __('admin.finance.top_zones_by_admin_cost') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-2.5 text-start font-semibold text-gray-600">{{ __('admin.finance.zone') }}</th>
                        <th class="px-4 py-2.5 text-end font-semibold text-gray-600">{{ __('admin.finance.orders') }}</th>
                        <th class="px-4 py-2.5 text-end font-semibold text-gray-600">{{ __('admin.finance.total_gap') }}</th>
                        <th class="px-4 py-2.5 text-end font-semibold text-gray-600">{{ __('admin.finance.admin_absorbed') }}</th>
                        <th class="px-4 py-2.5 text-end font-semibold text-gray-600">{{ __('admin.finance.vendor_contributed') }}</th>
                    </tr>
                </thead>
                <tbody id="exceptional-zone-tbody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
    </div>
</div>

{{-- ─── Exchange Rates panel ─────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">{{ __('admin.finance.exchange_rates') }}</h2>
        <a href="{{ route('admin.currencies.index') }}"
            class="text-xs text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1">
            {{ __('admin.finance.manage_currencies') }}
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
            </svg>
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-4 py-2.5 text-start font-semibold text-gray-600">{{ __('admin.finance.exchange_rate_currency') }}</th>
                    <th class="px-4 py-2.5 text-end font-semibold text-gray-600">{{ __('admin.finance.exchange_rate_rate') }}</th>
                    <th class="px-4 py-2.5 text-start font-semibold text-gray-600">{{ __('admin.finance.exchange_rate_override') }}</th>
                    <th class="px-4 py-2.5 text-start font-semibold text-gray-600">{{ __('admin.finance.exchange_rate_last_updated') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($currencies as $currency)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-medium text-gray-900">
                            {{ $currency->code }}
                            <span class="ml-1 text-gray-400 font-normal">{{ $currency->name }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-end font-mono text-gray-800" dir="ltr">
                            {{ number_format($currency->exchange_rate_to_base, 6) }}
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($currency->is_manually_overridden)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ __('admin.finance.exchange_rate_manual') }}</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('admin.finance.exchange_rate_auto') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs">
                            {{ $currency->rate_updated_at ? $currency->rate_updated_at->diffForHumans() : '—' }}
                            @if ($currency->rate_updated_at)
                                <span class="ml-1 text-gray-400">({{ $currency->rate_updated_at->format('Y-m-d H:i') }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-end">
                            <a href="{{ route('admin.currencies.edit', $currency->code) }}"
                                class="text-xs text-primary-600 hover:text-primary-700 font-medium">{{ __('admin.finance.edit_rate') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    financeUnlaunched: @json(__('admin.finance.unlaunched')),
    financeFailedToLoadData: @json(__('admin.finance.failed_to_load_data')),
    financeExceptionalOrders: @json(__('admin.finance.orders')),
    financeTotalGap: @json(__('admin.finance.total_gap')),
    financeAdminAbsorbed: @json(__('admin.finance.admin_absorbed')),
    financeVendorContributed: @json(__('admin.finance.vendor_contributed')),
    financeNoData: @json(__('admin.finance.no_data_for_period')),
});

(function () {
    // ── State ─────────────────────────────────────────────────────────────────
    let currentView = 'native'; // 'native' | 'usd'
    let currentRows = [];
    let totalUsd    = 0;

    // ── DOM refs ──────────────────────────────────────────────────────────────
    const filterFrom       = document.getElementById('filter-from');
    const filterTo         = document.getElementById('filter-to');
    const filterUnlaunched = document.getElementById('filter-include-unlaunched');
    const btnApply         = document.getElementById('btn-apply');
    const btnExport        = document.getElementById('btn-export');
    const tableLoading     = document.getElementById('table-loading');
    const tableWrap        = document.getElementById('table-wrap');
    const tableEmpty       = document.getElementById('table-empty');
    const tbody            = document.getElementById('report-tbody');
    const tfoot            = document.getElementById('report-tfoot');
    const totalUsdCell     = document.getElementById('total-usd-cell');
    const colUsdHeader     = document.getElementById('col-usd-header');
    const usdCaveat        = document.getElementById('usd-caveat');
    const tabNative        = document.getElementById('tab-native');
    const tabUsd           = document.getElementById('tab-usd');

    // ── Init dates to this month ───────────────────────────────────────────
    const now = new Date();
    filterFrom.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    filterTo.value   = now.toISOString().slice(0, 10);

    // ── Period quick-selects ───────────────────────────────────────────────
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.period-btn').forEach(b =>
                b.classList.replace('bg-primary-600', 'bg-white') ||
                b.classList.replace('text-white', 'text-gray-700'));
            btn.classList.replace('bg-white', 'bg-primary-600');
            btn.classList.replace('text-gray-700', 'text-white');

            const today = new Date();
            let from, to = today;

            switch (btn.dataset.period) {
                case 'this_month':
                    from = new Date(today.getFullYear(), today.getMonth(), 1);
                    break;
                case 'last_month':
                    from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    to   = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                case 'this_quarter': {
                    const q = Math.floor(today.getMonth() / 3);
                    from = new Date(today.getFullYear(), q * 3, 1);
                    break;
                }
                case 'this_year':
                    from = new Date(today.getFullYear(), 0, 1);
                    break;
            }

            filterFrom.value = from.toISOString().slice(0, 10);
            filterTo.value   = to.toISOString().slice(0, 10);
            load();
        });
    });

    // ── View tabs ──────────────────────────────────────────────────────────
    tabNative.addEventListener('click', () => setView('native'));
    tabUsd.addEventListener('click',    () => setView('usd'));

    function setView(view) {
        currentView = view;

        tabNative.className = view === 'native'
            ? 'view-tab px-4 py-2 rounded-lg text-sm font-medium bg-primary-600 text-white transition-colors'
            : 'view-tab px-4 py-2 rounded-lg text-sm font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors';
        tabUsd.className = view === 'usd'
            ? 'view-tab px-4 py-2 rounded-lg text-sm font-medium bg-primary-600 text-white transition-colors'
            : 'view-tab px-4 py-2 rounded-lg text-sm font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors';

        colUsdHeader.classList.toggle('hidden', view !== 'usd');
        usdCaveat.classList.toggle('hidden', view !== 'usd');
        tfoot.classList.toggle('hidden', view !== 'usd');

        render();
    }

    // ── Load ───────────────────────────────────────────────────────────────
    btnApply.addEventListener('click', load);
    filterUnlaunched.addEventListener('change', load);

    function load() {
        tableLoading.classList.remove('hidden');
        tableWrap.classList.add('hidden');

        const params = new URLSearchParams({
            from: filterFrom.value,
            to:   filterTo.value,
            include_unlaunched: filterUnlaunched.checked ? '1' : '0',
        });

        fetch(`{{ route('admin.reports.financial.data') }}?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(json => {
                currentRows = json.rows || [];
                totalUsd    = json.total_usd || 0;
                tableLoading.classList.add('hidden');
                tableWrap.classList.remove('hidden');
                render();
                renderExceptionalZoneSubsidies(json.exceptional_zone_subsidies || { by_currency: [], top_zones: [] });
            })
            .catch(() => {
                tableLoading.classList.add('hidden');
                tableWrap.classList.remove('hidden');
                tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-8 text-center text-red-500 text-sm">${window.TRANSLATIONS.financeFailedToLoadData}</td></tr>`;
            });
    }

    // ── Exceptional Zone Subsidies ────────────────────────────────────────
    const exceptionalEmpty   = document.getElementById('exceptional-zone-empty');
    const exceptionalContent = document.getElementById('exceptional-zone-content');
    const exceptionalCards   = document.getElementById('exceptional-currency-cards');
    const exceptionalTbody   = document.getElementById('exceptional-zone-tbody');

    function renderExceptionalZoneSubsidies(data) {
        const byCurrency = data.by_currency || [];
        const topZones    = data.top_zones || [];

        if (byCurrency.length === 0) {
            exceptionalEmpty.classList.remove('hidden');
            exceptionalContent.classList.add('hidden');
            return;
        }
        exceptionalEmpty.classList.add('hidden');
        exceptionalContent.classList.remove('hidden');

        // Grouped by currency — never summed across currencies.
        exceptionalCards.innerHTML = byCurrency.map(row => `
            <div class="border border-gray-200 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-2">${row.currency_code} &middot; ${Number(row.exceptional_orders).toLocaleString()} ${window.TRANSLATIONS.financeExceptionalOrders || ''}</p>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600">${window.TRANSLATIONS.financeTotalGap || ''}</span>
                    <span class="font-medium text-gray-900">${fmt(row.total_gap)} ${row.currency_code}</span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600">${window.TRANSLATIONS.financeAdminAbsorbed || ''}</span>
                    <span class="font-medium text-green-700">${fmt(row.admin_absorbed)} ${row.currency_code}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">${window.TRANSLATIONS.financeVendorContributed || ''}</span>
                    <span class="font-medium text-orange-700">${fmt(row.vendor_contributed)} ${row.currency_code}</span>
                </div>
            </div>
        `).join('');

        exceptionalTbody.innerHTML = topZones.length
            ? topZones.map(z => `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 font-medium text-gray-900">${z.zone_name}</td>
                    <td class="px-4 py-2.5 text-end tabular-nums text-gray-700">${Number(z.exceptional_orders).toLocaleString()}</td>
                    <td class="px-4 py-2.5 text-end tabular-nums text-gray-700">${fmt(z.total_gap)} ${z.currency_code}</td>
                    <td class="px-4 py-2.5 text-end tabular-nums text-green-700">${fmt(z.admin_absorbed)} ${z.currency_code}</td>
                    <td class="px-4 py-2.5 text-end tabular-nums text-orange-700">${fmt(z.vendor_contributed)} ${z.currency_code}</td>
                </tr>
            `).join('')
            : `<tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-400">${window.TRANSLATIONS.financeNoData || ''}</td></tr>`;
    }

    // ── Render ─────────────────────────────────────────────────────────────
    function fmt(amount, decimals = 2) {
        return Number(amount).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function fmtUsd(val) {
        if (val === null || val === undefined) return '<span class="text-gray-300">—</span>';
        return '$' + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function render() {
        const showUsd = currentView === 'usd';

        if (currentRows.length === 0) {
            tbody.innerHTML = '';
            tableEmpty.classList.remove('hidden');
            tfoot.classList.add('hidden');
            return;
        }
        tableEmpty.classList.add('hidden');

        tbody.innerHTML = currentRows.map(row => {
            const launched = row.is_launched
                ? ''
                : `<span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">${window.TRANSLATIONS.financeUnlaunched}</span>`;

            const vatWarn = row.vat_discrepancy
                ? '<svg class="inline w-3.5 h-3.5 text-amber-500 ml-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>'
                : '';

            const ccy = row.currency_code;

            return `<tr class="hover:bg-gray-50">
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="text-base mr-1.5">${row.flag_emoji ?? ''}</span>
                    <span class="font-medium text-gray-900">${row.country_name}</span>
                    <span class="ml-1.5 text-xs text-gray-400">${row.iso_code_2}</span>
                    ${launched}
                </td>
                <td class="px-4 py-3 text-end tabular-nums text-gray-700">${row.order_count.toLocaleString()}</td>
                <td class="px-4 py-3 text-end tabular-nums text-gray-900 font-medium">${fmt(row.revenue)} ${ccy}</td>
                <td class="px-4 py-3 text-end tabular-nums text-gray-700">${fmt(row.commission)} ${ccy}</td>
                <td class="px-4 py-3 text-end tabular-nums text-gray-500">${fmt(row.gateway_fee)} ${ccy}</td>
                <td class="px-4 py-3 text-end tabular-nums text-gray-700">${fmt(row.vat)} ${ccy}${vatWarn}</td>
                <td class="px-4 py-3 text-end tabular-nums text-gray-700">${fmt(row.marketer)} ${ccy}</td>
                <td class="px-4 py-3 text-end tabular-nums text-gray-700">${fmt(row.ad_revenue)} ${ccy}</td>
                ${showUsd ? `<td class="px-4 py-3 text-end tabular-nums font-medium text-blue-700 bg-blue-50">${fmtUsd(row.revenue_usd)}</td>` : ''}
            </tr>`;
        }).join('');

        // Total row
        totalUsdCell.textContent = '$' + Number(totalUsd).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Ensure tfoot visibility matches view
        tfoot.classList.toggle('hidden', !showUsd);
    }

    // ── Export ─────────────────────────────────────────────────────────────
    function exportReport(format) {
        const params = new URLSearchParams({
            from: filterFrom.value,
            to:   filterTo.value,
            include_unlaunched: filterUnlaunched.checked ? '1' : '0',
            format,
        });
        window.location.href = `{{ route('admin.reports.financial.export') }}?${params}`;
    }
    btnExport.addEventListener('click', () => exportReport('csv'));
    document.getElementById('btn-export-excel').addEventListener('click', () => exportReport('excel'));
    document.getElementById('btn-export-word').addEventListener('click', () => exportReport('word'));

    // ── Initial load ───────────────────────────────────────────────────────
    load();
})();
</script>
@endpush
