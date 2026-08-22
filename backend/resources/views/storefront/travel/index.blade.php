@extends('layouts.storefront')

@section('title', __('portal.travel_page.packages_title'))

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-8">

    {{-- Hero --}}
    <div class="bg-gradient-to-l from-blue-600 to-blue-800 rounded-3xl p-8 text-white">
        <h1 class="text-3xl font-black mb-2">{{ __('portal.travel_page.hero_title') }}</h1>
        <p class="text-blue-200 text-lg">{{ __('portal.travel_page.hero_subtitle') }}</p>
    </div>

    {{-- Search Filters --}}
    <form method="GET" class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('portal.travel_page.filter_country') }}</label>
                <select name="country" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">{{ __('portal.travel_page.filter_all_countries') }}</option>
                    @foreach($countries as $c)
                    <option value="{{ $c }}" {{ request('country') === $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('portal.travel_page.filter_city') }}</label>
                <input type="text" name="city" value="{{ request('city') }}" placeholder="{{ __('portal.travel_page.filter_city_placeholder') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('portal.travel_page.filter_from_date') }}</label>
                <input type="date" name="departure_from" value="{{ request('departure_from') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('portal.travel_page.filter_to_date') }}</label>
                <input type="date" name="departure_to" value="{{ request('departure_to') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('portal.travel_page.filter_max_price') }}</label>
                <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="{{ __('portal.travel_page.filter_max_price_placeholder') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white rounded-lg py-2 text-sm font-medium hover:bg-blue-500">{{ __('portal.travel_page.filter_search') }}</button>
                <a href="{{ url()->current() }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">{{ __('portal.travel_page.filter_clear') }}</a>
            </div>
        </div>
    </form>

    {{-- Results --}}
    @if($packages->isEmpty())
    <div class="text-center py-16">
        <p class="text-5xl mb-4">✈</p>
        <p class="text-gray-500 text-lg">{{ __('portal.travel_page.no_packages') }}</p>
        <a href="{{ url()->current() }}" class="mt-4 inline-block text-blue-600 hover:underline text-sm">{{ __('portal.travel_page.view_all_packages') }}</a>
    </div>
    @else
    <div>
        <p class="text-sm text-gray-500 mb-4">{{ __('portal.travel_page.packages_available', ['count' => $packages->total()]) }}</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach($packages as $pkg)
            @php $cover = $pkg->coverImage(); @endphp
            <a href="{{ route('travel.show', $pkg) }}"
               class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow group">
                {{-- Cover image --}}
                @if($cover)
                <img src="{{ $cover->url() }}" class="w-full h-44 object-cover group-hover:scale-105 transition-transform duration-300">
                @else
                <div class="w-full h-44 bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-400 text-5xl">✈</div>
                @endif

                <div class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-bold text-gray-900 text-sm leading-tight line-clamp-2">{{ $pkg->title_ar ?: $pkg->title_en }}</h3>
                    </div>
                    <p class="text-xs text-blue-600 font-medium">
                        📍 {{ $pkg->destination_country }}{{ $pkg->destination_city ? '، '.$pkg->destination_city : '' }}
                    </p>
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span>🗓 {{ $pkg->departure_date->format('d M Y') }}</span>
                        <span>⏱ {{ __('portal.travel_page.duration', ['days' => $pkg->duration_days, 'nights' => $pkg->duration_nights]) }}</span>
                    </div>
                    @if($pkg->available_seats !== null)
                    <p class="text-xs text-amber-600">{{ __('portal.travel_page.seats_remaining', ['count' => $pkg->seatsRemaining()]) }}</p>
                    @endif
                    <div class="pt-2 border-t border-gray-100 flex items-center justify-between">
                        <span class="font-black text-gray-900">{{ $pkg->priceFormatted() }}</span>
                        <span class="text-xs text-gray-400">{{ __('portal.travel_page.per_person') }}</span>
                    </div>
                </div>
            </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $packages->links() }}
        </div>
    </div>
    @endif
</div>
@endsection
