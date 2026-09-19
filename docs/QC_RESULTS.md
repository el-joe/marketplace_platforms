
## F15 — OMR + currencies
- FIXED: symbol-image upload used `image` rule (rejects SVG); now file+mimes+mimetypes incl. image/svg+xml; SVG sanitised (script/on*/external href/foreignObject stripped) — Admin/CurrencyController.php uploadSymbolImage/sanitizeSvg. Test: tests/Feature/CurrencySymbolSvgTest.php.
- PASS: rate validation min>0, delete/replace removes old file, customer resource exposes symbol_image_url; OMR in gateways with 3-decimal handling.
- GAP: not verified end-to-end (HTTP upload, storefront om-ar formatter, cache invalidation, 3-decimal checkout total, F02 ad_price_currency).
