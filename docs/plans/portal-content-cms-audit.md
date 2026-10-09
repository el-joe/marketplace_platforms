# Portal Content CMS — Full Audit, Fix Plan & Prompts

**Date:** 2026-10-09
**Scope:** `backend/resources/views/portal/**` (all public portal routes in `routes/portal.php`), the four portal layouts (`layouts/portal|advertise|adsupport|helpcenter.blade.php`), the Portal Content admin editor, and the Blog / Help Center / Ad Support / FAQ content modules.
**Inputs:** `origin/main @ 7c581cdb`, `backend/database/schema/mysql-schema.sql`, `backups/marketplace_platform_live.sql` (live dump == local `marketplace_platform_live`, 595 `portal_contents` rows).

---

## 1. How the CMS works (baseline)

| Piece | Location | Notes |
|---|---|---|
| Table | `portal_contents` (`page_key`, `block_key`, `field_key` unique; `type` ∈ text/richtext/link/image; `value_en`, `value_ar`, `value_url`; `is_active`; `updated_by_admin_id`) | One row = one editable field |
| Model | `app/Models/PortalContent.php` | `forPage()` cached 1h per page_key, `flush()` |
| Helpers | `app/Helpers/portal_content.php` | `portal_content()`, `portal_link()`, `portal_image()` — **always** take the current hard-coded copy as fallback, so a missing/empty/inactive row renders today's text |
| Admin | `Admin\PortalContentController` + `admin/portal-content/{index,edit}` | Lists page_keys that exist in DB; bulk-saves one page |
| Seed data | `PortalContentSeeder` + `Batch1..4` | `updateOrCreate` on the unique key |

**Rule adopted for this work:** the blade fallback is the source of truth for the *default* value ("current text and images become the default when empty"). Every field must (a) be rendered through a helper and (b) have a seeded row so admins can see and edit it.

---

## 2. Findings

### 2.1 Critical — live site shows wrong content

| # | Finding | Evidence | Impact |
|---|---|---|---|
| C1 | **DB rows override the rebranded copy with old "noon" copy.** Blades were rebranded noon→Nawy, but seeders and the live DB were not. Because a DB row wins over the fallback, the live portal still shows "noon". | 196 field diffs between DB and blade fallbacks across 123 keys; **134 live values contain "noon"/"نون"** (titles, meta, alt text, testimonials, FAQ header, blog header…). No row was ever edited by an admin (`updated_by_admin_id IS NULL` on all 595 rows). | Wrong brand on every portal page |
| C2 | **DB image rows override Nawy images with noon CDN images** (`home`/`how-it-works`/`fulfillment`/`smart-tools`/`sellers`/`advertisers` heroes, ad-support header logo → intercom noon logo). Blades fall back to `/images/nawy_*.{jpg,png}`. | `src` diffs in diff run | Wrong imagery / competitor brand |
| C3 | **Seeders are destructive.** Every seeder uses `updateOrCreate`, so re-running them on production silently overwrites admin edits. This is why nobody dared re-seed, and why C1/C2 drifted. | `PortalContentSeeder*.php` loops | Data loss risk on deploy |
| C4 | **Help Center language toggle is broken.** Rows `helpcenter.header.language_toggle_{desktop,mobile}` store a fixed URL `/language/ar` (so Arabic users can never switch to English) and the labels are inverted (`value_en='English'` is shown to English users). | DB + `partials/helpcenter-header.blade.php` | Users stuck in one language |
| C5 | **Page `<title>` suffix and default titles say "noon".** `layouts/portal` uses `__('common.partner_brand_title')` = "noon for Sellers"; help center = "noon Seller Help Center"; advertise = "… \| noon". Not CMS-editable. | `lang/{en,ar}/common.php` | SEO + brand |

### 2.2 High — content not editable from admin (hard-coded)

