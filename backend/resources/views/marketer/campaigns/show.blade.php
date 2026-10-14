@extends('layouts.marketer')
@php
    $campaign = $invitation->campaign;
    $product = $campaign->vendorListing?->productVariant?->product ?? $campaign->adminListing?->productVariant?->product;
    $title = $product?->name_ar ?? $campaign->title;
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
                <div class="text-lg font-black text-yellow-600">{{ \App\Support\Money::format($campaign->marketer_commission_amount) }}</div>
                <div class="text-xs text-gray-500">{{ __('marketer.campaigns.commission_per_sale') }}</div>
            </div>
        </div>
    </div>

    @if($marketer->isAffiliate() && ! $marketer->isInfluencer())
        <x-affiliate-fee-history :campaign="$campaign" audience="marketer" :invitation-id="$invitation->id" />
    @endif

    <a href="{{ route('marketer.campaigns.active') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('marketer.campaigns.active_title') }}</a>
</div>
@endsection
