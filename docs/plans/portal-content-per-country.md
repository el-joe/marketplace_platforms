# Portal Content — Per-Country Editing + Section Rows with Screenshots

Date: 2026-10-24. Team: **TL/BA** (this plan), **Dev** (senior full-stack), **QA** (senior tester).
Status: planned → executed work-package by work-package (WP1…WP5). Each WP prompt below is self-contained.

## 0. Audit (what exists today)
- Admin: `GET /portal-content` (`PortalContentController@index`) lists distinct `page_key` rows; `GET/POST /portal-content/{pageKey}` edits **all** rows of one page in a flat list grouped by `block_key` (alphabetical!), fields = `value_en`, `value_ar`, `value_url`, `is_active`, image upload.
- Model `App\Models\PortalContent` (uuid, unique `(page_key, block_key, field_key)`, **no country column**), cache `portal_content:{page}`, flushed on save.
- Blade helpers `portal_content()`, `portal_link()`, `portal_image()` (`app/Helpers/portal_content.php`) read `PortalContent::forPage($pageKey)`; locale from `session('locale','ar')`.
- Canonical defaults: `database/seeders/data/portal_content.php` (~960 rows) loaded through trait `SyncsPortalContent` (non-destructive: never overwrites rows with `updated_by_admin_id`). Data migrations call the seeder on deploy.
- ~28 page keys: register, travel-agency, how-it-works, advertise-product, smart-tools, advertise-brands, helpcenter, home, adsupport, sellers, account_setup, nav, fulfillment, marketers, open-souq, advertise-display, advertise-advertisers, advertise-request, marketer-type, blog, join, companies, layout, faq, cta_footer, advertise_footer, country_selector, language_selector, footer.
- Portal (`routes/portal.php`, domain `portal.*`) resolves the visitor's country via `Country::resolveSiteCode()` = route `{country}` segment → `session('portal_country')` → IP → Accept-Language → first launched country. `countries` has `site_code`, `iso_code_2`, `flag_emoji`, `name_en`, `name_ar`, `is_active`, `is_launched`. Flag SVG: `https://f.nooncdn.com/s/app/com/common/images/flags/{iso2}.svg` (already used in the portal country selector).
- `portal.nawyweb.com` is **not reachable from the agent sandbox (403)** → screenshots are produced by rendering the portal locally (php artisan serve + Playwright Chromium) or fall back to generated placeholders the owner can replace.

## 1. Target behaviour
1. `/portal-content` = one row per page, each row with **one edit button per active country** (flag icon + country name). Pressing the Kuwait flag → `/portal-content/kw/home` (URL segment = `countries.site_code`, lowercase as in portal routes).
2. Content is **stored per country**. Every existing row becomes the default value of every country (data migration + auto-copy for new countries + lazy materialisation when an admin opens a country page).
3. `/portal-content/{country}/{page}` = vertical list of **section rows**. Each row is a 2-column card: **screenshot of that section** (translated: `ar` / `en` image chosen by admin locale… see §4) and the **inputs** for that section. Column order follows the admin panel direction (`dir="rtl"` flips automatically via logical CSS / grid + `dir`).
4. Sections are **ordered exactly as they appear on the portal page** (config map, not alphabetical). Labels EN/AR per section.
5. The portal reads country-specific content: country row (active, non-empty) → global base row → Blade fallback.
6. Old URL `/portal-content/{pageKey}` redirects (302) to the first active country's page; old POST kept as alias for BC.

