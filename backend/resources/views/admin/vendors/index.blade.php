@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.vendors.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.vendors.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.vendors.manage_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            @if($pendingCount > 0)
                <a href="{{ route('admin.vendor-applications.index') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-warning-100 text-warning-800 px-3 py-1.5 text-sm font-medium hover:bg-warning-200 transition-colors">
                    <span>{{ __('admin.vendors.application_queue') }}</span>
                    <span
                        class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-warning-600 text-white text-xs font-bold">{{ $pendingCount }}</span>
                </a>
            @endif
            <button type="button" id="export-btn" class="btn btn-secondary btn-sm">{{ __('admin.vendors.export') }}</button>
            <x-export-dropdown />
        </div>
    </div>

    {{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="w-full">
                <x-date-range-filter />
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.vendors.search_placeholder') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendors.status_column') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.vendors.all_statuses') }}</option>
                    <option value="pending">{{ __('admin.vendors.pending') }}</option>
                    <option value="active">{{ __('admin.vendors.active') }}</option>
                    <option value="suspended">{{ __('admin.vendors.suspended') }}</option>
                    <option value="under_review">{{ __('admin.vendors.under_review') }}</option>
                    <option value="rejected">{{ __('admin.vendors.rejected') }}</option>
                    <option value="blacklisted">{{ __('admin.vendors.blacklisted') }}</option>
                </select>
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.country') }}</label>
                <select id="filter-country" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.vendors.all_countries') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendors.account_manager') }}</label>
                <select id="filter-manager" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.vendors.all_managers') }}</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendors.from_label') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.vendors.to_label') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('admin.vendors.reset') }}</button>
        </form>
    </x-card>

    {{-- ─── Bulk action bar (hidden until rows selected) ─────────────────────── --}}
    <div id="bulk-bar"
        class="hidden mb-4 flex items-center gap-3 rounded-lg bg-primary-50 border border-primary-200 px-4 py-2">
        <span class="text-sm font-medium text-primary-800"><span id="selected-count">0</span> {{ \Illuminate\Support\Str::after(__('admin.vendors.selected_count'), ':count ') }}</span>
        <div class="flex items-center gap-2 ml-2">
            <button type="button" data-bulk="suspend" class="btn btn-warning btn-xs">{{ __('admin.vendors.bulk_suspend_btn') }}</button>
            <button type="button" data-bulk="reactivate" class="btn btn-success btn-xs">{{ __('admin.vendors.bulk_reactivate_btn') }}</button>
            <button type="button" data-bulk="place_hold" class="btn btn-ghost btn-xs">{{ __('admin.vendors.place_hold_btn') }}</button>
            <button type="button" data-bulk="assign_manager" class="btn btn-ghost btn-xs">{{ __('admin.vendors.assign_manager_btn') }}</button>
            <button type="button" data-bulk="export" class="btn btn-ghost btn-xs">{{ __('admin.vendors.export_csv') }}</button>
        </div>
        <button type="button" id="clear-selection" class="btn btn-ghost btn-xs ml-auto text-gray-500">✕ {{ __('admin.vendors.clear') }}</button>
    </div>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="vendors-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-3 w-8">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300">
                        </th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.store_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.owner_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.email_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.gmv_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.orders_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.rating_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.status_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.manager_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.joined_column') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.vendors.actions_column') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Per-row action dropdown (shared, positioned via JS) ────────────────── --}}
    <div id="vendor-row-dropdown"
        class="hidden fixed z-50 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-1"
        style="top:0;left:0">
        <a id="vrd-view" href="#"
            class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('admin.vendors.view') }}</a>
        <!-- <a id="vrd-edit" href="#"
            class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit</a> -->
        <div id="vrd-divider" class="hidden border-t border-gray-100 my-1"></div>
        <button id="vrd-approve" type="button"
            class="hidden w-full text-start px-3 py-2 text-sm text-success-600 hover:bg-gray-50">{{ __('admin.vendors.approve_vendor_action') }}</button>
        <button id="vrd-reject" type="button"
            class="hidden w-full text-start px-3 py-2 text-sm text-danger-600 hover:bg-gray-50">{{ __('admin.vendors.reject_vendor_action') }}</button>
        <button id="vrd-suspend" type="button"
            class="hidden w-full text-start px-3 py-2 text-sm text-danger-600 hover:bg-gray-50">{{ __('admin.vendors.suspend_vendor_action') }}</button>
        <button id="vrd-reactivate" type="button"
            class="hidden w-full text-start px-3 py-2 text-sm text-success-600 hover:bg-gray-50">{{ __('admin.vendors.reactivate_vendor_action') }}</button>
    </div>

    {{-- ─── Suspend vendor modal ─────────────────────────────────────────────── --}}
    <x-modal id="suspend-modal" title="{{ __('admin.vendors.suspend_vendor_title') }}" size="sm">
        <p class="text-sm text-gray-600 mb-3">{{ \Illuminate\Support\Str::before(__('admin.vendors.suspend_vendor_confirm'), ':name') }}<strong id="suspend-vendor-name"></strong>{{ \Illuminate\Support\Str::after(__('admin.vendors.suspend_vendor_confirm'), ':name') }}</p>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('admin.vendors.suspension_reason') }} <span class="text-danger-500">*</span>
            </label>
            <textarea id="suspend-reason" class="form-input w-full resize-none" rows="3"
                placeholder="{{ __('admin.vendors.reason_suspend_placeholder') }}"></textarea>
            <p id="suspend-reason-error" class="hidden text-xs text-danger-600 mt-1">
                {{ __('admin.vendors.reason_required_hint') }}
            </p>
        </div>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.vendors.cancel') }}</button>
            <button type="button" id="suspend-confirm-btn" class="btn btn-danger btn-sm">{{ __('admin.vendors.suspend_vendor_action') }}</button>
        </x-slot>
    </x-modal>

    {{-- ─── Approve vendor modal ─────────────────────────────────────────────── --}}
    <x-modal id="approve-modal" title="{{ __('admin.vendors.approve_vendor_title') }}" size="sm">
        <p class="text-sm text-gray-600">
            {{ \Illuminate\Support\Str::before(__('admin.vendors.approve_vendor_confirm'), ':name') }}<strong id="approve-vendor-name"></strong>{{ \Illuminate\Support\Str::after(__('admin.vendors.approve_vendor_confirm'), ':name') }}
        </p>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.vendors.cancel') }}</button>
            <button type="button" id="approve-confirm-btn" class="btn btn-success btn-sm">{{ __('admin.vendors.approve_vendor_action') }}</button>
        </x-slot>
    </x-modal>

    {{-- ─── Reject vendor modal ──────────────────────────────────────────────── --}}
    <x-modal id="reject-modal" title="{{ __('admin.vendors.reject_vendor_title') }}" size="sm">
        <p class="text-sm text-gray-600 mb-3">{{ \Illuminate\Support\Str::before(__('admin.vendors.reject_vendor_confirm'), ':name') }}<strong id="reject-vendor-name"></strong>{{ \Illuminate\Support\Str::after(__('admin.vendors.reject_vendor_confirm'), ':name') }}</p>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('admin.vendors.rejection_reason') }} <span class="text-danger-500">*</span>
            </label>
            <textarea id="reject-reason" class="form-input w-full resize-none" rows="3"
                placeholder="{{ __('admin.vendors.reason_reject_placeholder') }}"></textarea>
            <p id="reject-reason-error" class="hidden text-xs text-danger-600 mt-1">
                {{ __('admin.vendors.reason_required_hint') }}
            </p>
        </div>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.vendors.cancel') }}</button>
            <button type="button" id="reject-confirm-btn" class="btn btn-danger btn-sm">{{ __('admin.vendors.reject_vendor_action') }}</button>
        </x-slot>
    </x-modal>

    {{-- ─── Reactivate vendor modal ──────────────────────────────────────────── --}}
    <x-modal id="reactivate-modal" title="{{ __('admin.vendors.reactivate_vendor_title') }}" size="sm">
        <p class="text-sm text-gray-600">
            {{ \Illuminate\Support\Str::before(__('admin.vendors.reactivate_vendor_confirm'), ':name') }}<strong id="reactivate-vendor-name"></strong>{{ \Illuminate\Support\Str::after(__('admin.vendors.reactivate_vendor_confirm'), ':name') }}
        </p>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.vendors.cancel') }}</button>
            <button type="button" id="reactivate-confirm-btn" class="btn btn-success btn-sm">{{ __('admin.vendors.reactivate_vendor_action') }}</button>
        </x-slot>
    </x-modal>

    {{-- ─── Bulk Suspend modal ───────────────────────────────────────────────── --}}
    <x-modal id="bulk-suspend-modal" title="{{ __('admin.vendors.bulk_suspend_title') }}" size="sm">
        <p class="text-sm text-gray-600 mb-3">
            {{ \Illuminate\Support\Str::before(__('admin.vendors.bulk_suspend_confirm'), ':count') }}<strong><span id="bulk-suspend-count">0</span>{{ \Illuminate\Support\Str::after(__('admin.vendors.bulk_suspend_confirm'), ':count') }}</strong>
        </p>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('admin.vendors.suspension_reason') }} <span class="text-danger-500">*</span>
            </label>
            <textarea id="bulk-suspend-reason" class="form-input w-full resize-none" rows="3"
                placeholder="{{ __('admin.vendors.reason_bulk_suspend_placeholder') }}"></textarea>
            <p id="bulk-suspend-reason-error" class="hidden text-xs text-danger-600 mt-1">
                {{ __('admin.vendors.reason_required_hint') }}
            </p>
        </div>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.vendors.cancel') }}</button>
            <button type="button" id="bulk-suspend-confirm-btn" class="btn btn-danger btn-sm">{{ __('admin.vendors.suspend_all') }}</button>
        </x-slot>
    </x-modal>

    {{-- ─── Bulk Reactivate modal ────────────────────────────────────────────── --}}
    <x-modal id="bulk-reactivate-modal" title="{{ __('admin.vendors.bulk_reactivate_title') }}" size="sm">
        <p class="text-sm text-gray-600">
            {{ \Illuminate\Support\Str::before(__('admin.vendors.bulk_reactivate_confirm'), ':count') }}<strong><span id="bulk-reactivate-count">0</span>{{ \Illuminate\Support\Str::after(__('admin.vendors.bulk_reactivate_confirm'), ':count') }}</strong>
        </p>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.vendors.cancel') }}</button>
            <button type="button" id="bulk-reactivate-confirm-btn" class="btn btn-success btn-sm">{{ __('admin.vendors.reactivate_all') }}</button>
        </x-slot>
    </x-modal>

    {{-- ─── Generic bulk modal (place_hold / assign_manager / export) ──────────── --}}
    <x-modal id="bulk-misc-modal" title="{{ __('admin.vendors.bulk_action_title') }}" size="sm">
        <div class="space-y-3">
            <div id="bulk-admin-select" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendors.assign_to_admin') }}</label>
                <select name="admin_id" id="bulk-admin-id" class="form-input w-full">
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
            <div id="bulk-reason-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.reason') }}</label>
                <textarea id="bulk-reason" class="form-input w-full resize-none" rows="3"></textarea>
            </div>
        </div>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.vendors.cancel') }}</button>
            <button type="button" id="bulk-misc-confirm-btn" class="btn btn-primary btn-sm">{{ __('admin.vendors.confirm') }}</button>
        </x-slot>
    </x-modal>

@endsection

@push('scripts')
    @vite(['resources/js/admin/vendors.js'])
@endpush
