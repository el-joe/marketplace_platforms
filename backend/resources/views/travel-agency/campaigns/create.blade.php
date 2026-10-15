@extends('layouts.travel-agency')

@section('title', __('travel.campaigns.new_campaign'))
@section('page-title', __('travel.campaigns.title'))

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('travel-agency.campaigns.create') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('travel.campaigns.choose_trip_title') }}</a>
        <h1 class="mt-2 text-2xl font-black text-gray-900">{{ __('travel.campaigns.create_title') }}</h1>
        <p class="text-sm text-gray-500">{{ __('travel.campaigns.create_subtitle') }}</p>
    </div>

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        <div class="font-semibold">{{ __('travel.campaigns.how_it_works_title') }}</div>
        <p class="mt-1 text-xs">{{ __('travel.campaigns.how_it_works_body') }}</p>
    </div>

    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <x-marketer-campaign-wizard
        vertical="travel"
        :subject="$subject"
        :influencers="$influencers"
        :affiliate-count="$affiliateCount"
        :quote="$quote"
        :action="route('travel-agency.campaigns.store')"
        :quote-url="route('travel-agency.campaigns.quote')"
        :quote-params="['trip_id' => $subject->model->id]"
        :item-inputs="['travel_package_id' => $subject->model->id]"
        :cancel-url="route('travel-agency.campaigns.index')"
        :wallet-url="route('travel-agency.finance.wallet')"
    />
</div>
@endsection
