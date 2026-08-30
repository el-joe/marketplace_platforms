@extends('layouts.partner')

@section('title', __('partner.finance.title'))
@section('page-title', __('partner.finance.title'))

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    @if(session('locale', 'ar') === 'ar')
        <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            flatpickr('#date-range-picker', {
                mode: 'range',
                locale: '{{ session('locale', 'ar') }}',
                dateFormat: 'Y-m-d',
                defaultDate: [
                    '{{ $dateFrom->format('Y-m-d') }}',
                    '{{ $dateTo->format('Y-m-d') }}',
                ],
                onClose: function (selectedDates) {
                    if (selectedDates.length === 2) {
                        document.getElementById('date_from').value = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        document.getElementById('date_to').value   = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                        document.getElementById('filter-form').submit();
                    }
                },
            });

            // Sale row popover toggle
            document.querySelectorAll('[data-popover-trigger]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const id = btn.getAttribute('data-popover-trigger');
                    document.querySelectorAll('[data-popover]').forEach(function (p) {
                        if (p.getAttribute('data-popover') !== id) p.classList.add('hidden');
                    });
                    document.querySelector('[data-popover="' + id + '"]').classList.toggle('hidden');
                });
            });

            document.addEventListener('click', function () {
                document.querySelectorAll('[data-popover]').forEach(function (p) {
                    p.classList.add('hidden');
                });
            });
        });
    </script>
@endpush

