<?php

namespace App\Http\Middleware;

use App\Models\Country;
use App\Services\PortalAnalytics\PortalTracker;
use App\Services\PortalAnalytics\UserAgentClassifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Anonymous portal visitor analytics.
 *
 * Gives every real browser a random visitor id cookie (HttpOnly, SameSite=Lax, one year, no
 * personal data), and tells the {@see PortalTracker} what to record. Nothing is written
 * here: the tracker flushes in {@see self::terminate()}, after the response was sent, and
 * any failure is logged and swallowed. Crawlers, HEAD/OPTIONS requests and visitors who send
 * Do-Not-Track are never tracked.
 *
 * A page view is a successful HTML GET (not AJAX, not a helper route from
 * `portal_analytics.excluded_route_names`, not a browser prefetch). Re-requesting the page
 * the visitor is already on (a refresh) is not counted again, see the tracker.
 */
class TrackPortalVisit
{
    public function __construct(private readonly PortalTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = null;
        $isNewVisitor = false;

        try {
            [$visitorId, $isNewVisitor] = $this->begin($request);
        } catch (Throwable $e) {
            $this->logFailure($e);
        }

        $response = $next($request);

        try {
            if ($visitorId !== null && $isNewVisitor) {
                $response->headers->setCookie($this->cookie($visitorId));
            }
        } catch (Throwable $e) {
            $this->logFailure($e);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if ($this->tracker->isActive() && $this->isCountablePageView($request, $response)) {
                $this->tracker->recordPageView($this->pagePath($request), $request->route()?->getName());
            }
        } catch (Throwable $e) {
            $this->logFailure($e);
        }

        $this->tracker->flush();
    }

    /**
     * @return array{0: ?string, 1: bool} visitor id (null when not tracked) and whether it was just issued
     */
    private function begin(Request $request): array
    {
        if (! $this->isTrackable($request)) {
            return [null, false];
        }

        $cookieValue = $request->cookie((string) config('portal_analytics.cookie.name'));
        $isNewVisitor = ! is_string($cookieValue) || ! Str::isUuid($cookieValue);
        $visitorId = $isNewVisitor ? (string) Str::uuid() : strtolower($cookieValue);

        $this->tracker->begin([
            'visitor_id' => $visitorId,
            'country_code' => $this->countryCode($request),
            'ip_hash' => $this->hashIp($request->ip()),
            'device_type' => UserAgentClassifier::deviceType($request->userAgent()),
            'locale' => $this->locale(),
            'referrer_host' => $this->referrerHost($request),
            'utm' => [
                'source' => $this->utm($request, 'utm_source'),
                'medium' => $this->utm($request, 'utm_medium'),
                'campaign' => $this->utm($request, 'utm_campaign'),
            ],
        ]);

        return [$visitorId, $isNewVisitor];
    }

    private function isTrackable(Request $request): bool
    {
        if (! config('portal_analytics.enabled')) {
            return false;
        }

        if ($request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return false;
        }

        if (config('portal_analytics.honor_dnt') && $request->header('DNT') === '1') {
            return false;
        }

        return ! UserAgentClassifier::isBot($request->userAgent());
    }

    private function isCountablePageView(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?: $request->header('Purpose')));
        if (str_contains($purpose, 'prefetch')) {
            return false;
        }

        if (in_array($request->route()?->getName(), (array) config('portal_analytics.excluded_route_names', []), true)) {
            return false;
        }

        return $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    /**
     * The path without query string; the leading {country} segment is removed so one page is
     * one row whichever country prefix it was reached under.
     */
    private function pagePath(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $country = $request->route()?->parameter('country');

        if (is_string($country) && $country !== '' && str_starts_with(strtolower($path), '/'.strtolower($country))) {
            $path = substr($path, strlen($country) + 1) ?: '/';
        }

        return Str::limit($path === '' ? '/' : $path, 191, '');
    }

    private function countryCode(Request $request): ?string
    {
        $code = $request->attributes->get(Country::PORTAL_REQUEST_ATTRIBUTE);

        return is_string($code) && $code !== '' ? Str::limit($code, 20, '') : null;
    }

    private function locale(): ?string
    {
        return Str::limit((string) app()->getLocale(), 5, '') ?: null;
    }

    private function referrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');
        $host = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;

        if (! is_string($host) || $host === '' || strcasecmp($host, $request->getHost()) === 0) {
            return null;
        }

        return Str::limit(strtolower($host), 191, '');
    }

    private function utm(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(preg_replace('/[^\p{L}\p{N}_\-\. ]/u', '', $value) ?? '', 100, '') ?: null;
    }

    /**
     * Salted HMAC of the IP: lets us tell "many visits from one network" apart without
     * keeping the address. The salt derives from APP_KEY, so hashes cannot be reversed by a
     * lookup table built elsewhere.
     */
    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, hash('sha256', 'portal-analytics|'.config('app.key')));
    }

    private function cookie(string $visitorId): Cookie
    {
        return cookie(
            (string) config('portal_analytics.cookie.name'),
            $visitorId,
            (int) config('portal_analytics.cookie.minutes'),
            '/',
            config('session.domain'),
            (bool) config('session.secure'),
            true,
            false,
            'lax',
        );
    }

    private function logFailure(Throwable $e): void
    {
        Log::warning('Portal analytics tracking failed', [
            'exception' => $e::class,
            'error' => $e->getMessage(),
        ]);
    }
}