| Area | File | Hard-coded items |
|---|---|---|
| Page titles/meta | `home`, `faq`, `how-it-works`, `fulfillment`, `smart-tools`, `sellers` (title + description) | ternaries in `@section('title')` |
| Layout defaults | `layouts/portal|advertise|adsupport|helpcenter` | default title/suffix/description (lang files), ad-support favicon + header background image (intercom CDN), help-center favicon |
| Main nav | `partials/nav.blade.php` | logo ×2, logo aria, 8 sub-menu labels, Menu/Close aria ×3, mobile "Sign Up Now" CTA; 3 helper calls without DB rows (`lang_option_en/ar`, `country_toggle.mobile_aria_label`) |
| Advertise nav | `partials/advertise-nav.blade.php` | logo + aria/alt "Nawy ads", Menu aria, EN/AR short toggle; **mobile "Start now" points to noon Ad Manager while desktop points to `/register`** |
| Home | `hero` (image row missing), `why-sell` (3 image rows missing), `smart-tools` teaser (3 images), `testimonials` (YouTube embed URL) | |
| Getting started | `how-it-works` (5 floating grid images, 4 locale-specific Dubai Traders banner images), `account-setup` ("Step N:" prefix), `fulfilment-model` (YouTube embed + title) | |
| Fulfilment | `fulfillment-detail` (2 YouTube embeds + titles) | |
| Smart tools | `smart-tools-detail` (Ads logo + alt "noon Ads", 3 fee-card images) | |
| Sellers (ads) | `sellers-solutions` (4 images), `sellers-testimonials` (3 logos, 3 slider aria rows missing) | |
| Brands (ads) | `brands-testimonials` (3 logos) | |
| Display / Product ads | `display-hero`, `product-hero` (3 feature icons each, Start-now CTA href hard-coded to noon Ad Manager), `display-quick-guide`, `product-quick-guide` (video file + play/rewind/forward icons) | |
| Register | `register.blade.php` (logo word "Nawy"), `register/step-2` (3 placeholders) | |
| Blog | `blog/index`, `blog/show`, `blog/_post-card` | 9 UI labels via `__('portal.blog.*')`; `meta_description` section is set but **never rendered** (portal layout has no `@yield('meta_description')`) |
| Help Center | `helpcenter-header` (logo, home aria), `helpcenter-footer` (logo), `article`/`category` (breadcrumb aria; 3 rows missing: `article.site_title`, `article.updated_label`, `category.site_title`) | |
| Ad Support | `adsupport/article`, `adsupport/collection` (breadcrumb aria) | |
| Missing rows | `how-it-works.fulfilment-model.new_window_title` | |

### 2.3 Medium — admin editor limitations

1. Image rows accept **file upload only** — admins cannot paste a URL, cannot reset to default, and the `image` rule rejects **SVG** (most logos/icons are SVG).
2. `portal_image()` treats any non-`http` value as a `public`-disk path, so a site asset like `/images/nawy_ui.jpeg` cannot be stored as a value → seeded rows had to point to the noon CDN instead.
3. Admin preview duplicates URL-resolution logic (`edit.blade.php`).

### 2.4 Content modules (Blog / Help Center / Ad Support / FAQ)

| # | Finding | Evidence |
|---|---|---|
| M1 | **Bilingual columns empty.** All 9 `help_center_categories`, 14 `help_center_articles`, 10 `ad_support_collections`, 12 `ad_support_articles` have `*_en` **and** `*_ar` = NULL; text lives only in the legacy single-language columns (`name`, `title`, `excerpt`, `body`, `description`) — a mix of Arabic and English rows. Portal falls back to legacy so both languages show the same text; admin edit forms show empty required `*_en` fields. | live DB |
| M2 | `updatedLabel()` on `HelpCenterArticle`/`AdSupportArticle` uses `format('F j, Y')` → English month names on Arabic pages. | models |
| M3 | Ad Support has **no country scoping** (Help Center has `country_id` = `countries.site_code`; Blog has `country_id` = `countries.id` FK). Inconsistent country keys across modules. | schema |
| M4 | Ad-support collection icons hot-link `intercom.help/noon-adsupport/...` (9/10 rows). | live DB |
| M5 | "noon" brand inside **content records**: FAQ 18/25, blog 2/12, help-center articles/categories, ad-support articles. Editorial content — not auto-rewritten. | live DB, `FaqSeeder`, `HelpCenterSeeder`, `AdSupportSeeder` |
| M6 | FAQ partials query `Faq::forContext()` directly inside Blade (`partials/faq|product-faq|display-faq`). Works, but belongs in the controller/view-composer. | views |

### 2.5 Low / informational

