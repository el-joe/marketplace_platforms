<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\VendorPageVendorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Models\MarketerListing;
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
     * GET /vendors
     * Public store directory: paginated, searchable list of active vendors.
     */
    public function index(Request $request): JsonResponse
    {
        $country = $request->attributes->get('country');

        $vendors = Vendor::where('global_status', 'active')
            ->when($country, fn ($q) => $q->where('country_id', $country->id))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('store_name', 'LIKE', "%{$request->search}%")
                    ->orWhere('store_description', 'LIKE', "%{$request->search}%");
            }))
            ->with(['country:id,name_en,name_ar'])
            ->orderByDesc('store_rating_avg')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success([
            'items' => VendorPageVendorResource::collection($vendors->getCollection())->resolve(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'per_page' => $vendors->perPage(),
                'total' => $vendors->total(),
            ],
        ]);
    }

    /**
     * GET /vendors/{vendor_id}
     * Vendor storefront page: vendor metadata, page_builder, and live listing grid.
     */
    public function show(Request $request,$country, string $vendorId): JsonResponse
    {
        $country = $request->attributes->get('country');
        $vendor = Vendor::where('id', $vendorId)
            ->where('global_status', 'active')
            ->with([
                'country:id,name_en,name_ar',
                'businessAddress:id,area,street_address,city_id',
                'businessAddress.city:id,name_en,name_ar',
            ])
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

        // Include this vendor's marketer-promoted variants so the buy-box dedup can
        // pick the best listing per variant (vendor listings still win by priority).
        $variantIds = $paginator->getCollection()->pluck('product_variant_id')->unique()->values()->all();

        $marketerListings = MarketerListing::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->with([
                'productVariant.product.images',
                'productVariant.product.category:id,name_en,name_ar,slug',
                'marketer:id,name,marketer_type',
                'marketer.marketerProfile:id,marketer_id,profile_slug',
            ])
            ->get();

        $deduped = $this->listings->dedupByVariant(
            $paginator->getCollection()->concat($marketerListings)->all()
        );

        $items = collect($deduped)->map(function ($listing) use ($country, $wishlistListingIds) {
            $product = $listing->productVariant->product;

            return $this->listings->toMixedCardShape(
                $listing,
                $product,
                $country,
                in_array($listing->id, $wishlistListingIds),
            );
        })->values()->toArray();

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
