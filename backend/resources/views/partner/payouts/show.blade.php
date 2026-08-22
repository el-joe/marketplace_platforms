@extends('layouts.partner')

@php
    $statusMap = [
        'pending' => ['bg-yellow-100 text-yellow-700', __('partner.payouts.status_pending')],
        'approved' => ['bg-blue-100 text-blue-700', __('partner.payouts.status_approved')],
        'processing' => ['bg-indigo-100 text-indigo-700', __('partner.payouts.status_processing')],
        'completed' => ['bg-green-100 text-green-700', __('partner.payouts.status_completed')],
        'failed' => ['bg-red-100 text-red-700', __('partner.payouts.status_failed')],
        'on_hold' => ['bg-gray-100 text-gray-600', __('partner.payouts.status_on_hold')],
    ];
    [$statusCls, $statusLabel] = $statusMap[$payout->status->value] ?? ['bg-gray-100 text-gray-500', $payout->status->value];

    $methodLabels = [
        'bank_transfer' => __('partner.payouts.method_bank_transfer'),
        'wallet' => __('partner.payouts.method_wallet'),
        'paypal' => 'PayPal',
    ];

    $subOrderItems = $payout->items->where('item_type', 'sub_order');
    $promotionFeeItems = $payout->items->where('item_type', 'promotion_fee');
@endphp

@section('title', __('partner.payouts.payout_details_title', ['number' => $payout->payout_number]))
@section('page-title', __('partner.payouts.payout_details'))

