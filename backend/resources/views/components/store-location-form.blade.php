{{--
    Vendor store location card (business address + pin) — partner profile and admin vendor page.
    $location is VendorStoreLocationService::present(). Requires resources/js/components/location-picker.js.
--}}
@props([
    'location',
    'url',
    'method' => 'PUT',
    'canEdit' => true,
])
<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-gray-200 shadow-sm p-6']) }} id="store-location">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-800">{{ __('store_location.section_title') }}</h2>
            <p class="mt-0.5 text-xs text-gray-500">{{ __('store_location.section_hint') }}</p>
        </div>
        <span data-lp-status="set" class="{{ $location['has_coordinates'] ? '' : 'hidden' }} inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
            {{ __('store_location.status_set') }}
        </span>
        <span data-lp-status="missing" class="{{ $location['has_coordinates'] ? 'hidden' : '' }} inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
            {{ __('store_location.status_missing') }}
        </span>
    </div>

    @unless($canEdit)
        <p class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700">{{ __('store_location.owner_only') }}</p>
    @endunless

    <form data-location-form data-url="{{ $url }}" data-method="{{ $method }}"
          data-saving-label="{{ __('store_location.saving') }}" data-failed-label="{{ __('store_location.save_failed') }}"
          novalidate class="space-y-4">
        <div>
            <label for="store-location-address" class="block text-sm font-medium text-gray-700 mb-1">{{ __('store_location.address') }}</label>
            <input type="text" id="store-location-address" name="store_address" maxlength="255"
                   value="{{ $location['store_address'] }}" placeholder="{{ __('store_location.address_placeholder') }}"
                   @disabled(! $canEdit)
                   class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-400">
            <p class="mt-1 text-xs text-red-600 hidden" data-error-for="store_address"></p>
        </div>

        <x-location-picker :latitude="$location['latitude']" :longitude="$location['longitude']" :disabled="! $canEdit" />

        @if($canEdit)
            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60">
                    {{ __('store_location.save') }}
                </button>
            </div>
        @endif
    </form>
</div>
