@props(['invitation', 'compact' => false])

@php
    use App\Services\Marketer\CommissionResult;
    use App\Services\Marketer\MarketerCommissionCalculator;
    use App\Support\Money;

    $campaign = $invitation->campaign;
    $marketer = $invitation->marketer;
    $vertical = in_array($campaign->campaign_category, ['classified', 'travel'], true) ? $campaign->campaign_category : 'product';
    $price = (float) ($campaign->getPromotedItem()?->price ?? 0);
    $ccy = $campaign->currency;
    $m = fn ($v) => Money::format($v).' '.$ccy;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    /** @var CommissionResult|null $r */
    $r = $marketer ? app(MarketerCommissionCalculator::class)->compute($marketer, $campaign, $price, 1, $invitation) : null;
@endphp

{{-- "Your commission" on a campaign, for the marketer who holds the invitation: the formula by his own type, worked on the
     promoted item's current price, then the platform commission fee and what he nets. Own figures only. --}}
@if ($r)
    <div class="rounded-lg border border-yellow-200 bg-yellow-50/60 p-3 text-xs text-gray-700 space-y-1" data-testid="commission-preview">
        <div class="flex items-center justify-between gap-2">
            <span class="font-semibold text-gray-900">{{ __('marketer_campaigns.preview.title') }}</span>
            <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-gray-600">{{ __('marketer_campaigns.calc.type.'.$r->marketerType->value) }}</span>
        </div>

        @if ($r->perCampaign)
            <div>{{ __('marketer_campaigns.calc.influencer_formula', ['amount' => $m($r->gross)]) }}</div>
        @elseif ($r->mode === CommissionResult::MODE_NONE)
            <div class="text-amber-700">{{ __('marketer_campaigns.calc.no_rule') }}</div>
        @elseif ($r->mode === CommissionResult::MODE_PERCENTAGE)
            <div>{{ __('marketer_campaigns.calc.percentage_formula', ['rate' => $pct($r->rate), 'basis' => $m($r->basis), 'gross' => $m($r->gross)]) }}</div>
        @else
            <div>{{ __('marketer_campaigns.calc.broker_formula', ['fixed' => $m($r->fixedAmount ?? 0), 'qty' => $r->quantity, 'rate' => $pct($r->rate ?? 0), 'basis' => $m($r->basis), 'gross' => $m($r->gross)]) }}</div>
        @endif

        @if ($r->mode !== CommissionResult::MODE_NONE || $r->perCampaign)
            <div>{{ __('marketer_campaigns.calc.fee_formula', ['pct' => $pct($r->feePercentage), 'fixed' => $m($r->feeFixed), 'gross' => $m($r->gross), 'fee' => $m($r->fee)]) }}</div>
            <div class="font-semibold text-green-700">{{ __('marketer_campaigns.calc.net_formula', ['gross' => $m($r->gross), 'fee' => $m($r->fee), 'net' => $m($r->net)]) }}</div>
        @endif

        @unless ($compact)
            <p class="text-[11px] text-gray-500">
                {{ $r->perCampaign ? __('marketer_campaigns.preview.influencer_when') : __('marketer_campaigns.vertical.'.$vertical.'.basis_note') }}
                {{ $r->perCampaign ? '' : __('marketer_campaigns.vertical.'.$vertical.'.sale_when') }}
            </p>
        @endunless
    </div>
@endif
