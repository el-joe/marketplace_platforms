# International Product Shipping — Design & Execution Plan (v2)
Author pass: Sonnet 5 | Generated: 2026-09-19 | Base commit: `3591d89`
Rewritten after reading the actual schema dump (`marketplace_platform.sql`, 40k lines) — v1 of
this doc guessed at a zone-based design without checking what already exists; this version is
grounded in it and supersedes v1 entirely.

**Problem statement:** A product listed on one country storefront (e.g. a vendor listing under
`/uae/...`) should be purchasable by a customer whose shipping address is in a different country
(e.g. Egypt), with correct pricing/currency, a real cross-border shipping cost and ETA, customs
handling, COD eligibility, and tracking. This is genuinely missing end-to-end today.

---

## What already exists (verified against `marketplace_platform.sql` + app code — do not rebuild these)

- **Country routing:** `Route::prefix('{country}')->middleware('detect.country')` resolves a
  `countries` row by `site_code` (e.g. `uae`). `countries` has `currency_code`, `vat_rate`,
  `cod_available`, `is_launched` — string/UUID PK.
- **Domestic shipping is already rich:** `shipping_zones` (scoped to one `country_id`, has
  `cities`), `shipping_rates` (`origin_zone_id` → `destination_zone_id`, both zones — this is
  intra-country zone-to-zone routing for warehouse dispatch, NOT cross-country), `delivery_zones`
  (a second, newer-looking per-country zone table with city_ids/polygon — likely overlapping with
  `shipping_zones`, not our concern to reconcile here), `shipping_weight_slabs`,
  `warehouse_exceptional_zones`, `warehouse_shipping_surcharges`, `vendor_city_shipping_surcharges`,
  `shipping_fallback_rules`. All of this is single-country.
- **`shipping_carriers`**: `country_id` is nullable — a carrier row can already be
  country-agnostic. `supports_cod`, `supports_returns`, `tracking_url_pattern` already exist.
- **`shipping_companies`**: has `served_countries`/`served_cities` as **JSON** — the one existing
  multi-country construct, and it's a pre-existing violation of the "no JSON for structured
  relational data" invariant. Do not copy this pattern for new work.
- **`marketplace_shipping_rules`**: has the exact `vendor_listing_id` nullable / `admin_listing_id`
  nullable pair pattern this codebase already uses to attach a rule to either listing type — reuse
  this pattern for our new eligibility table instead of inventing two separate tables (v1 of this
  doc wrongly proposed two).
- **`product_countries` / `product_country_settings`**: per-`(product_id, country_id)`
  availability + localized overrides — this is the "is this product sellable/visible in country
  X at all" gate. It has nothing to do with physical shipping capability; it's a separate,
  earlier gate that any international-shipping check must sit behind.
- **`vendor_listings.is_global_shipping` / `global_system_type`** (`express_fbn` /
  `merchant_fbp` / `marketplace`): **red herring** — despite the Arabic comment ("النظام العالمي" /
  "the global system"), this is Nawi-express-fulfillment vs. merchant-fulfillment classification
  (confirmed via `ListingShippingResolver.php:60` — it only gates `category_shipping_methods`
  eligibility columns `is_available_for_express_fbn`/`is_available_for_merchant_fbp`). It is
  unrelated to cross-border shipping. Do not repurpose it.
- **`vendor_listings.country_id`** is NOT NULL, single value — one listing = one country, its own
  `price`/`currency`. Selling into another country needs either (a) a second listing row in that
  country, or (b) genuine cross-border fulfillment from the first listing's warehouse — this plan
  is about (b).
- **`orders.country_id`** / **`orders.currency`**: single value, assumed today to be the buyer's
  storefront country/currency and (implicitly) to match every line's origin. `sub_orders` already
  carries its own `carrier_id`, `tracking_number`, `shipping`, `carrier_shipping_cost`,
  `shipping_gap`, `fulfillment_model` (`fbm`/`fbn` only at sub-order level, `cross_dock` exists
  only on `vendor_listings`).
