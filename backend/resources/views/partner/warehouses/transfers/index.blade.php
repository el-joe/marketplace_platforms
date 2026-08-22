@extends('layouts.partner')
@section('title', __('partner.warehouses.transfers'))
@section('page-title', __('partner.warehouses.transfers_page_title'))

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@push('scripts')
    @vite('resources/js/partner/warehouses.js')
    <script>
        window.TRANSFERS_CFG = {
            datatableUrl: '{{ route('partner.warehouses.transfers.datatable') }}',
            createUrl:    '{{ route('partner.warehouses.transfers.create') }}',
        };
    </script>
@endpush

@section('content')

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500">{{ __('partner.warehouses.transfers_subtitle') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('partner.warehouses.index') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                {{ __('partner.warehouses.back_to_my_warehouses') }}
            </a>
            <a href="{{ route('partner.warehouses.transfers.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                {{ __('partner.warehouses.new_transfer') }}
            </a>
        </div>
    </div>

    {{-- Search --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex items-center gap-3">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
            </svg>
            <input id="transfers-search" type="text" placeholder="{{ __('partner.warehouses.search_transfer_placeholder') }}"
                class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-400/40 placeholder-gray-400">
        </div>
    </div>

    {{-- DataTable --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table id="transfers-table" class="w-full text-sm" style="width:100%">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('partner.warehouses.transfer_number') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('partner.warehouses.from') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('partner.warehouses.to') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('common.status') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('common.date') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"></tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
            <span id="transfers-table-info" class="text-xs text-gray-400"></span>
            <div id="transfers-table-pagination" class="flex items-center gap-1"></div>
        </div>
    </div>

@endsection
