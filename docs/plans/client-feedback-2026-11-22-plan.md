# Client Feedback Fix Plan — 2026-11-22/23

**Sources**
- Mostaql deal #9921662, client message 2026-11-22 21:23 (original note list + 2 screenshots: cart banner `omn-ar/cart`, portal `/fulfillment` broken Arabic heading).
- WhatsApp "mahmoud ismael", 2026-11-22 21:44 → 2026-11-23 00:57. QA triage of every client note, numbered ١–٤٠ (items 7, 28, 34 were never sent as separate messages; 7 = active/visible/featured, covered by later updates).

**Environment rules for every agent**
- Local dev box (`*.noon.loc`, DB `marketplace_platform_live` = local copy). Never deploy, never push, never commit.
- Only NEW migrations (never edit/delete old ones). `php artisan migrate` OK; `migrate:fresh/reset`, `db:wipe` forbidden.
- Do not change finance/historical data automatically (fees, invoices, payouts).
- Tests: MySQL, **always parallel**: `php artisan test --parallel` (paratest; per-process DBs `marketplace_test_test_N`). New tests must be parallel-safe (no fixed IDs, no shared files, no reliance on run order).
- Admin is Blade + Tailwind + jQuery (`backend/resources/views/admin`), storefront is Next.js (`frontend/src/features/noon`), portal is Blade (`resources/views/portal`).
- Every user-facing string in AR + EN (`backend/lang/{ar,en}`, `frontend/locale`).
- Pricing: orders/marketer/wallet/payout chain is DECIMAL(19,4); other domains BIGINT minor units — check before touching money.

## Team
| Role | Owns |
|---|---|
| PM / BA (me) | Triage, plan, sequencing, client-decision log, final report |
| Senior Backend | Laravel services, controllers, migrations, jobs, mail |
| Senior Frontend | Next.js storefront, admin Blade/JS, portal Blade |
| Senior UI/UX | Category single-save form, country picker on mobile, admin sidebar search, page-builder media UX, portal classifieds cards |
| Senior Graphic | Portal Arabic animated heading, cart empty-state banner as editable asset |
| Problem Solver + DB | Overlap math, timezone drift, order-cancel cascade, schema for new features, indexes |
| Senior QA | Parallel MySQL test suite, regression tests per workstream, browser smoke on local |
| Senior QC | Code review of the whole diff against this plan, i18n completeness, security |

