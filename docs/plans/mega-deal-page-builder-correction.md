# Correction Plan: Mega Deal Must Read From Page Builder, Not a Flat Column

## Background
The earlier plan (`docs/plans/dynamic-badges-and-classified-actions.md`, Task A/B/C — commits `6d88d09`, `f684856`, `2ff43ca`) added a flat `products.is_mega_deal` boolean and wired the PDP "Mega deal" badge to it. The user has now clarified: **Mega Deal is an existing Page Builder block type (`mega_deals`)**, fully built out already (admin CRUD, product picker, countdown, visibility windows) and completely disconnected from that new flat column. The flat column is redundant, will never be set by anything real, and must be replaced with a live query against the Page Builder data.

No design/style changes in any task below — logic/data-wiring only.

## Confirmed facts (read-only research, not yet verified against current file line numbers — each sub-agent must re-check before editing)
- Page Builder hierarchy: `pages` -> `page_sections` -> `page_blocks` (`App\Models\PageBlock`, `backend/app/Models/PageBlock.php`). Blocks have `block_type` (mega_deals is one), a `config` JSON column, `is_visible`, `visible_from`, `visible_until`, `country_override`.
- Product association: `App\Models\PageBlockProduct` pivot (`page_block_id`, `product_variant_id`, `position`, `tab_index`, `added_by_admin_id`) — joins through `ProductVariant` to `Product`.
- Hydration/rendering logic already exists: `backend/app/Services/Shared/PageBuilderService.php` (skeleton query ~L130 includes `mega_deals` in `block_type` whereIn; hydration switch ~L556-580 for `mega_deals`).
- Admin management already exists and is fully functional: `backend/app/Http/Controllers/Admin/PageBuilderController.php`, routes `backend/routes/admin.php` (~L460-491), Blade config form `backend/resources/views/admin/page-builder/config-forms/mega-deals.blade.php`, seeded in `backend/database/seeders/BlockTypeSeeder.php` (~L62-63). **No new admin panel work is needed for Mega Deal** — admins already create/manage these sections today.
- Currently wrong/redundant, added by the prior task and must be removed/reworked:
  - Migration `backend/database/migrations/2026_09_19_100000_create_product_promo_badges_table.php` — the part adding `is_mega_deal` to `products` (NOT the `product_promo_badges` table itself, which is unrelated and correct/keep).
  - `backend/app/Models/Product.php` (~L36 fillable, ~L58 casts) — `is_mega_deal` references.
  - `backend/app/Services/.../ProductQueryService.php` (~L254, `p.is_mega_deal` column read) — replace with a real join/subquery against active `mega_deals` page blocks.
  - `backend/app/Http/Resources/Customer/ProductDetailResource.php` (~L69, `is_mega_deal` field) — replace with computed value from Page Builder data, not a raw model attribute.
  - `frontend/src/features/noon/productView/base-info.tsx` (~L49, gated on `product.is_mega_deal`) — no change needed here IF the API field name (`is_mega_deal`) stays the same in the response; only the backend computation changes. Confirm field name is preserved so frontend doesn't need touching.

**Note:** `product_promo_badges` table, model, `ProductPromoBadge`, and the rotating `AnimatedBadge` wiring (PDP + listing card) are UNRELATED to this correction and are correct as-is — do not touch them in any task below.

## Tasks / Sub-agent Prompts

