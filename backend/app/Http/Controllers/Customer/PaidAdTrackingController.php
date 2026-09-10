<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Jobs\Ads\RecordPaidAdEventsJob;
use App\Models\AdFraudPattern;
use App\Services\Ads\PaidAdPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaidAdTrackingController extends Controller
{
    private const BOT_UA_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|preview/i';

    /**
     * POST /api/customer/v1/{country}/ads/impressions
     * Public, no auth. Body: { items: [{id, sig}], session_id? }.
     * Must respond fast — validate cheaply, dedupe in cache, record via a queued job.
     */
    public function impressions(Request $request, $country): JsonResponse
    {
        $payload = $this->decodePayload($request);
        $sessionId = $this->resolveSessionId($request, $payload);

        if (! $sessionId) {
            return response()->noContent();
        }

        $items = (array) ($payload['items'] ?? []);
        $items = array_slice($items, 0, (int) config('ads.max_tracking_batch'));

        if ($this->isBlocked($request)) {
            return response()->noContent();
        }

        $ttl = now()->addMinutes((int) config('ads.impression_dedupe_minutes'));
        $accepted = [];

        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $sig = $item['sig'] ?? null;

            if (! is_string($id) || ! is_string($sig) || ! PaidAdPresenter::verifySig($id, $sig)) {
                continue;
            }

            if (Cache::add("pad:imp:{$id}:{$sessionId}", 1, $ttl)) {
                $accepted[] = $id;
            }
        }

        if (! empty($accepted)) {
            dispatch(new RecordPaidAdEventsJob($accepted, 'impression'));
        }

        return response()->noContent();
    }

    /**
     * POST /api/customer/v1/{country}/ads/clicks
     * Public, no auth. Body: { id, sig, session_id? }.
     */
    public function click(Request $request, $country): JsonResponse
    {
        $payload = $this->decodePayload($request);
        $sessionId = $this->resolveSessionId($request, $payload);

        if (! $sessionId) {
            return response()->noContent();
        }

        $id = $payload['id'] ?? null;
        $sig = $payload['sig'] ?? null;

        if (! is_string($id) || ! is_string($sig) || ! PaidAdPresenter::verifySig($id, $sig)) {
            return response()->noContent();
        }

        if ($this->isBlocked($request)) {
            return response()->noContent();
        }

        $ttl = now()->addMinutes((int) config('ads.click_dedupe_minutes'));

        if (Cache::add("pad:clk:{$id}:{$sessionId}", 1, $ttl)) {
            dispatch(new RecordPaidAdEventsJob([$id], 'click'));
        }

        return response()->noContent();
    }

    /**
     * navigator.sendBeacon posts with content-type text/plain — fall back to
     * decoding the raw body as JSON when Laravel's normal form/json parsing
     * doesn't yield anything.
     */
    private function decodePayload(Request $request): array
    {
        $data = $request->all();
        if (! empty($data)) {
            return $data;
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveSessionId(Request $request, array $payload): ?string
    {
        $sessionId = $request->header('X-Session-Id') ?? ($payload['session_id'] ?? null);

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    private function isBlocked(Request $request): bool
    {
        $ua = (string) $request->userAgent();
        if ($ua !== '' && preg_match(self::BOT_UA_PATTERN, $ua)) {
            return true;
        }

        return AdFraudPattern::where('ip_address', $request->ip())
            ->where('is_blocked', true)
            ->exists();
    }
}
