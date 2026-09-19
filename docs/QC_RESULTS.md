
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