## 2. Design decisions
- **DB** (new migration only; never edit/rename old ones; never touch schema dump): add `portal_contents.country_code varchar(20) NOT NULL DEFAULT '*'` (`'*'` = global base layer, kept as fallback and as the template for new countries). Replace unique key with `(country_code, page_key, block_key, field_key)`, add index `(country_code, page_key)`.
- **Resolver**: `PortalContent::forPage($page, $country = null)`; country = explicit param → `request()->route('country')` → `session('portal_country')` → `Country::resolveSiteCode(null)`. Cache key `portal_content:{country}:{page}`; flush per country+page (and all countries when a `'*'` row changes). Merge rule per field: country row if it exists and `is_active` and non-empty, else `'*'` row, else Blade fallback. Image/link: same.
- **Materialisation service** `PortalContentCountrySync`: `copyGlobalToCountry(Country)`, `ensureCountryRows(country, page)`; `SyncsPortalContent` also fans new/refreshed global rows out to every active country row that is not admin-edited. `Country` created/updated-active observer copies global rows.
- **Section map** `config/portal_sections.php`: `page_key => [ ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'image' => 'hero'], … ]` in portal render order, plus `route` (portal URL path) for screenshots. Unknown blocks are appended alphabetically under "Other".
- **Screenshots**: `public/images/portal-sections/{page}/{block}.{en|ar}.png` (+ shared `_placeholder.{en|ar}.svg`). View picks `…ar.png` when admin locale is `ar`, else `…en.png`, falls back to the other language, then placeholder. A command `php artisan portal:screenshots` (Playwright via node script in `backend/scripts/`) re-generates them locally (documented), so the owner can rerun/replace.
- **Permissions**: unchanged `portal_content.view` / `portal_content.edit`.
- **No new third-party packages.**

## 3. Work packages (run in order; WP1‖WP2 parallel, WP3‖WP4 parallel after them, WP5 last)

### WP1 — Backend: per-country storage + resolver (Dev)
Prompt:
> Repo `/home/claude/marketplace_platforms/backend` (Laravel 11). Read `docs/plans/portal-content-per-country.md` §1–2. Implement ONLY the backend data layer: (1) NEW migration `2026_10_24_*_add_country_code_to_portal_contents` (never edit existing migrations, never regenerate `database/schema/mysql-schema.sql`): add `country_code`, swap the unique index, add index; (2) data migration `…_copy_portal_content_to_countries` that, for every row in `countries`, copies all `'*'` rows (preserve values, `updated_by_admin_id`, is_active, sort_order) into that country's rows if missing — idempotent; (3) update `PortalContent` (fillable, `forPage($page,$country=null)` with merge rule + per-country cache key + `flush($page,$country=null)` that flushes all countries for `'*'`, `resolveCountryCode()`); (4) update helpers `portal_content/portal_link/portal_image` to use the resolver unchanged in signature; (5) `App\Services\PortalContentCountrySync` + `Country` observer (register in AppServiceProvider) + make `SyncsPortalContent` also fan out to country rows (only non-admin-edited ones refreshed); (6) keep `PortalContentSeeder` working. Add Pest/PHPUnit feature tests in `tests/Feature/Portal/PortalContentCountryTest.php` (resolution order, fallback, flush, observer, idempotent copy, portal page renders country-specific text). DB for tests: `service mariadb start`, MariaDB `marketplace_test` root/root. Local-only skip-worktree patches may exist — do not commit them. Run `php artisan test tests/Feature/Portal` and `--filter=PortalContent`. Report files changed + test result in ≤10 lines. Do NOT commit.

### WP2 — Section map (Dev/BA)
Prompt:
> Read `docs/plans/portal-content-per-country.md`. Produce `backend/config/portal_sections.php` per §2: for every page_key in `database/seeders/data/portal_content.php` map its blocks IN THE ORDER they render in the portal Blade view(s) (`resources/views/portal/**`, partials, layouts; follow `@include` order top to bottom; layout/nav/footer keys = their visual order). Include for each page: `label_en/ar`, `route` (portal URL path using a `{country}` placeholder where the route has one, e.g. `/{country}/advertise/sellers`), `scope` ('page'|'shared' for nav/layout/footer/cta_footer/country_selector etc.), and for each section `block`, `label_en`, `label_ar` (proper Arabic), `selector` (CSS selector or `[data-portal-section="…"]` that wraps the section on the rendered page). Where a section has no stable selector, ADD a minimal `data-portal-section="{block}"` attribute to the wrapping element in the Blade partial (attribute only, no visual change). Every `block_key` present in the seeder data must appear exactly once; add a test `tests/Unit/PortalSectionsConfigTest.php` asserting full coverage + no duplicates. Report ≤10 lines. Do NOT commit.

