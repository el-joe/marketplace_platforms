# Portal section screenshots

The admin "Portal content" editor shows, next to each section's inputs, a screenshot stored in
`public/images/portal-sections/{page}/{block}.{en|ar}.png` (shared fallbacks:
`public/images/portal-sections/_placeholder.{en|ar}.svg`). Sections that could not be captured
(no DOM selector such as `meta`, pages needing data/build output) hold a labelled placeholder PNG.

## Regenerate (optional, local only)

The live portal is not reachable from the sandbox, so shots are rendered from a local copy.

1. Use an isolated database (never your test DB): `DB_DATABASE=portal_shots`, run `php artisan migrate`,
   `db:seed --class=CountrySeeder`, `db:seed --class=PortalContentSeeder`; add one published row each of
   help-center category/article, ad-support collection/article, blog post and marketer types if you want those pages.
2. Build assets (`npm run build`) so every Vite entry exists, then `APP_DOMAIN=localhost php artisan serve --port=8765`.
3. Run: `php artisan portal:screenshots --base=http://portal.localhost:8765 [--country=egy] [--page=home]`
   (Playwright + Chromium; set `CHROMIUM_PATH` if not `/opt/pw-browsers/chromium`).

The script (`scripts/portal-screenshots.mjs`) loads each page at 1440px in `en` and `ar`, screenshots the
`selector` from `config/portal_sections.php`, downsizes to <= 900px wide (<= ~120 KB), and writes placeholders on failure.
Optional: quantise PNGs afterwards (e.g. pngquant) to cut size further.

## Replace a screenshot manually

Drop a PNG with the same name (e.g. `home/hero.ar.png`) into the folder; no code change is needed.
