<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\BrandResource;
use App\Models\Brand;
use App\Models\Country;
use App\Models\VendorListing;
use App\Services\Customer\ListingQueryService;
use App\Services\Shared\PageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandPageController extends Controller
{
    public function __construct(
        private readonly ListingQueryService $listings,
        private readonly PageBuilderService $pageBuilder,
    ) {}

    /**
     * GET /brands/{slug}
     * Brand page: brand metadata, page_builder, and live listing grid.
     */
    public function show(Request $request, $country, string $id): JsonResponse
    {
        $country = $request->attributes->get("country");

        $brand = Brand::where('id', $id)
            ->where('is_active', true)
            ->firstOrFail();

        $pageBuilder = $this->pageBuilder->resolve(
            $country,
            'brand',
            $brand->id,
            $this->pageBuilder->detectDevice($request),
            auth('customer')->check() ? 'authenticated' : 'guest',
        );

        $paginator = VendorListing::where('country_id', $country->id)
            ->where('status', 'active')
            ->whereHas(
                'productVariant.product',
                fn ($q) => $q->where('brand_id', $brand->id)->where('status', 'active'),
            )
            ->whereHas('vendor', fn ($q) => $q->where('global_status', 'active'))
            ->with([
                'productVariant.product.images',
                'productVariant.product.category:id,name_en,name_ar,slug',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,min_delivery_days,max_delivery_days',
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

        return response()->json([
            'success' => true,
            'data' => [
                'brand' => new BrandResource($brand),
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
            ],
        ]);
    }
}
