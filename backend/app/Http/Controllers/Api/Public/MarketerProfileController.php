<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\ClassifiedListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\ExclusiveContract;
use App\Models\Marketer;
use App\Models\MarketerListing;
use App\Models\MarketerProfile;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\MarketerProfileCache;
use App\Services\Customer\PromoBadgeResolver;
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
                'marketer' => fn ($q) => $q->select('id', 'name', 'country_id', 'total_campaigns', 'total_conversions')
                    ->withCount(['classifiedListings as classified_count' => fn ($c) => $c->where('status', 'active')]),
                'marketer.marketerJobs',
                'bannerFile',
                'avatarFile',
            ])
            ->addSelect(['marketer_profiles.*', 'ad_price', 'ad_price_currency'])
            ->when($request->type, fn ($q) => $q->whereHas('marketer.marketerJobs', fn ($s) => $s->where('key', $request->type)))
            ->when($countryId, fn ($q) => $q->whereHas('marketer', fn ($s) => $s->where('country_id', $countryId)))
            ->orderByDesc('total_conversions')
            ->paginate((int) $request->query('per_page', 24));

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $defaultLocale = config('app.frontend_default_locale', 'uae-en');

        $items = $profiles->getCollection()->filter(fn (MarketerProfile $profile) => $profile->marketer !== null)->map(function (MarketerProfile $profile) use ($frontendUrl, $defaultLocale) {
            $marketer = $profile->marketer;

            return [
                'id' => $marketer->id,
                'name' => $marketer->name,
                'marketer_type' => $marketer->marketerJobs->first()?->key,
                'profile_slug' => $profile->profile_slug,
                'profile_url' => $frontendUrl.'/'.$defaultLocale.'/marketer/'.$profile->profile_slug,
                'banner_url' => $profile->bannerFile?->url,
                'avatar_url' => $profile->avatarFile?->url,
                'total_campaigns' => $marketer->total_campaigns,
                'total_conversions' => $marketer->total_conversions,
                'avatar_initial' => mb_substr($marketer->name, 0, 1),
                'specialty_ar' => $profile->specialty_ar,
                'specialty_en' => $profile->specialty_en,
                'ad_price' => $profile->ad_price,
                'ad_price_currency' => $profile->ad_price_currency,
                'classified_count' => (int) ($marketer->classified_count ?? 0),
            ];
        })->values()->all();

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
        ]);
    }

    /**
     * GET /api/public/v1/marketers/{slug}
     * Public marketer profile page data. Cached for 5 minutes — public
     * pages don't need real-time freshness, and this keeps response
     * times well under 1s under load.
     */
    /** Public directory of active influencers or brokers (affiliates). */
    public function directory(Request $request, $country, string $type): JsonResponse
    {
        $marketerType = $type === 'brokers' ? 'affiliate' : 'influencer';
        $rows = MarketerProfile::query()
            ->whereNotNull('profile_slug')
            ->whereHas('marketer', fn ($q) => $q->whereHas('marketerJobs', fn ($j) => $j->where('key', $marketerType))->where('global_status', 'active'))
            ->with(['marketer:id,name', 'avatarFile', 'brokerCategory', 'brokerCity'])
            ->orderBy('id')
            ->paginate(min(48, max(1, (int) $request->query('per_page', 12))));

        return ApiResponse::success($rows->through(fn ($p) => [
            'slug' => $p->profile_slug,
            'name' => $p->marketer?->name,
            'bio_ar' => $p->bio_ar,
            'bio_en' => $p->bio_en,
            'specialty_ar' => $p->specialty_ar,
            'specialty_en' => $p->specialty_en,
            'avatar_url' => $p->avatarFile?->url,
            'category_name_ar' => $p->brokerCategory?->name_ar,
            'category_name_en' => $p->brokerCategory?->name_en,
        ]));
    }

    public function show(Request $request, $country, string $slug): JsonResponse
    {
        $countryId = $request->attributes->get('country')?->id ?? 'global';

        $ownPage = max(1, (int) $request->query('own_page', 1));
        $campaignPage = max(1, (int) $request->query('campaign_page', 1));
        $marketerCampaignPage = max(1, (int) $request->query('marketer_campaign_page', 1));
        $perPage = min(24, max(1, (int) $request->query('per_page', 12)));

        $headerKey = MarketerProfileCache::key($slug, $countryId).':header';
        $header = Cache::get($headerKey);

        if ($header === null) {
            $header = $this->buildHeader($request, $slug);

            if ($header === null) {
                return ApiResponse::error('Marketer not found.', [], 404);
            }

            Cache::put($headerKey, $header, 300);
        }

        $country = Country::find($header['_country_id']);

        if (! $country) {
            return ApiResponse::error('Marketer not found.', [], 404);
        }

        $wishlistIds = $this->listings->wishlistListingIds(auth('customer')->id());

        [$ownListings, $vendorCampaignListings, $marketerCampaignListings] = $this->buildListings(
            $header['_marketer_id'],
            $country,
            $ownPage,
            $campaignPage,
            $marketerCampaignPage,
            $perPage,
            $wishlistIds,
        );

        $response = $header;
        unset($response['_marketer_id'], $response['_country_id']);
        $response['own_listings'] = $ownListings;
        $response['vendor_campaign_listings'] = $vendorCampaignListings;
        $response['marketer_campaign_listings'] = $marketerCampaignListings;
        // Backward-compat alias for existing consumers
        $response['campaign_listings'] = $vendorCampaignListings;
        $response['exclusive_contracts'] = $this->exclusiveContracts($header['_marketer_id']);
        $classified = $this->classifiedListings($header['_marketer_id']);
        $response['classified_listings'] = $classified;
        $response['classified_count'] = count($classified);

        return ApiResponse::success($response);
    }

    /** Public-safe active exclusive contracts (no file paths / notes). */
    private function exclusiveContracts(string $marketerId): array
    {
        return ExclusiveContract::active()
            ->where('marketer_id', $marketerId)
            ->with(['classifiedCategory:id,name_ar,name_en', 'classifiedListing:id,title_ar,title_en'])
            ->get()
            ->map(fn ($c) => [
                'scope' => $c->classified_listing_id ? 'listing' : 'category',
                'category_name' => $c->classifiedCategory?->name_ar,
                'category_name_en' => $c->classifiedCategory?->name_en,
                'listing_title' => $c->classifiedListing?->title_ar,
                'listing_title_en' => $c->classifiedListing?->title_en,
                'ends_at' => $c->ends_at?->toIso8601String(),
            ])->values()->all();
    }

    private function classifiedListings(string $marketerId): array
    {
        return ClassifiedListing::query()
            ->where('seller_type', Marketer::class)
            ->where('seller_id', $marketerId)
            ->where('status', ClassifiedListingStatus::Active)
            ->with(['classifiedCategory:id,name_ar,name_en', 'images'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'listing_number' => $l->listing_number,
                'slug' => $l->slug,
                'title_ar' => $l->title_ar,
                'title_en' => $l->title_en,
                'price' => $l->price,
                'currency' => $l->currency,
                'price_negotiable' => (bool) $l->price_negotiable,
                'category' => ['name_ar' => $l->classifiedCategory?->name_ar, 'name_en' => $l->classifiedCategory?->name_en],
                'first_image' => $l->primary_image_url,
                'listing_purpose' => $l->listing_purpose,
                'views_count' => (int) $l->views_count,
            ])->values()->all();
    }

    private function buildHeader(Request $request, string $slug): ?array
    {
        $profile = MarketerProfile::where('profile_slug', $slug)
            ->with([
                'marketer:id,name,country_id,global_status,total_campaigns,total_conversions',
                'marketer.marketerJobs',
                'marketer.country:id,name_en,name_ar,currency_code',
                'bannerFile',
                'avatarFile',
                'brokerCategory:id,name_en,name_ar',
                'brokerCity:id,name_en,name_ar',
            ])
            ->first();

        if (! $profile || ! $profile->marketer || ($profile->marketer->global_status?->value ?? $profile->marketer->global_status) !== 'active') {
            return null;
        }

        $marketer = $profile->marketer;

        $country = $request->attributes->get('country')
            ?? Country::find($marketer->country_id)
            ?? Country::where('is_active', true)->first();

        if (! $country) {
            return null;
        }

        $measurements = null;
        if ($marketer->isInfluencer()) {
            $measurements = [
                'clothing_size' => $profile->clothing_size,
                'shirt_size' => $profile->shirt_size,
                'pants_size' => $profile->pants_size,
                'dress_size' => $profile->dress_size,
                'abaya_size' => $profile->abaya_size,
                'shoe_size' => $profile->shoe_size,
                'shoe_size_system' => $profile->shoe_size_system,
                'chest_cm' => $profile->chest_cm,
                'waist_cm' => $profile->waist_cm,
                'hip_cm' => $profile->hip_cm,
                'height_cm' => $profile->height_cm,
                'item_length_cm' => $profile->item_length_cm,
                'sleeve_from_neck_cm' => $profile->sleeve_from_neck_cm,
                'sleeve_from_shoulder_cm' => $profile->sleeve_from_shoulder_cm,
                'sleeve_width_cm' => $profile->sleeve_width_cm,
                'notes' => $profile->measurements_notes,
            ];
        }

        $brokerSpecialization = null;
        if ($marketer->isAffiliate()) {
            $brokerSpecialization = [
                'categories' => $marketer->categoriesFor('affiliate', 'product')->map(fn ($c) => [
                    'category_id' => $c->id,
                    'category_name_en' => $c->name_en,
                    'category_name_ar' => $c->name_ar,
                ])->values()->all(),
                'city_id' => $profile->broker_city_id,
                'city_name_en' => $profile->brokerCity?->name_en,
                'city_name_ar' => $profile->brokerCity?->name_ar,
                'serves_all_cities' => $profile->broker_serves_all_cities,
            ];
        }

        $qrUrl = $profile->qr_code_path
            ? Storage::disk('public')->url($profile->qr_code_path)
            : null;

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $defaultLocale = config('app.frontend_default_locale', 'uae-en');

        return [
            'marketer' => [
                'id' => $marketer->id,
                'name' => $marketer->name,
                'marketer_type' => $marketer->marketerJobs->first()?->key,
                'country' => $marketer->country ? [
                    'name_en' => $marketer->country->name_en,
                    'name_ar' => $marketer->country->name_ar,
                ] : null,
                'total_campaigns' => $marketer->total_campaigns,
                'total_conversions' => $marketer->total_conversions,
            ],
            'profile' => [
                'slug' => $profile->profile_slug,
                'bio_ar' => $profile->bio_ar,
                'bio_en' => $profile->bio_en,
                'specialty_ar' => $profile->specialty_ar,
                'specialty_en' => $profile->specialty_en,
                'video_url' => $profile->video_url,
                'social_links' => $profile->social_links ?? [],
                'contact_details' => $profile->contact_details ?? [],
                'banner_url' => $profile->bannerFile?->url,
                'avatar_url' => $profile->avatarFile?->url,
                'qr_code_url' => $qrUrl,
                'profile_url' => $frontendUrl.'/'.$defaultLocale.'/marketer/'.$profile->profile_slug,
                'ad_price' => $profile->ad_price,
                'ad_price_currency' => $profile->ad_price_currency,
                'measurements' => $measurements,
                'broker_specialization' => $brokerSpecialization,
            ],
            '_marketer_id' => $marketer->id,
            '_country_id' => $country->id,
        ];
    }

    /**
     * Fetches the three listing sections (own + vendor campaigns + marketer campaigns),
     * never cached — they change with page params and per-customer wishlist state.
     *
     * @return array{0: array, 1: array, 2: array}
     */
    private function buildListings(
        string $marketerId,
        Country $country,
        int $ownPage,
        int $campaignPage,
        int $marketerCampaignPage,
        int $perPage,
        array $wishlistIds,
    ): array {
        $ownOffset = ($ownPage - 1) * $perPage;
        $campaignOffset = ($campaignPage - 1) * $perPage;
        $marketerCampaignOffset = ($marketerCampaignPage - 1) * $perPage;

        $eagerLoads = [
            'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
            'productVariant.images',
            'productVariant.product:id,name_en,name_ar,slug,category_id,brand_id',
            'productVariant.product.images',
            'productVariant.product.category:id,name_en,name_ar,slug',
            'productVariant.product.brand:id,name_en,name_ar,slug,logo_media_id',
            'marketer:id,name',
            'marketer.marketerJobs',
            'marketer.marketerProfile:id,marketer_id,profile_slug',
        ];

        $campaignEagerLoads = array_merge($eagerLoads, [
            'invitation:id,campaign_id,referral_code',
            'invitation.campaign:id,vendor_id,title,status,owner_type,owner_id',
            'invitation.campaign.vendor:id,store_name',
        ]);

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

        // Section B — vendor campaign listings (campaign.vendor_id IS NOT NULL)
        $vendorCampaignBaseQuery = fn () => MarketerListing::query()
            ->where('marketer_id', $marketerId)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->whereNotNull('invitation_id')
            ->whereHas('invitation.campaign', fn ($q) => $q->whereNotNull('vendor_id'));

        $vendorCampaignListings = $vendorCampaignBaseQuery()
            ->with($campaignEagerLoads)
            ->orderByDesc('total_sold')
            ->orderByDesc('created_at')
            ->skip($campaignOffset)
            ->take($perPage)
            ->get();

        $vendorCampaignTotal = $vendorCampaignBaseQuery()->count();

        // Section C — marketer-to-marketer campaign listings (campaign.vendor_id IS NULL)
        $marketerCampaignBaseQuery = fn () => MarketerListing::query()
            ->where('marketer_id', $marketerId)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->whereNotNull('invitation_id')
            ->whereHas('invitation.campaign', fn ($q) => $q->whereNull('vendor_id'));

        $marketerCampaignListings = $marketerCampaignBaseQuery()
            ->with($campaignEagerLoads)
            ->orderByDesc('total_sold')
            ->orderByDesc('created_at')
            ->skip($marketerCampaignOffset)
            ->take($perPage)
            ->get();

        $marketerCampaignTotal = $marketerCampaignBaseQuery()->count();

        $allListings = collect($ownListings)
            ->concat($vendorCampaignListings)
            ->concat($marketerCampaignListings);

        PromoBadgeResolver::instance()->prime(PromoBadgeResolver::tuplesForListings($allListings));

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

        $toCampaignCard = function (MarketerListing $listing) use ($country, $wishlistIds) {
            $card = $this->listings->toMarketerCardShape(
                listing: $listing,
                product: $listing->productVariant->product,
                country: $country,
                isWishlisted: in_array($listing->id, $wishlistIds, true),
            );
            $card['campaign'] = $listing->invitation?->campaign ? [
                'id' => $listing->invitation->campaign->id,
                'title' => $listing->invitation->campaign->title,
                'vendor_name' => $listing->invitation->campaign->vendor?->store_name,
            ] : null;

            return $card;
        };

        $vendorCampaignCards = collect($vendorCampaignListings)->map($toCampaignCard)->values()->all();
        $marketerCampaignCards = collect($marketerCampaignListings)->map($toCampaignCard)->values()->all();

        $ownLastPage = (int) max(1, ceil($ownTotal / $perPage));
        $vendorCampaignLastPage = (int) max(1, ceil($vendorCampaignTotal / $perPage));
        $marketerCampaignLastPage = (int) max(1, ceil($marketerCampaignTotal / $perPage));

        return [
            [
                'items' => $ownCards,
                'meta' => [
                    'current_page' => $ownPage,
                    'last_page' => $ownLastPage,
                    'per_page' => $perPage,
                    'total' => $ownTotal,
                ],
            ],
            [
                'items' => $vendorCampaignCards,
                'meta' => [
                    'current_page' => $campaignPage,
                    'last_page' => $vendorCampaignLastPage,
                    'per_page' => $perPage,
                    'total' => $vendorCampaignTotal,
                ],
            ],
            [
                'items' => $marketerCampaignCards,
                'meta' => [
                    'current_page' => $marketerCampaignPage,
                    'last_page' => $marketerCampaignLastPage,
                    'per_page' => $perPage,
                    'total' => $marketerCampaignTotal,
                ],
            ],
        ];
    }
}