- **`shipments`** (per sub-order, one carrier, one tracking number, status enum) +
  **`shipment_tracking_events`** (append-only, `shipment_id` FK, free-text `status`/`description`/
  `location`/`occurred_at`, `raw_payload` JSON for carrier webhook payloads — this is fine, it's
  an opaque passthrough field, not structured data we're choosing to normalize). **This table
  already fully supports a multi-leg tracking timeline** (v1 of this doc wrongly proposed a new
  `international_shipment_legs` table — not needed, just insert more events with descriptive
  `status`/`location`, e.g. "export_scan" / "customs_cleared" / "linehaul" / "import_scan").
- **No FX/currency-conversion table anywhere.** No customs/duty/tariff/HS-code table anywhere. No
  country-to-country shipping rate table anywhere. These three are the real gaps.
- **`CodValidationService`**: confirmed no international concept (per prior audit).

## What's genuinely missing (the actual scope of this feature)

1. A way to say "listing X (in country A) can physically ship to country B" — doesn't exist.
2. A country-pair (or listing-country → destination-country) shipping rate/ETA — doesn't exist.
3. Currency conversion with an auditable snapshot per order — doesn't exist.
4. Customs/duty handling in the price and checkout display — doesn't exist.
5. COD policy for cross-border orders — doesn't exist (this is `QUESTION-1.md` / FIX-S1).
6. A small extension to already-fine tracking (`shipment_tracking_events`) so a multi-leg
   international journey is distinguishable from a domestic one — mostly exists, needs 2 columns.

## Design decisions

1. **No new "zone" abstraction.** Direct `origin_country_id` → `destination_country_id` rate
   rows. The existing `shipping_zones` name is taken and means something else (intra-country);
   reusing or shadowing it would be confusing and wrong. Table: `international_shipping_rates`,
   column names mirrored from the existing `shipping_rates` table for consistency
   (`base_fee`, `rate_per_kg`, `free_shipping_threshold`, `cod_extra_fee`-style naming, all
   BIGINT).
2. **One eligibility table, not two**, following the `marketplace_shipping_rules` precedent
   exactly: `international_shipping_eligibility (vendor_listing_id nullable, admin_listing_id
   nullable, destination_country_id, is_active)`. A row = "this listing can ship to this
   destination." Uniform opt-in for both vendor and admin listings (simpler mental model than
   v1's opt-in/opt-out asymmetry) — Nawi's own catalog can be bulk-seeded "ships everywhere
   active+launched" via a one-off artisan command/seeder rather than needing different schema
   semantics for admin listings. **This still needs your confirmation (was Q3, see below).**
3. **Currency: convert once, at order placement, snapshot the rate.** New append-only
   `currency_exchange_rates` table (`from_currency_code`, `to_currency_code`,
   `rate_numerator`/`rate_denominator` BIGINT — never float, never a plain decimal rate), most
   recent `effective_at` row wins. `sub_orders` gets `origin_country_id`,
   `fx_rate_numerator`/`fx_rate_denominator`/`fx_rate_captured_at` (nullable — null means
   domestic, no conversion happened). "Is this sub-order international" is computed as
   `origin_country_id !== orders.country_id` — no generated column, no extra boolean to keep in
   sync, computed in a model accessor.
4. **COD: prepaid-only for international lines (v1 recommendation, adopted).** Cross-border COD
   collection is operationally unreliable; this is a stronger, simpler answer than "exempt from
   the cap" and directly resolves `QUESTION-1.md`.
5. **Duty: DDP (Delivered Duty Paid) for v1 (recommendation, adopted).** `customs_fee_flat` on
   `international_shipping_rates` gets folded into the checkout total as its own named line item
   (`customs_duty`), not hidden inside `shipping`. Shown to the customer before payment, not
   collected on delivery.
6. **Tracking: extend, don't replace.** Add nullable `carrier_id` (FK `shipping_carriers`) and
   `external_tracking_number` (VARCHAR) to `shipment_tracking_events`, so an international
   shipment's customs/linehaul legs — which may run under a different carrier's tracking number
   than the primary `shipments.tracking_number` — can be recorded per-event without inventing a
   parallel table. `shipments.status` stays the coarse top-level state; the events table carries
   the detailed multi-leg trail, exactly as it does today for domestic carrier webhooks.

## Open questions (need your answer before Phase 3 starts — Phases 1, 2, 4, 5 can proceed now)

- **Q3 (was the only real open item):** Confirm uniform opt-in eligibility (design decision #2)
  for both vendor and admin listings is acceptable — i.e. Nawi's own catalog needs an explicit
  seed/backfill of `international_shipping_eligibility` rows rather than shipping everywhere by
  default with no row. Proceeding with this by default unless told otherwise.
- **Q4 (deferred, not blocking Phases 1-6):** Reverse logistics/returns for a cross-border order —
  needs its own follow-up doc once this feature is live and has real order volume to reason about.

Q1 (COD policy) and Q2 (duty model) are resolved above and adopted — proceeding without further
sign-off per your instruction to just execute.

---

## Phases — each is a self-contained sub-agent prompt

### Phase 1 — Schema foundation (migrations + models only)
**Blocked on:** nothing.
**Prompt:**
> Repo: /var/www/marketplace (Laravel 11, PHP 8.3). Read
> `docs/plans/international_product_shipping.md` in full first — it explains exactly why each
> table below is shaped this way and what already exists that you must NOT duplicate (domestic
> `shipping_zones`/`shipping_rates`, `shipping_carriers`, `shipment_tracking_events`,
> `marketplace_shipping_rules`'s vendor_listing_id/admin_listing_id nullable-pair pattern).
>
> Before writing anything, read: `backend/database/migrations/` for the most recent migration
> (for the timestamp prefix), an existing migration that creates a table with the
> vendor_listing_id/admin_listing_id nullable pair (`marketplace_shipping_rules`) as your
> structural template, and `backend/app/Models/ShippingCarrier.php` +
> `backend/app/Models/Country.php` as model-style templates (HasUuids, casts, fillable, string PK
> for Country FKs — Country's PK is `char(36)` string, NOT an auto-increment int).
>
> Create migrations (do NOT run them) for:
> 1. `international_shipping_rates`: id (UUID), origin_country_id (FK countries), 
>    destination_country_id (FK countries), carrier_id (FK shipping_carriers, nullable),
>    base_fee (BIGINT), rate_per_kg (BIGINT), customs_fee_flat (BIGINT, nullable),
>    min_eta_days (SMALLINT), max_eta_days (SMALLINT), is_active (boolean default true),
>    timestamps. Unique composite index on (origin_country_id, destination_country_id,
>    carrier_id).
> 2. `international_shipping_eligibility`: id (UUID), vendor_listing_id (FK vendor_listings,
>    nullable), admin_listing_id (FK admin_listings, nullable), destination_country_id (FK
>    countries), is_active (boolean default true), timestamps. Match whatever check-constraint or
>    convention `marketplace_shipping_rules` uses to ensure exactly one of the two listing FKs is
>    set (read that migration first) — replicate the same approach here exactly, don't invent a
>    different one.
> 3. `currency_exchange_rates`: id (UUID), from_currency_code (CHAR(3)), to_currency_code
>    (CHAR(3)), rate_numerator (BIGINT), rate_denominator (BIGINT), effective_at (TIMESTAMP),
>    created_at only (append-only — no updated_at, this table is never updated in place, mirror
>    `InventoryMovement`'s migration for the append-only convention if it has one worth copying).
>    Composite index on (from_currency_code, to_currency_code, effective_at).
> 4. Alter `sub_orders`: add nullable `origin_country_id` (FK countries), nullable
>    `fx_rate_numerator` (BIGINT), nullable `fx_rate_denominator` (BIGINT), nullable
>    `fx_rate_captured_at` (TIMESTAMP).
> 5. Alter `shipment_tracking_events`: add nullable `carrier_id` (FK shipping_carriers), nullable
>    `external_tracking_number` (VARCHAR 100).
>
> Write Eloquent models `InternationalShippingRate`, `InternationalShippingEligibility`,
> `CurrencyExchangeRate` (all `HasUuids`), with `belongsTo` relations to `Country`/
> `ShippingCarrier`/`VendorListing`/`AdminListing` as appropriate. Add the new columns to
> `SubOrder` and `ShipmentTrackingEvent` models' `$fillable`/casts.
>
> Constraints: every money column BIGINT, never float, never `/100`/`*100` outside legitimate
> percentage math. Every new PK UUID via HasUuids. No JSON columns. Do NOT touch
> `database/schema/mysql-schema.sql`, `package-lock.json`, `.env` files, or
> `marketplace_platform.sql` (that's a data dump snapshot, not a live schema source — never
> edit it).
>
> Verify: re-read every file you wrote; run `php -l` on every new/changed PHP file and report
> results; confirm every country_id FK column type matches `countries.id` (char(36) string, not
> an integer or a different UUID format); confirm the vendor_listing_id/admin_listing_id
> exactly-one-set convention matches `marketplace_shipping_rules`'s approach.
>
> Git commit only the files you created/changed, descriptive message. End with:
> `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.
>
> Report back: every file created/changed, php -l results, commit hash, and exactly which
> convention you copied from `marketplace_shipping_rules` for the nullable-pair constraint.

### Phase 2 — Rate & FX calculation services
**Blocked on:** Phase 1 merged.
**Prompt:**
> Repo: /var/www/marketplace. Implement two services under `backend/app/Services/Shipping/`:
> 1. `InternationalShippingRateService::quote(string $originCountryId, string
>    $destinationCountryId, int $weightGrams): array` — look up the active
>    `international_shipping_rates` row for that country pair (prefer a row with a specific
>    `carrier_id` if one matches an eligible carrier, else the row with `carrier_id IS NULL` as a
>    generic fallback — read the Phase 1 migration to confirm this fallback semantics is what was
>    built, adjust if it differs), compute `base_fee + rate_per_kg * ceil(weightGrams / 1000)` +
>    `customs_fee_flat` (0 if null), return the fee breakdown (shipping fee, customs fee, total,
>    ETA min/max days). Throw a typed exception (check `backend/app/Exceptions/` for this
>    codebase's exception conventions) if no rate row exists for that corridor — never silently
>    fall back to a domestic rate or zero.
> 2. `CurrencyConversionService::convert(int $amountMinor, string $fromCurrency, string
>    $toCurrency): array` — same-currency short-circuits to the input amount with
>    numerator=denominator=1 and no DB query. Otherwise looks up the most recent
>    `currency_exchange_rates` row by `effective_at <= now()` for that currency pair, computes
>    `intdiv($amountMinor * $rateNumerator, $rateDenominator)` (integer-only, never float),
>    returns the converted amount plus the numerator/denominator/effective_at used (callers
>    snapshot these onto the sub_order). Throw if no rate exists.
>
> Write PHPUnit tests under `backend/tests/Unit/Services/Shipping/` for both: no-rate-found
> throws; fee math with weight rounding correctly up to the next full kg (e.g. 1001g rounds to 2kg
> worth of per-kg fee); same-currency short-circuit does zero DB queries (assert via query count
> or a mock); most-recent-effective_at wins when multiple rate rows exist for the same pair; no
> float anywhere (grep your own new files for `(float)`, `floatval`, or bare division without
> `intdiv`/explicit integer math and fix any you find).
>
> Run the test suite for these two test files and report pass/fail output. Git commit. End with:
> `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.
>
> Report back: files created, test run output, commit hash.

### Phase 3 — Checkout, pricing, and COD integration
**Blocked on:** Phase 2 merged. Q1/Q2 already resolved (prepaid-only COD, DDP duty) — proceed
using those answers; Q3 (uniform opt-in eligibility) is assumed yes per the design doc unless
told otherwise before this phase starts.
**Prompt:**
> Repo: /var/www/marketplace. Read `docs/plans/international_product_shipping.md` in full first,
> especially design decisions #3-#6 and the adopted Q1/Q2 answers (COD = prepaid-only for
> international lines; duty = DDP, itemized as its own `customs_duty` line).
> 1. In `backend/app/Services/Checkout/CartLineSource.php` and
>    `backend/app/Services/Checkout/CheckoutPricingEngine.php`: when building a cart line, compare
>    the listing's `country_id` to the order's destination `country_id`. If they differ, validate
>    an active `international_shipping_eligibility` row exists (vendor_listing_id or
>    admin_listing_id matching, destination_country_id = order's country) — reject with a clear
>    typed error if not (product/listing doesn't ship there). Also confirm `product_countries`
>    (if a row exists for that product+destination country) doesn't mark it unavailable — that
>    gate takes precedence.
> 2. For eligible international lines: call `InternationalShippingRateService::quote()` for the
>    shipping+customs fee (do not route these through the existing domestic
>    `ShippingMethodResolverService`/`shipping_zones` machinery — that's intra-country only), and
>    `CurrencyConversionService::convert()` to convert the listing's price (its own currency) into
>    the order's currency. At order placement (not cart-add time), snapshot
>    `fx_rate_numerator`/`fx_rate_denominator`/`fx_rate_captured_at` and `origin_country_id` onto
>    the `sub_order` row.
> 3. In `backend/app/Services/Customer/CodValidationService.php`: if any cart line is
>    international (per the origin/destination country comparison above), reject `cod` as the
>    selected payment method with a clear customer-facing error — international lines are
>    prepaid-only. This also resolves `QUESTION-1.md`/FIX-S1 — update that file's status to
>    resolved, referencing this implementation.
> 4. Add a `customs_duty` field to the checkout `order_summary` payload
>    (`backend/app/Services/Checkout/PricedCart.php` — mirror exactly how `loyalty_discount` was
>    added there recently, same pattern) and render it in
>    `frontend/src/features/noon/checkout/payment-summary.tsx` as a charge line (not a discount —
>    follow the `shipping`/`cod_fee` styling, not the green/negative `gift_card_applied` styling).
>    Add `customsDuty` locale keys to both `frontend/locale/en.json` and `frontend/locale/ar.json`.
> 5. Confirm no `/100`/`*100` anywhere new, all money BIGINT end-to-end. Re-read every changed
>    file. Run `php -l` on changed PHP and `npx tsc --noEmit` scoped to changed frontend files.
>    Split into 2-3 coherent commits if that makes sense (e.g. backend pricing/COD, then
>    frontend). End each with: `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not
>    push.
>
> Report back: files changed, commit hash(es), and explicit confirmation that a cart with an
> international line can no longer select COD as payment method (describe how you verified this
> — unit test, or manual trace through the code path).

### Phase 4 — Tracking extension
**Blocked on:** Phase 1 merged (independent of Phase 3, can run in parallel).
**Prompt:**
> Repo: /var/www/marketplace. Using the `carrier_id`/`external_tracking_number` columns added to
> `shipment_tracking_events` in Phase 1 (read `docs/plans/international_product_shipping.md`
> design decision #6 for why no new table was created):
> 1. Add a small helper on the `Shipment` model or a new
>    `backend/app/Services/Shipping/InternationalTrackingService.php` with a method
>    `recordLeg(Shipment $shipment, string $status, string $description, ?string $carrierId,
>    ?string $externalTrackingNumber, ?string $location, Carbon $occurredAt)` that just creates a
>    new `ShipmentTrackingEvent` row (this table is already append-only by convention — never
>    update an existing row).
> 2. Find the existing customer order-tracking endpoint (grep `routes/api_customer_v1.php` for
>    the shipment/tracking route) and confirm it already returns all `shipment_tracking_events`
>    for a shipment — if it does, no backend change needed there, just confirm. If it filters or
>    truncates the event list in a way that would hide multi-leg international events, fix that.
> 3. On the frontend order-tracking page (find it under `frontend/src/features/noon/`), confirm
>    the existing timeline component already renders arbitrary tracking events generically
>    (status/description/location/occurred_at) — if so, no change needed, an international
>    shipment's extra events will just show up. If the component assumes a fixed small set of
>    statuses, extend it to render unknown/extra statuses gracefully instead of hardcoding a list.
>
> This phase should end up being small — the existing generic event log likely needs no
> structural frontend change, only verification. Re-read every changed file, run `php -l`/
> `npx tsc --noEmit` on anything touched. Git commit (skip if truly nothing needed changing,
> in which case just report that verification passed with no diff). End with:
> `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.
>
> Report back: what (if anything) needed changing, files touched, commit hash or "no changes
> needed" with your verification evidence.

### Phase 5 — Admin tooling
**Blocked on:** Phase 1 merged.
**Prompt:**
> Repo: /var/www/marketplace. Build admin CRUD, reusing this codebase's existing simple
> admin-resource conventions (find an existing controller+Blade index/edit pair for a comparably
> simple settings-style resource under `backend/app/Http/Controllers/Admin/` as your template):
> 1. CRUD for `international_shipping_rates` (origin country × destination country × carrier →
>    fees/ETA).
> 2. CRUD for `currency_exchange_rates` — UI must only ever INSERT a new rate row, never edit one
>    in place (append-only, per Phase 1's design) — show rate history per currency pair, most
>    recent first.
> 3. A "ships to" destination picker on the existing vendor-listing edit view (find it — likely
>    under `backend/resources/views/admin/vendor_listings/` or wherever those are edited) writing
>    rows to `international_shipping_eligibility` with `vendor_listing_id` set. Same for the
>    admin-listing edit view with `admin_listing_id` set.
> 4. A one-off artisan command (e.g. `international-shipping:seed-admin-eligibility`) that backfills
>    `international_shipping_eligibility` rows for all active `admin_listings` against all
>    active+launched `countries` — per design decision #2 (uniform opt-in, Nawi's own catalog
>    bulk-seeded rather than defaulted). Do not run it, just write it and document the exact
>    command to run in your report.
>
> Re-read every file. Run `php -l` on all PHP files. Git commit. End with:
> `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.
>
> Report back: files created/changed, commit hash, exact artisan command name for the seeder.

### Phase 6 — Customer-facing UI
**Blocked on:** Phase 3 merged.
**Prompt:**
> Repo: /var/www/marketplace (Next.js frontend). Reuse the existing badge/UI patterns from recent
> promo-badge work (`promo_badges`/`AnimatedBadge` on product cards and PDP) as your template:
> 1. A "Ships from {origin country}" indicator on PDP/product card when the listing's country
>    differs from the currently-selected storefront country and an active
>    `international_shipping_eligibility` row exists for that destination.
> 2. Show the ETA range and a "customs included" note (DDP, per the adopted Q2 answer) near the
>    shipping info.
> 3. Locale keys in both `locale/en.json` and `locale/ar.json`.
>
> Re-read every changed file, run `npx tsc --noEmit` scoped to changed files. Git commit. End
> with: `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`. Do not push.
>
> Report back: files changed, commit hash.

---

## Phase 7 — QC pass (run after Phases 1-6 all land)
**Blocked on:** all of Phases 1-6 merged.
This phase is run by the orchestrating session directly (not delegated), acting as senior QC:
- Re-read the full diff across all phases as one unit (not just each phase's self-report).
- Verify: no float/`/100`/`*100` money bugs anywhere in the new code; every new PK is UUID; no
  new JSON columns for structured data; `shipment_tracking_events`/`currency_exchange_rates`
  truly never get UPDATE'd, only INSERT'd (grep for `->update(` / `::update(` / `save()` on
  existing rows of these models across the whole diff).
- Verify the COD rejection path actually triggers for an international cart line (trace the code
  path or add/run a test if one doesn't already cover it).
- Verify a domestic-only order is completely unaffected (all new columns nullable, all new logic
  gated behind "listing country != order country").
- Run full `php artisan test` (or this repo's actual test command) and `npx tsc --noEmit` across
  the whole frontend, not just changed files, to catch cross-phase breakage.
- Produce a findings list (bugs found, fixed, or flagged) and update this doc's Execution
  Summary table with final status.

---

## Execution tracking

| Phase | Status | Blocked on | Commit(s) |
|---|---|---|---|
| 1 — Schema foundation | Not started | — | — |
| 2 — Rate/FX services | Not started | Phase 1 | — |
| 3 — Checkout/COD | Not started | Phase 2 | — |
| 4 — Tracking extension | Not started | Phase 1 | — |
| 5 — Admin tooling | Not started | Phase 1 | — |
| 6 — Customer UI | Not started | Phase 3 | — |
| 7 — QC pass | Not started | Phases 1-6 | — |

## Migrations needed (run on server, in order)
- All Phase 1 migration files, then `php artisan schema:dump --prune`.
- Phase 5's admin-eligibility seeder command, run once after Phase 5 deploys (not a migration,
  an artisan command — do not add it to a migration file).

## Non-negotiable constraints
- All new money columns: BIGINT, no floats, no stray `/100`/`*100` outside legitimate percentage
  math.
- All new PKs: UUID via `HasUuids`.
- No JSON columns for structured relational data (do not copy `shipping_companies`.
  `served_countries`/`served_cities`'s existing JSON pattern).
- Append-only for `currency_exchange_rates` and the tracking-event extension — insert-only, never
  mutate history, matching `InventoryMovement`'s established pattern in this codebase.
- Every new table/column must be additive and nullable where it touches existing tables
  (`sub_orders`, `shipment_tracking_events`) — zero behavior change for domestic orders.
- Never edit `database/schema/mysql-schema.sql` or `marketplace_platform.sql` directly — both are
  generated/dumped artifacts, not sources of truth to hand-edit.
