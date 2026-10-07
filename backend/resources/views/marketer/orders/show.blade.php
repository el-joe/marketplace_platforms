@extends('layouts.marketer')
@section('title', __('marketer.orders.show_title'))
@section('page-title', __('marketer.orders.show_title'))

@section('content')
<div class="max-w-2xl space-y-5">

@if(isset($subOrder))
    {{-- ── Own-listing sub-order detail ── --}}
    @php
        $order = $subOrder->order;
        $snap  = $order->shipping_address_snapshot ?? [];
        $city    = $snap['city'] ?? null;
        $country = $order->country?->{'name_' . app()->getLocale()} ?? $order->country?->name_en ?? null;
        $location = collect([$city, $country])->filter()->implode('، ');

        $statusKey = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : (string) $subOrder->status;
        $statusColors = [
            'placed'           => 'bg-blue-100 text-blue-700',
            'confirmed'        => 'bg-indigo-100 text-indigo-700',
            'processing'       => 'bg-purple-100 text-purple-700',
            'packed'           => 'bg-orange-100 text-orange-700',
            'shipped'          => 'bg-sky-100 text-sky-700',
            'out_for_delivery' => 'bg-teal-100 text-teal-700',
            'delivered'        => 'bg-green-100 text-green-700',
            'completed'        => 'bg-green-200 text-green-800',
            'cancelled'        => 'bg-red-100 text-red-700',
            'returned'         => 'bg-pink-100 text-pink-700',
            'refunded'         => 'bg-gray-100 text-gray-500',
        ];
        $statusCls = $statusColors[$statusKey] ?? 'bg-gray-100 text-gray-500';
    @endphp

    {{-- Order summary card --}}
    <div class="bg-white rounded-xl border p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <div class="font-black text-gray-900 text-lg">{{ $order->order_number }}</div>
                <div class="text-xs text-gray-400 mt-0.5">{{ $order->placed_at?->format('Y-m-d H:i') }}</div>
            </div>
            <div>
                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $statusCls }}">{{ $statusKey }}</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm border-t border-gray-100 pt-4">
            <div>
                <span class="text-gray-400">{{ __('marketer.orders.location_header') }}: </span>
                <strong>{{ $location ?: '—' }}</strong>
            </div>
            <div>
                <span class="text-gray-400">{{ __('marketer.orders.total_label') }}: </span>
                <strong class="text-gray-900">{{ number_format($subOrder->subtotal) }} {{ $order->currency }}</strong>
            </div>
            @if($subOrder->warehouse)
            <div>
                <span class="text-gray-400">{{ __('marketer.orders.warehouse_label') }}: </span>
                <strong>{{ $subOrder->warehouse->name }}</strong>
            </div>
            @endif
        </div>
    </div>

    {{-- Order items --}}
    @if($subOrder->items->isNotEmpty())
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b font-bold text-gray-800">{{ __('marketer.orders.items_heading') }}</div>
        <div class="divide-y divide-gray-100">
            @foreach($subOrder->items as $item)
            @php
                $snap = $item->product_snapshot ?? [];
                $productName = $snap['name_ar'] ?? $snap['name_en']
                    ?? $item->productVariant?->product?->{'name_' . app()->getLocale()}
                    ?? $item->productVariant?->product?->name_en
                    ?? '—';
                $fulfillKey = $item->fulfillment_status ?? '';
            @endphp
            <div class="px-5 py-4 flex items-center gap-4">
                <div class="flex-1">
                    <div class="font-semibold text-sm text-gray-900">{{ $productName }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">
                        {{ __('marketer.orders.quantity_label') }} {{ $item->quantity }} × {{ number_format($item->unit_price) }}
                        @if($fulfillKey)
                            <span class="ms-2 px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded text-xs">{{ $fulfillKey }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-sm font-bold text-gray-900">{{ number_format($item->line_total) }}</div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 border-t bg-gray-50 flex justify-between text-sm font-bold">
            <span>{{ __('marketer.orders.total_label') }}</span>
            <span>{{ number_format($subOrder->subtotal) }} {{ $order->currency }}</span>
        </div>
    </div>
    @endif

    {{-- Shipping destination (city/country only — no customer name or full address) --}}
    @if($location)
    <div class="bg-white rounded-xl border p-5">
        <div class="font-bold text-gray-800 mb-2 text-sm">{{ __('marketer.orders.shipping_destination_heading') }}</div>
        <div class="text-sm text-gray-600">{{ $location }}</div>
    </div>
    @endif

@else
    {{-- ── Campaign conversion detail (existing) ── --}}
    @php
        $order    = $conversion->order;
        $campaign = $conversion->invitation->campaign;
    @endphp

    {{-- Order summary card --}}
    <div class="bg-white rounded-xl border p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <div class="font-black text-gray-900 text-lg">{{ $order->order_number }}</div>
                <div class="text-xs text-gray-400 mt-0.5">{{ $order->placed_at?->format('Y-m-d H:i') }}</div>
            </div>
            <div class="flex flex-col items-end gap-1">
                <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded font-semibold">{{ $order->status }}</span>
                @if($conversion->commissioned)
                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded">{{ __('marketer.orders.commission_collected_badge') }}</span>
                @else
                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded">{{ __('marketer.orders.commission_pending_badge') }}</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm border-t border-gray-100 pt-4">
            <div>
                <span class="text-gray-400">{{ __('marketer.orders.commission_earned_label') }} </span>
                <strong class="text-green-600">{{ number_format($conversion->commission_amount) }} {{ $conversion->currency }}</strong>
            </div>
            <div>
                <span class="text-gray-400">{{ __('marketer.orders.campaign_label') }} </span>
                <strong>{{ $campaign->getPromotedTitle() }}</strong>
            </div>
            <div>
                <span class="text-gray-400">{{ __('marketer.orders.campaign_type_label') }} </span>
                <strong>{{ [
                    'product'    => __('marketer.orders.type_product'),
                    'travel'     => __('marketer.orders.type_travel'),
                    'classified' => __('marketer.orders.type_classified'),
                ][$campaign->campaign_category ?? 'product'] }}</strong>
            </div>
        </div>
    </div>

    {{-- Order items --}}
    @if($order->items->isNotEmpty())
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b font-bold text-gray-800">{{ __('marketer.orders.items_heading') }}</div>
        <div class="divide-y divide-gray-100">
            @foreach($order->items as $item)
            @php $snap = $item->product_snapshot ?? []; @endphp
            <div class="px-5 py-4 flex items-center gap-4">
                <div class="flex-1">
                    <div class="font-semibold text-sm text-gray-900">{{ $snap['name_ar'] ?? $snap['name_en'] ?? '—' }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ __('marketer.orders.quantity_label') }} {{ $item->quantity }} × {{ number_format($item->unit_price) }}</div>
                </div>
                <div class="text-sm font-bold text-gray-900">{{ number_format($item->line_total) }}</div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 border-t bg-gray-50 flex justify-between text-sm font-bold">
            <span>{{ __('marketer.orders.total_label') }}</span>
            <span>{{ number_format($order->total) }} {{ $order->currency }}</span>
        </div>
    </div>
    @endif

@endif

    {{-- Read-only notice --}}
    <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl text-sm text-blue-700">
        {{ __('marketer.orders.readonly_notice') }}
    </div>

    <a href="{{ route('marketer.orders.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        {{ __('marketer.orders.back_to_orders') }}
    </a>

</div>
@endsection