@section('content')

    {{-- Back --}}
    <div class="mb-4">
        <a href="{{ route('partner.payouts.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            {{ __('partner.payouts.back_to_payouts') }}
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- ── LEFT ── --}}
        <div class="lg:col-span-8 space-y-5">

            {{-- Header card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h2 class="font-bold text-gray-900 text-lg font-mono">{{ $payout->payout_number }}</h2>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusCls }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500">
                            {{ __('partner.payouts.period') }}:
                            <span class="font-medium text-gray-700">
                                {{ $payout->period_start?->format('d M Y') }}
                                —
                                {{ $payout->period_end?->format('d M Y') }}
                            </span>
                        </p>
                    </div>
                    @if($payout->receipt_url)
                        <a href="{{ $payout->receipt_url }}" target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            {{ __('partner.payouts.download_receipt') }}
                        </a>
                    @endif
                </div>

                {{-- Amounts breakdown --}}
                <div class="bg-gray-50 rounded-xl p-4 space-y-2.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('partner.payouts.gross_sales') }}</span>
                        <span class="font-medium text-gray-900">
                            {{ number_format($payout->gross_sales / 100, 2) }} {{ $payout->currency }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('partner.payouts.platform_commission') }}</span>
                        <span class="text-red-500">
                            − {{ number_format($payout->commission / 100, 2) }} {{ $payout->currency }}
                        </span>
                    </div>
                    @if($payout->refunds_deducted)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ __('partner.payouts.refunds_deducted') }}</span>
                            <span class="text-red-500">
                                − {{ number_format($payout->refunds_deducted / 100, 2) }} {{ $payout->currency }}
                            </span>
                        </div>
                    @endif
                    @if($payout->chargebacks_deducted)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ __('partner.payouts.chargebacks_deducted') }}</span>
                            <span class="text-red-500">
                                − {{ number_format($payout->chargebacks_deducted / 100, 2) }} {{ $payout->currency }}
                            </span>
                        </div>
                    @endif
                    @if($payout->storage_fees)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ __('partner.payouts.storage_fees') }}</span>
                            <span class="text-red-500">
                                − {{ number_format($payout->storage_fees / 100, 2) }} {{ $payout->currency }}
                            </span>
                        </div>
                    @endif
                    @if($payout->ad_fees)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ __('partner.payouts.ad_fees') }}</span>
                            <span class="text-red-500">
                                − {{ number_format($payout->ad_fees / 100, 2) }} {{ $payout->currency }}
                            </span>
                        </div>
                    @endif
                    @if($payout->other_adjustments)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ __('partner.payouts.other_adjustments') }}</span>
                            <span class="{{ $payout->other_adjustments < 0 ? 'text-red-500' : 'text-green-600' }}">
                                {{ $payout->other_adjustments < 0 ? '−' : '+' }}
                                {{ number_format(abs($payout->other_adjustments) / 100, 2) }} {{ $payout->currency }}
                            </span>
                        </div>
                    @endif
                    <div class="border-t border-gray-200 pt-2.5 flex justify-between">
                        <span class="font-semibold text-gray-800">{{ __('partner.payouts.net_amount_transferred') }}</span>
                        <span class="font-bold text-xl text-gray-900">
                            {{ number_format($payout->net_amount / 100, 2) }} {{ $payout->currency }}
                        </span>
                    </div>
                </div>

                @if($payout->failed_reason)
                    <div class="mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                        <p class="text-xs font-semibold text-red-700 mb-1">{{ __('partner.payouts.failure_reason') }}</p>
                        <p class="text-sm text-red-800">{{ $payout->failed_reason }}</p>
                    </div>
                @endif
            </div>

            {{-- Items table --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">{{ __('partner.payouts.included_orders') }}</h3>
                    <span class="text-xs text-gray-400">{{ __('partner.payouts.order_count', ['count' => $subOrderItems->count()]) }}</span>
                </div>

                @if($subOrderItems->isEmpty())
                    <div class="py-10 text-center">
                        <p class="text-sm text-gray-400">{{ __('partner.payouts.no_items_for_payout') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr class="text-xs text-gray-500 uppercase">
                                    <th class="text-right py-3 px-5 font-medium">{{ __('partner.payouts.order_number_header') }}</th>
                                    <th class="py-3 px-4 text-center font-medium">{{ __('partner.payouts.total') }}</th>
                                    <th class="py-3 px-4 text-center font-medium">{{ __('partner.payouts.commission') }}</th>
                                    <th class="py-3 px-4 text-center font-medium">{{ __('partner.payouts.net') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($subOrderItems as $item)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="py-3 px-5">
                                            <span class="font-mono text-xs text-gray-700">
                                                {{ $item->subOrder?->sub_order_number ?? '#' . $item->sub_order_id }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center text-gray-700">
                                            {{ number_format($item->gross / 100, 2) }}
                                        </td>
                                        <td class="py-3 px-4 text-center text-red-400 text-xs">
                                            − {{ number_format($item->commission / 100, 2) }}
                                        </td>
                                        <td class="py-3 px-4 text-center font-semibold text-gray-900">
                                            {{ number_format($item->net / 100, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-gray-200 bg-gray-50">
                                <tr class="text-sm font-semibold text-gray-800">
                                    <td class="py-3 px-5">{{ __('partner.payouts.total') }}</td>
                                    <td class="py-3 px-4 text-center">
                                        {{ number_format($subOrderItems->sum('gross') / 100, 2) }}
                                    </td>
                                    <td class="py-3 px-4 text-center text-red-500">
                                        − {{ number_format($subOrderItems->sum('commission') / 100, 2) }}
                                    </td>
                                    <td class="py-3 px-4 text-center text-gray-900 font-bold">
                                        {{ number_format($subOrderItems->sum('net') / 100, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Influencer promotion fee deductions (collapsible) --}}
            @if($promotionFeeItems->isNotEmpty())
                <details class="bg-white rounded-2xl border border-gray-200 overflow-hidden group">
                    <summary class="px-5 py-4 border-b border-gray-100 flex items-center justify-between cursor-pointer select-none list-none">
                        <h3 class="font-semibold text-gray-800">{{ __('partner.payouts.influencer_promotion_fees') }}</h3>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-400">{{ __('partner.payouts.promotion_fee_count', ['count' => $promotionFeeItems->count()]) }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </summary>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr class="text-xs text-gray-500 uppercase">
                                    <th class="text-right py-3 px-5 font-medium">{{ __('partner.payouts.description') }}</th>
                                    <th class="py-3 px-4 text-center font-medium">{{ __('partner.payouts.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($promotionFeeItems as $item)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="py-3 px-5 text-gray-700">
                                            {{ $item->description }}
                                        </td>
                                        <td class="py-3 px-4 text-center text-red-500 font-medium">
                                            − {{ number_format(abs($item->net) / 100, 2) }} {{ $payout->currency }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-gray-200 bg-gray-50">
                                <tr class="text-sm font-semibold text-gray-800">
                                    <td class="py-3 px-5">{{ __('partner.payouts.total') }}</td>
                                    <td class="py-3 px-4 text-center text-red-500">
                                        − {{ number_format($promotionFeeItems->sum(fn($i) => abs($i->net)) / 100, 2) }} {{ $payout->currency }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </details>
            @endif

        </div>

        {{-- ── RIGHT SIDEBAR ── --}}
        <div class="lg:col-span-4 space-y-4">

            {{-- Payment details --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h4 class="font-semibold text-gray-800 text-sm mb-3">{{ __('partner.payouts.transfer_details') }}</h4>
                <div class="space-y-2.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('partner.payouts.payment_method') }}</span>
                        <span
                            class="font-medium">{{ $methodLabels[$payout->payout_method] ?? $payout->payout_method }}</span>
                    </div>
                    @if($payout->bankAccount)
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('partner.payouts.bank') }}</span>
                            <span class="font-medium">{{ $payout->bankAccount->bank_name }}</span>
                        </div>
                    @endif
                    @if($payout->gateway_reference)
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('partner.payouts.transfer_reference') }}</span>
                            <span class="font-mono text-xs text-gray-700">{{ $payout->gateway_reference }}</span>
                        </div>
                    @endif
                    @if($payout->processed_at)
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('partner.payouts.transfer_date') }}</span>
                            <span class="font-medium">{{ $payout->processed_at->format('d M Y') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Receipt download if available --}}
            @if($payout->receipt_url)
                <div class="bg-green-50 border border-green-200 rounded-2xl p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-semibold text-green-800 text-sm">{{ __('partner.payouts.receipt_available') }}</span>
                    </div>
                    <a href="{{ $payout->receipt_url }}" target="_blank" rel="noopener noreferrer"
                        class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold text-sm py-2.5 rounded-xl transition-colors">
                        {{ __('partner.payouts.download_pdf_receipt') }}
                    </a>
                </div>
            @endif

        </div>
    </div>

@endsection