## Triage (client item → status → workstream)
| # | Issue | Type | WS |
|---|---|---|---|
| 1 | Mobile "Deliver to" sheet has no country picker; Add address → login | Confirmed bug | WS7 |
| 2 | No "Forgot password" on customer site + admin login | Confirmed bug | WS7 |
| 3 | Admin product list shows EN name in AR panel | Confirmed bug | WS4 |
| 4 | Category select in product create EN only | Confirmed bug | WS4 |
| 5 | Category: fill everything (incl. shipping) and save once | UX improvement | WS6 |
| 6 | Admin listings vs custom pages vs products — what's the difference? | Question | WS15 |
| 7 | Active vs Visible vs Featured; inactive category page still opens | Partly confirmed | WS6 |
| 8 | Category availability per country from the category form (all / some / all-except) | Feature (reuse country exceptions) | WS6 |
| 9 | FBN storage fee: no undo for Invoiced/Paid, not in activity log, EN buttons | Confirmed | WS5 |
| 10 | FBN free-period rule overlap rejects 0–999 when 1000–∞ exists; EN error | Confirmed bug | WS5 |
| 11 | Free-period rule effective date (from creation vs from date X) | Feature | WS5 |
| 12 | Packaging catalog: admin-defined custom types | Feature | WS10 |
| 13 | Packaging requests date filter | Not reproduced (incl. same-day) | WS15 (answer) + QA regression test |
| 14 | Customer gender (register + admin filter); metrics by date range | Feature | WS11 |
| 15 | Acquisition commissions: what/who/how created; page empty, no create | Question + gap | WS12 |
| 16 | Roles: FAQ permission exists but EN-labelled; not all sections mapped | Confirmed i18n + audit | WS4 |
| 16b | (WhatsApp 2026-11-23 01:39) FAQ-only admin role (`qa_faqs_only_test`) can open `/subscriptions/plans` and sees create/edit/deactivate/delete — route not permission-guarded. Other tested sections (orders, customers, admins) correctly 403 | **Security, confirmed** | WS4 (priority) |
| 17 | Admin last-login time inaccurate | Not reproduced | WS15 (ask for example) |
| 18 | Banner device targeting ignored; mobile banner needs desktop image | Confirmed bug | WS3 |
| 19 | Free-delivery radius + distance price tiers (supermarket/food) | Feature (needs decisions) | WS13 |
| 20 | Portal "السوق المفتوح" → "الإعلانات المبوبة" | Content | WS8 |
| 21 | Cart empty-state banner: where to edit; "نونها الحين" goes nowhere | Confirmed (CTA dead) | WS3 |
| 22 | Portal /fulfillment Arabic heading letters split by wavy animation | Confirmed bug | WS8 |
| 23 | Hide countries: storefront OK; portal register + partner listing create still show drafts (JO, QA) | Confirmed gap | WS9 |
| 24 | Reduced/zero platform commission for orders via product barcode/link | Feature | WS12 |
| 25 | Classifieds branches (real estate/cars/travel) each with page, signup, contract, policy, commission; auto-propagate | Feature (partly exists) | WS8 + WS12 |
| 26 | Untranslated admin strings; sidebar search; search/filter on lists | Confirmed i18n + feature | WS4 |
| 27 | Adding images/banners in pages is painful (make like portal content) | UX improvement | WS14 |
| 29 | Packaging catalog list shows Free / ∞ despite per-country price/stock | Confirmed display bug | WS10 |
| 30 | Cart banner uses EN image in AR UI | Confirmed bug | WS3 |
| 31 | Banner schedule shifts −4h on every save | Confirmed bug | WS3 |
| 32 | Countries list shows "Enabled" for a disabled country; no way back to Draft | Confirmed UX gap | WS9 |
| 33 | Vendor register with duplicate phone → raw SQL error leaking password hash | **Security, confirmed** | WS1 |
| 35 | Cart shows countdown with "(dummy data)" | Confirmed bug | WS7 |
| 36 | Unpaid COD cancel sends "refund processed" email | Confirmed bug | WS2 |
| 37 | Cancelling all items / main order doesn't cascade to sub-order, COD tx, prep timer | Confirmed bug | WS2 |
| 38 | Checkout "Get it by no estimated delivery date" | Confirmed bug | WS7 |
| 39 | Vendor cancel returns success with no effect | Confirmed bug | WS2 |
| 40 | Admin manual wallet credit → 500 (`source_type` missing), silent UI, error leak | Confirmed bug | WS1 |
| — | Client says the "explanation link" sent earlier was an HR-system file | Admin/comms | WS15 |
| — | User: all tests must run in parallel on MySQL | Infra | WS0 |

## Workstreams (run sequentially, one agent each)

### WS0 — Parallel MySQL test infrastructure (QA + Backend)
- Add `brianium/paratest` (dev) at the version matching phpunit 12 / Laravel 13 (user approved).
- Ensure `php artisan test --parallel` creates/migrates per-process DBs on MySQL (root/root) and passes as well as the serial baseline. Fix parallel-unsafe tests (shared files, fixed IDs, static state) without weakening assertions.
- Add `composer test` → `php artisan test --parallel` if a script exists/fits. Record baseline: pass/fail counts, duration. Pre-existing failures listed, not hidden.

### WS1 — Security & integrity (Backend + Problem Solver)
- #33 Vendor registration: validate phone (and email) uniqueness at the step where it's entered (live check endpoint/step validation) and again on submit; catch unique-constraint `QueryException` → friendly localized error; never render exception/SQL/form values.
- #40 Admin manual wallet adjustment: set `source_type`/`source_id` (follow existing wallet transaction conventions), wrap in DB transaction (no partial write), show clear UI error; JSON errors without internals.
- Global: audit admin/portal/partner AJAX error handling in `bootstrap/app.php` exception rendering so non-debug and JSON responses never expose SQL. Note in report: production must run `APP_DEBUG=false`.
- Tests for both.

