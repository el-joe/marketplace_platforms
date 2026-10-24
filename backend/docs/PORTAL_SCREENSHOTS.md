# Portal band screenshots

The admin "Portal content" editor shows one row per visual **band** of a portal page (hero band, "why join"
cards, getting-started, banner + fulfilment, smart tools, testimonials, help, ...). Each row has a full-width
screenshot and the inputs of every block rendered inside that band. The band map is `config/portal_bands.php`
(generated, committed); screenshots are `public/images/portal-sections/{page}/{NN-slug}.{en|ar}.png`
(shared fallbacks: `_placeholder.{en|ar}.svg`). `config/portal_sections.php` remains the block-level source of truth.

## Regenerate (optional, local only)

The live portal is not reachable from the sandbox, so shots are rendered from a local copy of the same code.

1. Use an isolated database (never your test DB): `DB_DATABASE=portal_shots`, run `php artisan migrate`,
   `db:seed --class=CountrySeeder`, `PortalContentSeeder`, `MarketerJobSeeder`; add one published row each of
   help-center category/article, ad-support collection/article and blog post if you want those pages.
2. `npm run build` (every Vite entry must exist), then `DB_DATABASE=portal_shots APP_DOMAIN=localhost php artisan serve --port=8765`.
3. `DB_DATABASE=portal_shots APP_DOMAIN=localhost php artisan portal:screenshots --base=http://portal.localhost:8765 [--country=egy] [--page=home]`
   (Playwright + Chromium; `CHROMIUM_PATH` if not `/opt/pw-browsers/chromium`; ImageMagick `convert` is used to resize/quantise).
   `--page` re-renders one admin page and merges it into the existing `config/portal_bands.php`.
4. `vendor/bin/pint config/portal_bands.php`, then `php artisan test tests/Unit/PortalBandsConfigTest.php`.

## How bands are detected (`scripts/portal-screenshots.mjs`)

- Viewport 1700px, both locales (`ar` RTL, `en`); fixed header made static, floating widgets hidden, lazy content scrolled in.
- Bands = header + the top-level children of `<main>` (plain wrapper divs are opened up) + footers; bands < 120px are
  merged into the following neighbour; hidden / zero-height elements are skipped.
- Blocks map to bands by `data-portal-section` selector containment. Blocks stored under another page key but rendered
  on a page (a `route` override in `portal_sections.php`, e.g. `how-it-works/why-sell` on `/vendors`) become cross-key
  `[page_key, block_key]` pairs of that page. Blocks without a selector / not rendered attach to the preceding band
  (config order) or to `00-page-settings` (`image => null`).
- Band labels come from the first heading per locale (fallback: first block label). Images are resized to <= 1200px
  wide and quantised to ~200 KB.
- Images of remote CDNs (not reachable in the sandbox) render as broken images; replace the PNG manually if needed.

## Replace a screenshot manually

Drop a PNG with the same name (e.g. `home/01-hero.ar.png`) into the folder; no code change is needed.
