{{--
    Latitude/longitude picker (client item #19 — distance-based delivery origins).
    Driven by resources/js/components/location-picker.js (include it with @vite on the page).

    With services.google.maps_key: a map with a draggable pin (click to place), loaded only when the picker scrolls
    into view. Without a key: numeric inputs + "use my current location" + "open in Google Maps" link.

    Usage: <x-location-picker :latitude="$lat" :longitude="$lng" />
--}}
@props([
    'latitude' => null,
    'longitude' => null,
    'latName' => 'latitude',
    'lngName' => 'longitude',
    'disabled' => false,
    'defaultCenter' => [23.5880, 58.3829],
])
@php
    $mapsKey = config('services.google.maps_key') ?: null;
    $uid = 'lp-'.\Illuminate\Support\Str::random(6);
    $fmt = fn ($value) => $value === null || $value === '' ? '' : rtrim(rtrim(number_format((float) $value, 7, '.', ''), '0'), '.');
    $i18n = [
        'locating' => __('store_location.locating'),
        'use_current_location' => __('store_location.use_current_location'),
        'geolocation_unsupported' => __('store_location.geolocation_unsupported'),
        'geolocation_failed' => __('store_location.geolocation_failed'),
    ];
    $inputClass = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-400';
@endphp
<div {{ $attributes->merge(['class' => 'space-y-3']) }}
     data-location-picker
     data-maps-key="{{ $mapsKey }}"
     data-default-lat="{{ $defaultCenter[0] }}"
     data-default-lng="{{ $defaultCenter[1] }}"
     data-i18n="{{ json_encode($i18n) }}">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label for="{{ $uid }}-lat" class="block text-sm font-medium text-gray-700 mb-1">{{ __('store_location.latitude') }}</label>
            <input type="text" inputmode="decimal" dir="ltr" id="{{ $uid }}-lat" name="{{ $latName }}"
                   value="{{ old($latName, $fmt($latitude)) }}" placeholder="23.5880000" autocomplete="off"
                   data-lp-lat @disabled($disabled) class="{{ $inputClass }}">
            <p class="mt-1 text-xs text-red-600 {{ $errors->has($latName) ? '' : 'hidden' }}" data-error-for="{{ $latName }}">{{ $errors->first($latName) }}</p>
        </div>
        <div>
            <label for="{{ $uid }}-lng" class="block text-sm font-medium text-gray-700 mb-1">{{ __('store_location.longitude') }}</label>
            <input type="text" inputmode="decimal" dir="ltr" id="{{ $uid }}-lng" name="{{ $lngName }}"
                   value="{{ old($lngName, $fmt($longitude)) }}" placeholder="58.3829000" autocomplete="off"
                   data-lp-lng @disabled($disabled) class="{{ $inputClass }}">
            <p class="mt-1 text-xs text-red-600 {{ $errors->has($lngName) ? '' : 'hidden' }}" data-error-for="{{ $lngName }}">{{ $errors->first($lngName) }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @unless($disabled)
            <button type="button" data-lp-locate
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v3m0 14v3m10-10h-3M5 12H2m15 0a5 5 0 11-10 0 5 5 0 0110 0z"/></svg>
                <span data-lp-locate-label>{{ __('store_location.use_current_location') }}</span>
            </button>
            <button type="button" data-lp-clear
                    class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100">
                {{ __('store_location.clear') }}
            </button>
        @endunless
        <a href="#" target="_blank" rel="noopener" data-lp-open
           class="hidden inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline">
            {{ __('store_location.open_in_google_maps') }} ↗
        </a>
    </div>

    @if($mapsKey)
        <div data-lp-map class="h-72 w-full rounded-lg border border-gray-200 bg-gray-100" role="application" aria-label="{{ __('store_location.map_hint') }}"></div>
        @unless($disabled)
            <p class="text-xs text-gray-500">{{ __('store_location.map_hint') }}</p>
        @endunless
    @else
        <p class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">{{ __('store_location.no_key_hint') }}</p>
    @endif
</div>
