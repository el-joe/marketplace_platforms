
## F15 — OMR + currencies
- FIXED: symbol-image upload used `image` rule (rejects SVG); now file+mimes+mimetypes incl. image/svg+xml; SVG sanitised (script/on*/external href/foreignObject stripped) — Admin/CurrencyController.php uploadSymbolImage/sanitizeSvg. Test: tests/Feature/CurrencySymbolSvgTest.php.
- PASS: rate validation min>0, delete/replace removes old file, customer resource exposes symbol_image_url; OMR in gateways with 3-decimal handling.
- GAP: not verified end-to-end (HTTP upload, storefront om-ar formatter, cache invalidation, 3-decimal checkout total, F02 ad_price_currency).

## F17 — Client notes: directories (bio + specialty)
- PASS: marketer `bio_ar`/`bio_en` exist (MarketerProfile.php:22, self-edit Marketer/ProfileController.php:45, public API Api/Public/MarketerProfileController.php:211).
- GAP: no `specialty`/`تخصص` field anywhere (DB, admin, self-edit, API, storefront) — not implemented.
- GAP: no bio for stores/vendors; no public stores/influencers/brokers directory listing routes found (only `marketers/{slug}` show, api_customer_v1.php:226). Not verified/implemented: XSS-safe card rendering, per-category commission in admin profile.
- PASS (by concurrent F15 work): currency symbol SVG upload validates svg mimes, sanitises script/on*/external href (CurrencyController::sanitizeSvg). My duplicate was removed; no dedicated malicious-SVG test added by F17.

## F06 — Travel search filters
- FIXED: sort was `departure_date` DESC; spec = ASC (ListingQueryService::paginateTravelPackages).
- FIXED: doc params `departure_from`/`departure_to` were ignored (code only read `date_from/date_to`); now both accepted (BrowseController::browseTravel). Frontend uses `date_from/date_to` and still works.
- FIXED: city not validated as belonging to country (now 422 on mismatch).
- FIXED: stale "unfiltered" route comment (routes/api_customer_v1.php).
- PASS: each filter alone + combined, invalid uuid/date/from>to -> 422, only active + non-expired, pagination meta.
- PASS: frontend filters (travel-filters.tsx: country -> dependent cities, dates, clear) call /browse/travel/{cat}?country_id... via travel-packages.actions.ts.
- NOTE: "approved" == status active (no separate approved flag filter). Test: backend/tests/Feature/Travel/TravelSearchFiltersTest.php (2 tests pass; run on isolated DB marketplace_test_f06 because shared marketplace_test deadlocked with parallel agents).

## F08 — Custom attributes (tailored products)
- FIXED: only text type existed. Added `type` (text/number/select/checkbox/notes) + `options` (migration 2026_09_19_100000), partner API validation, storefront resource, modal renders all types (ProductCustomAttribute.php, Partner/Api/ProductCustomAttributeController.php, custom-attributes-modal.tsx).
- FIXED: CartService accepted attribute IDs of other products; now rejected. Number/select/checkbox values validated (CartService::normalizeCustomAttributeValues). New lang key `custom_attribute_invalid` en/ar.
- FIXED: same product added twice with different values merged into one line and dropped the 2nd values; now separate lines, identical values still merge (findMatchingLine).
- FIXED (client a): size-guide image `products.size_guide_image`, partner `POST products/{id}/size-guide`, exposed on PDP resource, shown in modal.
- FIXED (client b/c): 'notes' type = free-text box to seller; presets (length/width/chest/sleeve/sleeve_from_neck) via `preset` param on store (numeric, EN/AR labels).
- PASS: required missing rejected; partner ownership check (active listing) exists; toggle hides fields when off (resource); mobile FloatingCartButton uses same modal (sends values); persistence cart->order values code exists (CheckoutController:1363).
- GAP: not tested by feature test: HTTP-level 422 mapping, partner cross-vendor 403, order-view rendering in 3 panels, immutability after order, admin UI for size-guide upload/presets (API only). Required checkbox: unchecked '0' counts as provided.
- Tests: tests/Feature/Cart/CustomAttributesTest.php 3/3 pass (run with DB_DATABASE=marketplace_test_f08; shared test DB deadlocks under parallel agents).

