# International Product Shipping — Design & Execution Plan
Author pass: Sonnet 5 | Generated: 2026-09-19 | Base commit: `7b579cc`

**Problem statement:** A product listed on one country storefront (e.g. a vendor listing under
`/uae/...`) should be purchasable by a customer whose shipping address is in a *different*
country (e.g. Egypt), with correct pricing/currency, a real cross-border shipping cost and ETA,
customs/duty handling, COD eligibility, and multi-leg tracking. Today the platform has **zero**
representation of this: every listing, carrier, and order is implicitly single-country
(`vendor_listings.country_id` is NOT NULL/single-value, `shipping_carriers.country_id` is
single-value, `orders.country_id` is single-value and assumed to match the listing's country).
This doc also resolves `QUESTION-1.md` (FIX-S1's blocked COD "international product" exemption)
as a side effect of Phase 1.

---

## Current-state findings (grounding — do not re-derive, verified against working tree)

- Country routing: `Route::prefix('{country}')->middleware('detect.country')` resolves a
  `Country` row (string PK) by `site_code` (`backend/app/Http/Middleware/DetectCountry.php`,
  `backend/app/Models/Country.php`).
- Every `vendor_listings` row has one `country_id` + its own `currency` (schema
  `mysql-schema.sql:6583-6645`). Selling the same product in multiple countries today means
  multiple separate listing rows, each independently priced/stocked. No `available_countries`
  concept ties them together.
- `orders.country_id` + `orders.currency` are single-value and assumed to equal the listing's
  country/currency.
- `fulfillment_model` enum on `vendor_listings` is `fbm|fbn|cross_dock` (not `fbp` — correct any
  future references). Admin/platform listings have no column and are implicitly `fbn`.
- Shipping infra already exists and is solid within a country: `Shipment`, `ShippingCarrier`
  (single-`country_id`), `ShippingMethod`, `ShipmentTrackingEvent`, `CategoryShippingMethod`,
  `CarrierPerformanceRating`, `CarrierClaim`, `InboundShipment`, `AramexCarrier`/`ManualCarrier`
  service classes.
- The **one** existing multi-country construct is `shipping_companies.served_countries`/
  `served_cities` — stored as **JSON**, which violates platform invariant #8 (no JSON blobs for
  structured relational data). Do not replicate this pattern for new work; call it out as
  pre-existing tech debt.
- `CodValidationService` has zero concept of "international" — confirmed, this plan defines it.
- `InventoryMovement` is append-only — any stock decrement for an international order still goes
  through it normally (warehouse stock never changes ownership until it ships; no new invariant
  needed there).

## Design decisions (the part that needs your sign-off before Phase 1 starts)

1. **Zone-based shipping, not O(n²) country pairs.** Model `shipping_zones` (e.g. "GCC", "MENA",
   "Rest of World") with a `shipping_zone_countries` pivot, then rate cards keyed on
   `(origin_country_id, destination_zone_id, carrier_id)`. A raw country-to-country pair table
   would work for a handful of corridors but doesn't scale past ~10 countries; zones do, and the
   platform already has multi-country ambition (`Country.is_launched`).
2. **Opt-in at the listing level, not global.** A vendor's UAE listing shipping to Egypt is a
   deliberate choice (customs, returns cost, product suitability), not a default. New pivot
   `vendor_listing_ship_destinations (vendor_listing_id, destination_country_id)` — explicit FK
   rows, never a JSON array, consistent with invariant #8. Admin/platform (`admin_listings`)
   listings default to "ships everywhere the platform operates" unless explicitly restricted,
   mirrored via `admin_listing_ship_restrictions` (opt-out list) since Nawi's own catalog is the
   common case and per-listing opt-in would be needless friction — see Q3 below to confirm this
   asymmetry is acceptable.
3. **"International order" is a computed relationship, not a stored flag.** An order is
   international iff `orders.country_id != sub_orders.listing_country_id` for at least one sub-
   order. Store the per-sub-order origin/destination pair explicitly on `sub_orders`
   (`origin_country_id`, already implied by the listing, kept redundant on the row for query/
   audit simplicity — money/shipping snapshots are always denormalized onto the order in this
   codebase already, e.g. `shipping_address_snapshot`).
4. **Currency: convert once, at order placement, and snapshot the rate.** Checkout displays in
   the *buyer's* storefront currency (per invariant #9, never sum across currencies). The vendor's
   listing price (their country's currency) is converted via a new `currency_exchange_rates`
   table (base-currency-integer-safe fixed-point rate, e.g. `rate_numerator`/`rate_denominator`
   BIGINTs, not float) snapshotted onto the `sub_order` as `fx_rate_numerator`/
   `fx_rate_denominator` + `fx_rate_captured_at`, so historical orders are immune to later rate
   changes. This is a new concept — the platform has no FX table today; flag as the single
   highest-risk new subsystem in this plan (money correctness).
5. **COD exemption resolved:** an order/cart item is "international" (per the definition in #3)
   → COD is exempted from the global/Supermal caps by definition (cross-border COD is
   operationally near-impossible to collect reliably, so international lines should default to
   **prepaid-only**, not COD-exempt-from-limit). This is a *stronger* answer than the original
   QUESTION-1 (exemption) — recommend prepaid-only for international lines rather than "no COD
   limit"; flagged as Q1 below, needs explicit confirmation since it changes the original ask.
6. **Tracking:** reuse `Shipment`/`ShipmentTrackingEvent` for the domestic leg at both ends, add a
   new `international_shipment_legs` table
   (`shipment_id`, `leg_type` enum(`export`,`customs`,`linehaul`,`import`,`last_mile`),
   `carrier_id` nullable, `external_tracking_number`, `status`, timestamps) so a single logical
   shipment can show a multi-leg progress trail (export scan → customs cleared → linehaul →
   import → last-mile carrier → delivered) without overloading the existing single-carrier
   `shipments.status` enum.

## Open questions (need your answer before Phase 3+ starts — Phase 1/2 can proceed regardless)

- **Q1:** International order lines — hard-block COD entirely (prepaid only), or allow COD with
  a *separate*, lower cap (needs its own setting)? Recommendation: prepaid-only for v1.
- **Q2:** Who owns customs/duty cost — absorbed into the displayed price (DDP, "Delivered Duty
  Paid"), or collected from the customer on delivery (DDU)? This changes checkout line items and
  is a legal/finance decision, not engineering. Recommendation: DDP for v1 (simpler UX, matches
  "show final price" expectations), revisit DDU later if margins require it.
- **Q3:** Confirm the admin/platform listing default-ships-everywhere-with-opt-out vs. vendor
  listing opt-in asymmetry (design decision #2) is acceptable, or whether platform listings
  should also be opt-in per destination.
- **Q4:** Return/reverse logistics for a cross-border order — same `ReturnRequestService` flow,
  or does an international return need its own policy (who pays return shipping, customs on
  return)? Out of scope for Phase 1-4 below; needs its own follow-up doc once Q1-Q3 land.

---

## Phases — each is a self-contained sub-agent prompt

Run phases in order; each phase's prompt is written to be handed to a fresh sub-agent with no
other context. Do not start a phase whose "Blocked on" isn't satisfied.

### Phase 1 — Schema foundation (migrations only, no logic)
**Blocked on:** nothing — can start immediately.
**Prompt:**
> Repo: /var/www/marketplace (Laravel 11). Add migrations (correct `YYYY_MM_DD_HHMMSS` prefix
> based on the latest file in `backend/database/migrations/`) for:
> 1. `shipping_zones` (id UUID via HasUuids, name_en, name_ar, is_active, timestamps).
> 2. `shipping_zone_countries` (shipping_zone_id FK, country_id FK, composite unique, no
>    surrogate PK needed beyond the pair — but follow this codebase's convention, check an
>    existing pivot migration first for the exact pattern used, e.g. `product_country_buybox`).
> 3. `vendor_listing_ship_destinations` (vendor_listing_id FK, destination_country_id FK,
>    composite unique, created_at).
> 4. `admin_listing_ship_restrictions` (admin_listing_id FK, destination_country_id FK, composite
>    unique, created_at) — an opt-OUT list; absence of a row means "ships there".
> 5. `international_shipping_rates` (id UUID, origin_country_id FK, destination_zone_id FK to
>    shipping_zones, carrier_id FK nullable to shipping_carriers, base_fee_minor BIGINT,
>    per_kg_fee_minor BIGINT, customs_fee_flat_minor BIGINT nullable, eta_days_min SMALLINT,
>    eta_days_max SMALLINT, is_active, timestamps) — all money columns BIGINT, no floats.
> 6. `currency_exchange_rates` (id UUID, from_currency_code CHAR(3), to_currency_code CHAR(3),
>    rate_numerator BIGINT, rate_denominator BIGINT, effective_at TIMESTAMP, created_at) —
>    append-only, never updated in place (new row per rate change, most-recent-by-effective_at
>    wins). Add composite index (from_currency_code, to_currency_code, effective_at).
> 7. On `sub_orders`: add nullable `origin_country_id` FK, `is_international` BOOLEAN GENERATED
>    ALWAYS AS (`origin_country_id IS NOT NULL AND origin_country_id != <the order's country_id
>    column, via a generated expression or a trigger — check whether this DB version supports
>    cross-column generated columns; if not, compute it in the application layer instead and drop
>    this column>`), `fx_rate_numerator` BIGINT nullable, `fx_rate_denominator` BIGINT nullable,
>    `fx_rate_captured_at` TIMESTAMP nullable.
> 8. On `international_shipment_legs` — new table: id UUID, shipment_id FK, leg_type
>    enum(`export`,`customs`,`linehaul`,`import`,`last_mile`), carrier_id FK nullable,
>    external_tracking_number VARCHAR nullable, status VARCHAR, occurred_at TIMESTAMP nullable,
>    timestamps.
>
> Do NOT run the migrations. Do NOT touch `database/schema/mysql-schema.sql` (auto-generated —
> note in your report that `php artisan schema:dump --prune` must run after these apply on
> server). Write corresponding Eloquent models with `HasUuids` for new UUID-PK tables, following
> this codebase's existing model conventions (check `ShippingCarrier.php` or `Country.php` as a
> template for casts/fillable). Confirm every money column is BIGINT via `php artisan tinker`-free
> static read of the migration file — do not add any `/100` or `*100`. Run `php -l` on every new
> file. Git commit only these new files, message describing the schema foundation. End with:
> `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.

### Phase 2 — Rate & FX calculation services (pure logic, unit-testable, no checkout wiring yet)
**Blocked on:** Phase 1 merged.
**Prompt:**
> Repo: /var/www/marketplace. Implement two services under `backend/app/Services/Shipping/`:
> 1. `InternationalShippingRateService` — given a destination country + total weight (grams) +
>    origin country, resolve the destination's `shipping_zone_countries` row, look up the
>    matching `international_shipping_rates` row (origin_country_id + destination_zone_id,
>    is_active), and return `base_fee_minor + per_kg_fee_minor * ceil(weight_grams / 1000)` plus
>    `customs_fee_flat_minor` (0 if null) as a single BIGINT total in the *origin* listing's
>    currency, plus the ETA range. Throw a typed exception (follow existing exception patterns in
>    `backend/app/Exceptions/`) if no rate exists for that corridor — this must be a hard stop,
>    never a silent fallback that lets an unpriced international order through.
> 2. `CurrencyConversionService` — given an amount (BIGINT minor units) + from/to currency codes,
>    look up the most recent `currency_exchange_rates` row by `effective_at <= now()` and return
>    `floor(amount * rate_numerator / rate_denominator)` plus the numerator/denominator/captured_at
>    used (so callers can snapshot them). Same-currency calls should short-circuit and return the
>    amount unchanged (numerator=denominator=1) without a DB lookup. Throw if no rate row exists.
>    Never use float arithmetic anywhere in this service — integer-only BIGINT math throughout.
> Write PHPUnit tests under `backend/tests/Unit/Services/Shipping/` covering: no-rate-found
> throws, correct fee math with weight rounding up to the next kg, same-currency short-circuit,
> and rate-selection picks the most recent `effective_at` row when multiple exist. Run
> `php artisan test --filter=InternationalShippingRateServiceTest` and
> `--filter=CurrencyConversionServiceTest` (or this repo's actual test-run convention — check
> `backend/tests/` structure first) and confirm they pass. Git commit, message describing the two
> services + tests. End with: `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not
> push.

### Phase 3 — Checkout & COD integration
**Blocked on:** Phase 2 merged, **and Q1 + Q2 answered** (do not start without explicit answers —
these change the checkout line-item shape and the COD gate).
**Prompt (fill in {Q1_ANSWER} and {Q2_ANSWER} before dispatching):**
> Repo: /var/www/marketplace. Wire international shipping into checkout, given these product
> decisions: Q1 (COD for international lines) = {Q1_ANSWER}; Q2 (duty model) = {Q2_ANSWER}.
> 1. In `backend/app/Services/Checkout/CartLineSource.php` and `CheckoutPricingEngine.php`,
>    detect when a cart line's listing country differs from the order's destination country
>    (`orders.country_id` / the buyer's selected storefront country). Validate against
>    `vendor_listing_ship_destinations` (or `admin_listing_ship_restrictions` for admin listings)
>    — reject the line with a typed, user-facing error if that destination isn't served.
>    Consult `docs/plans/international_product_shipping.md`'s design decisions #2/#3 in this repo
>    for the exact opt-in/opt-out semantics per listing type.
> 2. For international lines, call `InternationalShippingRateService` for the shipping cost line
>    item (do not use the existing domestic `ShippingMethodResolverService` for these lines), and
>    `CurrencyConversionService` to convert the listing-currency price into the buyer's storefront
>    currency, snapshotting `fx_rate_numerator`/`fx_rate_denominator`/`fx_rate_captured_at` onto
>    the `sub_order` at the point of order placement (not at cart-add time — rates can move).
> 3. In `backend/app/Services/Customer/CodValidationService.php`, apply the Q1 answer: if
>    prepaid-only, reject COD as the payment method whenever any cart line is international
>    (clear error message); if a separate lower cap, add that setting (name it
>    `cod_international_max_amount` for consistency with the existing `cod_global_max_amount`/
>    `cod_supermall_max_amount` naming) and enforce it.
> 4. If Q2 = DDP: fold `customs_fee_flat_minor` into the displayed subtotal/total as a named line
>    item (e.g. `customs_duty`) rather than hiding it inside `shipping_fee`, so the customer sees
>    it itemized — this also needs a new field in the checkout `order_summary` payload
>    (`PricedCart.php`, mirroring how `loyalty_discount` was recently added) and a corresponding
>    frontend line in `frontend/src/features/noon/checkout/payment-summary.tsx` (green-negative
>    style isn't right here — it's a charge, not a discount; follow the `shipping`/`cod_fee` line
>    styling instead). Add locale keys to both `locale/en.json` and `locale/ar.json`.
> 5. Confirm no `/100`/`*100` introduced, all new money fields BIGINT end-to-end frontend and
>    backend. Re-read every changed file. Run `php -l` on changed PHP files and
>    `npx tsc --noEmit` scoped to changed frontend files. Git commit (may need 2-3 commits if
>    backend/frontend split makes sense — use judgment, but keep each commit buildable/coherent).
>    End each with: `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.

### Phase 4 — Multi-leg tracking
**Blocked on:** Phase 1 merged (does not need Phase 3 — can run in parallel with it once schema
exists).
**Prompt:**
> Repo: /var/www/marketplace. Extend shipment tracking for international orders using the
> `international_shipment_legs` table from Phase 1.
> 1. Add an `InternationalShipmentLeg` model + relation on `Shipment` (`hasMany`).
> 2. Add a service `backend/app/Services/Shipping/InternationalTrackingService.php` with a method
>    to append a new leg event (`recordLeg(Shipment $shipment, string $legType, ?string $carrierId,
>    ?string $externalTrackingNumber, string $status, ?Carbon $occurredAt)`) — this is
>    append-only (new row per event, mirroring `InventoryMovement`'s pattern in this codebase —
>    never update an existing leg row's status in place, always insert a new leg row so history
>    is preserved; if you need "current status", compute it as the latest row per leg_type).
> 3. Expose a read endpoint (follow this codebase's existing customer order-tracking endpoint
>    pattern — find it first, e.g. under `routes/api_customer_v1.php`) that returns the full leg
>    history for an international shipment, ordered chronologically, for the order-tracking page.
> 4. Add a minimal frontend tracking timeline addition (find the existing order-tracking page
>    under `frontend/src/features/noon/` and extend it, don't build a new page) that renders the
>    extra legs when present, falling back to the existing single-status display for domestic
>    shipments (i.e. this must be fully backward-compatible — most shipments have zero
>    international legs).
> Re-read every changed file, run `php -l` and `npx tsc --noEmit` scoped appropriately. Git
> commit. End with: `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.

### Phase 5 — Admin tooling
**Blocked on:** Phase 1 merged.
**Prompt:**
> Repo: /var/www/marketplace. Build the admin surface for this feature, reusing this codebase's
> existing admin CRUD conventions (find an existing simple admin resource controller + Blade
> index/edit view pair as a template, e.g. check `Admin\SettingsController` or a similar simple
> resource under `backend/app/Http/Controllers/Admin/`):
> 1. CRUD for `shipping_zones` and their country membership (`shipping_zone_countries`).
> 2. CRUD for `international_shipping_rates` (origin country × destination zone × carrier →
>    fees/ETA).
> 3. CRUD for `currency_exchange_rates` (append-only — the UI should only ever INSERT a new rate
>    row, never edit an existing one, consistent with the model design in Phase 1).
> 4. A per-listing "ships to" destination picker on the existing vendor-listing edit view (find
>    it, likely under `backend/resources/views/admin/vendor_listings/` or wherever
>    `vendor_listings` are edited) writing to `vendor_listing_ship_destinations`, and the
>    equivalent opt-out picker for admin listings writing to `admin_listing_ship_restrictions`.
> Re-read every changed/created file. Run `php -l` on all PHP files. Add route entries following
> this codebase's existing admin route file conventions and naming. Git commit. End with:
> `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.

### Phase 6 — Customer-facing UI polish
**Blocked on:** Phase 3 merged.
**Prompt:**
> Repo: /var/www/marketplace (Next.js frontend). Add customer-facing signals for international
> products, reusing existing badge/UI components (check how `promo_badges`/`AnimatedBadge` are
> rendered on product cards and PDP from recent work in this repo for the pattern to follow):
> 1. A "Ships from {origin country flag/name}" badge or note on the PDP and product card when a
>    listing's `vendor_listing_ship_destinations` includes the currently-selected storefront
>    country and that country differs from the listing's own `country_id`.
> 2. Show the ETA range (from `InternationalShippingRateService`) and, if Q2={DDP}, a "customs
>    included" note, or if {DDU}, a "customs fees may apply on delivery" disclaimer — reuse the
>    Q2 answer resolved in Phase 3.
> 3. Locale keys in both `locale/en.json` and `locale/ar.json`.
> Re-read every changed file, run `npx tsc --noEmit` scoped to changed files. Git commit. End
> with: `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.

---

## Execution tracking
_To be filled in as phases run:_

| Phase | Status | Blocked on | Commit(s) |
|---|---|---|---|
| 1 — Schema foundation | Not started | — | — |
| 2 — Rate/FX services | Not started | Phase 1 | — |
| 3 — Checkout/COD | Not started | Phase 2, Q1, Q2 | — |
| 4 — Multi-leg tracking | Not started | Phase 1 | — |
| 5 — Admin tooling | Not started | Phase 1 | — |
| 6 — Customer UI | Not started | Phase 3 | — |

## Migrations needed (run on server, in this order)
- All Phase 1 migration files, then `php artisan schema:dump --prune`.
- Phase 3's `cod_international_max_amount` setting seed migration, if Q1 answer requires it.

## Non-negotiable constraints (carried over from platform invariants)
- All new money columns: BIGINT, no floats, no stray `/100`/`*100` outside legitimate percentage
  math (VAT/commission/coupon %).
- All new PKs: UUID via `HasUuids`.
- No JSON blobs for structured relational data (this explicitly means: do not copy
  `shipping_companies.served_countries`'s JSON pattern for any new multi-country modeling here —
  use the zone/pivot tables specified above instead).
- `InventoryMovement`/append-only pattern is the model to follow for `currency_exchange_rates`
  and `international_shipment_legs` — insert-only, never mutate history.
- Multi-currency: every display/calculation stays scoped to one currency at a time; the FX
  conversion happens exactly once, at order placement, with the rate snapshotted — never
  re-derive or re-sum across currencies later.
