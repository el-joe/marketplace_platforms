@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/admin/vendor-change-requests.js'])
@endpush

@section('title', __('admin.vendor_change_requests.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.vendor_change_requests.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.vendor_change_requests.subtitle') }}</p>
        </div>
    </div>

    {{-- ─── Status Tabs ─────────────────────────────────────────────────────── --}}
    <div class="w-full gap-1 mb-5 border-b border-gray-200">
        @php
            $tabs = [
                '' => __('admin.vendor_change_requests.all'),
                'pending' => __('admin.vendor_change_requests.pending'),
                'approved' => __('admin.vendor_change_requests.approved'),
                'rejected' => __('admin.vendor_change_requests.rejected'),
                'cancelled' => __('admin.vendor_change_requests.cancelled'),
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
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendor_change_requests.search_label') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.vendor_change_requests.search_placeholder') }}">
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendor_change_requests.section') }}</label>
                <select id="filter-section" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.vendor_change_requests.any_section') }}</option>
                    @foreach($sections as $section)
                        <option value="{{ $section }}">{{ __('admin.vendors.section_' . $section) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-56">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendor_change_requests.vendor') }}</label>
                <select id="filter-vendor_id" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.vendor_change_requests.any_vendor') }}</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->store_name }}</option>
                    @endforeach
                </select>
            </div>
            <button id="btn-reset-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('admin.vendor_change_requests.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="vendor-change-requests-table" data-url="{{ route('admin.vendor-change-requests.datatable') }}" class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.vendor_change_requests.vendor_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.vendor_change_requests.section_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.vendor_change_requests.type_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.vendor_change_requests.requested_by_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.vendor_change_requests.requested_at_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.vendor_change_requests.status_column') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

@endsection