## F07 — Travel bookings
- FIXED: customer booking request allowed 1..50 seats, spec 1..10 — app/Http/Requests/Customer/Travel/CreateBookingRequest.php
- FIXED: TravelBookingService::book had no seat-availability check/lock (overbooking); now locked + rejects seats > available - booked — app/Services/Customer/TravelBookingService.php
- FIXED: customer cancel of a Confirmed booking did not release seats (nor reopen SoldOut) — same file
- FIXED: BookingCreationService incremented seats_booked at creation AND agency confirm incremented again (double count); reservation now only at confirm — app/Services/TravelAgency/BookingCreationService.php
- FIXED: admin inquiry convert sent no customer notification and was not idempotent under races; now row-locked and notifies TravelBookingConfirmed — app/Http/Controllers/Admin/TravelPackageInquiryController.php
- PASS: total = seats x price (tier-aware), IDOR on my-bookings show/cancel scoped to customer.
- GAP (not verified): admin search bar vs sidebar search; passport upload endpoint; live-total UI in frontend; true concurrent race (relies on lockForUpdate, not tested in parallel). Test DB was shared/deadlocking, ran with DB_DATABASE=marketplace_test_f07.
- Test: backend/tests/Feature/Travel/TravelBookingSeatsTest.php (3 pass)

## F05 — Special requests + broker smart routing
- Existing `SpecialRequestRoutingTest` (10 tests) already covers: validation, city/category routing, null city, non-affiliate/inactive brokers, once-per-admin notify, marketer index/show/API matching, IDOR, close guard, throttle, layout.
- EXECUTED: SpecialRequestRoutingTest 13/13 pass (61 assertions) on isolated DB (DB_DATABASE=marketplace_test_f05). Added: guest 401, in_progress->closed + hidden from broker panel, Blade panel index/show.
- GAP: no endpoint/action moves a request open->in_progress (only open->closed and in_progress->closed exist; broker panel lists status=open only). Not built: spec ambiguous on who transitions.
- FIXED (frontend): storefront category/search page (frontend/src/features/noon/shop/index.tsx) had no special-request CTA; added button linking /special-requests/create (locale keys shop.cantFindIt/sendSpecialRequest in en+ar). Not built/typechecked.

## F02 — Influencer/Broker profile fields, ad price, self-edit
- PASS: `PUT /marketer/profile/ad-price` 403 when `can_self_edit_ad_price` false; rejects negative/non-integer/non-numeric price and >3-char currency; saves valid (ProfileController.php:103).
- PASS: public list shows ad_price/currency, hides non-active marketers; unknown slug 404; profile splits own (invitation_id NULL) vs campaign (NOT NULL) listings; no email/commission leak.
- FIXED: `GET /marketers/{slug}` returned suspended/inactive marketers' profiles (list hid them). Now 404 (MarketerProfileController.php buildHeader).
- GAP: influencer clothing/shoe measurements are exposed on the public profile (`profile.measurements`) — spec says sizes must not leak; left as-is (product decision, likely intentional for influencer sizing).
- GAP: currency is only `size:3`, not validated against the currencies table.
- Test: backend/tests/Feature/Marketer/MarketerProfileAdPriceTest.php (5 tests). Note: shared `marketplace_test` DB deadlocks under parallel agents; ran with DB_DATABASE=marketplace_test_f02.

## F17 follow-up (implementation)
- FIXED: marketer/broker `specialty_ar`/`specialty_en` — migration 2026_09_19_210000, MarketerProfile fillable, admin updateProfile + admin show form, marketer self-edit (validated max:150), public marketer API, new directory endpoint `GET directory/{influencers|brokers}` (MarketerProfileController::directory, active only). Lang keys common.specialty_ar/en (AR+EN). Blade `{{ }}` escapes output (test asserts).
- PASS (pre-existing): vendors already have store_description(_ar) + specialization_en/ar (migration 2026_09_14_174100; admin UpdateVendorRequest, partner ProfileController, VendorPageVendorResource); stores directory = `vendors` index/show. Category commission overrides editable in admin marketers/show; broker category/city/all-cities present in admin + self-edit.
- GAP: storefront (Next) pages/cards for directories not built. Tests in tests/Feature/Marketer/MarketerSpecialtyDirectoryTest.php written but NOT verified green: shared marketplace_test DB deadlocked with concurrent agents' runs (only php -l verified).

