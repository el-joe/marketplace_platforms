# FIXES.md — Platform Audit Results
Generated: 2026-09-18 | Commit: f3ff324 (HEAD, main)

Scope: items A–J from the product-owner brief. Every claim below is based on reading the current
working tree (not the brief's stale line numbers/paths, several of which have moved since the
brief was written).

---

## CRITICAL (breaks functionality)

_None found among items A–J. `AutoCompleteOrdersJob` / `CheckSlaBreachJob` are registered
(`backend/routes/console.php:28-29`), the marketer checkout groupBy crash (J) is already fixed,
and the sponsored-products category filter (G) is already applied — see DONE notes below._

---

## HIGH (wrong output, financial impact, or broken UX)

### FIX-H1: Payment summary omits the loyalty-points discount line item
- **File(s):**
  - `frontend/src/features/noon/checkout/payment-summary.tsx` (component, lines 18–133)
  - `frontend/src/features/noon/checkout/types/checkout.type.ts` (`OrderSummary` interface, commented block lines 75–85, and the live `IPrepareCheckout`/order_summary shape around line 119+)
  - Backend already emits the field: `backend/app/Services/Checkout/PricedCart.php:25` (`loyaltyDiscount` readonly prop) and `:44`/`:61` (`'loyalty_discount' => $this->loyaltyDiscount` in `toArray()`), consumed at `backend/app/Http/Controllers/Customer/CheckoutController.php:406,474` (`'order_summary' => $summary` where `$summary = $pricedCart->toArray()`).
- **Root cause:** `CheckoutCalculationService`/`CheckoutPricingEngine` already compute and return `loyalty_discount` in the `order_summary` payload (confirmed present in `PricedCart::toArray()`). `PaymentSummary` renders `cod_fee`, `warranty_total`, and `gift_card_applied` but has no block for `loyalty_discount`, so a customer who redeems loyalty points never sees that discount reflected in the line-item breakdown even though it is subtracted from `total` server-side — money "disappears" from the customer's view without explanation.
- **Fix:** Add a `checkoutSummary.loyalty_discount > 0` conditional block to `payment-summary.tsx` mirroring the `gift_card_applied` block (green, negative-signed `Price`), add `loyalty_discount: number` to the `OrderSummary` type, and add a `loyaltyDiscount` / equivalent translation key to `checkout` locale namespace (en + ar).
- **Acceptance test:** Apply loyalty points at checkout so `loyalty_discount > 0` in the `/prepare` response; `PaymentSummary` renders a "Loyalty discount" row equal to that amount, and `total` shown still equals `subtotal - discount - loyalty_discount + shipping + cod_fee + warranty_total - gift_card_applied + tax` (minus wallet deduction).

### FIX-H2: Ad-package "listing boost" ranking is not applied to browse/search results
- **File(s):** `backend/app/Services/Customer/ProductQueryService.php`, `backend/app/Models/AdPackage.php`, `backend/database/migrations/2026_09_12_000001_create_ad_packages_table.php` (tier enum: `serious`, `serious_featured`), `backend/app/Models/VendorAdSubscription.php`
- **Root cause:** `ProductQueryService` has no reference to `boost_priority`, `ad_packages`, or `VendorAdSubscription` at all — only `SponsoredProductService::inject()` (a *separate* slot-injection mechanism at fixed positions 1/5/9, driven by `ad_campaigns`/`ad_campaign_products`) affects result order. A vendor who buys a "serious" (non-featured) Nawi ad package gets no ranking boost in organic browse results — the feature described in the brief ("listing boost & popup logic") only has the popup half wired (see DONE note below), not the sort/ranking half.
- **Fix:** In `ProductQueryService`'s base listing query, left-join active `vendor_ad_subscriptions` (via `vendor_listing_id`, `status = active`, `ends_at > now()` or null) joined to `ad_packages` on `tier`, and add a boost term to the `ORDER BY` (e.g. `ORDER BY (subscription exists) DESC, tier weight DESC, <existing sort>`), gated so it only reorders within relevance and never overrides an explicit customer sort (price/rating) if one is requested. Needs a small scoring column or `CASE` expression — no schema change required if `ad_packages`/`vendor_ad_subscriptions` already carry `tier` and `is_active`/`ends_at`.
- **Acceptance test:** A vendor listing with an active "serious" or "serious_featured" ad subscription appears above equivalent non-boosted listings on the default category sort, and boosted position does not change when the customer explicitly sorts by price/newest (those sorts should override boost).

### FIX-H3: Nawi Ads popup (serious_featured tier) has no frontend consumer
- **File(s):** `backend/app/Http/Controllers/Api/Public/AdPopupController.php` (exists, returns popup payload), `backend/routes/api_public.php:21` (`GET active-popup`, routed and public), frontend: no match for `active-popup` anywhere under `frontend/src`.
- **Root cause:** Backend is fully wired — `AdPopupController::show()` picks a random active `VendorAdSubscription` on the `serious_featured` tier with popup copy filled in, and the route is public. But no frontend component calls `active-popup` or renders a popup — grep across `frontend/src` for `active-popup` and for popup components under `features/noon` returns nothing relevant (only generic UI popovers/dropdowns, unrelated). So `serious_featured` vendors pay for a popup placement that never displays.
- **Fix:** Add a frontend popup component (e.g. `frontend/src/features/noon/ads/serious-featured-popup.tsx`) that fetches `GET /active-popup` on storefront mount (once per session, e.g. gated by a `sessionStorage` "seen" flag), and renders `title_en/ar`, `body_en/ar`, `image_url`, with a CTA linking to `product_slug`. Mount it in the root customer layout.
- **Acceptance test:** With an active `serious_featured` subscription seeded with popup fields, loading the storefront shows the popup once per session; clicking the CTA navigates to `product_slug`.

---

## MEDIUM (missing feature that has DB schema but no UI/route)

