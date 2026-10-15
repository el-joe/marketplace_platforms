@extends('layouts.partner')

@section('title', __('partner.marketer_campaigns.create_title'))
@section('page-title', __('partner.marketer_campaigns.create_title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('marketer_campaigns.classified.create_title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('marketer_campaigns.classified.create_subtitle') }}</p>
    </div>

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        {{ __('partner.marketer_campaigns.auto_replace_notice', ['hours' => (int) setting('marketer_invitation_timeout_hours', 12)]) }}
    </div>

    {{-- How a classified sale reaches the marketer: the seller closes the sale himself, so it must be told to us. --}}
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" data-testid="classified-sold-notice">
        <div class="font-semibold">{{ __('marketer_campaigns.classified.sold_notice_title') }}</div>
        <p class="mt-1 text-xs">{{ __('marketer_campaigns.classified.sold_notice_body') }}</p>
    </div>

    @if(session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
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

    <x-marketer-campaign-wizard
        vertical="classified"
        :subject="$subject"
        :influencers="$influencers"
        :affiliate-count="$affiliateCount"
        :quote="$quote"
        :action="route('partner.marketer-campaigns.store')"
        :quote-url="route('partner.marketer-campaigns.quote')"
        :quote-params="['classified_id' => $classifiedListing->id]"
        :item-inputs="['classified_listing_id' => $classifiedListing->id]"
        :cancel-url="route('partner.classifieds.show', $classifiedListing->id)"
        :wallet-url="route('partner.wallet.index')"
    />

</div>
@endsection
