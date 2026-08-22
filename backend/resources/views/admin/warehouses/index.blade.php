@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/warehouses.js'])
@endpush

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    deactivateWarehouseConfirm: @json(__('admin.warehouses_section.deactivate_warehouse_confirm')),
    activateWarehouseConfirm: @json(__('admin.warehouses_section.activate_warehouse_confirm')),
    requestFailed: @json(__('admin.warehouses_section.request_failed')),
});
</script>
@endpush

@section('title', __('admin.warehouses_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.warehouses_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.warehouses_section.manage_desc') }}</p>
        </div>
        @if(auth('admin')->user()->can('warehouses.view'))
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.warehouses.shipping-surcharges.index') }}" class="btn btn-secondary btn-sm">Shipping Surcharges</a>
                <a href="{{ route('admin.warehouses.create') }}" class="btn btn-primary btn-sm">+ {{ __('admin.warehouses_section.add_warehouse') }}</a>
            </div>
        @endif
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    @php
        $totalPct = $stats['total_capacity'] > 0
            ? round($stats['used_capacity'] / $stats['total_capacity'] * 100, 1)
            : 0;
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.warehouses_section.platform_fbn') }}" :value="number_format($stats['platform'])"
            iconBg="bg-indigo-100 text-indigo-600" />
        <x-stat-card title="{{ __('admin.warehouses_section.seller_owned') }}" :value="number_format($stats['seller_owned'])"
            iconBg="bg-orange-100 text-orange-600" />
        <x-stat-card title="{{ __('admin.warehouses_section.third_party') }}" :value="number_format($stats['third_party'])" iconBg="bg-gray-100 text-gray-500" />
        <x-stat-card title="{{ __('admin.warehouses_section.total_capacity_stat') }}" :value="number_format($stats['total_capacity'], 1) . ' m³'"
            iconBg="bg-teal-100 text-teal-600" :subtitle="__('admin.warehouses_section.used_pct', ['pct' => $totalPct])" />
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warehouses_section.search_name_code') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm"
                    placeholder="{{ __('admin.warehouses_section.warehouse_name_or_code_placeholder') }}">
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.type') }}</label>
                <select id="filter-type" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.warehouses_section.all_types') }}</option>
                    <option value="platform_fbn">{{ __('admin.warehouses_section.platform_fbn_option') }}</option>
                    <option value="seller_owned">{{ __('admin.warehouses_section.seller_owned') }}</option>
                    <option value="third_party">{{ __('admin.warehouses_section.third_party') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="1">{{ __('common.active') }}</option>
                    <option value="0">{{ __('common.inactive') }}</option>
                </select>
            </div>
            <button id="btn-reset-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('common.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="warehouses-table" data-url="{{ route('admin.warehouses.datatable') }}" class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4">{{ __('common.name') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.code_column') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.type_column') }}</th>
                        <th class="pb-3 pr-4">{{ __('common.country') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.vendor_column') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.manager') }}</th>
                        <th class="pb-3 pr-4 min-w-[120px]">{{ __('admin.warehouses_section.capacity_column') }}</th>
                        <th class="pb-3 pr-4">{{ __('common.status') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

@endsection
