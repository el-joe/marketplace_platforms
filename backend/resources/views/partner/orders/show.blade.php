@extends('layouts.partner')

@section('title', __('partner.orders.order_title', ['number' => $subOrder->sub_order_number]))
@section('page-title', __('partner.orders.order_details'))

@push('scripts')
    @vite('resources/js/partner/orders.js')
    <script>
        window.ORDER_DETAIL = {
            confirmUrl: '{{ route('partner.orders.confirm', $subOrder->sub_order_number) }}',
            shipUrl: '{{ route('partner.orders.ship', $subOrder->sub_order_number) }}',
            shipPreviewUrl: '{{ route('partner.orders.ship-preview', $subOrder->sub_order_number) }}',
            outForDeliveryUrl: '{{ route('partner.orders.out-for-delivery', $subOrder->sub_order_number) }}',
            deliverUrl: '{{ route('partner.orders.deliver', $subOrder->sub_order_number) }}',
            cancelUrl: '{{ route('partner.orders.cancel', $subOrder->sub_order_number) }}',
            csrf: '{{ csrf_token() }}',
            status: '{{ $subOrder->status->value }}',
        };
        window.PARTNER_TRANSLATIONS = window.PARTNER_TRANSLATIONS || {};
        Object.assign(window.PARTNER_TRANSLATIONS, {
            confirmOrderConfirm: @json(__('partner.orders.confirm_order_confirm')),
            confirmOutForDelivery: @json(__('partner.orders.confirm_out_for_delivery')),
            confirmDeliveryConfirm: @json(__('partner.orders.confirm_delivery_confirm')),
        });
    </script>
@endpush

