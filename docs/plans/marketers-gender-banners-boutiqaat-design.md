# Marketers list + details: gender split, marketer banners, boutiqaat-style design, client filters

Date: 2026-10-24. Team: **TL/BA** (this plan), **Dev** (senior full-stack), **QA** (senior tester).
Inputs: client message (Arabic, below), two HTML exports of boutiqaat.com (`/ar-sa/men/` list, `/ar-sa/men/amin-alord/cb/` details) → trimmed copies in `docs/reference/boutiqaat/{list-men,detail-celebrity}.trim.html` (header/footer removed, repeats collapsed; **CSS was not provided**, so fidelity = structure, grid, proportions, behaviours; colours/fonts stay on our design tokens with a gold accent).
Hard rule from the owner: **keep our site layout (header, footer, mobile nav, popups) exactly as is** — only the two pages' *content areas* are re-designed. Take from boutiqaat only: (1) gender split men/women, (2) the ad-banner carousels, (3) the details-page product grid + filter sidebar pattern.

## 0. Requirements (merged: client + owner)
| # | Requirement | Source |
|---|---|---|
| R1 | Marketer list page shows marketers **split by gender** (male / female) — tabs Men (default) / Women, boutiqaat-style portrait cards in carousel rows | owner + client |
| R2 | List page shows each marketer **type** (influencer / affiliate / broker) as its own section ("title + view-all" with carousel/grid), within the selected gender | owner |
| R3 | List page has a **banners section** (hero slider on top; optional 2-column ad strip) | owner |
| R4 | Marketer details page = **same content as today** (products etc.) in the boutiqaat layout (title, **banners carousel**, search + sort bar, product grid, right-side filter sidebar) + banners inside | owner + client |
| R5 | **Filters on details page**: ads the marketer promotes **for sellers** vs **own products**; split ads for sellers into **products** vs **classified ads**; own-products filter; category/brand/price/discount; sort | client |
| R6 | New admin entity **Marketer Banners**: admin names the banner; checkbox **Global**; when Global → choose gender scope (All / Men / Women); when not Global → multi-select marketers where the banner appears inside | owner |
| R7 | Marketer **gender** (male/female) must exist (none today) and be editable by marketer (own profile) and admin | derived |
| R8 | Design = Nawy slides 10 (celebrities lists) and 11 (brokers & companies lists, classified) look, via boutiqaat structure | client |

### Decisions / assumptions (flagged for owner confirmation)
1. **Gender-less marketers** (all existing ones, `gender IS NULL`) appear in **both** tabs until gender is set (no one disappears at deploy). Admin list gets a gender column + filter "not set".
2. **Banner visibility rule**: `Global` banner → shown on the **list page** (hero/strip, only on the tab matching its gender scope; `All` = both tabs) **and** inside the **details page of every marketer** whose gender matches the scope (`All` = every marketer). **Non-global** banner → shown only inside the **details pages of the selected marketers** (not on the list page).
3. Banners live in the existing **`banners` table/admin** (reuse images desktop/mobile × en/ar, schedule, status, priority, click tracking, `BannerService`) with new placement codes + targeting columns — not a new banner system.
4. **Out of scope now (client wish-list, phase 2)**: banner targeting by marketer *type* (celebs only / sellers only / brokers only / all-except), algorithmic targeting (top stores / best-selling marketers this month), photo/video split of promoted ad creatives on the profile. Schema leaves room (`marketer_types` JSON-free pivot can be added later). To be specified by owner.
5. Travel offers are **not** added to the profile (not requested).
6. URLs: keep `/marketers` and `/marketer/[slug]`; gender via `?gender=men|women`, type via `?type=`.

## 1. Audit summary (current state)
- Frontend (Next 16, next-intl, Tailwind 4, Swiper): list `frontend/app/[locale]/(noon)/(pages-with-footer)/marketers/page.tsx` + `src/features/noon/marketers-list/**` (tabs All/Influencers/Affiliates only, gradient `marketer-card`); details `.../marketer/[slug]/page.tsx` + `src/features/noon/marketer-profile/**` (banner, sidebar, tabs own / vendor campaigns / marketer campaigns / classifieds, load-more grid). APIs `GET /api/public/v1/{country}/marketers` and `/marketers/{slug}` (`App\Http\Controllers\Api\Public\MarketerProfileController`). Envelope `ApiResponse::success|paginated` (`{success,message,data}`; paginated → `data.items`, `data.meta`).
- Backend: `Marketer` has no type/gender; type = `marketer_jobs.key` via pivot (`MarketerType::heldBy()`); `MarketerProfile` has `profile_slug`, avatar/banner files, bios, specialties, measurements, broker fields. Gender exists only on `customers`.
- Banners: `app/Models/Banner.php` (uuid, `placement_code` ENUM extended by migrations, `country_id` nullable, link fields, schedule, status, priority, `File` morph images desktop/mobile en/ar), `BannerPlacementDefinition`, `BannerService::getActivePlacement` (single banner), admin `Admin/BannerController` + `resources/views/admin/banners/*` (permissions `banners.view/create/edit/delete`), click endpoint `POST banners/{banner}/click`.
- Invariants to respect: UUID PKs; money BIGINT; never edit/delete migrations or dump schema; relational (no JSON blobs for structured data); admin inline `hasPermissionTo` pattern.

