<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CountrySwitchController extends Controller
{
    /**
     * GET /country/{country} — the country switcher.
     *
     * Persists the pick in the session (404 unless the country is
     * portal-selectable) and returns to the previous page. On `{country}`
     * prefixed pages the URL segment is rewritten to the new country.
     */
    public function __invoke(Request $request, string $country): RedirectResponse
    {
        $country = strtolower($country);

        abort_unless(Country::isPortalSelectable($country), 404);

        $request->session()->put(Country::PORTAL_SESSION_KEY, $country);

        $previous = url()->previous();
        $parts = parse_url($previous);

        try {
            $matched = app('router')->getRoutes()->match(Request::create($previous));
        } catch (Throwable) {
            $matched = null;
        }

        if ($matched && $matched->parameter('country') !== null && isset($parts['path'])) {
            $segments = explode('/', ltrim($parts['path'], '/'));
            $segments[0] = $country;
            $previous = $request->getSchemeAndHttpHost().'/'.implode('/', $segments)
                .(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return redirect($previous);
    }
}
