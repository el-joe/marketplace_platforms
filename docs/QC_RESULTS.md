
## F15 — OMR + currencies
- FIXED: symbol-image upload used `image` rule (rejects SVG); now file+mimes+mimetypes incl. image/svg+xml; SVG sanitised (script/on*/external href/foreignObject stripped) — Admin/CurrencyController.php uploadSymbolImage/sanitizeSvg. Test: tests/Feature/CurrencySymbolSvgTest.php.
- PASS: rate validation min>0, delete/replace removes old file, customer resource exposes symbol_image_url; OMR in gateways with 3-decimal handling.
- GAP: not verified end-to-end (HTTP upload, storefront om-ar formatter, cache invalidation, 3-decimal checkout total, F02 ad_price_currency).

## F17 — Client notes: directories (bio + specialty)
- PASS: marketer `bio_ar`/`bio_en` exist (MarketerProfile.php:22, self-edit Marketer/ProfileController.php:45, public API Api/Public/MarketerProfileController.php:211).
- GAP: no `specialty`/`تخصص` field anywhere (DB, admin, self-edit, API, storefront) — not implemented.
- GAP: no bio for stores/vendors; no public stores/influencers/brokers directory listing routes found (only `marketers/{slug}` show, api_customer_v1.php:226). Not verified/implemented: XSS-safe card rendering, per-category commission in admin profile.
- PASS (by concurrent F15 work): currency symbol SVG upload validates svg mimes, sanitises script/on*/external href (CurrencyController::sanitizeSvg). My duplicate was removed; no dedicated malicious-SVG test added by F17.
