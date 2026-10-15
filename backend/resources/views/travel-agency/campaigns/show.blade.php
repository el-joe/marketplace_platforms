@extends('layouts.travel-agency')

@php
    $trip = $campaign->travelPackage;
    $tripTitle = $trip?->title_ar ?: $trip?->title_en;
    $heading = $campaign->title ?: __('travel.campaigns.untitled', ['trip' => $tripTitle]);
    $user = auth()->guard('travel_agency')->user();
    $canManage = $user?->can('campaigns.manage');
    $live = in_array($campaign->status, ['active', 'auto_approved'], true);
    $canResume = $campaign->status === 'paused' && $campaign->owner_paused_at;
    $canCancel = ! in_array($campaign->status, \App\Services\MarketerCampaignService::TERMINAL_STATUSES, true);
    $statusClass = match ($campaign->status) {
        'active' => 'bg-green-100 text-green-700',
        'auto_approved' => 'bg-blue-100 text-blue-700',
        'pending_admin' => 'bg-yellow-100 text-yellow-700',
        'rejected' => 'bg-red-100 text-red-700',
        'paused' => 'bg-orange-100 text-orange-700',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp

@section('title', $heading)
@section('page-title', __('travel.campaigns.title'))

@section('content')
<div class="space-y-6" x-data="{ tab: 'info' }">
    <a href="{{ route('travel-agency.campaigns.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('travel.campaigns.back') }}</a>

    @foreach (['success' => 'green', 'error' => 'red'] as $flash => $color)
        @if (session($flash))
            <div class="rounded-lg border border-{{ $color }}-200 bg-{{ $color }}-50 px-4 py-3 text-sm text-{{ $color }}-800">{{ session($flash) }}</div>
        @endif
    @endforeach

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-black text-gray-900">{{ $heading }}</h1>
            <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusClass }}">{{ __('partner.marketer_campaigns_my.status.'.$campaign->status) }}</span>
        </div>
        @if ($canManage)
            <div class="flex flex-wrap gap-2">
                @if ($live)
                    <form method="POST" action="{{ route('travel-agency.campaigns.pause', $campaign->id) }}" onsubmit="return confirm(@js(__('travel.campaigns.actions.confirm_pause')))">
                        @csrf
                        <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('travel.campaigns.actions.pause') }}</button>
                    </form>
                @endif
                @if ($canResume)
                    <form method="POST" action="{{ route('travel-agency.campaigns.resume', $campaign->id) }}" onsubmit="return confirm(@js(__('travel.campaigns.actions.confirm_resume')))">
                        @csrf
                        <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('travel.campaigns.actions.resume') }}</button>
                    </form>
                @endif
                @if ($canCancel)
                    <form method="POST" action="{{ route('travel-agency.campaigns.cancel', $campaign->id) }}" onsubmit="return confirm(@js(__('travel.campaigns.actions.confirm_cancel')))">
                        @csrf
                        <button class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('travel.campaigns.actions.cancel') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if ($live && $canManage)
        <p class="text-xs text-gray-500">{{ __('travel.campaigns.pause_hint') }}</p>
    @endif

    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-x-6 overflow-x-auto">
            @foreach (['info' => 'tab_info', 'marketers' => 'tab_marketers', 'conversions' => 'tab_conversions'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium">{{ __('partner.marketer_campaigns_my.'.$label) }}</button>
            @endforeach
        </nav>
    </div>

    <div x-show="tab === 'info'" x-cloak class="space-y-6">
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800">
            <div class="font-semibold">{{ __('travel.campaigns.how_it_works_title') }}</div>
            <p class="mt-1">{{ __('travel.campaigns.how_it_works_body') }}</p>
        </div>
        <x-marketer-campaign-info :campaign="$campaign" :calculations="$calculations" :price="$examplePrice" />
    </div>

    <div x-show="tab === 'marketers'" x-cloak>
        <x-marketer-campaign-marketers :campaign="$campaign" :calculations="$calculations" />
    </div>

    <div x-show="tab === 'conversions'" x-cloak>
        <x-marketer-campaign-conversions :campaign="$campaign" />
    </div>
</div>
@endsection