### WS2 — Order cancellation consistency (Backend + Problem Solver/DB)
- #37 When all quantities of a sub-order are cancelled (admin "cancel selected items"), cancel the sub-order; when all sub-orders cancelled, cancel the master order; cancel pending COD transaction, stop prep SLA timer/tasks, delivery assignment; never double-restock or double-refund (idempotent).
- Admin changing master order status → cancelled cascades to sub-orders + COD.
- #39 Vendor cancel on an already-cancelled/inconsistent order: either perform or reject with clear message — never success without effect.
- #36 Refund email only when a captured amount exists and a refund record is created; unpaid COD → "order/item cancelled" notification without money promise.
- Tests covering COD unpaid, prepaid, partial cancel, full cancel, repeat cancel.

### WS3 — Banners (Backend + Frontend + Problem Solver)
- #18 Enforce `target_device` server-side/client-side at render (mobile-only hidden on desktop and vice-versa); a mobile banner works with only mobile images (desktop image not required) — validation and rendering.
- #30 Pick image by locale × device with explicit fallback order (same-locale other device? → define: locale first, then other locale same device).
- #31 Schedule timezone: form shows/inputs country/admin TZ, stored UTC, re-save without edits keeps identical values (test multiple save cycles).
- #21 Cart empty-state banner: find the source (likely hard-coded in frontend); make it come from Cart Page Banner placement with fallback, and make image + CTA clickable to admin-defined link. Report to PM where the client edits it.

### WS4 — Admin localization & navigation (Frontend + Backend + UI/UX)
- #3 Product name/listing names by panel locale with fallback (lists, selects, search).
- #4 Category select/search (product create/edit and everywhere categories are picked) by locale with fallback; search matches both languages.
- #16 Roles: translate permission group + action labels (AR/EN); add search box on role form; audit that every admin route/section has a permission (FBN fees, contracts, acquisition commissions, FAQs…) — list gaps and add missing ones via seeder/migration (additive).
- #16b PRIORITY: guard `/subscriptions/plans` (all actions) with permissions; then sweep EVERY admin route (`php artisan route:list` for admin domain) for missing permission middleware/policy checks on both pages and mutating actions; add a test that iterates admin routes and asserts each has a permission gate (allow-list exceptions like dashboard/profile/logout). Include order actions that only require `orders.view` (force cancel, cancel items, status change, refund) — WS2 finding.
- #26 Translate remaining strings: breadcrumbs, Mark Invoiced/Mark Paid, rich-text editor toolbar, page-builder block names, FBN overlap error. Add admin sidebar search (client-side filter of menu items, AR+EN). Audit list screens lacking search/filter; add to the main ones missing them; list what was done.

### WS5 — FBN storage fees (Backend + Problem Solver)
- #10 Fix overlap check (`FbnController` free-period rules): half-open/closed range math incl. open-ended max; adjacent ranges allowed; localized error naming the conflicting rule.
- #9 Revert actions: Invoiced→Pending, Paid→Invoiced, permission-gated, confirmation modal, reason field; activity-log entries for invoice/pay/revert with actor, time, before/after. Translate buttons.
- #11 Rule effective date: "apply from creation" (default) or "apply to storage from date X". Existing invoiced/paid fees never auto-recalculated; pending fees recalculated only after explicit confirmation showing impact preview.

### WS6 — Categories (Backend + UI/UX + Frontend)
- #5 Create form contains all sections (incl. shipping methods, attributes, commission, countries) and saves once; edit page offers single "Save all" (keep section saves only if needed).
- #8 Category country availability: mode = all / only selected / all except selected — stored once, synced with the existing per-country category exceptions (single source of truth; no duplicate system). Define precedence and document in UI hint.
- #7 Semantics: Active = exists/usable (inactive → category page 404, products hidden from category browsing); Visible = shown in menus/navigation; Featured = homepage featured categories section. Verify each flag is honored by storefront API; add helper text in form.

### WS7 — Storefront fixes (Frontend + UI/UX)
- #1 Mobile Deliver-to sheet: country selector identical to desktop, usable logged-out; "Add address" for guests prompts login only for address, not for country.
- #2 Forgot/reset password for customers (storefront modal + API: check existing customer API before adding) and admin login (`/forgot-password`, reuse vendor/marketer broker pattern). Verify mail flow locally (log mailer).
- #35 Remove "(dummy data)"; drive countdown from real data or hide.
- #38 Checkout delivery estimate: show real date when computed; otherwise hide line (no "Get it by no estimated delivery date").

