@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/banners.js'])
@endpush

@section('title', __('admin.banners.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.banners.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.banners.manage_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-export-dropdown />
            @if(auth('admin')->user()->can('create', App\Models\Banner::class))
                <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">
                    {{ __('admin.banners.new_banner') }}
                </a>
            @endif
        </div>
    </div>

    {{-- ─── Stats ────────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-card title="{{ __('admin.banners.active') }}" :value="number_format($stats['active'])" icon="check-circle"
            iconBg="bg-success-100 text-success-600" />
        <x-stat-card title="{{ __('admin.banners.scheduled') }}" :value="number_format($stats['scheduled'])" icon="calendar"
            iconBg="bg-primary-100 text-primary-600" />
        <x-stat-card title="{{ __('admin.banners.expired') }}" :value="number_format($stats['expired'])" icon="x-circle"
            iconBg="bg-danger-100 text-danger-600" />
        <x-stat-card title="{{ __('admin.banners.impressions_today') }}" :value="number_format($stats['impressions_today'])" icon="eye"
            iconBg="bg-warning-100 text-warning-600" />
    </div>

    {{-- ─── Status tabs ─────────────────────────────────────────────────────────── --}}
    <div class="flex gap-1 mb-4 border-b border-gray-200">
        @foreach(['' => __('admin.banners.all'), 'active' => __('admin.banners.active'), 'scheduled' => __('admin.banners.scheduled'), 'expired' => __('admin.banners.expired'), 'inactive' => __('admin.banners.inactive')] as $val => $label)
            <button type="button"
                class="status-tab px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                                                    {{ $val === '' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                data-status="{{ $val }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.banners.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.banners.search_placeholder') }}">
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.banners.placement') }}</label>
                <select id="filter-placement" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.banners.all_placements') }}</option>
                    @foreach($placements as $p)
                        <option value="{{ $p->code }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.banners.country') }}</label>
                <select id="filter-country" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.banners.all_countries') }}</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.banners.device') }}</label>
                <select id="filter-device" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.banners.all_devices') }}</option>
                    <option value="all">{{ __('admin.banners.all') }}</option>
                    <option value="desktop">{{ __('admin.banners.desktop') }}</option>
                    <option value="mobile">{{ __('admin.banners.mobile') }}</option>
                    <option value="app">{{ __('admin.banners.app') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.banners.starts_from') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.banners.ends_by') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('admin.banners.reset') }}</button>
        </form>
    </x-card>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="banners-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-3 w-16 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.image') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.name') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.placement') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.country') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.status') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.device') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.audience') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.dates') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.impr') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.clicks') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.ctr') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.banners.data_table.priority') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.banners.data_table.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>`

    {{-- ─── Delete Confirm Modal ────────────────────────────────────────────────── --}}
    <x-modal id="delete-modal" title="{{ __('admin.banners.delete_banner_title') }}" size="sm">
        @php
            [$delBefore, $delAfter] = explode(':name', __('admin.banners.delete_banner_confirm'));
        @endphp
        <p class="text-sm text-gray-600">
            {{ $delBefore }}<strong id="delete-banner-name"></strong>{{ $delAfter }}
        </p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" data-modal-close>{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-delete-btn" class="btn btn-danger">{{ __('admin.banners.delete') }}</button>
        </div>
    </x-modal>

    {{-- ─── Duplicate Confirm Modal ─────────────────────────────────────────────── --}}
    <x-modal id="duplicate-modal" title="{{ __('admin.banners.duplicate_banner_title') }}" size="sm">
        @php
            [$dupBefore, $dupAfter] = explode(':name', __('admin.banners.duplicate_banner_confirm'));
        @endphp
        <p class="text-sm text-gray-600">
            {{ $dupBefore }}<strong id="duplicate-banner-name"></strong>{{ $dupAfter }}
        </p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" data-modal-close>{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-duplicate-btn" class="btn btn-primary">{{ __('admin.banners.duplicate') }}</button>
        </div>
    </x-modal>

@endsection
