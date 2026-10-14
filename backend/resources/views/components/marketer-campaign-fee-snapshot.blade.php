@props(['campaign'])

{{-- Fee figures saved on the campaign at creation (server-side quote); never recomputed from current settings. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-orange-200 bg-orange-50/40 p-4 text-sm']) }} data-testid="campaign-fee-snapshot">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
        <h4 class="font-semibold text-gray-800">{{ __('marketing_fees.campaign.snapshot_title') }}</h4>
        @if($campaign->fulfillment_type)
            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">
                {{ __('marketing_fees.campaign.fulfillment_type') }}: <strong>{{ $campaign->fulfillment_type->label() }}</strong>
            </span>
        @endif
    </div>
    @if($campaign->campaign_grand_total === null)
        <p class="text-xs text-gray-500">{{ __('marketing_fees.campaign.snapshot_none') }}</p>
    @else
        <p class="text-xs text-gray-500 mb-2">{{ __('marketing_fees.campaign.snapshot_hint') }}</p>
        <dl class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @foreach([
                'subtotal' => $campaign->campaign_subtotal,
                'fixed_fee' => $campaign->marketing_fee_fixed,
                'variable_fee' => $campaign->marketing_fee_variable,
                'marketing_fees' => $campaign->marketing_fee_total,
                'grand_total' => $campaign->campaign_grand_total,
            ] as $key => $value)
                <div>
                    <dt class="text-xs text-gray-500">{{ __('marketing_fees.campaign.'.$key) }}</dt>
                    <dd class="font-semibold {{ $key === 'grand_total' ? 'text-orange-700' : 'text-gray-900' }}" data-testid="snapshot-{{ $key }}">{{ \App\Support\Money::format($value ?? 0) }} {{ $campaign->currency }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
