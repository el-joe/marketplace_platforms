@props(['campaign', 'audience' => 'vendor', 'invitationId' => null, 'settleRoute' => null])

@php
    use App\Models\MarketerCampaignFeeAccrual as Accrual;
    use App\Support\Money;

    $isMarketer = $audience === 'marketer';
    $rows = $campaign->feeAccruals()
        ->with('order:id,order_number')
        ->when($isMarketer, fn ($q) => $q->where('invitation_id', $invitationId))
        ->get();

    // The marketer only sees their own rows, so the running total is rebuilt from those rows.
    $cumulative = 0.0;
    $rows = $rows->map(function ($row) use ($isMarketer, &$cumulative) {
        $cumulative = round($cumulative + (float) $row->marketing_total_delta, 2);
        $row->display_running = $isMarketer ? $cumulative : (float) $row->running_marketing_total;

        return $row;
    });

    $totalCommission = round((float) $rows->sum(fn ($r) => (float) $r->commission_base), 2);
    $totalFees = round((float) $rows->sum(fn ($r) => (float) $r->marketing_total_delta), 2);
    $settledFees = round((float) $rows->whereNotNull('settlement_id')->sum(fn ($r) => (float) $r->marketing_total_delta), 2);
    $unsettledFees = round($totalFees - $settledFees, 2);
    $pendingStatement = $isMarketer ? collect() : $campaign->feeSettlements()->where('status', 'pending')->where('kind', 'charge')->get();
    // P7: refund-after-settlement credits to the vendor are history rows of their own (vendor and admin only).
    $refunds = $isMarketer ? collect() : $campaign->feeSettlements()->where('kind', 'refund')->orderBy('created_at')->get();

    $show = $rows->isNotEmpty() || $refunds->isNotEmpty() || $isMarketer || (! $isMarketer && $campaign->vendor_id && $campaign->invitations->contains(fn ($i) => $i->marketer?->isAffiliate()));
@endphp

@if($show)
<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm text-sm']) }} data-testid="affiliate-fee-history" data-audience="{{ $audience }}">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
        <h3 class="font-semibold text-gray-800">{{ __('marketing_fees.affiliate.history_title') }}</h3>
        @if(! $isMarketer)
            <span class="text-xs text-gray-500" data-testid="affiliate-settled-state">
                {{ $campaign->affiliate_fee_settled_at
                    ? __('marketing_fees.affiliate.settled_state', ['date' => $campaign->affiliate_fee_settled_at->format('Y/m/d H:i')])
                    : __('marketing_fees.affiliate.not_settled_state') }}
            </span>
        @endif
    </div>
    <p class="text-xs text-gray-500 mb-3">{{ __('marketing_fees.affiliate.history_hint_'.$audience) }}</p>

    @foreach($pendingStatement as $pending)
        <div class="mb-3 rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-800" data-testid="affiliate-pending-statement">
            {{ __('marketing_fees.affiliate.pending_statement', ['amount' => Money::format($pending->amount), 'currency' => $campaign->currency]) }}
        </div>
    @endforeach

    @if($rows->isEmpty() && $refunds->isEmpty())
        <p class="text-xs text-gray-500">{{ __('marketing_fees.affiliate.empty') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ __('marketing_fees.affiliate.order') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('marketing_fees.affiliate.date') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('marketing_fees.affiliate.qty') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('marketing_fees.affiliate.commission') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('marketing_fees.affiliate.fee_delta') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('marketing_fees.affiliate.running_total') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('marketing_fees.affiliate.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                        @php
                            $reversal = $row->entry_type === Accrual::ENTRY_REVERSAL;
                            $statusKey = $reversal ? 'reversed' : ($row->settlement_id ? 'settled' : 'accrued');
                        @endphp
                        <tr data-testid="affiliate-fee-row">
                            <td class="px-3 py-2 font-mono text-xs">{{ $row->order?->order_number ?? '#'.substr((string) $row->order_id, 0, 8) }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $row->created_at?->format('Y/m/d') }}</td>
                            <td class="px-3 py-2 text-end" dir="ltr">{{ $reversal ? '-' : '' }}{{ $row->quantity }}</td>
                            <td class="px-3 py-2 text-end" dir="ltr">{{ Money::format($row->commission_base) }}</td>
                            <td class="px-3 py-2 text-end {{ $reversal ? 'text-red-600' : '' }}" dir="ltr">{{ Money::format($row->marketing_total_delta) }}</td>
                            <td class="px-3 py-2 text-end font-medium" dir="ltr">{{ Money::format($row->display_running) }}</td>
                            <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $statusKey === 'settled' ? 'bg-green-100 text-green-700' : ($statusKey === 'reversed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">{{ __('marketing_fees.affiliate.status_'.$statusKey) }}</span></td>
                        </tr>
                    @endforeach
                    @foreach($refunds as $refund)
                        <tr data-testid="affiliate-refund-row" class="bg-green-50">
                            <td class="px-3 py-2 text-xs font-semibold text-green-800" colspan="2">{{ __('marketing_fees.affiliate.refund_row') }}</td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2 text-end text-green-700" dir="ltr">+{{ Money::format($refund->amount) }}</td>
                            <td class="px-3 py-2 text-end text-xs text-gray-500">{{ $refund->created_at?->format('Y/m/d') }}</td>
                            <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $refund->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ __('marketing_fees.affiliate.refund_status_'.($refund->status === 'paid' ? 'credited' : 'pending')) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                    <tr data-testid="affiliate-fee-totals">
                        <td class="px-3 py-2" colspan="3">{{ __('marketing_fees.affiliate.totals') }}</td>
                        <td class="px-3 py-2 text-end" dir="ltr">{{ Money::format($totalCommission) }}</td>
                        <td class="px-3 py-2 text-end" dir="ltr">{{ Money::format($totalFees) }}</td>
                        <td class="px-3 py-2 text-end" dir="ltr">{{ Money::format($totalFees) }} {{ $campaign->currency }}</td>
                        <td class="px-3 py-2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @unless($isMarketer)
            <dl class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div><dt class="text-xs text-gray-500">{{ __('marketing_fees.affiliate.total_commission') }}</dt><dd class="font-semibold" data-testid="affiliate-total-commission">{{ Money::format($totalCommission) }} {{ $campaign->currency }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('marketing_fees.affiliate.total_fees') }}</dt><dd class="font-semibold" data-testid="affiliate-total-fees">{{ Money::format($totalFees) }} {{ $campaign->currency }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('marketing_fees.affiliate.settled_amount') }}</dt><dd class="font-semibold text-green-700" data-testid="affiliate-settled-amount">{{ Money::format($settledFees) }} {{ $campaign->currency }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('marketing_fees.affiliate.unsettled_amount') }}</dt><dd class="font-semibold text-orange-700" data-testid="affiliate-unsettled-amount">{{ Money::format($unsettledFees) }} {{ $campaign->currency }}</dd></div>
            </dl>
        @endunless
    @endif

    @if($settleRoute)
        <form method="POST" action="{{ $settleRoute }}" class="mt-3" onsubmit="return confirm(@js(__('marketing_fees.affiliate.settle_confirm')))">
            @csrf
            <button type="submit" class="rounded-lg bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-orange-700" data-testid="affiliate-settle-now">{{ __('marketing_fees.affiliate.settle_now') }}</button>
        </form>
    @endif
</div>
@endif
