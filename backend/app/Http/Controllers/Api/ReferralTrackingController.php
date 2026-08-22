<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use App\Services\LastClickAttributionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ReferralTrackingController extends Controller
{
    public function __construct(private LastClickAttributionService $attributionService) {}

    /**
     * GET /r/{code}
     * Track a referral click and redirect to the product page.
     */
    public function track(string $code, Request $request): RedirectResponse
    {
        $invitation = MarketerCampaignInvitation::where('referral_code', $code)
            ->where('status', 'accepted')
            ->with('campaign.vendorListing.productVariant')
            ->first();

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        if (!$invitation) {
            return redirect($frontendUrl);
        }

        $sessionId = $request->header('X-Session-Id')
            ?? $request->cookie('session_id')
            ?? session()->getId();

        $this->attributionService->recordClick($code, $sessionId);

        $vendorListing = $invitation->campaign?->vendorListing;
        $variantId = $vendorListing?->productVariant?->id;
        $listingId = $vendorListing?->id;

        if (!$variantId || !$listingId) {
            return redirect($frontendUrl);
        }

        return redirect("{$frontendUrl}/products/{$variantId}/{$listingId}");
    }
}
