# Marketer Campaign tab refactor (vendor listing page) — plan & sub-agent prompts

Page: `partner.noon.codefanz.com/listings/{id}` → tab **Marketer Campaign**
(`backend/resources/views/partner/listings/show.blade.php`, tab `marketer_campaign`, ~L550-640;
data in `Partner\ListingController@show` ~L586; campaigns in `Partner\MarketerCampaignController`,
`app/Services/MarketerCampaignService.php`).

Rules for every agent: work in `/home/claude/marketplace_platforms/backend`; read `.ai/rules/index.md` first;
never edit existing migrations (new ones only); BIGINT base-currency money, no /100; UUID PKs; Arabic UI strings go
through `lang/*` files (`partner.*`, `admin.*`); run `vendor/bin/pint --dirty --format agent`;
commit on `main` with the attribution trailer; **do not push** (the orchestrator pushes at the end).
No `.env` in this container → `php artisan migrate`/tests needing DB cannot run; verify with `php -l`, `route:list` if bootable, and careful reading.

## 1. Current state (from DB dump `backups/marketplace_platform.sql` + code)

- `marketer_campaigns`: vendor_id, vendor_listing_id, commission_type(fixed|tiered|last_click), max_commission_budget,
  platform/marketer_commission_amount, requested_marketer_vendor_ids(json), status(pending_admin, marketer_requested, active,
  auto_approved, rejected, paused, done, cancelled), auto_approve_at, *_sample_qty_snapshot, title, notes, selected_ad_types,
  vendor_ad_notes, SoftDeletes. Dump already has 8 campaigns, several per listing over time.
- `marketer_campaign_invitations`: status(pending, accepted, rejected, timed_out, replaced, cancelled), acceptance_window_hours (12 default),
  expires_at, referral_code/link/qr, whatsapp_sent, replaced_invitation_id, platform_fee_amount/status(not_applicable, pending, paid, waived)/payer.
- `marketer_campaign_samples` (platform / marketer owner), `marketer_campaign_tiered_rules`, `marketer_campaign_conversions`.
- Admin-controlled settings already in DB: `marketer_influencer_fee_country_settings.fee_per_influencer` (per country),
  `marketer_commission_rules` / `marketer_category_commissions` (mode fixed|percentage|both, rate, min, flat; per marketer + category),
  `marketer_commission_country_settings` (influencer/affiliate amount per category+country), `marketer_profiles.commission_discount_*`,
  settings keys `marketer_influencer_fee_payer`, `marketer_invitation_timeout_hours`, `marketer_campaign_auto_approve_hours`,
  `marketer_campaign_allowed_fulfilment_models`, category `min_stock_for_campaign`, `influencer_sample_qty`, `affiliate_sample_qty`, `platform_sample_qty`.
- Tab today: shows ONLY the latest campaign (`->first()`), a link to details, or a "create" CTA. `MarketerCampaignController` has
  create/store/index/show/cancel/cancelActive/inviteMarketers/approveRequest/searchMarketers — **no edit/update/delete**, and
  `hasActiveCampaign()` forbids a second non-terminal campaign per listing.

## 2. Client requirements → mapping (source: pasted client text, 2 sections)

