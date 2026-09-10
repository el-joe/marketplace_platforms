<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketer\Ads\QuoteRequest;
use App\Http\Resources\Vendor\Ads\AdSlotResource;
use App\Http\Responses\ApiResponse;
use App\Models\Marketer;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerProfile;
use App\Models\PaidAdSlot;
use App\Services\Ads\AdSlotAvailabilityService;
use App\Services\Ads\AdSlotQuoteService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdSlotController extends Controller
{
    public function __construct(
        private readonly AdSlotQuoteService $quoteService,
        private readonly AdSlotAvailabilityService $availabilityService,
    ) {
    }

    private function marketer(): Marketer
    {
        return auth('marketer_api')->user()->marketer;
    }

    private function baseQuery()
    {
        $marketer = $this->marketer();

        return PaidAdSlot::bookable()
            ->where('country_id', $marketer->country_id)
            ->whereIn('allowed_advertisers', ['marketer', 'both']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->with(['placementDefinition', 'pageBlock.page', 'country']);

        if ($surface = $request->query('surface')) {
            $query->where('target_type', $surface);
        }
        if ($pricingModel = $request->query('pricing_model')) {
            $query->where('pricing_model', $pricingModel);
        }

        $slots = $query->orderBy('sort_order')->get();

        return ApiResponse::success(AdSlotResource::collection($slots)->resolve());
    }

    public function show(string $id): JsonResponse
    {
        $slot = $this->baseQuery()->with(['placementDefinition', 'pageBlock.page', 'country'])->find($id);

        if (! $slot) {
            return ApiResponse::error('Not found.', [], 404);
        }

        return ApiResponse::success(new AdSlotResource($slot));
    }

    public function calendar(Request $request, string $id): JsonResponse
    {
        $slot = $this->baseQuery()->find($id);
        if (! $slot) {
            return ApiResponse::error('Not found.', [], 404);
        }

        $month = $request->query('month') ? Carbon::parse($request->query('month').'-01') : now();

        return ApiResponse::success($this->availabilityService->calendar($slot, $month));
    }

    public function quote(QuoteRequest $request, string $id): JsonResponse
    {
        $slot = $this->baseQuery()->find($id);
        if (! $slot) {
            return ApiResponse::error('Not found.', [], 404);
        }

        try {
            $quote = $this->quoteService->quote(
                $slot,
                Carbon::parse($request->validated('booked_from')),
                Carbon::parse($request->validated('booked_until')),
                $request->validated('budget'),
            );
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success($quote);
    }

    /**
     * Destinations available to this marketer: their own profile page, and
     * campaigns they have an accepted invitation for.
     */
    public function destinations(Request $request): JsonResponse
    {
        $marketer = $this->marketer();
        $type = $request->query('type');

        if ($type === 'marketer_profile') {
            $profile = MarketerProfile::where('marketer_id', $marketer->id)->first();

            if (! $profile?->profile_slug) {
                return ApiResponse::success([], 'Profile page is not set up yet.');
            }

            return ApiResponse::success([[
                'id' => $marketer->id,
                'label' => $profile->profile_slug,
                'url' => '/marketer/'.$profile->profile_slug,
            ]]);
        }

        if ($type === 'campaign') {
            $invitations = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
                ->where('status', 'accepted')
                ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['active', 'auto_approved']))
                ->with('campaign')
                ->get();

            // MarketerCampaign has no fixed start/end date window today, so every
            // accepted, still-active-or-auto_approved campaign is offered.
            $items = $invitations->map(fn (MarketerCampaignInvitation $invitation) => [
                'id' => $invitation->campaign->id,
                'label' => $invitation->campaign->getPromotedTitle(),
                'referral_code' => $invitation->referral_code,
                'available' => true,
                'reason' => null,
            ]);

            return ApiResponse::success($items->values());
        }

        return ApiResponse::success([]);
    }
}
