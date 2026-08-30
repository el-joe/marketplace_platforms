@extends('layouts.carrier')

@section('title', __('carrier.assignment_detail.breadcrumb'))

@section('content')

@php
    $statusColors = [
        'assigned'  => 'amber',
        'accepted'  => 'blue',
        'picked_up' => 'indigo',
        'delivered' => 'emerald',
        'failed'    => 'red',
    ];
    $statusLabels = [
        'assigned'  => __('carrier.assignments.status_assigned'),
        'accepted'  => __('carrier.assignments.status_accepted'),
        'picked_up' => __('carrier.assignments.status_picked_up'),
        'delivered' => __('carrier.assignments.status_delivered'),
        'failed'    => __('carrier.assignments.status_failed'),
    ];
    $failureReasonLabels = [
        'customer_unavailable' => __('carrier.assignment_detail.failure_reasons.customer_unavailable'),
        'wrong_address'        => __('carrier.assignment_detail.failure_reasons.wrong_address'),
        'refused_delivery'     => __('carrier.assignment_detail.failure_reasons.refused_delivery'),
        'damaged_item'         => __('carrier.assignment_detail.failure_reasons.damaged_item'),
        'other'                => __('carrier.assignment_detail.failure_reasons.other'),
    ];

    $shipment   = $assignment->shipment;
    $subOrder   = $shipment?->subOrder;
    $order      = $subOrder?->order;
    $customer   = $order?->customer;
    $address    = $order?->shipping_address_snapshot ?? [];

    $maskedName = (function () use ($customer): string {
        $name = $customer?->name;
        if (!$name) return '—';
        $parts = explode(' ', trim($name));
        if (count($parts) === 1) return $parts[0];
        return $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.';
    })();

    $maskedPhone = $customer?->phone
        ? preg_replace('/(\d{3})\d+(\d{2})/', '$1•••••$2', $customer->phone)
        : '—';

    $c = $statusColors[$assignment->status->value] ?? 'gray';

    $canReassign = in_array($assignment->status, [\App\Enums\DeliveryAssignmentStatus::Assigned, \App\Enums\DeliveryAssignmentStatus::Accepted], true);

    $timeline = [
        ['label' => __('carrier.assignment_detail.stage_assigned'),  'at' => $assignment->assigned_at],
        ['label' => __('carrier.assignment_detail.stage_accepted'),  'at' => $assignment->accepted_at],
        ['label' => __('carrier.assignment_detail.stage_picked_up'), 'at' => $assignment->picked_up_at],
        ['label' => __('carrier.assignment_detail.stage_delivered'), 'at' => $assignment->delivered_at ?? $assignment->failed_at],
    ];
    $timeline = array_filter($timeline, fn ($step) => $step['at'] !== null);
@endphp

{{-- Breadcrumb --}}
<div class="mb-6 flex items-center gap-2 text-sm text-gray-500">
    <a href="{{ route('carrier.assignments.index') }}" class="hover:text-indigo-600 transition">{{ __('carrier.nav.assignments') }}</a>
    <span>/</span>
    <span class="text-gray-800 font-medium">{{ __('carrier.assignment_detail.breadcrumb') }}</span>
</div>

