<?php

namespace App\Services\Customer;

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;

class AddressService
{
    /**
     * Set the given address as default, unsetting any previous default
     * for this customer in a single UPDATE rather than N+1.
     */
    public function setDefault(Customer $customer, Address $address): void
    {
        // Clear existing default in one query
        $customer->addresses()
            ->where('id', '!=', $address->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);
    }

    public function canDelete(Address $address): bool
    {
        // payment_methods table removed — saved cards no longer exist
        return true;
    }

    /**
     * Resolve country/city ids from the geocoded ISO code and city name.
     * Cities missing from the DB are created (inactive) so the address keeps
     * the real location; falls back to the given defaults when unresolved.
     *
     * @return array{country_id: ?string, city_id: ?string}
     */
    public function resolveLocation(?string $countryCode, ?string $cityName, ?string $fallbackCountryId, ?string $cityId = null): array
    {
        $country = $countryCode ? Country::where('iso_code_2', strtoupper($countryCode))->first() : null;
        $countryId = $country?->id ?? $fallbackCountryId;

        if ($cityId || !$cityName || !$countryId) {
            return ['country_id' => $countryId, 'city_id' => $cityId];
        }

        $name = trim($cityName);
        $city = City::where('country_id', $countryId)
            ->where(fn ($q) => $q->whereRaw('LOWER(name_en) = ?', [mb_strtolower($name)])
                ->orWhere('name_ar', $name))
            ->first();

        $city ??= City::create([
            'country_id' => $countryId,
            'name_en' => $name,
            'name_ar' => $name,
            'is_active' => false,
        ]);

        return ['country_id' => $countryId, 'city_id' => $city->id];
    }
}
