@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/transactions.js'])
@endpush

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    copiedToClipboard: @json(__('admin.transactions.copied_to_clipboard')),
    copyFailed: @json(__('admin.transactions.copy_failed')),
    approveRefundConfirm: @json(__('admin.transactions.approve_refund_confirm')),
    refundApproved: @json(__('admin.transactions.refund_approved')),
    approvalFailed: @json(__('admin.transactions.approval_failed')),
    refundAmountLabel: @json(__('admin.transactions.refund_amount_label')),
    rejecting: @json(__('admin.transactions.rejecting')),
    refundRejected: @json(__('admin.transactions.refund_rejected')),
    rejectionFailed: @json(__('admin.transactions.rejection_failed')),
    confirmReject: @json(__('admin.transactions.confirm_reject')),
});
</script>
@endpush

@section('title', __('admin.transactions.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.transactions.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.transactions.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-export-dropdown />
            <a href="{{ route('admin.transactions.refunds.index') }}" class="btn btn-secondary btn-sm">
                {{ __('admin.transactions.refund_queue') }}
                @if($stats['pending_refunds'] > 0)
                    <span
                        class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-700">{{ $stats['pending_refunds'] }}</span>
                @endif
            </a>
        </div>
    </div>

    {{-- ─── Stats ──────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.transactions.volume_today') }}" :value="'$' . number_format($stats['volume_today'] / 100, 2)"
            iconBg="bg-green-100 text-green-600" />
        <x-stat-card title="{{ __('admin.transactions.succeeded_today') }}" :value="number_format($stats['succeeded_today'])"
            iconBg="bg-success-100 text-success-600" />
        <x-stat-card title="{{ __('admin.transactions.failed_today') }}" :value="number_format($stats['failed_today'])"
            iconBg="{{ $stats['failed_today'] > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.transactions.pending_refunds') }}" :value="number_format($stats['pending_refunds'])"
            iconBg="{{ $stats['pending_refunds'] > 0 ? 'bg-warning-100 text-warning-600' : 'bg-gray-100 text-gray-400' }}" />
    </div>

    {{-- ─── Pending Refunds Alert ───────────────────────────────────────────────── --}}
    @if($stats['pending_refunds'] > 0)
        <div
            class="mb-5 flex items-center gap-3 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
            <svg class="w-5 h-5 flex-shrink-0 text-warning-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            <span>
                <strong>{{ number_format($stats['pending_refunds']) }}</strong>
                {{ trans_choice('admin.transactions.refunds_pending_approval', $stats['pending_refunds']) }}
                <a href="{{ route('admin.transactions.refunds.index') }}" class="underline font-medium">{{ __('admin.transactions.review_now') }}</a>
            </span>
        </div>
    @endif

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.transactions.search_gateway_order') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm"
                    placeholder="{{ __('admin.transactions.search_gateway_order_placeholder') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.type') }}</label>
                <select id="filter-type" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.transactions.all_types') }}</option>
                    <option value="authorization">{{ __('admin.transactions.authorization') }}</option>
                    <option value="capture">{{ __('admin.transactions.capture') }}</option>
                    <option value="sale">{{ __('admin.transactions.sale') }}</option>
                    <option value="refund">{{ __('admin.transactions.refund') }}</option>
                    <option value="void">{{ __('admin.transactions.void') }}</option>
                    <option value="chargeback">{{ __('admin.transactions.chargeback') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.transactions.all_statuses') }}</option>
                    <option value="pending">{{ __('common.pending') }}</option>
                    <option value="succeeded">{{ __('admin.transactions.succeeded') }}</option>
                    <option value="failed">{{ __('admin.finance.failed') }}</option>
                    <option value="cancelled">{{ __('common.cancelled') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.transactions.gateway') }}</label>
                <select id="filter-gateway" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.transactions.all_gateways') }}</option>
                    @foreach($gateways as $gw)
                        <option value="{{ $gw }}">{{ $gw }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.transactions.payment_method') ?? 'Method' }}</label>
                <select id="filter-payment-method" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.all') }}</option>
                    <option value="cod">{{ __('admin.orders.payment_cod') }}</option>
                    <option value="wallet">{{ __('admin.orders.payment_wallet') }}</option>
                    <option value="bank_transfer">{{ __('admin.orders.payment_bank_transfer') }}</option>
                    <option value="thawani">Thawani</option>
                    <option value="paytabs">Paytabs</option>
                    <option value="stripe">Stripe</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.transactions.amount_min') }}</label>
                <input type="number" step="0.01" min="0" id="filter-amount-min" class="form-input w-full text-sm"
                    placeholder="0.00" dir="ltr">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.transactions.amount_max') }}</label>
                <input type="number" step="0.01" min="0" id="filter-amount-max" class="form-input w-full text-sm"
                    placeholder="9999.00" dir="ltr">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.from') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm" dir="ltr">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.to') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm" dir="ltr">
            </div>
            <div>
                <button type="button" id="clear-filters" class="btn btn-secondary btn-sm">{{ __('common.clear') }}</button>
            </div>
        </div>
    </x-card>

    {{-- ─── DataTable ──────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="transactions-table" class="w-full" style="width:100%">
                <thead>
                    <tr>
                        <th>{{ __('admin.transactions.gateway_tx_id') }}</th>
                        <th>{{ __('admin.transactions.order') }}</th>
                        <th>{{ __('admin.transactions.customer') }}</th>
                        <th>{{ __('common.type') }}</th>
                        <th>{{ __('admin.transactions.gateway') }}</th>
                        <th>{{ __('common.amount') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('admin.transactions.failure_code') }}</th>
                        <th>{{ __('admin.transactions.processed_at') }}</th>
                        <th>{{ __('common.created_at') }}</th>
                        <th class="no-sort"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

@endsection
