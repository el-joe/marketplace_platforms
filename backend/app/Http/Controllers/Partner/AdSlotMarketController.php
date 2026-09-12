<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PaidAdSlot;
use App\Models\Vendor;
use App\Services\Ads\AdSlotAvailabilityService;
use App\Services\Ads\AdSlotQuoteService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdSlotMarketController extends Controller
{
    public function __construct(
        private readonly AdSlotQuoteService $quoteService,
        private readonly AdSlotAvailabilityService $availabilityService,
    ) {
    }

    private function vendor(): Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    private function baseQuery()
    {
        $vendor = $this->vendor();

        return PaidAdSlot::bookable()
            ->where('country_id', $vendor->country_id)
            ->whereIn('allowed_advertisers', ['vendor', 'both']);
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
            if ($slot->target_type === \App\Enums\PaidAdSlotTargetType::ListingPromotion) {
                return 'promotions';
            }
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

        return view('partner.ad-slots.index', compact('grouped'));
    }

    public function show(string $slot): View
    {
        $slot = $this->baseQuery()->with(['placementDefinition', 'pageBlock.page', 'country'])->findOrFail($slot);

        return view('partner.ad-slots.show', compact('slot'));
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

    public function destinations(Request $request): JsonResponse
    {
        $controller = app(\App\Http\Controllers\Vendor\AdSlotController::class);

        // Reuses the same vendor-scoped destination lookup as the mobile API.
        return $controller->destinations($request);
    }
}
