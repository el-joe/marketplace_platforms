@extends('layouts.partner')

@section('title', __('partner.dashboard.title'))
@section('page-title', __('partner.dashboard.title'))

@push('scripts')
    @vite('resources/js/partner/dashboard.js')
    @php
        $data = collect(range(6, 0))->mapWithKeys(fn($i) => [now()->subDays($i)->format('Y-m-d') => 0])
            ->merge($stats['revenue_chart']->pluck('total', 'date'))
            ->map(fn($v) => round($v / 100, 2));

    @endphp
    <script>
        window.DASHBOARD = {
            revenueChart: @json($data),
        };
    </script>
@endpush

@section('content')

    {{-- SLA urgent alert --}}
    @if($stats['sla_urgent'] > 0)
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-center gap-3">
            <span class="text-red-500 text-xl flex-shrink-0">⚠</span>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-red-800">{{ __('partner.dashboard.sla_urgent_orders', ['count' => $stats['sla_urgent']]) }}</p>
                <p class="text-sm text-red-600">{{ __('partner.dashboard.sla_urgent_message') }}</p>
            </div>
            @if(Route::has('partner.orders.index'))
                <a href="{{ route('partner.orders.index', ['sla_urgent' => 1]) }}"
                    class="shrink-0 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                    {{ __('partner.dashboard.view_urgent_orders') }}
                </a>
            @endif
        </div>
    @endif

    @php $currency = auth()->guard('vendor')->user()->vendor?->country?->currency_code ?? '' @endphp

    {{-- KPI Cards Row 1 --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <x-partner-stat-card title="{{ __('partner.dashboard.revenue_month') }}" :value="number_format($stats['revenue_month'], 2)" :suffix="$currency"
            icon="banknotes" color="green" />

        <x-partner-stat-card title="{{ __('partner.dashboard.orders_today') }}" :value="$stats['orders_today']" icon="shopping-bag" color="blue" />

        <x-partner-stat-card title="{{ __('partner.dashboard.pending_orders') }}" :value="$stats['pending_orders']" icon="clock"
            :color="$stats['pending_orders'] > 0 ? 'warning' : 'gray'" :link="Route::has('partner.orders.index') ? route('partner.orders.index', ['status' => 'placed']) : null" />

        <x-partner-stat-card title="{{ __('partner.dashboard.pending_payout') }}" :value="number_format($stats['pending_payout'], 2)" :suffix="$currency"
            icon="credit-card" color="primary" />
    </div>

    {{-- KPI Cards Row 2 --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-partner-stat-card title="{{ __('partner.dashboard.low_stock_products') }}" :value="$stats['low_stock']" icon="exclamation-triangle"
            :color="$stats['low_stock'] > 0 ? 'warning' : 'success'" :link="Route::has('partner.inventory.low-stock') ? route('partner.inventory.low-stock') : null" />

        <x-partner-stat-card title="{{ __('partner.dashboard.open_disputes') }}" :value="$stats['open_disputes']" icon="scale"
            :color="$stats['open_disputes'] > 0 ? 'danger' : 'gray'" />

        <x-partner-stat-card title="{{ __('partner.dashboard.store_rating') }}" :value="number_format($stats['rating_avg'], 1)" suffix="/ 5 ⭐" icon="star"
            color="yellow" />

        <x-partner-stat-card title="{{ __('partner.dashboard.active_strikes') }}" :value="$stats['active_strikes']" icon="exclamation-circle"
            :color="$stats['active_strikes'] > 0 ? 'danger' : 'success'" />
    </div>

    {{-- Charts + Quick Actions --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Revenue chart (2/3) --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-800">{{ __('partner.dashboard.revenue_last_7_days') }}</h3>
                <span class="text-xs text-gray-400">{{ $currency }}</span>
            </div>
            <canvas id="revenue-chart" height="200"></canvas>
        </div>

        {{-- Quick actions (1/3) --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('partner.dashboard.quick_actions') }}</h3>
            <div class="space-y-2">
                @if(Route::has('partner.orders.index'))
                    <a href="{{ route('partner.orders.index', ['status' => 'placed']) }}"
                        class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center shrink-0">
                            <x-heroicon name="shopping-bag" class="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ __('partner.dashboard.process_orders') }}</p>
                            <p class="text-xs text-gray-500">{{ __('partner.dashboard.new_orders_count', ['count' => $stats['pending_orders']]) }}</p>
                        </div>
                    </a>
                @endif
                @if(Route::has('partner.listings.create'))
                    <a href="{{ route('partner.listings.create') }}"
                        class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center shrink-0">
                            <x-heroicon name="plus" class="w-5 h-5 text-green-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ __('partner.dashboard.add_product') }}</p>
                            <p class="text-xs text-gray-500">{{ __('partner.dashboard.new_listing') }}</p>
                        </div>
                    </a>
                @endif
                @if(Route::has('partner.inventory.index'))
                    <a href="{{ route('partner.inventory.index') }}"
                        class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 bg-yellow-100 rounded-xl flex items-center justify-center shrink-0">
                            <x-heroicon name="cube" class="w-5 h-5 text-yellow-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ __('partner.dashboard.update_inventory') }}</p>
                            <p class="text-xs text-gray-500">{{ __('partner.dashboard.low_stock_count', ['count' => $stats['low_stock']]) }}</p>
                        </div>
                    </a>
                @endif
                @if(Route::has('partner.payouts.index'))
                    <a href="{{ route('partner.payouts.index') }}"
                        class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center shrink-0">
                            <x-heroicon name="banknotes" class="w-5 h-5 text-purple-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ __('partner.dashboard.pending_payout') }}</p>
                            <p class="text-xs text-gray-500">{{ number_format($stats['pending_payout'], 2) }} {{ $currency }}
                                {{ __('partner.dashboard.pending_amount') }}</p>
                        </div>
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Recent orders --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">{{ __('partner.dashboard.recent_orders') }}</h3>
            @if(Route::has('partner.orders.index'))
                <a href="{{ route('partner.orders.index') }}" class="text-sm text-primary-600 hover:underline">
                    {{ __('partner.dashboard.view_all') }}
                </a>
            @endif
        </div>

        @if($stats['recent_orders']->isEmpty())
            <div class="text-center py-8 text-gray-400 text-sm">{{ __('partner.dashboard.no_orders_yet') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-xs text-gray-500 uppercase">
                            <th class="text-right pb-2 font-medium">{{ __('partner.dashboard.order_number') }}</th>
                            <th class="text-right pb-2 font-medium">{{ __('common.status') }}</th>
                            <th class="text-right pb-2 font-medium">{{ __('common.amount') }}</th>
                            <th class="text-right pb-2 font-medium">{{ __('partner.dashboard.ship_deadline') }}</th>
                            <th class="text-right pb-2 font-medium">{{ __('common.date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($stats['recent_orders'] as $subOrder)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3">
                                    @if(Route::has('partner.orders.show'))
                                        <a href="{{ route('partner.orders.show', $subOrder->sub_order_number) }}"
                                            class="text-primary-600 hover:underline font-mono text-xs">
                                            {{ $subOrder->sub_order_number }}
                                        </a>
                                    @else
                                        <span class="font-mono text-xs text-gray-600">{{ $subOrder->sub_order_number }}</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <x-status-badge :status="$subOrder->status->value" />
                                </td>
                                <td class="py-3 font-medium text-gray-800">
                                    {{ number_format($subOrder->vendor_payout / 100, 2) }}
                                    <span class="text-xs text-gray-400">{{ $currency }}</span>
                                </td>
                                <td class="py-3">
                                    @if($subOrder->sla_ship_deadline)
                                        <span @class([
                                            'text-xs',
                                            'text-red-600 font-semibold' => now()->gt($subOrder->sla_ship_deadline),
                                            'text-orange-500 font-medium' => !now()->gt($subOrder->sla_ship_deadline) && now()->addHours(2)->gt($subOrder->sla_ship_deadline),
                                            'text-gray-500' => !now()->addHours(2)->gt($subOrder->sla_ship_deadline),
                                        ])>
                                            {{ $subOrder->sla_ship_deadline->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="py-3 text-gray-400 text-xs">
                                    {{ $subOrder->created_at->format('M d, H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection