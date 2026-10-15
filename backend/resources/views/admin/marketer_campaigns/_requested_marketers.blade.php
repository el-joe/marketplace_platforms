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
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3 text-sm">
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.quote_selected') }}</div>
                    <div class="font-semibold text-gray-800">
                        {{ $requestedQuote['influencer_count'] }} {{ __('admin.marketer_campaigns.influencer_label') }}
                        @if($requestedQuote['open_to_affiliates'])
                            · {{ __('marketer_campaigns.wizard.audience_open') }}
                        @endif
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('marketer_campaigns.summary.influencers_fixed') }}</div>
                    <div class="font-semibold text-purple-700">{{ \App\Support\Money::format($requestedQuote['influencer_fixed_total']) }} {{ $currency }}</div>
                    <div class="text-[11px] text-gray-500">{{ __('marketer_campaigns.summary.remaining') }}: {{ \App\Support\Money::format($requestedQuote['remaining_budget']) }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3" data-testid="requested-grand-total">
                    <div class="text-xs text-gray-500 mb-1">{{ __('marketer_campaigns.summary.grand_total') }}</div>
                    <div class="font-semibold text-gray-800">{{ \App\Support\Money::format($marketerCampaign->campaign_grand_total ?? $requestedQuote['campaign_grand_total']) }} {{ $currency }}</div>
                    <div class="text-[11px] text-gray-500">
                        {{ __('marketer_campaigns.summary.campaign_fee') }}: {{ \App\Support\Money::format($marketerCampaign->marketing_fee_total ?? $requestedQuote['marketing']['total']) }}
                        · {{ ($marketerCampaign->fulfillment_type ?? \App\Enums\MarketingFeeType::from($requestedQuote['fulfillment_type']))->label() }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.quote_stock') }}</div>
                    <div class="font-semibold {{ $requestedQuote['stock_available'] < $requestedQuote['stock_min'] ? 'text-red-600' : 'text-gray-800' }}">
                        {{ $requestedQuote['stock_available'] }} / {{ $requestedQuote['stock_min'] }}
                    </div>
                    <div class="text-[11px] text-gray-500">{{ __('admin.marketer_campaigns.quote_samples') }}: {{ $requestedQuote['sample_total'] }}</div>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_marketer') }}</th>
                        <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.requested_status') }}</th>
                        <th class="py-2 pr-4">{{ __('marketer_campaigns.wizard.fixed_col') }}</th>
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
                                @if($row && $row['fixed'] > 0)
                                    {{ \App\Support\Money::format($row['fixed']) }} {{ $currency }}
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
