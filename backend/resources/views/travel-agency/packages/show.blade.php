@extends('layouts.travel-agency')

@section('title', $package->title_ar ?: $package->title_en)

@section('content')
    <div class="max-w-4xl space-y-6">

        {{-- Header --}}
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-black text-gray-900">{{ $package->title_ar ?: $package->title_en }}</h1>
                <p class="text-sm text-gray-400">{{ $package->title_en }}</p>
            </div>
            <div class="flex gap-2">
                @if(in_array($package->status, [\App\Enums\TravelPackageStatus::Draft, \App\Enums\TravelPackageStatus::PendingReview]))
                    <a href="{{ route('travel-agency.packages.edit', $package) }}"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">{{ __('travel.packages.edit') }}</a>
                @endif
                @if($package->status === \App\Enums\TravelPackageStatus::Draft)
                    <form method="POST" action="{{ route('travel-agency.packages.submit', $package) }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-400">
                            {{ __('travel.packages.submit_for_review') }}
                        </button>
                    </form>
                @endif
                @if($package->status === \App\Enums\TravelPackageStatus::Active && ($package->seatsRemaining() === null || $package->seatsRemaining() > 0))
                    <a href="{{ route('travel-agency.bookings.create', ['package_id' => $package->id]) }}"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-500">
                        + {{ __('travel.bookings.new_booking') }}
                    </a>
                @endif
                <a href="{{ route('travel-agency.packages.index') }}"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('travel.packages.back') }}</a>
            </div>
        </div>

        {{-- Status --}}
        @php
            $colors = ['draft' => 'bg-gray-100 text-gray-600', 'pending_review' => 'bg-amber-100 text-amber-700', 'active' => 'bg-emerald-100 text-emerald-700', 'sold_out' => 'bg-purple-100 text-purple-700', 'expired' => 'bg-gray-100 text-gray-500'];
            $labels = [
                'draft' => __('travel.dashboard.draft'),
                'pending_review' => __('travel.dashboard.pending_review'),
                'active' => __('travel.dashboard.active'),
                'sold_out' => __('travel.dashboard.sold_out'),
                'expired' => __('travel.dashboard.expired')
            ];
        @endphp
        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium {{ $colors[$package->status->value] ?? '' }}">
            {{ $labels[$package->status->value] ?? $package->status->label() }}
        </span>

        {{-- Details --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">{{ __('travel.packages.trip_details') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('travel.packages.destination') }}</dt>
                        <dd class="text-gray-900">
                            {{ $package->destinationCountry?->name_en ?? '-' }} , {{ $package->destinationCity?->name_en ?? '-' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('travel.packages.departure') }}</dt>
                        <dd class="text-gray-900">{{ $package->departure_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('travel.packages.return') }}</dt>
                        <dd class="text-gray-900">{{ $package->return_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('travel.packages.duration') }}</dt>
                        <dd class="text-gray-900">{{ $package->duration_days }} {{ __('travel.packages.days') }} / {{ $package->duration_nights }} {{ __('travel.packages.nights') }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('travel.packages.price_per_person') }}</dt>
                        <dd class="text-gray-900 font-bold">{{ $package->priceFormatted() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('travel.packages.seats') }}</dt>
                        <dd class="text-gray-900">{{ $package->seats_booked }} {{ __('travel.packages.booked_seats_label') }} /
                            {{ $package->available_seats ?? '∞' }} {{ __('travel.packages.available') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">{{ __('travel.packages.inclusions') }}</h3>
                @if($package->inclusions && $package->inclusions->isNotEmpty())
                    <ul class="space-y-1 text-sm text-gray-700">
                        @foreach($package->inclusions as $item)
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span>
                                {{ $item->icon }} {{ $item->name }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-400">{{ __('travel.packages.not_specified') }}</p>
                @endif
            </div>
        </div>

        {{-- Pricing Tiers --}}
        @if($package->pricing_tiers_enabled && $package->pricingTiers->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide mb-4">{{ __('travel.packages.pricing_tiers') }}</h3>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-start font-semibold text-gray-700">{{ __('travel.packages.travelers_count') }}</th>
                            <th class="px-4 py-2 text-start font-semibold text-gray-700">{{ __('travel.packages.price') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($package->pricingTiers as $tier)
                            <tr>
                                <td class="px-4 py-2 text-gray-700">{{ __('travel.packages.per_traveler_count', ['count' => $tier->travelers_count]) }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ \App\Helpers\CurrencyFormatter::formatPrice($tier->price, $package->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @unless($package->show_pricing_tiers_to_customer)
                    <p class="text-xs text-gray-400 mt-3">{{ __('travel.packages.pricing_tiers_description') }}</p>
                @endunless
            </div>
        @endif

        {{-- Contract --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide mb-4">{{ __('travel.packages.package_contract') }}</h3>
            @if($package->contract_file_path)
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <svg class="w-8 h-8 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $package->contract_file_original_name }}</p>
                            @if($package->contract_uploaded_at)
                                <p class="text-xs text-gray-400 mt-0.5">{{ __('travel.packages.uploaded_at') }}{{ $package->contract_uploaded_at->format('d M Y H:i') }}</p>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('travel-agency.packages.contract.download', $package) }}"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 shrink-0">
                        {{ __('travel.packages.download_contract') }}
                    </a>
                </div>
            @else
                <p class="text-sm text-amber-600 flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('travel.packages.no_contract_uploaded') }}
                </p>
            @endif
        </div>

        {{-- Media --}}
        @if($package->media->count())
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide mb-4">{{ __('travel.packages.media') }}</h3>
                <div class="grid grid-cols-4 gap-3">
                    @foreach($package->media as $m)
                        @if($m->media_type === 'image')
                            <img src="{{ $m->url() }}" class="rounded-lg h-28 w-full object-cover border border-gray-200">
                        @else
                            <video src="{{ $m->url() }}" controls
                                class="rounded-lg h-28 w-full object-cover border border-gray-200"></video>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Bookings --}}
        @if($package->bookings->count())
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('travel.bookings.title') }} ({{ $package->bookings->count() }})</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.booking_number') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.customer') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.travelers') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.total') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.status') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($package->bookings as $booking)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $booking->booking_number }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $booking->customer?->name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $booking->travelers_count }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $booking->totalFormatted() }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $booking->status->label() }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $booking->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
