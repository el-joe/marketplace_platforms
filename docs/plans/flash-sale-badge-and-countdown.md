# Plan: PDP Deal Badge Must Be Mega Deal OR Flash Sale (With Countdown)

## Background
The PDP currently shows a "Mega deal" badge (`frontend/src/features/noon/productView/base-info.tsx` ~L49, gated on `product.is_mega_deal`, computed dynamically from Page Builder data — see `docs/plans/mega-deal-page-builder-correction.md`, commit `ca2ba81`). The user has confirmed noon.com shows **one of two** possible badges in that slot — "Mega deal" or "Flash sale" (عرض برق) — and Flash Sale additionally shows a **live countdown** to when the sale ends (e.g. "04h:11m"), matching a screenshot of noon.com's real behavior.

Research findings (do not re-derive, verified already):
- **Mega Deal** = pure Page Builder concept (`page_blocks.block_type='mega_deals'`), no separate model. Already correctly wired (`is_mega_deal: boolean` on product detail/list API).
- **Flash Sale** is a DIFFERENT, deeper feature — NOT just a page-builder block:
  - `App\Models\FlashSale` — campaign model: `sale_starts_at`, `sale_ends_at`, `status` (draft→...→live→ended), `country_id`.
  - `App\Models\FlashSaleSubmission` — per-listing pivot: `flash_sale_id`, `vendor_listing_id`/`admin_listing_id`, `flash_price`, `original_price`, `calculated_discount_pct`, `quantity_remaining` (generated column), own status machine ending in `live`.
  - Source of truth for "is this product in an active flash sale, and when does it end": `FlashSaleSubmission.status='live'` → parent `FlashSale.sale_ends_at`. This is per-campaign (all products in the same flash sale share one end time), reached per-product via `FlashSaleSubmission.flash_sale_id`.
  - The existing `flash_sale` Page Builder block type (homepage section, `PageBuilderService::productsFromFlashSale()` ~L759-793) is just a thin display wrapper around the real `FlashSale`/`FlashSaleSubmission` models and is itself half-wired today: it has a `show_countdown` config toggle but never actually passes an end timestamp through to the frontend (`frontend/src/components/shared/page-builder/sections/flash-sale.tsx` has no countdown UI at all). **That homepage gap is out of scope for this plan** — focus is the PDP product-level badge, but Task H's backend work should reuse/expose the same `sale_ends_at` value so a future fix to the homepage section is trivial.
  - Currently `ProductDetailResource.php` and `ProductQueryService.php` expose NOTHING flash-sale related — no `is_flash_sale`, no `flash_sale_ends_at`.
- No design/style changes — logic/data-wiring only, reusing existing badge markup/classes.
- A countdown mechanism already exists in the codebase for reuse: `frontend/src/hooks/useCountDown.ts` (used elsewhere, e.g. delivery countdown in the PDP screenshot "Order in 18h 04m"). Reuse it — do not build a new countdown hook.

## Tasks / Sub-agent Prompts

### Task H — Backend: expose per-product flash-sale status + end time
```
In /var/www/marketplace/backend (Laravel), add flash-sale awareness to the product detail and product listing/card APIs, mirroring the existing mega-deal pattern (see backend/app/Services/Shared/PageBuilderService.php's `activeMegaDealProductIds`/`isProductInActiveMegaDeal`, and their usage in backend/app/Services/Customer/ProductQueryService.php and backend/app/Http/Resources/Customer/ProductDetailResource.php — read these first to copy the exact batching/wiring pattern).

Read backend/app/Models/FlashSale.php and backend/app/Models/FlashSaleSubmission.php in full first. The source of truth is: a listing has an active flash-sale price when its FlashSaleSubmission has status = 'live' (confirm exact status enum value by reading the model/migration), and the countdown end time is that submission's parent FlashSale.sale_ends_at.

1. Add a method (e.g. on a FlashSaleService if one exists — check backend/app/Services for FlashSaleService first and extend it rather than duplicating logic in PageBuilderService, since FlashSale is unrelated to Page Builder) that, given a batch of product/listing ids, returns which ones have a live FlashSaleSubmission and each one's parent FlashSale.sale_ends_at. Mirror the exact batching approach used for `activeMegaDealProductIds` (one/two queries per page, not per row).
2. Wire this into `ProductQueryService::buildProductsPayload()` the same way `is_mega_deal` is wired (~L211 per the mega-deal commit) — add `is_flash_sale: boolean` and `flash_sale_ends_at: string|null` (ISO 8601 timestamp) to each product row.
3. Wire the equivalent single-product check into `ProductDetailResource.php`/its controllers (ProductController.php, ListingController.php, and ListingDetailController.php's productShape() — all three were touched for is_mega_deal, per commit ca2ba81 — apply the same three-place pattern here) — add `is_flash_sale` and `flash_sale_ends_at` fields.
4. A product should never be BOTH — if a listing happens to have both an active mega-deal Page Builder association AND a live flash-sale submission (edge case), pick flash sale as the higher-priority badge on the backend by NOT setting is_mega_deal true in that case (or leave both booleans accurate and let the frontend prioritize — your call, but document which approach you took and why in your report).
5. Add backend tests: product with a live FlashSaleSubmission reports is_flash_sale=true and the correct flash_sale_ends_at matching its FlashSale.sale_ends_at; product with only a draft/ended/non-live submission reports false; product with neither reports false for both flash sale and mega deal fields (regression check). Re-run full test suite, confirm no regressions (existing mega-deal/promo-badge tests must still pass).
6. Commit only files changed for this task. End commit message with:
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>

Report: exact field names/shapes added, which model/service you extended, your mega-deal-vs-flash-sale precedence decision, and the commit hash.
```

