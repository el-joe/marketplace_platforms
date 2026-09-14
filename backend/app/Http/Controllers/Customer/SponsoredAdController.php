<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdImpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SponsoredAdController extends Controller
{
    /**
     * POST /api/customer/v1/{country}/ads/sponsored/click
     * Public, no auth. Records a click on the product-page cross-sell ad bar
     * and, for CPC campaigns, charges the campaign's budget.
     */
    public function click(Request $request, $country): JsonResponse
    {
        $data = $request->validate([
            'impression_id'     => ['required', 'uuid', 'exists:ad_impressions,id'],
            'vendor_listing_id' => ['required', 'uuid'],
        ]);

        dispatch(function () use ($data, $request) {
            $impression = AdImpression::find($data['impression_id']);
            if (!$impression || $impression->was_clicked) {
                return;
            }

            $campaign = AdCampaign::find($impression->ad_campaign_id);
            if (!$campaign || $campaign->status !== 'active') {
                return;
            }

            $cost = $campaign->type === 'cpc' ? $campaign->bid : 0;

            AdClick::create([
                'id'                => (string) Str::uuid(),
                'ad_impression_id'  => $impression->id,
                'ad_campaign_id'    => $campaign->id,
                'vendor_listing_id' => $data['vendor_listing_id'],
                'customer_id'       => auth('customer')->id(),
                'session_id'        => $request->header('X-Session-Id') ?? Str::random(26),
                'ip_address'        => $request->ip(),
                'user_agent'        => $request->userAgent(),
                'is_fraud_suspect'  => false,
                'cost'              => $cost,
                'country_id'        => $request->attributes->get('country')?->id,
                'clicked_at'        => now(),
            ]);

            $impression->update(['was_clicked' => true, 'clicked_at' => now(), 'cost_charged' => $cost]);

            if ($cost > 0) {
                $campaign->increment('budget_spent_total', $cost);
                $campaign->increment('budget_spent_today', $cost);
            }
        })->afterResponse();

        return ApiResponse::success(null, 'Click recorded', 200);
    }
}