{{-- Header --}}
<div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
    <div>
        <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.assignment_detail.breadcrumb') }}</h1>
        @if($subOrder)
        <p class="text-sm text-gray-500 mt-0.5 font-mono">{{ $subOrder->sub_order_number }}</p>
        @endif
    </div>
    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-{{ $c }}-100 text-{{ $c }}-700">
        {{ $statusLabels[$assignment->status->value] ?? $assignment->status->value }}
    </span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── LEFT (main) ── --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Customer & Delivery Info --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('carrier.assignment_detail.customer_info') }}</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">{{ __('carrier.assignment_detail.name') }}</p>
                    <p class="font-medium text-gray-900">{{ $maskedName }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">{{ __('carrier.assignment_detail.phone') }}</p>
                    <p class="font-medium text-gray-900 font-mono">{{ $maskedPhone }}</p>
                </div>
            </div>
            @if(!empty($address))
            <div class="mt-4 pt-4 border-t border-gray-100 text-sm text-gray-700 space-y-0.5">
                @if(!empty($address['name']))<p class="font-medium">{{ $address['name'] }}</p>@endif
                @if(!empty($address['address_line_1']))<p>{{ $address['address_line_1'] }}</p>@endif
                @if(!empty($address['address_line_2']))<p>{{ $address['address_line_2'] }}</p>@endif
                <p>
                    @if(!empty($address['city']))
                        @php
                            $rawCity = $address['city'];
                            $cityLabel = is_array($rawCity)
                                ? ($rawCity[app()->getLocale()] ?? $rawCity['en'] ?? $rawCity['ar'] ?? '')
                                : $rawCity;
                        @endphp
                        {{ $cityLabel }}
                    @endif
                    @if(!empty($address['state'])), {{ $address['state'] }}@endif
                    @if(!empty($address['postal_code'])) {{ $address['postal_code'] }}@endif
                </p>
                @if(!empty($address['country']))<p>{{ $address['country'] }}</p>@endif
            </div>
            @endif
        </div>

        {{-- Order Items --}}
        @if($subOrder && $subOrder->items->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('carrier.assignment_detail.order_items') }}</h3>
            <div class="divide-y divide-gray-50">
                @foreach($subOrder->items as $item)
                @php
                    $snap    = $item->product_snapshot ?? [];
                    $imgUrl  = $snap['image_url'] ?? null;
                    $name    = $snap['name_ar'] ?? $snap['name'] ?? __('carrier.assignment_detail.product');
                    $variant = $snap['variant'] ?? null;
                @endphp
                <div class="flex items-start gap-4 py-4 first:pt-0 last:pb-0">
                    <div class="w-14 h-14 rounded-xl border border-gray-100 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                        @if($imgUrl)
                            <img src="{{ $imgUrl }}" alt="{{ $name }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 text-sm">{{ $name }}</p>
                        @if($variant)<p class="text-xs text-gray-500 mt-0.5">{{ $variant }}</p>@endif
                        <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $item->sku }}</p>
                    </div>
                    <div class="text-right shrink-0 text-sm">
                        <p class="text-gray-500">{{ $item->quantity }} × {{ number_format($item->unit_price, 2) }}</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ number_format($item->line_total, 2) }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Shipment Tracking --}}
        @if($shipment && $shipment->trackingEvents->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-1">{{ __('carrier.assignment_detail.shipment_tracking') }}</h3>
            <p class="text-xs text-gray-400 mb-4 font-mono">{{ $shipment->tracking_number }}</p>
            <ol class="relative border-s border-gray-200 space-y-4 pe-4">
                @foreach($shipment->trackingEvents as $event)
                <li class="ms-4">
                    <div class="absolute w-2 h-2 bg-gray-300 rounded-full -start-1 mt-1.5 border border-white"></div>
                    <p class="text-sm text-gray-700">{{ $event->description }}</p>
                    <time class="text-xs text-gray-400">{{ $event->occurred_at?->format('Y-m-d H:i') }}</time>
                </li>
                @endforeach
            </ol>
        </div>
        @endif

    </div>

    {{-- ── RIGHT SIDEBAR ── --}}
    <div class="space-y-6">

        {{-- Assignment Status Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('carrier.assignment_detail.assignment_status') }}</h3>

            {{-- Agent info --}}
            <div class="mb-4 pb-4 border-b border-gray-100 text-sm space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('carrier.assignment_detail.agent') }}</span>
                    <span class="font-medium text-gray-900">{{ $assignment->agent?->name ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('carrier.assignment_detail.vehicle') }}</span>
                    <span class="text-gray-700">{{ $assignment->agent?->vehicle_type?->label() ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('carrier.assignment_detail.phone') }}</span>
                    <span class="font-mono text-gray-700 text-xs">{{ $assignment->agent?->phone ?? '—' }}</span>
                </div>
            </div>

            {{-- Timeline — only show steps that actually occurred --}}
            @if(!empty($timeline))
            <div class="mb-4 pb-4 border-b border-gray-100">
                <p class="text-xs text-gray-400 mb-3 font-semibold uppercase">{{ __('carrier.assignment_detail.stages') }}</p>
                <ol class="relative border-s border-indigo-200 space-y-3 ps-4">
                    @foreach($timeline as $step)
                    <li>
                        <div class="absolute w-2 h-2 bg-indigo-400 rounded-full -start-1 border border-white"></div>
                        <p class="text-sm font-medium text-gray-800">{{ $step['label'] }}</p>
                        <time class="text-xs text-gray-400">{{ $step['at']->format('Y-m-d H:i') }}</time>
                    </li>
                    @endforeach
                </ol>
            </div>
            @endif

            {{-- Failed callout --}}
            @if($assignment->status === \App\Enums\DeliveryAssignmentStatus::Failed)
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm">
                <p class="font-semibold text-red-700 mb-1">
                    {{ $failureReasonLabels[$assignment->failure_reason] ?? $assignment->failure_reason ?? __('carrier.assignment_detail.delivery_failed') }}
                </p>
                @if($assignment->failure_notes)
                <p class="text-red-600 text-xs">{{ $assignment->failure_notes }}</p>
                @endif
            </div>
            @endif

            {{-- Delivered extras --}}
            @if($assignment->status === \App\Enums\DeliveryAssignmentStatus::Delivered)
            <div class="mb-4 pb-4 border-b border-gray-100 text-sm space-y-1.5">
                @if($assignment->customer_rating)
                <div class="flex justify-between items-center">
                    <span class="text-gray-500">{{ __('carrier.assignment_detail.customer_rating') }}</span>
                    <span class="text-amber-500">
                        @for($i = 1; $i <= 5; $i++)
                            {{ $i <= $assignment->customer_rating ? '★' : '☆' }}
                        @endfor
                    </span>
                </div>
                @endif
                @if($assignment->agent_notes)
                <div>
                    <p class="text-gray-500 mb-0.5">{{ __('carrier.assignment_detail.agent_notes') }}</p>
                    <p class="text-gray-700 text-xs">{{ $assignment->agent_notes }}</p>
                </div>
                @endif
            </div>
            @endif

            {{-- COD --}}
            @if($assignment->cod_amount_collected)
            <div class="mb-4 pb-4 border-b border-gray-100 text-sm space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('carrier.assignment_detail.cash_on_delivery') }}</span>
                    <span class="font-semibold text-gray-900">{{ number_format($assignment->cod_amount_collected, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('carrier.assignment_detail.otp') }}</span>
                    <span class="{{ $assignment->otp_verified ? 'text-emerald-600' : 'text-red-500' }} font-medium text-xs">
                        {{ $assignment->otp_verified ? __('carrier.assignment_detail.verified') : __('carrier.assignment_detail.not_verified') }}
                    </span>
                </div>
            </div>
            @endif

            {{-- Reassign button --}}
            @if($canReassign && auth('shipping_supervisor')->user()->hasPermission('assign_orders'))
            <button onclick="const m=document.getElementById('reassign-modal');m.classList.remove('hidden');m.style.display='flex';"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 rounded-xl transition">
                {{ __('carrier.assignment_detail.reassign_agent') }}
            </button>
            @endif
        </div>

        {{-- Shipment info --}}
        @if($shipment)
        <div class="bg-white rounded-2xl border border-gray-200 p-6 text-sm space-y-2">
            <h3 class="font-semibold text-gray-800 mb-3">{{ __('carrier.assignment_detail.shipment_info') }}</h3>
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('carrier.assignment_detail.tracking_number') }}</span>
                <span class="font-mono text-xs text-gray-700">{{ $shipment->tracking_number }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('carrier.assignment_detail.status') }}</span>
                <span class="text-gray-700">{{ $shipment->status->value }}</span>
            </div>
            @if($shipment->weight_grams)
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('carrier.assignment_detail.weight') }}</span>
                <span class="text-gray-700">{{ number_format($shipment->weight_grams / 1000, 2) }} {{ __('carrier.assignment_detail.weight_unit_kg') }}</span>
            </div>
            @endif
        </div>
        @endif

    </div>
