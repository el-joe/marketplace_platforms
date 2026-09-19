# Features QC Plan — docstxt/FEATURE-GUIDE (3 files)

Source docs: `docstxt/FEATURE-GUIDE.md.txt` (v1, older, includes "COD not implemented" note),
`FEATURE-GUIDE.md (1).txt` (post-fix, commit 6a1649e), `FEATURE-GUIDE.md (2).txt` (user-facing, Sep 2026).
Where they conflict, **(2) and (1) win over v1** (v1 says COD limits and Omani rial are unbuilt; later docs say done — verify, don't assume).

## Git rule (user-authorized: commit to `main`)
Working tree is shared and has unrelated WIP. Each agent commits ONLY its own files: `git add <explicit paths>` then `git commit -m "..." -- <paths>` (never `git add -A`/`.`, never stash/reset/checkout others' changes, no push, no --no-verify). If index.lock exists, wait and retry. Commit message ends with `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.

## How to use
Each `## Fxx` section is a self-contained prompt. Run them as independent sub-agents (they touch mostly disjoint files).
Every agent: (1) read the spec bullets, (2) locate the implementation, (3) write/extend a Feature test in `backend/tests/Feature/...`,
(4) run it (`cd backend && php artisan test --filter=...`), (5) FIX real bugs in app code (never weaken the test), (6) append a result block to
`docs/QC_RESULTS.md` (PASS / FIXED / GAP + file:line). Commit per the Git rule above. Do not touch unrelated dirty files (see `git status`: ad-billing work is in flight — F01 must not overwrite it).

## Shared rules for every prompt
- Stack: Laravel in `/var/www/marketplace/backend`, Next/React storefront in `/var/www/marketplace/frontend`, panels are Blade.
- Money = base-currency integers; `/100` is % math or basis points, not a bug (see memory: pricing_model). Still verify no `*100` slips.
- Look at the target before editing. Report faithfully: failing tests are reported with output, skipped items stated.
- Arabic + English lang keys must both exist for any new UI string (`backend/lang/{ar,en}`).
- Auth/permission: verify each endpoint rejects unauthenticated, wrong-role, and other-tenant access (IDOR).

## Known doc-vs-code findings to verify first (from initial route grep)
1. Doc routes `/admin/ad-packages`, `/partner/ad-subscriptions` did **not** appear in the `routes/*.php` grep — find where they really live (maybe under `subscription`/`ads` prefix) or they are missing.
2. Doc says `POST /admin/settings/currencies/OMR/symbol-image`; code has `currencies/{code}/symbol-image` in `routes/admin.php:685` (path prefix differs — confirm).
3. Doc says `GET /api/customer/v1/{region}/travel?country_id&city_id&departure_from&departure_to`; route at `api_customer_v1.php:90` comment says "unfiltered" — confirm filters actually apply.
4. Doc says COD limits are set in `admin/content-settings` keys `cod_global_max_amount`, `cod_supermall_max_amount`, `cod_supermall_category_id`; only `CodValidationService.php` matched the key — confirm keys are seeded/editable in the UI and enforced in cart AND checkout.
5. Doc says popup uses `localStorage nawi_ads_popup_seen`; found in `frontend/src/features/noon/ads/serious-featured-popup.tsx` — verify.
6. Doc says `GET /orders/{id}/invoice`; code is `{orderNumber}/invoice` (`api_customer_v1.php:562`) — verify param and PDF output.
7. Existing tests to extend, not duplicate: `Ads/NawiAdsLifecycleTest`, `Ads/AdPopupControllerTest`, `Ads/ListingBoostRankingTest`, `MarketerContractAuditTrailTest`, `SpecialRequestRoutingTest`, `WarrantyLifecycleTest`, `Checkout/*`.

---

## F01 — Nawi Ad Packages (serious / serious_featured) + popup
Spec: admin CRUD packages (tier serious|serious_featured, name en/ar, price_monthly BIGINT, currency, is_active toggle, delete). Partner subscribes
(`ad_package_id, listing_id, billing_method wallet|payout_deduction`; `popup_*` fields required ONLY for serious_featured), lists, cancels.
Customer: subscribed listings rank first in category (weighted), badge "مميّز"/"إعلان"; `GET /api/public/active-popup` returns first active
serious_featured sub with a complete popup; shown once (localStorage `nawi_ads_popup_seen`).
Check: validation matrix (popup_* required/not required), wallet insufficient balance, payout_deduction ledger entry, cancel stops boost + popup,
expired/inactive package excluded, popup incomplete excluded, ranking with/without subscription, other-vendor listing_id rejected (IDOR),
Arabic+English names. **Coordinate:** `AdBillingSeparationTest.php` and Ads services are uncommitted WIP — read them, don't overwrite; run `Feature/Ads` fully.
Deliver: tests in `tests/Feature/Ads/`, fixes.

## F02 — Influencer/Broker profile fields, ad price, self-edit
Spec: admin create/edit marketer (types influencer/broker/affiliate); fields ad_price(+currency), can_self_edit_ad_price, broker_category_id, broker_city_id,
broker_serves_all_cities. `PUT /marketer/profile/ad-price` allowed only if can_self_edit_ad_price. Public list `GET /api/public/v1/{region}/marketers`
shows ad price; profile `/marketers/{slug}` returns Section A (personal, invitation_id NULL) and Section B (campaign, invitation_id NOT NULL).
Check: self-edit blocked when flag false (403), negative/non-integer price, currency validation, section split correctness, unapproved/inactive marketer hidden,
slug 404, sensitive fields (sizes, email, commission discount) NOT leaked in public API.

## F03 — Sample sizes + samples lifecycle
Spec: marketer_profiles size fields (clothing/shirt/pants/dress/abaya/shoe + shoe_size_system EU/US/UK, chest/waist/hip/height/item_length/sleeve_* cm, notes).
Admin campaign page shows sizes next to sample; `PATCH /admin/marketer-campaigns/{c}/samples/{s}` status pending→dispatched→delivered→returned,
quantity, sample_owner platform|marketer. Marketer `POST /marketer/samples/{sample}/address`; can confirm receipt.
Check: invalid status transitions rejected, sample belongs to campaign (route-binding scope), other marketer cannot submit address for someone else's sample,
sizes rendered in admin AND partner view (doc v1 table flags partner view ❌ — later docs claim ✅: verify and implement if missing), enum validation, cm bounds.

## F04 — Contracts & acceptance audit trail
Spec: admin uploads PDF or HTML text (title en/ar) → new `version_number`, old versions immutable/never deleted; `is_required` toggle;
customer `GET/POST /api/customer/v1/{region}/marketers/{id}/contract[/accept]` with `version_id`; stores customer_id, ip, user_agent, accepted_at,
version_id, order_id; checkout blocked until accepted when cart has marketer product with required contract; admin acceptances list + version download.
Check: extend `MarketerContractAuditTrailTest`; accepting an old (superseded) version_id rejected or recorded correctly; order stays linked to the version accepted at purchase;
checkout returns clear error without acceptance; HTML sanitisation (XSS in text contract); PDF mime/size validation; download authz; acceptances not editable/deletable.

## F05 — Special requests + broker smart routing
Spec: customer `POST .../special-requests` {category_id, city_id nullable=all, title_en, description_en, budget BIGINT, budget_currency}; every broker with matching
broker_category_id AND (broker_city_id == city OR serves_all_cities) is notified; marketer `GET /marketer/special-requests[/{id}]` only matching ones; statuses open→in_progress→closed.
Check: extend `SpecialRequestRoutingTest` — city null, non-matching category/city not notified, influencers (non-brokers) not notified, inactive broker,
guest cannot create, validation, marketer cannot open non-matching request (IDOR), status transition rules, API + Blade panel both work, storefront "send special request" button on browse pages exists (doc (2) §5).

## F06 — Travel search filters
Spec: `GET /api/customer/v1/{region}/travel?country_id&city_id&departure_from&departure_to&page`, sorted departure asc; UI: country → dependent city list, date range, clear filters, auto-refresh (`/uae-ar/flights`, doc v1 also says `/browse/travel`).
Check: each filter alone + combined, invalid uuid/date/from>to → 422, only active/approved/non-expired packages, pagination, sort, city belongs to country;
frontend filters exist and call the API (doc v1 flagged ❌ Frontend). Fix backend controller comment/behavior mismatch at `api_customer_v1.php:89-90`.

## F07 — Travel bookings fixes
Spec: multi-seat booking field 1..10 (capped by availability) with live total; admin inquiry → "convert to confirmed booking" sends customer notification;
admin search bar matches sidebar search; profile my-bookings; passport upload; cancel.
Check: seats > available rejected, total = seats × price (+ currency), race on last seats (concurrency), convert-once idempotency, notification queued, other customer cannot view booking.

## F08 — Custom attributes (tailored products)
Spec: admin product toggle `has_custom_attributes`; partner CRUD `/partner-api/products/{id}/custom-attributes` (label, unit, is_required, types: text/number/select/checkbox) + toggle;
customer modal on add-to-cart incl. mobile `FloatingCartButton` (must send `custom_attribute_values`); required enforced (`custom_attribute_required`); values persist
cart_item_custom_attribute_values → order_item_custom_attribute_values; shown in admin/partner/customer order pages.
Check: required missing → 422, number/select validation, option not in list, attribute of another product rejected, partner cannot edit another vendor's product,
toggle off hides fields, values immutable after order, rendering in all 3 order views, checkout with same product twice with different values stays separate lines.
NOTE: doc lists 4 field types — confirm the model/DB actually supports select + checkbox, not only text/number.

**Client addendum (F08):** (a) size-guide image (دليل المقاسات) — admin/partner can attach a size-guide image to a product/category and the storefront shows it near the custom fields; (b) seller can add options such as open/closed abaya (select/checkbox) AND the customer gets a free-text notes box to the seller; (c) fixed body-measurement fields (length, width, chest, sleeve, sleeve-from-neck) available as presets. Implement if missing.

## F09 — Commission discounts (vendor + marketer)
Spec: type none|flat|percentage; flat BIGINT; percentage 0–100 (doc(2) example "10% off commission"); notes. Applied in `CheckoutCalculationService::calculateCommission()` (verify the class name — may now be `CheckoutPricingEngine`).
Check: unit tests for none/flat/percentage, flat > commission clamps at 0 (never negative), percentage >100 rejected, marketer vs vendor stacking, rounding, admin form validation,
payout ledger reflects discounted commission (run `PayoutLedgerReconciliationTest`, `Checkout/CheckoutMoneySplitTest`). **Doc inconsistency:** v1 says flat "500 = 500 per sale", doc(2) says "50 = 50 riyals" — confirm unit is base-currency integer, not minor units ×100.

## F10 — Coupon shipping-type restriction
Spec: `shipping_type_restriction` all|fbn|fbp|fbm on admin (and vendor) coupon create/update; cart with non-matching items → error "هذه القسيمة غير صالحة لنوع الشحن المحدد"; mixed cart behavior defined.
Check: matrix coupon×item type, mixed cart (discount only eligible lines? or reject? — document actual behavior, ensure discount base excludes ineligible lines), invalid enum 422,
vendor coupon requests (`Vendor/Store|UpdateCouponRequest`) also accept/validate the field, both AR/EN error strings, checkout `prepare()` recheck, concurrency test `CouponUsageConcurrencyTest` still green.

## F11 — COD limits
Spec: settings `cod_global_max_amount` (0 = unlimited), `cod_supermall_max_amount`, `cod_supermall_category_id` in admin content-settings; exempt: Nawi admin listings; forbidden: international shipments.
Example: 50000 ⇒ 500. Enforced in cart availability of payment methods AND at checkout submission (server-side, not only UI hiding).
Check: boundary (=limit ok, +1 blocked), mixed cart (Nawi + partner items: is the limit on partner subtotal or whole cart? document + assert), supermall category, setting=0, missing setting,
international ⇒ COD blocked, direct POST with `payment_method=cod` over limit rejected, settings appear in admin UI with validation, `Checkout/PaymentMethodMatrixTest` extended.

## F12 — International shipping (7 phases)
Spec: supported country pairs, rates + customs duty % per route, separate `customs_duty` line in customer invoice, "ships internationally" badge, COD blocked,
tracking stages: origin dispatch → customs clearance → delivery; admin pages `InternationalShippingRate`, `Eligibility`, `admin listings eligibility` seeding command.
Check: unsupported pair rejected (`InternationalShippingIneligibleException`), missing rate (`...RateNotFoundException`), currency conversion (`CurrencyExchangeRateNotFoundException`),
totals reconcile (`CheckoutPricingReconciliationTest`), mixed local+intl cart splits, tracking event order + customer visibility, duty rounding, admin CRUD authz.
Doc(2) says setup at `/countries` + `/vendor-listings`; doc(1) says Delivery & Logistics → International Shipping — confirm actual menu/route and that both docs' entry points exist or fix nav.

## F13 — Checkout fixes
(a) Coupon carried from cart: `prepare()` reads `cart.coupon.code` when `coupon_code` omitted (commit 18cacd3) — test null vs present, expired coupon on cart.
(b) Wallet: insufficient balance → clear error BEFORE order creation (no cancelled orphan order); sufficient → deducted; partial wallet + other method; double-submit idempotency.
(c) Payment summary shows subtotal, discount, shipping ("مجاني" when 0), COD fee only when COD, warranty, tax, wallet deduction, total — verify frontend against API totals for each combination.
Run all `Feature/Checkout/*`. Check rounding parity between preview (`prepare`) and final order.

## F14 — Extended warranty + invoice PDF + bank-transfer instructions
Warranty: add at purchase; claim only after order status delivered (button on my-warranties and order detail); claim creation validation; extend `WarrantyLifecycleTest`, `CartWarrantyTest`.
Invoice: `GET /orders/{orderNumber}/invoice` returns PDF (content-type, filename), only owner can download, includes duty/discount/tax lines, Arabic glyphs render (RTL/font).
Bank transfer: instructions (bank, IBAN, beneficiary, amount, reference=order number) on success page AND `profile/orders/{id}`; values come from settings; hidden for other payment methods; other customer 403/404.

## F15 — Omani rial (OMR) + currencies
Spec: OMR in currencies table, symbol "ر.ع", admin `POST .../currencies/OMR/symbol-image` (image validation), `PATCH .../OMR/rate {rate}` (doc example 1 OMR = 3.85 AED — sanity-check: real ≈ 9.5 AED; treat as sample, don't hardcode),
customer `GET .../currencies` returns symbol/image. OMR uses **3 decimal places** (baisa = 1/1000) — verify the money formatter and integer storage don't assume 2 decimals (`/100`) for OMR; test 1.500 OMR display/checkout total.
Check: rate validation (>0), symbol image mime/size, delete image, cache invalidation of currency list, storefront region for Oman (`om-ar`?) formats price correctly, ad_price_currency "OMR" accepted in F02.

## F17 — Client notes: directories (stores / influencers / brokers) with bio + specialty
Client asked for public listing pages for stores, influencers and brokers, each with a written bio (نبذة تعريفية, EN/AR) and specialty/interests (تخصص).
Also: per-marketer/per-broker/per-category commission set by admin in each profile; contract shown per chosen marketer; broker picks category then city or all cities.
Check: bio + specialty fields exist (DB, admin form, marketer self-edit if allowed, public API, storefront card + profile); stores directory + store page show bio;
category-specific commission per marketer works and is editable in admin profile; XSS-safe rendering; AR/EN. Implement whatever is missing.

**Client addendum (F15, important):** a country's currency symbol must be either plain text OR an uploaded image, and the image upload MUST accept SVG (plus png/jpg/webp). Verify validation rules allow `svg` (mimes + mimetypes `image/svg+xml`), SVG is sanitised (strip `<script>`, `on*=` handlers, external refs) or served safely, storage/URL resolves, the storefront/admin/partner price formatter renders image-symbol when present else text, and delete/replace works. Test with an SVG fixture and a malicious SVG.

## F16 — Cross-cutting sweep (run LAST, after F01–F15)
1. `cd backend && php artisan test` — report full pass/fail counts; triage failures as (new-from-QC / pre-existing / WIP-ad-billing).
2. `php artisan route:list` diff against every URL in the 3 docs — produce a table doc-URL → route/exists/missing; fix missing routes or correct docs.
3. Lang parity: every key in `lang/en/*.php` exists in `lang/ar/*.php` for files touched by F01–F15.
4. Permission sweep: each admin route above has `admin.permission:*` middleware; partner/marketer routes scoped to owner.
5. Frontend: `cd frontend && npm run lint && npm run build` (or `tsc --noEmit`) — no new errors; Playwright smoke (`backend/playwright.config.ts`) for popup, checkout contract modal, custom-attribute modal, travel filters, special-request form.
6. Write final `docs/QC_RESULTS.md` summary table: Feature | Status (PASS/FIXED/GAP) | Tests added | Fixes | Remaining risks.

---

## Suggested execution order / parallelism
- Wave 1 (parallel, independent): F02, F03, F05, F06, F07, F08, F15
- Wave 2 (parallel, touches checkout/pricing — run F09/F10/F11 serially or in separate worktrees): F09, F10, F11, F12
- Wave 3: F13, F14, F01 (after WIP ad-billing is committed or agreed), F04
- Wave 4: F16
Tip: use `isolation: "worktree"` for Wave 2 agents to avoid conflicting edits in `CheckoutPricingEngine`/`CartService`.
