{{-- Totals of what marketer campaigns cost the owner (vendor, agency or classified vendor), per currency.
     $marketingTotals = [currency => [campaign_fee, campaign_fee_pending, campaign_budget_commission]] --}}
@php $marketingTotals = collect($marketingTotals ?? [])->filter(fn ($t) => collect($t)->only(['campaign_fee', 'campaign_fee_pending', 'campaign_budget_commission'])->sum() > 0); @endphp
@if($marketingTotals->isNotEmpty())
    <div class="space-y-3 mb-6" data-marketing-totals>
        @foreach($marketingTotals as $ccy => $t)
            <div data-currency="{{ $ccy }}">
                @if(! empty($marketingTitle))
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ $marketingTitle }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></h3>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 mb-1">{{ __('marketing_fees.reports.vendor_campaign_fee') }}</p>
                        <p class="text-lg font-bold text-orange-600" data-total="campaign_fee">{{ \App\Support\Money::format($t['campaign_fee'] ?? 0) }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></p>
                        @if(($t['campaign_fee_pending'] ?? 0) > 0)
                            <p class="text-xs text-amber-600 mt-1" data-total="campaign_fee_pending">{{ __('marketing_fees.reports.vendor_campaign_fee_pending', ['amount' => \App\Support\Money::format($t['campaign_fee_pending']), 'currency' => $ccy]) }}</p>
                        @endif
                        <p class="text-[11px] text-gray-400 mt-2">{{ __('marketing_fees.reports.vendor_campaign_fee_help') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 mb-1">{{ __('marketing_fees.reports.vendor_budget_commission') }}</p>
                        <p class="text-lg font-bold text-orange-600" data-total="campaign_budget_commission">{{ \App\Support\Money::format($t['campaign_budget_commission'] ?? 0) }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></p>
                        <p class="text-[11px] text-gray-400 mt-2">{{ __('marketing_fees.reports.vendor_budget_commission_help') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 mb-1">{{ __('marketing_fees.reports.vendor_total_charged') }}</p>
                        <p class="text-lg font-bold text-gray-900" data-total="total_charged">{{ \App\Support\Money::format(($t['campaign_fee'] ?? 0) + ($t['campaign_budget_commission'] ?? 0)) }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></p>
                        <p class="text-[11px] text-gray-400 mt-2">{{ __('marketing_fees.reports.vendor_total_charged_help') }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
