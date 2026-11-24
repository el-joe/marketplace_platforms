# Client Retest Fix Plan — 2026-11-24

**Source:** WhatsApp "mahmoud ismael", 2026-11-23 17:13 → 2026-11-24 13:19. QA retested the 40 client notes on the LIVE site (nawyweb.com) after deploy of commit `30287054`, plus one new finding.

**Environment rules:** same as `client-feedback-2026-11-22-plan.md`:
- Local dev only; never commit, push or deploy. Only NEW migrations.
- AR + EN strings.
- Tests: `cd backend && php artisan test --parallel` (MySQL); baseline 1803 pass / 0 fail.
- Frontend: tsc, eslint, `next build` (with local `NEXT_PUBLIC_*` overrides; never call api.nawyweb.com).

## Retest results
**Confirmed fixed on live (no action):**
- #1, #2 (link), #3, #4, #5, #8, #9, #13, #15 (explained), #16 / #16b (403s), #17 (not reproduced), #18, #20, #22, #23, #29, #30, #31, #32, #35, #36, #38, #39 (status), #40 (credit/debit), #6 (explained)
- #14 gender filter; #26 sidebar search

**Partial: QA can't test on live without touching real data.** These are covered by our feature tests:
- #10 / #11 rule save + effective-date fee calc
- #25 / #24 commission calc
- #27 actual upload/save

## Action items
| ID | Item | Type | Owner |
|---|---|---|---|
| R1 | #40 Wallet balance > order total: checkout shows wallet −15.75 / remaining 0, but place-order rejects "wallet amount cannot exceed order total" (balance 20 and 25.75 vs total 15.75 incl. VAT). Works when balance == total exactly. | **Confirmed bug** | Backend + Frontend |
| R2 | #40 After a wallet refund, the order's payment box still shows the original sale "succeeded" with no refund line | Display gap | Backend + Frontend |
| R3 | #37/#39 After full cancel of a COD order, the COD collection box (admin order page and partner order page alert) still says "pending / awaiting collection from rider" | **Confirmed bug** | Backend |
| R4 | NEW: cancellation email "View details" button links to `https://api.nawyweb.com/api/customer/v1/uae/orders/{number}`. Opened in a browser while logged out, it returns HTTP 500 `Route [login] not defined` **with stack trace and code paths shown** (prod debug on). JSON request → 401. | **Security + bug** | Backend |
| R5 | #12 `/packaging/types` returns 500: `data-type='@json($type->only([...]))'` (Blade bracket parse) | **Confirmed bug** | Backend |
| R6 | #34 portal `/fulfillment` FBN and FBP "مثالي لـ:" should read "مثالي لك:" (default + live portal_content rows if untouched) | Content | Frontend |
| R7 | #14 storefront register (uae-en): Continue button stays disabled until the **marketing consent** checkbox is ticked, with no reason shown (confirmed 13:25). Marketing consent must be optional; only required fields (terms) may gate Continue, and the reason must be shown. Gender saving confirmed OK. | **Confirmed bug** | Frontend + UI/UX |
| R8 | #21 live empty cart still shows static `empty_cart_ar.avif` with no link (expected: Cart Page Banner or fallback linked to `/`) | Verify / fix | Frontend |
| R9 | #7 Featured has no visible effect: the home page has no "category pills" block | Discoverability | Frontend + UI/UX |
| R10 | #19 QA looked in `/delivery/zones` and found no distance settings (they live on `/shipping-zones` → Distance pricing tab) | Discoverability | UI/UX + Backend |
| R11 | #24 QA looked in `/subscriptions/plans` and found no product-link commission (it is at Vendors → Product link / QR commission) | Discoverability | UI/UX + Backend |
| R12 | (13:26) Same API link confirmed in the refund email + all 3 cancellation emails. Other order emails: check every customer, vendor and admin mail's buttons for API/wrong-host links | Audit | Backend |
| R13 | Prod config: live shows stack traces → `APP_DEBUG=true` on production | **Ops (user)** | PM → user |

## Workstreams (sequential)
- **RA: Orders, payments & email.** Backend + Problem Solver: R1, R2, R3, R4, R12.
- **RB: Admin, portal & storefront UX.** Frontend + UI/UX + Graphic: R5, R6, R7, R8, R9, R10, R11.
- **RC: QA + QC.** Full parallel suite, builds, local browser smoke of every R item, diff review.

