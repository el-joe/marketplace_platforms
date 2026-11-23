{{-- Distance-based delivery snapshot stored on the sub-order at place-order (client item #19). --}}
@php
    $dp = $subOrder->distance_pricing;
    $km = fn ($value) => $value === null ? '—' : __('store_location.order.km', ['km' => rtrim(rtrim(number_format((float) $value, 1), '0'), '.')]);
    $originType = $dp['origin_type'] ?? null;
    $originName = in_array($originType, ['fulfilling_warehouse', 'vendor_warehouse'], true)
        ? ($distanceOriginWarehouses[$dp['origin_id']] ?? null)
        : null;
    $tier = $dp['tier'] ?? null;
    $status = $dp['status'] ?? 'fallback';
@endphp
<div class="col-span-3 rounded-lg border border-blue-100 bg-blue-50/50 px-3 py-2 space-y-1" data-distance-snapshot>
    <div class="flex flex-wrap items-center gap-2">
        <span class="font-medium text-gray-700">{{ __('store_location.order.title') }}:</span>
        <x-badge :color="match($status) { 'tier' => 'primary', 'free_within_radius', 'free_by_order_amount' => 'success', 'unavailable' => 'danger', default => 'gray' }">
            {{ __('store_location.order.statuses.'.$status) }}
        </x-badge>
        @if(! ($dp['applied'] ?? false))
            <span class="text-gray-500">{{ __('store_location.order.not_charged') }}</span>
        @endif
        @if(! empty($dp['fallback_reason']))
            <span class="text-gray-500">({{ __('store_location.order.fallback_reasons.'.$dp['fallback_reason']) }})</span>
        @endif
    </div>
    <div class="flex flex-wrap gap-x-4 gap-y-1">
        <span><span class="font-medium text-gray-700">{{ __('store_location.order.distance') }}:</span> <span dir="ltr">{{ $km($dp['distance_km'] ?? null) }}</span></span>
        <span>
            <span class="font-medium text-gray-700">{{ __('store_location.order.origin') }}:</span>
            {{ __('store_location.order.origin_types.'.($originType ?? 'none')) }}@if($originName) — {{ $originName }}@endif
        </span>
        @if($tier)
            <span>
                <span class="font-medium text-gray-700">{{ __('store_location.order.tier') }}:</span>
                <span dir="ltr">{{ $tier['to_km'] === null
                    ? __('store_location.order.tier_open', ['from' => $tier['from_km']])
                    : __('store_location.order.tier_range', ['from' => $tier['from_km'], 'to' => $tier['to_km']]) }}</span>
            </span>
        @endif
        @if(($dp['fee'] ?? null) !== null)
            <span><span class="font-medium text-gray-700">{{ __('store_location.order.fee') }}:</span> {{ $fmt($dp['fee']) }}</span>
        @endif
        <span class="text-gray-400">
            {{ __('store_location.order.rule') }}:
            @if(($dp['free_radius_km'] ?? null) !== null){{ __('store_location.order.free_radius', ['km' => $dp['free_radius_km']]) }} · @endif
            @if(($dp['max_service_radius_km'] ?? null) !== null){{ __('store_location.order.max_radius', ['km' => $dp['max_service_radius_km']]) }} · @endif
            <span class="font-mono">{{ \Illuminate\Support\Str::limit((string) ($dp['rule_id'] ?? ''), 8, '') }}</span>
        </span>
    </div>
</div>