@section('content')

    @php
        $order = $subOrder->order;
        $address = $order->shipping_address_snapshot ?? [];
        $customer = $order->customer;

        // Mask customer name: first name + last initial
        $maskName = function (?string $name): string {
            if (!$name)
                return '—';
            $parts = explode(' ', trim($name));
            if (count($parts) === 1)
                return $parts[0];
            return $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.';
        };

        $maskedName = $maskName($customer?->name);
        $maskedPhone = $customer?->phone
            ? preg_replace('/(\d{3})\d+(\d{2})/', '$1•••••$2', $customer->phone)
            : '—';

        $isUrgent = $subOrder->sla_ship_deadline &&
            now()->addHours(2)->gt($subOrder->sla_ship_deadline) &&
            !in_array($subOrder->status->value, ['shipped', 'delivered', 'completed', 'cancelled']);
        $isPast = $subOrder->sla_ship_deadline && now()->gt($subOrder->sla_ship_deadline);

        $statusLabels = [
            'placed' => __('partner.orders.status.placed'),
            'confirmed' => __('partner.orders.status.confirmed'),
            'processing' => __('partner.orders.status.processing'),
            'packed' => __('partner.orders.status.packed'),
            'shipped' => __('partner.orders.status.shipped'),
            'out_for_delivery' => __('partner.orders.status.out_for_delivery'),
            'delivered' => __('partner.orders.status.delivered'),
            'completed' => __('common.completed'),
            'cancelled' => __('common.cancelled'),
            'return_requested' => __('partner.orders.status.return_requested'),
            'returned' => __('partner.orders.status.returned'),
        ];
    @endphp

    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('partner.orders.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <x-heroicon name="arrow-right" class="w-4 h-4" />
            {{ __('partner.orders.back_to_orders') }}
        </a>
    </div>

    {{-- Main grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- ── LEFT COLUMN ── --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Order Items --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ __('partner.orders.order_items') }}</h3>
                <div class="divide-y divide-gray-50">
                    @foreach($subOrder->items as $item)
                        @php
                            $snap = $item->product_snapshot ?? [];
                            $imgUrl = $snap['image_url'] ?? null;
                            $name = $snap['name_ar'] ?? $snap['name'] ?? __('partner.orders.product');
                            $variant = $snap['variant'] ?? null;
                        @endphp
                        <div class="flex items-start gap-4 py-4 first:pt-0 last:pb-0">
                            <div
                                class="w-14 h-14 rounded-xl border border-gray-100 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                                @if($imgUrl)
                                    <img src="{{ $imgUrl }}" alt="{{ $name }}" class="w-full h-full object-cover">
                                @else
                                    <x-heroicon name="cube" class="w-6 h-6 text-gray-300" />
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 text-sm">{{ $name }}</p>
                                @if($variant)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $variant }}</p>
                                @endif
                                <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $item->sku }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm text-gray-500">{{ $item->quantity }} ×
                                    {{ number_format($item->unit_price / 100, 2) }} {{ $currency }}</p>
                                <p class="font-semibold text-gray-900 mt-0.5">{{ number_format($item->line_total / 100, 2) }}
                                    {{ $currency }}</p>
                                @if(($item->commission_amount ?? 0) > 0)
                                    <p class="text-xs text-red-500 mt-0.5">
                                        {{ __('partner.orders.commission') }}: −{{ number_format($item->commission_amount / 100, 2) }} {{ $currency }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Delivery Address (full, visible in detail only) --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <x-heroicon name="map-pin" class="w-4 h-4 text-gray-400" />
                    {{ __('partner.orders.delivery_address') }}
                </h3>
                <div class="text-sm text-gray-700 space-y-1">
                    <p class="font-medium">{{ $address['name'] ?? $maskedName }}</p>
                    @if(!empty($address['address_line_1']))
                        <p>{{ $address['address_line_1'] }}</p>
                    @endif
                    @if(!empty($address['address_line_2']))
                        <p>{{ $address['address_line_2'] }}</p>
                    @endif
                    @php
                        $addrStr = function ($val): ?string {
                            if (is_array($val))  return $val[app()->getLocale()] ?? $val['en'] ?? $val['ar'] ?? $val['name_en'] ?? $val['name_ar'] ?? null;
                            if (is_object($val)) return $val->name_en   ?? $val->name_ar   ?? null;
                            return $val ?: null;
                        };
                    @endphp
                    <p>
                        {{ implode(', ', array_filter([
        $addrStr($address['area']    ?? null),
        $addrStr($address['city']    ?? null),
        $addrStr($address['country'] ?? null),
    ])) }}
                    </p>
                    @if(!empty($address['zip_code']))
                        <p class="text-gray-400 text-xs">{{ $address['zip_code'] }}</p>
                    @endif
                </div>

                {{-- Masked customer contact --}}
                <div class="mt-4 pt-4 border-t border-gray-50 flex items-center gap-6">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <x-heroicon name="user" class="w-4 h-4 text-gray-400" />
                        <span>{{ $maskedName }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <x-heroicon name="phone" class="w-4 h-4 text-gray-400" />
                        <span dir="ltr" class="font-mono select-all">{{ $maskedPhone }}</span>
                    </div>
                </div>
            </div>

            {{-- Customer notes --}}
            @if($order->customer_notes)
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
                    <div class="flex items-start gap-2">
                        <x-heroicon name="chat-bubble-left" class="w-4 h-4 text-amber-500 mt-0.5 shrink-0" />
                        <div>
                            <p class="text-xs font-semibold text-amber-700 mb-1">{{ __('partner.orders.customer_notes') }}</p>
                            <p class="text-sm text-amber-900">{{ $order->customer_notes }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Status history timeline --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ __('partner.orders.status_history') }}</h3>
                <ol class="relative border-s border-gray-200 space-y-5 pe-4">
                    @foreach($subOrder->statusHistories as $history)
                        <li class="ms-4">
                            <div class="absolute w-2.5 h-2.5 bg-yellow-400 rounded-full -start-1.5 border border-white mt-1.5">
                            </div>
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">
                                        {{ $statusLabels[$history->to_status] ?? $history->to_status }}
                                    </p>
                                    @if($history->reason)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $history->reason }}</p>
                                    @endif
                                </div>
                                <time class="text-xs text-gray-400 shrink-0 ms-4">
                                    {{ $history->created_at->format('M d, H:i') }}
                                </time>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Tracking events (if shipped) --}}
            @if($subOrder->shipments->isNotEmpty())
                @foreach($subOrder->shipments as $shipment)
                    @if($shipment->trackingEvents->isNotEmpty())
                        <div class="bg-white rounded-2xl border border-gray-200 p-6">
                            <h3 class="font-semibold text-gray-800 mb-1 flex items-center gap-2">
                                <x-heroicon name="truck" class="w-4 h-4 text-gray-400" />
                                {{ __('partner.orders.shipment_tracking') }}
                            </h3>
                            <p class="text-xs text-gray-400 mb-4 font-mono">{{ $shipment->tracking_number }}</p>
                            <ol class="relative border-s border-gray-200 space-y-4 pe-4">
                                @foreach($shipment->trackingEvents->sortByDesc('occurred_at') as $event)
                                    <li class="ms-4">
                                        <div class="absolute w-2 h-2 bg-gray-300 rounded-full -start-1 mt-1.5 border border-white"></div>
                                        <p class="text-sm text-gray-700">{{ $event->description }}</p>
                                        <time class="text-xs text-gray-400">{{ $event->occurred_at?->format('M d, H:i') }}</time>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                @endforeach
            @endif

        </div>

        {{-- ── RIGHT SIDEBAR ── --}}
        <div class="lg:col-span-4 space-y-4">

            {{-- Order Summary card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h4 class="font-semibold text-gray-800 mb-3 text-sm">{{ __('partner.orders.order_summary') }}</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>{{ __('partner.orders.order_number') }}</span>
                        <span class="font-mono text-xs text-gray-800">{{ $subOrder->sub_order_number }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>{{ __('common.subtotal') }}</span>
                        <span class="font-medium">{{ number_format($subOrder->subtotal / 100, 2) }} {{ $currency }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>{{ __('partner.orders.platform_commission') }}</span>
                        <span class="text-red-500">- {{ number_format($subOrder->platform_commission / 100, 2) }}
                            {{ $currency }}</span>
                    </div>
                    @if($subOrder->gateway_fee > 0)
                        <div class="flex justify-between text-gray-600">
                            <span class="inline-flex items-center gap-1">
                                {{ __('partner.orders.gateway_fee') }}
                                <span class="cursor-help text-gray-400" title="{{ __('partner.orders.gateway_fee_tooltip') }}">
                                    <x-heroicon name="information-circle" class="w-3.5 h-3.5" />
                                </span>
                            </span>
                            <span class="text-red-500">- {{ number_format($subOrder->gateway_fee / 100, 2) }}
                                {{ $currency }}</span>
                        </div>
                    @endif
                    <div class="border-t border-gray-100 pt-2 flex justify-between font-semibold text-gray-900">
                        <span>{{ __('partner.orders.net_payout') }}</span>
                        <span class="text-green-600">{{ number_format($subOrder->vendor_payout / 100, 2) }}
                            {{ $currency }}</span>
                    </div>
                </div>

                @if($subOrder->shipping_gap > 0)
                    <div class="mt-3 p-3 bg-orange-50 border border-orange-200 rounded text-sm">
                        <p class="font-semibold text-orange-800 mb-2">{{ __('partner.orders.exceptional_zone_delivery_cost') }}</p>
                        <div class="space-y-1 text-orange-700">
                            <div class="flex justify-between">
                                <span>{{ __('partner.orders.customer_paid_for_shipping') }}</span>
                                <span>{{ number_format($subOrder->shipping / 100, 2) }} {{ $currency }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ __('partner.orders.carrier_actual_charge') }}</span>
                                <span>{{ number_format($subOrder->carrier_shipping_cost / 100, 2) }} {{ $currency }}</span>
                            </div>
                            <div class="flex justify-between font-medium border-t border-orange-300 pt-1">
                                <span>{{ __('partner.orders.gap') }}</span>
                                <span>{{ number_format($subOrder->shipping_gap / 100, 2) }} {{ $currency }}</span>
                            </div>
                            <div class="flex justify-between text-red-700">
                                <span>{{ __('partner.orders.your_share_of_gap') }}</span>
                                <span>- {{ number_format($subOrder->vendor_contribution_amount / 100, 2) }} {{ $currency }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if(!$subOrder->cod_remittance_confirmed && $order->payment_method === 'cod')
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
                    <strong>{{ __('partner.orders.alert') }}:</strong> {{ __('partner.orders.cod_pending_notice', ['amount' => number_format($subOrder->vendor_payout / 100, 2) . ' ' . $currency]) }}
                </div>
            @endif

            {{-- SLA card --}}
            @if($subOrder->sla_ship_deadline && !in_array($subOrder->status->value, ['shipped', 'delivered', 'completed', 'cancelled']))
                <div @class([
                    'rounded-2xl border p-5',
                    'bg-red-50 border-red-200' => $isPast,
                    'bg-orange-50 border-orange-200' => !$isPast && $isUrgent,
                    'bg-white border-gray-200' => !$isPast && !$isUrgent,
                ])>
                    <div class="flex items-center gap-2 mb-2">
                        <x-heroicon name="clock" @class(['w-4 h-4', 'text-red-500' => $isPast, 'text-orange-500' => !$isPast && $isUrgent, 'text-gray-400' => !$isPast && !$isUrgent]) />
                        <h4
                            class="text-sm font-semibold {{ $isPast ? 'text-red-700' : ($isUrgent ? 'text-orange-700' : 'text-gray-700') }}">
                            {{ __('partner.orders.ship_by') }}
                        </h4>
                    </div>
                    <p
                        class="{{ $isPast ? 'text-red-800 font-bold' : ($isUrgent ? 'text-orange-700 font-semibold' : 'text-gray-800') }} text-sm">
                        {{ $subOrder->sla_ship_deadline->diffForHumans() }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ $subOrder->sla_ship_deadline->format('d M Y, H:i') }}
                    </p>
                </div>
            @endif

            {{-- Actions card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h4 class="font-semibold text-gray-800 mb-3 text-sm">{{ __('common.actions') }}</h4>
                <div class="space-y-2">

                    {{-- Current status badge --}}
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs text-gray-500">{{ __('partner.orders.current_status') }}</span>
                        <x-status-badge :status="$subOrder->status->value" />
                    </div>

                    @if($subOrder->status->value === 'placed')
                        <button id="btn-confirm"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                            <x-heroicon name="check-circle" class="w-4 h-4" />
                            {{ __('partner.orders.confirm_order') }}
                        </button>
                    @endif

                    @if(in_array($subOrder->status->value, ['placed', 'confirmed', 'processing', 'packed']))
                        @if($subOrder->shipping_method_id)
                            <button id="btn-ship"
                                class="w-full bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                                <x-heroicon name="truck" class="w-4 h-4" />
                                {{ __('partner.orders.ship_order') }}
                            </button>
                        @else
                            <p class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5 text-center">
                                {{ __('partner.orders.awaiting_shipping_method') }}
                            </p>
                        @endif
                    @endif

                    @if($subOrder->status->value === 'shipped')
                        <button id="btn-out-for-delivery"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                            <x-heroicon name="truck" class="w-4 h-4" />
                            {{ __('partner.orders.update_out_for_delivery') }}
                        </button>
                    @endif

                    @if(in_array($subOrder->status->value, ['shipped', 'out_for_delivery']))
                        <button id="btn-deliver"
                            class="w-full bg-green-700 hover:bg-green-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                            <x-heroicon name="check-badge" class="w-4 h-4" />
                            {{ __('partner.orders.confirm_delivery') }}
                        </button>
                    @endif

                    @if(!in_array($subOrder->status->value, ['shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'return_requested', 'returned']))
                        <button id="btn-cancel"
                            class="w-full bg-white border border-red-200 hover:bg-red-50 text-red-600 text-sm font-semibold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                            <x-heroicon name="x-circle" class="w-4 h-4" />
                            {{ __('partner.orders.cancel_order') }}
                        </button>
                    @endif

                    @if(in_array($subOrder->status->value, ['delivered', 'completed', 'cancelled']))
                        <p class="text-xs text-gray-400 text-center py-1">{{ __('partner.orders.no_actions_available') }}</p>
                    @endif

                </div>
            </div>

            {{-- Carrier / Tracking (if shipped) --}}
            @if($subOrder->status->value !== 'placed' && $subOrder->tracking_number)
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h4 class="font-semibold text-gray-800 mb-3 text-sm flex items-center gap-2">
                        <x-heroicon name="truck" class="w-4 h-4 text-gray-400" />
                        {{ __('partner.orders.shipping_info') }}
                    </h4>
                    <div class="space-y-2 text-sm">
                        @if($subOrder->shippingMethod)
                            <div class="flex justify-between text-gray-600">
                                <span>{{ __('partner.orders.shipping_method') }}</span>
                                <span class="font-medium">{{ $subOrder->shippingMethod->name }}</span>
                            </div>
                        @endif
                        @if($subOrder->carrier)
                            <div class="flex justify-between text-gray-600">
                                <span>{{ __('partner.orders.carrier') }}</span>
                                <span class="font-medium">{{ $subOrder->carrier->name }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-gray-600">
                            <span>{{ __('partner.orders.tracking_number') }}</span>
                            <span class="font-mono text-xs text-gray-800 select-all">{{ $subOrder->tracking_number }}</span>
                        </div>
                        @if($subOrder->estimated_delivery_date)
                            <div class="flex justify-between text-gray-600">
                                <span>{{ __('partner.orders.estimated_delivery') }}</span>
                                <span class="font-medium">{{ $subOrder->estimated_delivery_date->format('d M Y') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </div>

    </div>

    {{-- ── SHIP MODAL ── --}}
    <div id="ship-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">{{ __('partner.orders.confirm_shipment') }}</h3>
                <button id="ship-modal-close" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <x-heroicon name="x-mark" class="w-5 h-5" />
                </button>
            </div>
            <form id="ship-form" class="p-6 space-y-4">
                @if($subOrder->shippingMethod)
                    <div class="text-sm text-gray-600 bg-gray-50 rounded-xl px-3 py-2.5">
                        {{ __('partner.orders.assigned_shipping_method') }} <span class="font-medium text-gray-800">{{ $subOrder->shippingMethod->name }}</span>
                        @if($subOrder->carrier)
                            {{ __('partner.orders.via_carrier') }} <span class="font-medium text-gray-800">{{ $subOrder->carrier->name }}</span>
                        @endif
                    </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.orders.tracking_number') }}</label>
                    <input type="text" name="tracking_number" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                        placeholder="{{ __('partner.orders.tracking_number_placeholder') }}">
                </div>
                <div id="ship-delivery-estimate" class="hidden bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
                    <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-0.5">
                        {{ __('partner.orders.estimated_delivery_auto') }}
                    </p>
                    <p id="ship-delivery-label" class="text-sm font-medium text-indigo-800"></p>
                    <p class="text-xs text-indigo-400 mt-0.5">{{ __('partner.orders.estimated_delivery_auto_note') }}</p>
                </div>
                <div id="ship-no-method" class="hidden bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">
                    {{ __('partner.orders.no_shipping_method_estimate') }}
                </div>
                <div id="ship-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 rounded-xl transition-colors text-sm">
                        {{ __('partner.orders.confirm_shipment') }}
                    </button>
                    <button type="button" id="ship-cancel-btn"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl transition-colors text-sm">
                        {{ __('common.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── CANCEL MODAL ── --}}
    <div id="cancel-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 text-red-700">{{ __('partner.orders.cancel_order') }}</h3>
                <button id="cancel-modal-close" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <x-heroicon name="x-mark" class="w-5 h-5" />
                </button>
            </div>
            <form id="cancel-form" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.orders.cancel_reason') }}</label>
                    <select name="reason" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400/40">
                        <option value="">{{ __('partner.orders.select_reason') }}</option>
                        <option value="out_of_stock">{{ __('partner.orders.reason_out_of_stock') }}</option>
                        <option value="unable_to_fulfill">{{ __('partner.orders.reason_unable_to_fulfill') }}</option>
                        <option value="duplicate_order">{{ __('partner.orders.reason_duplicate_order') }}</option>
                        <option value="customer_request">{{ __('partner.orders.reason_customer_request') }}</option>
                        <option value="pricing_error">{{ __('partner.orders.reason_pricing_error') }}</option>
                        <option value="other">{{ __('partner.orders.reason_other') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.orders.additional_notes') }} <span
                            class="text-gray-400">({{ __('common.optional') }})</span></label>
                    <textarea name="reason_notes" rows="3"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400/40 resize-none"
                        placeholder="{{ __('partner.orders.additional_notes_placeholder') }}"></textarea>
                </div>
                <div id="cancel-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl transition-colors text-sm">
                        {{ __('partner.orders.confirm_cancellation') }}
                    </button>
                    <button type="button" id="cancel-cancel-btn"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl transition-colors text-sm">
                        {{ __('common.back') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection