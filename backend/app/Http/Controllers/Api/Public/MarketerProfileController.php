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
                'avatarFile',
            ])
            ->addSelect(['marketer_profiles.*', 'ad_price', 'ad_price_currency'])
            ->when($request->type, fn ($q) => $q->whereHas('marketer', fn ($s) => $s->where('marketer_type', $request->type)))
            ->when($countryId, fn ($q) => $q->whereHas('marketer', fn ($s) => $s->where('country_id', $countryId)))
            ->orderByDesc('total_conversions')
            ->paginate((int) $request->query('per_page', 24));

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        $items = $profiles->getCollection()->filter(fn (MarketerProfile $profile) => $profile->marketer !== null)->map(function (MarketerProfile $profile) use ($frontendUrl) {
            $marketer = $profile->marketer;

            return [
                'id'                => $marketer->id,
                'name'              => $marketer->name,
                'marketer_type'     => $marketer->marketer_type,
                'profile_slug'      => $profile->profile_slug,
                'profile_url'       => $frontendUrl . '/marketer/' . $profile->profile_slug,
                'banner_url'        => $profile->bannerFile?->url,
                'avatar_url'        => $profile->avatarFile?->url,
                'total_campaigns'   => $marketer->total_campaigns,
                'total_conversions' => $marketer->total_conversions,
                'avatar_initial'    => mb_substr($marketer->name, 0, 1),
                'ad_price'          => $profile->ad_price,
                'ad_price_currency' => $profile->ad_price_currency,
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
    public function show(Request $request, $countryId, string $slug): JsonResponse
    {
        $countryId = $request->attributes->get('country')?->id ?? 'global';

        $ownPage      = max(1, (int) $request->query('own_page', 1));
        $campaignPage = max(1, (int) $request->query('campaign_page', 1));
        $perPage      = min(24, max(1, (int) $request->query('per_page', 12)));

        $headerKey = MarketerProfileCache::key($slug, $countryId) . ':header';
        $header    = Cache::get($headerKey);

        if ($header === null) {
            $header = $this->buildHeader($request, $slug);

            if ($header === null) {
                return ApiResponse::error('Marketer not found.', [], 404);
            }

            Cache::put($headerKey, $header, 300);
        }

        $country = Country::find($header['_country_id']);

        if (!$country) {
            return ApiResponse::error('Marketer not found.', [], 404);
        }

        $wishlistIds = $this->listings->wishlistListingIds(auth('customer')->id());

        [$ownListings, $campaignListings] = $this->buildListings(
            $header['_marketer_id'],
            $country,
            $ownPage,
            $campaignPage,
            $perPage,
            $wishlistIds,
        );

        $response = $header;
        unset($response['_marketer_id'], $response['_country_id']);
        $response['own_listings']      = $ownListings;
        $response['campaign_listings'] = $campaignListings;

        return ApiResponse::success($response);
    }

    private function buildHeader(Request $request, string $slug): ?array
    {
        $profile = MarketerProfile::where('profile_slug', $slug)
            ->with([
                'marketer:id,name,marketer_type,country_id,total_campaigns,total_conversions',
                'marketer.country:id,name_en,name_ar,currency_code',
                'bannerFile',
                'avatarFile',
                'brokerCategory:id,name_en,name_ar',
                'brokerCity:id,name_en,name_ar',
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

        $measurements = null;
        if ($marketer->isInfluencer()) {
            $measurements = [
                'clothing_size'     => $profile->clothing_size,
                'shirt_size'        => $profile->shirt_size,
                'pants_size'        => $profile->pants_size,
                'dress_size'        => $profile->dress_size,
                'abaya_size'        => $profile->abaya_size,
                'shoe_size'         => $profile->shoe_size,
                'shoe_size_system'  => $profile->shoe_size_system,
                'chest_cm'                  => $profile->chest_cm,
                'waist_cm'                  => $profile->waist_cm,
                'hip_cm'                    => $profile->hip_cm,
                'height_cm'                 => $profile->height_cm,
                'item_length_cm'            => $profile->item_length_cm,
                'sleeve_from_neck_cm'       => $profile->sleeve_from_neck_cm,
                'sleeve_from_shoulder_cm'   => $profile->sleeve_from_shoulder_cm,
                'sleeve_width_cm'           => $profile->sleeve_width_cm,
                'notes'                     => $profile->measurements_notes,
            ];
        }

        $brokerSpecialization = null;
        if ($marketer->isAffiliate()) {
            $brokerSpecialization = [
                'category_id'       => $profile->broker_category_id,
                'category_name_en'  => $profile->brokerCategory?->name_en,
                'category_name_ar'  => $profile->brokerCategory?->name_ar,
                'city_id'           => $profile->broker_city_id,
                'city_name_en'      => $profile->brokerCity?->name_en,
                'city_name_ar'      => $profile->brokerCity?->name_ar,
                'serves_all_cities' => $profile->broker_serves_all_cities,
            ];
        }

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
                'avatar_url'      => $profile->avatarFile?->url,
                'qr_code_url'     => $qrUrl,
                'profile_url'     => $frontendUrl . '/marketer/' . $profile->profile_slug,
                'ad_price'        => $profile->ad_price,
                'ad_price_currency' => $profile->ad_price_currency,
                'measurements'    => $measurements,
                'broker_specialization' => $brokerSpecialization,
            ],
            '_marketer_id' => $marketer->id,
            '_country_id'  => $country->id,
        ];
    }

    /**
     * Fetches the two listing sections (own + campaign), never cached —
     * they change with page params and per-customer wishlist state.
     *
     * @return array{0: array, 1: array}
     */
    private function buildListings(
        string $marketerId,
        Country $country,
        int $ownPage,
        int $campaignPage,
        int $perPage,
        array $wishlistIds,
    ): array {
        $ownOffset      = ($ownPage - 1) * $perPage;
        $campaignOffset = ($campaignPage - 1) * $perPage;

        $eagerLoads = [
            'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
            'productVariant.images',
            'productVariant.product:id,name_en,name_ar,slug,category_id,brand_id',
            'productVariant.product.images',
            'productVariant.product.category:id,name_en,name_ar,slug',
            'productVariant.product.brand:id,name_en,name_ar,slug,logo_media_id',
            'marketer:id,name,marketer_type',
            'marketer.marketerProfile:id,marketer_id,profile_slug',
        ];

        // Section A — own listings (no campaign link)
        $ownBaseQuery = fn () => MarketerListing::query()
            ->where('marketer_id', $marketerId)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->whereNull('invitation_id');

        $ownListings = $ownBaseQuery()
            ->with($eagerLoads)
            ->orderByDesc('total_sold')
            ->orderByDesc('created_at')
            ->skip($ownOffset)
            ->take($perPage)
            ->get();

        $ownTotal = $ownBaseQuery()->count();

        // Section B — campaign-linked listings
        $campaignBaseQuery = fn () => MarketerListing::query()
            ->where('marketer_id', $marketerId)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->whereNotNull('invitation_id');

        $campaignListings = $campaignBaseQuery()
            ->with(array_merge($eagerLoads, [
                'invitation:id,campaign_id,referral_code',
                'invitation.campaign:id,vendor_id,title,status',
                'invitation.campaign.vendor:id,store_name',
            ]))
            ->orderByDesc('total_sold')
            ->orderByDesc('created_at')
            ->skip($campaignOffset)
            ->take($perPage)
            ->get();

        $campaignTotal = $campaignBaseQuery()->count();

        \App\Services\Customer\PromoBadgeResolver::instance()->prime(\App\Services\Customer\PromoBadgeResolver::tuplesForListings(collect($ownListings)->concat($campaignListings)));
        $ownCards = collect($ownListings)->map(function (MarketerListing $listing) use ($country, $wishlistIds) {
            $card = $this->listings->toMarketerCardShape(
                listing: $listing,
                product: $listing->productVariant->product,
                country: $country,
                isWishlisted: in_array($listing->id, $wishlistIds, true),
            );
            $card['campaign'] = null;
            return $card;
        })->values()->all();

        $campaignCards = collect($campaignListings)->map(function (MarketerListing $listing) use ($country, $wishlistIds) {
            $card = $this->listings->toMarketerCardShape(
                listing: $listing,
                product: $listing->productVariant->product,
                country: $country,
                isWishlisted: in_array($listing->id, $wishlistIds, true),
            );
            $card['campaign'] = $listing->invitation?->campaign ? [
                'id'          => $listing->invitation->campaign->id,
                'title'       => $listing->invitation->campaign->title,
                'vendor_name' => $listing->invitation->campaign->vendor?->store_name,
            ] : null;
            return $card;
        })->values()->all();

        $ownLastPage      = (int) max(1, ceil($ownTotal / $perPage));
        $campaignLastPage = (int) max(1, ceil($campaignTotal / $perPage));

        return [
            [
                'items' => $ownCards,
                'meta'  => [
                    'current_page' => $ownPage,
                    'last_page'    => $ownLastPage,
                    'per_page'     => $perPage,
                    'total'        => $ownTotal,
                ],
            ],
            [
                'items' => $campaignCards,
                'meta'  => [
                    'current_page' => $campaignPage,
                    'last_page'    => $campaignLastPage,
                    'per_page'     => $perPage,
                    'total'        => $campaignTotal,
                ],
            ],
        ];
    }
}
