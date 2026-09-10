<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerProfile;
use App\Models\PaidAdSlot;
use App\Services\Ads\AdSlotAvailabilityService;
use App\Services\Ads\AdSlotQuoteService;
use App\Services\WalletService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromoteController extends Controller
{
    public function __construct(
        private readonly AdSlotQuoteService $quoteService,
        private readonly AdSlotAvailabilityService $availabilityService,
    ) {
    }

    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    private function baseQuery()
    {
        $marketer = $this->marketer();

        return PaidAdSlot::bookable()
            ->where('country_id', $marketer->country_id)
            ->whereIn('allowed_advertisers', ['marketer', 'both']);
    }

    public function index(Request $request): View
    {
        $slots = $this->baseQuery()
            ->with(['placementDefinition', 'pageBlock.page', 'country'])
            ->when($request->query('surface'), fn ($q, $v) => $q->where('target_type', $v))
            ->when($request->query('pricing_model'), fn ($q, $v) => $q->where('pricing_model', $v))
            ->orderBy('sort_order')
            ->get();

        $grouped = $slots->groupBy(function (PaidAdSlot $slot) {
            if ($slot->target_type->value === 'page_block') {
                return 'homepage';
            }
            $code = $slot->placementDefinition?->code ?? '';
            return match (true) {
                str_contains($code, 'cart') => 'cart',
                str_contains($code, 'product') => 'product',
                str_contains($code, 'search') => 'search',
                str_contains($code, 'category') => 'category',
                default => 'homepage',
            };
        });

        return view('marketer.promote.index', compact('grouped'));
    }

    public function show(string $slot): View
    {
        $slot = $this->baseQuery()->with(['placementDefinition', 'pageBlock.page', 'country'])->findOrFail($slot);

        return view('marketer.promote.show', compact('slot'));
    }

    public function calendar(Request $request, string $slot): JsonResponse
    {
        $slot = $this->baseQuery()->findOrFail($slot);
        $month = $request->query('month') ? Carbon::parse($request->query('month').'-01') : now();

        return response()->json(['success' => true, 'data' => $this->availabilityService->calendar($slot, $month)]);
    }

    public function quote(Request $request, string $slot): JsonResponse
    {
        $slot = $this->baseQuery()->findOrFail($slot);

        $data = $request->validate([
            'booked_from' => ['required', 'date'],
            'booked_until' => ['required', 'date', 'after_or_equal:booked_from'],
            'budget' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $quote = $this->quoteService->quote(
                $slot,
                Carbon::parse($data['booked_from']),
                Carbon::parse($data['booked_until']),
                $data['budget'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $quote]);
    }

    /**
     * Destinations available to this marketer: their own profile page, and
     * campaigns they have an accepted invitation for. Mirrors the mobile API's
     * Api\Marketer\AdSlotController::destinations() logic (web/API use separate
     * auth guards, so this cannot simply delegate to it).
     */
    public function destinations(Request $request): JsonResponse
    {
        $marketer = $this->marketer();
        $type = $request->query('type');

        if ($type === 'marketer_profile') {
            $profile = MarketerProfile::where('marketer_id', $marketer->id)->first();

            if (! $profile?->profile_slug) {
                return response()->json(['success' => true, 'data' => [], 'message' => 'Profile page is not set up yet.']);
            }

            return response()->json(['success' => true, 'data' => [[
                'id' => $marketer->id,
                'label' => $profile->profile_slug,
                'url' => '/marketer/'.$profile->profile_slug,
            ]]]);
        }

        if ($type === 'campaign') {
            $invitations = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
                ->where('status', 'accepted')
                ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['active', 'auto_approved']))
                ->with('campaign')
                ->get();

            $items = $invitations->map(fn (MarketerCampaignInvitation $invitation) => [
                'id' => $invitation->campaign->id,
                'label' => $invitation->campaign->getPromotedTitle(),
                'referral_code' => $invitation->referral_code,
                'available' => true,
                'reason' => null,
            ]);

            return response()->json(['success' => true, 'data' => $items->values()]);
        }

        return response()->json(['success' => true, 'data' => []]);
    }

    public function walletBalance(): JsonResponse
    {
        $marketer = $this->marketer();
        $marketer->loadMissing('country');
        $currency = $marketer->country?->currency_code;

        if (! $currency) {
            return response()->json(['success' => false, 'message' => 'Wallet currency is not set for this account.'], 422);
        }

        $wallet = app(WalletService::class)->getOrCreateWallet('marketer', $marketer->id, $currency);

        return response()->json(['success' => true, 'data' => ['balance' => $wallet->balance, 'currency' => $wallet->currency]]);
    }
}
