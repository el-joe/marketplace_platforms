
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