### WP3 — Admin UI (Dev)
Prompt:
> Depends on WP1+WP2. Read `docs/plans/portal-content-per-country.md`. Refactor `app/Http/Controllers/Admin/PortalContentController.php`, `routes/admin.php` (portal-content group), and views under `resources/views/admin/portal-content/`: (a) index: table page × countries; each row shows page label (from `config/portal_sections.php`) + one button per active country with flag (`https://f.nooncdn.com/s/app/com/common/images/flags/{iso2}.svg`, emoji fallback) linking to `route('admin.portal-content.page',[country,page])`; (b) `GET /portal-content/{country}/{pageKey}` + `POST` (validate country exists/active, pageKey known; call `PortalContentCountrySync::ensureCountryRows` first; save only that country's rows; flush country cache); legacy `GET /portal-content/{pageKey}` redirects to first active country; keep old route names working via redirect for BC; (c) edit page: country switcher (flags) at top, page title, sections in portal order as rows; each row = 2-column grid: screenshot column (picked by admin locale: `{page}/{block}.ar.png` for ar else en, fall back to other language, then placeholder) and fields column (existing field widgets: text/richtext/link/image, EN+AR inputs, active toggle) — whole row direction follows admin locale via `dir`/logical properties (RTL: screenshot on the right); sticky Save bar; section anchor nav (sidebar/sticky chips) with unsaved-changes guard; screenshot click opens lightbox (Alpine). Responsive: stacks on mobile. (d) lang keys in `lang/en/admin.php` + `lang/ar/admin.php` for every new string; (e) feature tests `tests/Feature/Admin/PortalContentCountryAdminTest.php` (index shows flags/links, page 200 per country, sections order = config order, save updates only that country, permission 403, legacy redirect). Report ≤10 lines. Do NOT commit.

### WP4 — Screenshots (Dev/QA)
Prompt:
> Depends on WP2. Read `docs/plans/portal-content-per-country.md`. portal.nawyweb.com is blocked (403) from the sandbox — do not retry it. Instead: start MariaDB (`service mariadb start`), prepare a local DB with migrations + `PortalContentSeeder` + a couple of countries (use an isolated database name, e.g. `portal_shots`, via env overrides; do NOT touch `marketplace_test`; never run `schema:dump`), serve the portal (`php artisan serve` with `APP_PORTAL_SUBDOMAIN` host mapping or a `Host: portal.<domain>` header via Playwright route override). With Playwright + Chromium (`/opt/pw-browsers/chromium`, `executablePath`, no browser download) write `backend/scripts/portal-screenshots.mjs` that, for every page in `config/portal_sections.php` and both locales (`/language/ar`, `/language/en` or `session('locale')`), loads the page at 1440px width, waits for fonts/images, and saves each section element (`selector`) to `backend/public/images/portal-sections/{page}/{block}.{en|ar}.png` (≤ ~120 KB each: resize to max 900px wide, optimise). Pages with `{country}` use the first active country. Shared scope (nav/footer/layout) captured from the home page. For any section that cannot be captured, generate a labelled placeholder PNG/SVG (section name EN/AR) so no row is imageless; write `public/images/portal-sections/_placeholder.en.svg` and `.ar.svg`. Add `php artisan portal:screenshots` thin wrapper command (documented, optional) and `docs/PORTAL_SCREENSHOTS.md` (how to regenerate/replace). Report counts (captured/placeholder) ≤10 lines. Do NOT commit.

### WP5 — QA (senior tester)
Prompt:
> Independent review; do not trust the Dev reports. Read the plan §1 acceptance list. (1) Run `php artisan test tests/Feature/Portal tests/Feature/Admin --filter=PortalContent` + `tests/Unit/PortalSectionsConfigTest.php` + a broader `php artisan test` subset touching portal/countries. (2) Verify manually via `php artisan tinker`/HTTP: data migration idempotent; every country has full row set; new Country gets rows; portal text differs per country after editing one country only; fallback chain; image upload per country; legacy URL redirect; RTL/LTR markup (dir attrs); sections order == portal render order for 3 sample pages; lang keys exist in both languages; no N+1 on index (query count); no migrations edited (`git diff --stat -- database/migrations database/schema` shows only new files); screenshots exist for every config section and locale. (3) Fix small defects directly, report larger ones. Output: a pass/fail checklist ≤25 lines.

## 4. Acceptance checklist
- [ ] `/portal-content` shows one flag button per active country per page.
- [ ] `/portal-content/kw/home` (any `site_code`) opens that country's content; saving affects only that country.
- [ ] Every existing row exists for every country (default = current values).
- [ ] Sections are rows, 2 columns, screenshot + inputs, direction follows admin locale, screenshot language follows admin locale.
- [ ] Section order = portal order; labels EN/AR.
- [ ] Portal pages render country-specific content with global fallback.
- [ ] Existing migrations/schema dump untouched; new migrations are additive and idempotent.
- [ ] All new tests green; no regressions in portal tests.

## 5. Risks / notes
- Content volume ×N countries (≈1k rows × countries) — acceptable; cache per country+page keeps reads cheap.
- Screenshots reflect local default data, not live production edits; owner may replace PNGs in `public/images/portal-sections/`.
- `portal.nawyweb.com` could not be reached from the sandbox, so live-site screenshots were not used.

---

# Addendum (2026-10-24 PM) — "Bands": one screenshot per visual page section

Feedback: the per-block crops (WP4) are too granular. The admin must show **one row per visual band of the portal page** (hero band, "why join" cards band, getting-started band, banner+fulfilment band, smart-tools band, testimonials band, help band, final CTA band …) — each row = full-width screenshot of that band (en/ar) + inputs of **all blocks that render inside that band**, in the **same order as the portal page** (DOM order). `portal.nawyweb.com` is still unreachable from the sandbox shell/browser, so bands are captured from a local render of the same code (identical to what was deployed).

## New config schema — `config/portal_bands.php` (generated, committed)
```php
return [
  'home' => [                       // admin page key (same keys as config/portal_sections.php)
    'label_en' => '…', 'label_ar' => '…', 'route' => '/vendors', 'scope' => 'page',
    'bands' => [                    // DOM order of the portal page
      [
        'key'      => '02-why-join',          // unique within page, "NN-slug"
        'label_en' => 'Why join us', 'label_ar' => 'لماذا تنضم إلينا؟',
        'image'    => 'home/02-why-join',     // file stem; files = public/images/portal-sections/{image}.{en|ar}.png
        'blocks'   => [ ['home','why_join'], ['how-it-works','why-sell'] ],   // [page_key, block_key]; may point to other page keys
      ],
      // first band may be 'key'=>'00-page-settings' with image=>null (meta/title blocks that render no visible element)
    ],
  ],
];
```
Rules: a `[page_key, block_key]` pair appears in exactly one band across the whole file (coverage = every seeded block in `database/seeders/data/portal_content.php`); `image => null` → placeholder. `config/portal_sections.php` stays as the block-level source of truth (labels/selectors/routes) and as fallback when a page has no entry in `portal_bands.php` (each block = its own band, no image).

## Work packages
- **WP6 (Dev, admin)** — controller/views/tests adopt bands: editor rows = bands (screenshot col + fields col with block sub-headings), cross-page-key blocks editable/saveable (row lookup by `[country_code, page_key, block_key]`, only pairs declared in that admin page's bands), section anchor chips = bands, saving still scoped to the country; old per-block images no longer referenced.
- **WP7 (Dev/QA, screenshots+generator)** — delete all old PNGs under `public/images/portal-sections/`, rewrite `scripts/portal-screenshots.mjs` to detect bands per rendered page (both locales; width 1700px; top-level content children of the layout's main container + header + footer; merge elements shorter than ~120px into neighbours; skip hidden/zero-height), screenshot each band element, map blocks → bands via `data-portal-section` containment (blocks with `selector => null`/not found attach to the preceding band by config order, or to `00-page-settings`), derive bilingual band labels from the first heading text per locale (fallback: first block label), write images + `config/portal_bands.php`, add `tests/Unit/PortalBandsConfigTest.php` (coverage, uniqueness, files exist en+ar, order sanity).
- **WP8 (QA)** — independent verification + push.
WP6 ‖ WP7 (WP6 tests use an in-test `config(['portal_bands' => …])` fixture; integration check by WP8).
