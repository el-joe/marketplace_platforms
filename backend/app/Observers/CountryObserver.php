<?php

namespace App\Observers;

use App\Models\Country;
use App\Services\PortalContentCountrySync;

/**
 * Gives every new / newly-activated country a full copy of the global
 * Portal Content rows so its admin can edit them immediately.
 */
class CountryObserver
{
    public function __construct(private readonly PortalContentCountrySync $sync) {}

    public function created(Country $country): void
    {
        if ($country->is_active) {
            $this->sync->copyGlobalToCountry($country);
        }
    }

    public function updated(Country $country): void
    {
        if ($country->is_active && ($country->wasChanged('is_active') || $country->wasChanged('site_code'))) {
            $this->sync->copyGlobalToCountry($country);
        }
    }
}
