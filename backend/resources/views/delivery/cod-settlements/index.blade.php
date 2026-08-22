@extends('layouts.delivery')

@section('title', __('delivery.cod.my_cod_payments'))

@section('content')

@php
    /** @var \App\Models\DeliveryAgent $agent */
    $currency = $agent->country?->currency_code
        ?? $agent->zone?->country?->currency_code
        ?? 'AED';

    $statusChipMap = [
        'pending'  => ['class' => 'chip-cod-pending',  'label' => __('delivery.cod.status_pending')],
        'settled'  => ['class' => 'chip-cod-settled',  'label' => __('delivery.cod.status_settled')],
        'disputed' => ['class' => 'chip-cod-disputed', 'label' => __('delivery.cod.status_disputed')],
    ];

    function codAmount(int $amount, string $currency = 'SAR'): string {
        return number_format($amount, 2) . ' ' . $currency;
    }
@endphp

<style>
    .chip-cod-pending  { background:#4a3000; color:#facc15; }
    .chip-cod-settled  { background:#14532d; color:#86efac; }
    .chip-cod-disputed { background:#450a0a; color:#fca5a5; }

    [dir="rtl"] { direction: rtl; text-align: right; }

    .settlement-row { cursor: pointer; }
    .delivery-rows  { display: none; }
    .delivery-rows.open { display: table-row-group; }
</style>

<div dir="rtl" class="space-y-5">

    {{-- ── Page Title ───────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-3 mb-1">
        <div class="text-2xl">💵</div>
        <div>
            <h1 class="text-lg font-bold">{{ __('delivery.cod.my_cod_payments') }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.cod.settlements_subtitle') }}</p>
        </div>
    </div>

    {{-- ── Current Period Summary ───────────────────────────────────────────── --}}
    <div class="d-card space-y-3">
        <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-slate-300">{{ __('delivery.cod.current_period_today') }}</p>
            <span class="chip chip-cod-pending text-xs">{{ __('delivery.cod.status_pending') }}</span>
        </div>

        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="bg-slate-800 rounded-xl p-3">
                <p class="text-base font-bold text-slate-100">{{ codAmount($currentCodTotal, $currency) }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.cod.total_collected') }}</p>
            </div>
            <div class="bg-slate-800 rounded-xl p-3">
                <p class="text-base font-bold text-green-400">{{ codAmount($currentEarningsTotal, $currency) }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.cod.earned_fees') }}</p>
            </div>
            <div class="bg-slate-800 rounded-xl p-3">
                <p class="text-base font-bold {{ $currentNetOwed > 0 ? 'text-yellow-400' : 'text-slate-400' }}">
                    {{ codAmount($currentNetOwed, $currency) }}
                </p>
                <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.cod.net_owed') }}</p>
            </div>
        </div>

        @if($currentPeriodAssignments->isNotEmpty())
            <div class="border-t border-slate-700 pt-3">
                <p class="text-xs font-semibold text-slate-400 mb-2">{{ __('delivery.cod.deliveries_today') }}</p>
                <div class="space-y-2">
                    @foreach($currentPeriodAssignments as $a)
                        <div class="flex items-center justify-between text-sm bg-slate-800 rounded-lg px-3 py-2">
                            <div>
                                <p class="font-semibold">#{{ $a->subOrder?->sub_order_number ?? $a->id }}</p>
                                <p class="text-xs text-slate-400">{{ $a->delivered_at?->format('H:i') }}</p>
                            </div>
                            <p class="font-bold text-yellow-300">{{ codAmount($a->cod_amount_collected, $currency) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-center text-xs text-slate-500 py-2">{{ __('delivery.cod.no_cod_deliveries_today') }}</p>
        @endif
    </div>

    {{-- ── Settlements History ──────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.cod.settlements_history') }}</h2>

        @forelse($settlements as $settlement)
            @php
                $chip = $statusChipMap[$settlement->status->value] ?? ['class' => 'chip-cod-pending', 'label' => $settlement->status->label()];
            @endphp

            <div class="d-card mb-3" x-data="{ open: false }">

                {{-- Settlement header row --}}
                <button type="button"
                        class="w-full flex items-start justify-between gap-2 text-right"
                        @click="open = !open">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="chip {{ $chip['class'] }} text-xs">{{ $chip['label'] }}</span>
                            <span class="text-xs text-slate-400">
                                {{ $settlement->period_start->format('Y/m/d') }}
                                —
                                {{ $settlement->period_end->format('Y/m/d') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center mt-3">
                            <div>
                                <p class="text-sm font-bold text-slate-100">{{ codAmount($settlement->total_cod_collected, $currency) }}</p>
                                <p class="text-xs text-slate-500">{{ __('delivery.cod.total_collected') }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-green-400">{{ codAmount($settlement->total_earnings_owed, $currency) }}</p>
                                <p class="text-xs text-slate-500">{{ __('delivery.cod.earned_fees') }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold {{ $settlement->net_to_remit > 0 ? 'text-yellow-400' : 'text-slate-400' }}">
                                    {{ codAmount($settlement->net_to_remit, $currency) }}
                                </p>
                                <p class="text-xs text-slate-500">{{ __('delivery.cod.net_owed') }}</p>
                            </div>
                        </div>
                    </div>

                    <svg class="w-4 h-4 text-slate-500 flex-shrink-0 mt-1 transition-transform duration-200"
                         :class="{ 'rotate-180': open }"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>

                {{-- Delivery breakdown --}}
                <div x-show="open" x-collapse class="border-t border-slate-700 mt-3 pt-3 space-y-2">
                    <p class="text-xs font-semibold text-slate-400 mb-2">{{ __('delivery.cod.settlements_included') }}</p>

                    @forelse($settlement->assignments as $a)
                        <div class="flex items-center justify-between text-sm bg-slate-800 rounded-lg px-3 py-2">
                            <div>
                                <p class="font-semibold">#{{ $a->subOrder?->sub_order_number ?? $a->id }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ $a->delivered_at?->format('Y/m/d H:i') ?? '—' }}
                                </p>
                            </div>
                            <p class="font-bold text-yellow-300">
                                {{ codAmount($a->cod_amount_collected ?? 0, $currency) }}
                            </p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500 text-center py-2">{{ __('delivery.cod.no_linked_deliveries') }}</p>
                    @endforelse

                    @if($settlement->status === \App\Enums\DeliveryAgentCodSettlementStatus::Pending && $settlement->net_to_remit > 0)
                        <div class="mt-3 p-3 rounded-xl bg-yellow-900/30 border border-yellow-700/40 text-yellow-300 text-xs leading-relaxed">
                            {{ __('delivery.cod.please_remit') }}
                            <span class="font-bold">{{ codAmount($settlement->net_to_remit, $currency) }}</span>
                            {{ __('delivery.cod.to_supervisor') }}
                        </div>
                    @endif
                </div>

            </div>
        @empty
            <div class="d-card text-center py-10">
                <div class="text-4xl mb-3">📋</div>
                <p class="font-semibold text-slate-300">{{ __('delivery.cod.no_settlements_yet') }}</p>
                <p class="text-sm text-slate-500 mt-1">{{ __('delivery.cod.settlements_will_appear_here') }}</p>
            </div>
        @endforelse

        {{ $settlements->links() }}
    </div>

</div>

@endsection