### WS8 — Vendor portal content (Frontend + Graphic + UI/UX)
- #22 Arabic animated headings: animate per word/phrase for RTL (never split Arabic letters); keep EN letter wave. Check all `animate-wavy` uses.
- #20/#25 Join page: card renamed "الإعلانات المبوبة" / "Classifieds"; selecting it shows sub-options generated from active classified categories (real estate, cars, travel & tourism…), each leading to its page/signup with its contract template; new categories appear automatically. Reuse existing classified categories + contract templates. Travel shown under classifieds per client.

### WS9 — Country visibility (Backend + Frontend)
- #23 Draft/inactive countries hidden from portal registration country list and partner listing create (and validated server-side), unless admin explicitly allows "accept vendor registrations before launch" (add flag if missing; default hidden). Client decision logged.
- #32 Countries list: show real visibility status (Active/Hidden/Draft) distinct from setup; allow reverting to Draft.

### WS10 — Packaging (Backend + Frontend)
- #12 Admin-managed packaging types (CRUD, AR/EN names), existing enum values migrated as seeded types; items keep their type.
- #29 Catalog list shows per-country price/stock (or "varies by country" with tooltip) instead of Free/∞; rename default columns if they are defaults.

### WS11 — Customers (Backend + Frontend)
- #14 Gender (optional: male/female) on storefront registration + profile + admin customer form; admin filter. Metrics (orders, spend, points) computed for chosen date range with clear label ("activity between"), separate from "joined between".

### WS12 — Commissions (Backend + Problem Solver)
- #15 Document acquisition commissions from code (trigger, beneficiary, rate, duration) and add the missing admin path to assign an acquiring employee/rate if absent.
- #24 Barcode/product-link attribution: QR/barcode + share link carry a source param; attribution stored on order item (window configurable); admin rule: discount on platform commission (percent / fixed / both / zero), selectable sale models; shown on vendor statement. Reuse existing attribution/tracking if present.
- #25 Per-classified-category commission (incl. marketer commission) surfaced as first-class columns, using existing "section exceptions".

### WS13 — Distance-based delivery (Backend + Frontend + DB)
- #19 Per zone/store type: free-delivery radius (km) + tiers (from–to km → price). Distance = straight line (haversine) from vendor/warehouse location to customer address (default, configurable later). Precedence: order-amount free shipping still wins if met. Admin UI with map radius preview if Google Maps already in project. Logged as client decision.

### WS14 — Page builder media UX (UI/UX + Frontend)
- #27 Image fields in page builder/custom/static pages: drag-drop upload, preview, reuse media library, per-device/locale slots — modelled on portal-content editor.

### WS15 — Client answers (PM/BA)
- Arabic reply document covering #6, #13, #15, #17, #19 decisions, #23 decision, banner editing location (#21), and the wrong HR link. Saved as `docs/client-reply-2026-11-23-ar.md`.

### WS16 — QA (Senior QA)
- Full `php artisan test --parallel`; `frontend` lint/typecheck/build; browser smoke on local dev for every confirmed bug.

### WS17 — QC (Senior QC)
- Review the full diff vs this plan: correctness, i18n completeness, security, migrations additive, no finance data mutation. Fix findings.

## Client decisions assumed (to confirm)
1. Distance = straight line from vendor location; order-amount free shipping overrides distance pricing.
2. Draft countries hidden from vendor registration by default; per-country toggle "open vendor registration before launch".
3. Free-period rule changes never touch invoiced/paid fees.
4. Barcode/link attribution window 7 days (configurable), last-touch.
5. Inactive category → 404; Visible → navigation only; Featured → homepage featured section.

