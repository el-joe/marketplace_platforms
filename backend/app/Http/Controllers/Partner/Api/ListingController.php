<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\VendorListingResource;
use App\Http\Responses\ApiResponse;
use App\Models\VendorListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $query = VendorListing::where('vendor_id', $vendorId)
            ->with(['productVariant.product.images', 'country', 'primaryShippingMethod'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('productVariant.product', function ($pq) use ($request) {
                $pq->where('name_en', 'like', "%{$request->search}%")
                   ->orWhere('name_ar', 'like', "%{$request->search}%")
                   ->orWhere('model_number', 'like', "%{$request->search}%");
            }))
            ->latest();

        return ApiResponse::paginated($query->paginate((int) ($request->per_page ?? 20)), VendorListingResource::class);
    }

    public function show(string $id): JsonResponse
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
}
