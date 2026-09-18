{{--
    "Ships to" destination picker for a vendor listing.
    Requires: $listing, $shipsToCountries (Country[] excluding listing's own country),
              $selectedDestinationIds (string[] of currently active destination_country_id)
--}}
<div class="max-w-2xl bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4 mt-6">
    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('admin.international_shipping.ships_to_title') }}</h2>
    <p class="text-xs text-gray-400">{{ __('admin.international_shipping.ships_to_help') }}</p>

    <form method="POST" action="{{ route('admin.vendor-listings.ships-to.update', $listing) }}" novalidate>
        @csrf

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-72 overflow-y-auto border border-gray-100 rounded-lg p-3">
            @forelse($shipsToCountries as $country)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="destination_country_ids[]" value="{{ $country->id }}"
                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-200"
                           {{ in_array($country->id, $selectedDestinationIds, true) ? 'checked' : '' }}>
                    {{ $country->name_en }}
                </label>
            @empty
                <p class="text-xs text-gray-400 col-span-full">{{ __('admin.international_shipping.no_other_countries') }}</p>
            @endforelse
        </div>

        <div class="pt-3">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.international_shipping.save_ships_to') }}</button>
        </div>
    </form>
</div>