## 2. Data model (new migrations only)
1. `marketer_profiles.gender` `enum('male','female') NULL`, index. (+ `App\Enums\MarketerGender`.)
2. `banners.placement_code` ENUM extended (new migration altering the enum the same way earlier migrations did) with: `marketers_list_hero`, `marketers_list_strip`, `marketer_profile_slider`.
3. `banners`: add `is_global boolean NOT NULL DEFAULT 0` and `gender_scope enum('all','male','female') NOT NULL DEFAULT 'all'` (meaningful only when `is_global`); used only by the marketer placements.
4. Pivot `banner_marketer` (`banner_id` uuid FK cascade, `marketer_id` uuid FK cascade, unique pair, timestamps) — selected marketers when `is_global = 0`.
5. `Banner::marketers()` BelongsToMany; scopes `forMarketerList($gender)`, `forMarketerProfile(Marketer $m)`; `BannerService::activeForMarketerList(placement, gender, countryId)` returning an ordered collection (priority, schedule, status, country null|match, device both) and `activeForMarketerProfile(Marketer, countryId)`.
6. Seeder/factory updates (factories for banner marketer placements; marketer profile gender).

## 3. API contract (all under `GET /api/public/v1/{country}/…`, envelope `ApiResponse`)
**`GET marketers`** params: `gender=male|female` (omit = both), `type=influencer|affiliate|broker`, `search`, `sort=top|newest|name`, `page`, `per_page` (default 24), `grouped=1`.
- default → `data.items[]`, `data.meta` (unchanged shape) + each item adds `gender`, `type` (primary job key), `type_label`.
- `grouped=1` → `data.sections[] = { type, label, total, items[ <=12 cards ] }` for the 3 types (empty types omitted) + `data.banners = { hero: Banner[], strip: Banner[] }` for the requested gender (banner resource: `id,title,subtitle,cta_label,link,image_desktop,image_mobile,priority` localized, absolute URLs, `click_url`).
- cards: `id,slug,name,avatar_url,banner_url,type,type_label,gender,specialty,followers/products counts (existing fields kept)`.

**`GET marketers/{slug}`** (existing sections kept for BC) new params: `source=all|own|for_sellers`, `kind=all|products|classified` (applies to `for_sellers`; `own` = products), `q`, `category[]`, `brand[]`, `price_min`, `price_max`, `discount=1`, `sort=newest|price_asc|price_desc`, `page`, `per_page`.
- adds `data.profile.gender`, `data.banners[]` (profile slider banners for this marketer), `data.listings = { items[], meta }` (the filtered unified grid), `data.facets = { sources[{key,count}], kinds[{key,count}], categories[{id,name,count}], brands[...], price{min,max}, discounted_count }`.
- classified ads the marketer promotes for sellers (campaigns with `classified_listing_id`) are returned as cards with `kind=classified`.
Frontend work packages may mock this contract until the backend lands.

## 4. Work packages
Order: **WP-A** → (**WP-B** ‖ **WP-C** ‖ **WP-D** ‖ **WP-E**) → **WP-F** (QA). Each agent: no commits (TL commits), never edit existing migrations/schema dump, pint on PHP, tests via `service mariadb start` (marketplace_test root/root; local skip-worktree patches exist — leave them).

### WP-A — Data layer (Dev)
Implement §2 completely (migrations, enum, models, factories, `BannerService` list/profile methods, `BannerPlacementDefinition` entries with recommended sizes: list hero 1700×450, strip 840×300 ×2, profile slider 1000×280), tests `tests/Feature/Marketer/MarketerBannerTargetingTest.php` (global/gender/selected/schedule/status/country rules incl. the visibility rule in §0-2).