### FIX-M1: Travel search filters have no frontend inputs
- **File(s):** `backend/app/Http/Controllers/Customer/BrowseController.php:189-230` (`browseTravel`, validates and applies `country_id`, `city_id`, `date_from`, `date_to`), `frontend/src/features/flights/travel-packages/index.tsx` (only reads `page`, `category` from `searchParams`), `frontend/src/features/flights/api/travel-packages.actions.ts` (`getTravelPackages` only forwards `categoryId`/`page`/`perPage` — never `country_id`/`city_id`/`date_from`/`date_to`).
- **Root cause:** The brief's `TravelController` (`backend/app/Http/Controllers/Storefront/TravelController.php`) is a separate admin/agency-portal-facing Blade controller using `country`/`city`/`departure_from`/`departure_to` — not what the Next.js customer app calls. The customer app actually calls `GET /browse/travel/{id}` → `BrowseController::browseTravel()`, which already validates and applies `country_id`, `city_id`, `date_from`, `date_to` (lines 215-230) via `ListingQueryService::paginateTravelPackages()`. The frontend simply never sends these params and has no filter UI (`TravelHero`, `CategoryTabs` components only handle hero copy and category tabs, no date/country/city inputs).
- **Fix:** Add filter controls (country select, city select, date-range picker) to `frontend/src/features/flights/travel-packages/`, wire them into `searchParams`, and extend `getTravelPackages()`/`ListTravelPackagesFilters` to forward `country_id`, `city_id`, `date_from`, `date_to` to the existing, already-working backend endpoint.
- **Acceptance test:** Selecting a country/city/date range on the travel listing page updates the URL query params and the returned package list is filtered server-side accordingly (verified against `BrowseController::browseTravel`'s existing validation).

---

## LOW (cosmetic, locale keys, labels)

_None identified beyond the locale keys needed for FIX-H1 (loyalty discount label) — folded into that fix rather than listed separately._

---

## SKIP (needs business decision before code — document, do not implement)

### FIX-S1: COD limits — "international products" exemption has no schema concept
- **Reason:** COD limits are otherwise already implemented and working: `backend/app/Services/Customer/CodValidationService.php` enforces `cod_global_max_amount` and a separate `cod_supermall_max_amount` (scoped via `cod_supermall_category_id`'s nested-set `lft`/`rgt` range), both settings seeded by `backend/database/migrations/2026_09_12_173000_add_cod_limit_settings.php` under the generic `Setting` model (`category = 'orders'`), editable through the existing dynamic admin Settings UI (`Admin\SettingsController` — no bespoke `cod_limit_settings` table needed, and none exists, by design). Nawi/platform products are already exempt (`$item->adminListing !== null` check, `CodValidationService.php` line ~30). However, the brief's second exemption — **"international products"** — has no corresponding concept anywhere in the schema or models: no `is_international`, `ships_internationally`, or `international_shipping` field exists on `VendorListing`, `AdminListing`, `Product`, or `Country` (grepped across `backend/app` and `mysql-schema.sql`, zero hits).
- **Open question:** What defines an "international product" for COD-exemption purposes — country of the seller vs. country of fulfillment vs. an explicit per-listing flag vs. cross-border shipping method? Once defined, the fix is a one-line addition to `CodValidationService::validate()`'s exemption check (`if ($item->adminListing !== null || $this->isInternational($item)) continue;`), but the field/logic to determine "international" must be specified by product first — this may also require a migration if a new column is chosen.

---

## DONE (already correctly implemented — verified this pass, no action needed)

- **G. Sponsored products category filter** — `backend/app/Services/Customer/SponsoredProductService.php::fetchSponsored()` (lines 201-249) already accepts and applies `$categoryIds` via `whereHas('productVariant.product', ...)`, and also mirrors attribute filters from `ProductQueryService::applyFilters`. `PROMPT-sponsored-category-filter.md`'s fix is applied. *(Side note, not in scope A–J: `fetchSponsored()`'s `ac.ends_at` clause at lines 214-218 uses an un-grouped `->orWhere('ac.ends_at', '>', now())` after two chained `->where()`/`whereNull()` calls, which is a classic Eloquent OR-precedence bug that can leak expired campaigns into results regardless of country/status — worth a follow-up look though it wasn't asked for in items A–J.)*
- **F. Coupon `shipping_type_restriction` enforcement** — `backend/app/Services/Checkout/CheckoutPricingEngine.php:801-809` checks the coupon's restriction against each cart line's derived `shipping_type` (fbn/fbp/fbm) and rejects the coupon with a typed error when any line mismatches.
- **I. `order.completed` automation** — Both `AutoCompleteOrdersJob` and `CheckSlaBreachJob` exist under `backend/app/Jobs/` and are registered in `backend/routes/console.php:28-29` (`everyFifteenMinutes()` / `dailyAt('02:00')`).
- **J. Marketer checkout crash** — `backend/app/Http/Controllers/Customer/CheckoutController.php` no longer groups by raw `$item->vendorListing->vendor_id`; it resolves a `CartLineSource`/`sellerParty` per item first (see `resolveMarketerCartItems()` at line 1537 and the `groupBy(fn ($item) => $cartLineSources[$item->id]->sellerParty)` at line 1619), which is null-safe for marketer-listing items. No null-pointer risk found on the current code path.
- **E. OMR currency symbol** — `frontend/src/helpers/get-currency-symbol.ts:107` already maps `OMR: "ر.ع."`.
- **B. COD limits (core enforcement)** — see FIX-S1 above; everything except the "international products" exemption is implemented and working, including the Supermal-specific limit.
- **H. `cod_fee` / `warranty_total` / `gift_card_applied`** — all three already render conditionally in `payment-summary.tsx` (lines 62-96). Only `loyalty_discount` is missing — see FIX-H1.
- **A.2 (popup route wiring)** — route exists and is public (`backend/routes/api_public.php:21`); only the frontend consumer is missing — see FIX-H3.
- **FIX-M2. Influencer body/size measurements in sample-dispatch UI** — already fully implemented in both views. `backend/resources/views/partner/marketer_campaigns/show.blade.php:397-444` renders an "Influencer Sizes" block (`influencer_measurements` label) inside an `@if ($isInfluencer && $sampleProfile)` row, iterating `clothing_size`, `shirt_size`, `pants_size`, `dress_size`, `abaya_size`, `shoe_size` (+ `shoe_size_system`), `chest_cm`, `waist_cm`, plus `hip_cm`/`height_cm`/sleeve/item-length fields, each only shown `@if(!is_null($value) && $value !== '')`; `measurements_notes` shown when present (lines 430-435); an `@elseif ($isInfluencer && !$sampleProfile)` branch (lines 438-443) shows a "no measurements on file" notice. `backend/resources/views/admin/marketer_campaigns/show.blade.php:605-654` mirrors the identical pattern for the admin view. No gap found — no code changes made.

---

## Execution Summary
_Diagnosis only in this pass — no fixes implemented yet per instructions. To be filled in during the execution pass:_

| Fix ID | Status | Files changed | Notes |
|--------|--------|---------------|-------|
| FIX-H1 | Not started | — | Minimal frontend-only change |
| FIX-H2 | Not started | — | Needs ORDER BY design decision (boost vs. explicit sort precedence) |
| FIX-H3 | Not started | — | New frontend component + mount point |
| FIX-M1 | Not started | — | Frontend filter UI + param plumbing only; backend already works |
| FIX-M2 | DONE, no action needed | — | Sizes already rendered in both `show.blade.php` views (partner:397-444, admin:605-654) |
| FIX-S1 | Blocked | — | Needs product-owner answer; see QUESTION-1.md when written |

## Migrations needed (run on server)
- None required for FIX-H1, FIX-H3, FIX-M1 (all additive UI/query work on existing schema).
- FIX-H2 may need a lightweight index on `vendor_ad_subscriptions(vendor_listing_id, status, ends_at)` if one doesn't already exist — to be confirmed during implementation.
- FIX-S1 will need a migration once the "international product" definition is decided (new column or derivation logic).

## Questions requiring product owner input
- FIX-S1: definition of "international product" for COD exemption purposes (see above). To be written as `QUESTION-1.md` in the execution pass.
