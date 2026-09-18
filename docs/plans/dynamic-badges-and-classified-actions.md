# Plan: Replace Static Badges with Dynamic Data + Fix Missing Classified Actions

Scope note: **No design/style changes.** Every task below is pure logic/data-wiring — replace hardcoded strings/arrays with real API-backed data, and wire up actions that currently do nothing. Component markup, classNames, and visual layout must stay untouched.

## 1. Findings (analysis)

### 1.1 PDP rotating badge — `frontend/src/features/noon/productView/base-info.tsx`
- L128-134: `AnimatedBadge` is fed a **hardcoded** `badges` array (`"hello world hello world"`, `"hello world5"`, `"hello world"`, all `CarIcon`). This is the "multi text with icon changed every 2 second" badge seen next to price in the screenshot.
- L38-39: `"Mega deal"` badge renders **unconditionally** (translation-driven text, but no data condition gating it) — it should only show when the product/listing is actually part of an active deal/campaign.
- Compare with `best_seller_badge` / `shipping_badge` (L154-171, and `IProductDetails` in `frontend/src/features/noon/productView/types/product-details.ts` L9, L51, L142) — these ARE real API-backed fields and prove the intended pattern.

### 1.2 Listing card rotating badge — `frontend/src/components/shared/product-card.tsx`
- L211-217: same `AnimatedBadge` component, hardcoded `"hello world"`, `"hello world2"`, `"hello world3"`.
- L220-240: the `shipping_badge` block right below it IS real (driven by `productData.shipping_badge.*`), confirming the pattern to copy.

### 1.3 The rotating-badge mechanism itself
- `frontend/src/components/shared/animated-badge.tsx` — generic Swiper-based component (`autoplay.delay: 2000`, vertical loop). It is **fine as-is**; it just needs real `badges` props instead of hardcoded arrays. No changes needed here unless the data shape requires a new prop (e.g. optional per-badge link/href).

### 1.4 Backend schema
- `backend/database/schema/mysql-schema.sql` has generic badge columns (`badge_label_en/ar`, `badge_color_hex`, `badge_text_color_hex`, `badge_image_path` — L248-249, L5257-5261) and `express_badge_label_en/ar` (L457-458), already wired to `shipping_badge`/Noon Express.
- **No table/column exists for a "mega deal" flag or a "rotating multi-message promo badge" list.** This is genuinely missing backend data, not just a frontend wiring gap — needs a new model (e.g. `product_promo_badges`: `product_id`, `label_en/ar`, `icon`, `color`, `sort_order`, `active`) or a JSON field, plus an endpoint to serve it as part of the product/listing payload.

### 1.5 Classified & Classified Details pages — `frontend/src/features/classified/`
- `classified-view/classified-inquiry.tsx` L28-34: `handleSend` does **not call any API** — it just flips local state and resets via `setTimeout`. The seller never actually receives the inquiry. Needs a real POST to an inquiry/message endpoint.
- `classified-view/classified-header-details.tsx` L30-36: `toggleFavorite` only mutates local React state — no persistence call, so favorites don't survive reload/aren't visible elsewhere (e.g. Wishlist). Needs a real favorite/unfavorite API call + optimistic UI.
- `classified-view/classified-sidebar.tsx` L138-144: `"View All Listings"` is a dead `<a href="#seller-listings">` anchor, not routed anywhere real — needs a real route to the seller's listing page (e.g. `/seller/[id]/listings`).
- `PRESET_QUESTIONS` (L18-24) and the two static promo cards (L156-197) are legitimate static i18n/marketing content — **leave as-is**.

### 1.6 Admin / Vendor panel
- No standalone admin/vendor panel source directory exists inside this repo (`/var/www/marketplace`) — only `backend/vendor` (PHP Composer deps, unrelated) and backend test folders. The admin/vendor management UI is either a separate repository not present here, or served purely via backend Blade/API not yet located.
- **Action item:** confirm with the user/team where the admin/vendor panel actually lives before starting Task 4 below. If it's a separate repo, Task 4 becomes "add API + doc the contract" only; the panel UI work happens in that other repo.

## 2. Tasks / Sub-agent Prompts

Run each task below as an independent sub-agent (or sequentially yourself). Each prompt is self-contained.

---

### Task A — Backend: add promo-badge & mega-deal data model
```
In /var/www/marketplace/backend (Laravel), add the data model needed to drive two currently-hardcoded frontend badges:

1. A new table `product_promo_badges` (migration): id, product_id (FK), label_en, label_ar, icon_key (string, e.g. "car", "truck" — matches a small fixed icon set already used on frontend, see CarIcon usage in frontend/src/components/shared/product-card.tsx and base-info.tsx), color_hex, text_color_hex, sort_order, is_active, timestamps.
2. A boolean/flag `is_mega_deal` (or reuse existing deals/campaign relationship if one already exists — check backend/database/schema/mysql-schema.sql and backend/app/Models for an existing Deal/Campaign model before adding a new column) on the product/listing so the PDP "Mega deal" badge can be conditionally rendered.
3. Expose both in the existing product detail API response and the product listing/card API response (find the controllers/resources currently serving `shipping_badge`, `best_seller_badge` — likely in backend/app/Http/Resources or similar — and add `promo_badges: [...]` and `is_mega_deal: boolean` alongside them, following the exact same serialization pattern).
4. Do not change any existing fields or response shapes for shipping_badge/best_seller_badge — purely additive.
5. Add/update relevant backend tests for the new fields.

Do not touch frontend code. Report the exact new field names and response shape you produced so the frontend task can consume them.
```

---

