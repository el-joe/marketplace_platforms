@extends('layouts.partner')

@section('title', __('partner.marketer_campaigns.create_title'))
@section('page-title', __('partner.marketer_campaigns.create_title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_campaigns.create_title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_campaigns.create_subtitle') }}</p>
    </div>

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        {{ __('partner.marketer_campaigns.auto_replace_notice', ['hours' => (int) setting('marketer_invitation_timeout_hours', 12)]) }}
    </div>

    @if($vendorListing->fulfillment_model !== \App\Enums\FulfillmentModel::Fbn)
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
            {{ __('partner.marketer_campaigns.ship_to_warehouse_notice') }} {{ __('partner.marketer_campaigns.ship_to_warehouse_notice_extra') }}
        </div>
    @endif

    <div class="rounded-lg border px-4 py-3 text-sm {{ $stockAfterFixedSample < $minStock ? 'border-red-300 bg-red-50 text-red-800' : 'border-gray-200 bg-gray-50 text-gray-700' }}" data-testid="stock-requirement">
        {{ __('partner.marketer_campaigns.stock_requirement', ['min' => $minStock, 'available' => $availableStock]) }}
        {{ __('partner.marketer_campaigns.stock_after_fixed_sample', ['fixed' => $quote['platform_sample_qty'], 'after' => $stockAfterFixedSample]) }}
        @if($stockAfterFixedSample < $minStock)
            <strong>{{ __('partner.marketer_campaigns.stock_blocked') }}</strong>
        @endif
    </div>

    @if(session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php $subject = \App\Support\Marketer\CampaignSubject::of($vendorListing); @endphp

    <x-marketer-campaign-wizard
        vertical="product"
        :subject="$subject"
        :influencers="$influencers"
        :affiliate-count="$affiliateCount"
        :quote="$quote"
        :action="route('partner.marketer-campaigns.store')"
        :quote-url="route('partner.marketer-campaigns.quote')"
        :quote-params="['listing_id' => $vendorListing->id]"
        :item-inputs="['vendor_listing_id' => $vendorListing->id]"
        :cancel-url="route('partner.marketer-campaigns.index')"
        :wallet-url="route('partner.wallet.index')"
    >
        <x-slot:openExtra>
            <span class="block text-xs mt-1">{{ __('partner.marketer_campaigns.affiliate_sample_qty_line', ['qty' => $quote['affiliate_sample_qty']]) }}</span>
        </x-slot:openExtra>

        {{-- Samples: 1 fixed admin sample + 1 per selected influencer (+ affiliate qty). Product campaigns only. --}}
        <x-slot:samples>
            <div class="p-3 rounded-lg text-sm" :class="stockBlocked ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-purple-50 text-purple-800'" data-testid="sample-plan">
                <i class="fas fa-box-open mr-1"></i>
                <strong>{{ __('partner.marketer_campaigns.expected_samples_label') }}</strong>
                <span x-text="sampleTotal"></span>
                <span class="block text-xs mt-1 text-gray-600">
                    {{ __('partner.marketer_campaigns.sample_breakdown_admin') }}: <span x-text="quote.platform_sample_qty"></span>
                    &middot; {{ __('partner.marketer_campaigns.sample_breakdown_influencers') }}:
                    <span x-text="quote.influencer_count"></span> &times; <span x-text="quote.influencer_sample_qty"></span>
                    <template x-if="openAffiliates && quote.affiliate_sample_qty > 0">
                        <span>&middot; {{ __('partner.marketer_campaigns.sample_breakdown_affiliates') }}: <span x-text="quote.affiliate_sample_qty"></span></span>
                    </template>
                </span>
                <span class="block text-xs mt-1">
                    {{ __('partner.marketer_campaigns.stock_left_after_samples') }}: <strong x-text="stockLeft"></strong>
                    / {{ __('partner.marketer_campaigns.stock_min_short') }} <strong x-text="quote.stock_min"></strong>
                </span>
                <span class="block text-xs mt-1 text-gray-500">
                    {{ __('partner.marketer_campaigns.samples_auto_note') }}
                </span>
            </div>
        </x-slot:samples>
    </x-marketer-campaign-wizard>

</div>

@endsection