* Many default images are hot-linked from `f.nooncdn.com` / `advertise.noon.com` (competitor CDN — licensing + availability risk). Kept as defaults (they are "current"), now replaceable from admin.
* `partials/why-sell` defaults to `storage/why-sell/{1,2,3}.jpg` (public disk) — files are **not** present in this environment; verify on production or upload via admin.
* Country flags are hot-linked from `f.nooncdn.com/.../flags/{iso}.svg` in nav (per-record, not CMS).
* `partials/nav.blade.php` renders **two** mobile overlays bound to the same `mobileOpen` flag (in-header menu + drawer).
* Blog `og:image` uses a relative `Storage::url()` (should be absolute for social crawlers).

### 2.6 Found (and fixed) during implementation

| # | Finding | Fix |
|---|---|---|
| F1 | 15 partials default `$country ?? 'ae'`, but no country has `site_code = 'ae'` (UAE is `uae`). `Country::resolveSiteCode('ae')` returns `'ae'` unvalidated, so e.g. Smart-tools "Learn more" → `/ae/helpcenter` hid every UAE-scoped help article. | `?? \App\Models\Country::resolveSiteCode(null)` (session country → first launched country). |
| F2 | Advertise nav "Start now": desktop → `/register`, mobile → noon Ad Manager. | Both use the `nav.advertise_nav.start_now` link row (default `/register`). |
| F3 | `layouts/helpcenter` favicon points to `public/images/helpcenter/favicon.svg`, which does not exist. | Now CMS-editable (`layout.helpcenter.favicon`) — upload one in admin. |
| F4 | Mixing an inline `@php(...)` above a `@php … @endphp` block breaks Blade compilation (the block regex swallows the inline call). | Use block form in `partials/testimonials`; keep this in mind for future edits. |
| F5 | Hard-coded alt "noon Ads" on the Smart-tools Ads logo. | Default alt is now "Nawy Ads" (`smart-tools.ads.logo`). |

---

## 3. Implementation (done in this change)

### 3.1 Infrastructure
1. **Non-destructive seeding** — new `Database\Seeders\Concerns\SyncsPortalContent` trait used by every portal-content seeder:
   * missing row → insert;
   * existing row never edited by an admin (`updated_by_admin_id IS NULL`) → refresh type/values/sort to the canonical default;
   * admin-edited row → only `type`/`sort_order` are refreshed, **values are never touched**;
   * flushes the cache of every touched page.
2. **`PortalContent::resolveUrl()`** — single URL resolver: `http(s)://`, `//`, `mailto:`, `tel:`, `#` and site-absolute `/…` paths are used as-is; anything else is a `public`-disk path. Used by `portal_image()` and the admin preview.
3. **Admin editor** — image rows get a URL input next to the upload, a "reset to default" checkbox, and SVG support (`mimes:jpg,jpeg,png,webp,gif,svg|max:5120`); link URL fields explain that empty = default link.
4. **Data-sync migration** (`2026_10_09_000001_sync_portal_content_defaults`) — runs `PortalContentSeeder` so `php artisan migrate --force` on deploy inserts the new rows and corrects the drifted noon rows (C1, C2, C4) without touching admin edits. Removes the obsolete `home.testimonials.video_title` row (replaced by the `video` link row) only if it was never admin-edited.

### 3.2 Seed data (canonical defaults = blade fallbacks)
* `PortalContentSeederBatch1..4` were consolidated into one canonical data file, **`database/seeders/data/portal_content.php`** (712 rows, 20 page keys). `PortalContentSeeder` syncs it; `DatabaseSeeder` calls only `PortalContentSeeder`.
* The data was generated (not hand-copied) by rendering every portal route in EN/AR × two countries with recording helpers, unioned with a static parse of every helper call (for conditional branches such as empty states) and the previous seeder rows. Rules: text = Blade fallback; link URLs that vary by country/locale/environment → `NULL` (dynamic fallback); parameter-less routes → relative path (`/register`); `asset('images/x')` → `/images/x`.
* Result: existing rows realigned to the current Blade copy (noon→Nawy, Nawy imagery, helper types), 115 new rows (§4).

### 3.3 Blade conversions
Every item in §2.2 now goes through `portal_content()` / `portal_link()` / `portal_image()` with the current value as fallback. Pure layout ternaries (`rtl`/`ltr`, `text-right`, `-scale-x-100`, …) intentionally stay in Blade.

### 3.4 Content modules
* Migration `2026_10_09_000002_backfill_bilingual_help_and_ad_support_columns` — copies each legacy value into `*_ar` when it contains Arabic script, otherwise into `*_en` (only where both are NULL). Lossless; legacy columns untouched.
* `updatedLabel()` → `translatedFormat()`.
* Portal layout now renders `<meta name="description">` from `@yield('meta_description')`.

---

## 4. New / changed CMS keys

| page_key | block_key | field_key(s) | type |
|---|---|---|---|
| layout | portal | default_title, title_suffix, default_description | text |
| layout | advertise | default_title, default_description | text |
| layout | adsupport | default_title, default_description | text |
| layout | adsupport | favicon, header_background | image |
| layout | helpcenter | default_title, default_description | text |
| layout | helpcenter | favicon | image |
| home / faq / how-it-works / fulfillment / smart-tools | meta | title | text |
| sellers | meta | title, description | text |
| nav | logo | image (image), aria_label (text) | |
| nav | link_how_it_works | sub_registering, sub_listings, sub_fulfilment | text |
| nav | link_fulfillment | sub_fbn, sub_fbp | text |
| nav | link_smart_tools | sub_ads, sub_fees, sub_insights | text |
| nav | mobile | menu_aria, close_aria (text), cta_button (link) | |
| nav | lang_option_en / lang_option_ar | label | text |
| nav | country_toggle | label, mobile_aria_label | text |
| nav | advertise_nav | logo (image), logo_aria, menu_aria, language_short (text); start_now → **link** | |
| home | hero | photo | image |
| home | smart_tools_teaser_item_{1..3} | image | image |
| home | testimonials | video (link: label = iframe title, url = embed) — replaces `video_title` | link |
| how-it-works | why-sell-item-{1..3} | image | image |
| how-it-works | checklist | grid_image_{1..5} | image |
| how-it-works | trader_banner | image_mobile_en, image_mobile_ar, image_desktop_en, image_desktop_ar | image |
| how-it-works | fulfilment-model | video (link), new_window_title (text) | |
| account_setup | main | step_prefix | text |
| fulfillment | fbn / fbp | video | link |
| smart-tools | ads | logo | image |
| smart-tools | fee-card-{referral,fulfilment,other} | image | image |
| sellers | solution_{product_ads,display_ads,crm_social,brand_ads} | image | image |
| sellers | testimonial_{1..3} | logo | image |
| sellers | testimonials | prev_aria, next_aria, slide_aria | text |
| advertise-brands | testimonial_{1..3} | logo | image |
| advertise-display / advertise-product | hero_feature_{1..3} | icon | image |
| advertise-display / advertise-product | hero | cta_button → **link** | link |
| advertise-display / advertise-product | quick_guide | video (link), play_icon, rewind_icon, forward_icon (image) | |
| register | header | logo_text | text |
| register | step_2 | store_slug_placeholder, registration_number_placeholder, tax_id_placeholder | text |
| blog | meta | title | text |
| blog | header | title | text |
| blog | index | search_placeholder, search_button, all_filter, no_posts, min_read | text |
| blog | show | breadcrumb_aria, home_label, written_by, related_posts | text |
| helpcenter | header | logo (image), home_aria, open_menu_aria, close_menu_aria | |
| helpcenter | footer | logo | image |
| helpcenter | common | breadcrumb_aria | text |
| helpcenter | article / category | site_title (+ article.updated_label) | text |
| adsupport | common | breadcrumb_aria | text |

---

## 5. Deploy / verify

```bash
cd backend
php artisan migrate --force          # runs the sync + bilingual backfill migrations
php artisan view:clear
# optional, idempotent and safe at any time now:
php artisan db:seed --class=PortalContentSeeder --force
```

Verify:
1. `select count(*) from portal_contents where value_en like '%noon%' or value_ar like '%نون%';` → only intentional URLs/alt texts of hot-linked images remain.
2. Admin → Portal Content → every page key in §4 is listed; change a value → portal reflects it after save.
3. Help Center: toggle language both ways (desktop + mobile).
4. Blog post page source contains `<meta name="description">`.
5. Production: confirm `storage/app/public/why-sell/{1,2,3}.jpg` exist, otherwise upload via admin (`how-it-works › why-sell-item-N › image`).

