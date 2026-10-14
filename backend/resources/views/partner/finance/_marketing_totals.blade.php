{{-- P8: separate totals of the marketer-campaign charges. $marketingTotals = [currency => [influencer_fees, marketing_fees, affiliate_marketing_fees, refunds]] --}}
@php $marketingTotals = collect($marketingTotals ?? [])->filter(fn ($t) => collect($t)->only(['influencer_fees', 'marketing_fees', 'affiliate_marketing_fees', 'refunds'])->sum() > 0); @endphp
@if($marketingTotals->isNotEmpty())
    <div class="space-y-3 mb-6" data-marketing-totals>
        @foreach($marketingTotals as $ccy => $t)
            <div data-currency="{{ $ccy }}">
                @if(! empty($marketingTitle))
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ $marketingTitle }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></h3>
                @endif
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 mb-1">{{ __('marketing_fees.reports.vendor_influencer_fees') }}</p>
                        <p class="text-lg font-bold text-orange-600" data-total="influencer_fees">{{ \App\Support\Money::format($t['influencer_fees']) }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 mb-1">{{ __('marketing_fees.reports.vendor_marketing_fees') }}</p>
                        <p class="text-lg font-bold text-orange-600" data-total="marketing_fees">{{ \App\Support\Money::format($t['marketing_fees']) }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 mb-1">{{ __('marketing_fees.reports.vendor_affiliate_fees') }}</p>
                        <p class="text-lg font-bold text-orange-600" data-total="affiliate_marketing_fees">{{ \App\Support\Money::format($t['affiliate_marketing_fees']) }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 mb-1">{{ __('marketing_fees.reports.vendor_refunds') }}</p>
                        <p class="text-lg font-bold text-green-600" data-total="refunds">{{ \App\Support\Money::format($t['refunds']) }} <span class="text-xs font-normal text-gray-400">{{ $ccy }}</span></p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
