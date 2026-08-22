<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\CreateListingRequest;
use App\Http\Requests\Vendor\ListingIndexRequest;
use App\Http\Requests\Vendor\UpdateListingDeliveryCoverageRequest;
use App\Http\Requests\Vendor\UpdateListingPriceRequest;
use App\Http\Requests\Vendor\UpdateListingShippingRequest;
use App\Http\Requests\Vendor\UpdateListingStatusRequest;
use App\Http\Resources\Vendor\VendorListingResource;
use App\Http\Responses\ApiResponse;
use App\Models\VendorListing;
use App\Services\ListingShippingResolver;
use App\Services\Vendor\ListingService;
use Illuminate\Support\Facades\Gate;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService $listingService,
        private readonly ListingShippingResolver $shippingResolver,
    ) {
    }

    public function index(ListingIndexRequest $request): \Illuminate\Http\JsonResponse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        $query = VendorListing::where('vendor_id', $vendorId)
            ->with(['productVariant.product.images', 'country', 'primaryShippingMethod'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->whereHas('productVariant.product', function ($pq) use ($request) {
                $pq->where('name_en', 'like', "%{$request->search}%")
                    ->orWhere('name_ar', 'like', "%{$request->search}%")
                    ->orWhere('short_desc_en', 'like', "%{$request->search}%")
                    ->orWhere('model_number', 'like', "%{$request->search}%");
            }))
            ->latest();

        $listings = $query->paginate((int) ($request->per_page ?? 20));

        return ApiResponse::paginated($listings, VendorListingResource::class);
    }

    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        $listing = VendorListing::with([
            'productVariant.product.images',
            'productVariant.variantAttributes',
            'country',
            'primaryShippingMethod',
        ])->findOrFail($id);

        Gate::authorize('view', $listing);

        return ApiResponse::success(new VendorListingResource($listing));
    }

    public function store(CreateListingRequest $request): \Illuminate\Http\JsonResponse
    {
        $vendor = auth('vendor')->user()->vendor;
        $listing = $this->listingService->create($request->validated(), $vendor);

        return ApiResponse::success(
            new VendorListingResource($listing->load(['productVariant.product', 'country', 'primaryShippingMethod'])),
            'Listing submitted for review.',
            201
        );
    }

    public function updatePrice(UpdateListingPriceRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        $listing = VendorListing::findOrFail($id);

        Gate::authorize('updatePrice', $listing);

        if (!auth('vendor')->user()->can('listings.pricing.edit')) {
            return ApiResponse::error('You do not have permission to edit listing pricing.', [], 403);
        }

        $updated = $this->listingService->updatePrice($listing, $request->validated());

        return ApiResponse::success(new VendorListingResource($updated), 'Price updated.');
    }

    public function updateStatus(UpdateListingStatusRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        $listing = VendorListing::findOrFail($id);

        Gate::authorize('updateStatus', $listing);

        if (!auth('vendor')->user()->can('listings.publish')) {
            return ApiResponse::error('You do not have permission to publish or unpublish listings.', [], 403);
        }

        if (!in_array($listing->status?->value, ['active', 'paused'])) {
            return ApiResponse::error('Only active or paused listings can have their status changed by the vendor.', [], 422);
        }

        $updated = $this->listingService->updateStatus($listing, $request->status);

        return ApiResponse::success(new VendorListingResource($updated), 'Status updated.');
    }

    public function availableShippingMethods(string $id): \Illuminate\Http\JsonResponse
    {
        $listing = VendorListing::with('productVariant.product.category')->findOrFail($id);

        Gate::authorize('view', $listing);

        $methods = $this->shippingResolver->resolveForListing($listing)
            ->map(fn($method) => ['id' => $method->id, 'name' => $method->name])
            ->values();

        return ApiResponse::success($methods);
    }

    public function updateShipping(UpdateListingShippingRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        $listing = VendorListing::findOrFail($id);

        Gate::authorize('updateShipping', $listing);

        $updated = $this->listingService->updateShippingMethod($listing, $request->primary_shipping_method_id);

        return ApiResponse::success(new VendorListingResource($updated), 'Shipping method updated.');
    }

    public function updateDeliveryCoverage(UpdateListingDeliveryCoverageRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        $listing = VendorListing::findOrFail($id);

        Gate::authorize('updateDeliveryCoverage', $listing);

        $updated = $this->listingService->updateDeliveryCoverage($listing, $request->boolean('vendor_covers_delivery'));

        return ApiResponse::success(new VendorListingResource($updated), 'Delivery coverage setting updated.');
    }

    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        $listing = VendorListing::findOrFail($id);

        Gate::authorize('delete', $listing);

        $this->listingService->delete($listing);

        return ApiResponse::success(null, 'Listing archived.');
    }
}
