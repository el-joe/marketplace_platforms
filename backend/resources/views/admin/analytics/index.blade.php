@extends('layouts.admin')

@push('styles')
    @vite('resources/js/admin/analytics.js')
@endpush

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            vsPrevPeriod: @json(__('admin.analytics.vs_prev_period')),
            gmvLabel: @json(__('admin.analytics.gmv_col')),
            platformRevenue: @json(__('admin.analytics.platform_revenue')),
            revenueLabel: @json(__('admin.analytics.revenue')),
            vendorPayouts: @json(__('admin.analytics.vendor_payouts')),
            refunds: @json(__('admin.analytics.refunds')),
            ordersLabel: @json(__('admin.analytics.orders')),
            newCustomersLabel: @json(__('admin.analytics.new_customers')),
            breachRatePct: @json(__('admin.analytics.breach_rate_pct')),
            spendLabel: @json(__('admin.analytics.spend')),
            ticketsLabel: @json(__('admin.analytics.tickets')),
            returnsLabel: @json(__('admin.analytics.tab_returns')),
            createdLabel: @json(__('admin.analytics.created')),
            resolvedLabel: @json(__('admin.analytics.resolved')),
            usdEquiv: @json(__('admin.analytics.usd_equiv')),
            equivOf: @json(__('admin.analytics.equiv_of')),
            noData: @json(__('admin.no_data')),
        });
    </script>
@endpush

@section('title', __('admin.analytics.title'))

