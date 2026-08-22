@extends('layouts.partner')
@section('title', __('partner.warehouses.page_title'))
@section('page-title', __('partner.warehouses.page_title_full'))

@push('scripts')
    @vite('resources/js/partner/warehouses.js')
@endpush

@section('content')

    {{-- My seller-owned warehouses --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('partner.warehouses.my_warehouses') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.warehouses.seller_owned_desc') }}</p>
        </div>
        <a href="{{ route('partner.warehouses.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ __('partner.warehouses.register_warehouse') }}
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-8">
        <div class="p-4 border-b border-gray-100">
            <input type="text" id="seller-warehouses-search" placeholder="{{ __('partner.warehouses.search_placeholder') }}"
                   class="w-full max-w-xs rounded-lg border-gray-200 text-sm focus:border-primary-400 focus:ring-primary-400" />
        </div>
        <div class="overflow-x-auto">
            <table id="seller-warehouses-table" class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">{{ __('partner.warehouses.col_code') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('partner.warehouses.col_name') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('partner.warehouses.col_country') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.warehouses.total_units') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.warehouses.skus_stored') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('partner.warehouses.col_status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.warehouses.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
            <span id="seller-warehouses-table-info" class="text-xs text-gray-500"></span>
            <div id="seller-warehouses-table-pagination" class="flex gap-1"></div>
        </div>
    </div>

    <div class="mb-8">
        <a href="{{ route('partner.warehouses.transfers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 bg-white text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
            {{ __('partner.warehouses.view_all_transfers') }}
        </a>
    </div>

    {{-- Platform FBN warehouses (read-only) --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('partner.warehouses.platform_warehouses_title') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ __('partner.warehouses.platform_warehouses_subtitle') }}</p>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100">
            <input type="text" id="fbn-warehouses-search" placeholder="{{ __('partner.warehouses.search_placeholder') }}"
                   class="w-full max-w-xs rounded-lg border-gray-200 text-sm focus:border-primary-400 focus:ring-primary-400" />
        </div>
        <div class="overflow-x-auto">
            <table id="fbn-warehouses-table" class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">{{ __('partner.warehouses.col_name') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('partner.warehouses.col_country') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.warehouses.col_my_units') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.warehouses.col_my_skus') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.warehouses.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
            <span id="fbn-warehouses-table-info" class="text-xs text-gray-500"></span>
            <div id="fbn-warehouses-table-pagination" class="flex gap-1"></div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.WAREHOUSES_INDEX_CFG = {
        sellerDtUrl: '{{ route('partner.warehouses.dt.seller') }}',
        fbnDtUrl: '{{ route('partner.warehouses.dt.fbn') }}',
    };
</script>
@endpush