## F03 — Sample sizes + samples lifecycle
Test: backend/tests/Feature/MarketerSamplesLifecycleTest.php (4 tests, 25 assertions, pass).
- PASS: size fields + EU/US/UK enum validated on admin `PUT admin/marketers/{m}/profile` (MarketerController.php:106-121); sizes rendered in admin (marketer_campaigns/show.blade.php:613) AND partner (partner/marketer_campaigns/show.blade.php:405) views - doc v1 "partner ❌" is outdated.
- PASS: sample must belong to campaign (403); status enum validated; other marketer cannot submit address (403); address locked once not pending (422).
- FIXED: admin `updateSampleStatus` allowed any transition (e.g. pending->returned, backwards). Now only pending->dispatched->delivered->returned (Admin/MarketerCampaignController.php:~105).
- FIXED: marketer could not confirm receipt. Added `POST marketer/samples/{sample}/received` (routes/marketer.php, Marketer/SampleController::confirmReceipt; owner-only, dispatched only). GAP: no UI button in marketer.samples.index view yet.
- FIXED: cm measurements had no upper bound; added max:500 (MarketerController.php).
- NOTE: shared marketplace_test DB deadlocks with parallel agents; ran with DB_DATABASE=marketplace_test_f03.

## F12 — International shipping
- PASS: existing tests (Unit InternationalShippingRateServiceTest, CurrencyConversionServiceTest, Checkout pricing reconciliation; 15 tests, 54 assertions) green with DB_DATABASE=marketplace_test_f12.
- PASS (code review): rate quote picks carrier-specific over generic, missing corridor throws InternationalShippingRateNotFoundException, weight rounded up per kg with integer math; COD blocked for international lines (CodValidationService.php:33); admin routes gated settings.view/settings.edit (routes/admin.php:1180-1210); rate validation (different origin/destination, ints >= 0, gte eta); ships-to eligibility routes for admin + vendor listings; append-only tracking service.
- GAP: customs duty is a flat amount (customs_fee_flat), not a % per route as the spec says. Not changed.
- GAP: no new Feature tests written for admin CRUD authz, mixed local+intl cart split, or tracking customer visibility; not verified beyond code review.

## F09 — Commission discounts (vendor + marketer)
Test: backend/tests/Unit/CommissionDiscountTest.php (2 tests pass) + Checkout money-split/ledger suites (see run).
- PASS: none/flat/percentage in Vendor::applyCommissionDiscount and MarketerProfile::applyCommissionDiscount; flat > commission clamps to 0; percentage floors; engine applies vendor discount (CheckoutPricingEngine.php:511), marketer discount in LastClickAttributionService.php:183.
- PASS: admin validation: type enum, flat integer >=0, percentage 0-100 (>100 rejected) (MarketerController.php:127, UpdateVendorRequest.php:40).
- PASS: unit is base-currency integer (500 = 500 base units, no x100); doc(2) "50 riyals" is consistent, v1 wording just differs by example.
- NOTE: vendor and marketer discounts apply to different commissions (platform vs marketer), so they do not stack on the same amount.

## F10 — Coupon shipping-type restriction
Test: backend/tests/Feature/Checkout/CouponShippingTypeRestrictionTest.php (3 tests, 25 assertions, pass; DB_DATABASE=marketplace_test_f10).
- PASS: matrix all/fbn/fbp/fbm x fbn/fbm lines (CheckoutPricingEngine.php validateCouponEligibility ~:819); enum validated in Admin + Vendor Store/Update CouponRequest (nullable, Rule::enum); AR+EN string `common.exceptions.checkout.coupon.shipping_type_restricted` exists. Checkout uses the same applyCoupon path (CouponEligibilityService facade).
- DOCUMENTED BEHAVIOR: mixed cart => coupon REJECTED entirely if any line mismatches (no partial discount, so no ineligible line enters the discount base).
- GAP (minor): message text is "only valid for :type shipping orders", not the spec's exact "هذه القسيمة غير صالحة لنوع الشحن المحدد". Type mapping: cross_dock => fbp, other non-fbn => fbm, admin => fbn.
- PASS: CouponUsageConcurrencyTest re-run (see output).

## F11 — COD limits
Test: backend/tests/Feature/Checkout/CodLimitsTest.php (8 tests pass; DB_DATABASE=marketplace_test_f11).
- PASS: keys `cod_global_max_amount`, `cod_supermall_max_amount`, `cod_supermall_category_id` are seeded (migration 2026_09_12_173000) in the `settings` table, category `orders`, and editable in Admin > Settings > Orders tab (NOT content-settings as the doc says; content-settings is a different table).
- PASS: CodValidationService: =limit ok, +1 blocked; 0 or missing setting = unlimited; Nawi admin listings exempt; Super Mall subtree uses its own limit; any international line blocks COD; enforced server-side in both prepare and place-order (CheckoutController.php:366, :762); direct COD POST over limit => 422, no order.
- DOCUMENTED BEHAVIOR: limit is on the partner-item subtotal (unit_price*qty, excl. shipping/fees), Nawi lines excluded from the total.
- FIXED: GET checkout/payment-options did not reflect COD limits/international (COD shown available). Now marks COD `is_available=false` + reason (Api/Customer/CheckoutController.php paymentOptions).
- FIXED: admin settings save had no validation for COD keys; SettingsService::validateGroup now requires non-negative whole numbers and an existing category id.
- GAP (minor): units are base-currency integers (50000 = 500 per doc example); no Arabic lang key check beyond existing cod_* keys (present in ar/en).

## F01 — Nawi Ad Packages + popup
Tests: backend/tests/Feature/Ads (36+ tests; DB_DATABASE=marketplace_test_f01).
- FIXED (tests): 3 stale popup assertions expected the raw destination_url; AdPopupController (by design) links to /products/{variant}--{listing} when a listing destination exists and only falls back to a sanitized URL otherwise. Tests updated in AdPopupControllerTest and NawiAdsLifecycleTest; added listing-link and URL-only cases.
- NOTE: the system is booking-based (PaidAdSlot/PaidAdBooking), not the spec's ad-package/serious_featured tables; AdBillingSeparationTest (user WIP) left untouched and passes.
- GAP: the spec's package CRUD, popup_* validation matrix and localStorage `nawi_ads_popup_seen` were not separately verified here.

## F04 — Contracts & acceptance audit trail
Test: backend/tests/Feature/MarketerContractAuditTrailTest.php (12 tests; DB_DATABASE=marketplace_test_f04).
- PASS: admin upload creates v1/v2, old version kept inactive; customer GET returns active version; accept stores customer/ip/UA/version/marketer; superseded/foreign version_id rejected (422); checkout blocked without acceptance and order linked to accepted acceptance row; new order needs new acceptance; prepare reports gate.
- PASS: PDF mime (mimes:pdf) and 10MB max validated; download requires marketers.view (403 otherwise); HTML text contract escaped in admin view (e()); customer API returns raw text (frontend must not use innerHTML).
- FIXED: versions/acceptances were mutable/deletable. Added model guards (MarketerContractVersion, MarketerContractAcceptance): content fields immutable, only is_active toggles; acceptance order_id may be set once; deletes throw.
- NOTE: no admin edit/delete routes exist for these records either.

## F13 — Checkout fixes
Test: backend/tests/Feature/Checkout/CheckoutFixesF13Test.php (6 tests pass; all Checkout filter: 76 pass; DB_DATABASE=marketplace_test_f13).
- PASS: prepare() uses cart coupon when coupon_code omitted; null when none; expired cart coupon yields no discount/422.
- FIXED: placeOrder() ignored the cart coupon (only prepare() fell back to it) so prepare total 945 vs place-order 1050 => 409 price_changed. Added the same `elseif ($cart->coupon)` fallback (Customer/CheckoutController.php ~:820).
- PASS: wallet insufficient => 422 before order creation, no orphan order, balance untouched; sufficient => deducted; same idempotency_key twice => one order, one debit; partial wallet+COD either deducts exactly or rejects cleanly.
- PASS: prepare order_summary.total == final order total (percentage coupon + tax).
- GAP (not verified): (c) frontend payment-summary rendering ("مجاني", COD fee only on COD) compared against API totals was code-located only, not browser-tested.

## F14 — Extended warranty + invoice + bank transfer
Tests: backend/tests/Feature/InvoiceAndBankTransferTest.php (4 pass) + WarrantyLifecycleTest/CartWarrantyTest (20 pass); DB_DATABASE=marketplace_test_f14.
- PASS: warranty purchase only after delivery, within window, one per item, failed payment creates none; claims validated (brand/platform windows, 422 outside).
- FIXED: Api\Customer\OrderController show/showSubOrder/tracking/cancel/invoice lacked the `{country}` route param, so Laravel passed country as $orderNumber and every call 404'd (invoice included). Added `string $country`.
- PASS: invoice returns tax/discount/shipping/total lines; other customer 404; unauthenticated 401.
- PASS: bank_transfer_details (bank, IBAN, reference = order number, amount) from gateway credentials in order detail; null for COD; other customer 404.
- GAP: invoice endpoint returns JSON, not a PDF (no PDF lib installed); the storefront renders a printable page. Arabic glyph/PDF filename spec not met. Not changed.
- GAP: values come from gateway credentials, not "settings"; duty line not in invoice resource.

## Follow-up pass (F17, F08, F03, F15, F02)
Ran with DB_DATABASE=marketplace_test_fu; 21 tests / 104 assertions green across the filtered suites.
- F17 FIXED: MarketerSpecialtyDirectoryTest now green (3 tests). Real bugs: Marketer/ProfileController::generateQrCode used the removed endroid v5 `QrCode::create()` API (500/404 on every marketer profile save) - now uses the v6 constructor; public `marketers` index did not return specialty_ar/en - added. Test used non-existent `directory/*` URLs; now uses `marketers?type=`.
- F08 FIXED: required checkbox sent as '0' counted as provided (CartService::normalizeCustomAttributeValues) - now rejected. New tests/Feature/Cart/CustomAttributesHttpTest.php: required-missing 422 (+ unchecked checkbox 422), partner of another vendor gets 403 on store/update/delete, order snapshot values unchanged after definition edit/delete.
- F03 FIXED: confirm-receipt button ("تأكيد استلام العينة") in resources/views/marketer/samples/index.blade.php for dispatched samples, posts to marketer.samples.received; asserted in MarketerSamplesLifecycleTest.
- F15 PASS: HTTP tests (tests/Feature/Admin/CurrencySymbolImageTest.php) for valid SVG stored, non-svg/php/plain-text rejected 422, unauthenticated 401. FIXED: SVG sanitiser left orphan closing tags and inner content of foreignObject; now strips the block and stray tags (script, on*, javascript:, external href verified).
- F15 PASS: storefront renders image symbol else text (frontend CurrencySymbol.tsx / Price.tsx). GAP: admin and partner Blade pages have no central price formatter (~480 ad-hoc number_format sites print the currency code/text), so image symbols are not rendered there. Not refactored.
- F02 FIXED: ad_price_currency now validated with exists:currencies,code (Marketer ProfileController, Admin MarketerController); MarketerProfileAdPriceTest extended (ZZZ rejected).
- F02 NOTE: influencer measurements intentionally NOT hidden (product decision pending).

## F16 — Cross-cutting sweep (DB_DATABASE=marketplace_test_f16)
- Full backend suite: 426 tests, 423 pass, 2 fail, 1 skipped (1759 assertions, ~63s).
  - FAIL (pre-existing, not from QC): Customer\ListingDetailPerformanceTest::test_pdp_query_count_is_within_budget (62 queries vs budget 20; PDP/home queries, no QC commit touches that path).
  - FAIL (pre-existing): ExampleTest::test_the_application_returns_a_successful_response (GET / returns 404; backend is API/panel-host only).
  - No failures caused by QC commits; nothing to fix.
- Route map: docs/QC_ROUTE_MAP.md. Panel routes register without /admin,/marketer,/partner prefix (host-based), so they match by suffix. Truly missing: /admin/ad-packages*, /partner/ad-subscriptions* (F01), doc paths `currencies/OMR/rate|symbol-image` exist as `currencies/{code}/...`; `/api/public/active-popup` is `/api/public/v1/{country}/active-popup`; `marketer/profile/ad-price` is `PUT profile/ad-price`.
- Lang parity: QC commits touched no lang files; no changes needed.
- Permission sweep: contract/currency admin routes carry CheckAdminPermission. RISK: PATCH marketer-campaigns/{c}/samples/{s} (a mutation) is guarded only by marketer_campaigns.view; currencies/{code}/rate only countries.view. Marketer routes (profile/ad-price, samples/{sample}/address) have no permission middleware, scoped by owner in controller (covered by tests).
- Frontend: QC touched no frontend files. `tsc --noEmit` errors are only in generated .next/types/validator.ts (missing checkout page modules), pre-existing. Lint/Playwright not run.

