<?php

namespace App\Rules;

use App\Models\Country;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accepts only a country id vendors may register in or list in
 * ({@see Country::scopeAvailableForVendors()}): launched and active, or an
 * active draft the admin opened for vendors before launch.
 */
class CountryAvailableForVendors implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || ! Country::availableForVendors()->whereKey($value)->exists()) {
            $fail('validation.country_not_available_for_vendors')->translate();
        }
    }
}
