@extends('layouts.partner')

@section('title', __('partner.orders.title'))
@section('page-title', __('partner.orders.page_title'))

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@push('scripts')
    @vite('resources/js/partner/orders.js')
    <script>
        window.PARTNER_TRANSLATIONS = window.PARTNER_TRANSLATIONS || {};
        Object.assign(window.PARTNER_TRANSLATIONS, {
            confirmOrderConfirm: @json(__('partner.orders.confirm_order_confirm')),
            confirmOutForDelivery: @json(__('partner.orders.confirm_out_for_delivery')),
            confirmDeliveryConfirm: @json(__('partner.orders.confirm_delivery_confirm')),
        });
    </script>
@endpush

@section('content')

    {{-- Filter tabs --}}
    <div class="bg-white rounded-2xl border border-gray-200 mb-4">
        <div class="flex items-center overflow-x-auto">

            {{-- All tab --}}
            <a href="{{ route('partner.orders.index') }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                'border-yellow-400 text-yellow-600' => !request('status'),
                'border-transparent text-gray-500 hover:text-gray-700' => request('status'),
            ])>
                {{ __('common.all') }}
                <span class="mr-1.5 text-xs text-gray-400">({{ $counts->sum() }})</span>
            </a>

            {{-- SLA urgent --}}
            <a href="{{ route('partner.orders.index', ['status' => 'sla_urgent']) }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-1.5',
                'border-red-500 text-red-600' => request('status') === 'sla_urgent',
                'border-transparent text-gray-500 hover:text-gray-700' => request('status') !== 'sla_urgent',
            ])>
                ⚡ {{ __('partner.orders.sla_urgent') }}
                @if($slaUrgentCount > 0)
                    <span
                        class="bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full leading-none">{{ $slaUrgentCount }}</span>
                @endif
            </a>

            @php
                $tabMap = [
                    'placed' => __('partner.orders.status.placed'),
                    'confirmed' => __('partner.orders.status.confirmed'),
                    'processing' => __('partner.orders.status.processing'),
                    'packed' => __('partner.orders.status.packed'),
                    'shipped' => __('partner.orders.status.shipped'),
                    'out_for_delivery' => __('partner.orders.status.out_for_delivery'),
                    'delivered' => __('partner.orders.status.delivered'),
                    'completed' => __('common.completed'),
                    'cancelled' => __('common.cancelled'),
                ];
            @endphp

            @foreach($tabMap as $statusKey => $label)
                <a href="{{ route('partner.orders.index', ['status' => $statusKey]) }}" @class([
                    'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                    'border-yellow-400 text-yellow-600' => request('status') === $statusKey,
                    'border-transparent text-gray-500 hover:text-gray-700' => request('status') !== $statusKey,
                ])>
                    {{ $label }}
                    @if(($counts[$statusKey] ?? 0) > 0)
                        <span class="mr-1 text-xs text-gray-400">({{ $counts[$statusKey] }})</span>
                    @endif
                </a>
            @endforeach

        </div>
    </div>

    {{-- Date range filter --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('common.from') }}</label>
            <input type="date" id="filter-date-from" value="{{ request('date_from') }}"
                class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('common.to') }}</label>
            <input type="date" id="filter-date-to" value="{{ request('date_to') }}"
                class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
        </div>
        <button id="apply-date-filter"
            class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">
            {{ __('common.apply') }}
        </button>
        <button id="clear-date-filter"
            class="border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">
            {{ __('common.reset') }}
        </button>
        <div class="ms-auto">
            <x-export-dropdown />
        </div>
    </div>

    {{-- DataTable --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table id="orders-table" class="w-full text-sm" style="width:100%">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('partner.orders.order_number') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('common.status') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('common.amount') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('common.city') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.orders.items') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('partner.orders.ship_by') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('common.date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"></tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
            <span id="orders-table-info" class="text-xs text-gray-400"></span>
            <div id="orders-table-pagination" class="flex items-center gap-1"></div>
        </div>
    </div>

    <script>
        window.ORDERS = {
            datatableUrl: '{{ route('partner.orders.datatable') }}',
            statusFilter: '{{ request('status', 'all') }}',
            dateFrom: '{{ request('date_from', '') }}',
            dateTo: '{{ request('date_to', '') }}',
        };
    </script>

@endsection