@section('content')

    @php $currency ??= auth()->guard('vendor')->user()->vendor?->country?->currency_code ?? '' @endphp

    {{-- Summary stat cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.finance.total_sales') }}</p>
            <p class="text-2xl font-bold text-green-600">
                {{ number_format($totalSales, 2) }}
                <span class="text-sm font-normal text-gray-400">{{ $currency }}</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ __('partner.finance.total_sales_desc') }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.finance.total_refunds') }}</p>
            <p class="text-2xl font-bold text-red-500">
                {{ number_format($totalRefunds, 2) }}
                <span class="text-sm font-normal text-gray-400">{{ $currency }}</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ __('partner.finance.total_refunds_desc') }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.finance.total_paid_out') }}</p>
            <p class="text-2xl font-bold text-blue-600">
                {{ number_format($totalPaidOut, 2) }}
                <span class="text-sm font-normal text-gray-400">{{ $currency }}</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ __('partner.finance.total_paid_out_desc') }}</p>
        </div>

    </div>

    {{-- Transaction log --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

        {{-- Header + filters --}}
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-gray-800">{{ __('partner.finance.transaction_log') }}</h2>
                <span class="text-xs text-gray-400">{{ $transactions->total() }} {{ __('partner.finance.transaction_count') }}</span>
            </div>

            <form id="filter-form" method="GET" action="{{ route('partner.finance.transactions') }}"
                  class="mt-3 flex flex-wrap gap-3 items-end">
                <input type="hidden" id="date_from" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}">
                <input type="hidden" id="date_to"   name="date_to"   value="{{ $dateTo->format('Y-m-d') }}">

                {{-- Date range picker --}}
                <div class="flex-1 min-w-48">
                    <label class="block text-xs text-gray-500 mb-1">{{ __('partner.finance.date_range') }}</label>
                    <input id="date-range-picker" type="text" readonly
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-700 bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-200"
                           placeholder="{{ __('partner.finance.choose_date_range') }}">
                </div>

                {{-- Type filter --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('common.type') }}</label>
                    <select name="type" onchange="this.form.submit()"
                            class="border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="all"     @selected($type === 'all')>{{ __('common.all') }}</option>
                        <option value="sales"   @selected($type === 'sales')>{{ __('partner.finance.sales') }}</option>
                        <option value="refunds" @selected($type === 'refunds')>{{ __('partner.finance.refunds') }}</option>
                        <option value="payouts" @selected($type === 'payouts')>{{ __('partner.finance.payouts') }}</option>
                    </select>
                </div>
            </form>
        </div>

        @if($transactions->isEmpty())
            <div class="py-16 text-center">
                <div class="text-4xl mb-3">📋</div>
                <h3 class="font-semibold text-gray-800 mb-1">{{ __('partner.finance.no_transactions_found') }}</h3>
                <p class="text-sm text-gray-400">{{ __('partner.finance.try_adjusting_filters') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr class="text-xs text-gray-500 uppercase">
                            <th class="text-right py-3 px-5 font-medium">{{ __('common.date') }}</th>
                            <th class="py-3 px-4 text-center font-medium">{{ __('common.type') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('common.reference') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('common.description') }}</th>
                            <th class="py-3 px-4 text-left font-medium">{{ __('common.amount') }}</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($transactions as $index => $txn)
                            @php
                                $isSale   = $txn['type'] === 'sale';
                                $isRefund = $txn['type'] === 'refund';
                                $isPayout = $txn['type'] === 'payout';

                                $typeBadge = match($txn['type']) {
                                    'sale'   => ['bg-green-100 text-green-700', __('partner.finance.sales')],
                                    'refund' => ['bg-red-100 text-red-700',   __('partner.finance.refund')],
                                    'payout' => ['bg-blue-100 text-blue-700', __('partner.finance.payout')],
                                    default  => ['bg-gray-100 text-gray-600', $txn['type']],
                                };

                                $amountPositive = $txn['amount'] > 0;
                                $amountCls      = $amountPositive ? 'text-green-600' : 'text-red-500';
                                $amountSign     = $amountPositive ? '+' : '−';
                                $amountAbs      = abs($txn['amount']);
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">

                                {{-- Date --}}
                                <td class="py-3 px-5 text-xs text-gray-500 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($txn['date'])->format('Y/m/d') }}
                                </td>

                                {{-- Type badge --}}
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeBadge[0] }}">
                                        {{ $typeBadge[1] }}
                                    </span>
                                </td>

                                {{-- Reference --}}
                                <td class="py-3 px-4">
                                    @if($isPayout && $txn['payout_number'])
                                        <a href="{{ route('partner.payouts.show', $txn['payout_number']) }}"
                                           class="font-mono text-blue-600 hover:underline text-xs font-medium">
                                            {{ $txn['reference'] }}
                                        </a>
                                    @else
                                        <span class="font-mono text-xs text-gray-700">{{ $txn['reference'] }}</span>
                                    @endif
                                </td>

                                {{-- Description --}}
                                <td class="py-3 px-4 text-xs text-gray-600">
                                    {{ $txn['description'] }}
                                </td>

                                {{-- Amount --}}
                                <td class="py-3 px-4 text-left font-bold {{ $amountCls }} whitespace-nowrap">
                                    {{ $amountSign }} {{ number_format($amountAbs, 2) }}
                                    <span class="text-xs font-normal text-gray-400">{{ $currency }}</span>
                                </td>

                                {{-- Actions: popover for sales, download for payouts --}}
                                <td class="py-3 px-4 relative">
                                    @if($isSale)
                                        @php $popId = "pop-$index" @endphp
                                        <button type="button" data-popover-trigger="{{ $popId }}"
                                                class="text-xs text-gray-400 hover:text-blue-600 transition-colors px-2 py-1 rounded-lg hover:bg-blue-50">
                                            {{ __('partner.finance.details') }} ↓
                                        </button>
                                        {{-- Popover --}}
                                        <div data-popover="{{ $popId }}"
                                             class="hidden absolute left-0 z-20 mt-1 w-64 bg-white border border-gray-200 rounded-2xl shadow-lg p-4 text-xs text-right"
                                             style="top: 100%">
                                            <p class="font-semibold text-gray-800 mb-3">{{ __('partner.finance.profit_details') }}</p>
                                            <div class="space-y-2">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">{{ __('partner.finance.total_sales') }}</span>
                                                    <span class="font-medium text-gray-900">
                                                        {{ number_format(($txn['gross'] ?? 0), 2) }} {{ $currency }}
                                                    </span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">{{ __('partner.finance.platform_commission') }}</span>
                                                    <span class="text-red-500">
                                                        − {{ number_format(($txn['commission'] ?? 0), 2) }} {{ $currency }}
                                                    </span>
                                                </div>
                                                <div class="flex justify-between border-t border-gray-100 pt-2">
                                                    <span class="font-semibold text-gray-700">{{ __('partner.finance.net_profit') }}</span>
                                                    <span class="font-bold text-green-600">
                                                        {{ number_format(($txn['net'] ?? 0), 2) }} {{ $currency }}
                                                    </span>
                                                </div>
                                                <div class="border-t border-gray-100 pt-2">
                                                    @if($txn['payout_number'])
                                                        <p class="text-gray-500">{{ __('partner.finance.settlement_status') }}:</p>
                                                        <a href="{{ route('partner.payouts.show', $txn['payout_number']) }}"
                                                           class="text-blue-600 hover:underline font-medium">
                                                            {{ __('partner.finance.included_in') }} {{ $txn['payout_number'] }} →
                                                        </a>
                                                    @else
                                                        <p class="text-gray-400">{{ __('partner.finance.not_settled_yet') }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                    @elseif($isPayout && $txn['receipt_url'])
                                        <a href="{{ $txn['receipt_url'] }}" target="_blank" rel="noopener noreferrer"
                                           title="{{ __('partner.finance.download_receipt') }}"
                                           class="inline-flex items-center text-gray-400 hover:text-blue-600 transition-colors px-2 py-1 rounded-lg hover:bg-blue-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </a>
                                    @endif
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($transactions->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $transactions->links() }}
                </div>
            @endif
        @endif

    </div>

@endsection
