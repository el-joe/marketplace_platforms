<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReferralCodeResolver;
use App\Services\LastClickAttributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ReferralTrackingController extends Controller
{
    public function __construct(
        private LastClickAttributionService $attributionService,
        private ReferralCodeResolver $resolver,
    ) {}

    /**
     * GET /r/{code}
     * Track a referral click and return/redirect to the promoted product, classified listing or trip.
     * Responds with JSON when called via fetch (the Next.js /r/[code] page),
     * falls back to a plain redirect for any non-JS/legacy direct navigation.
     */
    public function track(string $code, Request $request): RedirectResponse|JsonResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $invitation = $this->resolver->find($code);

        // Unknown code: legacy behaviour (the storefront may treat it as a customer referral).
        if (! $invitation) {
            return $this->respond($request, $frontendUrl . '?ref=' . urlencode($code), false);
        }

        // P14: a code that is no longer attributable (replaced / timed out / rejected invitation,
        // grace over, cancelled or done campaign) records NO click and carries NO ref: the customer
        // just lands on the product page and no commission is created for anybody.
        if (! $this->resolver->acceptsNewClicks($invitation)) {
            $path = $this->resolver->destinationPath($invitation->campaign);

            return $this->respond($request, $path ? $frontendUrl.$path : $frontendUrl, false);
        }

        $sessionId = $request->header('X-Session-Id')
            ?? $request->query('session_id')
            ?? $request->cookie('session_id')
            ?? session()->getId();

        $this->attributionService->recordClick($code, $sessionId, $invitation);

        // Classified listings and trips have no marketer listing: the link lands on the promoted item itself. The
        // click (remembered per session) is matched when the visitor sends an inquiry or books the trip.
        if (in_array($invitation->campaign?->campaign_category, ['classified', 'travel'], true)) {
            $path = $this->resolver->destinationPath($invitation->campaign);
            $destination = $path ? $frontendUrl.$path.'?ref='.urlencode($code) : $frontendUrl.'?ref='.urlencode($code);

            return $this->respond($request, $destination, true);
        }

        // enhancement.md P-16: redirect to the MARKETER listing this
        // invitation created (App\Models\MarketerListing, keyed by
        // invitation_id), never the underlying vendor/admin listing.
        $marketerListing = $invitation->marketerListing()->with('productVariant.product')->first();
        $slug = $marketerListing?->productVariant?->product?->slug
            ?? $this->resolver->productSlug($invitation->campaign);

        $destination = $marketerListing
            ? "{$frontendUrl}/products/{$slug}?ref=" . urlencode($code) . '&marketer_listing_id=' . urlencode($marketerListing->id)
            : ($slug ? "{$frontendUrl}/products/{$slug}?ref=" . urlencode($code) : $frontendUrl . '?ref=' . urlencode($code));

        return $this->respond($request, $destination, true);
    }

    private function respond(Request $request, string $destination, bool $attributed): RedirectResponse|JsonResponse
    {
        return $request->wantsJson()
            ? response()->json(['destination' => $destination, 'attributed' => $attributed])
            : redirect($destination);
    }
}