| # | Requirement | Status / Work |
|---|---|---|
| R1 | Promotion fee set by admin, per influencer picked: 1 = 9, 2 = 18, 3 = 27 … (linear, per country, currency of country) | Setting exists (`fee_per_influencer`). Show live total in create/tab; ensure charge = count × fee |
| R2 | Fee profit goes to platform; to the seller it is a deduction in his statement from total weekly sales; may be deducted any time if balance short during that week | Wallet debit + `CollectPendingInfluencerFeesJob` exist. Verify ledger/statement line + weekly settlement deduction; add if missing. Payer default = vendor |
| R3 | Fee with commission marketers (affiliates) = free | Already (`not_applicable`/waived). Keep + show "Free" in UI |
| R4 | Commission of influencer/affiliate = per category, set by admin; admin cut taken from each marketer's profit per his profile (discount fields) | **Selected marketer's commission must come from admin settings, not typed by vendor.** Show read-only per-marketer rate in selection UI + campaign details; vendor keeps only budget cap/ad notes. Resolve via `CommissionRuleResolver` |
| R5 | Samples: 1 per chosen influencer + 1 for admin (2 influencers → 3 samples) deducted from stock as a normal sale incl. admin fixed+variable commission; seller statement shows 0 with note "promotion sample" | Samples rows exist. Verify stock deduction + commission + zero-priced statement line with note; add if missing |
| R6 | Affiliates: no per-marketer pick (offer visible to all registered); only admin-defined sample count | Handle in create UI: affiliate mode = "open to all", show sample qty |
| R7 | Admin sets minimum stock to allow promotion | `min_stock_for_campaign` exists; surface the number + current stock in UI, block with clear message |
| R8 | FBP/FBM: warn seller order is not complete until goods are sent to admin warehouses | Show warning banner in create + tab + campaign details |
| R9 | Influencer has 12h (admin-configurable) to accept/reject; on reject/timeout auto-pick another (suggest: least-requested last month), with admin transfer report; seller warned at selection time that choosing = consent | Exists (`replaceMarketer`, timeout job). Verify seller notice text, seller notification on reject ("searching replacement"), admin transfer report entry |
| R10 | Promotion requests sent to influencer by WhatsApp with accept/reject link; seller gets the action taken | `whatsapp_sent` exists. Verify; list gaps |
| R11 | Influencer must not be picked twice for same product in same month by another marketer (category 2) | Marketer-side; audit only |
| R12 | 4 marketer categories, monthly minimum promotions (admin-set), x/y counter, red alert + WhatsApp last-week reminder, penalty; barcode stability when ads move between influencers; VAT per country on all commissions; admin cut on net commissions in final statement | Mostly marketer/admin side (`PromotionQuotaService`, `PromotionCategoryTarget`). **Audit & report gaps only** — not part of this tab |

## 3. Target UX (tab)

Tab `marketer_campaign` becomes a CRUD table of **all** campaigns of this listing (paginated 10, newest first):
columns: title/#, status badge, commission type, invited/accepted counts, fee total, budget spent/cap, created date, actions.
Header buttons: **+ New campaign** (disabled with reason if listing not FBN/FBM-allowed, below min stock, or listing not active).
Row actions (permission-gated, existing `marketer_campaigns.*`): **View** (link to `partner.marketer-campaigns.show` — a real page),
**Edit** (only `pending_admin` / `marketer_requested`), **Invite more** (active), **Cancel** (pending/active), **Delete** (soft-delete, only cancelled/rejected/done/pending).
Empty state + stat tiles (total, active, pending, conversions, commission spent). Same list/UX also reachable from `partner.marketer-campaigns.index` with filter `?listing=`.
`create` route takes the listing (existing `/create/{vendorListing}`); after store redirect back to the listing tab (`#marketer_campaign`) with flash.

## 4. Agent prompts (run one by one, commit after each)

### P1 — Backend: multi-campaign data + CRUD endpoints
1. `Partner\ListingController@show`: replace `->first()` with a paginated/limited collection `$marketerCampaigns` (+ aggregate stats: total, by status, conversions count, `commission_budget_spent`, invitation counts via `withCount`); keep `$marketerCampaign` removed from compact; update every consumer.
2. `Partner\MarketerCampaignController`: add `edit`, `update` (only pending states: title, notes, commission_type, max_commission_budget, tiered rules, selected_ad_types, vendor_ad_notes; marketer list change goes through invite/cancel, not edit), `destroy` (soft delete; allowed statuses cancelled/rejected/done/pending_admin; never while active; if still pending_admin first cancel via service so listing `campaign_enabled` and invitations are cleaned up). Authorize vendor ownership + permission `marketer_campaigns.create|cancel|view` (add `marketer_campaigns.delete` to the permission seeder/migration pattern used by the repo and grant to the same roles that have `cancel`).
3. Relax `hasActiveCampaign()` (create + store): allow multiple campaigns per listing; instead forbid selecting a marketer who already has a non-terminal invitation (pending/accepted) in another non-terminal campaign of the same listing (validation error naming the marketer). Remove the "redirect to existing campaign" in `create()`.
4. Routes in `routes/partner.php` for edit/update/destroy next to existing marketer-campaigns routes (middleware pattern identical). Add a `listing` query filter to `index()`.
5. Make sure `listing->campaign_enabled` stays true while any non-terminal campaign exists and becomes false only when none remain (service cancel/done paths).
Commit: `feat(partner): marketer campaigns CRUD endpoints + multi-campaign per listing`.

