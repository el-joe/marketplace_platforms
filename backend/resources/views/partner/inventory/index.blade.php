@extends('layouts.partner')

@section('title', __('partner.inventory.title'))
@section('page-title', __('partner.inventory.page_title'))

@push('scripts')
    @vite('resources/js/partner/inventory.js')
    <script>
        window.INVENTORY = {
            datatableUrl: '{{ route('partner.inventory.datatable') }}',
            filterParam: '{{ request('filter', '') }}',
            adjustBaseUrl: '{{ Route::has('partner.listings.adjust-stock') ? url('') : '' }}',
            csrf: '{{ csrf_token() }}',
        };
    </script>
@endpush

@section('content')

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-partner-stat-card title="{{ __('partner.inventory.total_skus') }}" :value="number_format($stats->total_skus)" icon="tag" color="blue" />

        <x-partner-stat-card title="{{ __('partner.inventory.total_units') }}" :value="number_format($stats->total_on_hand)" icon="cube"
            color="green" />

        <x-partner-stat-card title="{{ __('partner.inventory.available_for_sale') }}" :value="number_format($stats->total_available)" icon="check-circle"
            color="success" :link="route('partner.inventory.index')" />

        <x-partner-stat-card title="{{ __('partner.inventory.low_stock_products') }}" :value="number_format($lowStockCount)" icon="exclamation-triangle"
            :color="$lowStockCount > 0 ? 'warning' : 'gray'" :link="route('partner.inventory.low-stock')" />
    </div>

    {{-- Filter tabs --}}
    <div class="bg-white rounded-2xl border border-gray-200 mb-4">
        <div class="flex items-center overflow-x-auto">
            <a href="{{ route('partner.inventory.index') }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                'border-yellow-400 text-yellow-600' => !request('filter'),
                'border-transparent text-gray-500 hover:text-gray-700' => request('filter'),
            ])>{{ __('partner.inventory.tab_all') }}</a>

            <a href="{{ route('partner.inventory.index', ['filter' => 'low_stock']) }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-1',
                'border-orange-400 text-orange-600' => request('filter') === 'low_stock',
                'border-transparent text-gray-500 hover:text-gray-700' => request('filter') !== 'low_stock',
            ])>
                ⚠ {{ __('partner.inventory.tab_low_stock') }}
                @if($lowStockCount > 0)
                    <span class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full leading-none">{{ $lowStockCount }}</span>
                @endif
            </a>

            <a href="{{ route('partner.inventory.index', ['filter' => 'out_of_stock']) }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-1',
                'border-red-400 text-red-600' => request('filter') === 'out_of_stock',
                'border-transparent text-gray-500 hover:text-gray-700' => request('filter') !== 'out_of_stock',
            ])>
                🚫 {{ __('partner.inventory.tab_out_of_stock') }}
                @if($outOfStockCount > 0)
                    <span class="bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full leading-none">{{ $outOfStockCount }}</span>
                @endif
            </a>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="relative">
            <input type="text" id="inventory-search" placeholder="{{ __('partner.inventory.search_placeholder') }}"
                class="w-64 border border-gray-200 rounded-xl pr-9 pl-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
            <svg class="w-4 h-4 text-gray-400 absolute top-2.5 right-2.5 pointer-events-none" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        <x-export-dropdown />
    </div>

    {{-- DataTable --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table id="inventory-table" class="w-full text-sm" style="width:100%">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('partner.inventory.table.product') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('partner.inventory.table.variant_sku') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('partner.inventory.table.warehouse') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.inventory.table.on_hand') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.inventory.table.reserved') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.inventory.table.available') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.inventory.table.inbound') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.inventory.table.damaged') }}</th>
                    <th class="px-4 py-3 w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"></tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/50" id="inventory-table-footer">
            <span id="inventory-info" class="text-xs text-gray-400"></span>
            <div id="inventory-pagination" class="flex items-center gap-1"></div>
        </div>
    </div>

    {{-- Adjust Stock Modal --}}
    <div id="inventory-adjust-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900 text-sm">{{ __('partner.inventory.adjust_modal_title') }}</h3>
                    <p id="inv-adjust-warehouse" class="text-xs text-gray-400"></p>
                </div>
                <button id="inv-adjust-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="inv-adjust-form" class="p-5 space-y-4">
                <input type="hidden" id="inv-adjust-listing-id">
                <input type="hidden" id="inv-adjust-inv-id" name="warehouse_inventory_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.inventory.current_stock_label') }}</label>
                    <p id="inv-adjust-current" class="text-2xl font-bold text-gray-900"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.inventory.adjustment_label') }} <span
                            class="text-xs text-gray-400">{{ __('partner.inventory.adjustment_hint') }}</span></label>
                    <input type="number" name="adjustment" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                        placeholder="{{ __('partner.inventory.adjustment_placeholder') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.inventory.reason_label') }} <span
                            class="text-red-500">*</span></label>
                    <select name="reason" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        <option value="">{{ __('partner.inventory.select_reason') }}</option>
                        <option value="received_stock">{{ __('partner.inventory.reasons.received_stock') }}</option>
                        <option value="damaged_goods">{{ __('partner.inventory.reasons.damaged_goods') }}</option>
                        <option value="inventory_count">{{ __('partner.inventory.reasons.inventory_count') }}</option>
                        <option value="returned_to_vendor">{{ __('partner.inventory.reasons.returned_to_vendor') }}</option>
                        <option value="transfer">{{ __('partner.inventory.reasons.transfer') }}</option>
                        <option value="other">{{ __('partner.inventory.reasons.other') }}</option>
                    </select>
                </div>
                <div id="inv-adjust-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-gray-900 hover:bg-gray-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('partner.inventory.confirm') }}</button>
                    <button type="button" id="inv-adjust-cancel"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('partner.inventory.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection