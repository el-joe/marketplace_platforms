<?php

namespace App\Services;

use Illuminate\Http\Request;

class ListingModeResolver
{
    public static function isNawyNow(Request $request): bool
    {
        return strtolower((string) $request->header('X-Listing-Type')) === 'nawy_now';
    }
}
