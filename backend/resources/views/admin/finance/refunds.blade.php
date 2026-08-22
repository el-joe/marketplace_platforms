@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/transactions.js'])
@endpush

@section('title', __('admin.finance.refund_queue'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.transactions.index') }}" class="hover:text-primary-600">{{ __('admin.transactions.title') }}</a>
                <span>/</span>
                <span class="text-gray-800">{{ __('admin.finance.refund_queue') }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.finance.refund_queue') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.finance.refund_queue_subtitle') }}</p>
        </div>
    </div>

    {{-- ─── Pending Alert ───────────────────────────────────────────────────────── --}}
    @if($pendingCount > 0)
        <div
            class="mb-5 flex items-center gap-3 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
            <svg class="w-5 h-5 flex-shrink-0 text-warning-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            <span><strong>{{ number_format($pendingCount) }}</strong> {{ trans_choice('admin.finance.refunds_awaiting_approval', $pendingCount) }}</span>
        </div>
    @endif

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.finance.search_order_customer') }}</label>
                <input type="text" id="refund-search-input" class="form-input w-full text-sm"
                    placeholder="{{ __('admin.finance.search_order_customer_placeholder') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="refund-filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.finance.all_statuses') }}</option>
                    <option value="pending" selected>{{ __('common.pending') }}</option>
                    <option value="approved">{{ __('common.approved') }}</option>
                    <option value="processing">{{ __('admin.finance.processing') }}</option>
                    <option value="completed">{{ __('common.completed') }}</option>
                    <option value="rejected">{{ __('common.rejected') }}</option>
                    <option value="failed">{{ __('admin.finance.failed') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.from') }}</label>
                <input type="date" id="refund-filter-date-from" class="form-input w-full text-sm" dir="ltr">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.to') }}</label>
                <input type="date" id="refund-filter-date-to" class="form-input w-full text-sm" dir="ltr">
            </div>
            <div>
                <button type="button" id="refund-clear-filters" class="btn btn-secondary btn-sm">{{ __('common.clear') }}</button>
            </div>
        </div>
    </x-card>

    {{-- ─── DataTable ──────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="refunds-table" class="w-full" style="width:100%">
                <thead>
                    <tr>
                        <th>{{ __('admin.finance.order_id') }}</th>
                        <th>{{ __('admin.finance.customer') }}</th>
                        <th>{{ __('common.amount') }}</th>
                        <th>{{ __('admin.finance.refund_reason') }}</th>
                        <th>{{ __('common.type') }}</th>
                        <th>{{ __('admin.finance.vendor_charged') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th class="no-sort">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Reject Modal ───────────────────────────────────────────────────────── --}}
    <x-modal id="refund-reject-modal" title="{{ __('admin.finance.reject_refund') }}" size="sm">
        <div class="space-y-4">
            <p class="text-sm text-gray-600">{{ __('admin.finance.reject_refund_hint') }}</p>
            <p id="refund-reject-amount" class="text-sm font-semibold text-gray-800"></p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.reason') }} <span
                        class="text-gray-400 font-normal">({{ __('common.optional') }})</span></label>
                <textarea id="refund-reject-reason" rows="3" class="form-input w-full text-sm"
                    placeholder="{{ __('admin.finance.reject_refund_placeholder') }}"></textarea>
            </div>
        </div>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary"
                onclick="$('#refund-reject-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-refund-reject-btn" class="btn btn-danger">{{ __('admin.finance.confirm_reject') }}</button>
        </x-slot>
    </x-modal>

@endsection