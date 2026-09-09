@extends('layouts.partner')

@section('title', __('partner.dashboard.title'))
@section('page-title', __('partner.dashboard.title'))

@section('content')

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <x-partner-stat-card title="{{ __('partner.nav.my_classifieds') }}" :value="$stats['total_listings']" icon="squares-plus" color="blue"
            :link="Route::has('partner.classifieds.index') ? route('partner.classifieds.index') : null" />

        <x-partner-stat-card title="{{ __('partner.dashboard_classified.active_listings') }}" :value="$stats['active_listings']" icon="check-circle" color="success" />

        <x-partner-stat-card title="{{ __('partner.dashboard_classified.pending_listings') }}" :value="$stats['pending_listings']" icon="clock"
            :color="$stats['pending_listings'] > 0 ? 'warning' : 'gray'" />

        <x-partner-stat-card title="{{ __('partner.dashboard_classified.paused_listings') }}" :value="$stats['paused_listings']" icon="exclamation-triangle" color="gray" />

        <x-partner-stat-card title="{{ __('partner.dashboard_classified.sold_listings') }}" :value="$stats['sold_listings']" icon="banknotes" color="success" />

        <x-partner-stat-card title="{{ __('partner.dashboard_classified.new_inquiries') }}" :value="$stats['new_inquiries']" icon="chat-bubble-left"
            :color="$stats['new_inquiries'] > 0 ? 'warning' : 'gray'" />

        <x-partner-stat-card title="{{ __('partner.dashboard.store_rating') }}" :value="number_format($stats['rating_avg'], 1)" suffix="/ 5 ⭐" icon="star" color="gray" />
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">{{ __('partner.dashboard_classified.recent_listings') }}</h3>

        @if($stats['recent_listings']->isEmpty())
            <p class="text-sm text-gray-500">{{ __('partner.dashboard_classified.no_listings') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase border-b border-gray-100">
                            <th class="py-2 pr-4">{{ __('partner.dashboard_classified.listing') }}</th>
                            <th class="py-2 pr-4">{{ __('partner.dashboard_classified.status') }}</th>
                            <th class="py-2 pr-4">{{ __('partner.dashboard_classified.created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['recent_listings'] as $listing)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 pr-4">
                                    @if(Route::has('partner.classifieds.show'))
                                        <a href="{{ route('partner.classifieds.show', $listing->id) }}" class="text-primary-600 hover:underline">{{ $listing->title ?? $listing->listing_number }}</a>
                                    @else
                                        {{ $listing->title ?? $listing->listing_number }}
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $listing->status?->label() ?? '—' }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $listing->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection
