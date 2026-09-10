<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Models\MarketerListing;
use App\Models\MarketerProfile;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\MarketerProfileCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MarketerProfileController extends Controller
{
    public function __construct(
        private readonly ListingQueryService $listings,
    ) {}

    /**
     * GET /api/public/v1/marketers
     * Paginated list of active marketers with a public profile.
     */
    public function index(Request $request): JsonResponse
    {
        $countryId = $request->attributes->get('country')?->id;

        $profiles = MarketerProfile::query()
            ->whereNotNull('profile_slug')
            ->whereHas('marketer', fn ($q) => $q->where('global_status', 'active'))
            ->with([
                'marketer:id,name,marketer_type,country_id,total_campaigns,total_conversions',
                'bannerFile',
            ])
            ->when($request->type, fn ($q) => $q->whereHas('marketer', fn ($s) => $s->where('marketer_type', $request->type)))
            ->when($countryId, fn ($q) => $q->whereHas('marketer', fn ($s) => $s->where('country_id', $countryId)))
            ->orderByDesc('total_conversions')
            ->paginate((int) $request->query('per_page', 24));

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        $items = $profiles->getCollection()->map(function (MarketerProfile $profile) use ($frontendUrl) {
            $marketer = $profile->marketer;

            return [
                'id'                => $marketer->id,
                'name'              => $marketer->name,
                'marketer_type'     => $marketer->marketer_type,
                'profile_slug'      => $profile->profile_slug,
                'profile_url'       => $frontendUrl . '/marketer/' . $profile->profile_slug,
                'banner_url'        => $profile->bannerFile?->url,
                'total_campaigns'   => $marketer->total_campaigns,
                'total_conversions' => $marketer->total_conversions,
                'avatar_initial'    => mb_substr($marketer->name, 0, 1),
            ];
        })->values()->all();

        return ApiResponse::success([
            'items' => $items,
            'meta'  => [
                'current_page' => $profiles->currentPage(),
                'last_page'    => $profiles->lastPage(),
                'per_page'     => $profiles->perPage(),
                'total'        => $profiles->total(),
            ],
        ]);
    }

    /**
     * GET /api/public/v1/marketers/{slug}
     * Public marketer profile page data. Cached for 5 minutes — public
     * pages don't need real-time freshness, and this keeps response
     * times well under 1s under load.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $countryId = $request->attributes->get('country')?->id ?? 'global';
        $cacheKey  = MarketerProfileCache::key($slug, $countryId);

        $cached = Cache::remember($cacheKey, 300, fn () => $this->buildProfileResponse($request, $slug));

        if ($cached === null) {
            return ApiResponse::error('Marketer not found.', [], 404);
        }

        return ApiResponse::success($cached);
    }

    private function buildProfileResponse(Request $request, string $slug): ?array
    {
        $profile = MarketerProfile::where('profile_slug', $slug)
            ->with([
                'marketer:id,name,marketer_type,country_id,total_campaigns,total_conversions',
                'marketer.country:id,name_en,name_ar,currency_code',
                'bannerFile',
            ])
            ->first();

        if (!$profile || !$profile->marketer) {
            return null;
        }

        $marketer = $profile->marketer;

        $country = $request->attributes->get('country')
            ?? Country::find($marketer->country_id)
            ?? Country::where('is_active', true)->first();

        if (!$country) {
            return null;
        }

        $marketerListings = MarketerListing::query()
            ->where('marketer_id', $marketer->id)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->with([
                'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
                'productVariant.images',
                'productVariant.product:id,name_en,name_ar,slug,category_id,brand_id',
                'productVariant.product.images',
                'productVariant.product.category:id,name_en,name_ar,slug',
                'productVariant.product.brand:id,name_en,name_ar,slug,logo_url',
            ])
            ->orderByDesc('total_sold')
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        // Dedup by variant (in case a marketer has both a campaign-linked and an
        // independent listing for the same variant).
        $deduped = $this->listings->dedupByVariant($marketerListings->all());

        $productCards = collect($deduped)->map(function (MarketerListing $listing) use ($country) {
            $variant = $listing->productVariant;
            $product = $variant->product;

            return [
                'listing_id'       => $listing->id,
                'listing_type'     => 'marketer',
                'product_id'       => $product->id,
                'product_slug'     => $product->slug,
                'variant_id'       => $variant->id,
                'variant_slug'     => $variant->slug,
                'variant_name'     => $variant->variant_name ?? $variant->sku,
                'sku'              => $variant->sku,
                'name_en'          => $product->name_en,
                'name_ar'          => $product->name_ar,
                'primary_image'    => $variant->images->first()?->url ?? $product->images->first()?->url,
                'images'           => $variant->images->map(fn ($i) => $i->url)->values()->all(),
                'category_name'    => ['en' => $product->category?->name_en, 'ar' => $product->category?->name_ar],
                'brand'            => $product->brand ? [
                    'id'       => $product->brand->id,
                    'name'     => ['en' => $product->brand->name_en, 'ar' => $product->brand->name_ar],
                    'logo_url' => $product->brand->logo_url,
                ] : null,
                'price'            => $listing->price,
                'price_formatted'  => number_format($listing->price, 2),
                'compare_at_price' => $listing->compare_at_price,
                'currency'         => $country->currency_code,
                'condition'        => $listing->condition,
                'referral_code'    => $listing->referral_code,
                'referral_link'    => $listing->referral_link,
                'total_sold'       => $listing->total_sold,
                'rating_avg'       => $listing->rating_avg,
                'rating_count'     => $listing->rating_count,
                'url_param'        => $variant->id . '--' . $listing->id,
                'product_url'      => route('customer.listing.show', [
                    $country->site_code,
                    $variant->id . '--' . $listing->id,
                ]),
            ];
        })->values()->all();

        $qrUrl = $profile->qr_code_path
            ? Storage::disk('public')->url($profile->qr_code_path)
            : null;

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return [
            'marketer' => [
                'id'                => $marketer->id,
                'name'              => $marketer->name,
                'marketer_type'     => $marketer->marketer_type,
                'country'           => $marketer->country ? [
                    'name_en' => $marketer->country->name_en,
                    'name_ar' => $marketer->country->name_ar,
                ] : null,
                'total_campaigns'   => $marketer->total_campaigns,
                'total_conversions' => $marketer->total_conversions,
            ],
            'profile' => [
                'slug'            => $profile->profile_slug,
                'bio_ar'          => $profile->bio_ar,
                'bio_en'          => $profile->bio_en,
                'video_url'       => $profile->video_url,
                'social_links'    => $profile->social_links ?? [],
                'contact_details' => $profile->contact_details ?? [],
                'banner_url'      => $profile->bannerFile?->url,
                'qr_code_url'     => $qrUrl,
                'profile_url'     => $frontendUrl . '/marketer/' . $profile->profile_slug,
            ],
            'listings' => [
                'items' => $productCards,
                'total' => count($productCards),
            ],
        ];
    }
}
