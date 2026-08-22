@extends('layouts.delivery')

@section('title', __('delivery.dashboard.title'))

@section('header-right')
    <form method="POST" action="{{ route('delivery.logout') }}">
        @csrf
        <button type="submit" class="text-slate-400 text-sm">{{ __('delivery.nav.logout') }}</button>
    </form>
@endsection

@section('content')

@php
    /** @var \App\Models\DeliveryAgent $agent */
    $statusChipMap = [
        'active'    => 'chip-accepted',
        'on_shift'  => 'chip-picked_up',
        'suspended' => 'chip-failed',
        'inactive'  => 'chip-assigned',
    ];
@endphp

{{-- ── Availability Toggle ─────────────────────────────────────────────────── --}}
<div class="d-card mb-4"
     x-data="{
         isAvailable: {{ auth('delivery')->user()->is_available ? 'true' : 'false' }},
         loading: false,
         toggle() {
             if (this.loading) return;
             this.loading = true;
             fetch('{{ route('delivery.availability.update') }}', {
                 method: 'PUT',
                 headers: {
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                     'Accept': 'application/json',
                 },
                 body: JSON.stringify({ is_available: !this.isAvailable }),
             })
             .then(r => r.json())
             .then(d => { if (d.success) this.isAvailable = d.is_available; })
             .finally(() => this.loading = false);
         }
     }">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-base font-bold" x-text="isAvailable ? '🟢 ' + {{ Illuminate\Support\Js::from(__('delivery.dashboard.online')) }} : '⚫ ' + {{ Illuminate\Support\Js::from(__('delivery.dashboard.offline')) }}"></p>
            <p class="text-xs text-slate-400 mt-0.5" x-text="isAvailable ? '{{ __('delivery.dashboard.ready_for_assignments') }}' : '{{ __('delivery.dashboard.not_receiving_assignments') }}'"></p>
        </div>
        <button type="button" @click="toggle()" :disabled="loading"
            class="relative focus:outline-none" style="width:64px; height:34px;">
            <div class="toggle-track" :class="{ 'on': isAvailable }">
                <div class="toggle-thumb"></div>
            </div>
        </button>
    </div>
</div>

{{-- ── Today Stats ─────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-3 mb-4">
    @foreach([
        ['label' => __('delivery.dashboard.total'),     'value' => $todayAssignments->total     ?? 0, 'color' => 'text-slate-300'],
        ['label' => __('delivery.dashboard.completed'), 'value' => $todayAssignments->completed ?? 0, 'color' => 'text-green-400'],
        ['label' => __('delivery.earnings.title'),  'value' => number_format(($earningsToday ?? 0) / 100, 2), 'color' => 'text-yellow-400'],
    ] as $s)
        <div class="d-card text-center">
            <p class="text-xl font-bold {{ $s['color'] }}">{{ $s['value'] }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ $s['label'] }}</p>
        </div>
    @endforeach
</div>

{{-- ── Zone Info ────────────────────────────────────────────────────────────── --}}
@if($agent->zone)
<div class="d-card mb-4">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-sm">{{ __('delivery.zone.your_zone') }}</h2>
        <span class="text-xs text-slate-500 font-mono">{{ $agent->zone->code }}</span>
    </div>
    <p class="text-lg font-black text-indigo-400 mb-3">{{ $agent->zone->name }}</p>
    <div class="grid grid-cols-2 gap-3 text-sm mb-4">
        <div>
            <p class="text-slate-500 text-xs">{{ __('delivery.zone.delivery_fee') }}</p>
            <p class="font-semibold">{{ number_format($agent->zone->base_delivery_fee) }} {{ $agent->zone->country?->currency_code }}</p>
        </div>
        <div>
            <p class="text-slate-500 text-xs">{{ __('delivery.zone.cod_fee') }}</p>
            <p class="font-semibold">{{ number_format($agent->zone->cod_fee) }} {{ $agent->zone->country?->currency_code }}</p>
        </div>
    </div>
    @if($zoneCities->isNotEmpty())
    <div>
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('delivery.zone.covered_cities') }}</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach($zoneCities as $city)
            <span class="bg-indigo-500/10 text-indigo-300 text-xs font-medium px-2 py-0.5 rounded-full">
                {{ app()->getLocale() === 'ar' ? $city->name_ar : $city->name_en }}
            </span>
            @endforeach
        </div>
    </div>
    @endif
