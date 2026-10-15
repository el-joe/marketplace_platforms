@extends('layouts.travel-agency')

@section('title', __('travel.campaigns.new_campaign'))
@section('page-title', __('travel.campaigns.title'))

@section('content')
<div class="space-y-5">
    <div>
        <a href="{{ route('travel-agency.campaigns.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('travel.campaigns.back') }}</a>
        <h1 class="mt-2 text-2xl font-black text-gray-900">{{ __('travel.campaigns.choose_trip_title') }}</h1>
        <p class="text-sm text-gray-500">{{ __('travel.campaigns.choose_trip_help') }}</p>
    </div>

    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800" data-testid="trip-commission-explainer">
        <div class="font-semibold">{{ __('travel.campaigns.how_it_works_title') }}</div>
        <p class="mt-1 text-xs">{{ __('travel.campaigns.how_it_works_body') }}</p>
    </div>

    @if ($trips->isEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">{{ __('travel.campaigns.no_eligible_trips') }}</div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($trips as $trip)
                @php $left = $trip->seatsRemaining(); @endphp
                <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="font-semibold text-gray-900">{{ $trip->title_ar ?: $trip->title_en }}</div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ $trip->currency }} {{ \App\Support\Money::format($trip->price) }}
                        @if ($trip->departure_date) &middot; {{ __('travel.campaigns.departure', ['date' => $trip->departure_date->format('Y/m/d')]) }} @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-400">{{ $left === null ? __('travel.campaigns.unlimited_seats') : __('travel.campaigns.seats_left', ['count' => $left]) }}</div>
                    <a href="{{ route('travel-agency.campaigns.create-trip', $trip->id) }}"
                       class="mt-4 inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                        {{ __('travel.campaigns.create_for_trip') }}
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
