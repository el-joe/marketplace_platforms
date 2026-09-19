# QC Report — "Nawi Ads" duplication (Ad Slots vs Ad Packages)

Date: 2026-09-19 · Scope: `backend/` (Laravel) + `frontend/` (Next.js storefront)

## 1. Verdict: DUPLICATED — two parallel systems implement the same product

"Nawi Ads" (paid listing boost, tier 1 "Serious", tier 2 "Serious + Featured" with popup) exists twice:

| Aspect | A. Ad Slots (`PaidAdSlot`, target `listing_promotion`) | B. Ad Packages (`AdPackage` + `VendorAdSubscription`) |
|---|---|---|
| Tiers | `listing-boost-serious`, `listing-boost-featured` (`NawiAdsSlotSeeder`) | `tier` enum `serious`, `serious_featured` |
| Admin UI | `admin.ad-slots.*`, `admin.paid-ad-bookings.*` | `admin.ad-packages.*`, `admin.ad-subscriptions.*` (nav label "Nawi Ads") |
| Vendor UI | `partner.ad-slots.*` + `partner.ad-bookings.*` | `partner.ad-subscriptions.*` |
| Pricing | `base_rate`, currency, quote service | `price_monthly` |
| Payment | Wallet via `AdBillingService` / `AdBookingService::pay` | **None** — `subscribe()` activates instantly |
| Approval / creative review | Yes (`requires_approval`, `AdCreativeService`) | None |
| Availability / concurrency | `AdSlotAvailabilityService`, `max_concurrent` | None |
| Boost sync | `PaidAdBoostSyncService` | inline in controller + `ads:expire-boosts` |
| Popup on storefront | **Not exposed** by `GET /active-popup` | Only source (`AdPopupController`) |
| Tests | `tests/Feature/Ads/AdBookingServiceTest.php` | none |

Both write the same columns `vendor_listings.is_ad_boosted` / `ad_boost_expires_at`, so they can corrupt each other.

## 2. Lifecycle check

**Path A (Ad Slots) — complete in backend, incomplete at the storefront edge:**
seed slot → admin edits (`admin/ad-slots`) → vendor browses (`partner/ad-slots`, group "promotions") → quote → booking → creative → submit → admin review → pay (wallet) → active → `PaidAdBoostSyncService` sets boost → listing sort (`Api/Customer/ListingController:305`) → expiry/terminal clears boost.
Gap: `shows_popup` tier never reaches the popup (`frontend/src/features/noon/ads/serious-featured-popup.tsx` → `/active-popup` reads only `VendorAdSubscription`). Also `NawiAdsSlotSeeder` is not registered in `DatabaseSeeder`.

**Path B (Ad Packages) — runs end to end but is unsafe:**
admin CRUD packages → vendor `packages` page → `subscribe` → boost + popup → `ads:expire-boosts` hourly / cancel.
Defects: free activation (no wallet charge), no approval of popup content (public XSS/abuse surface), no availability, no invoice/transaction, duplicate cancel logic, cancel/expire clears the boost even if a Path-A booking is still active.

## 3. Findings

| ID | Severity | Finding |
|---|---|---|
| NA-01 | High | Duplicate admin nav/entry points; "Nawi Ads" label points only at Packages, real slots are under Ad Slots |
| NA-02 | Critical | `Partner\AdSubscriptionController::subscribe` grants 30 days of boost/popup with **no payment** (`amount_paid` recorded but never debited) |
| NA-03 | High | `/active-popup` ignores active `PaidAdBooking` with `shows_popup=true` slots |
| NA-04 | High | Boost clear in `ExpireAdBoosts`, both `cancel()` methods ignores other active sources |
| NA-05 | Medium | `NawiAdsSlotSeeder` not in `DatabaseSeeder`; no tests for Path B / popup / cross-source boost |

Decision taken: **Ad Slots is the canonical system** (payment, approval, availability, tests already exist). Ad Packages is deprecated (soft: no data deletion, no table drops).

## 4. Fix prompts (each runs in its own sub-agent, sequentially; one commit to `main` per fix)

Common rules for every prompt: work in `/var/www/marketplace`, on `main`; touch only what the fix needs; run `php artisan test --filter=Ad` (and `php -l` on edited files) before committing; commit only your own files with a message `fix(nawi-ads): NA-0X <summary>`; do not push.

### NA-02 — Block unpaid activation
> In `backend/app/Http/Controllers/Partner/AdSubscriptionController.php`, `subscribe()` activates a boost without charging. Make it stop granting anything: return HTTP 410 JSON `{success:false, message, redirect: route('partner.ad-slots.index')}` pointing vendors to the Ad Slots "promotions" group. Keep `index` and `cancel` working for existing subscriptions. Update `partner/ad-subscriptions/packages.blade.php` so its subscribe UI links to `partner.ad-slots.index` instead of posting. Add a feature test asserting no `VendorAdSubscription` is created and the listing is not boosted.

### NA-01 — Single Nawi Ads entry point
> In `backend/app/Services/NavigationService.php` (~line 635) rename the Packages item to "Nawi Ads (Legacy Packages)" and add/keep one "Nawi Ads" item that links to `admin.ad-slots.index` filtered by `target_type=listing_promotion` (check the controller supports the filter; add it if missing). Update breadcrumbs in `Admin/AdPackageController` and `Admin/AdSubscriptionController` and the partner sidebar (`partner/partials/sidebar.blade.php`) the same way, and add a "legacy — read only" notice banner on `admin/ad-packages/index.blade.php`. Disable create/update in `AdPackageController` (store/update return 410 with message).

### NA-04 — Cross-source boost guard
> Create a single helper (e.g. `App\Services\Ads\ListingBoostService::refresh(VendorListing $listing)`) that recomputes `is_ad_boosted`/`ad_boost_expires_at` from BOTH sources: active `VendorAdSubscription` (status active, ends_at future) and active listing_promotion `PaidAdBooking` (via creative `destination_reference_id`). Use it from `ExpireAdBoosts`, both `cancel()` methods and `PaidAdBoostSyncService`, replacing direct column writes. Add tests: cancelling a legacy subscription while a slot booking is active keeps the boost; expiry of one keeps the other's `expires_at`.

### NA-03 — Popup from slot bookings
> Extend `Api/Public/AdPopupController::show` so it also considers active `PaidAdBooking`s on slots with `target_type=listing_promotion` and `shows_popup=true` whose creative is approved (use fields already on `PaidAdCreative`: title/body/image/cta — inspect the model and map them), merging with legacy subscriptions and returning the same JSON shape (`id,title_en,title_ar,body_en,body_ar,image_url,cta_url,product_slug`). Escape/validate `cta_url` (http/https only). Verify `frontend/src/features/noon/ads/types.ts` still matches. Add a feature test for both sources and for "no active" → `popup:null`.

### NA-05 — Seeding & test coverage
> Register `NawiAdsSlotSeeder` in `backend/database/seeders/DatabaseSeeder.php` after the country/admin seeders and make it idempotent (already `firstOrCreate`). Add `tests/Feature/Ads/NawiAdsLifecycleTest.php` covering: seed → quote → book → approve → pay → boost active → listing sorts first → expire → boost cleared; plus the popup tier. Fix any bug the test uncovers only if it is within the Ad services; otherwise report it.

## 5. Execution order and acceptance
NA-02 → NA-01 → NA-04 → NA-03 → NA-05. Acceptance: no path grants a boost/popup without payment; one visible "Nawi Ads" admin entry; boost state derived from both sources; popup shows for slot bookings; `php artisan test --filter=Ad` green.
