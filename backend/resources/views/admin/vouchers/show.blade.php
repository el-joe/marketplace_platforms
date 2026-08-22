@extends('layouts.admin')

@section('title', __('admin.vouchers_section.voucher_detail') . ': ' . e($voucher->code))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
    @php
        $eligibilityLabels = [
            'all' => __('admin.vouchers_section.eligibility_all'),
            'new_customers' => __('admin.vouchers_section.eligibility_new'),
            'specific_users' => __('admin.vouchers_section.eligibility_users'),
        ];
    @endphp

    <div class="space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <code class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">{{ $voucher->code }}</code>
                    <span class="badge {{ $voucher->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                        {{ $voucher->is_active ? __('admin.vouchers_section.active') : __('admin.vouchers_section.inactive') }}
                    </span>
                </div>
                <h1 class="text-xl font-semibold text-gray-900 mt-1">{{ $voucher->name }}</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.vouchers.index') }}" class="btn btn-secondary">{{ __('admin.vouchers_section.back_to_list') }}</a>
                <a href="{{ route('admin.vouchers.export', $voucher->id) }}" class="btn btn-secondary">{{ __('admin.vouchers_section.export_csv') }}</a>
                <a href="{{ route('admin.vouchers.edit', $voucher->id) }}" class="btn btn-secondary">{{ __('admin.vouchers_section.edit') }}</a>
                <button type="button" id="btn-toggle" data-url="{{ route('admin.vouchers.toggle', $voucher->id) }}" class="btn btn-secondary">
                    {{ __('admin.vouchers_section.toggle') }}
                </button>
                <form method="POST" action="{{ route('admin.vouchers.destroy', $voucher->id) }}" id="delete-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" id="btn-delete" class="btn btn-danger">{{ __('admin.vouchers_section.delete') }}</button>
                </form>
            </div>
        </div>

        {{-- Voucher detail card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.vouchers_section.voucher_detail') }}</h2>
            </div>
            <div class="px-5 py-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.code') }}</p>
                    <p class="font-medium text-gray-900 font-mono">{{ $voucher->code }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.name') }}</p>
                    <p class="font-medium text-gray-900">{{ $voucher->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.amount') }}</p>
                    <p class="font-medium text-gray-900">{{ number_format($voucher->amount) }} {{ $voucher->currency_code }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.country') }}</p>
                    <p class="font-medium text-gray-900">{{ $voucher->country->name_en ?? __('admin.vouchers_section.all_countries') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.customer_eligibility') }}</p>
                    <p class="font-medium text-gray-900">{{ $eligibilityLabels[$voucher->customer_eligibility] ?? $voucher->customer_eligibility }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.redeemed_limit') }}</p>
                    <p class="font-medium text-gray-900">{{ number_format($voucher->times_redeemed) }} / {{ $voucher->usage_limit_total ? number_format($voucher->usage_limit_total) : '∞' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.per_customer_limit') }}</p>
                    <p class="font-medium text-gray-900">{{ number_format($voucher->usage_limit_per_customer) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.valid_from') }}</p>
                    <p class="font-medium text-gray-900">{{ optional($voucher->valid_from)->format('Y-m-d H:i') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.valid_until') }}</p>
                    <p class="font-medium text-gray-900">{{ optional($voucher->valid_until)->format('Y-m-d H:i') }}</p>
                </div>
                @if($voucher->description)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.description') }}</p>
                        <p class="font-medium text-gray-900">{{ $voucher->description }}</p>
                    </div>
                @endif
                @if($voucher->title_en || $voucher->title_ar)
                    <div>
                        <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.title_en') }}</p>
                        <p class="font-medium text-gray-900">{{ $voucher->title_en ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.title_ar') }}</p>
                        <p class="font-medium text-gray-900" dir="rtl">{{ $voucher->title_ar ?: '—' }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.created_by') }}</p>
                    <p class="font-medium text-gray-900">{{ $voucher->createdByAdmin->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.created_at') }}</p>
                    <p class="font-medium text-gray-900">{{ optional($voucher->created_at)->format('Y-m-d H:i') }}</p>
                </div>
            </div>
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.times_redeemed') }}</p>
                <p class="text-xl font-semibold text-gray-900 mt-1">{{ number_format($redemptionStats['total_redeemed']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.unique_customers') }}</p>
                <p class="text-xl font-semibold text-gray-900 mt-1">{{ number_format($redemptionStats['unique_customers']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                <p class="text-xs text-gray-500">{{ __('admin.vouchers_section.total_value_credited') }}</p>
                <p class="text-xl font-semibold text-gray-900 mt-1">
                    {{ number_format($redemptionStats['total_credited']) }} {{ $redemptionStats['currency_code'] }}
                </p>
            </div>
        </div>

        {{-- Redemptions DataTable --}}
        @php
            $redemptionColumns = [
                ['title' => __('admin.vouchers_section.customer_name'), 'data' => 'customer_name', 'name' => 'customer_name'],
                ['title' => __('admin.vouchers_section.customer_email'), 'data' => 'customer_email', 'name' => 'customer_email'],
                ['title' => __('admin.vouchers_section.amount'), 'data' => 'amount', 'name' => 'amount', 'className' => 'text-end', 'searchable' => false],
                ['title' => __('admin.vouchers_section.currency'), 'data' => 'currency_code', 'name' => 'currency_code', 'searchable' => false],
                ['title' => __('admin.vouchers_section.wallet_balance_after'), 'data' => 'wallet_balance_after', 'name' => 'wallet_balance_after', 'className' => 'text-end', 'searchable' => false],
                ['title' => __('admin.vouchers_section.redeemed_at'), 'data' => 'redeemed_at', 'name' => 'redeemed_at', 'searchable' => false, 'render' => 'Renderers.date'],
            ];
        @endphp

        <x-table.datatable id="voucher-redemptions-table"
            url="{{ route('admin.vouchers.redemptions.data', $voucher->id) }}"
            :columns="$redemptionColumns" :page-length="25" :order="[[5, 'desc']]" />

    </div>
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            toggleFailed: @json(__('admin.vouchers_section.toggle_failed')),
            deleteVoucherTitle: @json(__('admin.vouchers_section.delete_voucher_title')),
            deleteVoucherConfirm: @json(__('admin.vouchers_section.delete_voucher_confirm', ['code' => $voucher->code])),
        });

        document.getElementById('btn-toggle')?.addEventListener('click', function () {
            $.ajax({
                url: this.dataset.url,
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(function () {
                    window.location.reload();
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.toggleFailed);
                });
        });

        document.getElementById('delete-form')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(window.TRANSLATIONS.deleteVoucherConfirm, { title: window.TRANSLATIONS.deleteVoucherTitle })
                : confirm(window.TRANSLATIONS.deleteVoucherConfirm);
            if (confirmed) this.submit();
        });
    </script>
@endpush