---

## 5.1 Verification performed (local copy of the live DB)

* `php artisan migrate` → both data migrations ran; `portal_contents` = 712 rows and matches the canonical data 1:1 (0 diffs, 0 extra rows; obsolete `video_title` removed).
* Help Center categories 9/9, help articles 14/14 and ad-support articles 12/12 now have `*_en`/`*_ar` populated from legacy columns.
* Rendered all 23 portal routes × {en, ar} × {uae, ksa} with the real helpers → 92/92 HTTP 200.
* Visible "noon" text remaining on rendered pages comes **only** from content records (FAQ, help-center, ad-support, blog) → P1. CMS rows still containing "noon": 7 alt texts describing still-noon-branded default photos → P2.
* Tests: `tests/Feature/Admin/PortalContentTest.php` (sync rules, URL resolution, admin URL/reset/SVG upload, data-file integrity) + related suites — 12/12 pass.

> Security note: admins can now upload SVG. Inside `<img>` scripts never run, but opening the raw `/storage/...svg` URL would; uploads are admin-only (`portal_content.edit`). If that is not acceptable, drop `svg` from the `mimes` rule in `Admin\PortalContentController::save()`.

---

## 6. Follow-up prompts (not done in this change — need a product/content decision)

> **P1 — Rebrand content records (editorial).**
> "In `backend`, list every FAQ (`faqs`), blog post (`blog_posts`), help-center category/article and ad-support collection/article whose text contains `noon` / `نون` (case-insensitive, excluding URLs). For each, propose the Nawy wording in both languages, show me the diff, and after my approval write a new data migration that updates only rows not edited after 2026-10-09. Also update `FaqSeeder`, `HelpCenterSeeder`, `AdSupportSeeder`, `BlogSeeder` to match."

> **P2 — Self-host third-party images.**
> "Download every default image/video referenced from `f.nooncdn.com`, `advertise.noon.com`, `intercom.help`, `downloads.intercomcdn.com` in `resources/views/portal/**`, `resources/views/layouts/{adsupport,helpcenter}.blade.php`, the portal-content seeders and the live `portal_contents`/`ad_support_collections.icon` rows into `public/images/portal/…` (only after I confirm we have the rights / replacements). Replace fallbacks and seeded values with the `/images/portal/...` paths and add a data migration for non-admin-edited rows."

> **P3 — Ad Support country scoping.**
> "Add nullable `country_id varchar(20)` (= `countries.site_code`, same as help center) to `ad_support_collections` and `ad_support_articles` via a new migration, add `scopeForCountry()` to both models mirroring `HelpCenterArticle`, filter in `Portal\AdSupportController`, and expose a country select in the admin forms. Add feature tests for country filtering."

> **P4 — Move FAQ queries out of Blade.**
> "Move `Faq::forContext(...)` queries from `partials/faq`, `product-faq`, `display-faq` into `Portal\LandingController` (or a view composer) and pass `$faqs` to the views. No visual change."

> **P5 — Nav duplicate mobile overlay.**
> "In `resources/views/portal/partials/nav.blade.php` there are two mobile overlays bound to `mobileOpen` (the `<div x-show=\"mobileOpen\">` inside `<header>` and the drawer after it). Remove the in-header one, keep the drawer, and verify open/close + body scroll lock on mobile."

> **P6 — Ad-support/help-center icons as uploads.**
> "Change `help_center_categories.icon` and `ad_support_collections.icon` admin inputs to accept either a URL or an uploaded SVG/PNG (public disk), and render them through `PortalContent::resolveUrl()`-style resolution in the portal views."

> **P7 — Remaining `lang/` brand strings.**
> "Search `lang/en` and `lang/ar` for `noon`/`نون` used by the portal or partner panels (e.g. `common.sell_on_noon_title`, `partner_brand_title`, `helpcenter_title`, `advertise_*`) and rebrand to Nawy; keys used only as CMS fallbacks can be removed after confirming no other references."

> **P8 — Blog absolute og:image.**
> "In `resources/views/portal/blog/show.blade.php` make `og:image` absolute (`url(Storage::url(...))`) and add `og:url` + `twitter:card`."
