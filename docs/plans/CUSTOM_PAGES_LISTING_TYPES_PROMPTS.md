# Custom Pages: listing-type + all-categories filters, and "Nawy Now" page/button

Sub-agent prompts. Run in order: **A1 → (A2 ∥ A3) → A4 → A5**. Each agent commits to `main` when done.

## 0. Analysis (facts verified in code + `marketplace_platform.sql`)

| Fact | Where | Consequence |
|---|---|---|
| `custom_pages` has no listing-type or all-categories column; categories live in `custom_page_category_map` | SQL:27059, `CustomPage.php` | Need migration: `listing_types` JSON (null = all) and `all_categories` bool |
| Three separate listing tables: `admin_listings`, `vendor_listings`, `marketer_listings` (marketer rows have `source_type`/`source_listing_id`, no inventory) | SQL:232 / 45046 / 32272 | "Type" = admin / vendor / marketer, not a column on one table |
| **`GET /products` (`Customer\ProductController::index`) is NOT the buy-box path.** It loads *all* active admin listings unpaginated, then paginates vendor listings, and never includes marketer listings. Category filter is applied separately in each block (`whereIn p.category_id`) | `ProductController.php:50-175` | The type filter must gate each block; marketer block must be added; admin block is unpaginated (perf risk for a large admin catalogue) |
| `ProductQueryService` reads `product_country_buybox` (one winner row per product, `listing_type` admin/vendor/marketer, priority admin > vendor > marketer). Used for `facets()` | `ProductQueryService.php`, SQL:36677 | Facet counts must respect the selected types too, or the sidebar shows counts that don't match the grid |
| `CategoryService::getCategoryIdsForFilter` returns the union of descendant IDs of the linked categories. **With zero linked categories it returns `[]`, and `whereIn(..., [])` matches nothing** | `CategoryService.php:~396-420` | "All categories" needs an explicit code path (no category restriction), otherwise the page is empty |
| `attributeFacets()` returns nothing when `$categoryIds` is empty | `ProductQueryService.php:130` | For all-categories, facets need a fallback (filterable attributes present in the result set) |
| Admin form already has a category search picker (`admin.page-builder.search.categories`, custom JS) and select2 is used elsewhere (`admin/admins/_form`, `flash-sales/edit`) | `_form.blade.php`, `admin/admins/_form.blade.php` | Use the same select2 multiple for types; add an "All categories" checkbox that disables the picker |
| Storefront catch-all `[...categorySlug]/page.tsx` passes only `?category=<slug>`; `has_filters` comes from `res.data.category.has_filters` | `frontend/app/[locale]/(noon)/[...categorySlug]/page.tsx` | Types stay **server-side** (read from the page, not a URL param), so users cannot bypass them. No change needed in the catch-all route |
| Slugs are unique across categories and custom pages through the polymorphic `slugs` table | `CustomPageService::uniqueSlug` | Seeder must reserve `nawy-now` via `Slug` and be idempotent |
| Existing fixed button: `LiveStreamButton` at `fixed bottom-20 right-4 md:bottom-6 md:right-6 z-50`, mounted in `(noon)/layout.tsx` | `frontend/src/components/shared/LiveStreamButton.tsx` | New button must stack with it (no overlap), support AR/RTL, and be i18n'd in `locale/{en,ar}.json` |
| Frontend is a modified Next.js: read `frontend/node_modules/next/dist/docs/` first (`frontend/AGENTS.md`) | `frontend/AGENTS.md` | Mandatory for frontend agents |
| Shared test DB deadlocks under parallel agents | `docs/QC_RESULTS.md` (F06/F08) | Each agent uses its own DB, e.g. `DB_DATABASE=marketplace_test_a2` |

### Decisions