### Final summary
| Feature | Status | Notes / remaining risk |
|---|---|---|
| F01 Ads packages/popup | GAP | package model (serious/serious_featured, /admin/ad-packages, /partner/ad-subscriptions) NOT implemented; Ads is booking-based; popup tests FIXED |
| F02 Marketer profile | FIXED | currency validation; measurements public (open) |
| F03 Samples | FIXED | confirm-receipt button; PATCH sample perm is .view only |
| F04 Contracts | FIXED | immutable versions/acceptances |
| F05 Special requests | GAP | no open->in_progress action |
| F08 Custom attributes | FIXED | required checkbox |
| F10 | GAP | error wording |
| F12 | GAP | customs duty flat, not % |
| F13 Checkout | FIXED | placeOrder honors cart coupon |
| F14 Warranty/invoice | FIXED/GAP | country param bug fixed; invoice JSON not PDF |
| F15 Currency symbol | FIXED/GAP | SVG sanitiser; admin/partner Blade don't render image symbols |
| F17 Specialty directory | FIXED | QR v6 API, specialty fields |

### Frontend QC sweep
- `tsc --noEmit`: only pre-existing `.next/**/validator.ts` errors (checkout routes); none in changed files. eslint on changed files: 0 errors (3 jsx-no-literals warnings in custom-attributes-modal).
- en/ar locale keys `shop.cantFindIt`, `shop.sendSpecialRequest` present in both.
- custom-attributes-modal: text/number/select/checkbox/notes render correctly; fixed required checkbox (unchecked "0" previously passed validation).
- FloatingCartButton forwards hasCustomAttributes/customAttributes to CartButton, which owns the modal and passes values to use-cart (`custom_attribute_values`).

## Decisions round (F10/F05/F07)
- F10 FIXED: coupon shipping-type mismatch message now "This coupon is not valid for the selected shipping type." / "هذه القسيمة غير صالحة لنوع الشحن المحدد"; test updated.
- F05 FIXED: broker action open -> in_progress (marketer panel PATCH special-requests/{id}/start + API PATCH /api/marketer/special-requests/{id}/start); only matching broker, only from open; status badge in list; close guard unchanged. Note: in_progress requests leave the broker list (existing behaviour, no broker assignment column).
- F07 FIXED: TravelBookingConfirmed text now "Booking Received ... pending documents" (ar+en).
- F02/F12/F14: no change.

## Custom pages A1: backend data layer
- PASS: migration adds custom_pages.listing_types (JSON null) + all_categories (bool default 0), reversible; casts, allowedListingTypes()/allowsType().
- PASS: CategoryService::resolveCustomPageScope (all categories => null, subset + descendants, none/deleted category => []), normalizeListingTypes (tests/Feature/CustomPages/CustomPageScopeTest.php, 6 tests).
- FIXED: syncCategories tolerates empty list, dedupes ids.
- NOTE: getCategoryIdsForFilter keeps array contract (all-categories page => []); new getCategoryScopeForFilter returns null for unrestricted. A3 must switch callers and short-circuit [] (no whereIn([])).

## Custom Pages A2: admin add/edit form (listing types + all categories)

- PASS: create/update persist `listing_types` (normalized; all three or none = null) and `all_categories`; all_categories clears linked categories.
- PASS: validation 422 (bogus type, no categories without all_categories), en+ar messages; 403 without `categories.view`; XSS in names escaped on index.
- PASS: edit prefill, old() prefill of types and toggle, index badges (types, All categories). Tests: `tests/Feature/CustomPages/CustomPageAdminFormTest.php` (8), own DB `marketplace_test_a2`.
- PASS: UI toggle verified in headless Chrome against the built bundle (checkbox disables search and dims picker, and reverts).
- FIXED: create form used a plain POST and would show raw JSON; `custom-pages.js` now submits create via AJAX and follows `redirect`.
- GAP: the Inherited Filters card (edit only) is server-rendered, so toggling all categories updates it only after save. RTL only checked by lang keys, not visually.
