@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/vendor-applications.js'])
@endpush

@section('title', __('admin.vendor_applications.queue_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.vendor_applications.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.vendor_applications.review_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="assign-me-btn" class="btn btn-secondary btn-sm"
                title="{{ __('admin.vendor_applications.auto_assign_title') }}">
                {{ __('admin.vendor_applications.auto_assign_to_me') }}
            </button>
            <x-export-dropdown />
        </div>
    </div>

    {{-- ─── Stats ──────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-card title="{{ __('admin.vendor_applications.pending_stat') }}" :value="number_format($stats['pending'])" iconBg="bg-warning-100 text-warning-600" />
        <x-stat-card title="{{ __('admin.vendor_applications.under_review_stat') }}" :value="number_format($stats['under_review'])"
            iconBg="bg-primary-100 text-primary-600" />
        <x-stat-card title="{{ __('admin.vendor_applications.waiting_5_days_stat') }}" :value="number_format($stats['waiting_5plus'])"
            iconBg="{{ $stats['waiting_5plus'] > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400' }}" />
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm"
                    placeholder="{{ __('admin.vendor_applications.search_placeholder') }}">
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendor_applications.status_label') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.vendor_applications.all') }}</option>
                    <option value="pending">{{ __('admin.vendor_applications.pending') }}</option>
                    <option value="under_review">{{ __('admin.vendor_applications.under_review') }}</option>
                    <option value="rejected">{{ __('admin.vendor_applications.global_status_rejected') }}</option>
                </select>
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendor_applications.country_label') }}</label>
                <select id="filter-country" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.vendor_applications.all_countries') }}</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendor_applications.waiting_days_min') }}</label>
                <input type="number" id="filter-days-min" class="form-input w-full text-sm" placeholder="{{ __('admin.vendor_applications.waiting_days_placeholder') }}" min="0">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.from') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.to') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('admin.vendor_applications.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Urgency legend ──────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 mb-4 text-xs text-gray-500">
        <span>{{ __('admin.vendor_applications.waiting_time_label') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-green-500"></span> {{ __('admin.vendor_applications.less_than_2_days') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-yellow-500"></span> {{ __('admin.vendor_applications.between_2_5_days') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-red-500"></span> {{ __('admin.vendor_applications.more_than_5_days') }}</span>
    </div>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="applications-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.store_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.business_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.country_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.type_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.docs_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.bank_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.waiting_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.submitted_column') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.vendor_applications.actions_column') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

@endsection
