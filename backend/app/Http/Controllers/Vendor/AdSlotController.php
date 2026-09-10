<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\PaidAdAdvertiserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Ads\QuoteRequest;
use App\Http\Resources\Vendor\Ads\AdSlotResource;
use App\Http\Responses\ApiResponse;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ClassifiedListing;
use App\Models\PaidAdSlot;
use App\Models\Vendor;
use App\Models\VendorListing;
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

    private function vendor(): Vendor
    {
        return auth('vendor')->user()->vendor;
    }

    private function baseQuery()
    {
        $vendor = $this->vendor();

        return PaidAdSlot::bookable()
            ->where('country_id', $vendor->country_id)
            ->whereIn('allowed_advertisers', ['vendor', 'both']);
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

    public function destinations(Request $request): JsonResponse
    {
        $vendor = $this->vendor();
        $type = $request->query('type');
        $q = $request->query('q');

        $items = match ($type) {
            'listing' => VendorListing::where('vendor_id', $vendor->id)
                ->where('status', 'active')
                ->with('productVariant.product')
                ->when($q, fn ($qu) => $qu->whereHas('productVariant.product', fn ($p) => $p->where('name_en', 'like', "%{$q}%")->orWhere('name_ar', 'like', "%{$q}%")))
                ->limit(20)->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'label' => $l->productVariant?->product?->name_en ?? $l->id,
                ]),
            'classified_listing' => ClassifiedListing::where('seller_type', Vendor::class)
                ->where('seller_id', $vendor->id)
                ->where('status', 'active')
                ->when($q, fn ($qu) => $qu->where('title_en', 'like', "%{$q}%")->orWhere('title_ar', 'like', "%{$q}%"))
                ->limit(20)->get()
                ->map(fn ($l) => ['id' => $l->id, 'label' => $l->title_en]),
            'brand' => Brand::whereHas('products.variants.vendorListings', fn ($qu) => $qu->where('vendor_id', $vendor->id)->where('status', 'active'))
                ->when($q, fn ($qu) => $qu->where('name_en', 'like', "%{$q}%"))
                ->limit(20)->get()
                ->map(fn ($b) => ['id' => $b->id, 'label' => $b->name_en]),
            'category' => Category::where('is_active', true)
                ->where('country_id', $vendor->country_id)
                ->when($q, fn ($qu) => $qu->where('name_en', 'like', "%{$q}%"))
                ->limit(20)->get()
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name_en]),
            'store' => collect([['id' => $vendor->id, 'label' => $vendor->store_name]]),
            default => collect(),
        };

        // Classified vendors advertise classified listings, not product listings.
        if ($type === 'listing' && $vendor->isClassifiedVendor()) {
            $items = collect();
        }

        return ApiResponse::success($items->values());
    }
}
