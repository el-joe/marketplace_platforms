<?php

namespace App\Http\Middleware;

use App\Models\Country;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectCountry
{
    public function handle(Request $request, Closure $next): Response
    {
        $siteCode = $request->route('country');
        $country = Country::where('site_code', $siteCode)->where('is_active', true)->first();

        if (!$country) {
            return response()->json([
                'success' => false,
                'message' => 'Country not found or not active.',
            ], 404);
        }

        // Bind resolved country so controllers and services can retrieve it
        $request->merge(['resolved_country_id' => $country->id]);
        $request->attributes->set('country', $country);

        return $next($request);
    }
}