## Progress log
- 13:25: #25 new classified branch auto-propagates (categories, commission page, contract template choices) ✅; #5 contract template on classified branch saves ✅.
- **RA ✅** R1 storefront sent the whole wallet balance as `wallet_amount_to_use`; backend rejected anything > total → server clamps to min(requested, payable total, balance) at both place-order points (`CheckoutWalletService::resolveAmountToUse`), 4-dp debits, request accepts 0–4 decimals; storefront sends the displayed deduction (one helper, no partial wallet on COD). R2 wallet tender of a cancel now = completed `refunds` row + `refund` payment tx (gateway wallet) + payment_status refunded/partially_refunded + refund email; customer order API `refunds[]` + storefront payment card. R3 `SubOrder::codCollectionStatus()` (pending/remitted/cancelled) drives admin COD box, payment badge, partner alert, vendor API. R4 `StorefrontUrl` helper for all customer notification links; guests on api/* always JSON 401 (bootstrap/app.php + panel auth middlewares on api paths). R12 audit fixes (assignment mail url('/'), relative warranty URLs, 2 missing routes, mail header logo → storefront, noon.com mailto). +6 test files; suite 1827 pass / 0 fail / 3 skipped; tsc, eslint, next build OK.
- **RB ✅** R5 root cause: Blade `@json` splits its argument on every comma (`json_encode($parts[0], $options, $depth)`), so `$type->only([...6 keys])` compiled to invalid PHP → 500 on any page with ≥1 type row. Fixed types page (`{{ json_encode(...) }}` in the attribute) + swept all 71 comma-containing `@json(...)` in 38 views (2 data-attributes → `{{ json_encode() }}`, rest → `{{ Js::from() }}`; one-comma cases had silently lost the JSON_HEX_* escaping, vendor-acquisition `data-config='…'` could break on an apostrophe). R6 «مثالي لك:» in view defaults + seeder + migration `2026_11_24_120000` (only rows still holding «مثالي لـ:»). R7 marketing consent optional (schema), Continue always enabled (except while sending), per-field localized errors + summary on submit; backend RegisterRequest never required consent/terms (consent isn't stored at all). R8 code + LIVE bundle both already link the fallback art to "/" (verified in live chunk); live Oman has no Cart Page Banner → fallback shows. Added an explicit "Your cart is empty / Start shopping" CTA. R9 category form Featured hint names the exact place + Page Builder link; palette sub-hint on Category Pills; block description migration `2026_11_24_120100` (old default only) + seeder; categories list warning listing published home pages without an auto (no hand-picked) visible pills block (read-only). R10 info card on Delivery zones → `/shipping-zones?tab=distance` (opens Distance tab + hint); sidebar "Distance-based pricing" under Platform Settings (settings.view). R11 sidebar "Product link / QR commission" in Finance & Payouts (product_link_commission.view) + pointer on Subscription plans. Extra: storefront disputes list linked `/disputes/{id}` but API is by dispute_number → fixed. New `<x-info-callout>`. +3 test files, +1 test; suite 1838 pass / 0 fail / 3 skipped; tsc, eslint, next build, backend build OK. Migrations not run on local DB.
- **RC ✅ (QA + QC)** Local migrate ran `2026_11_24_120000` + `2026_11_24_120100` (no pending). QC of RA+RB diff: wallet clamp min(requested,total,balance) at both place-order points + locked re-check in `applyWalletToOrder`, 4-dp `Money::number`; wallet refund = refunds row + `refund` tx + payment_status from pre-refund status (card refund still gated on captured); cancel idempotent under order lock; guest api/* → JSON 401, panel guests still redirect to their own login (new regression test for all 6 panels); 71 `Js::from`/`json_encode` conversions escape correctly (`{{ }}` on Htmlable `Js` = no double escape; attribute `json_encode` HTML-escaped by `{{ }}`); storefront email links match Next routes (`/{site}-{lang}/orders/{n}[/summary]`, returns/disputes by number). Fixes: sidebar now highlights only "Distance-based pricing" on `/shipping-zones?tab=distance` and only "Shipping zones" otherwise (`NavigationService::isItemActive`, +2 tests); 2 pre-existing raw `{!! json_encode !!}` in `<script>` (partner ad-slots show, marketer promote show) → `Js::from`. Suite 1846 pass / 0 fail / 3 skipped; backend build, tsc, eslint, next build (local overrides) OK. Local smoke OK (admin packaging types, cancelled COD order, zones card → distance tab, sidebar entries, categories Featured warning, portal «مثالي لك:», guest API JSON 401). Storefront browser smoke skipped: local backend CORS only allows *.noon.codefanz.com origins.

### Deploy checklist (this batch: RA + RB + RC)
1. Backup DB. `git pull` the batch; `composer install --no-dev -o`.
2. Migrations (both data-only, idempotent, only touch untouched defaults): `2026_11_24_120000_fix_fulfillment_ideal_label_arabic_copy`, `2026_11_24_120100_update_category_pills_block_description_for_featured` → `php artisan migrate --force` (plus any earlier pending ones from the 2026-11-22 batch, see that plan's follow-ups).
3. Backend assets: `npm ci && npm run build` (admin/partner/portal Blade JS).
4. Caches: `php artisan optimize:clear` then `php artisan config:cache route:cache view:cache` (views changed in 40 files; `view:clear` mandatory). Restart queue workers (`php artisan queue:restart`) so mails use the new `StorefrontUrl` links.
5. Prod `.env`: **`APP_DEBUG=false`** (R13), `APP_ENV=production`, `APP_URL=https://…` (admin host, https), **`FRONTEND_URL=https://nawyweb.com`** (no trailing slash; every customer email button is built from it), `APP_DOMAIN` = the panels' base domain. Re-run `config:cache` after editing.
6. Storefront: rebuild `next build` with the **production** `NEXT_PUBLIC_*` (local `.next` was built with local overrides — do not ship it); restart the Node process.
7. Post-deploy smoke: logged-out browser on `https://api.nawyweb.com/api/customer/v1/uae/orders/X` → JSON 401, no stack trace; `/packaging/types` 200; a test cancellation mail button opens `https://nawyweb.com/{site}-{lang}/orders/{n}`.
