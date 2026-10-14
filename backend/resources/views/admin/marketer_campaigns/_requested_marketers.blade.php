{{-- Marketers the vendor picked who have no invitation yet (invitations are created when the campaign is approved). --}}
@php
    $awaiting = $requestedMarketers->reject(fn ($m) => $marketerCampaign->invitations->contains('marketer_id', $m->id));
    $currency = $requestedQuote['currency'] ?? $marketerCampaign->currency;
@endphp

@if($awaiting->isNotEmpty())
    <div class="mb-4" data-testid="requested-marketers">
        <div class="mb-3 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-sm text-yellow-800">
            {{ __('admin.marketer_campaigns.requested_marketers_notice', ['count' => $awaiting->count()]) }}
        </div>

        @if($requestedQuote)
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-3 text-sm">
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.quote_selected') }}</div>
                    <div class="font-semibold text-gray-800">
                        {{ $requestedQuote['influencer_count'] }} {{ __('admin.marketer_campaigns.influencer_label') }}
                        · {{ $requestedQuote['affiliate_count'] }} {{ __('admin.marketer_campaigns.affiliate_label') }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.quote_total_fee') }}</div>
                    <div class="font-semibold text-gray-800">{{ \App\Support\Money::format($requestedQuote['total_fee_with_vat']) }} {{ $currency }}</div>
                    @if($requestedQuote['total_fee_vat'] > 0)
                        <div class="text-[11px] text-gray-500">{{ __('admin.marketer_campaigns.quote_incl_vat', ['vat' => \App\Support\Money::format($requestedQuote['total_fee_vat'])]) }}</div>
                    @endif
                </div>
                <div class="rounded-lg border border-gray-200 p-3" data-testid="requested-grand-total">
                    <div class="text-xs text-gray-500 mb-1">{{ __('marketing_fees.campaign.grand_total') }}</div>
                    <div class="font-semibold text-gray-800">{{ \App\Support\Money::format($marketerCampaign->campaign_grand_total ?? $requestedQuote['campaign_grand_total']) }} {{ $currency }}</div>
                    <div class="text-[11px] text-gray-500">
                        {{ __('marketing_fees.campaign.marketing_fees') }}: {{ \App\Support\Money::format($marketerCampaign->marketing_fee_total ?? $requestedQuote['marketing']['total']) }}
                        · {{ ($marketerCampaign->fulfillment_type ?? \App\Enums\MarketingFeeType::from($requestedQuote['fulfillment_type']))->label() }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.quote_samples') }}</div>
                    <div class="font-semibold text-gray-800">{{ $requestedQuote['sample_total'] }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.quote_stock') }}</div>
                    <div class="font-semibold {{ $requestedQuote['stock_available'] < $requestedQuote['stock_min'] ? 'text-red-600' : 'text-gray-800' }}">
                        {{ $requestedQuote['stock_available'] }} / {{ $requestedQuote['stock_min'] }}
                    </div>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_marketer') }}</th>
                        <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.requested_status') }}</th>
                        <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.platform_fees') }}</th>
                        <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.commission_estimate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($awaiting as $m)
                        @php
                            $row = $quoteRows[$m->id] ?? null;
                            $isInfluencer = $m->marketerJobs->contains('key', 'influencer');
                        @endphp
                        <tr class="border-b border-gray-50">
                            <td class="py-2 pr-4">
                                <span class="font-medium text-gray-800">{{ $m->name }}</span>
                                <span class="text-xs ml-2 px-2 py-0.5 rounded-full {{ $isInfluencer ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $isInfluencer ? __('admin.marketer_campaigns.influencer_label') : __('admin.marketer_campaigns.affiliate_label') }}
                                </span>
                            </td>
                            <td class="py-2 pr-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">{{ __('admin.marketer_campaigns.awaiting_approval') }}</span>
                            </td>
                            <td class="py-2 pr-4">
                                @if($row && $row['fee'] > 0)
                                    {{ \App\Support\Money::format($row['fee_total']) }} {{ $currency }}
                                    @if($row['fee_vat'] > 0)<span class="block text-[11px] text-gray-500">{{ __('admin.marketer_campaigns.quote_incl_vat', ['vat' => \App\Support\Money::format($row['fee_vat'])]) }}</span>@endif
                                @else
                                    <span class="text-green-600">{{ __('admin.marketer_campaigns.free_label') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">
                                @if($row)
                                    {{ \App\Support\Money::format($row['commission']['amount'] ?? 0) }} {{ $currency }}
                                    @if(! empty($row['commission']['rate']))<span class="block text-[11px] text-gray-500">{{ rtrim(rtrim(number_format((float) $row['commission']['rate'], 2), '0'), '.') }}%</span>@endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
