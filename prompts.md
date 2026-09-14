# MP-01 — Marketer Profile: Backend — Two Product Sections + Infinite Scroll + Image Fix

**Stack:** Laravel 11, `App\Http\Controllers\Api\Public\MarketerProfileController`, `App\Services\Customer\ListingQueryService`.
**Audited commit:** `6abb9a2`.

> Invariants: BIGINT money; no `/100`. Read every file before editing.

---

## 0. Read first

```bash
app/Http/Controllers/Api/Public/MarketerProfileController.php
app/Services/Customer/ListingQueryService.php   # toMarketerCardShape() line ~615; dedupByVariant() line ~390
app/Models/MarketerListing.php                  # invitation_id; listing_category; relations
app/Models/MarketerCampaignInvitation.php        # referral_code; campaign relation
app/Models/MarketerCampaign.php                  # vendor_listing_id; admin_listing_id; status
app/Models/Marketer.php                          # listings(); campaignInvitations()
app/Models/ProductImage.php                      # getUrlAttribute() → Storage::disk()->url($this->path)
routes/api_customer_v1.php                       # line ~210: GET {country}/marketers/{slug}
app/Http/Responses/ApiResponse.php               # ApiResponse::success() shape
```

---

## 1. The image bug — why images are blank

`getImageURL()` in the frontend falls back to `/images/no-image-available-icon.jpg` for any URL that doesn't start with `https`.

`ProductImage::getUrlAttribute()` returns `Storage::disk('public')->url($path)` — on local/development this resolves to `http://noon.codefanz.com/storage/...` (no https). The frontend helper drops it.

**Fix (backend only — do NOT change `getImageURL` logic):**

In `MarketerProfileController::buildProfileResponse()` the images reach the card via `toMarketerCardShape()` which calls `buildImagesSlider()` which reads `$img->url`. The `url` accessor is correct — the fix is in `APP_URL`.

**Verify first:**
```bash
grep -n "APP_URL\|APP_ASSET_URL\|ASSET_URL" .env
```
If `APP_URL=http://` (not https), the storage URLs will be http. **Do not change `.env` in the prompt.**

Instead, add a helper in `ListingQueryService` that forces https on storage URLs when the app is behind a reverse proxy (standard Laravel practice):

```php
private function ensureHttps(string $url): string
{
    if (config('app.env') !== 'local' && str_starts_with($url, 'http://')) {
        return 'https://' . substr($url, 7);
    }
    return $url;
}
```

Apply it in `buildImagesSlider()` on every `$img->url`:
```php
'url' => $this->ensureHttps($img->url),
```

Also apply in `toMarketerCardShape()` on `$variantImage` and `thumbnail`. **Only touch ListingQueryService, no other file.**

// VERIFY: check if `toCardShape()` and `toAdminCardShape()` (line ~538) have the same issue — if yes, apply `ensureHttps` to their image lines too so it's consistent across all product card shapes.

---

## 2. Two product sections: marketer's own + campaign products

