@props(['campaign', 'audience' => 'vendor'])

@php
    use App\Support\Money;

    $ccy = $campaign->currency;
    $budget = (float) $campaign->max_commission_budget;
    $spent = (float) $campaign->commission_budget_spent;
    $reserved = (float) $campaign->influencer_fixed_reserved;
    $used = $campaign->budgetUsed();
    $remaining = $campaign->budgetRemaining();
    $usedPct = $budget > 0 ? min(100, (int) round($used / $budget * 100)) : 0;
    $feeStatus = $campaign->campaign_fee_status ?: 'not_applicable';
    $feeColors = ['paid' => 'bg-green-100 text-green-700', 'pending' => 'bg-orange-100 text-orange-700', 'voided' => 'bg-gray-100 text-gray-600', 'not_applicable' => 'bg-gray-100 text-gray-500'];
@endphp

{{-- Money of the campaign, straight from the figures saved on it (fee snapshot taken at creation, never recomputed). --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-orange-200 bg-white shadow-sm overflow-hidden']) }} data-testid="campaign-cost-panel">
    <div class="px-5 py-4 bg-orange-50/60 border-b border-orange-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">{{ __('marketer_campaigns.cost.title') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('marketer_campaigns.cost.subtitle') }}</p>
        </div>
        @if($campaign->fulfillment_type)
            <span class="px-2 py-0.5 rounded-full text-xs bg-white border border-orange-200 text-gray-700">
                {{ __('marketing_fees.campaign.fulfillment_type') }}: <strong>{{ $campaign->fulfillment_type->label() }}</strong>
            </span>
        @endif
    </div>

    <dl class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x rtl:md:divide-x-reverse divide-gray-100 text-sm">
        {{-- Budget side: what the vendor commits to pay marketers --}}
        <div class="p-5 space-y-4">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="font-medium text-gray-800">{{ __('marketer_campaigns.cost.budget') }}</dt>
                    <dd class="font-semibold text-gray-900" data-testid="cost-budget">{{ $campaign->budgetLabel() }}</dd>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">{{ __('marketer_campaigns.cost.budget_help') }}</p>
                @if($budget > 0)
                    <div class="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden flex" title="{{ $usedPct }}%">
                        <div class="h-full bg-purple-500" style="width: {{ min(100, round($reserved / $budget * 100)) }}%"></div>
                        <div class="h-full bg-blue-500" style="width: {{ min(100, round($spent / $budget * 100)) }}%"></div>
                    </div>
                    <div class="mt-1 flex flex-wrap gap-x-4 text-[11px] text-gray-500">
                        <span><span class="inline-block w-2 h-2 rounded-full bg-purple-500 me-1"></span>{{ __('marketer_campaigns.cost.legend_influencers') }}</span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-blue-500 me-1"></span>{{ __('marketer_campaigns.cost.legend_commissions') }}</span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-gray-200 me-1"></span>{{ __('marketer_campaigns.cost.legend_remaining') }}</span>
                    </div>
                @endif
            </div>

            <div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-gray-700">{{ __('marketer_campaigns.cost.influencers_fixed') }}</dt>
                    <dd class="font-semibold text-purple-700" data-testid="cost-reserved">{{ Money::format($reserved).' '.$ccy }}</dd>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">{{ __('marketer_campaigns.cost.influencers_fixed_help') }}</p>
            </div>

            <div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-gray-700">{{ __('marketer_campaigns.cost.commissions_spent') }}</dt>
                    <dd class="font-semibold text-blue-700" data-testid="cost-spent">{{ Money::format($spent).' '.$ccy }}</dd>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">{{ __('marketer_campaigns.cost.commissions_spent_help') }}</p>
            </div>

            <div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="font-medium text-gray-800">{{ __('marketer_campaigns.cost.remaining') }}</dt>
                    <dd class="font-semibold text-gray-900" data-testid="cost-remaining">{{ $remaining === null ? __('marketer_campaigns.cost.unlimited') : Money::format(max(0, $remaining)).' '.$ccy }}</dd>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">{{ __('marketer_campaigns.cost.remaining_help') }}</p>
            </div>
        </div>

        {{-- Fee side: what the campaign costs on top --}}
        <div class="p-5 space-y-4">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="font-medium text-gray-800">
                        {{ __('marketer_campaigns.cost.campaign_fee') }}
                        <span class="ms-1 px-2 py-0.5 rounded-full text-[11px] {{ $feeColors[$feeStatus] ?? $feeColors['not_applicable'] }}">{{ __('marketer_campaigns.cost.fee_status.'.$feeStatus) }}</span>
                    </dt>
                    <dd class="font-semibold text-gray-900" data-testid="cost-fee-total">{{ Money::format($campaign->marketing_fee_total ?? 0).' '.$ccy }}</dd>
                </div>
                <div class="mt-1 ps-3 space-y-0.5 text-xs text-gray-500">
                    <div class="flex justify-between"><span>{{ __('marketer_campaigns.summary.fixed_fee') }}</span><span data-testid="cost-fee-fixed">{{ Money::format($campaign->marketing_fee_fixed ?? 0).' '.$ccy }}</span></div>
                    <div class="flex justify-between"><span>{{ __('marketer_campaigns.summary.variable_fee') }}</span><span data-testid="cost-fee-variable">{{ Money::format($campaign->marketing_fee_variable ?? 0).' '.$ccy }}</span></div>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">{{ __('marketer_campaigns.cost.campaign_fee_help') }}</p>
                @if($feeStatus === 'pending' && $audience === 'vendor')
                    <p class="mt-1 text-[11px] text-orange-600">{{ __('marketer_campaigns.cost.fee_pending_note') }}</p>
                @elseif($feeStatus === 'paid' && $campaign->campaign_fee_paid_at)
                    <p class="mt-1 text-[11px] text-green-600">{{ __('marketer_campaigns.cost.fee_paid_note', ['date' => $campaign->campaign_fee_paid_at->format('Y/m/d'), 'via' => __('marketer_campaigns.cost.paid_via.'.($campaign->campaign_fee_paid_via ?: 'wallet'))]) }}</p>
                @endif
            </div>

            <div class="rounded-lg bg-orange-50/50 border border-orange-100 p-3">
                <div class="flex items-center justify-between gap-2">
                    <dt class="font-bold text-gray-900">{{ __('marketer_campaigns.cost.grand_total') }}</dt>
                    <dd class="font-bold text-orange-700" data-testid="cost-grand-total">{{ Money::format($campaign->campaign_grand_total ?? ($budget + (float) $campaign->marketing_fee_total)).' '.$ccy }}</dd>
                </div>
                <p class="mt-1 text-[11px] text-gray-500">{{ __('marketer_campaigns.cost.grand_total_help') }}</p>
            </div>

            @if($campaign->ends_at)
                <div class="flex items-center justify-between gap-2 text-xs text-gray-600">
                    <span>{{ __('marketer_campaigns.cost.ends_at') }}</span>
                    <span class="font-medium">{{ $campaign->ends_at->format('Y/m/d') }}</span>
                </div>
            @endif
        </div>
    </dl>
</div>