1. **Storage:** `custom_pages.listing_types` (JSON array of `admin|vendor|marketer`, `NULL` = all types) and `custom_pages.all_categories` (bool, default false). No new tables.
2. **Semantics of a type filter (per block):** only listings of the selected types are shown. Admin block runs only if `admin` is selected; vendor block only if `vendor`; a new marketer block only if `marketer`. Empty selection is rejected by validation (use "all" = null instead).
3. **All categories:** `all_categories=true` means no category restriction, and the picker is ignored. Otherwise the union of linked categories plus their descendants (existing behaviour) applies. Zero linked categories with `all_categories=false` is rejected by validation, so the state "empty page" cannot be saved.
4. **Inherited filters:** unchanged for a category selection (attributes of the selected categories and descendants). For all-categories, use the filterable attributes that actually appear in the result set.
5. **Admin listings pagination:** keep behaviour, but cap/paginate if the type filter makes the admin block the only source (Nawy Now), otherwise the page loads every admin listing. A3 must handle this.
6. **Nawy Now:** a normal custom page (`slug=nawy-now`, `listing_types=['admin']`, `all_categories=true`, `has_filters=true`) created by an idempotent seeder. The frontend button just links to `/nawy-now`.

### Edge cases every agent must keep in mind

- Soft-deleted / inactive custom page → 404 on the storefront.
- Linked category deleted → FK cascade removes the map row; a page left with 0 categories and `all_categories=false` must not 500 (return empty result, not a SQL error).
- Categories changed → `CategoryService::flushCache()` and any product-payload cache key must include `listing_types` and `all_categories`, or stale results are served.
- Type inactive/out of stock: keep existing status/stock rules per block.
- Pagination `meta.total` must equal the filtered count; facet counts must match the grid.
- Filters combined with types (price, brand, rating, attributes, sort) still work; sort applies across the merged admin + vendor + marketer set or is clearly documented.
- Wishlist flags, promo badges and sponsored injection still work when a block is absent.
- Guest vs logged-in customer, en vs ar, RTL layout.
- Money values are base-currency integers (`/100` in code is % math, not a bug, see memory).
- Admin permission: existing `admin.permission:categories.view` on custom-page routes stays; do not loosen it.
- Validate on the server: `listing_types.*` in `admin,vendor,marketer`, `category_ids.*` exist, XSS-safe names.

### Rules for every agent

- You are a senior engineer + senior QC + problem solver. After implementing, **test it** (feature tests + manual HTTP/curl or browser check), find gaps (login/permission issues, edge cases above), and **fix them before finishing**.
- Read `/var/www/marketplace/marketplace_platform.sql` for the tables you touch before coding. Use a migration for schema changes (do not hand-edit the dump).
- Commit to `main` with `git add <only your files>` (never `git add -A`; other agents work in parallel). Message: `feat(custom-pages): <what>`. Append the trailer `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.
- Append a short PASS/FIXED/GAP section for your task to `docs/QC_RESULTS.md` in the same commit.
- Use your own test DB (`DB_DATABASE=marketplace_test_<agentid>`).

---

## A1: Backend data layer (run first)

```
Task: add listing-type and all-categories support to custom pages (data layer only).

Read: marketplace_platform.sql (custom_pages, custom_page_category_map, admin_listings,
vendor_listings, marketer_listings), backend/app/Models/CustomPage.php,
backend/app/Services/CustomPageService.php, backend/app/Services/Customer/CategoryService.php
(resolveSlug, getCategoryIdsForFilter, getDescendantIds, flushCache).

Do:
1. Migration (new timestamped file): custom_pages.listing_types JSON NULL, custom_pages.all_categories
   BOOLEAN NOT NULL DEFAULT 0. Reversible down().
2. CustomPage model: fillable, casts (listing_types => array, all_categories => boolean), constant
   LISTING_TYPES = ['admin','vendor','marketer'], helper allowedListingTypes(): array (null/empty => all three),
   helper allowsType(string).
3. CategoryService: add resolveCustomPageScope(CustomPage): array{category_ids: ?list<string>, listing_types: list<string>}.
   category_ids = null when all_categories is true (NO restriction), else union of descendants.
   Make getCategoryIdsForFilter safe: a custom page with all_categories returns null-equivalent (document how callers
   treat it); a page with no categories and all_categories=false returns [] and callers must short-circuit to an empty
   result instead of running whereIn([]).
