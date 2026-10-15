@extends('layouts.marketer')
@php
    $campaign = $invitation->campaign;
    $product = $campaign->vendorListing?->productVariant?->product ?? $campaign->adminListing?->productVariant?->product;
    $title = $product?->name_ar ?? $campaign->getPromotedTitle();
@endphp
@section('title', $title)
@section('page-title', $title)

@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        <div class="text-sm text-gray-500">{{ $campaign->vendor->name ?? 'ناوي' }} • {{ $campaign->country->name_ar ?? '' }}</div>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-gray-900">{{ number_format($invitation->conversions_count ?? 0) }}</div>
                <div class="text-xs text-gray-500">{{ __('marketer.campaigns.conversions') }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-green-600">{{ \App\Support\Money::format($invitation->conversions_sum_commission_amount ?? 0) }}</div>
                <div class="text-xs text-gray-500">{{ __('marketer.campaigns.earned_commission') }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-yellow-600">{{ ($invitation->influencer_fixed_status ?? 'none') !== 'none' ? \App\Support\Money::format($invitation->influencer_fixed_amount) : __('marketer_campaigns.marketer_panel.rate_short') }}</div>
                <div class="text-xs text-gray-500">{{ __('marketer_campaigns.marketer_panel.terms_label') }}</div>
            </div>
        </div>
    </div>

    <x-marketer-campaign-commission-preview :invitation="$invitation" />

    {{-- Your earnings on this campaign (phase F redesigns the marketer finance screens) --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm space-y-2" data-testid="marketer-campaign-earnings">
        <div class="font-semibold text-gray-800">{{ __('marketer_campaigns.marketer_panel.title') }}</div>
        @if($invitation->influencer_fixed_status !== \App\Models\MarketerCampaignInvitation::FIXED_NONE)
            <div class="text-gray-700">{{ __('marketer_campaigns.marketer_panel.influencer_line', ['amount' => \App\Support\Money::format($invitation->influencer_fixed_amount).' '.$campaign->currency]) }}</div>
            <div class="text-xs text-gray-500">{{ __('marketer_campaigns.calc.fixed_status.'.$invitation->influencer_fixed_status) }}</div>
            @if($invitation->influencer_fixed_status === \App\Models\MarketerCampaignInvitation::FIXED_PAID)
                <div class="text-green-600">{{ __('marketer_campaigns.marketer_panel.influencer_paid', ['net' => \App\Support\Money::format($invitation->influencer_fixed_net_amount).' '.$campaign->currency, 'fee' => \App\Support\Money::format($invitation->influencer_fixed_fee_amount).' '.$campaign->currency]) }}</div>
            @endif
        @else
            <div class="text-gray-700">{{ __('marketer_campaigns.marketer_panel.sales_line', ['count' => (int) $invitation->total_conversions, 'amount' => \App\Support\Money::format($invitation->total_commission_earned).' '.$campaign->currency]) }}</div>
            <div class="text-xs text-gray-500">{{ __('marketer_campaigns.marketer_panel.sales_help') }}</div>
        @endif
    </div>

    <a href="{{ route('marketer.campaigns.active') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('marketer.campaigns.active_title') }}</a>
</div>
@endsection
