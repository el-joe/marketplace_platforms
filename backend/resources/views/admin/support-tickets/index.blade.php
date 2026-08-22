@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/support-tickets.js'])
@endpush

@section('title', __('admin.support_tickets.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.support_tickets.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.support_tickets.subtitle') }}</p>
        </div>
        <x-export-dropdown />
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.support_tickets.open') }}" :value="number_format($stats['open'])"
            iconBg="{{ $stats['open'] > 0 ? 'bg-yellow-100 text-yellow-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.support_tickets.in_progress_stat') }}" :value="number_format($stats['in_progress'])" iconBg="bg-blue-100 text-blue-600" />
        <x-stat-card title="{{ __('admin.support_tickets.urgent_stat') }}" :value="number_format($stats['urgent'])"
            iconBg="{{ $stats['urgent'] > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.support_tickets.unassigned') }}" :value="number_format($stats['unassigned'])"
            iconBg="{{ $stats['unassigned'] > 0 ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-400' }}"
            :subtitle="$stats['avg_first_response_minutes'] ? __('admin.support_tickets.avg_resp', ['hours' => round($stats['avg_first_response_minutes'] / 60, 1)]) : null" />
    </div>

    {{-- ─── Status Tabs ─────────────────────────────────────────────────────── --}}
    <div class="flex gap-1 mb-5 border-b border-gray-200">
        @php
            $tabs = [
                '' => __('admin.support_tickets.all'),
                'open' => __('admin.support_tickets.open'),
                'in_progress' => __('admin.support_tickets.in_progress_stat'),
                'waiting_customer' => __('admin.support_tickets.waiting_tab'),
                '__unassigned' => __('admin.support_tickets.unassigned'),
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
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.support_tickets.search_label') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.support_tickets.search_placeholder') }}">
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.support_tickets.category') }}</label>
                <select id="filter-category" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.support_tickets.all_categories') }}</option>
                    <option value="order_issue">{{ __('admin.support_tickets.category_order_issue') }}</option>
                    <option value="payment_issue">{{ __('admin.support_tickets.category_payment_issue') }}</option>
                    <option value="account">{{ __('admin.support_tickets.category_account') }}</option>
                    <option value="technical">{{ __('admin.support_tickets.category_technical') }}</option>
                    <option value="product_inquiry">{{ __('admin.support_tickets.category_product_inquiry') }}</option>
                    <option value="policy">{{ __('admin.support_tickets.category_policy') }}</option>
                    <option value="payout">{{ __('admin.support_tickets.category_payout') }}</option>
                    <option value="catalog">{{ __('admin.support_tickets.category_catalog') }}</option>
                    <option value="other">{{ __('admin.support_tickets.category_other') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.support_tickets.priority') }}</label>
                <select id="filter-priority" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.support_tickets.all_priorities') }}</option>
                    <option value="urgent">{{ __('admin.support_tickets.urgent') }}</option>
                    <option value="high">{{ __('admin.support_tickets.high') }}</option>
                    <option value="normal">{{ __('admin.support_tickets.normal') }}</option>
                    <option value="low">{{ __('admin.support_tickets.low') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.support_tickets.actor_type') }}</label>
                <select id="filter-requester-role" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.support_tickets.all') }}</option>
                    <option value="customer">{{ __('admin.support_tickets.customer') }}</option>
                    <option value="seller">{{ __('admin.support_tickets.vendor_seller') }}</option>
                </select>
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.support_tickets.assigned_to') }}</label>
                <select id="filter-assigned" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.support_tickets.anyone') }}</option>
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
            <button id="btn-reset-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('admin.support_tickets.reset') }}</button>
        </div>
    </x-card>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="support-tickets-table" data-url="{{ route('admin.support-tickets.datatable') }}"
                class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.support_tickets.ticket_number_col') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.support_tickets.requester') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.support_tickets.category') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.support_tickets.priority') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.status') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.support_tickets.subject') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.support_tickets.assigned_to') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.support_tickets.response') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.support_tickets.opened') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

@endsection
