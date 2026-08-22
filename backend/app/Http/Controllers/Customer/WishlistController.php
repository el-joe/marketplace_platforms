<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\WishlistStoreRequest;
use App\Http\Resources\Customer\WishlistResource;
use App\Http\Responses\ApiResponse;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    private const LISTING_EAGER_LOADS = [
        'vendorListing.productVariant.product.images',
        'vendorListing.vendor:id,store_name',
        'vendorListing.primaryShippingMethod',
    ];

    public function index($country): JsonResponse
    {
        $items = Wishlist::where('customer_id', auth('customer')->id())
            ->with(self::LISTING_EAGER_LOADS)
            ->latest('added_at')
            ->paginate(20);

        return ApiResponse::paginated($items, WishlistResource::class);
    }

    public function store(WishlistStoreRequest $request, $country): JsonResponse
    {
        $customerId = auth('customer')->id();
        $vendorListingId = $request->validated('vendor_listing_id');

        $existing = Wishlist::where('customer_id', $customerId)
            ->where('vendor_listing_id', $vendorListingId)
            ->first();

        if ($existing) {
            return ApiResponse::error(__('common.exceptions.wishlist.already_in_wishlist'), [], 422);
        }

        $wishlist = Wishlist::create([
            'customer_id'       => $customerId,
            'vendor_listing_id' => $vendorListingId,
        ]);

        $wishlist->load(self::LISTING_EAGER_LOADS);

        return ApiResponse::success(
            (new WishlistResource($wishlist))->toArray($request),
            __('common.exceptions.wishlist.added'),
            201,
        );
    }

    public function destroy($country, string $vendorListingId): JsonResponse
    {
        $deleted = Wishlist::where('customer_id', auth('customer')->id())
            ->where('vendor_listing_id', $vendorListingId)
            ->delete();

        if (!$deleted) {
            return ApiResponse::error(__('common.exceptions.wishlist.not_found'), [], 404);
        }

        return ApiResponse::success(null, __('common.exceptions.wishlist.removed'));
    }
}
