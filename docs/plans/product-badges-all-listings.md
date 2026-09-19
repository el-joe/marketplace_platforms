# Plan: Product promo badges across product / vendor / admin / marketer listings

## 0. Source of truth: schema check (`backend/database/schema/mysql-schema.sql`)

Findings from reading the dump before planning:

1. `product_promo_badges` in the dump has only `product_id` — it is **stale**. The live DB also has `vendor_listing_id`, `admin_listing_id`, `marketer_listing_id` (migrations `2026_09_19_120000`, `_150000`). The dump also still has `products.is_mega_deal`, which `2026_09_19_110000_drop_is_mega_deal_from_products` removes. Laravel loads the dump and then runs later migrations, so behaviour is correct, but the dump must be regenerated (`php artisan schema:dump`) once schema work is done (repo convention: feature commits touch the dump, e.g. 6d88d09).
2. **No integrity rule on ownership.** Nothing stops a row having two owner columns set (e.g. vendor + admin). Readers assume at most one. Needs a `CHECK` (MySQL 8.0.16+, server is 8.0.46) : at most one of the three owner columns is non-null.
3. Listing FKs cascade on hard delete. `marketer_listings` and `vendor_listings` use SoftDeletes, so badges of soft-deleted listings linger but are never read (the buy-box excludes them) — acceptable; document it.
4. No index for the product-level lookup (`product_id` + all three owners NULL). Existing `(product_id, is_active, sort_order)` index covers it; verify with EXPLAIN in Wave B.
5. `icon_key` is a free string (max 50). Frontend resolves it against the full lucide `icons` map and silently falls back to a Tag icon. Add a server-side whitelist so typos are rejected instead of silently rendering a wrong icon.

## 1. Current state (already built this session, uncommitted)

- Data: `product_promo_badges` with `product_id` + three nullable listing owner columns; `Product::promoBadges()` (product-level, active only), `Product::allPromoBadges()`, `promoBadges()` relation on Vendor/Admin/MarketerListing, `ProductPromoBadge::scopeProductLevel()`.
- Write path: `App\Services\Shared\PromoBadgeSyncService` (replace-style sync, owner-scoped) used by admin product update, admin listing, partner listing, marketer listing.
- UI: admin product edit "Promo Badges" tab (edit only); admin-listing edit panel; partner listing show panel; marketer listing page `/listings/{id}/promo-badges`. Shared partial `resources/views/shared/promo-badges-editor.blade.php`.
- Read path: `ProductQueryService::buildProductsPayload` (cards) and `ListingDetailController::productShape` (PDP) prefer listing badges, fall back to product-level.
- Frontend: `product-card.tsx`, `base-info.tsx`, `small-screen-price.tsx` map `promo_badges`.
- Tests: `ProductPromoBadgeTest` (5 passing).

## 2. Gaps to close

| # | Gap | Wave |
|---|-----|------|
| G1 | No DB `CHECK`, stale schema dump, no icon whitelist, no cleanup of placeholder "hello world%" rows | A |
| G2 | No cache busting when badges change (PageCacheService / CachedListingResolver / page blocks) — storefront can serve stale badges | A |
| G3 | Card/list surfaces other than `ProductQueryService` don't emit `promo_badges`: `ListingQueryService`, `BrowseService`, `PageRendererService`, `FlashSaleItemResource`, `WishlistResource`, `CartItemResource`, `SponsoredProductService`, `Api/Customer/{Admin,Vendor,Marketer}ListingResource`, PDP related/FBT/top-picks/more-from-brand lists | B |
| G4 | Product-vs-listing fallback logic is duplicated in two places; needs one batched resolver (no N+1) | B |
| G5 | Default (product-level) badges only editable on product **edit**, not **create**; no activity-log entry; hard-coded English strings; admin-listing create flow has no badge step | C |
| G6 | Partner/marketer: hard-coded Arabic strings (no lang files); no API parity (`api_partner.php`, `api_vendor.php`, `api_marketer.php`); no authorization tests (cross-vendor/marketer 403, non-variant marketer listings) | D |
| G7 | Frontend: verify every card/PDP/cart/wishlist surface renders real `promo_badges`, no placeholders, types complete, `cart-item.tsx` AnimatedBadge source checked | E |
| G8 | Full regression + review | F |

## 3. Execution waves (sub-agents)

Shared rules for every agent: do not `git commit`; touch only the files in your ownership list; run **only your own tests**, against a private database (`DB_DATABASE=marketplace_test_<wave letter>`, create it first) because RefreshDatabase runs collide on a shared DB; report changed files + test results honestly.

- **Wave A (blocking, alone)** — schema + write-path foundations. Owns: `database/migrations/*`, `database/schema/mysql-schema.sql`, `config/promo_badges.php`, `app/Services/Shared/PromoBadgeSyncService.php`, `app/Console/Commands/*PromoBadge*`.
- **Wave B, C, D, E (parallel, after A)** — disjoint file ownership:
  - **B** backend read path: `app/Services/Customer/*`, `app/Http/Resources/**`, `Customer/ListingDetailController.php`, new `app/Services/Customer/PromoBadgeResolver.php`, new tests `tests/Feature/PromoBadgeReadPathTest.php`.
  - **C** admin panel: `Admin/ProductController.php`, `Admin/AdminListingController.php`, `StoreProductRequest`/`UpdateProductRequest`, `resources/views/admin/**`, `routes/admin.php`, `lang/*/admin.php`, `tests/Feature/Admin/PromoBadgeAdminTest.php`.
  - **D** partner + marketer: `Partner/ListingController.php`, `Marketer/ListingController.php`, `Api/**` partner/vendor/marketer listing controllers, `routes/partner.php|marketer.php|api_*.php`, `resources/views/partner|marketer/**`, `lang/*/partner.php|marketer.php`, `tests/Feature/PromoBadgePartnerMarketerTest.php`.
  - **E** frontend: `frontend/src/**` only.
- **Wave F (after B–E)** — integration: full backend test run, `code-review`, cross-check every owner type end to end, update this doc with results.
