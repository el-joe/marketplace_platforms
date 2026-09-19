
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
