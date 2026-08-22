<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Classified\CreateInquiryRequest;
use App\Http\Requests\Customer\Travel\CreateBookingRequest;
use App\Http\Requests\Customer\Travel\SignContractRequest;
use App\Http\Resources\Customer\ClassifiedListingDetailResource;
use App\Http\Resources\Customer\ListingInquiryResource;
use App\Http\Resources\Customer\TravelBookingSubmittedResource;
use App\Http\Resources\Customer\TravelContractResource;
use App\Http\Resources\Customer\TravelPackageDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Services\Customer\ClassifiedDetailService;
use App\Services\Customer\ClassifiedInquiryService;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\TravelBookingService;
use App\Services\Customer\TravelPackageDetailService;
use App\Services\Customer\BuyBoxService;
use App\Services\Customer\ProductViewService;
use App\Services\Customer\ProductDetailEnrichmentService;
use App\Services\Customer\ReviewService;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Http\Resources\Customer\ProductDetailResource;
use App\Support\SafeCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ListingController extends Controller
{
    public function __construct(
        private readonly ClassifiedDetailService $classifiedDetail,
        private readonly TravelPackageDetailService $travelDetail,
        private readonly ClassifiedInquiryService $inquiryService,
        private readonly TravelBookingService $bookingService,
        private readonly BuyBoxService $buyBox,
        private readonly ProductViewService $viewService,
        private readonly ProductDetailEnrichmentService $enrichment,
        private readonly ReviewService $reviewService,
        private readonly ListingQueryService $listings,
    ) {}

    public function show(Request $request,$country, string $type, string $slug): JsonResponse
    {
        $country = $request->attributes->get("country");

        return match ($type) {
            'product'    => $this->showProduct($request, $country, $slug),
            'classified' => $this->showClassified($request, $country, $slug),
            'travel'     => $this->showTravel($request, $country, $slug),
            default      => throw new NotFoundHttpException(__('common.exceptions.listing.unknown_type')),
        };
    }

    public function createInquiry(
        CreateInquiryRequest $request,
        $country,
        string $slug,
    ): JsonResponse {
        $country = $request->attributes->get('country');
        $listing = $this->classifiedDetail->findActive($slug, $country);

        abort_if(! $listing, 404, __('common.exceptions.listing.not_found'));

        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();
        $inquiry  = $this->inquiryService->create($listing, $customer, $request->validated());

        $resource = new ListingInquiryResource($inquiry);
        $resource->listingSlug = $slug;

        return ApiResponse::success($resource, __('common.exceptions.listing.inquiry_submitted'), 201);
    }

    public function createBooking(
        CreateBookingRequest $request,
        $_country,
        string $slug,
    ): JsonResponse {
        $_country = $request->attributes->get('country');
        $package = $this->travelDetail->findActive($slug);

        if (! $package) {
            abort(404, __('common.exceptions.listing.travel_package_not_found_expired'));
        }

        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();
        $booking  = $this->bookingService->book($package, $customer, $request->validated());

        $resource = new TravelBookingSubmittedResource($booking);
        $resource->currency = $package->currency;
        $resource->pendingMessage = __('common.exceptions.listing.booking_pending_review');

        return ApiResponse::success($resource, __('common.exceptions.listing.booking_submitted'), 201);
    }

    public function signContract(
        SignContractRequest $request,
        $_country,
        string $slug,
        string $bookingNumber,
    ): JsonResponse {
        $_country = $request->attributes->get('country');
        // Verify the package still exists (even if expired — contract signing can happen post-departure)
        $packageExists = \App\Models\TravelPackage::where('slug', $slug)->exists();
        abort_if(! $packageExists, 404, __('common.exceptions.listing.travel_package_not_found'));

        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();
        $booking  = $this->bookingService->signContract($customer, $bookingNumber, $request->validated()['signature_data']);

        return ApiResponse::success(new TravelContractResource($booking), __('common.exceptions.listing.contract_signed'));
    }

    /**
     * GET /listings/classified/{slug}/similar
     * Returns up to 12 active listings in the same category and country,
     * excluding the current listing, ordered by most recent.
     */
    public function similarClassified(Request $request, $country, string $slug): JsonResponse
    {
        $country = $request->attributes->get('country');

        $listing = ClassifiedListing::where('slug', $slug)
            ->where('status', 'active')
            ->where('country_id', $country->id)
            ->select(['id', 'classified_category_id', 'country_id'])
            ->first();

        if (! $listing) {
            return ApiResponse::success(['items' => []]);
        }

        $items = SafeCache::remember(
            "similar_classified:{$listing->id}",
            300,
            fn () => ClassifiedListing::where('status', 'active')
                ->where('classified_category_id', $listing->classified_category_id)
                ->where('country_id', $listing->country_id)
                ->where('id', '!=', $listing->id)
                ->with([
                    'images' => fn ($q) => $q->orderBy('position')->limit(1),
                    'city:id,name_en,name_ar',
                ])
                ->orderByDesc('created_at')
                ->limit(12)
                ->get()
                ->map(fn ($l) => $this->listings->toClassifiedCardShape($l))
                ->values()
                ->all()
        );

        return ApiResponse::success(['items' => $items]);
    }

    // ── Private branch methods ────────────────────────────────────────────────

    private function showProduct(Request $request, $country, string $slug): JsonResponse
    {
        $country = $request->attributes->get('country');

        $product = Product::where('slug', $slug)
            ->where('status', 'active')
            // ->whereHas('countrySettings', fn ($q) => $q
            //     ->where('country_id', $country->id)
            //     ->where('is_available', true)
            // )
            ->with([
                'brand',
                'category',
                'images',
                'highlights',
                'specifications',
                'variants.variantAttributes.attribute',
                'variants.variantAttributes.attributeValue',
                'countrySettings' => fn ($q) => $q->where('country_id', $country->id),
            ])
            ->firstOrFail();

        $listings = $this->buyBox->getListings($product, $country);
        $product->setRelation('activeListings', $listings);

        $adminListing = \App\Models\AdminListing::where('country_id', $country->id)
            ->where('status', 'active')
            ->whereHas('productVariant', fn ($q) => $q->where('product_id', $product->id))
            ->with([
                'productVariant.product.images',
                'productVariant.product.category',
                'productVariant.product.brand',
                'productVariant.product.highlights',
                'productVariant.product.specifications',
                'productVariant.variantAttributes.attribute',
                'productVariant.variantAttributes.attributeValue',
                'primaryShippingMethod',
            ])
            ->orderBy('price')
            ->first();

        // Admin listing wins the buy-box; redirect detail to it.
        if ($adminListing) {
            return app(\App\Http\Controllers\Customer\ListingDetailController::class)
                ->showFromListing($request, $country, $adminListing);
        }

        $reviews = $product->reviews()
            ->where('status', 'published')
            ->with([
                'vendorReply',
                'customer:id,name',
                'files',
                'vendorListing.vendor:id,store_name',
                'vendorListing.productVariant.variantAttributes.attribute',
                'vendorListing.productVariant.variantAttributes.attributeValue',
            ])
            ->orderByDesc('helpful_count')
            ->limit(5)
            ->get();
        $product->setRelation('topReviews', $reviews);

        $buyBoxPrice = $listings->first()?->price;
        $related     = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->whereHas('countrySettings', fn ($q) => $q
                ->where('country_id', $country->id)
                ->where('is_available', true)
            )
            ->with('images')
            ->when($buyBoxPrice, fn ($q) => $q->whereHas('variants.vendorListings', fn ($q2) => $q2
                ->where('country_id', $country->id)
                ->where('status', 'active')
                ->whereBetween('price', [(int) ($buyBoxPrice * 0.7), (int) ($buyBoxPrice * 1.3)])
            ))
            ->orderByRating()
            ->limit(8)
            ->get();
        $product->setRelation('related', $related);

        $isWishlisted = false;
        if (($customerId = auth('customer')->id()) && ($buyBoxListing = $listings->first())) {
            $isWishlisted = WishlistItem::where('customer_id', $customerId)
                ->where('vendor_listing_id', $buyBoxListing->id)
                ->exists();
        }

        $this->viewService->logView(
            product: $product,
            country: $country,
            customerId: auth('customer')->id(),
            sessionId: $request->hasSession() ? $request->session()->getId() : '',
            source: $request->query('source', 'direct'),
            referrerUrl: $request->header('Referer'),
        );

        $buyBoxListing = $listings->first();
        $customer      = auth('customer')->user();

        $resource                  = new ProductDetailResource($product);
        $resource->isWishlisted    = $isWishlisted;
        $resource->ratingBreakdown = $this->reviewService->ratingBreakdown($product);
        $resource->enrichment      = [
            'best_seller_badge' => $this->enrichment->getBestSellerBadge($product, $country),
            'delivery_options'  => $this->enrichment->getDeliveryOptions(
                $product,
                $country,
                $request->query('address_id'),
                $buyBoxListing,
            ),
            'coupons' => $this->enrichment->getApplicableCoupons(
                $product,
                $country,
                $customer,
                $buyBoxListing,
            ),
            'payment_options' => $this->enrichment->getPaymentOptions(
                $country,
                (int) ($buyBoxListing?->price ?? 0),
                $customer,
            ),
        ];

        return ApiResponse::success($resource->toArray($request));
    }

    private function showClassified(Request $request, $country, string $listingNumber): JsonResponse
    {
        $country = $request->attributes->get('country');

        $listing = $this->classifiedDetail->findActive($listingNumber, $country);

        abort_if(! $listing, 404, __('common.exceptions.listing.not_found'));

        $this->classifiedDetail->incrementViews($listing);

        $resource             = new ClassifiedListingDetailResource($listing);
        $resource->sellerInfo = $this->classifiedDetail->sellerInfo($listing);

        return ApiResponse::success($resource->toArray($request));
    }

    private function showTravel(Request $request, Country $_country, string $id): JsonResponse
    {
        $package = $this->travelDetail->findActive($id);

        abort_if(! $package, 404, __('common.exceptions.listing.travel_package_not_found_expired'));

        return ApiResponse::success((new TravelPackageDetailResource($package))->toArray($request));
    }
}
