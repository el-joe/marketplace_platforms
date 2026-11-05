<?php

namespace App\Http\Middleware;

use App\Models\Country;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the visitor's portal country once per request and always writes
 * it to `session('portal_country')`, so every later page, link and form agrees
 * with what the header shows.
 *
 * Order: route `{country}` segment, session, IP / Accept-Language detection,
 * the single default ({@see Country::defaultSiteCode()}). Every candidate is
 * validated against the portal-selectable countries; nothing the client sends
 * is trusted. An unknown `{country}` URL segment is redirected (GET) to the
 * same URL for the resolved country, or rejected with 404 for other methods.
 */
class SetPortalCountry
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $segment = $route?->parameter('country');

        if (is_string($segment) && $route?->getName() !== 'portal.country') {
            $segment = strtolower($segment);

            if (Country::isPortalSelectable($segment)) {
                Country::rememberForRequest($segment);

                return $next($request);
            }

            return $this->rejectUnknownSegment($request);
        }

        $stored = $request->hasSession() ? $request->session()->get(Country::PORTAL_SESSION_KEY) : null;

        if (is_string($stored) && Country::isPortalSelectable($stored)) {
            $request->attributes->set(Country::PORTAL_REQUEST_ATTRIBUTE, $stored);

            return $next($request);
        }

        $resolved = Country::resolveFromRequest($request) ?? Country::defaultSiteCode();

        if ($resolved) {
            Country::rememberForRequest($resolved);
        }

        return $next($request);
    }

    /**
     * Send a stale or invalid `{country}` URL segment to the visitor's real country.
     */
    private function rejectUnknownSegment(Request $request): Response
    {
        $route = $request->route();
        $fallback = $this->fallbackCountry($request);

        abort_unless($request->isMethod('GET') && $fallback !== null && $route?->getName(), 404);

        $url = route($route->getName(), array_merge($route->parameters(), ['country' => $fallback]));

        if ($query = $request->getQueryString()) {
            $url .= '?'.$query;
        }

        return redirect($url);
    }

    private function fallbackCountry(Request $request): ?string
    {
        $stored = $request->hasSession() ? $request->session()->get(Country::PORTAL_SESSION_KEY) : null;

        if (is_string($stored) && Country::isPortalSelectable($stored)) {
            return $stored;
        }

        return Country::resolveFromRequest($request) ?? Country::defaultSiteCode();
    }
}
