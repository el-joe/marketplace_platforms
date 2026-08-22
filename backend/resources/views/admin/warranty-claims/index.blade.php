@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/warranty-claims.js'])
@endpush

@section('title', __('admin.warranty_claims_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.warranty_claims_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.warranty_claims_section.subtitle') }}</p>
        </div>
        <x-export-dropdown />
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.warranty_claims_section.submitted') }}" :value="number_format($stats['submitted'])"
            iconBg="{{ $stats['submitted'] > 0 ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.warranty_claims_section.under_review') }}" :value="number_format($stats['under_review'])"
            iconBg="bg-yellow-100 text-yellow-600" />
        <x-stat-card title="{{ __('admin.warranty_claims_section.approved') }}" :value="number_format($stats['approved'])"
            iconBg="bg-green-100 text-green-600" />
        <x-stat-card title="{{ __('admin.warranty_claims_section.resolved_30d') }}" :value="number_format($stats['resolved_30d'])"
            iconBg="bg-gray-100 text-gray-500" />
    </div>

    {{-- ─── Status Tabs ─────────────────────────────────────────────────────── --}}
    <div class="w-full gap-1 mb-5 border-b border-gray-200">
        @php
            $tabs = [
                '' => __('admin.warranty_claims_section.all'),
                'submitted' => __('admin.warranty_claims_section.submitted'),
                'under_review' => __('admin.warranty_claims_section.under_review'),
                'approved' => __('admin.warranty_claims_section.approved'),
                'rejected' => __('admin.warranty_claims_section.rejected'),
                'resolved' => __('admin.warranty_claims_section.resolved'),
            ];
        @endphp
        @foreach($tabs as $tabValue => $tabLabel)
            <button data-status-filter="{{ $tabValue }}"
                class="status-tab px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors
                                    {{ $tabValue === '' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warranty_claims_section.search_label') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.warranty_claims_section.search_placeholder') }}">
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warranty_claims_section.listing_type') }}</label>
                <select id="filter-listing-type" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.warranty_claims_section.any') }}</option>
                    <option value="vendor_listing">{{ __('admin.warranty_claims_section.vendor_listing') }}</option>
                    <option value="admin_listing">{{ __('admin.warranty_claims_section.admin_listing') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.from') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.to') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <button id="btn-reset-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('admin.warranty_claims_section.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="warranty-claims-table" data-url="{{ route('admin.warranty-claims.datatable') }}" class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_claims_section.claim_number_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_claims_section.customer_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_claims_section.product_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_claims_section.vendor_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_claims_section.listing_type_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_claims_section.status_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_claims_section.date_col') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

@endsection