## Progress log
(updated by PM after each workstream)
- **WS0 ✅** paratest 7.20 added; `php artisan test --parallel` (28 procs) → 1527 pass / 1 fail / 3 skip in ~27s (serial 417s). Worker DBs reused when migrations unchanged (tests/TestCase.php). Fixed CouponUsageConcurrencyTest for reruns. Pre-existing: MarketerContractAuditTrailTest (fakes wrong disk); date/TZ-boundary flaky tests (ContractTemplateUploadTest, SellerAgreementLifecycleTest, NawiAdsLifecycleTest x3); serial-only perms on storage/framework/testing (owned by joe).
- **WS1 ✅** #33 registration: early phone/email uniqueness + submit re-check + race → localized 422, JS never shows 5xx text. #40 admin wallet adjust now uses canonical `wallets` via WalletService (source_type=admin_adjustment), atomic, localized errors. Global: DB exceptions on JSON requests → generic 500 unless local+debug. +7 tests; suite 1534 pass/1 known fail. Follow-ups: prod APP_DEBUG=false; npm run build; plaintext password kept in registration session (QC).
- **WS2 ✅** All cancel entry points go through OrderCancellationService: cascade item→sub-order→master, COD tx cancelled/reduced, refund only for captured money (one record, mail only then), rider assignments cancelled (new migration: delivery_assignments.status `cancelled`), idempotent, localized refusals; vendor no-op fixed. +10 tests; suite 1544 pass/1 known. Open finance decisions: recalc order totals on partial COD cancel? ledger over-reversal after partial refund; who bears gateway fee on vendor/admin cancel; shipped-parcel carrier cancel. Order admin actions only need `orders.view` → handled in WS4 permission audit.
- **WS5 ✅** #10 server overlap math was right; real cause: JS sent empty/Arabic-digit max as null (=open-ended). Now explicit open_ended flag, Arabic digit normalization, localized error naming conflicting rule. #9 revert Paid→Invoiced / Invoiced→Pending (perm `fbn_storage_fees.revert_status`, reason, activity log); refused when fee was deducted in a payout (payout_id now stored). Fixed: regenerate month no longer re-prices invoiced/paid fees. #11 `effective_from` (A: creation / B: date), pending-impact preview + confirmed recalculation. +29 tests; suite 1573 pass/1 known. Decisions: date-based rule replacement for same weights; effective date = stored_since; legacy invoiced fees revert policy; check live for seeded 0–999 (60d) rule. FBN routes lack permission gates → WS4.
- **WS3 ✅** #18 per-device winner (`getActivePlacementByDevice`, `mobile_variant`), CSS show/hide (no UA sniffing), mobile-only works w/o desktop image. #30 PlacementAdService filled AR slots with EN files; new `resolveImageGrid`. #31 edit form put raw UTC into input → −4h per save; fixed + TZ hint. #21 empty-cart art was hard-coded with `href="#"`; now driven by Cart Page Banner (admin → Banners → placement "Cart Page Banner", URL field) with artwork fallback → `/`. `homepage_*` placements are dead code (hero = Page Builder hero_slider). +12 tests; suite 1585 pass/1 known; next build OK. Decisions: separate empty-cart placement (size mismatch); deals page for fallback link; require image for targeted device at save.
- **WS4a ✅** 1119 admin routes audited: 175 ungated + 163 mutating-with-view-only → 0 (49 allow-listed). #16b fixed (subscriptions.view/manage). 19 new permissions (migration 2026_11_23_115354), granted to super_admin + roles with the matching prior permission. Found: `orders.edit` didn't exist (only super admin could ship). +22 tests (route-gate sweep test); suite 1607 pass/1 known. Table: docs/plans/admin-permission-sweep-2026-11-23.md. PM decision: restore vendor_relations_admin's previous effective abilities (documents.verify, blacklist, strikes.issue, payouts.hold) → WS4b. Open: contracts/classifieds.manage super_admin only; delivery perms for ops; gate docs/*; review live-only roles after migrate.
- **WS4b ✅** vendor_relations_admin abilities restored (migration 2026_11_23_122657). #3/#4 central `Bilingual` helpers + HasLocalizedName; products DataTable, pickers, ~230 Blade lines localized; category search both languages. #16 `lang/*/permissions.php` (76 groups) + role-form search/select-all. #26 breadcrumbs map, Summernote ar-AR, page-builder block labels, DataTables AR chrome; sidebar search (Ctrl+K, "/", Esc, arrows). List search 59→71/106 pages; fixed broken search on FBN inbound/fees/rules, brands, attributes. Suite (PM-verified) 1617 pass/1 known; build OK. Leftovers: partner/travel role labels, docs pages, some JS strings, a few lists without search (listed in report).
- **WS7 ✅** #1 country switcher (was `hidden lg:flex`) now on all widths, guest address box separated. #2 customer reset API existed but never sent mail + broker table missing → new broker table migration, mail, storefront modal + /reset-password page (reset now needs token+email+password); admin /forgot-password added. #35 real `order_cutoff_at` countdown (hidden when null — no method has cutoff set). #38 `estimated_delivery_date` per shipping group, line hidden when none. +13 tests; suite 1630 pass/1 known; next build OK. Notes: prod FRONTEND_URL; handling_time_hours=24 on all methods; 3 delivery-date formulas (pick one); partner/marketer forgot-password reveal email existence → QC.
- **WS8 ✅** #22 new `<x-portal.wavy-text>`: per-letter wave only for Latin, per-word for Arabic, reduced-motion aware. #20/#25 /join card → «الإعلانات المبوبة» (migration rewrites untouched default copy), sub-options from active top-level classified categories per country; travel agency shown as sub-option; per-category landing `/companies/open-souq/{slug}` → registration signs that category's contract at signup (CategoryContractService). Legacy URLs kept. +13 tests (Portal 121/121); build OK. PM decision: rename «السوق المفتوح» everywhere (partner/admin/marketer) → WS9. Client questions: travel category vs agency, sub-category signup, block signup without contract?, separate policy doc?
- **WS9 ✅** #23 drafts are is_active=1/is_launched=0 → vendor pickers checked only is_active. New `accepts_vendors_before_launch` flag (Oman set true to keep current behaviour), `Country::availableForVendors()` + `CountryAvailableForVendors` rule applied to portal/travel/partner/vendor API/marketer pickers & validation. #32 list badges Live/Draft/Hidden/Deleted + Sellers column + filters + legend; revert-to-draft (countries.launch, logged). Fixed: unchecked toggles (COD) never saved. Rename «السوق المفتوح» → «الإعلانات المبوبة» in all display text. +13 tests; suite 1656 pass/1 known.
- **WS6 ✅** #5 one shared form for create/edit, single "Save all" in one transaction (CategoryFormService + ValidatesCategoryForm), sticky section nav, errors per section, unsaved guard. #8 "Countries" section (all / only / except) writes the same `country_categories.is_available` rows; parent exclusion inherited. #7 root cause: `/products?category=` ignored is_active + frontend swallowed errors → now 404; Visible = nav only; Featured feeds page-builder "category pills" block (new storefront section). +10 tests; suite 1666 pass/1 known; both builds OK. Client Qs: hide inactive-category products in search/home?; new countries default in "only" mode; featured via pills block OK?
- **WS10 ✅** #12 `packaging_supply_types` table (seeded with old codes, FK + code kept on items), types CRUD under packaging.manage, inline "+ New type"; found ENUM lacked bubble_wrap (would SQL-fail). #29 list read unused legacy columns → now per-country "OM 0.50 OMR · 100 / AE 0.60 AED · 50" + country filter. #13 CONFIRMED after all: admin search text OR-bypassed date/status filters; partner filter used UTC day — both fixed. +18 tests; suite 1684 pass/1 known; build OK.
- **WS11 ✅** #14 gender (existing column normalized to enum male/female nullable) on register/profile/admin + filter. Found: old date filter = join date only; orders/spend columns read dead `total_orders/total_spent` (stale seed); sort off-by-one; export ignored filters — all fixed. New "Joined between" + "Activity between" (live aggregates, excl. cancelled & failed payment, per-currency spend, USD-equiv sort). Index customers(created_at). +11 tests; suite 1695 pass/1 known; builds OK. Decisions: count refunded orders?; drop/maintain dead columns; loyalty ledger missing.
- **WS12 ✅** #15 acquisition = platform pays its own employee for bringing a vendor (manual assign, monthly job, min 60/max 100 sales); broken because dropdown only listed vendor_relations_admin (0 admins), no create button, start=approval date → expired, unique key blocked reassign. Fixed + help panel; earnings never paid (decision). #24 signed vendor product link/QR (`/s/{token}`, partner listing page), per-country rule (percent/fixed per unit/both/zero, window 7d, sale models), discount applied to that line's commission at checkout, stored on order_items, shown on partner order/statement + admin; last-touch vs marketer. #25 classifieds charge no platform sale commission; per-category commission page edits existing marketer_default_commissions with inheritance (NULL = inherit). 5 migrations; +25 tests; suite 1720 pass/1 known; builds OK. QC follow-up: product link should preselect vendor's own offer.
- **WS13 ✅** #19 per (zone, method) rule: free radius, max radius, km tiers, optional category scope; origin = fulfilling warehouse → vendor warehouse → vendor business address; haversine; precedence missing coords→normal, beyond max→unavailable (422), amount threshold→free, free radius→free, tier, gap→normal. Admin tab on /shipping-zones (map preview only if GOOGLE_MAPS_API_KEY), storefront note in cart/checkout. +22 tests; suite 1742 pass/1 known; builds OK. Gap: 0 vendors have coordinates and no UI to set them → WS13b. Client Qs: FBN/FBM now pay distance fee; mixed groups; multi-store sum vs farthest; gap→unavailable?; unify free thresholds.
- **WS13b ✅** Store location (business address lat/lng) editable in partner profile, admin vendor page (#store-location), vendor API; shared `<x-location-picker>` (Google map if key, else coords + geolocation); warehouses use it. `sub_orders.distance_pricing` JSON snapshot shown on admin order page; coverage hint on distance tab. +8 tests; suite 1750 pass/1 known. Options: Leaflet/OSM map when no Google key; owner-only location edit.
- **WS15 ✅** Arabic client reply drafted: docs/client-reply-2026-11-23-ar.md (fixed list, new features, answers #6/#7/#15/#16/#17/HR link, 8 decision questions). Update #27 line after WS14.
- **WS14 ✅** `<x-form.media-field>` (drag-drop, preview, progress, library from `files`, Desktop/Mobile × AR/EN with "same for all") on hero slides, ad images, promo tiles, section bg, video poster, full-banner preview, custom pages (image field was missing), static pages. Found: static-page images saved as base64 were stripped by sanitizer → silently lost; now uploaded. `/media/upload` + `/media/library` gated per context. +19 tests; suite 1769 pass/1 known; build OK. Leftovers: blog/help-center/ad-support same base64 bug → QC.
- **WS17 QC ✅** Fixed: plaintext password in registration session AND echoed into page HTML (vendor + travel wizards) → hash only; forgot-password enumeration + no throttle on partner/marketer/vendor API/travel API; banner requests authorize(); page-builder `preview` lang key collision; AR breadcrumbs; banner targeted-device image required; partner/travel role labels translated; acquisition widget month; product link lands on vendor's own offer; blog/help/ad-support editors upload images. +20 tests; 1789 pass, 1 known + 5 date-boundary flakies (run at ~00:05 Oman). Open: portal article bodies unsanitized ({!! !!}) → WS16; deploy note: order_items FK + customers.gender migrations rebuild tables (low-traffic window); OMR 3-decimal rounding decision.
- **WS16 QA ✅** Portal blog/help-center/ad-support bodies sanitized on save + render (heading ids + hc-* classes kept). Fixed date-boundary flaky tests + MarketerContractAuditTrailTest (disk aligned to `private`). Browser smoke 12/12 pass (screenshots in session scratchpad/qa); found + fixed `x-form-input` ignoring `id` (FBN rule form + weight-slab modal couldn't save). PM fix: free-period "from creation" day now in admin TZ + Arabic month name.
- **FINAL:** `php artisan test --parallel` → 1806 tests, 1803 pass, 0 fail, 3 skipped. 18 new migrations (list in QA report). Nothing committed/pushed.
- **Follow-ups (not done):** shipping_methods has no Arabic name column (EN names in AR UI); frontend/.next was built with local NEXT_PUBLIC_* → rebuild with prod env before deploy; deploy order_items FK + customers.gender migrations in low-traffic window; prod APP_DEBUG=false; client decisions in docs/client-reply-2026-11-23-ar.md.