</div>

{{-- Reassign Modal --}}
@if($canReassign && auth('shipping_supervisor')->user()->hasPermission('assign_orders'))
<div id="reassign-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-bold text-gray-900">{{ __('carrier.assignment_detail.reassign_agent') }}</h2>
            <button onclick="const m=document.getElementById('reassign-modal');m.style.display='none';m.classList.add('hidden');"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>

        @if($availableAgents->isEmpty())
        <p class="text-sm text-gray-500 text-center py-6">{{ __('carrier.assignments.no_available_agents') }}</p>
        @else
        <div id="reassign-agents" class="space-y-2 max-h-72 overflow-y-auto mb-5">
            @foreach($availableAgents as $agent)
            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-indigo-400 transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                <input type="radio" name="reassign_agent" value="{{ $agent->id }}" class="accent-indigo-600">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900">{{ $agent->name }}</p>
                    <p class="text-xs text-gray-500">{{ $agent->vehicle_type->label() }}</p>
                </div>
                @if($agent->rating_avg)
                <span class="text-xs text-amber-500 shrink-0">★ {{ number_format($agent->rating_avg, 1) }}</span>
                @endif
            </label>
            @endforeach
        </div>
        <button onclick="submitReassign('{{ route('carrier.assignments.reassign', $assignment->id) }}')"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl transition text-sm">
            {{ __('carrier.assignments.confirm_reassign') }}
        </button>
        @endif
    </div>
</div>
@endif

@push('scripts')
<script>
function submitReassign(url) {
    const selected = document.querySelector('input[name="reassign_agent"]:checked');
    if (!selected) { alert('{{ __('carrier.assignments.please_select_agent') }}'); return; }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ new_agent_id: selected.value }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { window.location.reload(); }
        else { alert(data.message); }
    });
}
</script>
@endpush

@endsection