4. CustomPageService: syncCategories tolerates an empty list; add normalizeListingTypes(?array): ?array
   (dedupe, keep order, drop unknowns, all three or empty => null).
5. Unit/feature tests: model casts, scope resolution (all categories, subset with descendants, empty, deleted category),
   normalizeListingTypes.

Do NOT touch controllers/views/frontend (other agents own them).
Verify: php artisan migrate on your test DB, run your tests, fix failures.
Commit to main (only your files).
```

## A2: Admin panel add/edit form (after A1, parallel with A3)

```
Task: admin UI + validation for the new custom page fields.

Read: backend/app/Http/Controllers/Admin/CustomPageController.php (store/update/edit/syncCategories),
backend/resources/views/admin/custom-pages/{_form,create,edit,index}.blade.php and the JS that powers the category
picker (grep ROUTES_CUSTOM_PAGE / custom-page-category-list), an existing select2 multiple in
resources/views/admin/admins/_form.blade.php, lang/{en,ar}/admin.php custom_pages block.

Do:
1. Form: a "Listing types" select2 MULTIPLE (options All types [empty], Admin listings, Vendor listings, Marketer listings)
   with an "All types" convenience so nothing selected = all. Prefill on edit and keep old() on validation error.
2. Form: an "All categories" checkbox above the category picker. When checked, disable/hide the picker and the
   inherited-filters card explains "all filterable attributes in results". When unchecked, the picker works as today.
3. Controller store/update validation: listing_types nullable|array, listing_types.* in admin,vendor,marketer;
   all_categories nullable|boolean; require category_ids min:1 when all_categories is false (clear 422 message, en+ar).
   Persist via CustomPageService::normalizeListingTypes; call syncCategories (empty list when all_categories is true).
4. Index table: show a badge column for types and "All categories".
5. Add en + ar lang keys. RTL check.
6. Feature tests (HTTP as admin): create with types + categories, create with all_categories, update, validation errors,
   old input preserved, unauthorized admin (without categories.view) gets 403, XSS in names escaped.

Also manually open create/edit in the browser (or curl with session) and confirm the JS toggle works.
Fix every issue found before finishing. Commit to main (only your files).
```

## A3: Storefront API (after A1, parallel with A2)

```
Task: make GET /api/customer/v1/{country}/products honour the custom page's listing types and all-categories flag.

Read: backend/app/Http/Controllers/Customer/ProductController.php (index + resolvePageBuilder + the response payload
'category' block), Services/Customer/ProductQueryService.php (facets, attributeFacets, applyFilters, buy-box baseQuery),
Services/Customer/ListingQueryService.php (applyFilters, toAdminCardShape, toCardShape, marketer card if any),
Services/Customer/CategoryService.php (A1 scope helper), BuyBoxRebuildService (marketer candidates),
SponsoredAdService inject, and the cache keys used for the products payload.

Do:
1. In index(): if the resolved slug is a custom page, use its scope (A1). Skip the admin block unless admin allowed,
   skip vendor unless vendor allowed, ADD a marketer listings block (active marketer_listings joined to products/variants,
   same category/price filters, card shape consistent with existing cards, referral info preserved) only when allowed.
   When category_ids is null (all categories) apply no category clause; when [] return an empty, well-formed payload.
2. Pagination: total, last_page and items must be consistent across the merged blocks. If the admin block is the only
   allowed type, paginate it (no unbounded load). Document how sort works across blocks.
3. facets(): apply the same type restriction (buy-box winner or listing tables; pick the approach that keeps counts equal
   to the grid and explain it in a code comment). attributeFacets for all categories: filterable attributes present in the
   filtered set (bounded, indexed query, no N+1).
4. Response 'category' payload for a custom page must include has_filters, name, and the effective listing_types so the
   frontend can show them; keep the shape backward compatible.
