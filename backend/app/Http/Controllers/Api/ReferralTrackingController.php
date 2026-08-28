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
            return redirect($frontendUrl . '?ref=' . urlencode($code))
                ->cookie('mkt_ref', $code, 60 * 24 * 30, '/', null, true, false);
        }

        $sessionId = $request->header('X-Session-Id')
            ?? $request->cookie('session_id')
            ?? session()->getId();

        $this->attributionService->recordClick($code, $sessionId);

        $vendorListing = $invitation->campaign?->vendorListing;
        $listingId = $vendorListing?->id;

        $destination = $listingId
            ? "{$frontendUrl}/products/{$listingId}?ref=" . urlencode($code)
            : $frontendUrl . '?ref=' . urlencode($code);

        // 30-day cookie so the referral code survives registration even if
        // the customer browses before signing up.
        return redirect($destination)
            ->cookie('mkt_ref', $code, 60 * 24 * 30, '/', null, true, false);
    }
}
