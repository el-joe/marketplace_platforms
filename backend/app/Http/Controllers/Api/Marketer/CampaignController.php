<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    public function __construct(private readonly MarketerCampaignService $campaignService) {}

    private function marketer()
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    public function active()
    {
        $marketer = $this->marketer();
        $data = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['active', 'auto_approved']))
            ->with(['campaign.country'])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function finished()
    {
        $marketer = $this->marketer();
        $data = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['done', 'cancelled', 'rejected']))
            ->with(['campaign.country'])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'uuid'],
            'listing_type' => ['required', 'in:vendor_listing,admin_listing'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $source = $validated['listing_type'] === 'vendor_listing'
            ? CampaignSource::vendorListing($validated['listing_id'])
            : CampaignSource::adminListing($validated['listing_id']);

        $campaign = $this->campaignService->requestCampaign($this->marketer(), $source, $validated);

        return response()->json(['success' => true, 'data' => $campaign], 201);
    }
}
