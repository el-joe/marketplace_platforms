<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Back-office subdomain prefixes — these panels serve both AR and EN
     * to authenticated staff, with locale driven by session preference.
     */
    protected const BACK_OFFICE_PREFIXES = [
        'admin.',
        'partner.',
        'marketer.',
        'delivery.',
        'carrier.',
        'travel-agency.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if ($this->isSupported($locale)) {
            App::setLocale($locale);
            Carbon::setLocale($locale);

            $dir = $locale === 'ar' ? 'rtl' : 'ltr';
            $request->session()->put('locale', $locale);
            $request->session()->put('dir', $dir);
        }

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $host = $request->getHost();

        if ($this->isBackOffice($host)) {
            return $this->resolveBackOfficeLocale($request);
        }

        return $this->resolvePortalLocale($request);
    }

    protected function isBackOffice(string $host): bool
    {
        foreach (self::BACK_OFFICE_PREFIXES as $prefix) {
            if (str_starts_with($host, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Back-office: session first (explicit user choice), then authenticated
     * user preference (if model has a locale column), then platform default.
     */
    protected function resolveBackOfficeLocale(Request $request): string
    {
        if ($request->session()->has('locale')) {
            return (string) $request->session()->get('locale');
        }

        $user = $request->user();
        if ($user && isset($user->locale) && $user->locale) {
            return (string) $user->locale;
        }

        return config('app.locale', 'ar');
    }

    /**
     * Portal (storefront): URL segment first (/en/ or /ar/), then
     * country default (always 'ar' per platform config), then Accept-Language.
     */
    protected function resolvePortalLocale(Request $request): string
    {
        if ($request->session()->has('locale')) {
            return (string) $request->session()->get('locale');
        }

        $segment = $request->segment(1);
        if (in_array($segment, ['ar', 'en'], true)) {
            return $segment;
        }

        $accept = $request->header('Accept-Language', '');
        if ($accept) {
            $primary = explode(',', $accept)[0];
            $lang    = strtolower(substr(explode(';', $primary)[0], 0, 2));
            if ($this->isSupported($lang)) {
                return $lang;
            }
        }

        return config('app.locale', 'ar');
    }

    protected function isSupported(string $locale): bool
    {
        return in_array($locale, config('app.available_locales', ['ar', 'en']), true);
    }
}