### Task B — Frontend: wire PDP rotating badge + Mega deal to real data
```
In /var/www/marketplace/frontend, remove the hardcoded placeholder data feeding the rotating "AnimatedBadge" on the Product Detail Page and gate the "Mega deal" badge on real data. Do NOT change any styling, layout, or the AnimatedBadge component itself (frontend/src/components/shared/animated-badge.tsx stays untouched).

Files:
- frontend/src/features/noon/productView/base-info.tsx (L128-134 currently hardcodes badges=[{label:"hello world hello world",...}, ...]; L38-39 renders "Mega deal" unconditionally)
- frontend/src/features/noon/productView/types/product-details.ts (IProductDetails type — add fields to match backend, e.g. `promo_badges?: {label_en, label_ar, icon_key, color_hex, text_color_hex}[]` and `is_mega_deal?: boolean`)

Steps:
1. Confirm the backend response shape for `promo_badges` and `is_mega_deal` (see Task A's output, or inspect the live API response for a product detail endpoint).
2. Update IProductDetails type to include these fields.
3. Replace the hardcoded `badges` array at base-info.tsx L128-134 with a mapping over `product.promo_badges` (localized label per current locale, icon resolved via a small icon-key -> component map — check how `icon_key`/similar is resolved elsewhere, e.g. shipping_badge icon resolution, and reuse that pattern). If `promo_badges` is empty/undefined, do not render the AnimatedBadge at all (no empty-state placeholder).
4. Wrap the "Mega deal" badge JSX in a condition on `product.is_mega_deal` (or equivalent real flag) instead of always rendering.
5. Do not modify any other badges (best_seller_badge, shipping_badge) — they already work correctly.
6. Verify no TypeScript errors, run frontend build/typecheck.
```

---

### Task C — Frontend: wire Listing Card rotating badge to real data
```
In /var/www/marketplace/frontend, same objective as Task B but for the product listing card component: frontend/src/components/shared/product-card.tsx (L211-217 currently hardcodes badges=[{label:"hello world",...}, {label:"hello world2",...}, {label:"hello world3",...}]).

1. Confirm/reuse the same `promo_badges` field from the listing API response (Task A also updates the listing/card serializer — confirm field name matches product detail's).
2. Update the card's product data TypeScript type to include `promo_badges`.
3. Replace the hardcoded array with a mapping over `productData.promo_badges`, same icon-resolution and localization approach as Task B, for consistency.
4. If `promo_badges` is empty/undefined, don't render the AnimatedBadge for that card (matches the "no card should show placeholder text" requirement) — the shipping_badge block right below (L220-240) already handles its own real data and is untouched.
5. Do not change spacing/classNames/layout — only the data source.
6. Verify no TypeScript errors, run frontend build/typecheck. Spot check by rendering a product listing page to confirm cards without promo_badges data simply omit the rotating badge cleanly (no layout shift/empty pill).
```

---

### Task D — Frontend: fix non-functional Classified actions
```
In /var/www/marketplace/frontend/src/features/classified/, fix three broken/fake actions on the Classified Details page. Do not change any styling or layout — only wire real logic/API calls in place of no-ops.

1. classified-view/classified-inquiry.tsx (L28-34): `handleSend` currently does nothing but flip local state via setTimeout — no network call. Find (or, if genuinely absent, ask the backend to confirm) the existing inquiry/contact-seller endpoint (check frontend/src/services or services/get for an existing classified inquiry/message API; check backend routes for something like POST /classifieds/{id}/inquiries). Wire handleSend to actually POST the message/selected preset question to that endpoint, keep existing loading/sent UI states, add error handling (toast/inline error) on failure without changing the visual success/sent state shown on success.

2. classified-view/classified-header-details.tsx (L30-36): `toggleFavorite` only sets local React state — add a real favorite/unfavorite API call (check if a generic favorites/wishlist endpoint already exists for classifieds, since regular products have a Wishlist feature — reuse that pattern/service if it covers classifieds, otherwise flag if backend needs a new endpoint rather than inventing one). Keep optimistic UI update (toggle immediately) but roll back on API failure.

3. classified-view/classified-sidebar.tsx (L138-144): `"View All Listings"` is a dead `<a href="#seller-listings">` anchor. Replace with a real Next.js Link to the seller's listings page. If no such route/page currently exists, check frontend/app routing for anything like a seller profile/listings page; if genuinely missing, stop and report back rather than inventing a fake route — this may need a new page (out of scope for this task, flag it).

Do not touch PRESET_QUESTIONS or the two static promo cards (L156-197) in classified-sidebar.tsx — those are legitimate static content, not bugs.

Report which of the three sub-items required a new backend endpoint vs reused an existing one.
```

---

### Task E — Admin/Vendor panel (pending location confirmation)
```
Before starting: confirm where the admin/vendor panel source actually lives — it was not found inside /var/www/marketplace (only backend Laravel API + a frontend Next.js app were found; no admin/vendor UI directory exists in this repo). Ask the user for the panel's repo/path.

Once located, the panel needs a management screen for:
1. `product_promo_badges` (from Task A) — CRUD per product/listing: label (en/ar), icon, colors, sort order, active toggle. This replaces what is currently invisible/hardcoded — vendors/admins should be able to configure the rotating badge messages shown on PDP and listing cards.
2. `is_mega_deal` flag (from Task A) — expose as a toggle or tie it to an existing deals/campaign management screen if one already exists in the panel (check first before adding a duplicate toggle).

If the panel is confirmed to live outside this repo, this task is descriptive only (document the API contract from Task A for whoever owns that repo) rather than an implementation task here.
```

---

## 3. Suggested execution order
1. Task A (backend data model) — blocks B, C, E.
2. Task B and Task C in parallel (frontend PDP + card badges) once Task A's field names are confirmed.
3. Task D (classified actions) — independent, can run in parallel with A/B/C.
4. Task E — only after confirming panel location with the user.
