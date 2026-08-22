<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\AdminListingStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\AdminListingResource;
use App\Http\Resources\Api\Customer\VendorListingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Address;
use App\Models\AdminListing;
use App\Models\Country;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\VendorListing;
use App\Services\AppContextService;
use App\Services\Customer\ListingIdentifierService;
use App\Services\Customer\ProductDetailEnrichmentService;
use App\Services\SavingsBenefitsService;
use App\Services\ShippingCalculationService;
use App\Services\WarrantyPlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingIdentifierService $identifiers,
        private readonly ShippingCalculationService $shipping,
        private readonly WarrantyPlanService $warrantyPlanService,
        private readonly SavingsBenefitsService $savingsBenefitsService,
        private readonly AppContextService $appContext,
        private readonly ProductDetailEnrichmentService $enrichment,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $isNawyNow = $this->appContext->isNawyNow();
        $country = $this->resolveCountry($request);

        if (!$country) {
            return ApiResponse::error(__('customer_api.listing.country_not_found'), [], 404);
        }

        $perPage = (int) $request->query('per_page', 20);

        $paginator = $isNawyNow
            ? $this->buildAdminQuery($request, $country)->paginate($perPage)
            : $this->buildVendorQuery($request, $country)->paginate($perPage);

        $items = $isNawyNow
            ? $paginator->getCollection()->map(fn (AdminListing $listing) => $this->adminListingShape($listing, $country))->values()->all()
            : $paginator->getCollection()->map(fn (VendorListing $listing) => $this->vendorListingShape($listing, $country))->values()->all();

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function show(Request $request, string $identifier): JsonResponse
    {
        $isNawyNow = $this->appContext->isNawyNow();
        $country = $this->resolveCountry($request);

        if (!$country) {
            return ApiResponse::error(__('customer_api.listing.country_not_found'), [], 404);
        }

        if ($isNawyNow) {
            $listing = $this->resolveAdminListing($identifier, $country);

            if (!$listing) {
                return ApiResponse::error(__('customer_api.listing.not_found'), [], 404);
            }

            return ApiResponse::success([
                'listing' => $this->adminListingShape($listing, $country),
                'delivery_info' => $this->adminDeliveryInfo($listing),
                'savings_and_benefits' => $this->savingsBenefitsService->get(
                    (int) $listing->price,
                    $country->id,
                    $listing->currency,
                ),
            ]);
        }

        $type = $this->identifiers->detectType($identifier);
        $listing = $this->identifiers->resolve($identifier, $type, $country);

        if (!$listing) {
            return ApiResponse::error(__('customer_api.listing.not_found'), [], 404);
        }

        $address = $this->resolveCustomerDefaultAddress($request);

        return ApiResponse::success([
            'listing' => $this->vendorListingShape($listing, $country),
            'delivery_info' => $this->enrichment->getDeliveryOptions(
                $listing->productVariant->product,
                $country,
                $request->query('address_id') ?? $address?->id,
                $listing,
            ),
            'warranty_plans' => $this->warrantyPlansFor($listing, $country),
            'savings_and_benefits' => $this->savingsBenefitsService->get(
                (int) $listing->price,
                $country->id,
                $listing->currency,
            ),
        ]);
    }

    public function shippingEstimate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'address_id' => ['required', 'uuid'],
            'shipping_method_id' => ['required', 'uuid'],
        ]);

        $country = $this->resolveCountry($request);

        if (!$country) {
            return ApiResponse::error(__('customer_api.listing.country_not_found'), [], 404);
        }

        $listing = VendorListing::where('id', $id)
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereNull('deleted_at')
            ->with(['vendor', 'productVariant'])
            ->first();

        if (!$listing) {
            return ApiResponse::error(__('customer_api.listing.not_found'), [], 404);
        }

        $address = Address::with('city')->find($request->input('address_id'));
        $zone = $address?->city?->shipping_zone_id
            ? ShippingZone::find($address->city->shipping_zone_id)
            : null;

        if (!$zone) {
            return ApiResponse::error(__('customer_api.listing.shipping_zone_unresolvable'), [], 422);
        }

        $method = ShippingMethod::find($request->input('shipping_method_id'));

        if (!$method) {
            return ApiResponse::error(__('customer_api.listing.shipping_method_not_found'), [], 404);
        }

        $weightGrams = $this->effectiveWeightGrams($listing);

        $breakdown = $this->shipping->calculate(
            vendor: $listing->vendor,
            listing: $listing,
            quantity: 1,
            destinationZone: $zone,
            method: $method,
            billableWeightGrams: $weightGrams,
        );

        return ApiResponse::success([
            'fee' => $breakdown->customerPays,
            'cod_fee' => null,
            'is_exceptional' => $breakdown->isExceptional,
            'is_free_shipping' => $breakdown->customerPays === 0,
            'currency' => $country->currency_code,
            'method_details' => [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'delivery_days_min' => $method->min_delivery_days,
                'delivery_days_max' => $method->max_delivery_days,
            ],
        ]);
    }

    // ── Query builders ───────────────────────────────────────────────────────

    private function buildAdminQuery(Request $request, Country $country): Builder
    {
        $query = AdminListing::query()
            ->select('admin_listings.*')
            ->where('admin_listings.country_id', $country->id)
            ->where('admin_listings.status', AdminListingStatus::Active->value)
            ->with([
                'productVariant.images',
                'productVariant.product.images',
                'productVariant.product.category',
            ]);

        if ($categoryId = $request->query('category_id')) {
            $query->whereHas('productVariant.product', fn ($q) => $q->where('category_id', $categoryId));
        }

        if ($minPrice = $request->query('min_price')) {
            $query->where('admin_listings.price', '>=', (int) $minPrice);
        }

        if ($maxPrice = $request->query('max_price')) {
            $query->where('admin_listings.price', '<=', (int) $maxPrice);
        }

        $query->orderBy('admin_listings.price', 'asc');

        return $query;
    }

    private function buildVendorQuery(Request $request, Country $country): Builder
    {
        $query = VendorListing::query()
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereNull('deleted_at')
            ->with([
                'productVariant.images',
                'productVariant.product.images',
                'productVariant.product.category',
            ]);

        if ($categoryId = $request->query('category_id')) {
            $query->whereHas('productVariant.product', fn ($q) => $q->where('category_id', $categoryId));
        }

        if ($condition = $request->query('condition')) {
            $query->where('condition', $condition);
        }

        if ($minPrice = $request->query('min_price')) {
            $query->where('price', '>=', (int) $minPrice);
        }

        if ($maxPrice = $request->query('max_price')) {
            $query->where('price', '<=', (int) $maxPrice);
        }

        $this->applyVendorSort($query, $request->query('sort'));

        return $query;
    }

    private function applyVendorSort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'newest' => $query->orderByDesc('created_at'),
            'rating' => $query->orderByDesc('rating_avg'),
            default => $query->orderByDesc('score')->orderByDesc('buy_box_won_at'),
        };
    }

    // ── Detail resolution ────────────────────────────────────────────────────

    private function resolveAdminListing(string $identifier, Country $country): ?AdminListing
    {
        $query = AdminListing::where('country_id', $country->id)
            ->where('status', AdminListingStatus::Active->value)
            ->with(['productVariant.product.images', 'productVariant.product.category']);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return $query->where('id', $identifier)->first();
        }

        $variant = ProductVariant::where('sku', $identifier)->first();

        if ($variant) {
            return $query->where('product_variant_id', $variant->id)->first();
        }

        return $query->whereHas('productVariant.product', fn ($q) => $q->where('slug', $identifier))->first();
    }

    // ── Delivery info ────────────────────────────────────────────────────────

    private function adminDeliveryInfo(AdminListing $listing): array
    {
        return [
            'shipping_cost' => $listing->shipping_cost,
            'currency' => $listing->currency,
            'fulfillment_type' => $listing->fulfillment_type,
            'payment_options' => $listing->payment_options,
        ];
    }

    private function warrantyPlansFor(VendorListing $listing, Country $country): array
    {
        $product = $listing->productVariant->product;

        if (!$product->category_id) {
            return [];
        }

        $plans = $this->warrantyPlanService->getPlansForProduct($product, $country->id, $country->currency_code);

        return array_values(array_filter($plans, fn (array $plan) => $plan['currency'] === $country->currency_code));
    }

    private function effectiveWeightGrams(VendorListing $listing): int
    {
        $length = $listing->declared_length_cm ?? $listing->productVariant->length_cm ?? 0;
        $width = $listing->declared_width_cm ?? $listing->productVariant->width_cm ?? 0;
        $height = $listing->declared_height_cm ?? $listing->productVariant->height_cm ?? 0;
        $weight = $listing->declared_weight_grams ?? $listing->productVariant->weight_grams ?? 0;

        return $this->shipping->resolveEffectiveWeightGrams(
            (int) $length,
            (int) $width,
            (int) $height,
            (int) $weight,
        );
    }

    // ── Shapes ───────────────────────────────────────────────────────────────

    private function vendorListingShape(VendorListing $listing, Country $country): array
    {
        return (new VendorListingResource($listing, $country))->toArray(request());
    }

    private function adminListingShape(AdminListing $listing, Country $country): array
    {
        return (new AdminListingResource($listing, $country))->toArray(request());
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    private function resolveCountry(Request $request): ?Country
    {
        $country = $request->attributes->get('country');

        if ($country instanceof Country) {
            return $country;
        }

        $customerId = auth('customer')->id();

        if ($customerId) {
            return auth('customer')->user()?->country;
        }

        return null;
    }

    private function resolveCustomerDefaultAddress(Request $request): ?Address
    {
        $customer = auth('customer')->user();

        if (!$customer) {
            return null;
        }

        return Address::with('city')
            ->where('addressable_type', get_class($customer))
            ->where('addressable_id', $customer->id)
            ->where('is_default', true)
            ->first();
    }
}
