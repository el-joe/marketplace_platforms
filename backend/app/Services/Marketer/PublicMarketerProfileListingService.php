<?php

namespace App\Services\Marketer;

use App\Enums\ClassifiedListingStatus;
use App\Enums\TravelPackageStatus;
use App\Http\Resources\Public\MarketerBannerResource;
use App\Models\Country;
use App\Models\ExclusiveContract;
use App\Models\MarketerListing;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\PromoBadgeResolver;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Filtered, unified listing grid + facets for GET /marketers/{slug}.
 *
 * Own listings = MarketerListing without invitation (products only).
 * For-sellers = listings born from a vendor or marketer campaign (source=vendor_campaign | marketer_campaign
 * narrows to one of the two; product cards carry the matching `section`); kind=classified are the
 * campaign-promoted classified ads (listing_category=classified), kind=travel the campaign-promoted
 * travel packages (listing_category=travel), everything else is a product. `category` means a product
 * category, or a classified category when kind=classified; `destination` only applies to kind=travel.
 * Never cached: depends on filters, pagination and the customer's wishlist.
 */
class PublicMarketerProfileListingService
{
    public const SOURCES = ['all', 'own', 'for_sellers', 'vendor_campaign', 'marketer_campaign'];

    public const KINDS = ['all', 'products', 'classified', 'travel'];

    public const SORTS = ['popular', 'newest', 'price_asc', 'price_desc'];

    public function __construct(private readonly ListingQueryService $listings) {}

    /**
     * @return array{source: string, kind: string, q: ?string, category: list<string>, brand: list<string>, destination: list<string>, price_min: int|float|null, price_max: int|float|null, discount: bool, sort: string, page: int, per_page: int}
     */
    public function filtersFrom(Request $request): array
    {
        $ids = fn (string $key) => collect((array) $request->query($key, []))
            ->flatMap(fn ($v) => is_string($v) ? explode(',', $v) : [])
            ->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all();
        $money = fn (string $key) => is_numeric($request->query($key)) && (float) $request->query($key) >= 0 ? Money::number($request->query($key)) : null;
        $q = trim((string) $request->query('q', ''));

        return [
            'source' => in_array($request->query('source'), self::SOURCES, true) ? (string) $request->query('source') : 'all',
            'kind' => in_array($request->query('kind'), self::KINDS, true) ? (string) $request->query('kind') : 'all',
            'q' => $q !== '' ? $q : null,
            'category' => $ids('category'),
            'brand' => $ids('brand'),
            'destination' => $ids('destination'),
            'price_min' => $money('price_min'),
            'price_max' => $money('price_max'),
            'discount' => $request->boolean('discount'),
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'popular',
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => min(48, max(1, (int) $request->query('per_page', 12))),
        ];
    }

