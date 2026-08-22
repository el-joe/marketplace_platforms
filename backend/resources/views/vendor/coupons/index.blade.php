@extends('layouts.partner')

@section('title', __('partner.coupons.index.title'))
@section('page-title', __('partner.coupons.index.title'))

@push('scripts')
    @vite('resources/js/vendor/coupons.js')
    <script>
        window.COUPONS = {
            datatableUrl: '{{ route('partner.coupons.datatable') }}',
        };
    </script>
@endpush

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Page header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.coupons.index.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('partner.coupons.index.subtitle') }}</p>
            </div>
            <a href="{{ route('partner.coupons.create') }}"
               class="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                {{ __('partner.coupons.index.new_coupon') }}
            </a>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap items-center gap-3">
            <input type="text" id="coupon-search" placeholder="{{ __('partner.coupons.index.search_placeholder') }}"
                   class="flex-1 min-w-[200px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">

            <select id="coupon-type-filter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('partner.coupons.index.all_types') }}</option>
                <option value="percentage">{{ __('partner.coupons.index.type_percentage') }}</option>
                <option value="fixed_amount">{{ __('partner.coupons.index.type_fixed_amount') }}</option>
                <option value="free_shipping">{{ __('partner.coupons.index.type_free_shipping') }}</option>
                <option value="bogo">{{ __('partner.coupons.index.type_bogo') }}</option>
            </select>

            <select id="coupon-active-filter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('partner.coupons.index.all_statuses') }}</option>
                <option value="1">{{ __('partner.coupons.index.active') }}</option>
                <option value="0">{{ __('partner.coupons.index.inactive') }}</option>
            </select>
        </div>

        {{-- DataTable --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table id="coupons-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr>
                        <th>{{ __('partner.coupons.index.table_code') }}</th>
                        <th>{{ __('partner.coupons.index.table_name') }}</th>
                        <th>{{ __('partner.coupons.index.table_type') }}</th>
                        <th>{{ __('partner.coupons.index.table_scope') }}</th>
                        <th>{{ __('partner.coupons.index.table_value') }}</th>
                        <th>{{ __('partner.coupons.index.table_used') }}</th>
                        <th>{{ __('partner.coupons.index.table_status') }}</th>
                        <th>{{ __('partner.coupons.index.table_valid_until') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection
