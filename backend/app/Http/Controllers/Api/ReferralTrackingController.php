<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use App\Services\LastClickAttributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ReferralTrackingController extends Controller
{
    public function __construct(private LastClickAttributionService $attributionService) {}

    /**
     * GET /r/{code}
     * Track a referral click and return/redirect to the product page.
     * Responds with JSON when called via fetch (the Next.js /r/[code] page),
     * falls back to a plain redirect for any non-JS/legacy direct navigation.
     */
    public function track(string $code, Request $request): RedirectResponse|JsonResponse
    {
        $invitation = MarketerCampaignInvitation::where('referral_code', $code)
            ->where('status', 'accepted')
            ->with('campaign.vendorListing.productVariant.product')
            ->first();

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        if (!$invitation) {
            $destination = $frontendUrl . '?ref=' . urlencode($code);

            return $request->wantsJson()
                ? response()->json(['destination' => $destination])
                : redirect($destination);
        }

        $sessionId = $request->header('X-Session-Id')
            ?? $request->query('session_id')
            ?? $request->cookie('session_id')
            ?? session()->getId();

        $this->attributionService->recordClick($code, $sessionId);

        $slug = $invitation->campaign
            ?->vendorListing
            ?->productVariant
            ?->product
            ?->slug;

        $destination = $slug
            ? "{$frontendUrl}/products/{$slug}?ref=" . urlencode($code)
            : $frontendUrl . '?ref=' . urlencode($code);

        return $request->wantsJson()
            ? response()->json(['destination' => $destination])
            : redirect($destination);
    }
}