    /**
     * Whether any narrowing filter (other than sort/pagination) is active.
     *
     * @param  array<string, mixed>  $f
     */
    public function isFiltered(array $f): bool
    {
        return $f['source'] !== 'all' || $f['kind'] !== 'all' || $f['q'] !== null || $f['category'] !== []
            || $f['brand'] !== [] || $f['destination'] !== [] || $f['price_min'] !== null || $f['price_max'] !== null || $f['discount'];
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  list<string>  $skip  filter dimensions ignored (used for facet counts)
     */
    public function baseQuery(string $marketerId, string $countryId, array $f, array $skip = []): Builder
    {
        $query = MarketerListing::query()
            ->where('marketer_listings.marketer_id', $marketerId)
            ->where('marketer_listings.country_id', $countryId)
            ->where('marketer_listings.status', 'active')
            ->where(function (Builder $w) {
                $w->where('marketer_listings.listing_category', 'product')
                    ->orWhere(function (Builder $c) {
                        $c->where('marketer_listings.listing_category', 'classified')
                            ->whereNotNull('marketer_listings.invitation_id')
                            ->whereHas('classifiedListing', fn ($l) => $l->where('status', ClassifiedListingStatus::Active));
                    })
                    ->orWhere(function (Builder $t) {
                        $t->where('marketer_listings.listing_category', 'travel')
                            ->whereNotNull('marketer_listings.invitation_id')
                            ->whereHas('travelPackage', fn ($p) => $p
                                ->where('status', TravelPackageStatus::Active->value)
                                ->where('departure_date', '>=', Carbon::today()));
                    });
            })
            ->where(function (Builder $w) {
                $w->whereNull('marketer_listings.invitation_id')
                    ->orWhereHas('invitation.campaign');
            })
            // A marketer promoting the same source (or classified ad) in several campaigns shows it once.
            ->whereNotExists(fn ($d) => $d->select(DB::raw(1))->from('marketer_listings as dup')
                ->whereColumn('dup.marketer_id', 'marketer_listings.marketer_id')
                ->where('dup.status', 'active')->whereNull('dup.deleted_at')
                ->whereColumn('dup.id', '<', 'marketer_listings.id')
                ->where(fn ($m) => $m
                    ->where(fn ($s) => $s->whereNotNull('marketer_listings.source_listing_id')->whereColumn('dup.source_listing_id', 'marketer_listings.source_listing_id'))
                    ->orWhere(fn ($c) => $c->whereNotNull('marketer_listings.classified_listing_id')->whereColumn('dup.classified_listing_id', 'marketer_listings.classified_listing_id'))
                    ->orWhere(fn ($t) => $t->whereNotNull('marketer_listings.travel_package_id')->whereColumn('dup.travel_package_id', 'marketer_listings.travel_package_id'))));

        if (! in_array('source', $skip, true)) {
            match ($f['source']) {
                'own' => $query->whereNull('marketer_listings.invitation_id'),
                'for_sellers' => $query->whereNotNull('marketer_listings.invitation_id'),
                'vendor_campaign' => $query->where('marketer_listings.listing_category', 'product')
                    ->whereNotNull('marketer_listings.invitation_id')
                    ->whereHas('invitation.campaign', fn ($c) => $c->whereNotNull('vendor_id')),
                'marketer_campaign' => $query->where('marketer_listings.listing_category', 'product')
                    ->whereNotNull('marketer_listings.invitation_id')
                    ->whereHas('invitation.campaign', fn ($c) => $c->whereNull('vendor_id')),
                default => null,
            };
        }

        if (! in_array('kind', $skip, true)) {
            match ($f['kind']) {
                'products' => $query->where('marketer_listings.listing_category', 'product'),
                'classified' => $query->where('marketer_listings.listing_category', 'classified'),
                'travel' => $query->where('marketer_listings.listing_category', 'travel'),
                default => null,
            };
        }

        if ($f['q'] !== null && ! in_array('q', $skip, true)) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $query->where(fn ($w) => $w
                ->whereHas('productVariant', fn ($v) => $v
                    ->where('variant_name', 'like', $like)->orWhere('variant_name_ar', 'like', $like)
                    ->orWhereHas('product', fn ($p) => $p->where('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)))
                ->orWhereHas('classifiedListing', fn ($c) => $c->where('title_en', 'like', $like)->orWhere('title_ar', 'like', $like))
                ->orWhereHas('travelPackage', fn ($t) => $t->where('title_en', 'like', $like)->orWhere('title_ar', 'like', $like)
                    ->orWhere('destination_city', 'like', $like)->orWhere('destination_country', 'like', $like)));
        }

        if ($f['category'] !== [] && ! in_array('category', $skip, true)) {
            if ($f['kind'] === 'classified') {
                $query->whereHas('classifiedListing', fn ($c) => $c->whereIn('classified_category_id', $f['category']));
            } else {
                $query->whereHas('productVariant.product', fn ($p) => $p->whereIn('category_id', $f['category']));
            }
        }

        if ($f['destination'] !== [] && ! in_array('destination', $skip, true)) {
            $query->whereHas('travelPackage', fn ($t) => $t->whereIn('destination_travel_country_id', $f['destination']));
        }

        if ($f['brand'] !== [] && ! in_array('brand', $skip, true)) {
            $query->whereHas('productVariant.product', fn ($p) => $p->whereIn('brand_id', $f['brand']));
        }

        if (! in_array('price', $skip, true)) {
            if ($f['price_min'] !== null) {
                $query->where('marketer_listings.price', '>=', $f['price_min']);
            }
            if ($f['price_max'] !== null) {
                $query->where('marketer_listings.price', '<=', $f['price_max']);
            }
        }

        if ($f['discount'] && ! in_array('discount', $skip, true)) {
            $query->whereNotNull('marketer_listings.compare_at_price')
                ->whereColumn('marketer_listings.compare_at_price', '>', 'marketer_listings.price');
        }

        return $query;
    }

    /**
     * Unified filtered grid: `{ items, meta }`.
     *
     * @param  array<string, mixed>  $f
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listings(string $marketerId, Country $country, array $f, array $wishlistIds, string $locale = 'en'): array
    {
        $query = $this->baseQuery($marketerId, $country->id, $f);
        $total = (clone $query)->count();

        match ($f['sort']) {
            'newest' => $query->orderByDesc('marketer_listings.created_at'),
            'price_asc' => $query->orderBy('marketer_listings.price')->orderByDesc('marketer_listings.created_at'),
            'price_desc' => $query->orderByDesc('marketer_listings.price')->orderByDesc('marketer_listings.created_at'),
            default => $query->orderByDesc('marketer_listings.total_sold')->orderByDesc('marketer_listings.created_at'),
        };

        $rows = $query
            ->orderBy('marketer_listings.id')
            ->with([
                'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
                'productVariant.images',
                'productVariant.product:id,name_en,name_ar,slug,category_id,brand_id,has_custom_attributes',
                'productVariant.product.images',
                'productVariant.product.category:id,name_en,name_ar,slug',
                'productVariant.product.brand:id,name_en,name_ar,slug,logo_media_id',
                'marketer:id,name',
                'marketer.marketerJobs',
                'marketer.marketerProfile:id,marketer_id,profile_slug',
                'invitation:id,campaign_id,referral_code',
                'invitation.campaign:id,vendor_id,title,status,owner_type,owner_id',
                'invitation.campaign.vendor:id,store_name',
                'classifiedListing.classifiedCategory:id,name_ar,name_en',
                'classifiedListing.city:id,name_ar,name_en',
                'classifiedListing.images',
                'travelPackage.agency:id,name',
                'travelPackage.categories:id,name_en,name_ar,slug',
                'travelPackage.destinationCountry:id,name_en,name_ar',
                'travelPackage.destinationCity:id,name_en,name_ar',
                'travelPackage.media' => fn ($m) => $m->orderBy('position'),
            ])
            ->forPage($f['page'], $f['per_page'])
            ->get();

        PromoBadgeResolver::instance()->prime(PromoBadgeResolver::tuplesForListings($rows->where('listing_category', 'product')));

        $contracts = ExclusiveContract::active()->where('marketer_id', $marketerId)->get(['classified_category_id', 'classified_listing_id']);

        return [
            'items' => $rows->map(fn (MarketerListing $l) => $this->card($l, $country, $wishlistIds, $contracts, $locale))->values()->all(),
            'meta' => [
                'current_page' => $f['page'],
                'last_page' => (int) max(1, ceil($total / $f['per_page'])),
                'per_page' => $f['per_page'],
                'total' => $total,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function facets(string $marketerId, Country $country, array $f, string $locale): array
    {
        $cid = $country->id;
        $name = $locale === 'ar' ? 'name_ar' : 'name_en';
        $kind = $f['kind'];

        $sources = collect(['all', 'own', 'vendor_campaign', 'marketer_campaign'])
            ->map(fn ($key) => ['key' => $key, 'count' => $this->baseQuery($marketerId, $cid, ['source' => $key] + $f, [])->count()])
            ->values()->all();

        // Tab counts ignore every other filter so the tabs stay stable while filtering inside one.
        $unfiltered = ['source' => 'all', 'q' => null, 'category' => [], 'brand' => [], 'destination' => [], 'price_min' => null, 'price_max' => null, 'discount' => false] + $f;
        $kinds = collect(self::KINDS)
            ->map(fn ($key) => ['key' => $key, 'count' => $this->baseQuery($marketerId, $cid, ['kind' => $key] + $unfiltered, [])->count()])
            ->values()->all();

        $grouped = fn (string $skip, string $table, string $fk) => $this->baseQuery($marketerId, $cid, $f, [$skip])
            ->join('product_variants as fv', 'fv.id', '=', 'marketer_listings.product_variant_id')
            ->join('products as fp', 'fp.id', '=', 'fv.product_id')
            ->join("{$table} as ft", 'ft.id', '=', "fp.{$fk}")
            ->whereNull('fv.deleted_at')->whereNull('fp.deleted_at')
            ->groupBy('ft.id', 'ft.name_ar', 'ft.name_en')
            ->orderByDesc(DB::raw('count(*)'))
            ->toBase()
            ->get(['ft.id', "ft.{$name} as name", DB::raw('count(*) as cnt')])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'count' => (int) $r->cnt])
            ->values()->all();

        $classifiedCategories = fn () => $this->baseQuery($marketerId, $cid, $f, ['category'])
            ->join('classified_listings as cl', 'cl.id', '=', 'marketer_listings.classified_listing_id')
            ->join('classified_categories as cc', 'cc.id', '=', 'cl.classified_category_id')
            ->groupBy('cc.id', 'cc.name_ar', 'cc.name_en')
            ->orderByDesc(DB::raw('count(*)'))
            ->toBase()
            ->get(['cc.id', "cc.{$name} as name", DB::raw('count(*) as cnt')])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'count' => (int) $r->cnt])
            ->values()->all();

        $destinations = fn () => $this->baseQuery($marketerId, $cid, $f, ['destination'])
            ->join('travel_packages as tp', 'tp.id', '=', 'marketer_listings.travel_package_id')
            ->join('travel_countries as tc', 'tc.id', '=', 'tp.destination_travel_country_id')
            ->groupBy('tc.id', 'tc.name_ar', 'tc.name_en')
            ->orderByDesc(DB::raw('count(*)'))
            ->toBase()
            ->get(['tc.id', "tc.{$name} as name", DB::raw('count(*) as cnt')])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'count' => (int) $r->cnt])
            ->values()->all();

        $priceRange = $this->baseQuery($marketerId, $cid, $f, ['price'])
            ->selectRaw('min(marketer_listings.price) as lo, max(marketer_listings.price) as hi')->toBase()->first();

        $isProducts = ! in_array($kind, ['classified', 'travel'], true);

        return [
            'sources' => $sources,
            'kinds' => $kinds,
            'categories' => match ($kind) {
                'classified' => $classifiedCategories(),
                'travel' => [],
                default => $grouped('category', 'categories', 'category_id'),
            },
            'brands' => $isProducts ? $grouped('brand', 'brands', 'brand_id') : [],
            'destinations' => $kind === 'travel' ? $destinations() : [],
            'price' => [
                'min' => $priceRange?->lo !== null ? Money::number($priceRange->lo) : null,
                'max' => $priceRange?->hi !== null ? Money::number($priceRange->hi) : null,
            ],
            'discounted_count' => $isProducts ? $this->baseQuery($marketerId, $cid, ['discount' => true] + $f)->count() : 0,
        ];
    }

    /**
     * @param  list<string>  $wishlistIds
     * @return array<string, mixed>
     */
    private function card(MarketerListing $listing, Country $country, array $wishlistIds, $contracts, string $locale): array
    {
        $source = $listing->invitation_id === null ? 'own' : 'for_sellers';
        $campaign = $listing->invitation?->campaign;
        $campaignShape = $campaign ? [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'vendor_name' => $campaign->vendor?->store_name,
            'is_vendor' => $campaign->vendor_id !== null,
        ] : null;

        if ($listing->listing_category === 'travel') {
            return [
                'id' => $listing->id,
                'kind' => 'travel',
                'source' => $source,
                'is_exclusive' => false,
                'discount_percent' => null,
                'listing_id' => $listing->id,
                'listing_type' => 'travel',
                'referral_code' => $listing->referral_code,
            ] + $this->listings->toTravelCardShape($listing->travelPackage) + ['campaign' => $campaignShape];
        }

        if ($listing->listing_category === 'classified') {
            $ad = $listing->classifiedListing;

            return [
                'id' => $listing->id,
                'kind' => 'classified',
                'source' => $source,
                'is_exclusive' => $contracts->contains(fn ($c) => $c->classified_listing_id === $ad->id
                    || ($c->classified_listing_id === null && $c->classified_category_id === $ad->classified_category_id)),
                'discount_percent' => null,
                'location' => $locale === 'ar' ? ($ad->city?->name_ar ?: $ad->city?->name_en) : ($ad->city?->name_en ?: $ad->city?->name_ar),
                'image' => $ad->primary_image_url ? ['url' => $ad->primary_image_url] : null,
                'listing_id' => $listing->id,
                'listing_type' => 'classified',
                'classified_id' => $ad->id,
                'listing_number' => $ad->listing_number,
                'slug' => $ad->slug,
                'title_en' => $ad->title_en,
                'title_ar' => $ad->title_ar,
                'name' => ['ar' => $ad->title_ar, 'en' => $ad->title_en],
                'thumbnail' => $ad->primary_image_url,
                'primary_image' => $ad->primary_image_url,
                'category_name' => ['en' => $ad->classifiedCategory?->name_en, 'ar' => $ad->classifiedCategory?->name_ar],
                'price' => $listing->price,
                'price_formatted' => number_format($listing->price, 2),
                'compare_at_price' => null,
                'currency' => $listing->currency,
                'price_negotiable' => (bool) $ad->price_negotiable,
                'listing_purpose' => $ad->listing_purpose,
                'referral_code' => $listing->referral_code,
                'campaign' => $campaignShape,
            ];
        }

        $card = $this->listings->toMarketerCardShape(
            listing: $listing,
            product: $listing->productVariant->product,
            country: $country,
            isWishlisted: in_array($listing->id, $wishlistIds, true),
        );

        $compare = $listing->compare_at_price;
        $percent = $compare && $compare > $listing->price ? (int) floor(($compare - $listing->price) / $compare * 100) : null;

        return [
            'id' => $listing->id,
            'kind' => 'product',
            'source' => $source,
            'section' => match (true) {
                $listing->invitation_id === null => 'own',
                $campaign?->vendor_id !== null => 'vendor_campaign',
                default => 'marketer_campaign',
            },
            'is_exclusive' => false,
            'discount_percent' => $percent,
        ] + $card + ['campaign' => $campaignShape];
    }

    public static function localeFor(Request $request): string
    {
        return MarketerBannerResource::localeFor($request);
    }
}
