@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.customers_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.customers_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.customers_section.manage_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <x-export-dropdown />
        </div>
    </div>

    {{-- ─── Stats row ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <x-stat-card title="{{ __('admin.customers_section.total_customers') }}" :value="number_format($stats['total'])" icon="users" />
        <x-stat-card title="{{ __('admin.customers_section.active_stat') }}" :value="number_format($stats['active'])" icon="check-circle"
            iconBg="bg-success-100 text-success-600" />
        <x-stat-card title="{{ __('admin.customers_section.suspended_stat') }}" :value="number_format($stats['suspended'])" icon="pause-circle"
            iconBg="bg-warning-100 text-warning-600" />
        <x-stat-card title="{{ __('admin.customers_section.banned_stat') }}" :value="number_format($stats['banned'])" icon="x-circle"
            iconBg="bg-danger-100 text-danger-600" />
        <x-stat-card title="{{ __('admin.customers_section.new_this_week') }}" :value="number_format($stats['new_this_week'])" icon="user-plus"
            iconBg="bg-primary-100 text-primary-600" />
    </div>

    {{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.customers_section.search_placeholder') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.customers_section.status_column') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.customers_section.all_statuses') }}</option>
                    <option value="active">{{ __('admin.customers_section.active_option') }}</option>
                    <option value="suspended">{{ __('admin.customers_section.suspended_option') }}</option>
                    <option value="banned">{{ __('admin.customers_section.banned_option') }}</option>
                </select>
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.customers_section.country_column') }}</label>
                <select id="filter-country" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.customers_section.all_countries') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">
                            {{ $country->flag_emoji ? $country->flag_emoji . ' ' : '' }}{{ $country->name_en }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.customers_section.from_label') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.customers_section.to_label') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <div class="w-28">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.customers_section.min_orders') }}</label>
                <input type="number" id="filter-min-orders" min="0" class="form-input w-full text-sm" placeholder="0">
            </div>
            <div class="flex items-end gap-2">
                <label class="flex items-center gap-1.5 text-sm text-gray-700 pb-2 cursor-pointer">
                    <input type="checkbox" id="filter-verified" class="rounded border-gray-300">
                    {{ __('admin.customers_section.email_verified_only') }}
                </label>
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('common.reset') }}</button>
        </form>
    </x-card>

    {{-- ─── Bulk action bar ─────────────────────────────────────────────────────── --}}
    <div id="bulk-bar"
        class="hidden mb-4 flex items-center gap-3 rounded-lg bg-primary-50 border border-primary-200 px-4 py-2">
        <span class="text-sm font-medium text-primary-800"><span id="selected-count">0</span> {{ __('admin.customers_section.selected') }}</span>
        <div class="flex items-center gap-2 ml-2">
            <button type="button" data-bulk="suspend" class="btn btn-warning btn-xs">{{ __('admin.customers_section.suspend_selected') }}</button>
            <button type="button" data-bulk="export" class="btn btn-ghost btn-xs">{{ __('admin.customers_section.export_selected') }}</button>
        </div>
        <button type="button" id="clear-selection" class="btn btn-ghost btn-xs ml-auto text-gray-500">✕ {{ __('common.clear') }}</button>
    </div>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="customers-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-3 w-8">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300">
                        </th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.name_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.email_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.phone_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.country_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.status_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.orders_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.spent_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.points_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.customers_section.joined_column') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Suspend reason modal ────────────────────────────────────────────────── --}}
    <x-modal id="suspend-modal" title="{{ __('admin.customers_section.suspend_customer') }}" size="sm">
        <p class="text-sm text-gray-600 mb-3">{{ \Illuminate\Support\Str::before(__('admin.customers_section.suspend_customer_confirm'), ':name') }}<strong id="suspend-customer-name"></strong>{{ \Illuminate\Support\Str::after(__('admin.customers_section.suspend_customer_confirm'), ':name') }}</p>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.reason_label') }} <span
                    class="text-danger-500">*</span></label>
            <textarea id="suspend-reason" class="form-input w-full resize-none" rows="3"
                placeholder="{{ __('admin.customers_section.enter_reason_placeholder') }}"></textarea>
        </div>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
            <button type="button" id="suspend-confirm-btn" class="btn btn-warning btn-sm">{{ __('admin.customers_section.suspend') }}</button>
        </x-slot>
    </x-modal>

    {{-- ─── Ban modal (requires typed confirmation) ────────────────────────────── --}}
    <x-modal id="ban-modal" title="{{ __('admin.customers_section.ban_customer') }}" size="sm" :persistent="true">
        <p class="text-sm text-gray-600 mb-3">{{ \Illuminate\Support\Str::before(__('admin.customers_section.ban_customer_confirm'), ':name') }}<strong id="ban-customer-name"></strong>{{ \Illuminate\Support\Str::after(__('admin.customers_section.ban_customer_confirm'), ':name') }}</p>
        <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.reason_label') }} <span
                    class="text-danger-500">*</span></label>
            <textarea id="ban-reason" class="form-input w-full resize-none" rows="3" placeholder="{{ __('admin.customers_section.enter_reason_placeholder') }}"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.type_confirm_hint') }} <code
                    class="bg-gray-100 px-1 rounded">CONFIRM</code></label>
            <input type="text" id="ban-confirm-input" class="form-input w-full" placeholder="CONFIRM" autocomplete="off">
        </div>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
            <button type="button" id="ban-confirm-btn" class="btn btn-danger btn-sm" disabled>{{ __('admin.customers_section.ban_customer') }}</button>
        </x-slot>
    </x-modal>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            pleaseWait: @json(__('admin.customers_section.please_wait')),
            searchPlaceholder: @json(__('admin.customers_section.search_ellipsis')),
            suspendCountPrompt: @json(__('admin.customers_section.suspend_count_prompt')),
            customersSuspended: @json(__('admin.customers_section.customers_suspended')),
            someActionsFailed: @json(__('admin.customers_section.some_actions_failed')),
            useFiltersExportHint: @json(__('admin.customers_section.use_filters_export_hint')),
            enterReason: @json(__('admin.customers_section.enter_reason')),
            suspend: @json(__('admin.customers_section.suspend')),
            customerSuspended: @json(__('admin.customers_section.customer_suspended')),
            actionFailed: @json(__('admin.customers_section.action_failed')),
            typeConfirmToProceed: @json(__('admin.customers_section.type_confirm_to_proceed')),
            banCustomer: @json(__('admin.customers_section.ban_customer')),
            customerBanned: @json(__('admin.customers_section.customer_banned')),
            reactivateConfirm: @json(__('admin.customers_section.reactivate_confirm')),
            thisCustomer: @json(__('admin.customers_section.this_customer')),
            customerReactivated: @json(__('admin.customers_section.customer_reactivated')),
            enterNonZeroAdjustment: @json(__('admin.customers_section.enter_non_zero_adjustment')),
            apply: @json(__('admin.customers_section.apply')),
            loyaltyPointsAdjusted: @json(__('admin.customers_section.loyalty_points_adjusted')),
            fillAllFields: @json(__('admin.customers_section.fill_all_fields')),
            send: @json(__('admin.customers_section.send')),
            notificationSent: @json(__('admin.customers_section.notification_sent')),
            failedToSend: @json(__('admin.customers_section.failed_to_send')),
            fullListDatatable: @json(__('admin.customers_section.full_list_datatable')),
            hideDatatable: @json(__('admin.customers_section.hide_datatable')),
            copied: @json(__('admin.customers_section.copied')),
            copyFailed: @json(__('admin.customers_section.copy_failed')),
        });
    </script>
    @vite(['resources/js/admin/customers.js'])
    <script>
        window.customersTableUrl = '{{ route('admin.customers.datatable') }}';
    </script>
@endpush