</div>
@else
<div class="d-card mb-4 text-sm text-amber-400">
    {{ __('delivery.zone.no_zone_assigned') }}
</div>
@endif

{{-- ── Pending Assignments ──────────────────────────────────────────────────── --}}
@if($pendingAssignments->isNotEmpty())
    <div class="mb-5">
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.dashboard.pending_actions') }}</h2>
        <div class="space-y-3">
            @foreach($pendingAssignments as $a)
                @php
                    $chipClass = 'chip-' . $a->status->value;
                    $actionLabel = match($a->status) {
                        \App\Enums\DeliveryAssignmentStatus::Assigned  => __('delivery.assignments.accept'),
                        \App\Enums\DeliveryAssignmentStatus::Accepted  => __('delivery.dashboard.mark_picked_up'),
                        \App\Enums\DeliveryAssignmentStatus::PickedUp => __('delivery.assignments.confirm_delivery'),
                        default     => __('delivery.dashboard.view'),
                    };
                    $actionColor = match($a->status) {
                        \App\Enums\DeliveryAssignmentStatus::PickedUp => 'btn-yellow',
                        \App\Enums\DeliveryAssignmentStatus::Accepted  => 'btn-blue',
                        default     => 'btn-outline',
                    };
                @endphp
                <a href="{{ route('delivery.assignments.show', $a->id) }}" class="block d-card hover:bg-slate-700/60 transition-colors">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <p class="font-semibold text-sm">#{{ $a->subOrder?->sub_order_number ?? $a->id }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ $a->subOrder?->items?->count() ?? 0 }} {{ __('delivery.assignments.items') }}
                                @if($a->assigned_at)
                                    · {{ $a->assigned_at->format('H:i') }}
                                @endif
                            </p>
                        </div>
                        <span class="chip {{ $chipClass }}">{{ __('delivery.assignments.status_' . $a->status->value) }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                        <span class="flex-1 btn-action {{ $actionColor }} text-sm min-h-[46px]">{{ $actionLabel }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@else
    <div class="d-card text-center py-10 mb-4">
        <div class="text-4xl mb-3">📦</div>
        <p class="font-semibold text-slate-300">{{ __('delivery.dashboard.no_pending_assignments') }}</p>
        <p class="text-sm text-slate-500 mt-1">{{ __('delivery.dashboard.check_back_soon') }}</p>
    </div>
@endif

{{-- ── Quick Actions ────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-3 mb-6">
    <a href="{{ route('delivery.assignments.index') }}"
       class="d-card text-center py-5 hover:bg-slate-700/60 transition-colors">
        <div class="text-2xl mb-1">📦</div>
        <p class="text-sm font-semibold">{{ __('delivery.dashboard.all_orders') }}</p>
        <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.dashboard.todays_list') }}</p>
    </a>
    <a href="{{ route('delivery.earnings.index') }}"
       class="d-card text-center py-5 hover:bg-slate-700/60 transition-colors">
        <div class="text-2xl mb-1">💰</div>
        <p class="text-sm font-semibold">{{ __('delivery.dashboard.my_earnings') }}</p>
        <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.dashboard.payouts_and_history') }}</p>
    </a>
</div>

@endsection

@push('scripts')
<script>
// Start background location tracking
@if(auth('delivery')->user()->status === \App\Enums\DeliveryAgentStatus::OnShift || auth('delivery')->user()->is_available)
    window.DeliveryLocation.start(@json(route('delivery.location.update')));
@endif
</script>
@endpush
