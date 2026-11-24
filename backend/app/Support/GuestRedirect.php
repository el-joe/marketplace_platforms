<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Where an unauthenticated request goes (R4, retest 2026-11-24). The framework default is
 * route('login'), which this app does not define, so a guest opening an API URL in a browser got
 * a 500 "Route [login] not defined" with a stack trace.
 */
class GuestRedirect
{
    /** @var array<string, string> panel subdomain key (config app.subdomains) => login route name */
    private const PANEL_LOGIN_ROUTES = [
        'admin' => 'admin.login',
        'partner' => 'partner.login',
        'delivery' => 'delivery.login',
        'travel' => 'travel-agency.login',
        'carrier' => 'carrier.login',
        'marketer' => 'marketer.login',
    ];

    /**
     * Anything under /api, or any request on the api.* host, is an API call: always JSON, never a redirect.
     */
    public static function isApiRequest(Request $request): bool
    {
        return $request->is('api', 'api/*') || str_starts_with(strtolower($request->getHost()), 'api.');
    }

    public static function loginUrlFor(Request $request): ?string
    {
        if (self::isApiRequest($request) || $request->expectsJson()) {
            return null;
        }

        $subdomain = self::subdomainOf($request);

        foreach (self::PANEL_LOGIN_ROUTES as $key => $routeName) {
            if ($subdomain !== null && $subdomain === config("app.subdomains.{$key}") && Route::has($routeName)) {
                return route($routeName);
            }
        }

        // Customer pages served by Laravel (classifieds/travel booking forms): sign in on the storefront.
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return $frontendUrl !== '' ? $frontendUrl : null;
    }

    private static function subdomainOf(Request $request): ?string
    {
        $host = strtolower($request->getHost());
        $baseDomain = strtolower((string) config('app.domain'));

        if ($baseDomain === '' || ! str_ends_with($host, '.'.$baseDomain)) {
            return null;
        }

        return substr($host, 0, -strlen('.'.$baseDomain));
    }
}