@section('content')

    {{-- ─── Page Header ──────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.analytics.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.analytics.subtitle') }}</p>
        </div>

        {{-- ─── Filters ─────────────────────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-center gap-2">

            {{-- Country filter --}}
            <select id="filter-country" class="form-input py-1.5 text-sm w-36">
                <option value="">{{ __('admin.analytics.all_countries') }}</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name_en }} ({{ $country->iso_code_2 }})</option>
                @endforeach
            </select>

            {{-- Period tabs --}}
            <div class="inline-flex rounded-lg shadow-sm border border-gray-300 overflow-hidden" role="group" id="period-tabs">
                @foreach ([
                    'today'   => __('admin.analytics.today'),
                    'week'    => '7D',
                    'month'   => '30D',
                    'quarter' => '90D',
                    'year'    => '1Y',
                    'custom'  => __('admin.analytics.custom'),
                ] as $key => $label)
                    <button
                        type="button"
                        data-period="{{ $key }}"
                        class="period-btn px-3 py-1.5 text-sm font-medium border-r border-gray-300 last:border-r-0 transition-colors
                               {{ $key === 'month' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Custom date range (hidden by default) --}}
            <div id="custom-date-range" class="hidden flex items-center gap-1">
                <input type="date" id="date-from" class="form-input py-1.5 text-sm w-36" />
                <span class="text-gray-500 text-sm">–</span>
                <input type="date" id="date-to" class="form-input py-1.5 text-sm w-36" />
                <button id="apply-custom" type="button" class="btn btn-primary btn-sm">{{ __('admin.analytics.apply') }}</button>
            </div>

            <x-export-dropdown />
        </div>
    </div>

    {{-- ─── Section 1: KPI Cards ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6" id="kpi-grid">
        <div id="kpi-gmv">
            <x-stat-card title="{{ __('admin.analytics.gmv') }}" value="—" icon="banknotes" iconBg="bg-primary-100 text-primary-600" :loading="true" />
        </div>
        <div id="kpi-revenue">
            <x-stat-card title="{{ __('admin.analytics.platform_revenue') }}" value="—" icon="chart-bar" iconBg="bg-success-100 text-success-600" :loading="true" />
        </div>
        <div id="kpi-orders">
            <x-stat-card title="{{ __('admin.analytics.total_orders') }}" value="—" icon="shopping-bag" iconBg="bg-blue-100 text-blue-600" :loading="true" />
        </div>
        <div id="kpi-aov">
            <x-stat-card title="{{ __('admin.analytics.avg_order_value') }}" value="—" icon="currency-dollar" iconBg="bg-indigo-100 text-indigo-600" :loading="true" />
        </div>
        <div id="kpi-new-customers">
            <x-stat-card title="{{ __('admin.analytics.new_customers') }}" value="—" icon="user-plus" iconBg="bg-teal-100 text-teal-600" :loading="true" />
        </div>
        <div id="kpi-active-vendors">
            <x-stat-card title="{{ __('admin.analytics.active_vendors') }}" value="—" icon="building-storefront" iconBg="bg-orange-100 text-orange-600" :loading="true" />
        </div>
        <div id="kpi-sla">
            <x-stat-card title="{{ __('admin.analytics.sla_compliance') }}" value="—" icon="check-badge" iconBg="bg-green-100 text-green-600" :loading="true" />
        </div>
        <div id="kpi-return-rate">
            <x-stat-card title="{{ __('admin.analytics.return_rate') }}" value="—" icon="arrow-uturn-left" iconBg="bg-red-100 text-red-600" :loading="true" />
        </div>
    </div>

    {{-- ─── Section 2: Revenue Chart ─────────────────────────────────────────────── --}}
    <x-card class="mb-6">
        <x-slot:title>{{ __('admin.analytics.revenue_overview') }}</x-slot:title>
        <x-slot:actions>
            <div class="inline-flex rounded-lg shadow-sm border border-gray-300 overflow-hidden" id="revenue-range-tabs">
                @foreach (['week' => '7D', 'month' => '30D', 'quarter' => '90D', 'year' => '1Y'] as $k => $l)
                    <button type="button" data-range="{{ $k }}"
                        class="rev-range-btn px-3 py-1 text-xs font-medium border-r border-gray-300 last:border-r-0 transition-colors
                               {{ $k === 'month' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ $l }}
                    </button>
                @endforeach
            </div>
        </x-slot:actions>
        <div class="p-6">
            <canvas id="revenue-chart" height="100"></canvas>
        </div>
    </x-card>

    {{-- ─── Section 3: Three Side-by-Side Charts ─────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Orders by Status --}}
        <x-card>
            <x-slot:title>{{ __('admin.analytics.orders_by_status') }}</x-slot:title>
            <div class="p-6">
                <canvas id="status-chart" height="220"></canvas>
            </div>
        </x-card>

        {{-- Payment Methods --}}
        <x-card>
            <x-slot:title>{{ __('admin.analytics.payment_methods') }}</x-slot:title>
            <div class="p-6">
                <canvas id="payment-chart" height="220"></canvas>
            </div>
        </x-card>

        {{-- Top Categories --}}
        <x-card>
            <x-slot:title>{{ __('admin.analytics.revenue_by_category') }}</x-slot:title>
            <div class="p-6">
                <canvas id="category-chart" height="220"></canvas>
            </div>
        </x-card>
    </div>

    {{-- ─── Section 4: Top Products & Vendors ────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Top Products --}}
        <x-card>
            <x-slot:title>{{ __('admin.analytics.top_products') }}</x-slot:title>
            <x-slot:subtitle>{{ __('admin.analytics.top_products_subtitle') }}</x-slot:subtitle>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">#</th>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('admin.analytics.product') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-gray-500">{{ __('admin.analytics.units') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-gray-500">{{ __('admin.analytics.revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody id="top-products-body" class="divide-y divide-gray-100">
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">
                            <div class="h-4 bg-gray-200 rounded animate-pulse w-full"></div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Top Vendors --}}
        <x-card>
            <x-slot:title>{{ __('admin.analytics.top_vendors') }}</x-slot:title>
            <x-slot:subtitle>{{ __('admin.analytics.top_vendors_subtitle') }}</x-slot:subtitle>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">#</th>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('admin.analytics.store') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-gray-500">{{ __('admin.analytics.orders') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-gray-500">{{ __('admin.analytics.gmv_col') }}</th>
                        </tr>
                    </thead>
                    <tbody id="top-vendors-body" class="divide-y divide-gray-100">
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">
                            <div class="h-4 bg-gray-200 rounded animate-pulse w-full"></div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ─── Section 5: Customers & Search ────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Customer Acquisition --}}
        <x-card>
            <x-slot:title>{{ __('admin.analytics.customer_acquisition') }}</x-slot:title>
            <div class="p-6 space-y-4">
                <canvas id="customer-acq-chart" height="160"></canvas>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500">{{ __('admin.analytics.new_buyers') }}</p>
                        <p id="new-buyers-count" class="text-2xl font-bold text-gray-900">—</p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500">{{ __('admin.analytics.returning_buyers') }}</p>
                        <p id="returning-buyers-count" class="text-2xl font-bold text-gray-900">—</p>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Search Analytics --}}
        <x-card>
            <x-slot:title>{{ __('admin.analytics.search_analytics') }}</x-slot:title>
            <div class="p-4 grid grid-cols-3 gap-3 border-b border-gray-100">
                <div class="text-center">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.total_searches') }}</p>
                    <p id="total-searches" class="text-xl font-bold text-gray-900">—</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.avg_ctr') }}</p>
                    <p id="search-avg-ctr" class="text-xl font-bold text-gray-900">—</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.zero_results') }}</p>
                    <p id="zero-result-rate" class="text-xl font-bold text-gray-900">—</p>
                </div>
            </div>
            <div class="overflow-x-auto max-h-64">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-500">{{ __('admin.analytics.query') }}</th>
                            <th class="px-4 py-2 text-end font-medium text-gray-500">{{ __('admin.analytics.searches') }}</th>
                            <th class="px-4 py-2 text-end font-medium text-gray-500">{{ __('admin.analytics.ctr') }}</th>
                        </tr>
                    </thead>
                    <tbody id="search-table-body" class="divide-y divide-gray-100">
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">{{ __('admin.analytics.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ─── Section 6: Operational Tabs ──────────────────────────────────────────── --}}
    <x-card>
        {{-- Tab headers --}}
        <div class="border-b border-gray-200 px-6">
            <nav class="-mb-px flex gap-1 overflow-x-auto" id="ops-tabs">
                @foreach ([
                    'sla'     => __('admin.analytics.tab_sla'),
                    'ads'     => __('admin.analytics.tab_ads'),
                    'flash'   => __('admin.analytics.tab_flash'),
                    'returns' => __('admin.analytics.tab_returns'),
                    'support' => __('admin.analytics.tab_support'),
                ] as $tab => $label)
                    <button type="button" data-tab="{{ $tab }}"
                        class="ops-tab-btn whitespace-nowrap py-3 px-4 text-sm font-medium border-b-2 transition-colors
                               {{ $tab === 'sla'
                                  ? 'border-primary-600 text-primary-600'
                                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- SLA Metrics --}}
        <div id="ops-sla" class="p-6">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6" id="sla-kpi-row">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.breach_rate') }}</p>
                    <p id="sla-breach-rate" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.compliance') }}</p>
                    <p id="sla-compliance" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.avg_ship_time') }}</p>
                    <p id="sla-avg-ship" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.avg_delivery_time') }}</p>
                    <p id="sla-avg-delivery" class="text-2xl font-bold text-gray-900">—</p>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.breach_rate_trend') }}</h4>
                    <canvas id="sla-trend-chart" height="160"></canvas>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.breach_by_vendor') }}</h4>
                    <div class="overflow-x-auto max-h-48">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500">
                                    <th class="text-start pb-2">{{ __('admin.analytics.store') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.orders') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.breached') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.rate') }}</th>
                                </tr>
                            </thead>
                            <tbody id="sla-vendor-table" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ad Performance --}}
        <div id="ops-ads" class="p-6 hidden">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.impressions') }}</p>
                    <p id="ads-impressions" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.clicks') }}</p>
                    <p id="ads-clicks" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.total_spend') }}</p>
                    <p id="ads-spend" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.attributed_revenue') }}</p>
                    <p id="ads-revenue" class="text-2xl font-bold text-gray-900">—</p>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.performance_trend') }}</h4>
                    <canvas id="ads-perf-chart" height="160"></canvas>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.top_campaigns') }}</h4>
                    <div class="overflow-x-auto max-h-48">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500">
                                    <th class="text-start pb-2">{{ __('admin.analytics.campaign') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.spend') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.revenue') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.ctr') }}</th>
                                </tr>
                            </thead>
                            <tbody id="ads-campaigns-table" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Flash Sales --}}
        <div id="ops-flash" class="p-6 hidden">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.units_sold') }}</p>
                    <p id="flash-units" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.total_revenue') }}</p>
                    <p id="flash-revenue" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.discount_given') }}</p>
                    <p id="flash-discount" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.avg_conversion') }}</p>
                    <p id="flash-cvr" class="text-2xl font-bold text-gray-900">—</p>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.top_flash_sales') }}</h4>
                    <div class="overflow-x-auto max-h-56">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500">
                                    <th class="text-start pb-2">{{ __('admin.analytics.sale') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.units') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.revenue') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.cvr') }}</th>
                                </tr>
                            </thead>
                            <tbody id="flash-sales-table" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.top_vendors_flash') }}</h4>
                    <div class="overflow-x-auto max-h-56">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500">
                                    <th class="text-start pb-2">{{ __('admin.analytics.store') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.units') }}</th>
                                    <th class="text-end pb-2">{{ __('admin.analytics.revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody id="flash-vendors-table" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Returns --}}
        <div id="ops-returns" class="p-6 hidden">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.total_returns') }}</p>
                    <p id="ret-total" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.return_rate') }}</p>
                    <p id="ret-rate" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl col-span-2 sm:col-span-1">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.most_common_reason') }}</p>
                    <p id="ret-top-reason" class="text-lg font-bold text-gray-900">—</p>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.returns_by_reason') }}</h4>
                    <canvas id="returns-reason-chart" height="180"></canvas>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.monthly_trend') }}</h4>
                    <canvas id="returns-trend-chart" height="180"></canvas>
                </div>
            </div>
        </div>

        {{-- Support --}}
        <div id="ops-support" class="p-6 hidden">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.open_tickets') }}</p>
                    <p id="sup-open" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.avg_first_response') }}</p>
                    <p id="sup-first-resp" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.avg_resolution') }}</p>
                    <p id="sup-resolution" class="text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">{{ __('admin.analytics.satisfaction_score') }}</p>
                    <p id="sup-satisfaction" class="text-2xl font-bold text-gray-900">—</p>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.tickets_by_category') }}</h4>
                    <canvas id="sup-category-chart" height="180"></canvas>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.analytics.resolution_trend') }}</h4>
                    <canvas id="sup-trend-chart" height="180"></canvas>
                </div>
            </div>
        </div>
    </x-card>

@endsection