### Task I — Frontend: PDP badge shows Mega Deal OR Flash Sale with live countdown
```
In /var/www/marketplace/frontend, update the PDP deal badge (frontend/src/features/noon/productView/base-info.tsx, currently ~L49 unconditionally-gated-on-is_mega_deal single "Mega deal" Badge, wired in a prior commit f684856) to show ONE of two badges depending on the product's data (Task H, must be complete first, provides `is_flash_sale: boolean` and `flash_sale_ends_at: string | null` alongside the existing `is_mega_deal: boolean`):

1. Update `IProductDetails` type (frontend/src/features/noon/productView/types/product-details.ts) to add `is_flash_sale?: boolean` and `flash_sale_ends_at?: string | null`.
2. Precedence: if `product.is_flash_sale` is true, render a "Flash sale" badge (add an i18n key similar to how `megaDeal` was already added — check locale files under frontend/locale or frontend/i18n for the existing `megaDeal` key and add a matching `flashSale` key, en+ar) with a live countdown next to/inside it, counting down to `flash_sale_ends_at`. Else if `product.is_mega_deal` is true, render the existing "Mega deal" badge exactly as today. Else render neither — same as today's fallback.
3. For the countdown, reuse the existing `frontend/src/hooks/useCountDown.ts` hook (check its exact signature/return shape by reading it — it's already used elsewhere in this same PDP, e.g. the "Order in 18h 04m" delivery countdown) rather than writing new interval/timer logic. Format consistent with the existing on-page countdown style (e.g. "04h 11m" pattern) — do not introduce a new visual style, only reuse the existing badge/pill markup and just swap its inner content between the static "Mega deal" label and the "Flash sale" label + countdown text.
4. Do not touch `promo_badges`/AnimatedBadge logic — unrelated, leave as-is.
5. Do not touch the listing/card component in this task (frontend/src/components/shared/product-card.tsx) — that's Task J below, separate.
6. Run `npx tsc --noEmit` to confirm no type errors. If feasible, run the dev server and visually confirm both states render (flash sale with ticking countdown; mega deal without) — if you can't run a live dev server, say so explicitly rather than claiming visual verification.
7. Commit only files changed for this task. End commit message with:
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>

Report the commit hash and whether you were able to visually verify both states.
```

### Task J — Frontend: listing card deal badge shows Mega Deal OR Flash Sale (with countdown if space allows)
```
In /var/www/marketplace/frontend/src/components/shared/product-card.tsx, apply the same Mega-Deal-OR-Flash-Sale precedence as Task I (which must be complete first — mirror its exact approach for consistency) to the product listing card, IF this card currently shows any mega-deal indicator today. First check: does product-card.tsx currently render anything gated on `is_mega_deal`? (Based on prior work, product-card.tsx got `is_mega_deal`/`promo_badges` added to its type in commit 2ff43ca, but confirm whether the mega-deal boolean is actually rendered anywhere in this card's JSX, or only fetched/typed without a UI consumer — if it's not rendered, note that and design the flash-sale-or-mega-deal indicator as a small addition consistent with the card's existing badge patterns, e.g. similar to the "عرض برق" flash-sale badge with countdown shown on real noon.com listing cards.)

1. Update the card's product type to include `is_flash_sale?: boolean` and `flash_sale_ends_at?: string | null` (mirroring is_mega_deal/promo_badges already added).
2. Add/adjust a small badge element following the same precedence rule (flash sale > mega deal > neither), reusing the same i18n keys and countdown hook (`useCountDown`) added/used in Task I. Keep it visually consistent with existing card badges (same badge component/classes pattern already used for shipping_badge on this card) — no new visual design.
3. Do not change layout/spacing/existing shipping_badge or promo_badges (AnimatedBadge) rendering.
4. Run `npx tsc --noEmit` to confirm no errors.
5. Commit only files changed for this task. End commit message with:
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>

Report the commit hash and whether a mega-deal badge existed on the card before this task (i.e. whether this was a net-new addition or an extension of existing UI).
```

## Execution order
1. Task H (backend) — must complete first.
2. Task I (PDP) — after H.
3. Task J (listing card) — after I, so it can mirror I's exact implementation pattern.
