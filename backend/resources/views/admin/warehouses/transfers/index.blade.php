@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/admin/warehouses.js'])
@endpush

@section('title', __('admin.warehouses_section.transfers_title'))

@section('content')

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.warehouses.index') }}" class="hover:text-primary-600">{{ __('admin.warehouses_section.title') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium">{{ __('admin.warehouses_section.transfers') }}</span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.warehouses_section.transfers_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.warehouses_section.track_stock_desc') }}</p>
        </div>
        @if(auth('admin')->user()->can('warehouses.view'))
            <a href="{{ route('admin.warehouses.transfers.create') }}" class="btn btn-primary btn-sm">{{ __('admin.warehouses_section.new_transfer') }}</a>
        @endif
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        <x-stat-card title="{{ __('common.pending') }}" :value="number_format($stats['pending'])" iconBg="bg-yellow-100 text-yellow-600" />
        <x-stat-card title="{{ __('admin.warehouses_section.in_transit') }}" :value="number_format($stats['in_transit'])" iconBg="bg-blue-100 text-blue-600" />
        <x-stat-card title="{{ __('admin.warehouses_section.received') }}" :value="number_format($stats['received'])" iconBg="bg-green-100 text-green-600" />
        <x-stat-card title="{{ __('common.cancelled') }}" :value="number_format($stats['cancelled'])" iconBg="bg-gray-100 text-gray-500" />
    </div>

    {{-- ─── Filter Bar ──────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.warehouses_section.all_statuses') }}</option>
                    <option value="pending">{{ __('common.pending') }}</option>
                    <option value="in_transit">{{ __('admin.warehouses_section.in_transit') }}</option>
                    <option value="received">{{ __('admin.warehouses_section.received') }}</option>
                    <option value="cancelled">{{ __('common.cancelled') }}</option>
                </select>
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warehouses_section.source_warehouse') }}</label>
                <select id="filter-source" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.warehouses_section.any') }}</option>
                    @foreach($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warehouses_section.destination_warehouse') }}</label>
                <select id="filter-dest" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.warehouses_section.any') }}</option>
                    @foreach($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <button id="btn-reset-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('common.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="transfers-table" data-url="{{ route('admin.warehouses.transfers.datatable') }}"
                class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.transfer_number_column') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.from') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.to') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.vendor_column') }}</th>
                        <th class="pb-3 pr-4">{{ __('common.status') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.warehouses_section.created_column') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

@endsection
