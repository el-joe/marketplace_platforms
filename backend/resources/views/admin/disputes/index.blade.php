@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/disputes.js'])
@endpush

@section('title', __('admin.disputes_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.disputes_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.disputes_section.subtitle') }}</p>
        </div>
        <x-export-dropdown />
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.disputes_section.open') }}" :value="number_format($stats['open'])"
            iconBg="{{ $stats['open'] > 0 ? 'bg-yellow-100 text-yellow-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.disputes_section.under_review') }}" :value="number_format($stats['under_review'])"
            iconBg="bg-indigo-100 text-indigo-600" />
        <x-stat-card title="{{ __('admin.disputes_section.escalate') }}" :value="number_format($stats['escalated'])"
            iconBg="{{ $stats['escalated'] > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.disputes_section.unassigned') }}" :value="number_format($stats['unassigned'])"
            iconBg="{{ $stats['unassigned'] > 0 ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.disputes_section.resolved_30d') }}" :value="number_format($stats['resolved_30d'])"
            iconBg="bg-green-100 text-green-600" :subtitle="$stats['avg_resolution_hours'] ? __('admin.disputes_section.avg_short', ['days' => round($stats['avg_resolution_hours'] / 24, 1)]) : null" />
    </div>

    {{-- ─── Status Tabs ─────────────────────────────────────────────────────── --}}
    <div class="w-full gap-1 mb-5 border-b border-gray-200">
        @php
            $tabs = [
                '' => __('admin.disputes_section.all'),
                'open' => __('admin.disputes_section.open'),
                'seller_responded' => __('admin.disputes_section.seller_responded'),
                'under_review' => __('admin.disputes_section.under_review'),
                'escalated' => __('admin.disputes_section.escalate'),
                'resolved' => __('admin.disputes_section.resolved'),
                'closed' => __('admin.disputes_section.closed'),
                '__unassigned' => __('admin.disputes_section.unassigned'),
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
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.disputes_section.search_label') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.disputes_section.search_placeholder') }}">
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.disputes_section.reason') }}</label>
                <select id="filter-reason" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.disputes_section.all_reasons') }}</option>
                    <option value="item_not_received">{{ __('admin.disputes_section.reason_item_not_received') }}</option>
                    <option value="item_damaged">{{ __('admin.disputes_section.reason_item_damaged') }}</option>
                    <option value="item_not_as_described">{{ __('admin.disputes_section.reason_item_not_as_described') }}</option>
                    <option value="counterfeit">{{ __('admin.disputes_section.reason_counterfeit') }}</option>
                    <option value="wrong_item">{{ __('admin.disputes_section.reason_wrong_item') }}</option>
                    <option value="quality_issue">{{ __('admin.disputes_section.reason_quality_issue') }}</option>
                    <option value="seller_unresponsive">{{ __('admin.disputes_section.reason_seller_unresponsive') }}</option>
                    <option value="refund_not_received">{{ __('admin.disputes_section.reason_refund_not_received') }}</option>
                    <option value="other">{{ __('admin.disputes_section.reason_other') }}</option>
                </select>
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.disputes_section.resolution') }}</label>
                <select id="filter-resolution" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.disputes_section.any') }}</option>
                    <option value="favor_customer">{{ __('admin.disputes_section.favor_customer') }}</option>
                    <option value="favor_seller">{{ __('admin.disputes_section.favor_seller') }}</option>
                    <option value="split">{{ __('admin.disputes_section.split') }}</option>
                    <option value="no_action">{{ __('admin.disputes_section.no_action') }}</option>
                </select>
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.disputes_section.assigned_to') }}</label>
                <select id="filter-assigned" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.disputes_section.anyone') }}</option>
                    @foreach($admins as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
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
            <button id="btn-reset-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('admin.disputes_section.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="disputes-table" data-url="{{ route('admin.disputes.datatable') }}" class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.disputes_section.dispute_number_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.disputes_section.order_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.disputes_section.customer_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.disputes_section.vendor_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.disputes_section.reason_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.disputes_section.status_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.disputes_section.assigned_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.disputes_section.opened_col') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

@endsection