### P2 — Frontend: rebuild the tab + views
Rebuild tab in `partner/listings/show.blade.php` per section 3 (extract to partial `partner/listings/partials/marketer-campaign-tab.blade.php`). Update `marketer_campaigns/index.blade.php` (listing filter, actions), add `marketer_campaigns/edit.blade.php` (reuse create form partial; extract shared fields into `_form.blade.php` if cheap), add Edit/Delete/Invite-more buttons + status timeline on `show.blade.php`. All strings in `lang/ar|en/partner.php` (check existing `partner.listings.show.marketer_campaign.*` keys first and extend). RTL-safe Tailwind, match existing card/table styling. Delete requires confirm.
Commit: `feat(partner): marketer campaign tab as full CRUD list with detail links`.

### P3 — Fees & commissions from admin settings (R1–R4, R6)
Create form + `show` page + tab:
- Selection list shows for each marketer: type (influencer/affiliate), country, **fee** (`marketer_influencer_fee_country_settings`, influencer only; affiliate = Free), and **commission** (resolved from admin rules for the listing's category via `CommissionRuleResolver`/`MarketerCommissionRule`, plus profile discount) — read-only, labelled "set by platform".
- Live total fee = influencer_count × fee_per_influencer (country of listing), via the existing `partner.listings.marketer-fee` endpoint (extend to return per-marketer breakdown if needed); show payer (`marketer_influencer_fee_payer`) and the "deducted from weekly sales statement, may be deducted earlier if balance is insufficient" notice.
- Backend: make `store` compute/validate server-side (never trust client numbers). Ensure vendor can no longer type per-sale commission amounts that contradict admin rules: keep `commission_type`/budget cap only if business logic still needs them — otherwise default `commission_type` to `fixed` and set `marketer_commission_amount` from rules at approval (`resolveDefaultCommission`). Document the decision in the commit body.
- Affiliate mode: UI says "open to all registered affiliates", shows admin-defined sample qty, no individual picking.
- Add admin-side settings gaps only if something in R1/R4 has no admin screen (check `Admin\MarketerSettingsController`, `admin/marketer_settings`).
Commit: `feat(partner): show admin-set fees/commissions per marketer in campaign flow`.

### P4 — Samples, stock rules, FBP/FBM warning (R5, R7, R8)
Verify and complete: (a) samples (1 per accepted influencer + platform qty) are deducted from stock as a normal sale line with admin fixed+variable commission; (b) vendor financial statement shows a 0-value line with the note "promotion sample / عينة ترويج" (find the vendor statement/ledger builder via `grep -ri statement app/Services`); (c) min-stock check (`min_stock_for_campaign`) enforced in `create` page (not only service) with numbers displayed; (d) warning banner for FBM/FBP: campaign does not proceed until stock is sent to admin warehouses. Add the missing pieces; keep changes minimal and covered by notes in commit body.
Commit: `feat(campaigns): promotion sample accounting, min-stock gating and FBM/FBP notice`.

### P5 — Notifications & replacement transparency (R9, R10)
Audit `acceptInvitation/rejectInvitation/handleInvitationTimeout/replaceMarketer` and notification classes: confirm (1) consent notice shown at selection time with the configured hours (`marketer_invitation_timeout_hours`), (2) vendor is notified of accept/reject/timeout and on reject gets "searching for a replacement influencer", (3) WhatsApp message carries accept/reject link (`whatsapp_sent`), (4) every replacement writes an admin-visible transfer record (from → to, reason, time) — add a small admin list/section on the campaign admin page if absent. Fix gaps.
Commit: `feat(campaigns): vendor/admin notifications and replacement report`.

### P6 — QA + gap report
Read-only review of P1–P5 diff against section 2 table; run `php -l` on all changed PHP, check every route name referenced in Blade exists (`grep` route names vs `routes/partner.php`), lang keys exist in both `ar` and `en`. Fix defects found. Then append **"Gap report"** to this file: status of R1–R12 (done / partial / not in scope) with file refs — R11/R12 marketer-side items listed as follow-ups with concrete next steps.
Commit: `docs(campaigns): QA fixes and requirement gap report`.

## 5. Gap report (P6 QA, after P1-P5)

QA performed: `php -l` on all changed PHP (clean); every `route()` name in changed Blade verified against `php artisan route:list` (all exist); changed Blade views compile (`view:cache`); controller views/variables checked (`create`: marketerVendors, affiliateCount, quote, minStock, availableStock; `show`: quote, quoteRows; `edit`; tab partial: marketerCampaigns, marketerCampaignStats, marketerCampaignBlock; no `$marketerCampaign` leftover in listing views); both migrations have correct `down()`; static + dynamic `__()` keys checked in `lang/ar` and `lang/en`.
Defects fixed: missing admin lang keys (ar+en) `admin.marketer_campaigns.transfer_reason_{replaced,pending,cancelled,accepted}` and `status_{marketer_requested,paused,cancelled,completed}` (would print raw keys on admin campaign page/transfer report). Known leftover (legacy values, low risk): `partner.marketer_campaigns_my.status.completed` and `.commission_type.percentage` have no translation.

| # | Status | Where / notes |
|---|---|---|
| R1 | Done | `MarketerCampaignQuoteService::quote()` (count x `fee_per_influencer`), `ListingController::getInfluencerFee`, create form live total, `show` page |
| R2 | Partial | Fee charged via wallet + `CollectPendingInfluencerFeesJob`; weekly-statement deduction line exists in `GenerateVendorPayoutsJob` (~L165-178) for legacy promotion requests but NOT verified for `MarketerCampaignInvitation.platform_fee_amount`. Next: confirm invitation fees reach `calc` and a `PayoutItem` (item_type influencer fee) is created; test shortfall-mid-week debit |
| R3 | Done | Affiliate fee = not_applicable; "Free" shown in create/tab/show |
| R4 | Done | Commission read-only per marketer from admin rules (quote service, `CommissionRuleResolver`); `store`/`update` force `commission_type=fixed`, ignore client amounts |
| R5 | Done (P8) | One fixed admin sample per product campaign + one sample per influencer at invitation dispatch + one affiliate row (`CampaignSampleService`); replacement re-points / cancel cancels undispatched samples; stock out once and admin sale commission (`CheckoutCalculationService::calculateCommission` on price x qty) snapshotted at dispatch; charged once as 0-priced `promotion_sample` PayoutItem (`PayoutCalculationService::selectSampleCommissions`, `GenerateVendorPayoutsJob`); feed/finance/admin payout views show it |
| R6 | Done | Affiliates "open to all" (`open_to_affiliates`), sample qty shown; no individual pick |
| R7 | Done | Min stock enforced in `create()`, listing tab (`marketerCampaignBlock`) and service, numbers displayed |
| R8 | Done | FBM/FBP warning banner in create, tab, show |
| R9 | Done | Consent notice with timeout hours, vendor notification (`CampaignInvitationResponseNotification`), admin transfer report on admin campaign page (`admin/marketer_campaigns/show.blade.php`) |
| R10 | Partial | `whatsapp_sent` flag and link exist; actual WhatsApp delivery of accept/reject link not verified end to end. Next: trace the send path in `MarketerCampaignService`, add a log/test with a fake gateway |
| R11 | Deferred (marketer side) | Same-month/same-product exclusivity across marketers not enforced; P1 only blocks the same listing's open campaigns. Next: check in `MarketerCampaignQuoteService::eligibleMarketers()` and `inviteMarketers()` for an accepted invitation in the current month for the same product by a category-2 marketer |
| R12 | Not in scope / deferred | Marketer categories, monthly quota counter and red alert, WhatsApp last-week reminder, penalty (`PromotionQuotaService`, `PromotionCategoryTarget`), barcode stability on reassignment, per-country VAT on commissions, admin cut in final statement. Next: audit those services against the spec in a separate task; marketer dashboard x/y counter is the first UI gap |

Other deferred: admin fixed+variable commission on samples (P4, see R5); no automated tests were run (no DB/.env in the container).

---

# PHASE 2 — finish every deferred flow, with tests on each panel

## 6. Test environment (already prepared in the cloud container — do not reinstall)
- MariaDB runs locally: if `mysql -uroot -proot -h127.0.0.1 -e 'select 1'` fails, start it with
  `mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld; (nohup mysqld_safe --user=mysql >/tmp/mysqld.log 2>&1 &); sleep 8`.
  DB `marketplace_test`, user root/root (matches phpunit.xml). `php-bcmath` is installed.
- `backend/database/schema/mysql-schema.sql` is **locally patched** (removed `WITH PARSER ngram`, MySQL-only) and marked
  `git update-index --skip-worktree`. NEVER commit it, never `git add -A`; add files by path. Original copy: `/tmp/claude-0/mysql-schema.orig.sql`.
- Run: `cd backend && php artisan test --compact tests/Feature/<File>.php` (output is JSON-ish; grep `"result"`/`"message"`).
  Feature tests that render Blade need Vite: use `$this->withoutVite()` in the test (do not run npm build).
- Known failing before phase 2 (environment / pre-existing): `CommissionReconciliationTest::test_two_item_qty_gt_one…`.
- Each agent: write tests for the flow it builds **on the panel(s) it touches** (Partner = guard `vendor`, Marketer = guard `marketer`, Admin = guard `admin`;
  copy auth/factory patterns from `tests/Feature/MarketerPromotionFlowTest.php`, `MarketerSamplesLifecycleTest.php`, `Marketer/MarketerLifecycleTest.php`, `Admin/*`),
  RUN them, and fix until green. Do not weaken existing tests. Money = BIGINT base units, no /100. UUID PKs. New migrations only.
- Commit by explicit paths, on `main`, no push. Trailer:
  `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>` / `Claude-Session: https://claude.ai/code/session_01PnUsba6EycSb23LHme8ch7`

## 7. Prompts

### P7 — Baseline triage (tests green before new work)
Run: MarketerPromotionFlowTest, MarketerSamplesLifecycleTest, MarketerCampaignSourcesTest, Commission/VendorCampaignPricingTest, Marketer/MarketerLifecycleTest, MarketerAttributionConversionTest, MarketerListingSourceTest, Cart/MarketerListingCartRetentionTest, Api/Marketer/ListingToggleStatusTest.
Observed: MarketerPromotionFlowTest 1 fail (ViteManifestNotFound → env, add `withoutVite()`); MarketerSamplesLifecycleTest 1 fail (expects 403 got 302); MarketerLifecycleTest 1 fail (“false is true”).
For each failure decide: environment, stale test, or regression from phase-1 commits `6c7b6e08..f0b7ec0d` or from the marketer-listings FBN refactor (`9019b2d0..84809e28`: status enum cast `MarketerListingStatus`, `source_listing_id` now nullable, new sub_orders.marketer_id). Fix code for regressions, update tests only when the old expectation is truly obsolete (explain in commit body). Goal: that list green.
Commit: `test: restore campaign/marketer baseline`.

### P8 — Samples per marketer + fixed admin sample (R5 complete)
Spec: every campaign has (a) ONE fixed admin/platform sample (qty = category `platform_sample_qty`, minimum 1 via setting `marketer_campaign_min_platform_sample_qty` default 1) created at campaign creation; (b) one sample row for **each influencer selected**, created when the invitation is dispatched (status pending, invitation_id set; qty = category `influencer_sample_qty`, minimum 1 via setting default 1) — not only at acceptance; (c) affiliates: no per-affiliate sample, only the admin-requested qty (`affiliate_sample_qty`) as platform-owned rows once per campaign if the campaign targets affiliates; (d) replacement of an influencer moves/re-points the undispatched sample to the new invitation (no duplicate), rejected/timed-out/cancelled with undispatched sample → sample `cancelled` (add enum value by migration if needed); (e) campaign totals: 2 influencers ⇒ 3 samples. Stock: available-stock check for campaign creation must subtract total samples; dispatch deducts stock once (existing `deductCampaignSample`).
Accounting (R5): a dispatched sample is treated as a normal sale for commission: compute admin fixed + variable commission with `CommissionRuleResolver` on the listing price × qty, charge that commission to the vendor, credit price 0. Feed `MarketerCampaignSample` rows into `GenerateVendorPayoutsJob` `calc['sample_items']`/PayoutItem `promotion_sample` (price 0, note «عينة ترويج مع المسوقين», commission deduction) — replace the dead legacy branch; keep `TransactionFeedService` line consistent (commission shown). Idempotent per sample (no double charge across weekly runs).
UI: samples table (owner, marketer, qty, status, stock deducted) on vendor campaign show, admin campaign show (with dispatch action existing), marketer samples page. 
Tests: Partner (campaign create → show samples), Admin (approve → dispatch sample → stock deducted once, repeat = no double), Marketer (samples page lists own sample), service test for 2 influencers = 3 samples + replacement + cancel, payout job test (price 0, commission charged once).
Commit: `feat(campaigns): per-marketer + admin samples with commission accounting`.

### P9 — Influencer fee settlement in weekly statement + VAT (R2 complete)
Spec: fee total = Σ influencers × `fee_per_influencer` (country). Platform profit. Seller sees it as a deduction line in his weekly statement against total weekly sales; may be taken at any time during the week when the wallet can cover it (existing `collectInfluencerFee`/`CollectPendingInfluencerFeesJob`); anything still `pending` at statement time is deducted in the statement as a PayoutItem (type `influencer_fee`, ref invitation id) and the invitation marked `paid` — never both (idempotent, race-safe via locking/status guard). Waived/affiliate = no line. VAT: apply the country VAT rate (find existing VAT/tax setting on `countries`/settings first; if none, add `vat_rate` setting per country with admin UI in marketer settings) on the fee: store `platform_fee_vat_amount` on invitation (migration), show fee + VAT in vendor statement/feed, campaign create quote and show page.
Tests: Partner (quote shows fee+VAT), service (wallet enough → paid immediately; short → stays pending → job pays later; statement run deducts exactly once; affiliate free), Admin (statement/payout view shows influencer_fee line).
Commit: `feat(campaigns): influencer fee weekly settlement with VAT`.

### P10 — WhatsApp delivery flow (R10 complete)
Audit the real path: `dispatchInvitation` → `SendCampaignWhatsAppNotificationJob` → gateway. Build/finish: a `WhatsAppGateway` contract (+ existing provider implementation or a `LogWhatsAppGateway` default driven by config/setting, bound in the container, swappable with a fake in tests); message templates ar/en with marketer name, product, fees-free/commission summary, deadline (acceptance_window_hours) and **signed accept/reject URLs** (public signed routes on the marketer domain that accept/reject the invitation through `MarketerCampaignService`, expired/used links show a friendly page; if marketer not logged in, show a minimal confirmation page); mark `whatsapp_sent/at` only on success, retry with backoff, failures logged and visible to admin on campaign show; vendor gets notified with the action taken (already via `CampaignInvitationResponseNotification` — also WhatsApp to vendor optional via same gateway, behind setting). Use `marketers.whatsapp_for_campaigns`.
Tests (with fake gateway): job sends once with signed links; accept link → invitation accepted, samples/fees flow triggered, vendor notified; reject link → rejected + replacement dispatched; tampered/expired signature → 403/expired page; admin campaign page shows failed sends.
Commit: `feat(campaigns): WhatsApp invitation delivery with signed accept/reject links`.

### P11 — Admin cut %, VAT on commissions, marketer net statement (R12 part)
Spec: every commission earned by influencer/affiliate/intermediary: gross commission → admin cut % (set by admin **per marketer in his profile**; ex. 100 gross, 30% ⇒ admin 30, net 70) → VAT per country on commissions → net paid to bank. Audit what exists (`marketer_profiles.commission_discount_*`, conversion `platform_commission_amount`, wallet service, withdrawal flow, `Admin\MarketerController` profile edit). Add `marketer_profiles.platform_cut_percentage` (decimal 5,2, migration) + admin edit field + audit; apply at conversion approval/credit; marketer Finance pages (commissions, wallet, withdrawal) show gross / admin cut / VAT / net; admin sees the same per conversion and in payout. Interaction with existing discount fields must be documented in code comment and commit body (discount reduces the cut). Round with integer math, no float drift.
Tests: Marketer panel (finance pages show breakdown 100→30→70), Admin (set % on profile, validation, audit), service tests for rounding/VAT/discount combos, withdrawal uses net.
Commit: `feat(marketer): admin cut percentage, VAT and net commission statements`.

### P12 — Promotion categories, monthly quotas, alerts, penalties (R12)
Spec (client): 4 categories with admin-defined **monthly minimum ads per category**: (1) influencer promoting sellers' products via seller requests (min 3 default); (2) influencer promoting OTHER influencers' own products (min 4; one product per owner influencer per month); (3) intermediaries ("الوسطاء") = admin-curated listings (existing platform products converted to this category, or new); (4) Nawy private products — same mechanics as 3. Marketer dashboard shows counter x/y per category; may exceed; below target at month end ⇒ red + alert; WhatsApp reminder in the **last week of month** when below target; contract penalty (fine) record when month closes short (admin-configurable amount per category, default 0, creates a ledger/fine row visible to admin and deducted at next payout); admin sets targets/penalties in marketer settings (`PromotionCategoryTarget`), and admin listing create/edit page gets an option "show in category 3 / 4" (checkbox/select) alongside existing fields, plus a separate admin page to add/convert products per category.
Audit `PromotionQuotaService`, `PromotionCategoryTarget`, `Admin\MarketerSettingsController` and marketer dashboard first; extend, don't duplicate. Month rollover job (`promotion:close-month`) scheduled in `routes/console.php`/Kernel pattern used by repo; reminder job uses WhatsAppGateway from P10 (+ in-app notification).
Tests: Marketer (dashboard counter, red state), Admin (targets CRUD, listing category option persists, fines list), service (progress counting per category, month close creates fine once, reminder sent once in last week only), scheduler registration.
Commit: `feat(marketer): promotion categories, monthly quotas, reminders and penalties`.

### P13 — Influencer-to-influencer promotion (R11) 
Spec: category 2 means an influencer promotes a product listed by another influencer (a marketer's own FBN listing, see `MarketerListing` with `source_listing_id IS NULL`, commit `9019b2d0..`). Add campaign source type `marketer_listing` (migration `marketer_campaigns.marketer_listing_id`, `CampaignSource::marketerListing()`, `createCampaign` branch with stock check against the marketer listing's `warehouse_inventories`, commission per category rules, platform cut), request flow in marketer panel (existing `campaigns/request` + search) so an influencer can pick other influencers' products; **owner influencer must see which influencer promoted his product** (owner campaigns page + admin page); enforce **one promotion per (promoter, owner influencer) per calendar month** with a clear error; promoter cannot promote own listing; owner’s listing must be `active`. Conversions: attribution creates commission for the promoter, owner is seller (sub_order seller_type marketer already built).
Tests: Marketer panel (request flow, month rule, own-listing block, owner sees promoter), Admin (campaign shows source + promoter), service tests incl. stock and commission.
Commit: `feat(campaigns): influencer-to-influencer promotion with monthly exclusivity`.

### P14 — Referral code / barcode stability (R12)
Spec: each influencer promotes with his own code/QR (invitation `referral_code`, `qr_code_path`); when an ad moves between influencers (replacement, timeout, reject) a customer who scanned an older code must not mis-credit commissions. Audit attribution (`/r/{code}` route, cookie/session attribution, `MarketerAttributionConversionTest`). Define & implement: replaced/timed_out/rejected invitation codes stay resolvable for a grace window (setting `marketer_referral_grace_days`, default 7) crediting the ORIGINAL marketer only for orders attributed before replacement (no new attributions after replacement ⇒ redirect to product page, no commission), new invitation gets a fresh code/QR; accepted code remains valid while campaign active; cancelled/done campaigns stop attributing; commission never goes to two marketers for one order; admin sees code history per campaign (chain via `replaced_invitation_id`).
Tests: attribution matrix (active, accepted, replaced-in-grace, replaced-after-grace, cancelled campaign), double-credit prevention, customer-facing route redirects, admin chain view.
Commit: `feat(campaigns): referral code lifecycle and attribution safety on reassignment`.

### P15 — Cross-panel lifecycle tests + final QA
Write `tests/Feature/MarketerCampaignLifecycleTest.php` covering the full flow through the three panels with HTTP requests (guards vendor → admin → marketer → customer order): vendor creates campaign from listing tab (fee+VAT quoted, samples listed) → admin approves → invitation dispatched (fake WhatsApp) → influencer accepts via signed link → fee settled → sample dispatched (stock deducted, commission) → customer order with referral code → conversion approved (admin cut, VAT, net) → weekly statement shows influencer fee + sample line + sales → marketer withdrawal net. Plus rejection/timeout → replacement path, and cancel path.
Then run all tests touched in phase 2 + the baseline list from P7; fix failures; update section 5 gap report (R1–R12 final status) and mark remaining items honestly. Restore nothing in the schema file (orchestrator does).
Commit: `test(campaigns): cross-panel lifecycle suite and final gap report`.