### WP-B — Public API (Dev)
Implement §3 in `Api/Public/MarketerProfileController` (+ services/resources, keep `MarketerProfileCache` correct for the new filters, no N+1, throttle unchanged), expose gender in resources, classified-campaign cards, facets. Tests: `tests/Feature/Marketer/MarketerPublicDirectoryGenderTest.php`, `MarketerProfileFiltersTest.php` (+ keep `MarketerSpecialtyDirectoryTest` green).

### WP-C — Admin + marketer panel (Dev)
(a) Banner admin: new placements selectable, when a marketer placement is chosen show **Global** checkbox → gender scope select (All/Men/Women, default Men) / when unchecked a multi-select of marketers (search by name/phone, existing select pattern in admin) ; validation in `StoreBannerRequest/UpdateBannerRequest`; banners index column/filter for marketer placements; lang en/ar. (b) Gender field: admin marketer show/edit (`marketers.profile.update`), marketer panel Blade profile + `Api/Marketer/ProfileController`, validation `in:male,female`; admin marketers list gender column + filter incl. "not set". (c) tests `tests/Feature/Admin/MarketerBannerAdminTest.php`, gender update tests.

### WP-D — Frontend: marketers list page (Dev)
Rebuild `src/features/noon/marketers-list/**` per R1–R3, R8 using `docs/reference/boutiqaat/list-men.trim.html` as the structural reference: top hero banner slider (Swiper, autoplay, RTL-aware, banners from `data.banners.hero`), gender tabs Men|Women (default Men, `?gender=`), type sections (title + gold "view all" → `?type=` grid mode with pagination/load-more), portrait celebrity-style cards (image ~283×324, name below, link to `/marketer/[slug]`), carousels with arrows, 2-col ad strip from `data.banners.strip`; skeletons, empty states, i18n (`locale/en.json`, `locale/ar.json`), RTL, responsive. **Do not touch header/footer/layout files.** Types in `helpers/types.ts`; fetch via `api.ts` using the contract (mock fallback behind a flag for local work).

### WP-E — Frontend: marketer details page (Dev)
Rebuild `src/features/noon/marketer-profile/**` per R4–R5, R8 using `detail-celebrity.trim.html`: breadcrumb + H1, banners carousel (`data.banners`, click tracking via banner click endpoint), search + sort bar, 4-column product grid (cards like boutiqaat: image, brand, name, old/new price, discount %, "exclusive" badge, quick action), **right-side (RTL: right) filter sidebar** with facets: Source (all / own products / promoted for sellers), Kind (products / classified ads), category, brand, price range, discounts; mobile = slide-over filter drawer; infinite/ load-more pagination; classified cards link to `/classified/find/[slug]`; keep existing profile header info/actions/sidebar content (bio, contact, social) reorganised **without removing features**. i18n en/ar. **Do not touch header/footer/layout files.**

### WP-F — QA (senior tester, independent)
Run all new + related suites (`tests/Feature/Marketer`, `tests/Feature/Admin --filter=Banner`, portal/admin suites), frontend `npm ci/install` + `npx tsc --noEmit` + `npm run lint` + `npm run build` where feasible; local end-to-end: isolated DB `marketer_shots` with demo data (≥3 types × both genders, banners global/selected), backend `php artisan serve`, frontend `next build/start` or dev with `NEXT_PUBLIC_API_URL` pointing local, Playwright screenshots of list (men/women, ar/en) and details (filters, banners) → read them, fix layout/RTL bugs; verify R1–R8 checklist, visibility rule matrix, no layout changes in header/footer, no migrations edited. Report pass/fail ≤30 lines.

## 5. Acceptance checklist
- [ ] `/marketers` → hero banners, Men/Women tabs (Men default), sections per type with portrait cards + view-all, ad strip; gender-less marketers appear in both tabs.
- [ ] `/marketer/[slug]` → banners carousel, search/sort, grid + filter sidebar (source/kind/category/brand/price/discount), classified ads promoted for sellers filterable.
- [ ] Admin: create banner with marketer placement: Global + gender scope, or selected marketers; appears per rule §0-2 and only when active/in schedule.
- [ ] Gender editable by marketer and admin; API returns it.
- [ ] Header/footer/layout untouched; RTL + LTR OK; mobile OK.
- [ ] New migrations additive; all tests green.

## 6. Open questions for owner (non-blocking, defaults applied)
Banner targeting by marketer type / algorithmic (phase 2); photo/video split of promoted creatives; whether non-global banners should also show on the list page; final gold/brand colours and exact boutiqaat CSS (send stylesheet if pixel-fidelity needed).
