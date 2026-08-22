@extends('layouts.admin')

@section('title', __('admin.nav.dashboard'))

@push('styles')
    @vite(['resources/js/admin/dashboard.js'])
@endpush

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            gmv: @json(__('admin.dashboard.gmv')),
            commission: @json(__('admin.commission')),
            vsLastPeriod: @json(__('admin.dashboard.vs_last_period')),
            noOrdersYet: @json(__('admin.dashboard.no_recent_orders')),
            noSellersData: @json(__('admin.dashboard.no_sellers_data')),
            noLowStockItems: @json(__('admin.dashboard.no_low_stock_items')),
        });
    </script>
@endpush

@section('content')
    <div class="space-y-6">
        {{-- ── HEADER ────────────────────────────────────────────────────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.nav.dashboard') }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.dashboard.welcome_back') }}</p>
            </div>

            {{-- Country filter + Period toggle --}}
            <div class="flex flex-wrap items-center gap-2">
                <select id="dashboard-country-filter"
                    class="text-sm rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 w-48">
                    <option value="">{{ __('admin.dashboard.all_countries') }}</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}"
                            {{ $defaultCountryId == $c->id ? 'selected' : '' }}>
                            {{ $c->flag_emoji }} {{ $c->name_en }} ({{ $c->currency_code }})
                        </option>
                    @endforeach
                </select>

                <div class="inline-flex items-center rounded-lg bg-gray-100 p-1 gap-0.5">
                    <button
                        class="stat-period-btn px-3 py-1.5 text-sm font-medium rounded-md transition-all text-gray-600 hover:text-gray-900"
                        data-period="today">{{ __('admin.dashboard.today') }}</button>
                    <button
                        class="stat-period-btn px-3 py-1.5 text-sm font-medium rounded-md transition-all bg-white shadow-sm text-gray-900 active"
                        data-period="week">{{ __('admin.dashboard.this_week') }}</button>
                    <button
                        class="stat-period-btn px-3 py-1.5 text-sm font-medium rounded-md transition-all text-gray-600 hover:text-gray-900"
                        data-period="month">{{ __('admin.dashboard.this_month') }}</button>
                </div>
            </div>
        </div>

        {{-- ── ROW 1: STAT CARDS ─────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

            {{-- GMV --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500">{{ __('admin.dashboard.gmv') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 stat-value" id="stat-gmv">
                            <span class="inline-block h-8 w-28 bg-gray-200 rounded animate-pulse"></span>
                        </p>
                        <p class="mt-2 text-xs text-gray-400 stat-change" id="stat-gmv-change"></p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg inline-flex items-center justify-center flex-shrink-0 bg-primary-100 text-primary-600">
                        <x-heroicon name="chart-bar" class="w-5 h-5" />
                    </div>
                </div>
            </div>

            {{-- Orders --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500">{{ __('admin.dashboard.total_orders') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 stat-value" id="stat-orders">
                            <span class="inline-block h-8 w-16 bg-gray-200 rounded animate-pulse"></span>
                        </p>
                        <p class="mt-2 text-xs text-gray-400 stat-change" id="stat-orders-change"></p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg inline-flex items-center justify-center flex-shrink-0 bg-warning-100 text-warning-600">
                        <x-heroicon name="shopping-bag" class="w-5 h-5" />
                    </div>
                </div>
            </div>

            {{-- Revenue (commission) --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500">{{ __('admin.dashboard.commission_revenue') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 stat-value" id="stat-revenue">
                            <span class="inline-block h-8 w-28 bg-gray-200 rounded animate-pulse"></span>
                        </p>
                        <p class="mt-2 text-xs text-gray-400 stat-change" id="stat-revenue-change"></p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg inline-flex items-center justify-center flex-shrink-0 bg-success-100 text-success-600">
                        <x-heroicon name="banknotes" class="w-5 h-5" />
                    </div>
                </div>
            </div>

            {{-- Active Sellers --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500">{{ __('admin.dashboard.active_sellers') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 stat-value" id="stat-sellers">
                            <span class="inline-block h-8 w-16 bg-gray-200 rounded animate-pulse"></span>
                        </p>
                        <p class="mt-2 text-xs text-gray-400 stat-change" id="stat-sellers-change"></p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg inline-flex items-center justify-center flex-shrink-0 bg-purple-100 text-purple-600">
                        <x-heroicon name="users" class="w-5 h-5" />
                    </div>
                </div>
            </div>
        </div>

        {{-- ── ROW 2: REVENUE CHART + ORDERS DONUT ───────────────────────────── --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

            {{-- Revenue line chart (2/3) --}}
            <x-card class="xl:col-span-2" padding="none">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('admin.dashboard.revenue_chart') }}</h3>
                    <div class="inline-flex items-center rounded-lg bg-gray-100 p-0.5 gap-0.5">
                        <button
                            class="chart-range-btn px-2.5 py-1 text-xs font-medium rounded-md transition-all text-gray-600 hover:text-gray-900"
                            data-range="7">{{ __('admin.dashboard.range_7d') }}</button>
                        <button
                            class="chart-range-btn px-2.5 py-1 text-xs font-medium rounded-md transition-all bg-white shadow-sm text-gray-900 active"
                            data-range="30">{{ __('admin.dashboard.range_30d') }}</button>
                        <button
                            class="chart-range-btn px-2.5 py-1 text-xs font-medium rounded-md transition-all text-gray-600 hover:text-gray-900"
                            data-range="90">{{ __('admin.dashboard.range_90d') }}</button>
                    </div>
                </div>
                <div class="p-5">
                    <div class="relative h-64">
                        <canvas id="revenue-chart"></canvas>
                    </div>
                </div>
            </x-card>

            {{-- Orders by status donut (1/3) --}}
            <x-card padding="none">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('admin.dashboard.orders_by_status') }}</h3>
                </div>
                <div class="p-5">
                    <div class="relative h-52 flex items-center justify-center">
                        <canvas id="orders-donut"></canvas>
                    </div>
                    <ul id="donut-legend" class="mt-3 space-y-1.5 text-xs text-gray-700"></ul>
                </div>
            </x-card>
        </div>

        {{-- ── ROW 3: RECENT ORDERS + PENDING APPROVALS ──────────────────────── --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

            {{-- Recent orders (2/3) --}}
            <x-card class="xl:col-span-2" padding="none">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('admin.dashboard.recent_orders') }}</h3>
                    <a href="{{ route('admin.orders.index') }}"
                        class="text-sm text-primary-600 hover:text-primary-700 font-medium">{{ __('admin.dashboard.view_all_orders') }} →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.orders.order_number') }}</th>
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.orders.customer') }}</th>
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('common.amount') }}</th>
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('common.status') }}</th>
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.dashboard.time') }}</th>
                            </tr>
                        </thead>
                        <tbody id="recent-orders-tbody" class="divide-y divide-gray-100">
                            {{-- Skeleton rows --}}
                            @for ($i = 0; $i < 5; $i++)
                                <tr>
                                    <td class="px-5 py-3">
                                        <div class="h-4 w-20 bg-gray-200 rounded animate-pulse"></div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="h-4 w-28 bg-gray-200 rounded animate-pulse"></div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="h-4 w-20 bg-gray-200 rounded animate-pulse"></div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="h-5 w-16 bg-gray-200 rounded-full animate-pulse"></div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="h-4 w-16 bg-gray-200 rounded animate-pulse"></div>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- Pending approvals (1/3) --}}
            <x-card padding="none">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('admin.dashboard.pending_approvals') }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.dashboard.items_needing_attention') }}</p>
                </div>
                <ul id="pending-list" class="divide-y divide-gray-100">
                    <li class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-warning-100 flex items-center justify-center">
                                <x-heroicon name="cube" class="w-4 h-4 text-warning-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ __('admin.nav.products') }}</p>
                                <p class="text-xs text-gray-500">{{ __('admin.dashboard.pending_review') }}</p>
                            </div>
                        </div>
                        <span class="pending-badge text-sm font-bold text-gray-900" id="pending-products">—</span>
                    </li>
                    <li class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center">
                                <x-heroicon name="building-storefront" class="w-4 h-4 text-primary-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ __('admin.nav.vendors') }}</p>
                                <p class="text-xs text-gray-500">{{ __('admin.dashboard.pending_approval') }}</p>
                            </div>
                        </div>
                        <span class="pending-badge text-sm font-bold text-gray-900" id="pending-vendors">—</span>
                    </li>
                    <li class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-danger-100 flex items-center justify-center">
                                <x-heroicon name="shield-exclamation" class="w-4 h-4 text-danger-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ __('admin.nav.disputes') }}</p>
                                <p class="text-xs text-gray-500">{{ __('admin.dashboard.open_cases') }}</p>
                            </div>
                        </div>
                        <span class="pending-badge text-sm font-bold text-gray-900" id="pending-disputes">—</span>
                    </li>
                    <li class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-success-100 flex items-center justify-center">
                                <x-heroicon name="banknotes" class="w-4 h-4 text-success-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ __('admin.dashboard.withdrawals') }}</p>
                                <p class="text-xs text-gray-500">{{ __('admin.dashboard.awaiting_payout') }}</p>
                            </div>
                        </div>
                        <span class="pending-badge text-sm font-bold text-gray-900" id="pending-withdrawals">—</span>
                    </li>
                    <li class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                                <x-heroicon name="arrow-uturn-left" class="w-4 h-4 text-gray-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ __('admin.dashboard.returns') }}</p>
                                <p class="text-xs text-gray-500">{{ __('admin.dashboard.pending_action') }}</p>
                            </div>
                        </div>
                        <span class="pending-badge text-sm font-bold text-gray-900" id="pending-returns">—</span>
                    </li>
                </ul>
            </x-card>
        </div>

        {{-- ── ROW 4: TOP SELLERS + LOW STOCK ALERTS ─────────────────────────── --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

            {{-- Top sellers --}}
            <x-card padding="none">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ __('admin.dashboard.top_sellers') }}</h3>
                        <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.dashboard.last_30_days_gmv') }}</p>
                    </div>
                    <a href="{{ route('admin.vendors.index') }}"
                        class="text-sm text-primary-600 hover:text-primary-700 font-medium">{{ __('admin.dashboard.view_all_vendors') }} →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    #</th>
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.dashboard.seller') }}</th>
                                <th
                                    class="px-5 py-3 text-end text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.dashboard.gmv') }}</th>
                                <th
                                    class="px-5 py-3 text-end text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.nav.orders') }}</th>
                            </tr>
                        </thead>
                        <tbody id="top-sellers-tbody" class="divide-y divide-gray-100">
                            @for ($i = 0; $i < 5; $i++)
                                <tr>
                                    <td class="px-5 py-3">
                                        <div class="h-4 w-4 bg-gray-200 rounded animate-pulse"></div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="h-4 w-32 bg-gray-200 rounded animate-pulse"></div>
                                    </td>
                                    <td class="px-5 py-3 text-end">
                                        <div class="h-4 w-20 bg-gray-200 rounded animate-pulse ml-auto"></div>
                                    </td>
                                    <td class="px-5 py-3 text-end">
                                        <div class="h-4 w-8 bg-gray-200 rounded animate-pulse ml-auto"></div>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- Low stock alerts --}}
            <x-card padding="none">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ __('admin.dashboard.low_stock_alerts') }}</h3>
                        <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.dashboard.low_stock_subtitle') }}</p>
                    </div>
                    <a href="{{ route('admin.products.index') }}"
                        class="text-sm text-primary-600 hover:text-primary-700 font-medium">{{ __('admin.dashboard.view_all_products') }} →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.dashboard.low_stock_product') }}</th>
                                <th
                                    class="px-5 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.dashboard.seller') }}</th>
                                <th
                                    class="px-5 py-3 text-end text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ __('admin.dashboard.current_stock') }}</th>
                            </tr>
                        </thead>
                        <tbody id="low-stock-tbody" class="divide-y divide-gray-100">
                            @for ($i = 0; $i < 5; $i++)
                                <tr>
                                    <td class="px-5 py-3">
                                        <div class="h-4 w-36 bg-gray-200 rounded animate-pulse"></div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="h-4 w-24 bg-gray-200 rounded animate-pulse"></div>
                                    </td>
                                    <td class="px-5 py-3 text-end">
                                        <div class="h-5 w-10 bg-gray-200 rounded-full animate-pulse ml-auto"></div>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

    </div>
@endsection