**Current state:** `buildProfileResponse()` fetches only `MarketerListing` rows (the marketer's own promoted listings). Campaign products (from `marketer_campaigns.vendor_listing_id` linked through accepted invitations) appear in `marketer_listings` via `invitation_id` — so they are already included, but the frontend can't distinguish them visually.

The requirement is to show them in **two clearly separated sections:**
- **Section A — "المنتجات المختارة" (Selected Products):** `MarketerListing` rows where `invitation_id IS NULL` (the marketer's own independent selections).
- **Section B — "منتجات الحملات" (Campaign Products):** `MarketerListing` rows where `invitation_id IS NOT NULL`, enriched with invitation + campaign context (vendor name, campaign title).

**Change in `buildProfileResponse()`:** split the existing query into two:

```php
// Section A — own listings (no campaign link)
$ownListings = MarketerListing::query()
    ->where('marketer_id', $marketer->id)
    ->where('country_id', $country->id)
    ->where('status', 'active')
    ->whereNull('invitation_id')
    ->with([
        'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
        'productVariant.images',
        'productVariant.product:id,name_en,name_ar,slug,category_id,brand_id',
        'productVariant.product.images',
        'productVariant.product.category:id,name_en,name_ar,slug',
        'productVariant.product.brand:id,name_en,name_ar,slug,logo_media_id',
        'marketer:id,name,marketer_type',
        'marketer.marketerProfile:id,marketer_id,profile_slug',
    ])
    ->orderByDesc('total_sold')
    ->orderByDesc('created_at')
    ->skip($ownOffset)
    ->take($perPage)
    ->get();

$ownTotal = MarketerListing::where('marketer_id', $marketer->id)
    ->where('country_id', $country->id)
    ->where('status', 'active')
    ->whereNull('invitation_id')
    ->count();

// Section B — campaign-linked listings
$campaignListings = MarketerListing::query()
    ->where('marketer_id', $marketer->id)
    ->where('country_id', $country->id)
    ->where('status', 'active')
    ->whereNotNull('invitation_id')
    ->with([
        'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
        'productVariant.images',
        'productVariant.product:id,name_en,name_ar,slug,category_id,brand_id',
        'productVariant.product.images',
        'productVariant.product.category:id,name_en,name_ar,slug',
        'productVariant.product.brand:id,name_en,name_ar,slug,logo_media_id',
        'marketer:id,name,marketer_type',
        'marketer.marketerProfile:id,marketer_id,profile_slug',
        'invitation:id,campaign_id,referral_code',
        'invitation.campaign:id,vendor_id,title,status',
        'invitation.campaign.vendor:id,store_name',
    ])
    ->orderByDesc('total_sold')
    ->orderByDesc('created_at')
    ->skip($campaignOffset)
    ->take($perPage)
    ->get();

$campaignTotal = MarketerListing::where('marketer_id', $marketer->id)
    ->where('country_id', $country->id)
    ->where('status', 'active')
    ->whereNotNull('invitation_id')
    ->count();
```

Wishlist ids query stays one call (covers both sections):
```php
$wishlistIds = $this->listings->wishlistListingIds(auth('customer')->id());
```

Map each section with `toMarketerCardShape()` (no changes to that method needed — it already handles the `is_wishlisted` flag).

For campaign cards, add campaign context after mapping:
```php
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
```

---

## 3. Pagination / Infinite scroll

Add query params to `show()` and read them in `buildProfileResponse()`:

```php
// In show():
$ownPage      = max(1, (int) $request->query('own_page', 1));
$campaignPage = max(1, (int) $request->query('campaign_page', 1));
$perPage      = min(24, max(1, (int) $request->query('per_page', 12)));

// Pass down:
$cached = $this->buildProfileResponse($request, $slug, $ownPage, $campaignPage, $perPage);
```

```php
private function buildProfileResponse(
    Request $request,
    string $slug,
    int $ownPage = 1,
    int $campaignPage = 1,
    int $perPage = 12,
): ?array
```

Offsets: `$ownOffset = ($ownPage - 1) * $perPage`, `$campaignOffset = ($campaignPage - 1) * $perPage`.

**Cache invalidation:** the cache key must include the page params so different pages don't collide:
```php
$cacheKey = MarketerProfileCache::key($slug, $countryId) . ":own{$ownPage}:cp{$campaignPage}:pp{$perPage}";
```
The base profile data (marketer info, sidebar) is the same for all pages — only the listings differ. To avoid caching the full response × N pages, cache only the profile header once and let the listings section remain uncached:

```php
// Cache only the static part
$headerKey = MarketerProfileCache::key($slug, $countryId) . ':header';
$header = Cache::remember($headerKey, 300, fn () => $this->buildHeader($request, $slug));
if ($header === null) return ApiResponse::error('Marketer not found.', [], 404);

// Listings always fresh (they change with page + wishlist)
[$ownCards, $campaignCards, $ownTotal, $campaignTotal] =
    $this->buildListings($header['_marketer_id'], $header['_country'], $ownPage, $campaignPage, $perPage, $wishlistIds);
```

Split `buildProfileResponse()` into `buildHeader(Request, string): ?array` (cacheable) and `buildListings(...): array` (never cached). Remove the `_marketer_id` and `_country` private keys from the final response before returning.

---

## 4. Response shape

```json
{
  "data": {
    "marketer": { ... },
    "profile": { ... },
    "own_listings": {
      "items": [ ...toMarketerCardShape ],
      "meta": { "current_page": 1, "last_page": 4, "per_page": 12, "total": 45 }
    },
    "campaign_listings": {
      "items": [ ...toMarketerCardShape + campaign:{id,title,vendor_name} ],
      "meta": { "current_page": 1, "last_page": 2, "per_page": 12, "total": 18 }
    }
  }
}
```

Remove the old `listings` key entirely — the frontend will be updated in MP-02.

---

## 5. Cart endpoint — marketer listing_type

`POST /cart/items` already accepts `listing_type`. Verify the backend cart controller handles `listing_type = "marketer"`:
```bash
grep -n "marketer\|listing_type" app/Http/Controllers/Customer/CartController.php | head -20
```
If `listing_type = "marketer"` is not handled → add it alongside `vendor` in the switch/match that resolves the listing model. The `marketer_listings.id` is what `listing_id` / `vendor_listing_id` should map to when `listing_type = "marketer"`. // VERIFY the exact param name expected by the cart controller.

---

## 6. Wishlist — marketer listing support

`POST /wishlist/items` with `listing_id` from a `MarketerListing`. Verify:
```bash
grep -n "marketer\|listing_type\|MarketerListing" app/Http/Controllers/Customer/WishlistController.php | head -20
```
If marketer listings are not handled → add them. The wishlist check in `ListingQueryService::wishlistListingIds()` already reads `listing_id` agnostically — confirm the wishlist model/table stores `listing_id` without a type constraint.

---

## 7. Acceptance

- `GET /{region}/marketers/{slug}` with no page params → `own_listings.meta.current_page = 1`, `campaign_listings.meta.current_page = 1`.
- `GET /{region}/marketers/{slug}?own_page=2&campaign_page=1` → second page of own listings, first page of campaign listings.
- `own_listings.items[*].campaign` is always `null`; `campaign_listings.items[*].campaign.vendor_name` is present when the invitation has a campaign.
- Old `listings` key is gone from the response.
- All `images[*].url` values start with `https` on production (verify with `curl | jq '[.data.own_listings.items[0].images[].url]'`).
- `POST /cart/items` with `{"vendor_listing_id": "<marketer_listing_id>", "listing_type": "marketer"}` → 200 (not 404 or 422).
- `POST /wishlist/items` with a marketer listing id → 200.



-------------------------------------------

# MP-02 — Marketer Profile: Frontend — Two Sections, Infinite Scroll, Cart + Wishlist

**Stack:** Next.js 15 App Router, RSC + Client leaf pattern, next-intl, TanStack Query, TypeScript.
**Audited commit:** `6abb9a2`.
**Depends on:** MP-01 applied and deployed.

> Follow `frontend/CLAUDE.md` + `AGENTS.md`: feature folder `src/features/noon/marketer-profile/`, kebab-case file names, RSC by default, `"use client"` only on interactive leaves.

---

## 0. Read first

```bash
src/features/noon/marketer-profile/api.ts          # getMarketerProfile(); uses NEXT_PUBLIC_API_PUBLIC_URL
src/features/noon/marketer-profile/helpers/types.ts # MarketerProfileListingItem, MarketerProfileData
src/features/noon/marketer-profile/helpers/to-product-card.ts
src/features/noon/marketer-profile/index.tsx        # RSC shell; passes data to ProductCard grid
src/components/shared/product-card.tsx              # uses Product from types/globals.ts; has AddToCartButton + wishlist
src/components/shared/add-to-cart-button.tsx        # addItem({ vendorListingId, quantity, listingType })
src/providers/wishlist-provider.tsx                 # addItem({ listingId, productVariantId })
src/hooks/use-cart.ts                               # addItem mutation; listingType param
src/helpers/get-image-url.ts                        # getImageURL: non-https → fallback image ← THE BUG
src/lib/utils.ts                                    # fetchInstance; apiBaseUrl
locale/en.json                                      # marketerProfile section starts line ~1106
locale/ar.json                                      # same section
types/globals.ts                                    # Product interface; Images interface
```

---

## 1. Fix the image bug in the frontend

`getImageURL()` returns a fallback for any URL that doesn't start with `https`. The backend's `Storage::disk('public')->url()` on the server returns `http://` on local/staging.

**Fix in `src/helpers/get-image-url.ts`:**
```ts
export const getImageURL = (url: string): string => {
  if (!url) return "/images/no-image-available-icon.jpg";
  // Accept both https and http — backend serves http behind a reverse proxy on some envs.
  if (url.startsWith("http://") || url.startsWith("https://")) return url;
  // Relative path — prefix with storage base if env var is set, otherwise fallback.
  if (url.startsWith("/storage/") || url.startsWith("storage/")) {
    const base = process.env.NEXT_PUBLIC_STORAGE_URL ?? "";
    return base ? `${base.replace(/\/$/, "")}/${url.replace(/^\//, "")}` : url;
  }
  return "/images/no-image-available-icon.jpg";
};
```

Add `NEXT_PUBLIC_STORAGE_URL=https://noon.codefanz.com` to `.env.local` (and document in `.env.example`). This fix is global and will also repair images on category and search pages.

---

## 2. Types — update for new API shape

**`src/features/noon/marketer-profile/helpers/types.ts`** — full rewrite:

```ts
export interface MarketerProfileMarketer {
  id: string;
  name: string;
  marketer_type: "influencer" | "affiliate";
  country?: { name_en: string; name_ar: string } | null;
  total_campaigns: number;
  total_conversions: number;
}

export interface MarketerProfileInfo {
  slug: string;
  bio_ar: string | null;
  bio_en: string | null;
  video_url: string | null;
  social_links: Record<string, string>;
  contact_details: Record<string, string>;
  banner_url: string | null;
  avatar_url: string | null;
  qr_code_url: string | null;
  profile_url: string;
  ad_price?: number | null;
  ad_price_currency?: string | null;
}

export interface CampaignContext {
  id: string;
  title: string | null;
  vendor_name: string | null;
}

export interface MarketerProfileListingItem {
  listing_id: string;
  listing_type: "marketer";
  product_id: string;
  product_slug: string;
  variant_id: string;
  variant_slug: string;
  variant_name: string;
  sku: string;
  name_en: string;
  name_ar: string;
  primary_image: string | null;
  images: Array<{ id: string; url: string; alt: { en: string | null; ar: string | null }; is_primary: boolean; position: number; variant_id: string | null }>;
  category_name: { en: string | null; ar: string | null };
  brand: { id: string; name: { en: string | null; ar: string | null }; logo_url: string | null } | null;
  price: number;
  price_formatted: string;
  compare_at_price: number | null;
  currency: string;
  condition: string;
  referral_code: string | null;
  referral_link: string | null;
  total_sold: number;
  rating_avg: number | null;
  rating_count: number;
  url_param: string;
  product_url: string;
  is_wishlisted: boolean;
  campaign?: CampaignContext | null;   // only on campaign_listings items
}

export interface ListingsMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface MarketerProfileData {
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
  own_listings: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
  campaign_listings: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
}
```

---

## 3. Update `to-product-card.ts`

Add `is_wishlisted` (now returned by the API):
```ts
is_wishlisted: item.is_wishlisted ?? false,
```
Images already come as objects from the API — map them directly instead of reconstructing:
```ts
images: item.images.map((img) => ({
  id: img.id ?? `${item.listing_id}-${img.position}`,
  url: img.url,
  alt: img.alt ?? { ar: item.name_ar, en: item.name_en },
  is_primary: img.is_primary ?? img.position === 0,
  position: img.position,
  variant_id: img.variant_id ?? item.variant_id,
})),
```

---

## 4. API — add paginated fetch + TanStack Query hooks

**`src/features/noon/marketer-profile/api.ts`** — add:

```ts
export interface MarketerListingsParams {
  slug: string;
  ownPage?: number;
  campaignPage?: number;
  perPage?: number;
}

/** Fetches only the listings portion (uncached, auth-aware for wishlist). */
export async function getMarketerListings(
  params: MarketerListingsParams
): Promise<Pick<MarketerProfileData, "own_listings" | "campaign_listings">> {
  const { slug, ownPage = 1, campaignPage = 1, perPage = 12 } = params;
  const qs = new URLSearchParams({
    own_page: String(ownPage),
    campaign_page: String(campaignPage),
    per_page: String(perPage),
  });
  const res = await fetch(
    `${PUBLIC_BASE}/${region}/marketers/${slug}?${qs}`,
    { cache: "no-store", headers: { Accept: "application/json" } }
  );
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const body: { data: MarketerProfileData } = await res.json();
  return {
    own_listings: body.data.own_listings,
    campaign_listings: body.data.campaign_listings,
  };
}
```

**`src/features/noon/marketer-profile/hooks/use-marketer-listings.ts`** (`"use client"`):

```ts
import { useInfiniteQuery } from "@tanstack/react-query";
import { getMarketerListings } from "../api";

export function useMarketerOwnListings(slug: string, perPage = 12) {
  return useInfiniteQuery({
    queryKey: ["marketer-own-listings", slug, perPage],
    queryFn: ({ pageParam = 1 }) =>
      getMarketerListings({ slug, ownPage: pageParam as number, perPage }),
    getNextPageParam: (last) =>
      last.own_listings.meta.current_page < last.own_listings.meta.last_page
        ? last.own_listings.meta.current_page + 1
        : undefined,
    initialPageParam: 1,
    staleTime: 30_000,
  });
}

export function useMarketerCampaignListings(slug: string, perPage = 12) {
  return useInfiniteQuery({
    queryKey: ["marketer-campaign-listings", slug, perPage],
    queryFn: ({ pageParam = 1 }) =>
      getMarketerListings({ slug, campaignPage: pageParam as number, perPage }),
    getNextPageParam: (last) =>
      last.campaign_listings.meta.current_page < last.campaign_listings.meta.last_page
        ? last.campaign_listings.meta.current_page + 1
        : undefined,
    initialPageParam: 1,
    staleTime: 30_000,
  });
}
```

---

## 5. Infinite scroll product grid — client leaf

**`src/features/noon/marketer-profile/marketer-listings-grid.tsx`** (`"use client"`):

```tsx
"use client";
import { useEffect, useRef } from "react";
import { useInfiniteQuery } from "@tanstack/react-query";
import ProductCard from "@/src/components/shared/product-card";
import { Spinner } from "@/src/components/ui/spinner";
import { toProductCard } from "./helpers/to-product-card";
import type { MarketerProfileMarketer, MarketerProfileInfo, MarketerProfileListingItem } from "./helpers/types";
import { getMarketerListings } from "./api";

interface Props {
  slug: string;
  section: "own" | "campaign";
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
  initialItems: MarketerProfileListingItem[];
  initialTotal: number;
  initialLastPage: number;
  emptyLabel: string;
  loadMoreLabel: string;
}

export default function MarketerListingsGrid({
  slug, section, marketer, profile,
  initialItems, initialTotal, initialLastPage,
  emptyLabel, loadMoreLabel,
}: Props) {
  const sentinelRef = useRef<HTMLDivElement>(null);

  const query = useInfiniteQuery({
    queryKey: ["marketer-listings", slug, section],
    queryFn: ({ pageParam = 2 }) =>
      getMarketerListings(
        section === "own"
          ? { slug, ownPage: pageParam as number }
          : { slug, campaignPage: pageParam as number }
      ),
    getNextPageParam: (last) => {
      const meta = section === "own"
        ? last.own_listings.meta
        : last.campaign_listings.meta;
      return meta.current_page < meta.last_page ? meta.current_page + 1 : undefined;
    },
    initialPageParam: 2,   // page 1 is already SSR
    enabled: initialLastPage > 1,
  });

  // Intersection observer — load more when sentinel enters viewport
  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel) return;
    const observer = new IntersectionObserver(
      ([entry]) => { if (entry.isIntersecting && query.hasNextPage && !query.isFetchingNextPage) query.fetchNextPage(); },
      { threshold: 0.1 }
    );
    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [query]);

  // Flatten fetched pages
  const fetchedItems = query.data?.pages.flatMap((page) =>
    (section === "own" ? page.own_listings : page.campaign_listings).items
  ) ?? [];

  const allItems = [...initialItems, ...fetchedItems];

  if (allItems.length === 0) {
    return (
      <div className="text-center py-16">
        <div className="text-5xl mb-3">📦</div>
        <p className="text-gray-500">{emptyLabel}</p>
      </div>
    );
  }

  return (
    <>
      <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 lg:gap-4">
        {allItems.map((item) => (
          <div key={item.listing_id} className="[&>div]:w-full">
            <ProductCard productData={toProductCard(item, marketer, profile)} />
          </div>
        ))}
      </div>

      {/* Sentinel for infinite scroll */}
      <div ref={sentinelRef} className="h-4 mt-4" />
      {query.isFetchingNextPage && (
        <div className="flex justify-center py-6">
          <Spinner />
        </div>
      )}
    </>
  );
}
```

---

## 6. Rewrite `index.tsx` (RSC shell)

```tsx
import { getTranslations } from "next-intl/server";
import MarketerProfileBanner from "./marketer-profile-banner";
import MarketerProfileSidebar from "./marketer-profile-sidebar";
import MarketerListingsGrid from "./marketer-listings-grid";
import { toProductCard } from "./helpers/to-product-card";
import type { MarketerProfileData } from "./helpers/types";
import useLocale from "@/src/hooks/use-locale";

interface Props { data: MarketerProfileData; }

export default async function MarketerProfileView({ data }: Props) {
  const { marketer, profile, own_listings, campaign_listings } = data;
  const t = await getTranslations("marketerProfile");

  return (
    <div className="bg-white min-h-screen">
      <MarketerProfileBanner
        bannerUrl={profile.banner_url}
        avatarUrl={profile.avatar_url}
        marketerName={marketer.name}
        marketerType={marketer.marketer_type}
        profileUrl={profile.profile_url}
        qrCodeUrl={profile.qr_code_url}
        labels={{
          influencerBadge: t("influencerBadge"),
          affiliateBadge: t("affiliateBadge"),
          copied: t("copied"),
          share: t("share"),
          qrTitle: t("qrTitle"),
          qrScanHint: t("qrScanHint", { name: marketer.name }),
          download: t("download"),
          close: t("close"),
        }}
      />

      <div className="container mx-auto px-4 py-8">
        <div className="flex flex-col lg:flex-row gap-8 items-start">
          <MarketerProfileSidebar marketer={marketer} profile={profile} />
          <div className="hidden lg:block w-px bg-gray-200 self-stretch" />

          <main className="flex-1 space-y-10">
            {/* Section A — Own products */}
            <section>
              <h2 className="text-xl font-bold text-gray-900 mb-4">
                {t("selectedProducts")}
                {own_listings.meta.total > 0 && (
                  <span className="ms-2 text-sm font-normal text-gray-400">
                    ({t("productsCount", { count: own_listings.meta.total })})
                  </span>
                )}
              </h2>
              <MarketerListingsGrid
                slug={profile.slug}
                section="own"
                marketer={marketer}
                profile={profile}
                initialItems={own_listings.items}
                initialTotal={own_listings.meta.total}
                initialLastPage={own_listings.meta.last_page}
                emptyLabel={t("emptyProducts")}
                loadMoreLabel={t("loadMore")}
              />
            </section>

            {/* Section B — Campaign products (only if any) */}
            {campaign_listings.meta.total > 0 && (
              <section>
                <h2 className="text-xl font-bold text-gray-900 mb-1">
                  {t("campaignProducts")}
                  <span className="ms-2 text-sm font-normal text-gray-400">
                    ({t("productsCount", { count: campaign_listings.meta.total })})
                  </span>
                </h2>
                <p className="text-xs text-gray-400 mb-4">{t("campaignProductsHint")}</p>
                <MarketerListingsGrid
                  slug={profile.slug}
                  section="campaign"
                  marketer={marketer}
                  profile={profile}
                  initialItems={campaign_listings.items}
                  initialTotal={campaign_listings.meta.total}
                  initialLastPage={campaign_listings.meta.last_page}
                  emptyLabel={t("emptyProducts")}
                  loadMoreLabel={t("loadMore")}
                />
              </section>
            )}
          </main>
        </div>
      </div>
    </div>
  );
}
```

---

## 7. Cart — pass `listing_type: "marketer"` from `AddToCartButton`

**Problem:** `AddToCartButton` calls `addItem({ quantity: 1, vendorListingId: listingId })` with no `listingType`, so the backend defaults to `"vendor"` and may reject a marketer listing id.

`AddToCartButton` currently has no way to know the listing type — it only receives `listingId`. Add an optional prop:

```tsx
// add-to-cart-button.tsx — add prop
type Props = {
  listingId: string;
  listingType?: string;          // ← new
  size?: "sm" | "base" | "lg";
  hasCustomAttributes?: boolean;
  customAttributes?: CustomAttributeDefinition[];
};
```

Pass it through both `addItem` call sites:
```tsx
addItem({ quantity: 1, vendorListingId: listingId, listingType: listingType ?? "vendor" });
```

In `ProductCard`, read `productData.listing_type` and forward it:
```tsx
<AddToCartButton
  listingId={productData.listing_id}
  listingType={productData.listing_type}   // ← add
  hasCustomAttributes={!!productData.has_custom_attributes}
  customAttributes={productData.custom_attributes ?? []}
/>
```

`listing_type` is already on the `Product` type (`types/globals.ts`). No type changes needed.

---

## 8. Wishlist — `is_wishlisted` initial state

`toProductCard()` currently hardcodes `is_wishlisted: false`. After MP-01, the API returns `is_wishlisted` per item. Update in `to-product-card.ts`:
```ts
is_wishlisted: item.is_wishlisted ?? false,
```

`ProductCard` already uses `useState(productData.is_wishlisted)` as initial state, so the heart icon will render correctly on first paint without an extra API call.

The `addToWishlist` call in `ProductCard` already passes `listingId` and `productVariantId` — no changes needed there.

---

## 9. Campaign badge on card

Campaign items carry a `campaign` object. In `to-product-card.ts`, add it to the mapped Product:
```ts
// Add to the return object when campaign is present:
...(item.campaign ? { campaign_context: item.campaign } : {}),
```

Add `campaign_context?: CampaignContext | null` to the `Product` interface in `types/globals.ts`.

In `ProductCard`, below the marketer attribution line, add a campaign badge:
```tsx
{"campaign_context" in productData && productData.campaign_context?.vendor_name && (
  <span className="px-1 lg:px-2.5 text-[8px] md:text-[10px] text-blue-500 font-medium">
    🛍 {productData.campaign_context.vendor_name}
  </span>
)}
```

---

## 10. Locale keys to add

Add to **`locale/en.json`** → `marketerProfile` object:
```json
"campaignProducts": "Campaign Products",
"campaignProductsHint": "Products from vendor campaigns this marketer is promoting",
"loadMore": "Load more",
"ownProductsEmpty": "No personal products added yet"
```

Add same keys to **`locale/ar.json`** → `marketerProfile`:
```json
"campaignProducts": "منتجات الحملات",
"campaignProductsHint": "منتجات من حملات البائعين التي يروج لها هذا الماركتر",
"loadMore": "تحميل المزيد",
"ownProductsEmpty": "لا توجد منتجات مضافة بعد"
```

---

## 11. Acceptance

- `/uae-en/marketer/yasmin-style-profile` — images render (no broken icons).
- Two sections appear: "Selected Products" and "Campaign Products" (latter hidden when count = 0).
- Scrolling to bottom of each section loads the next page; spinner shows during fetch.
- `is_wishlisted` heart fills correctly on first paint for logged-in users.
- "Add to cart" (`+`) works on marketer listings (no 404/422 from backend).
- Campaign product cards show the vendor name badge.
- Arabic locale shows section titles in Arabic; RTL layout preserved.
- `npm run build` passes with no TypeScript errors.

---------------------------------------------------------

# Marketer Profile — Fix crash, images, cart, wishlist, and product URL

## Context

Commit `062abef` already fixed three files in the **frontend** (pulled and ready):
- `src/features/noon/marketer-profile/helpers/types.ts` — `images` is now typed as `MarketerProfileImage[]` (objects, not strings)
- `src/features/noon/marketer-profile/helpers/to-product-card.ts` — maps image objects correctly
- `src/helpers/get-image-url.ts` — now accepts `http://` URLs

**Do not touch those three files.** Everything below is new work.

---

## Problem 1 — `product_url` points to the API, not the storefront

The API returns:
```
"product_url": "https://api.noon.codefanz.com/api/customer/v1/uae/l/VARIANT_ID--LISTING_ID"
```

`ProductCard` uses `product_url` as the `<Link href>` via `url_param`. That opens the raw API endpoint, not the storefront product page.

The frontend already has the correct route: `/products/{url_param}` — look at `ProductCard`:
```tsx
<Link href={`/products/${productData.url_param}`}>
```

So `url_param` is already correct (`VARIANT_ID--LISTING_ID`). The issue is that `to-product-card.ts` maps `product_url: item.product_url` which is the API URL. The `Link` uses `url_param` not `product_url` directly, so this is NOT breaking navigation. **Verify this is not an issue before touching it** — read `ProductCard` and confirm `href` uses `url_param`, not `product_url`.

---

## Problem 2 — Cart does not support `listing_type: "marketer"`

**File:** `backend/app/Http/Requests/Customer/AddCartItemRequest.php`

Current validation:
```php
'listing_type' => ['nullable', 'string', 'in:vendor,admin'],
'vendor_listing_id' => ['required_unless:listing_type,admin', 'nullable', 'uuid', 'exists:vendor_listings,id'],
```

When the frontend sends `listing_type: "marketer"`, it's rejected with 422 because `marketer` is not in the enum.

**Fix in `AddCartItemRequest.php`:**
```php
'listing_type'      => ['nullable', 'string', 'in:vendor,admin,marketer'],
'vendor_listing_id' => ['required_unless:listing_type,admin', 'nullable', 'uuid'],
// Remove the hard exists:vendor_listings,id check — the service validates existence
```

**File:** `backend/app/Http/Controllers/Customer/CartController.php` — `addItem()` method

Current logic:
```php
$isAdmin = $request->input('listing_type') === 'admin';
if ($isAdmin) {
    $item = $this->cartService->addAdminItem(...);
} else {
    $item = $this->cartService->addItem($cart, $request->vendor_listing_id, ...);
}
```

Add marketer branch:
```php
$listingType = $request->input('listing_type', 'vendor');

if ($listingType === 'admin') {
    $item = $this->cartService->addAdminItem($cart, $request->admin_listing_id, $request->quantity, $request->shipping_method_id, $countryId, $customAttributeValues);
} elseif ($listingType === 'marketer') {
    $item = $this->cartService->addMarketerItem($cart, $request->vendor_listing_id, $request->quantity, $request->shipping_method_id, $countryId);
} else {
    $item = $this->cartService->addItem($cart, $request->vendor_listing_id, $request->quantity, $request->shipping_method_id, $countryId, $customAttributeValues);
}
```

**File:** `backend/app/Services/Customer/CartService.php`

Read the existing `addItem()` method fully before writing `addMarketerItem()`. The new method should:
1. Find the `MarketerListing` by id (`$marketerListingId`) where `status = active`
2. Validate it's in the right country
3. Add it to the cart with `listing_type = 'marketer'` and `vendor_listing_id = $marketerListingId`

Read how `addItem()` works and mirror the same pattern. Do NOT guess the cart_items table columns — read the `CartItem` model and the `cart_items` migration first.

---

## Problem 3 — Frontend `AddToCartButton` doesn't pass `listing_type`

**File:** `frontend/src/components/shared/add-to-cart-button.tsx`

Add `listingType` prop and pass it to `addItem`:

```tsx
type Props = {
  listingId: string;
  listingType?: string;   // ← add this
  size?: "sm" | "base" | "lg";
  hasCustomAttributes?: boolean;
  customAttributes?: CustomAttributeDefinition[];
};
```

Both `addItem` call sites inside this file currently call:
```tsx
addItem({ quantity: 1, vendorListingId: listingId })
```

Change to:
```tsx
addItem({ quantity: 1, vendorListingId: listingId, listingType: listingType ?? "vendor" })
```

**File:** `frontend/src/components/shared/product-card.tsx`

Pass `listing_type` to `AddToCartButton`:
```tsx
<AddToCartButton
  listingId={productData.listing_id}
  listingType={productData.listing_type}   // ← add this line
  hasCustomAttributes={!!productData.has_custom_attributes}
  customAttributes={productData.custom_attributes ?? []}
/>
```

---

## Problem 4 — Wishlist `is_wishlisted` is hardcoded `false`

The API already returns `is_wishlisted` per listing. `to-product-card.ts` (already fixed in commit `062abef`) maps `is_wishlisted: item.is_wishlisted ?? false`. **No change needed here — already done.**

But verify the wishlist `addItem` call in `ProductCard` sends both params. Read `src/providers/wishlist-provider.tsx` and `src/hooks/use-wishlist.ts` to confirm `addItem({ listingId, productVariantId })` works for marketer listings (the `listing_id` is a `marketer_listings.id` UUID — the backend wishlist endpoint must accept it).

Check `backend/app/Http/Controllers/Customer/WishlistController.php`:
- If it validates `listing_id` against `vendor_listings` only → add `marketer_listings` to the OR check
- If it's already agnostic (just stores the UUID) → no change needed

---

## Problem 5 — `product_url` in the `ProductCard` link

Read `frontend/src/components/shared/product-card.tsx` line where `<Link href=...>` is.

Currently: `<Link href={`/products/${productData.url_param}`}>`

`url_param` for a marketer listing is `VARIANT_ID--LISTING_ID` (marketer listing id). The backend route `GET /l/{param}` needs to resolve this. Check:

```bash
grep -n "route.*listing.*show\|customer\.listing\.show\|/l/" backend/routes/api_customer_v1.php | head
```

And check what `ListingController::show()` does with the param when `listing_id` is a marketer listing UUID. If it only looks in `vendor_listings` → add a fallback to `marketer_listings`.

---

## Verification steps after applying

```bash
# Backend
php artisan route:list | grep "cart/items"
php artisan route:list | grep "wishlist"

# Test cart with marketer listing
curl -X POST https://api.noon.codefanz.com/api/customer/v1/uae/cart/items \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"vendor_listing_id":"01a09baf-f9d5-738f-a27d-50d322012b5f","listing_type":"marketer","quantity":1}'
# Expect: 200, not 422

# Frontend
cd frontend && npx tsc --noEmit 2>&1 | grep "add-to-cart-button\|product-card" | head
```

The marketer profile page at `/uae-en/marketer/yasmin-style-profile` should now:
- Show all 4 products with images ✓ (fixed in commit 062abef)
- Navigate to the product detail page when clicking a card
- Add to cart successfully
- Add to wishlist successfully

------------------------------------------------

# Marketer Listings — Cart, Wishlist & Product Page Full Implementation

## What's already done (DO NOT touch these files)
- `frontend/src/features/noon/marketer-profile/helpers/types.ts` ✓
- `frontend/src/features/noon/marketer-profile/helpers/to-product-card.ts` ✓
- `frontend/src/helpers/get-image-url.ts` ✓

The marketer profile page now renders correctly. This prompt wires up **cart**, **wishlist**, and **product detail page** for marketer listings.

---

## Overview of changes needed

| Layer | File | Change |
|---|---|---|
| Backend | `cart_items` migration | Add `marketer_listing_id` column |
| Backend | `wishlist_items` migration | Add `marketer_listing_id` column |
| Backend | `CartItem` model | Add fillable + relation |
| Backend | `WishlistItem` model | Add fillable + relation |
| Backend | `AddCartItemRequest` | Accept `listing_type=marketer` |
| Backend | `CartController` | Add marketer branch |
| Backend | `CartService` | Add `addMarketerItem()` |
| Backend | `CartService::recalculateCart()` | Handle marketer items |
| Backend | `CartService::buildShippingGroups()` | Include marketer items |
| Backend | `WishlistService` | Add `marketer_listing` to `TYPE_COLUMNS` |
| Backend | `WishlistController (Api)` | Add `marketer_listing` branch |
| Backend | `WishlistService::addItemOfType()` | Handle `marketer_listing_id` column |
| Backend | `ListingQueryService::wishlistListingIds()` | Include `marketer_listing_id` |
| Backend | `ListingDetailController::resolveListing()` | Fallback to `MarketerListing` |
| Frontend | `AddToCartButton` | Accept + forward `listingType` prop |
| Frontend | `ProductCard` | Pass `listing_type` to `AddToCartButton` |

---

## Step 1 — Migration: add `marketer_listing_id` to `cart_items`

Create `database/migrations/YYYY_MM_DD_HHMMSS_add_marketer_listing_id_to_cart_items.php`:

```php
Schema::table('cart_items', function (Blueprint $table) {
    $table->char('marketer_listing_id', 36)->nullable()->after('admin_listing_id');
    $table->foreign('marketer_listing_id')
          ->references('id')->on('marketer_listings')
          ->nullOnDelete();
    $table->index('marketer_listing_id');
});
```

`down()`:
```php
Schema::table('cart_items', function (Blueprint $table) {
    $table->dropForeign(['marketer_listing_id']);
    $table->dropColumn('marketer_listing_id');
});
```

---

## Step 2 — Migration: add `marketer_listing_id` to `wishlist_items`

Create a second migration for `wishlist_items`:

```php
Schema::table('wishlist_items', function (Blueprint $table) {
    $table->char('marketer_listing_id', 36)->nullable()->after('classified_listing_id');
    $table->foreign('marketer_listing_id')
          ->references('id')->on('marketer_listings')
          ->nullOnDelete();
    $table->index('marketer_listing_id');
});
```

---

## Step 3 — `CartItem` model

**File:** `app/Models/CartItem.php`

Read the file first. Add to `$fillable`:
```php
'marketer_listing_id',
```

Add relation:
```php
public function marketerListing(): BelongsTo
{
    return $this->belongsTo(\App\Models\MarketerListing::class, 'marketer_listing_id');
}
```

---

## Step 4 — `WishlistItem` model

**File:** `app/Models/WishlistItem.php`

Add to `$fillable`:
```php
'marketer_listing_id',
```

Add relation:
```php
public function marketerListing(): BelongsTo
{
    return $this->belongsTo(\App\Models\MarketerListing::class, 'marketer_listing_id');
}
```

---

## Step 5 — `AddCartItemRequest`

**File:** `app/Http/Requests/Customer/AddCartItemRequest.php`

Current rules:
```php
'listing_type' => ['nullable', 'string', 'in:vendor,admin'],
'vendor_listing_id' => ['required_unless:listing_type,admin', 'nullable', 'uuid', 'exists:vendor_listings,id'],
```

Replace with:
```php
'listing_type'      => ['nullable', 'string', 'in:vendor,admin,marketer'],
'vendor_listing_id' => [
    'nullable', 'uuid',
    // Required for vendor and marketer types; the service does the exists check
    Rule::requiredIf(fn () => in_array($this->input('listing_type', 'vendor'), ['vendor', 'marketer'], true)
        && $this->input('listing_type', 'vendor') !== 'admin'),
],
'admin_listing_id'  => ['required_if:listing_type,admin', 'nullable', 'uuid', 'exists:admin_listings,id'],
```

Keep all other rules unchanged.

---

## Step 6 — `CartController::addItem()`

**File:** `app/Http/Controllers/Customer/CartController.php`

Read the full `addItem()` method. Current logic:
```php
$isAdmin = $request->input('listing_type') === 'admin';
if ($isAdmin) {
    $item = $this->cartService->addAdminItem(...);
} else {
    $item = $this->cartService->addItem($cart, $request->vendor_listing_id, ...);
}
```

Replace with:
```php
$listingType = $request->input('listing_type', 'vendor');

if ($listingType === 'admin') {
    $item = $this->cartService->addAdminItem(
        $cart,
        $request->admin_listing_id,
        $request->quantity,
        $request->shipping_method_id,
        $countryId,
        $customAttributeValues,
    );
} elseif ($listingType === 'marketer') {
    $item = $this->cartService->addMarketerItem(
        $cart,
        $request->vendor_listing_id,   // frontend sends marketer_listing_id in this field
        $request->quantity,
        $countryId,
    );
} else {
    $item = $this->cartService->addItem(
        $cart,
        $request->vendor_listing_id,
        $request->quantity,
        $request->shipping_method_id,
        $countryId,
        $customAttributeValues,
    );
}
```

---

## Step 7 — `CartService::addMarketerItem()`

**File:** `app/Services/Customer/CartService.php`

Read `addItem()` and `addAdminItem()` fully before writing this. Add after `addAdminItem()`:

```php
public function addMarketerItem(
    Cart $cart,
    string $marketerListingId,
    int $quantity,
    string $countryId,
): CartItem {
    $listing = \App\Models\MarketerListing::with([
        'productVariant.product',
    ])
    ->where('id', $marketerListingId)
    ->where('country_id', $countryId)
    ->where('status', 'active')
    ->firstOrFail();

    $currentCount = $cart->items()->count();

    $existingItem = $cart->items()
        ->where('marketer_listing_id', $marketerListingId)
        ->first();

    if ($existingItem) {
        $newQty = $existingItem->quantity + $quantity;
        $existingItem->update(['quantity' => $newQty]);
        $item = $existingItem;
    } else {
        if ($currentCount >= self::MAX_ITEMS) {
            throw new \DomainException(__('common.exceptions.cart.max_items', ['max' => self::MAX_ITEMS]));
        }
        $item = $cart->items()->create([
            'marketer_listing_id' => $marketerListingId,
            'vendor_listing_id'   => null,
            'admin_listing_id'    => null,
            'quantity'            => $quantity,
            'unit_price'          => $listing->price,
            'added_at'            => now(),
        ]);
    }

    $this->recalculateCart($cart);

    return $item->fresh();
}
```

---

## Step 8 — `CartService::recalculateCart()` — handle marketer items

**File:** `app/Services/Customer/CartService.php` — private `recalculateCart()` method.

Read the full method. It currently loops `$cart->items` and checks `$item->admin_listing_id !== null` then falls back to `$item->vendorListing`. Add a marketer branch inside that loop:

```php
// Inside the foreach ($cart->items as $item) loop, add BEFORE the vendor check:
if ($item->marketer_listing_id !== null) {
    $listing = $item->marketerListing;
    if (!$listing || $listing->status !== 'active') {
        $item->delete();
        continue;
    }
    $livePrice = (int) $listing->price;
    if ((int) $item->unit_price !== $livePrice) {
        $priceChanges[$item->id] = true;
        $item->update(['unit_price' => $livePrice]);
    }
    continue;  // skip the vendor/admin price update logic below
}
```

Also update `itemEagerLoads()` to include marketer listings:
```php
// Add to the array:
'items.marketerListing.productVariant.product.images',
'items.marketerListing.productVariant.images',
```

---

## Step 9 — `CartService::buildShippingGroups()` — marketer items

**File:** `app/Services/Customer/CartService.php` — `buildShippingGroups()` method.

Read it fully. Inside the `$items->map(function (CartItem $item)` block, the current code does:
```php
$isVendor = (bool) $item->vendor_listing_id;
$listing = $isVendor ? $item->vendorListing : $item->adminListing;
```

Replace with:
```php
$isMarketer = (bool) $item->marketer_listing_id;
$isVendor   = !$isMarketer && (bool) $item->vendor_listing_id;
$listing    = $isMarketer
    ? $item->marketerListing
    : ($isVendor ? $item->vendorListing : $item->adminListing);
$listingType = $isMarketer ? 'marketer' : ($isVendor ? 'vendor' : 'admin');
```

Also update the `$items` query eager loads at the top of `buildShippingGroups()` to include:
```php
'marketerListing.productVariant.product.images',
'marketerListing.productVariant.images',
```

And in the returned item shape:
```php
'listing_id'   => $isMarketer ? $item->marketer_listing_id
                  : ($isVendor ? $item->vendor_listing_id : $item->admin_listing_id),
'listing_type' => $listingType,
'vendor'       => $isVendor ? [...] : null,   // keep existing vendor shape, just guard with $isVendor
```

---

## Step 10 — `WishlistService` — add marketer type

**File:** `app/Services/WishlistService.php`

The constant `TYPE_COLUMNS` at the top of the class is:
```php
private const TYPE_COLUMNS = [
    'vendor_listing' => 'vendor_listing_id',
    'admin_listing'  => 'admin_listing_id',
    'classified'     => 'classified_listing_id',
];
```

Add:
```php
'marketer_listing' => 'marketer_listing_id',
```

In `addItemOfType()`, the create block hardcodes the columns:
```php
$item = WishlistItem::create([
    'wishlist_group_id'      => $group->id,
    'customer_id'            => $customer->id,
    'vendor_listing_id'      => $itemType === 'vendor_listing'  ? $listingId : null,
    'admin_listing_id'       => $itemType === 'admin_listing'   ? $listingId : null,
    'classified_listing_id'  => $itemType === 'classified'      ? $listingId : null,
    'product_variant_id'     => $productVariantId,
    'added_at'               => now(),
]);
```

Add the marketer column:
```php
'marketer_listing_id' => $itemType === 'marketer_listing' ? $listingId : null,
```

---

## Step 11 — `WishlistController (Api)` — add marketer branch

**File:** `app/Http/Controllers/Api/Customer/WishlistController.php` — `addItem()` method.

Read it fully. Current validation:
```php
'item_type' => ['sometimes', 'in:classified'],
```

Change to:
```php
'item_type' => ['sometimes', 'in:classified,marketer'],
```

Current resolution logic:
```php
$isClassified  = ($data['item_type'] ?? null) === 'classified';
$isAdminListing = !$isClassified && ListingModeResolver::isNawyNow($request);

if ($isClassified) { ... }
elseif ($isAdminListing) { ... }
else {
    $listing = VendorListing::where(...)->first();
}
```

Add marketer branch:
```php
$isClassified   = ($data['item_type'] ?? null) === 'classified';
$isMarketer     = ($data['item_type'] ?? null) === 'marketer';
$isAdminListing = !$isClassified && !$isMarketer && ListingModeResolver::isNawyNow($request);

if ($isClassified) {
    $listing = \App\Models\ClassifiedListing::where('id', $data['listing_id'])
        ->where('status', \App\Enums\ClassifiedListingStatus::Active)->first();
} elseif ($isMarketer) {
    $listing = \App\Models\MarketerListing::where('id', $data['listing_id'])
        ->where('status', 'active')->first();
} elseif ($isAdminListing) {
    $listing = AdminListing::where('id', $data['listing_id'])
        ->where('status', AdminListingStatus::Active->value)->first();
} else {
    $listing = VendorListing::where('id', $data['listing_id'])
        ->where('status', VendorListingStatus::Active->value)->first();
}
```

And the `$itemType` line:
```php
// old:
$itemType = $isClassified ? 'classified' : ($isAdminListing ? 'admin_listing' : 'vendor_listing');
// new:
$itemType = match(true) {
    $isClassified   => 'classified',
    $isMarketer     => 'marketer_listing',
    $isAdminListing => 'admin_listing',
    default         => 'vendor_listing',
};
```

And in the response:
```php
'listing_type' => match($itemType) {
    'classified'      => null,
    'marketer_listing'=> 'marketer_listing',
    'admin_listing'   => 'admin_listing',
    default           => 'vendor_listing',
},
```

---

## Step 12 — `ListingQueryService::wishlistListingIds()`

**File:** `app/Services/Customer/ListingQueryService.php` — `wishlistListingIds()` method (~line 712).

Current:
```php
$rows = DB::table('wishlist_items')
    ->where('customer_id', $customerId)
    ->select('vendor_listing_id', 'admin_listing_id')
    ->get();

return $rows->pluck('vendor_listing_id')
    ->merge($rows->pluck('admin_listing_id'))
    ->filter()->unique()->values()->all();
```

Replace with:
```php
$rows = DB::table('wishlist_items')
    ->where('customer_id', $customerId)
    ->select('vendor_listing_id', 'admin_listing_id', 'marketer_listing_id')
    ->get();

return $rows->pluck('vendor_listing_id')
    ->merge($rows->pluck('admin_listing_id'))
    ->merge($rows->pluck('marketer_listing_id'))
    ->filter()->unique()->values()->all();
```

---

## Step 13 — `ListingDetailController::resolveListing()` — marketer fallback

**File:** `app/Http/Controllers/Customer/ListingDetailController.php`

Read `resolveListing()` (~line 215). It parses `VARIANT_ID--LISTING_ID` and looks up `VendorListing`. If not found, add a fallback for `MarketerListing`:

```php
// After the VendorListing query, if $result is null:
if (!$result && str_contains($identifier, '--')) {
    $parsed = $this->identifiers->parseListingRef($identifier);
    if ($parsed) {
        $result = \App\Models\MarketerListing::with([
            'productVariant.product.images',
            'productVariant.product.category',
            'productVariant.product.brand',
            'productVariant.product.highlights',
            'productVariant.product.specifications',
            'productVariant.variantAttributes.attribute',
            'productVariant.variantAttributes.attributeValue',
            'marketer.marketerProfile',
        ])
        ->whereHas('productVariant', fn($q) => $q->where('id', $parsed['product_variant_id']))
        ->where('id', 'like', $parsed['listing_id_prefix'] . '%')
        ->where('country_id', $country->id)
        ->where('status', 'active')
        ->first();
    }
}
```

Then in the `show()` method, after `$listing = $this->resolveListing(...)`, handle `MarketerListing` in the response shape. Read `listingShape()` and add a `MarketerListing` branch that returns the same keys as vendor shape but with `listing_type = 'marketer'`, `vendor = null`, `marketer = [id, name, profile_slug, profile_url]`, and `referral_code`.

---

## Step 14 — Frontend: `AddToCartButton`

**File:** `frontend/src/components/shared/add-to-cart-button.tsx`

Read the file fully. Add `listingType` to Props:
```tsx
type Props = {
  listingId: string;
  listingType?: string;   // add this
  size?: "sm" | "base" | "lg";
  hasCustomAttributes?: boolean;
  customAttributes?: CustomAttributeDefinition[];
};
```

In the component body, receive it:
```tsx
export default function AddToCartButton({
  listingId,
  listingType = "vendor",   // add with default
  size = "base",
  ...
```

Both `addItem` call sites inside the file:
```tsx
// Change from:
addItem({ quantity: 1, vendorListingId: listingId })
// To:
addItem({ quantity: 1, vendorListingId: listingId, listingType })
```

---

## Step 15 — Frontend: `ProductCard`

**File:** `frontend/src/components/shared/product-card.tsx`

Find the `<AddToCartButton` JSX. Add `listingType` prop:
```tsx
<AddToCartButton
  listingId={productData.listing_id}
  listingType={productData.listing_type}
  hasCustomAttributes={!!productData.has_custom_attributes}
  customAttributes={productData.custom_attributes ?? []}
/>
```

`listing_type` already exists on the `Product` type in `types/globals.ts` as `"admin" | "vendor" | "marketer"`. No type changes needed.

---

## After applying — run these checks

```bash
# 1. Migrations
php artisan migrate
php artisan schema:dump --prune

# 2. Quick smoke test — cart
# (replace TOKEN and LISTING_ID with real values)
curl -X POST https://api.noon.codefanz.com/api/customer/v1/uae/cart/items \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"vendor_listing_id":"01a09baf-f9d5-738f-a27d-50d322012b5f","listing_type":"marketer","quantity":1}'
# Expect: 200 with cart data, NOT 422

# 3. Quick smoke test — wishlist
curl -X POST https://api.noon.codefanz.com/api/customer/v1/uae/wishlist/items \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"listing_id":"01a09baf-f9d5-738f-a27d-50d322012b5f","item_type":"marketer","product_variant_id":"50d3dc22-0bdd-4e5f-a8e8-481dfee31c83"}'
# Expect: 201

# 4. Frontend TypeScript check
cd frontend && npx tsc --noEmit 2>&1 | grep "add-to-cart-button\|product-card" | head
```

## Critical: do NOT touch

- `CartService::addItems()` — it reads `listing_type` from the bulk items array; add `marketer` there only if you have time and test coverage
- `CheckoutService` / `placeOrder` — marketer listings go through the vendor's checkout flow; do not add marketer checkout logic here
- Existing `recalculateCart` vendor/admin branches — only add the marketer branch before them, do not modify existing logic