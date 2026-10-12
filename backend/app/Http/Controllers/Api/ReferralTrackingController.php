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
     * Track a referral click and return/redirect to the product page.
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
            $slug = $this->resolver->productSlug($invitation->campaign);

            return $this->respond($request, $slug ? "{$frontendUrl}/products/{$slug}" : $frontendUrl, false);
        }

        $sessionId = $request->header('X-Session-Id')
            ?? $request->query('session_id')
            ?? $request->cookie('session_id')
            ?? session()->getId();

        $this->attributionService->recordClick($code, $sessionId);

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