### Task F — Backend: replace flat `is_mega_deal` column with real Page Builder query
```
In /var/www/marketplace/backend (Laravel), correct a mistake from a previous task: "Mega Deal" was wired to a new flat `products.is_mega_deal` boolean column, but Mega Deal is actually an existing Page Builder block type (`page_blocks.block_type = 'mega_deals'`), fully built out with its own admin UI (backend/app/Http/Controllers/Admin/PageBuilderController.php) and product association via `App\Models\PageBlockProduct` (pivot: page_block_id, product_variant_id, position). The flat column is disconnected from reality and must be removed/replaced.

Before editing, read backend/app/Services/Shared/PageBuilderService.php in full to understand exactly how it determines an "active" mega_deals block (visibility: is_visible, visible_from, visible_until, country_override) and how it resolves block -> PageBlockProduct -> ProductVariant -> Product. Reuse that exact logic/query shape — do not reinvent visibility rules.

1. Write a migration to DROP the `is_mega_deal` column from `products` (the `product_promo_badges` table from the same original migration file is unrelated and must NOT be touched/dropped).
2. Remove `is_mega_deal` from `App\Models\Product` ($fillable, $casts).
3. In whichever service currently computes the product listing/card payload (grep for `is_mega_deal` in ProductQueryService or equivalent — it was added around a `p.is_mega_deal` column reference), replace the flat column read with a real computed boolean: "is this product currently part of an active mega_deals page block", implemented as an efficient batched query (mirror the existing batching pattern used for promo_badges/shipping_badge in the same service — one query per page of products, not one query per product) joining PageBlock (block_type='mega_deals', is_visible=true, visible_from/visible_until covering now()) -> PageBlockProduct -> ProductVariant -> product_id.
4. In `backend/app/Http/Resources/Customer/ProductDetailResource.php` (~L69), replace the raw `is_mega_deal` model-attribute read with the same computed check for the single product being shown (reuse a shared helper/method rather than duplicating the query logic between list and detail paths — e.g. add a method on PageBuilderService or a dedicated small service).
5. Keep the JSON field name in the API response exactly `is_mega_deal: boolean` so the frontend (already wired in a prior commit to read `product.is_mega_deal`) requires no changes.
6. Update/add backend tests: a product inside an active, visible mega_deals block's product list should report `is_mega_deal: true`; a product not included, or included only in an expired/invisible/future block, should report `false`. Re-run the full existing test suite for regressions (especially any Page Builder or product resource tests).
7. Run migrations, run tests, confirm clean.
8. Commit only the files changed for this task. End the commit message with:
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>

Report: the exact query/join you used, confirmation the JSON field name is unchanged, and the commit hash.
```

### Task G — Verify frontend needs no change + smoke-check
```
In /var/www/marketplace/frontend, verify (do not assume) that the PDP "Mega deal" badge (frontend/src/features/noon/productView/base-info.tsx, currently gated on `product.is_mega_deal` — see prior commit f684856) still works correctly now that the backend computes `is_mega_deal` dynamically from Page Builder data instead of a flat column (see Task F, must run/complete first).

1. Confirm the `IProductDetails` TypeScript type (frontend/src/features/noon/productView/types/product-details.ts) still matches: `is_mega_deal?: boolean` — no shape change expected, just confirm.
2. If the backend's new computed field is ever `null`/`undefined` instead of always a boolean, make sure the frontend condition (`product.is_mega_deal && (...)`) still degrades safely (falsy = badge hidden) — no code change needed if so, but verify explicitly by reading the actual API response for a product that IS in an active mega_deals block vs one that isn't.
3. Do NOT touch the `promo_badges` rotating AnimatedBadge logic (unrelated, already correct).
4. If everything already works with zero frontend changes, report that clearly and do NOT create an empty commit. Only commit if you actually needed to change something, with a descriptive message ending:
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>

Report your findings and whether any commit was made.
```

## Execution order
1. Task F must run first (backend) and fully complete/commit.
2. Task G runs after F, to verify/confirm the frontend still behaves correctly end-to-end.

## Task E (admin/vendor panel) — resolved, no longer needed for Mega Deal
The original open question ("Task E: admin/vendor panel location unknown") is resolved for the Mega Deal case: the Page Builder admin UI already exists in this repo (`backend/resources/views/admin/page-builder/`) and already lets admins manage Mega Deal sections and their products — no new panel work required there.

The `product_promo_badges` admin/vendor management screen (from the original plan's Task E, for the *rotating badge* messages — unrelated to Mega Deal) is still an open item: no admin/vendor panel source for that was found in this repo. That remains pending user confirmation of where that panel lives, separate from this correction.
