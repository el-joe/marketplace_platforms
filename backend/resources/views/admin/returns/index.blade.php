@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/returns.js'])
@endpush

@section('title', __('admin.returns_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.returns_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.returns_section.subtitle') }}</p>
        </div>
        <x-export-dropdown />
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.returns_section.requested') }}" :value="number_format($stats['pending'])"
            iconBg="{{ $stats['pending'] > 0 ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.returns_section.awaiting_pickup') }}" :value="number_format($stats['pickup_scheduled'])"
            iconBg="bg-blue-100 text-blue-600" />
        <x-stat-card title="{{ __('admin.returns_section.in_transit') }}" :value="number_format($stats['in_transit'])"
            iconBg="bg-indigo-100 text-indigo-600" />
        <x-stat-card title="{{ __('admin.returns_section.received') }}" :value="number_format($stats['received'])"
            iconBg="bg-purple-100 text-purple-600" />
        <x-stat-card title="{{ __('admin.returns_section.inspecting') }}" :value="number_format($stats['inspected'])"
            iconBg="bg-purple-100 text-purple-600" />
        <x-stat-card title="{{ __('admin.returns_section.completed') }}" :value="number_format($stats['refunded'])"
            iconBg="bg-green-100 text-green-600" />
    </div>

    {{-- ─── Status Tabs ─────────────────────────────────────────────────────── --}}
    <div class="w-full gap-1 mb-5 border-b border-gray-200">
        @php
            $tabs = [
                '' => __('admin.returns_section.all'),
                'requested' => __('admin.returns_section.requested'),
                'approved' => __('admin.returns_section.approved'),
                'awaiting_pickup' => __('admin.returns_section.awaiting_pickup'),
                'in_transit' => __('admin.returns_section.in_transit'),
                'received' => __('admin.returns_section.received'),
                'inspecting' => __('admin.returns_section.inspecting'),
                'completed' => __('admin.returns_section.completed'),
                'rejected' => __('admin.returns_section.rejected'),
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
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.returns_section.search_label') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.returns_section.search_placeholder') }}">
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.returns_section.vendor') }}</label>
                <select id="filter-vendor" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.returns_section.any_vendor') }}</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->store_name }}</option>
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
            <button id="btn-reset-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('admin.returns_section.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="returns-table" data-url="{{ route('admin.returns.datatable') }}" class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.returns_section.return_number_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.returns_section.order_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.returns_section.customer_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.returns_section.vendor_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.returns_section.reason_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.returns_section.items_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.returns_section.status_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.returns_section.created_col') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

@endsection
