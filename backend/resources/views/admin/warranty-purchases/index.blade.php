@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/warranty-purchases.js'])
@endpush

@section('title', __('admin.warranty_purchases_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.warranty_purchases_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.warranty_purchases_section.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.warranty-purchases.summary') }}" class="btn btn-secondary btn-sm">
            {{ __('admin.warranty_purchases_section.view_summary') }}
        </a>
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.warranty_purchases_section.active') }}" :value="number_format($stats['active'])"
            iconBg="bg-green-100 text-green-600" />
        <x-stat-card title="{{ __('admin.warranty_purchases_section.pending') }}" :value="number_format($stats['pending'])"
            iconBg="bg-yellow-100 text-yellow-600" />
        <x-stat-card title="{{ __('admin.warranty_purchases_section.expired') }}" :value="number_format($stats['expired'])"
            iconBg="bg-gray-100 text-gray-500" />
        <x-stat-card title="{{ __('admin.warranty_purchases_section.cancelled') }}" :value="number_format($stats['cancelled'])"
            iconBg="bg-red-100 text-red-600" />
    </div>

    {{-- ─── Status Tabs ─────────────────────────────────────────────────────── --}}
    <div class="w-full gap-1 mb-5 border-b border-gray-200">
        @php
            $tabs = [
                '' => __('admin.warranty_purchases_section.all'),
                'active' => __('admin.warranty_purchases_section.active'),
                'pending' => __('admin.warranty_purchases_section.pending'),
                'expired' => __('admin.warranty_purchases_section.expired'),
                'cancelled' => __('admin.warranty_purchases_section.cancelled'),
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
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warranty_purchases_section.search_label') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.warranty_purchases_section.search_placeholder') }}">
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warranty_purchases_section.plan') }}</label>
                <select id="filter-plan" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.warranty_purchases_section.any_plan') }}</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warranty_purchases_section.category') }}</label>
                <select id="filter-category" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.warranty_purchases_section.any_category') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name_en }}</option>
                    @endforeach
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
            <button id="btn-reset-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('admin.warranty_purchases_section.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="warranty-purchases-table" data-url="{{ route('admin.warranty-purchases.datatable') }}" class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.purchase_id_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.customer_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.product_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.plan_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.duration_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.price_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.status_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.coverage_starts_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.coverage_ends_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.warranty_purchases_section.date_col') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

@endsection
