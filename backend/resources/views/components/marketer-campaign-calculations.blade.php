@props(['campaign', 'calculations', 'price' => 0])

@php
    use App\Services\Marketer\CommissionResult;
    use App\Services\Marketer\MarketerCommissionCalculator;
    use App\Support\Money;

    $ccy = $campaign->currency;
    $vertical = in_array($campaign->campaign_category, ['classified', 'travel'], true) ? $campaign->campaign_category : 'product';
    $sources = [
        MarketerCommissionCalculator::SOURCE_SNAPSHOT => 'snapshot',
        MarketerCommissionCalculator::SOURCE_PROFILE => 'profile',
        MarketerCommissionCalculator::SOURCE_DEFAULT => 'default',
        MarketerCommissionCalculator::SOURCE_NONE => 'none',
    ];
    $m = fn ($v) => Money::format($v).' '.$ccy;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

{{-- Explains, per marketer, how his commission is calculated, using the promoted item's current price as the example. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white shadow-sm']) }} data-testid="campaign-calculations">
    <div class="px-5 pt-4">
        <h3 class="text-sm font-semibold text-gray-800">{{ __('marketer_campaigns.calc.title') }}</h3>
        <p class="mt-1 text-xs text-gray-500">{{ __('marketer_campaigns.calc.intro', ['price' => $m($price)]) }}</p>
    </div>

    @if($calculations->isEmpty())
        <p class="p-5 text-sm text-gray-400">{{ __('marketer_campaigns.calc.empty') }}</p>
    @else
        <div class="p-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($campaign->invitations->whereIn('id', $calculations->keys()) as $invitation)
                @php /** @var CommissionResult $r */ $r = $calculations[$invitation->id]; @endphp
                <div class="rounded-lg border border-gray-200 p-4 text-sm" data-testid="calc-{{ $invitation->id }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-gray-900">{{ $invitation->marketer?->name }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $r->perCampaign ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">{{ __('marketer_campaigns.calc.type.'.$r->marketerType->value) }}</span>
                    </div>

                    <ol class="mt-3 space-y-2 text-xs text-gray-600">
                        {{-- 1. gross commission --}}
                        <li>
                            <div class="font-medium text-gray-800">1. {{ __('marketer_campaigns.calc.step_commission') }}</div>
                            @if($r->perCampaign)
                                <div>{{ __('marketer_campaigns.calc.influencer_formula', ['amount' => $m($r->gross)]) }}</div>
                                <div class="text-gray-400">{{ __('marketer_campaigns.calc.source.'.($sources[$r->ruleSource] ?? 'none')) }}</div>
                            @elseif($r->mode === CommissionResult::MODE_NONE)
                                <div class="text-amber-600">{{ __('marketer_campaigns.calc.no_rule') }}</div>
                            @elseif($r->mode === CommissionResult::MODE_PERCENTAGE)
                                <div>{{ __('marketer_campaigns.calc.percentage_formula', ['rate' => $pct($r->rate), 'basis' => $m($r->basis), 'gross' => $m($r->gross)]) }}</div>
                            @else
                                <div>{{ __('marketer_campaigns.calc.broker_formula', [
                                    'fixed' => $m($r->fixedAmount ?? 0), 'qty' => $r->quantity,
                                    'rate' => $pct($r->rate ?? 0), 'basis' => $m($r->basis), 'gross' => $m($r->gross),
                                ]) }}</div>
                            @endif
                            @unless($r->perCampaign || $r->mode === CommissionResult::MODE_NONE)
                                <div class="text-gray-400">{{ __('marketer_campaigns.vertical.'.$vertical.'.basis_note') }}</div>
                            @endunless
                        </li>

                        {{-- 2. platform commission fee --}}
                        <li>
                            <div class="font-medium text-gray-800">2. {{ __('marketer_campaigns.calc.step_fee') }}</div>
                            <div>{{ __('marketer_campaigns.calc.fee_formula', [
                                'pct' => $pct($r->feePercentage), 'fixed' => $m($r->feeFixed), 'gross' => $m($r->gross), 'fee' => $m($r->fee),
                            ]) }}</div>
                        </li>

                        {{-- 3. net --}}
                        <li>
                            <div class="font-medium text-gray-800">3. {{ __('marketer_campaigns.calc.step_net') }}</div>
                            <div class="font-semibold text-green-700">{{ __('marketer_campaigns.calc.net_formula', ['gross' => $m($r->gross), 'fee' => $m($r->fee), 'net' => $m($r->net)]) }}</div>
                        </li>
                    </ol>

                    <p class="mt-3 rounded bg-gray-50 px-3 py-2 text-[11px] text-gray-500">
                        @if($r->perCampaign)
                            {{ __('marketer_campaigns.calc.influencer_when', ['status' => __('marketer_campaigns.calc.fixed_status.'.($invitation->influencer_fixed_status ?: 'none'))]) }}
                        @else
                            {{ __('marketer_campaigns.vertical.'.$vertical.'.sale_when') }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</div>
