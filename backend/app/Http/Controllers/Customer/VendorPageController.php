<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\VendorPageVendorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\Customer\ListingQueryService;
use App\Services\Shared\PageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorPageController extends Controller
{
    public function __construct(
        private readonly ListingQueryService $listings,
        private readonly PageBuilderService $pageBuilder,
    ) {}

    /**
     * GET /vendors/{vendor_id}
     * Vendor storefront page: vendor metadata, page_builder, and live listing grid.
     */
    public function show(Request $request,$country, string $vendorId): JsonResponse
    {
        $country = $request->attributes->get('country');
        $vendor = Vendor::where('id', $vendorId)
            ->where('global_status', 'active')
            ->with('country:id,name_en,name_ar')
            ->firstOrFail();

        $pageBuilder = $this->pageBuilder->resolve(
            $country,
            'vendor',
            $vendor->id,
            $this->pageBuilder->detectDevice($request),
            auth('customer')->check() ? 'authenticated' : 'guest',
        );

        $paginator = VendorListing::where('vendor_id', $vendor->id)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->with([
                'productVariant.product.images',
                'productVariant.product.category:id,name_en,name_ar,slug',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
                'vendor:id,store_name,store_rating_avg',
            ])
            ->orderByRaw("FIELD(global_system_type,'express_fbn','merchant_fbp','marketplace')")
            ->orderBy('price')
            ->paginate($request->integer('per_page', 20));

        $wishlistListingIds = $this->listings->wishlistListingIds(auth('customer')->id());

        $items = $paginator->getCollection()->map(function ($listing) use ($country, $wishlistListingIds) {
            $product = $listing->productVariant->product;

            return $this->listings->toCardShape(
                $listing,
                $product,
                $country,
                in_array($listing->id, $wishlistListingIds),
            );
        })->toArray();

        return ApiResponse::success([
            'vendor' => (new VendorPageVendorResource($vendor))->toArray($request),
            'page_builder' => $pageBuilder,
            'listings' => [
                'items' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }
}
