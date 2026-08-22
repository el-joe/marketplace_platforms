@extends('layouts.delivery')

@section('title', __('delivery.earnings.title'))

@section('content')

    @php
        /** @var \App\Models\DeliveryAgent $agent */
        $pendingCents = $earnings->where('status', \App\Enums\DeliveryAgentEarningStatus::Pending)->sum('amount');
        $paidCents = $earnings->where('status', \App\Enums\DeliveryAgentEarningStatus::Paid)->sum('amount');
        $earningTypeColors = [
            'base_fee' => 'chip-accepted',
            'cod_handling' => 'chip-picked_up',
            'bonus' => 'chip-delivered',
            'tip' => 'chip-delivered',
            'deduction' => 'chip-failed',
        ];
    @endphp

    {{-- ── Cash in Hand (COD Liability) ───────────────────────────────────────── --}}
    @if($cashInHandCents > 0)
        <div class="d-card mb-4 border border-red-500/50 bg-red-500/5">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center shrink-0">
                    <span class="text-red-400 text-lg">💵</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs font-bold text-red-400 uppercase tracking-wider mb-0.5">{{ __('delivery.cod.cash_in_custody') }}</p>
                    <p class="text-2xl font-extrabold text-red-300">{{ number_format($cashInHandCents / 100, 2) }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ __('delivery.cod.must_remit') }} — {{ __('delivery.cod.must_remit_hint') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Balance Cards ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 mb-5">
        <div class="d-card text-center py-5 border border-yellow-500/30">
            <p class="text-xs font-bold text-yellow-400 uppercase tracking-wider mb-1">{{ __('delivery.earnings.pending') }}</p>
            <p class="text-2xl font-extrabold text-yellow-300">{{ number_format($pendingCents / 100, 2) }}</p>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.earnings.awaiting_approval') }}</p>
        </div>
        <div class="d-card text-center py-5 border border-green-500/30">
            <p class="text-xs font-bold text-green-400 uppercase tracking-wider mb-1">{{ __('delivery.earnings.paid') }}</p>
            <p class="text-2xl font-extrabold text-green-300">{{ number_format($paidCents / 100, 2) }}</p>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.earnings.total_received') }}</p>
        </div>
    </div>

    {{-- ── Recent Earnings ──────────────────────────────────────────────────────── --}}
    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.earnings.earning_history') }}</h2>

    @if($earnings->isNotEmpty())
        <div class="space-y-2 mb-5">
            @foreach($earnings as $earning)
                @php
                    $chipClass = $earningTypeColors[$earning->earning_type] ?? 'chip-assigned';
                @endphp
                <div class="d-card flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="chip {{ $chipClass }}">{{ ucfirst(str_replace('_', ' ', $earning->earning_type)) }}</span>
                        </div>
                        @if($earning->deliveryAssignment?->subOrder?->sub_order_number)
                            <p class="text-xs text-slate-400">#{{ $earning->deliveryAssignment->subOrder->sub_order_number }}</p>
                        @endif
                        <p class="text-xs text-slate-500">{{ $earning->created_at->format('d M Y') }}</p>
                    </div>
                    <div class="text-right ml-3 flex-shrink-0">
                        <p class="font-bold {{ $earning->earning_type === 'deduction' ? 'text-red-400' : 'text-green-400' }}">
                            {{ $earning->earning_type === 'deduction' ? '-' : '+' }}{{ number_format($earning->amount, 2) }}
                        </p>
                        <span
                            class="chip {{ $earning->status === \App\Enums\DeliveryAgentEarningStatus::Paid ? 'chip-delivered' : ($earning->status === \App\Enums\DeliveryAgentEarningStatus::Cancelled ? 'chip-failed' : 'chip-assigned') }} text-xs">
                            {{ $earning->status->label() }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($earnings->hasPages())
            <div class="flex justify-center gap-4 text-sm mb-6">
                @if($earnings->onFirstPage())
                    <span class="text-slate-600">{{ __('delivery.earnings.previous') }}</span>
                @else
                    <a href="{{ $earnings->previousPageUrl() }}" class="text-yellow-400">{{ __('delivery.earnings.previous') }}</a>
                @endif
                <span class="text-slate-400">{{ __('delivery.earnings.page') }} {{ $earnings->currentPage() }}</span>
                @if($earnings->hasMorePages())
                    <a href="{{ $earnings->nextPageUrl() }}" class="text-yellow-400">{{ __('delivery.earnings.next') }}</a>
                @else
                    <span class="text-slate-600">{{ __('delivery.earnings.next') }}</span>
                @endif
            </div>
        @endif
    @else
        <div class="d-card text-center py-12 mb-4">
            <div class="text-4xl mb-2">💸</div>
            <p class="font-semibold text-slate-300">{{ __('delivery.earnings.no_earnings_yet') }}</p>
            <p class="text-sm text-slate-500 mt-1">{{ __('delivery.earnings.complete_deliveries_to_earn') }}</p>
        </div>
    @endif

    {{-- ── Payout History ───────────────────────────────────────────────────────── --}}
    @if($payouts->isNotEmpty())
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.earnings.payout_history') }}</h2>
        <div class="space-y-2 mb-6">
            @foreach($payouts as $payout)
                @php
                    $payoutChip = match ($payout->status) {
                        \App\Enums\DeliveryAgentPayoutStatus::Paid => 'chip-delivered',
                        \App\Enums\DeliveryAgentPayoutStatus::Approved => 'chip-accepted',
                        \App\Enums\DeliveryAgentPayoutStatus::Failed => 'chip-failed',
                        default => 'chip-assigned',
                    };
                @endphp
                <div class="d-card flex items-center justify-between">
                    <div>
                        <p class="text-xs font-mono text-slate-400">{{ $payout->payout_number }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $payout->period_start }} – {{ $payout->period_end }}
                            · {{ $payout->total_deliveries }} {{ __('delivery.earnings.deliveries') }}
                        </p>
                    </div>
                    <div class="text-right ml-3 flex-shrink-0">
                        <p class="font-bold text-white">{{ number_format($payout->net_amount, 2) }}</p>
                        <span class="chip {{ $payoutChip }}">{{ $payout->status->label() }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@endsection
