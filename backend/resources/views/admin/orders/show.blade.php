@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/orders.js'])
@endpush

@section('title', __('admin.orders.order_number') . ' ' . $order->order_number)

@section('content')
    @php
        $addr = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : json_decode($order->shipping_address_snapshot ?? '{}', true);
        $currency = strtoupper($order->currency);
        $fmt = fn($amount) => $currency . ' ' . number_format($amount, 2);

        $statusColors = [
            'placed' => 'gray',
            'confirmed' => 'primary',
            'partially_shipped' => 'primary',
            'shipped' => 'primary',
            'partially_delivered' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'warning',
            'disputed' => 'danger',
        ];
        $subStatusColors = [
            'placed' => 'gray',
            'confirmed' => 'primary',
            'processing' => 'primary',
            'packed' => 'primary',
            'shipped' => 'primary',
            'out_for_delivery' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            'returned' => 'warning',
            'refunded' => 'warning',
        ];
        $payStatusColors = [
            'pending' => 'gray',
            'authorized' => 'primary',
            'captured' => 'success',
            'failed' => 'danger',
            'refunded' => 'warning',
            'partially_refunded' => 'warning',
        ];
        $txTypeColors = [
            'authorization' => 'primary',
            'capture' => 'success',
            'sale' => 'success',
            'refund' => 'warning',
            'void' => 'gray',
            'chargeback' => 'danger',
        ];

        $riskScore = $order->risk_score;
        $riskColor = $riskScore >= 70 ? 'danger' : ($riskScore >= 40 ? 'warning' : 'success');
        $riskBg = $riskScore >= 70 ? 'bg-red-50 border-red-200' : ($riskScore >= 40 ? 'bg-amber-50 border-amber-200' : 'bg-green-50 border-green-200');
        $riskText = $riskScore >= 70 ? 'text-red-700' : ($riskScore >= 40 ? 'text-amber-700' : 'text-green-700');

        $payMethodLabels = [
            'cod'           => __('admin.orders.payment_cod'),
            'wallet'        => __('admin.orders.payment_wallet'),
            'bank_transfer' => __('admin.orders.payment_bank_transfer'),
            'thawani'       => 'Thawani Payment',
            'paytabs'       => 'Paytabs',
            'stripe'        => 'Stripe',
            // Legacy keys (orders placed before gateway rebuild)
            'card'          => __('admin.orders.payment_card'),
            'bnpl'          => __('admin.orders.payment_bnpl'),
        ];

        $disputeReasonLabels = [
            'item_not_received' => __('admin.disputes_section.reason_item_not_received'),
            'item_damaged' => __('admin.disputes_section.reason_item_damaged'),
            'item_not_as_described' => __('admin.disputes_section.reason_item_not_as_described'),
            'counterfeit' => __('admin.disputes_section.reason_counterfeit'),
            'wrong_item' => __('admin.disputes_section.reason_wrong_item'),
            'quality_issue' => __('admin.disputes_section.reason_quality_issue'),
            'seller_unresponsive' => __('admin.disputes_section.reason_seller_unresponsive'),
            'refund_not_received' => __('admin.disputes_section.reason_refund_not_received'),
            'other' => __('admin.disputes_section.reason_other'),
        ];

        $refundReasonLabels = [
            'customer_request' => __('admin.orders.refund_reason_customer_request'),
            'out_of_stock' => __('admin.orders.refund_reason_out_of_stock'),
            'damaged' => __('admin.orders.refund_reason_damaged'),
            'wrong_item' => __('admin.orders.refund_reason_wrong_item'),
            'not_as_described' => __('admin.orders.refund_reason_not_as_described'),
            'late_delivery' => __('admin.orders.refund_reason_late_delivery'),
            'duplicate_order' => __('admin.orders.refund_reason_duplicate_order'),
            'other' => __('admin.orders.refund_reason_other'),
        ];

        $orderStatusLabels = [
            'placed' => __('common.order_status.placed'),
            'confirmed' => __('common.order_status.confirmed'),
            'partially_shipped' => __('admin.orders.status_partially_shipped'),
            'shipped' => __('common.order_status.shipped'),
            'partially_delivered' => __('admin.orders.status_partially_delivered'),
            'delivered' => __('common.order_status.delivered'),
            'completed' => __('common.order_status.completed'),
            'cancelled' => __('common.order_status.cancelled'),
            'refunded' => __('common.order_status.refunded'),
            'disputed' => __('common.order_status.disputed'),
        ];
        $subOrderStatusLabels = [
            'placed' => __('common.order_status.placed'),
            'confirmed' => __('common.order_status.confirmed'),
            'processing' => __('admin.orders.status_processing'),
            'packed' => __('admin.orders.status_packed'),
            'shipped' => __('common.order_status.shipped'),
            'out_for_delivery' => __('admin.orders.status_out_for_delivery'),
            'delivered' => __('common.order_status.delivered'),
            'completed' => __('common.order_status.completed'),
            'cancelled' => __('common.order_status.cancelled'),
            'returned' => __('admin.orders.status_returned'),
            'refunded' => __('common.order_status.refunded'),
        ];
        $allStatusLabels = $orderStatusLabels + $subOrderStatusLabels;

        $fulfillmentStatusLabels = [
            'pending' => __('common.pending'),
            'picked' => __('admin.orders.item_status_picked'),
            'packed' => __('admin.orders.status_packed'),
            'shipped' => __('common.order_status.shipped'),
            'delivered' => __('common.order_status.delivered'),
            'returned' => __('admin.orders.status_returned'),
            'cancelled' => __('common.order_status.cancelled'),
        ];

        $txTypeLabels = [
            'authorization' => __('admin.orders.tx_type_authorization'),
            'capture' => __('admin.orders.tx_type_capture'),
            'sale' => __('admin.orders.tx_type_sale'),
            'refund' => __('admin.orders.tx_type_refund'),
            'void' => __('admin.orders.tx_type_void'),
            'chargeback' => __('admin.orders.tx_type_chargeback'),
        ];

        $txStatusLabels = [
            'pending' => __('common.pending'),
            'succeeded' => __('common.succeeded'),
            'failed' => __('common.failed'),
            'cancelled' => __('common.cancelled'),
        ];

        $disputeStatusLabels = [
            'open' => __('admin.disputes_section.open'),
            'seller_responded' => __('admin.disputes_section.seller_responded'),
            'under_review' => __('admin.disputes_section.under_review'),
            'escalated' => __('admin.disputes_section.escalated'),
            'resolved' => __('admin.disputes_section.resolved'),
            'closed' => __('admin.disputes_section.closed'),
        ];

        $refundStatusLabels = [
            'pending' => __('common.pending'),
            'approved' => __('common.approved'),
            'processing' => __('common.processing'),
            'completed' => __('common.completed'),
            'failed' => __('common.failed'),
            'rejected' => __('common.rejected'),
        ];

        $payStatusLabels = [
            'pending' => __('common.pending'),
            'authorized' => __('admin.orders.payment_authorized'),
            'captured' => __('admin.orders.payment_captured'),
            'failed' => __('admin.orders.payment_failed'),
            'refunded' => __('common.order_status.refunded'),
            'partially_refunded' => __('admin.orders.payment_partially_refunded'),
        ];
    @endphp

    <script>window.ORDER_ID = '{{ $order->id }}';</script>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        {{-- MAIN COLUMN (2/3) --}}
        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 w-full space-y-6">

            {{-- ──────────────────────────────────── --}}
            {{-- Order Items (Sub-orders accordion) --}}
            {{-- ──────────────────────────────────── --}}
            <x-card title="{{ __('admin.orders.order_items') }}">
                <x-slot:actions>
                    <span class="text-sm text-gray-500">{{ $order->subOrders->count() }} {{ __('admin.orders.sellers_count') }}</span>
                </x-slot:actions>

                @forelse($order->subOrders as $subOrder)
                        <div class="border border-gray-200 rounded-xl mb-3 last:mb-0 overflow-hidden">

                            {{-- Sub-order header --}}
                            <div class="sub-order-header flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200 cursor-pointer"
                                data-sub-order-id="{{ $subOrder->id }}">
                                <div class="flex items-center gap-3">
                                    <button type="button" class="sub-order-toggle text-gray-400 hover:text-gray-600">
                                        <svg class="toggle-icon w-4 h-4 transition-transform duration-200" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div>
                                        <span class="text-sm font-semibold text-gray-800">
                                            {{ $subOrder->vendor->store_name ?? $subOrder->vendor->name ?? __('admin.orders.unknown_seller') }}
                                        </span>
                                        <span class="text-xs text-gray-400 ml-2">{{ $subOrder->sub_order_number }}</span>
                                    </div>
                                    <x-badge :color="$subStatusColors[$subOrder->status->value] ?? 'gray'">
                                        {{ $subOrderStatusLabels[$subOrder->status->value] ?? ucwords(str_replace('_', ' ', $subOrder->status->value)) }}
                                    </x-badge>
                                    @if($subOrder->fulfillment_model === 'fbn')
                                        <x-badge color="primary">{{ __('admin.orders.fbn') }}</x-badge>
                                    @else
                                        <x-badge color="gray">{{ __('admin.orders.fbm') }}</x-badge>
                                    @endif
                                    @if($subOrder->sla_breached)
                                        <x-badge color="danger">{{ __('admin.orders.sla_breached') }}</x-badge>
                                    @endif
                                </div>
                                <span class="text-sm font-medium text-gray-700">{{ $fmt($subOrder->subtotal) }}</span>
                            </div>

                            {{-- Sub-order body --}}
                            <div class="sub-order-body">

                                {{-- Items table --}}
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50/50 border-b border-gray-100">
                                            <tr>
                                                <th class="px-4 py-2.5 text-start text-xs font-semibold text-gray-500 uppercase">
                                                    {{ __('admin.orders.product') }}</th>
                                                <th class="px-4 py-2.5 text-start text-xs font-semibold text-gray-500 uppercase">{{ __('common.sku') }}
                                                </th>
                                                <th
                                                    class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase w-16">
                                                    {{ __('admin.orders.qty') }}</th>
                                                <th class="px-4 py-2.5 text-end text-xs font-semibold text-gray-500 uppercase">
                                                    {{ __('admin.orders.unit_price') }}</th>
                                                <th class="px-4 py-2.5 text-end text-xs font-semibold text-gray-500 uppercase">
                                                    {{ __('common.total') }}</th>
                                                <th class="px-4 py-2.5 text-end text-xs font-semibold text-gray-500 uppercase">
                                                    {{ __('admin.orders.commission_amount') }}</th>
                                                <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase">
                                                    {{ __('common.status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($subOrder->items as $item)
                                                @php
                                                    $snap = is_array($item->product_snapshot) ? $item->product_snapshot : json_decode($item->product_snapshot ?? '{}', true);
                                                @endphp
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-3">
                                                            @if(!empty($snap['image_url']))
                                                                <img src="{{ $snap['image_url'] }}" alt=""
                                                                    class="w-10 h-10 rounded-lg object-cover border border-gray-200 flex-shrink-0">
                                                            @else
                                                                <div
                                                                    class="w-10 h-10 rounded-lg bg-gray-100 flex-shrink-0 flex items-center justify-center">
                                                                    <x-heroicon name="photo" class="w-5 h-5 text-gray-300" />
                                                                </div>
                                                            @endif
                                                            <div>
                                                                <div class="flex items-center gap-1.5">
                                                                    <p class="font-medium text-gray-900 text-sm">
                                                                        {{ $snap['name_en'] ?? $snap['name'] ?? __('admin.orders.product') . ' #' . $item->sku }}
                                                                    </p>
                                                                    @if($item->admin_listing_id)
                                                                        <a href="{{ route('admin.admin-listings.show', $item->admin_listing_id) }}">
                                                                            <x-badge color="primary" class="text-xs">{{ __('admin.orders.platform_listing') }}</x-badge>
                                                                        </a>
                                                                    @elseif($item->vendor_listing_id)
                                                                        <x-badge color="gray" class="text-xs">
                                                                            {{ $item->vendor?->store_name ?? $item->vendor?->name ?? $subOrder->vendor->store_name ?? $subOrder->vendor->name ?? __('admin.orders.unknown_seller') }}
                                                                        </x-badge>
                                                                    @endif
                                                                </div>
                                                                @if(!empty($snap['variant_label']))
                                                                    <p class="text-xs text-gray-400">{{ $snap['variant_label'] }}</p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $item->sku }}</td>
                                                    <td class="px-4 py-3 text-center font-medium">{{ $item->quantity }}</td>
                                                    <td class="px-4 py-3 text-end text-sm">{{ $fmt($item->unit_price) }}</td>
                                                    <td class="px-4 py-3 text-end font-medium">{{ $fmt($item->line_total) }}</td>
                                                    <td class="px-4 py-3 text-end text-xs text-gray-500">
                                                        @php
                                                            $fixed = $item->commission_fixed ?? 0;
                                                        @endphp
                                                        <span
                                                            class="font-mono">{{ number_format((float) $item->commission_rate_pct, 2) }}%</span>
                                                        @if($fixed > 0)
                                                            <span class="text-gray-400"> + {{ $fmt($fixed) }}</span>
                                                        @endif
                                                        <br>
                                                        <span class="text-danger-600 font-medium">=
                                                            {{ $fmt($item->commission_amount) }}</span>
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        <x-badge color="gray" class="text-xs">
                                                            {{ $fulfillmentStatusLabels[$item->fulfillment_status->value] ?? ucfirst(str_replace('_', ' ', $item->fulfillment_status->value)) }}
                                                        </x-badge>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Shipping & SLA info --}}
                                <div
                                    class="px-4 py-3 bg-gray-50/50 border-t border-gray-100 grid grid-cols-3 gap-4 text-xs text-gray-500">
                                    <div>
                                        <span class="font-medium text-gray-700">{{ __('admin.orders.carrier') }}:</span>
                                        {{ $subOrder->carrier->name ?? '—' }}
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-700">{{ __('admin.orders.tracking_number') }}:</span>
                                        @if($subOrder->tracking_number)
                                            <span class="font-mono text-gray-800">{{ $subOrder->tracking_number }}</span>
                                        @else
                                            —
                                        @endif
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-700">{{ __('admin.orders.est_delivery') }}:</span>
                                        {{ $subOrder->estimated_delivery_date
                    ? \Carbon\Carbon::parse($subOrder->estimated_delivery_date)->format('M j, Y')
                    : '—' }}
                                    </div>
                                    @if($subOrder->sla_ship_deadline)
                                        <div class="col-span-3 {{ $subOrder->sla_breached ? 'text-red-600 font-medium' : '' }}">
                                            <span class="font-medium">{{ __('admin.orders.sla_ship_deadline') }}:</span>
                                            {{ \Carbon\Carbon::parse($subOrder->sla_ship_deadline)->format('M j, Y H:i') }}
                                            @if($subOrder->sla_breached)
                                                <x-badge color="danger" class="ml-1">{{ __('admin.orders.breached') }}</x-badge>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                {{-- Financial breakdown (admin-only) --}}
                                <div class="px-4 py-3 border-t border-gray-100 space-y-1 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-500">{{ __('admin.orders.platform_commission') }}</span>
                                        <span class="text-danger-600">−{{ $fmt($subOrder->platform_commission) }}</span>
                                    </div>
                                    @if($subOrder->gateway_fee > 0)
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-500">{{ __('admin.orders.gateway_fee_vendor_borne') }}</span>
                                            <span class="text-danger-600">−{{ $fmt($subOrder->gateway_fee) }}</span>
                                        </div>
                                    @endif
                                    <div class="flex items-center justify-between font-medium text-gray-700">
                                        <span>{{ __('admin.orders.vendor_payout') }}</span>
                                        <span>{{ $fmt($subOrder->vendor_payout) }}</span>
                                    </div>
                                </div>

                            </div>{{-- /sub-order-body --}}
                        </div>
                @empty
                    <p class="text-sm text-gray-400 italic py-4 text-center">{{ __('admin.orders.no_sub_orders_found') }}</p>
                @endforelse
            </x-card>

            {{-- ──────────────────────────────────── --}}
            {{-- Shipping Assignment --}}
            {{-- ──────────────────────────────────── --}}
            <x-card title="{{ __('admin.orders.shipping_assignment') }}">
                @forelse($order->subOrders as $subOrder)
                    <div class="border border-gray-200 rounded-xl mb-3 last:mb-0 p-4 flex items-center justify-between gap-4"
                        data-shipping-sub-order-id="{{ $subOrder->id }}">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-semibold text-gray-800">
                                    {{ $subOrder->vendor->store_name ?? $subOrder->vendor->name ?? __('admin.orders.unknown_seller') }}
                                </span>
                                <span class="text-xs text-gray-400">{{ $subOrder->sub_order_number }}</span>
                            </div>
                            @if($subOrder->shippingMethod)
                                <p class="text-sm text-gray-600 shipping-assignment-summary">
                                    {{ __('admin.orders.assigned') }}: <span class="font-medium text-gray-900">{{ $subOrder->shippingMethod->name }}</span>
                                    @if($subOrder->carrier)
                                        {{ __('admin.orders.via') }} <span class="font-medium text-gray-900">{{ $subOrder->carrier->name }}</span>
                                    @endif
                                </p>
                            @else
                                <p class="text-sm font-medium text-amber-600 shipping-assignment-summary">{{ __('admin.orders.not_yet_assigned') }}</p>
                            @endif
                        </div>
                        <button type="button"
                            class="btn-assign-shipping border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-2 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            data-sub-order-id="{{ $subOrder->id }}"
                            data-shipping-url="{{ route('admin.orders.sub-orders.shipping-methods', $subOrder->id) }}"
                            data-assign-url="{{ route('admin.orders.sub-orders.assign-shipping', $subOrder->id) }}"
                            @if(in_array($subOrder->status->value, ['shipped', 'out_for_delivery', 'delivered', 'completed'])) disabled
                            @endif>
                            {{ $subOrder->shippingMethod ? __('admin.orders.reassign') : __('admin.orders.assign_shipping') }}
                        </button>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic py-4 text-center">{{ __('admin.orders.no_sub_orders_found') }}</p>
                @endforelse
            </x-card>

            {{-- ──────────────────────────────────── --}}
            {{-- Payment Transactions --}}
            {{-- ──────────────────────────────────── --}}
            <x-card title="{{ __('admin.orders.payment_transactions') }}">
                @forelse($order->transactions as $tx)
                    <div class="flex items-start gap-4 py-3 border-b border-gray-100 last:border-0">
                        <div class="flex-shrink-0 mt-0.5">
                            <x-badge :color="$txTypeColors[$tx->type->value] ?? 'gray'">
                                {{ $txTypeLabels[$tx->type->value] ?? ucfirst($tx->type->value) }}
                            </x-badge>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">{{ $fmt($tx->amount) }}</p>
                                <x-badge :color="$tx->status->value === 'succeeded' ? 'success' : ($tx->status->value === 'failed' ? 'danger' : 'gray')">
                                    {{ $txStatusLabels[$tx->status->value] ?? ucfirst($tx->status->value) }}
                                </x-badge>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="font-medium">{{ strtoupper($tx->gateway) }}</span>
                                <span class="mx-1">·</span>
                                <span class="font-mono">{{ Str::limit($tx->gateway_transaction_id, 32) }}</span>
                            </p>
                            @if($tx->failure_message)
                                <p class="text-xs text-red-600 mt-1">{{ $tx->failure_message }}</p>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 flex-shrink-0">
                            {{ $tx->processed_at ? \Carbon\Carbon::parse($tx->processed_at)->format('M j, Y H:i') : '—' }}
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic py-4 text-center">{{ __('admin.orders.no_transactions_recorded') }}</p>
                @endforelse
            </x-card>

            {{-- ──────────────────────────────────── --}}
            {{-- Status History --}}
            {{-- ──────────────────────────────────── --}}
            <x-card title="{{ __('admin.orders.status_history') }}">
                @forelse($order->statusHistories->sortByDesc('created_at') as $history)
                    <div class="relative pl-6 pb-4 last:pb-0">
                        {{-- Timeline dot --}}
                        <span
                            class="absolute left-0 top-1.5 w-2.5 h-2.5 rounded-full bg-primary-400 ring-2 ring-white ring-offset-0"></span>
                        {{-- Line --}}
                        @if(!$loop->last)
                            <span class="absolute left-1 top-4 bottom-0 w-px bg-gray-200"></span>
                        @endif

                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($history->sub_order_id)
                                        <span class="text-xs font-mono text-gray-400">
                                            {{ __('admin.orders.sub_order') }}
                                        </span>
                                    @endif
                                    <x-badge :color="$subStatusColors[$history->from_status] ?? 'gray'">
                                        {{ $allStatusLabels[$history->from_status] ?? ucwords(str_replace('_', ' ', $history->from_status)) }}
                                    </x-badge>
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7" />
                                    </svg>
                                    <x-badge :color="$subStatusColors[$history->to_status] ?? ($statusColors[$history->to_status] ?? 'gray')">
                                        {{ $allStatusLabels[$history->to_status] ?? ucwords(str_replace('_', ' ', $history->to_status)) }}
                                    </x-badge>
                                </div>
                                @if($history->reason)
                                    <p class="text-xs text-gray-500 mt-1">{{ $history->reason }}</p>
                                @endif
                                @if($history->changedByAdmin)
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ __('admin.orders.by_admin', ['name' => $history->changedByAdmin->name ?? __('admin.orders.admin_fallback')]) }}
                                    </p>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400 flex-shrink-0">
                                {{ \Carbon\Carbon::parse($history->created_at)->format('M j, Y H:i') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic py-4 text-center">{{ __('admin.orders.no_history_recorded') }}</p>
                @endforelse
            </x-card>

            {{-- ──────────────────────────────────── --}}
            {{-- Disputes (if any) --}}
            {{-- ──────────────────────────────────── --}}
            @if($order->disputes->isNotEmpty())
                <x-card title="{{ __('admin.nav.disputes') }}">
                    @foreach($order->disputes as $dispute)
                        <div class="border border-gray-200 rounded-xl p-4 mb-3 last:mb-0">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-mono text-sm font-semibold text-gray-700">{{ $dispute->dispute_number }}</span>
                                    <x-badge :color="in_array($dispute->status->value, ['resolved', 'closed']) ? 'success' : ($dispute->status->value === 'escalated' ? 'danger' : 'warning')">
                                        {{ $disputeStatusLabels[$dispute->status->value] ?? ucwords(str_replace('_', ' ', $dispute->status->value)) }}
                                    </x-badge>
                                </div>
                                <span class="text-xs text-gray-400">
                                    {{ \Carbon\Carbon::parse($dispute->created_at)->format('M j, Y') }}
                                </span>
                            </div>
                            <p class="text-xs font-medium text-gray-500 mb-1">
                                {{ __('admin.disputes_section.reason') }}: {{ $disputeReasonLabels[$dispute->reason->value] ?? $dispute->reason->value }}
                            </p>
                            <p class="text-sm text-gray-700">{{ $dispute->description }}</p>
                            @if($dispute->resolution_notes)
                                <div class="mt-2 p-2 bg-gray-50 rounded-lg border border-gray-100 text-xs text-gray-600">
                                    <span class="font-medium">{{ __('admin.disputes_section.resolution') }}:</span> {{ $dispute->resolution_notes }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </x-card>
            @endif

        </div>{{-- /main column --}}

        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        {{-- SIDEBAR (1/3) --}}
        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-80 flex-shrink-0 space-y-4 lg:sticky lg:top-20">

            {{-- Order Summary --}}
            <x-card title="{{ __('admin.orders.order_summary') }}">
                <div class="space-y-1 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">{{ __('admin.orders.order_number') }}</span>
                        <span class="font-semibold font-mono text-gray-900">{{ $order->order_number }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">{{ __('admin.orders.placed') }}</span>
                        <span class="text-gray-700">
                            {{ \Carbon\Carbon::parse($order->placed_at)->format('M j, Y H:i') }}
                        </span>
                    </div>
                    @if(!empty($addr['city']))
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">{{ __('common.country') }}</span>
                            <span class="text-gray-700">
                                {{ is_array($addr['city']) ? ($addr['city']['en'] ?? '') : $addr['city'] }}
                            </span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">{{ __('common.status') }}</span>
                        <x-badge :color="$statusColors[$order->status->value] ?? 'gray'">
                            {{ $allStatusLabels[$order->status->value] ?? ucwords(str_replace('_', ' ', $order->status->value)) }}
                        </x-badge>
                    </div>
                </div>

                <div class="border-t border-gray-100 mt-3 pt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>{{ __('common.subtotal') }}</span><span>{{ $fmt($order->subtotal) }}</span>
                    </div>
                    @if($order->discount > 0)
                        <div class="flex justify-between text-success-600">
                            <span>{{ __('common.discount') }}</span><span>−{{ $fmt($order->discount) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-gray-600">
                        <span>{{ __('admin.orders.shipping_cost') }}</span><span>{{ $fmt($order->shipping) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>{{ __('common.tax') }}</span><span>{{ $fmt($order->tax) }}</span>
                    </div>
                    @if($order->cod_fee > 0)
                        <div class="flex justify-between text-gray-600">
                            <span>{{ __('admin.orders.cod_fee') }}</span><span>{{ $fmt($order->cod_fee) }}</span>
                        </div>
                    @endif
                    @if($order->coupon_code_used)
                        <div class="flex justify-between text-gray-500 text-xs">
                            <span>{{ __('admin.orders.coupon') }}</span><span class="font-mono">{{ $order->coupon_code_used }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between font-semibold text-gray-900 text-base pt-1 border-t border-gray-100">
                        <span>{{ __('common.total') }}</span><span>{{ $fmt($order->total) }}</span>
                    </div>
                </div>

                <div class="border-t border-gray-100 mt-3 pt-3 flex items-center justify-between text-xs">
                    <span
                        class="text-gray-500">{{ $payMethodLabels[$order->payment_method] ?? $order->payment_method }}</span>
                    <x-badge :color="$payStatusColors[$order->payment_status->value] ?? 'gray'">
                        {{ $payStatusLabels[$order->payment_status->value] ?? ucwords(str_replace('_', ' ', $order->payment_status->value)) }}
                    </x-badge>
                </div>

                @if($order->payment_method === 'cod')
                    <div class="border-t border-gray-100 mt-3 pt-3 space-y-1 text-xs">
                        <p class="text-gray-500 font-medium uppercase tracking-wide">{{ __('admin.orders.cod_remittance') }}</p>
                        @php $firstSubOrder = $order->subOrders->first(); @endphp
                        @foreach($order->subOrders as $so)
                            @if(!$so->cod_remittance_confirmed)
                                <div class="flex items-start gap-1.5 text-amber-700">
                                    <x-heroicon name="clock" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-amber-500" />
                                    <span>
                                        {{ __('admin.orders.sub_order') }} #{{ $so->sub_order_number }}:
                                        {{ __('admin.orders.cod_pending') }}
                                        @if($so->codSettlement && $so->codSettlement->agent)
                                            {{ __('admin.orders.from_agent') }} {{ $so->codSettlement->agent->name }}
                                        @endif
                                    </span>
                                </div>
                            @else
                                <div class="flex items-start gap-1.5 text-green-700">
                                    <x-heroicon name="check-circle" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-green-500" />
                                    <span>
                                        {{ __('admin.orders.sub_order') }} #{{ $so->sub_order_number }}:
                                        {{ __('admin.orders.cod_remitted') }} —
                                        @if($so->codSettlement)
                                            <a href="{{ route('admin.cod-settlements.show', $so->codSettlement) }}"
                                                class="underline hover:text-green-900">
                                                {{ __('admin.orders.settlement') }} #{{ $so->cod_settlement_id }}
                                            </a>
                                        @endif
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Customer --}}
            <x-card title="{{ __('admin.orders.customer') }}">
                @if($order->customer)
                    <div class="space-y-2 text-sm">
                        <p class="font-semibold text-gray-900">{{ $order->customer->name }}</p>
                        @if($order->customer->email)
                            <p class="text-gray-500 text-xs">{{ $order->customer->email }}</p>
                        @endif
                        @if($order->customer->phone)
                            <p class="text-gray-500 text-xs">{{ $order->customer->phone }}</p>
                        @endif
                    </div>
                @endif

                @if(!empty($addr))
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('admin.orders.delivery_address') }}</p>
                        @php
                            // Normalise snapshot keys — snapshot uses recipient_name/street_address/etc.
                            // Blade historically expected name/line1/line2/city/zip/country/phone.
                            $addrName   = $addr['recipient_name']   ?? $addr['name']   ?? null;
                            $addrPhone  = $addr['recipient_phone']  ?? $addr['phone']  ?? null;
                            $addrLine1  = $addr['street_address']   ?? $addr['line1']  ?? null;
                            $addrLine2  = $addr['area']             ?? $addr['line2']  ?? null;
                            $addrCity   = $addr['city'] ?? null;
                            $addrCity   = is_array($addrCity) ? ($addrCity['en'] ?? '') : $addrCity;
                            $addrZip    = $addr['postal_code']      ?? $addr['zip']    ?? null;
                            $addrCountry= $addr['country']          ?? $addr['country_en'] ?? null;

                            // Compose building/floor/apartment into line2 if present
                            $parts = array_filter([
                                $addr['building']  ?? null,
                                $addr['floor']     ?? null ? 'Floor ' . ($addr['floor'] ?? '') : null,
                                $addr['apartment'] ?? null ? 'Apt ' . ($addr['apartment'] ?? '') : null,
                            ]);
                            if (!empty($parts)) {
                                $addrLine2 = implode(', ', $parts) . ($addrLine2 ? ', ' . $addrLine2 : '');
                            }
                        @endphp
                        <address class="not-italic text-sm text-gray-700 space-y-0.5">
                            @if(!empty($addrName))
                            <p class="font-medium">{{ $addrName }}</p>@endif
                            @if(!empty($addrLine1))
                            <p>{{ $addrLine1 }}</p>@endif
                            @if(!empty($addrLine2))
                            <p>{{ $addrLine2 }}</p>@endif
                            @if(!empty($addrCity))
                            <p>{{ $addrCity }}</p>@endif
                            @if(!empty($addrZip))
                            <p>{{ $addrZip }}</p>@endif
                            @if(!empty($addrCountry))
                            <p>{{ $addrCountry }}</p>@endif
                            @if(!empty($addrPhone))
                            <p class="text-gray-500">{{ $addrPhone }}</p>@endif
                            @if(!empty($addr['landmark']))
                            <p class="text-gray-400 text-xs italic">Near: {{ $addr['landmark'] }}</p>@endif
                        </address>
                    </div>
                @endif
            </x-card>

            {{-- Risk Assessment --}}
            <x-card title="{{ __('admin.dashboard.risk_assessment') }}">
                <div class="flex items-center gap-4 mb-3">
                    @if($riskScore !== null)
                        <div
                            class="w-16 h-16 rounded-full flex items-center justify-center font-bold text-xl {{ $riskBg }} {{ $riskText }} border-2 {{ str_replace('bg-', 'border-', explode(' ', $riskBg)[0]) }}-300 flex-shrink-0">
                            {{ (int) $riskScore }}
                        </div>
                        <div>
                            <p class="font-semibold {{ $riskText }}">
                                {{ $riskScore >= 70 ? __('admin.orders.risk_high_label') : ($riskScore >= 40 ? __('admin.orders.risk_medium_label') : __('admin.orders.risk_low_label')) }}
                            </p>
                            <p class="text-xs text-gray-400">{{ __('admin.orders.score_out_of_100') }}</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-400 italic">{{ __('admin.orders.no_risk_data') }}</p>
                    @endif
                </div>

                <div class="space-y-2 text-xs">
                    <div class="flex items-start gap-2">
                        <span class="text-gray-400 flex-shrink-0 w-20">{{ __('admin.orders.ip_address') }}</span>
                        <span class="font-mono text-gray-700 break-all">{{ $order->ip_address ?? '—' }}</span>
                    </div>
                    @if($order->device_fingerprint)
                        <div class="flex items-start gap-2">
                            <span class="text-gray-400 flex-shrink-0 w-20">{{ __('admin.orders.device') }}</span>
                            <span
                                class="font-mono text-gray-600 break-all text-xs">{{ Str::limit($order->device_fingerprint, 40) }}</span>
                        </div>
                    @endif
                </div>

                {{-- Fraud flag history entries --}}
                @php
                    $fraudFlags = $order->statusHistories->filter(
                        fn($h) => is_array($h->metadata) && ($h->metadata['action'] ?? '') === 'fraud_flagged'
                    );
                @endphp
                @if($fraudFlags->isNotEmpty())
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs font-semibold text-danger-600 uppercase tracking-wider mb-2">{{ __('admin.orders.fraud_flags') }}</p>
                        @foreach($fraudFlags as $flag)
                            <div class="text-xs text-gray-600 py-1">
                                <p class="text-red-600">{{ $flag->reason }}</p>
                                <p class="text-gray-400">{{ \Carbon\Carbon::parse($flag->created_at)->format('M j, Y H:i') }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Refunds (summary) --}}
            @if($order->refunds->isNotEmpty())
                <x-card title="{{ __('common.refunds') }}">
                    @foreach($order->refunds as $refund)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ $fmt($refund->amount) }}</p>
                                <p class="text-xs text-gray-400">{{ $refundReasonLabels[$refund->reason->value] ?? $refund->reason->value }}</p>
                            </div>
                            <x-badge :color="$refund->status->value === 'completed' ? 'success' : ($refund->status->value === 'rejected' || $refund->status->value === 'failed' ? 'danger' : 'warning')">
                                {{ $refundStatusLabels[$refund->status->value] ?? ucfirst($refund->status->value) }}
                            </x-badge>
                        </div>
                    @endforeach
                </x-card>
            @endif

            {{-- Actions --}}
            <x-card title="{{ __('common.actions') }}">
                <div class="space-y-2">
                    <button type="button" data-modal-open="update-status-modal"
                        class="btn btn-primary w-full justify-center">
                        <x-heroicon name="arrow-path" class="w-4 h-4 mr-1.5" />
                        {{ __('admin.orders.update_status') }}
                    </button>
                    <button type="button" data-modal-open="refund-modal" class="btn btn-secondary w-full justify-center">
                        <x-heroicon name="arrow-uturn-left" class="w-4 h-4 mr-1.5" />
                        {{ __('admin.orders.process_refund') }}
                    </button>
                    @if(!in_array($order->status->value, ['cancelled', 'refunded', 'completed']))
                        <button type="button" data-modal-open="force-cancel-modal"
                            class="btn btn-ghost w-full justify-center text-danger-600 hover:bg-danger-50">
                            <x-heroicon name="x-circle" class="w-4 h-4 mr-1.5" />
                            {{ __('admin.orders.force_cancel') }}
                        </button>
                    @endif
                    @if(!in_array($order->status->value, ['shipped', 'delivered', 'completed', 'cancelled', 'refunded']))
                        <button type="button" data-modal-open="cancel-items-modal" class="btn btn-ghost w-full justify-center text-danger-600 hover:bg-danger-50">
                            <x-heroicon name="x-circle" class="w-4 h-4 mr-1.5" />
                            {{ __('admin.orders.cancel_specific_items') }}
                        </button>
                    @endif
                    <button type="button" data-modal-open="dispute-modal" class="btn btn-ghost w-full justify-center">
                        <x-heroicon name="scale" class="w-4 h-4 mr-1.5" />
                        {{ __('admin.orders.escalate_dispute') }}
                    </button>
                    <button type="button" data-modal-open="fraud-modal"
                        class="btn btn-ghost w-full justify-center text-warning-700 hover:bg-warning-50">
                        <x-heroicon name="shield-exclamation" class="w-4 h-4 mr-1.5" />
                        {{ __('admin.orders.flag_fraud') }}
                    </button>
                </div>
            </x-card>

        </div>{{-- /sidebar --}}

    </div>{{-- /flex row --}}

    {{-- ═══════════════════════════════════════════════════════════════════════════ --}}
    {{-- MODALS --}}
    {{-- ═══════════════════════════════════════════════════════════════════════════ --}}

    {{-- 1. Update Order Status --}}
    @php
        $orderStatusTransitions = [
            'placed' => ['confirmed', 'cancelled', 'disputed'],
            'confirmed' => ['partially_shipped', 'shipped', 'cancelled', 'disputed'],
            'partially_shipped' => ['shipped', 'partially_delivered', 'cancelled', 'disputed'],
            'shipped' => ['partially_delivered', 'delivered', 'disputed'],
            'partially_delivered' => ['delivered', 'cancelled', 'disputed'],
            'delivered' => ['completed', 'refunded', 'disputed'],
            'completed' => ['refunded', 'disputed'],
            'cancelled' => ['refunded'],
            'refunded' => [],
            'disputed' => ['delivered', 'cancelled', 'refunded', 'completed'],
        ];
        $allowedNextStatuses = $orderStatusTransitions[$order->status->value] ?? [];
    @endphp
    <x-modal id="update-status-modal" title="{{ __('admin.orders.update_status') }}" size="md">
        <form id="update-status-form">
            @csrf
            <div class="space-y-4">
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">
                    {{ __('admin.orders.current_status') }}:
                    <x-badge :color="$statusColors[$order->status->value] ?? 'gray'" class="ml-1">
                        {{ $allStatusLabels[$order->status->value] ?? ucwords(str_replace('_', ' ', $order->status->value)) }}
                    </x-badge>
                </div>
                <div>
                    <label class="form-label" for="order-new-status">{{ __('admin.orders.new_status') }} <span
                            class="text-danger-500">*</span></label>
                    @if(empty($allowedNextStatuses))
                        <p class="text-sm text-gray-400 italic">{{ __('admin.orders.no_transitions_available') }}</p>
                    @else
                        <select id="order-new-status" name="new_status" class="form-select w-full">
                            <option value="">{{ __('admin.orders.select_new_status') }}</option>
                            @foreach($allowedNextStatuses as $s)
                                <option value="{{ $s }}">{{ $allStatusLabels[$s] ?? ucwords(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div>
                    <label class="form-label" for="order-status-reason">{{ __('admin.orders.reason_notes') }}</label>
                    <textarea id="order-status-reason" name="reason" rows="3" class="form-textarea w-full"
                        placeholder="{{ __('admin.orders.status_change_placeholder') }}"></textarea>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="submit" form="update-status-form" class="btn btn-primary" @if(empty($allowedNextStatuses))
                disabled @endif>
                    {{ __('admin.orders.update_status') }}
                </button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 2. Process Refund --}}
    <x-modal id="refund-modal" title="{{ __('admin.orders.process_refund') }}" size="md">
        <form id="refund-form">
            @csrf
            <div class="space-y-4">
                {{-- Refund type --}}
                <div>
                    <label class="form-label">{{ __('admin.orders.refund_type') }}</label>
                    <div class="space-y-2 mt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="refund_type" value="full" class="text-primary-600"
                                @if($order->payment_status->value !== 'refunded') checked @endif>
                            <span class="text-sm">{{ __('admin.orders.full_order') }} — <strong>{{ $fmt($order->total) }}</strong></span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="refund_type" value="shipping_only" class="text-primary-600">
                            <span class="text-sm">{{ __('admin.orders.shipping_only') }} — <strong>{{ $fmt($order->shipping) }}</strong></span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="refund_type" value="partial" class="text-primary-600">
                            <span class="text-sm">{{ __('admin.orders.partial_amount') }}</span>
                        </label>
                    </div>
                </div>

                {{-- Partial amount (shown/hidden by JS) --}}
                <div id="partial-amount-field" class="hidden">
                    <label class="form-label">{{ __('common.amount') }} ({{ $currency }})</label>
                    <input type="number" name="amount" min="1" step="1" max="{{ $order->total }}"
                        class="form-input w-full" placeholder="0.00">
                </div>

                {{-- Sub-order (optional) --}}
                <div>
                    <label class="form-label">{{ __('admin.orders.sub_order_optional') }}</label>
                    <select name="sub_order_id" class="form-select w-full">
                        <option value="">{{ __('admin.orders.entire_order') }}</option>
                        @foreach($order->subOrders as $so)
                            <option value="{{ $so->id }}">
                                {{ $so->sub_order_number }} — {{ $so->vendor->store_name ?? __('admin.orders.seller_fallback') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Reason --}}
                <div>
                    <label class="form-label">{{ __('common.reason') }} <span class="text-danger-500">*</span></label>
                    <select name="reason" class="form-select w-full">
                        @foreach($refundReasonLabels as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="form-label">{{ __('common.notes') }}</label>
                    <textarea name="reason_notes" rows="2" class="form-textarea w-full"
                        placeholder="{{ __('admin.orders.additional_context_placeholder') }}"></textarea>
                </div>

                {{-- Vendor chargeback --}}
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="vendor_charged_back" value="0">
                    <input type="checkbox" name="vendor_charged_back" value="1"
                        class="rounded text-primary-600 border-gray-300">
                    <span class="text-sm text-gray-700">{{ __('admin.orders.charge_back_to_seller') }}</span>
                </label>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="submit" form="refund-form" class="btn btn-secondary">{{ __('admin.orders.process_refund') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 3. Force Cancel --}}
    <x-modal id="force-cancel-modal" title="{{ __('admin.orders.force_cancel_order') }}" size="sm">
        <form id="force-cancel-form">
            @csrf
            <div class="space-y-4">
                <div
                    class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800 flex items-start gap-2">
                    <x-heroicon name="exclamation-triangle" class="w-5 h-5 flex-shrink-0 mt-0.5" />
                    <span>{{ __('admin.orders.force_cancel_warning') }}</span>
                </div>

                <div>
                    <label class="form-label">{{ __('admin.orders.cancel_reason') }} <span class="text-danger-500">*</span></label>
                    <textarea name="reason" rows="3" class="form-textarea w-full"
                        placeholder="{{ __('admin.orders.force_cancel_placeholder') }}"></textarea>
                </div>

                <label class="flex items-center gap-2 cursor-pointer" id="force-override-toggle">
                    <input type="hidden" name="force" value="0">
                    <input type="checkbox" name="force" value="1" class="rounded text-danger-600 border-gray-300">
                    <span class="text-sm text-gray-700">{{ __('admin.orders.force_cancel_override') }}</span>
                </label>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('admin.orders.abort') }}</button>
                <button type="submit" form="force-cancel-form" class="btn btn-danger">{{ __('admin.orders.force_cancel') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 3b. Cancel Specific Items --}}
    @php
        $cancellableItems = $order->subOrders->flatMap->items->reject(
            fn($item) => in_array($item->fulfillment_status->value, ['cancelled', 'returned'])
        );
    @endphp
    <x-modal id="cancel-items-modal" title="{{ __('admin.orders.cancel_specific_items') }}" size="md">
        <form id="cancel-items-form">
            @csrf
            <div class="space-y-4">
                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800 flex items-start gap-2">
                    <x-heroicon name="exclamation-triangle" class="w-5 h-5 flex-shrink-0 mt-0.5" />
                    <span>{{ __('admin.orders.cancel_items_warning') }}</span>
                </div>

                <div>
                    <label class="form-label">{{ __('admin.orders.select_items_to_cancel') }} <span class="text-danger-500">*</span></label>
                    <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-2">
                        @forelse($cancellableItems as $item)
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="rounded text-primary-600 border-gray-300">
                                <span>{{ $item->product_snapshot['name_en'] ?? $item->sku }} × {{ $item->quantity }} — {{ $fmt($item->line_total) }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-400 italic">{{ __('admin.orders.no_cancellable_items') }}</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <label class="form-label">{{ __('admin.orders.cancel_reason') }} <span class="text-danger-500">*</span></label>
                    <textarea name="cancel_reason" rows="3" class="form-textarea w-full"
                        placeholder="{{ __('admin.orders.force_cancel_placeholder') }}"></textarea>
                </div>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('admin.orders.abort') }}</button>
                <button type="submit" form="cancel-items-form" class="btn btn-danger">{{ __('admin.orders.cancel_specific_items') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 4. Escalate Dispute --}}
    <x-modal id="dispute-modal" title="{{ __('admin.orders.escalate_dispute') }}" size="md">
        <form id="dispute-form">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="form-label">{{ __('admin.orders.sub_order') }} <span class="text-danger-500">*</span></label>
                    <select name="sub_order_id" class="form-select w-full">
                        <option value="">{{ __('admin.orders.select_sub_order') }}</option>
                        @foreach($order->subOrders as $so)
                            <option value="{{ $so->id }}">
                                {{ $so->sub_order_number }} — {{ $so->vendor->store_name ?? __('admin.orders.seller_fallback') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('common.reason') }} <span class="text-danger-500">*</span></label>
                    <select name="reason" class="form-select w-full">
                        @foreach($disputeReasonLabels as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('common.description') }} <span class="text-danger-500">*</span></label>
                    <textarea name="description" rows="4" class="form-textarea w-full"
                        placeholder="{{ __('admin.orders.dispute_description_placeholder') }}"></textarea>
                </div>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="submit" form="dispute-form" class="btn btn-danger">{{ __('admin.orders.escalate_dispute') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 5. Flag Fraud --}}
    <x-modal id="fraud-modal" title="{{ __('admin.orders.flag_as_potential_fraud') }}" size="sm">
        <form id="fraud-form">
            @csrf
            <div class="space-y-4">
                <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800 flex items-start gap-2">
                    <x-heroicon name="shield-exclamation" class="w-5 h-5 flex-shrink-0 mt-0.5" />
                    <span>{{ __('admin.orders.flag_fraud_warning') }}</span>
                </div>
                <div>
                    <label class="form-label">{{ __('common.reason') }} <span class="text-danger-500">*</span></label>
                    <textarea name="reason" rows="3" class="form-textarea w-full"
                        placeholder="{{ __('admin.orders.fraud_indicators_placeholder') }}"></textarea>
                </div>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="submit" form="fraud-form" class="btn btn-danger">{{ __('admin.orders.flag_fraud') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 6. Assign Shipping Method --}}
    <x-modal id="shipping-assign-modal" title="{{ __('admin.orders.assign_carrier') }}" size="lg">
        <div id="shipping-assign-zone-warning"
            class="hidden rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800 mb-4">
            {{ __('admin.orders.cannot_determine_zone') }}
        </div>
        <div id="shipping-assign-loading" class="text-sm text-gray-500 py-8 text-center">{{ __('admin.orders.loading_methods') }}</div>
        <div id="shipping-assign-methods" class="space-y-3 hidden"></div>
        <div id="shipping-assign-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3 mt-4"></div>

        <x-slot:footer>
            <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
            <button type="button" id="shipping-assign-confirm" class="btn btn-primary" disabled>{{ __('admin.orders.confirm_carrier') }}</button>
        </x-slot:footer>
    </x-modal>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            action_completed: @json(__('admin.orders.action_completed')),
            generic_error: @json(__('admin.orders.generic_error')),
            no_eligible_shipping_methods: @json(__('admin.orders.no_eligible_shipping_methods')),
            any_carrier: @json(__('admin.orders.any_carrier')),
            no_carrier_rate_data: @json(__('admin.orders.no_carrier_rate_data')),
            failed_load_shipping_methods: @json(__('admin.orders.failed_load_shipping_methods')),
            shipping_method_assigned: @json(__('admin.orders.shipping_method_assigned')),
            failed_assign_shipping_method: @json(__('admin.orders.failed_assign_shipping_method')),
        });
    </script>
@endpush