5. Cache: include listing_types + all_categories (or the page updated_at) in any cache key; verify a change in admin is
   visible on the next request.
6. Tests: admin-only, vendor-only, marketer-only, multi-type, all types, all categories, subset with descendants,
   zero-category page, price/brand/attr/sort combined with types, pagination totals, facet counts == grid, guest and
   customer, inactive/deleted page -> 404, non-custom-page category browsing unchanged (regression).
7. Check query counts / EXPLAIN on the new marketer + admin queries.

Fix everything found. Commit to main (only your files).
```

## A4: Nawy Now seeder + homepage fixed button (after A2 and A3)

```
Task: Nawy Now custom page seeder and the fixed homepage button.

Read: frontend/AGENTS.md then frontend/node_modules/next/dist/docs/ for the relevant guides,
frontend/src/components/shared/LiveStreamButton.tsx, frontend/app/[locale]/(noon)/layout.tsx,
frontend/locale/{en,ar}.json, frontend/i18n/*, database/seeders/{HomePageSeeder,CategoryTreeSeeder}.php, Models/Slug.php,
CustomPageService::uniqueSlug.

Backend:
1. Seeder NawyNowCustomPageSeeder (register it where other seeders run): idempotent updateOrCreate on the slug
   'nawy-now'; name_en "Nawy Now", name_ar "نوي الآن"; listing_types ['admin']; all_categories true; has_filters true;
   is_active true; SEO texts en/ar; create the Slug record (sluggable = the page). If another entity already owns the
   slug, fail loudly with a clear message. Re-running must not duplicate anything and must not overwrite admin edits
   to unrelated fields unless documented.
2. Test: seeder twice = one page + one slug, GET /products?category=nawy-now returns admin listings only, filters
   (price/brand/attrs/sort) work, sidebar facets are present.

Frontend:
3. New client component NawyNowButton, fixed like LiveStreamButton but with the text "Nawy Now" / "نوي الآن"
   (i18n keys in locale/en.json and ar.json; no hard-coded strings), linking with the locale-aware Link to /nawy-now.
   Stack it above LiveStreamButton (no overlap on mobile bottom nav or desktop), z-index below modals/drawers,
   RTL mirrored side, focus ring + aria-label, hidden on the /nawy-now page itself. Mount in (noon)/layout.tsx.
4. Confirm /nawy-now renders through the existing catch-all route with the filter sidebar (desktop + mobile sheet) and
   the empty state when there are no admin listings.
5. npm run lint and the type-check/build for touched files must pass. Verify in a browser (en + ar, mobile + desktop);
   screenshots or a written check list in QC_RESULTS.

Fix every issue found. Commit to main (only your files).
```

## A5: Final QC and regression sweep (last)

```
Task: senior-QC pass over everything from A1-A4. You may fix bugs you find (small, targeted commits).

1. Re-read the diff since the first A1 commit (git log / git diff) and the edge-case list in this file. Check each item.
2. Run the whole affected backend suite (own test DB) plus lint/type-check/build for the frontend. Report exact
   failures; fix real ones.
3. End-to-end matrix against a seeded DB: create pages in admin for each combo (admin / vendor / marketer / two types /
   all types) x (all categories / one parent category with children / two categories / one category no children),
   then load each slug from the storefront API and the Next.js page. Assert every returned item's type and category is
   inside the scope, and that nothing valid is missing.
4. Auth/permission cases: admin without categories.view, logged-out customer, logged-in customer, wishlist toggle on
   an admin card and on a marketer card, cart add from each card type.
5. Regression: ordinary category pages, search, home page, flash-sale/mega-deal pages, page-builder custom_page blocks,
   navigation, the LiveStreamButton position.
6. Data-integrity: delete a linked category, deactivate/soft-delete a page, change types and check cache freshness,
   slug collision with an existing category.
7. Write results (PASS / FIXED / GAP) to docs/QC_RESULTS.md. Anything you cannot fix goes in as a GAP with a reason.
Commit to main (only your files).
```
