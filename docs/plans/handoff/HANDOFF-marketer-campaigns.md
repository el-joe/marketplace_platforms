# Handoff — Marketer campaigns & marketer-as-seller (what is done, what is left)

Repo: `el-joe/marketplace_platforms`, branch `main`, Laravel app in `backend/`.
Read first: `docs/plans/marketer-campaign-tab.md` (requirements R1-R12, plan, FINAL gap report in section 5).
All feature work is implemented and pushed. **What remains is test coverage, verification and QA.**

## Marketing fees + marketer payouts (P1-P9, 2026-10-14) - READ THIS FIRST
Plan: `docs/plans/marketing-fees-and-marketer-payouts.md`; owner decisions and every ambiguity: `docs/plans/marketing-fees-decisions-log.md`; before/after, current flow and open decisions: `docs/plans/marketing-fees-final-summary.md`.

**Flow in one paragraph.** Influencer campaign: vendor create form quotes (server side) fee x N + VAT + marketing fees (fixed per country + variable per country/category/type) = grand total (22.69 in the UAE example) -> the snapshot is stored on the campaign at creation -> admin approves -> each influencer's accept debits the vendor `platformFeeTotal()` (fee + VAT + its share of the marketing fees; last acceptor takes the rounding remainder) from the wallet, else the weekly statement -> once the fee is `paid` the influencer wallet is credited `fee x category fee-share %` (1.80 of 9.00 at 20 %), exactly once. Affiliate campaign: no upfront fee; every approved conversion writes a fee accrual row (marketing fees on the affiliate commission, flat amounts once) -> history on the vendor, admin and marketer campaign pages -> when the campaign finishes, is cancelled, or auto-pauses on `max_commission_budget`, the accrued total is debited from the vendor once (wallet all-or-nothing, else statement line `affiliate_marketing_fee`). The commission itself stays on the old per-order vendor-payout path (D10). Cancelled/rejected campaigns refund paid influencer fees and reverse the credit (P7).

**Tables.** New: `marketing_fee_country_settings`, `marketing_fee_category_settings`, `marketer_campaign_fee_accruals` (unique conversion_id + entry_type), `marketer_campaign_fee_settlements` (kind charge|refund). Changed: `marketer_commission_country_settings` (+`influencer_fee_share_pct`, `affiliate_commission_mode|rate|flat`; the two old amount columns are DROPPED), `marketer_campaigns` (+fulfillment_type, campaign_subtotal, marketing_fee_fixed|variable|total, campaign_grand_total, affiliate_fee_accrued, affiliate_fee_settled_at, marketing_fee_fixed_accrued), `marketer_campaign_invitations` (+platform_fee_marketing_amount, marketer_fee_share_pct, marketer_payout_amount, marketer_credited_at, marketer_payout_wallet_tx_id, refund/void/reversal/clawback columns, status `refunded`), `payout_items` (+marketing_fee_amount, +affiliate_fee_settlement_id, item type `affiliate_marketing_fee`).
**Services.** `MarketingFeeService`, `MarketerFeeShareResolver`, `AffiliateCommissionResolver`, `MarketerCampaignQuoteService` (+wallet check), `MarketerCampaignService` (snapshot, acceptance charge), `MarketerFeePayoutService`, `AffiliateFeeAccrualService`, `AffiliateFeeSettlementService`, `MarketerInvitationRefundService`, `MarketingFeeReportService` (single source of every report number), `FinancialReportService::marketingFeeLines()`.
**Jobs (routes/console.php).** `RetryMarketerFeeCreditsJob` hourly (credit retry), `RetryInvitationFeeRefundsJob` hourly (fee refund retry + marketer clawback recovery), `AffiliateFeeSettlementJob` hourly (affiliate settlement safety net and wallet retry), plus the existing `CollectPendingInfluencerFeesJob` daily.
**Settings / permissions.** `marketer_campaign_allowed_fulfilment_models` default now `['fbn','fbm','cross_dock']` (a stored row must include `cross_dock` by hand); `marketer_influencer_fee_payer` is ignored for vendor campaigns (D7). Permissions reused: `marketer_commission_settings.view|edit`, `marketer_fee_settings.edit`, `marketer_campaigns.approve` (settle now).
**Pages.** Admin: Marketing Fees (`admin.marketing-fees.index`, tabs fixed/variable/report + calculator), category edit tab Marketers, campaign show (snapshot, per-invitation payout, affiliate history + settle now), marketer profile earnings, finance report marketing block (`?section=marketing` CSV). Vendor: create form breakdown, campaign page, campaign list/transactions/statement totals. Marketer: `marketer.campaigns.show`, reports, wallet, API `/api/marketer/finance/reports`.

**Tests.** End to end: `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php` (influencer flow, affiliate finish, affiliate budget exhaustion). Per prompt: `MarketingFeeServiceTest`, `MarketerFeeShareResolverTest`, `Unit/MarketingFeeTypeTest`, `AdminMarketingFeesPageTest`, `Admin/CategoryMarketersTabTest`, `Commission/LegacyMarketerCommissionAmountsMigrationTest`, `Campaigns/{AffiliateCommissionResolverTest, MarketingFeeQuoteSnapshotTest, InfluencerChargeAndMarketerCreditTest, AffiliateFeeAccrualAndSettlementTest, CancellationRefundSymmetryTest, MarketerPerSaleCommissionTest}`, `Reports/{MarketingFeeFinancialReportTest, MarketingFeePanelReportsTest}` (fixtures `tests/Support/MarketingFeeReportCase.php`). Full suite on 2026-10-14: green (see final summary).

**Test environment notes (MariaDB).** Besides the schema parser patch below, two migrations (`2026_10_13_100001_*`, `2026_10_13_200001_*`) read a column default `NULL` as a string on MariaDB and are patched LOCALLY. All three files are marked `git update-index --skip-worktree` and must never be committed. The full suite takes ~7 minutes: run it in the background against a second database so the working DB stays free (`CREATE DATABASE marketplace_full; DB_DATABASE=marketplace_full php artisan test --compact > full.log &`; `phpunit.xml` env values do not override real env vars). Blade tests need `withoutVite()` (already in `tests/TestCase.php`).
**Browser smoke (not automated).** `.env` can point at a seeded local DB; `npm run build` fails offline because `laravel-vite-plugin` fetches the Bunny font: build with a throwaway copy of `vite.config.js` without the `fonts` entry and do not commit it or `public/build`. Panels are host based (`admin.localhost:8000`, `partner.localhost:8000`, `marketer.localhost:8000`); Playwright from `/opt/npm-tools` with `/opt/pw-browsers/chromium-*`; switch language with POST `/set-locale` (admin), `/locale/switch` (partner, marketer) via an in-page `fetch` (Playwright's request context cannot resolve `*.localhost`). Last run: 8 pages x en/ar, no 500 / raw keys / overflow.


## Done (implemented, pushed)
Marketer listings as FBN own-inventory seller listings (admin approve/reject, stock pages, orders tab, checkout/customer API);
vendor listing page "Marketer Campaign" tab as full CRUD (multi-campaign, view/edit/invite/cancel/delete); fees/commission from admin settings;
per-influencer + fixed admin samples with commission accounting; influencer fee weekly settlement + VAT; WhatsApp gateway with signed accept/reject links;
admin cut % + VAT + net commissions + ledger; 4 promotion categories with monthly quotas, reminders, fines; influencer-to-influencer promotion;
referral-code grace/attribution safety; marketer-as-seller sale settlement. Each flow already has its own tests (see `backend/tests/Feature/Campaigns/`, ~140 tests green when last run).

## Left
1. Cross-panel lifecycle test suite (draft in `docs/plans/handoff/MarketerCampaignLifecycleTest.draft.php.txt` — unverified, 364 lines).
2. Full regression run of campaign-related suites and fixing real defects found.
3. Triage of pre-existing failures (names below) — decide env vs real bug; fix real ones.
4. Manual/browser QA of the three panels (nothing was clicked in a browser so far).
5. Live Meta WhatsApp check (needs real credentials; default driver only logs).

Pre-existing failures observed at the time of the first handoff (ALL FIXED since, see the triage table below; the 2026-10-14 full suite is green): `CommissionReconciliationTest::test_two_item_qty_gt_one…`, `CheckoutMoneySplitTest`,
`PanelSmokeV2Test` (2), `PayoutLedgerReconciliationTest` (2), `MarketerProfileAdPriceTest`, `MarketerSpecialtyDirectoryTest`, `CommissionsControllerTest` (Vite), `CheckoutFixesF13`, `PaymentMethodMatrix`.

## Test environment setup (fresh cloud container; skip anything already present)
```bash
apt-get update; apt-get install -y mariadb-server php8.3-bcmath
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
(nohup mysqld_safe --user=mysql >/tmp/mysqld.log 2>&1 &); sleep 8
mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'root'; CREATE DATABASE IF NOT EXISTS marketplace_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd backend && composer install --no-interaction   # if vendor/ is missing
# MariaDB lacks MySQL's ngram parser used in the schema dump. Patch LOCALLY and never commit it:
sed -i 's/ WITH PARSER `\?ngram`\?//g' database/schema/mysql-schema.sql
git update-index --skip-worktree database/schema/mysql-schema.sql
php artisan test --compact tests/Feature/Campaigns
```
phpunit.xml already points at mysql 127.0.0.1 `marketplace_test` root/root. Blade-rendering tests need `$this->withoutVite()` (no npm build).
When finished: `git update-index --no-skip-worktree backend/database/schema/mysql-schema.sql && git checkout -- backend/database/schema/mysql-schema.sql` before committing. Always `git add` explicit paths.
Conventions: money = BIGINT base units (no /100); UUID PKs; new migrations only; Arabic+English lang keys; `vendor/bin/pint --dirty --format agent`; commit trailer
`Co-Authored-By: Claude <noreply@anthropic.com>`.

## Prompts to paste (run in order, one per Claude session or sub-agent)

### H1 — Lifecycle suite
> Repo el-joe/marketplace_platforms (pull main). Read docs/plans/handoff/HANDOFF-marketer-campaigns.md and docs/plans/marketer-campaign-tab.md. Set up the test environment exactly as the handoff says. Take the draft docs/plans/handoff/MarketerCampaignLifecycleTest.draft.php.txt, move it to backend/tests/Feature/Campaigns/MarketerCampaignLifecycleTest.php and complete it as real HTTP/job/service chains across Partner (guard vendor), Admin (guard admin), Marketer (guard marketer) and the customer order API using the fake WhatsApp gateway and the fixtures in backend/tests/Support and backend/tests/Feature/Campaigns. Scenarios: (1) happy path: vendor creates a campaign from the listing tab (fee+VAT quoted, samples listed) -> admin approves -> invitation + WhatsApp -> influencer accepts via signed link -> fee settled (wallet and weekly-statement variants) -> admin dispatches sample (stock deducted once, 0-price statement line with commission) -> customer order with referral code -> conversion approved (admin cut %, VAT, net) -> vendor weekly statement shows influencer fee + sample line -> marketer wallet/withdrawal uses net; (2) reject and timeout -> replacement -> old code grace vs new code, sample re-pointed (2 influencers => 3 samples); (3) cancel (samples cancelled, campaign_enabled false); (4) peer promotion (influencer promotes another influencer's own-FBN listing, owner sees promoter, one-per-owner-per-month rule, owner settlement credit); (5) monthly quota: month close creates the fine once, reminder sent once. Assert the visible results on each panel's pages. Fix real defects in app code (never weaken tests) and explain each in the commit body. Commit by explicit paths on main and push.

### H2 — Regression run + pre-existing failures triage
> Repo el-joe/marketplace_platforms (pull main). Read docs/plans/handoff/HANDOFF-marketer-campaigns.md; set up the test env as described. Run all of: tests/Feature/Campaigns, MarketerPromotionFlowTest, MarketerSamplesLifecycleTest, MarketerCampaignSourcesTest, Marketer/MarketerLifecycleTest, MarketerAttributionConversionTest, Marketer/MarketerNetCommissionStatementTest, MarketerSaleSettlementTest, PromotionQuotaP12Test, MarketerPromotionQuotaPanelTest, PromotionCategoryAdminTest, Commission/VendorCampaignPricingTest, Cart/MarketerListingCartRetentionTest, Checkout tests. Then take every failing test listed under "Pre-existing failures" in the handoff, check out commit 893eaaad's parent (git stash/worktree) to see whether it already failed before the marketer work, classify each as environment (Vite/MariaDB) / stale test / real bug, fix real bugs and stale tests, and document environment ones. Report a table and push fixes to main.

### H3 — Browser QA of the three panels
> Repo el-joe/marketplace_platforms (pull main). Read docs/plans/marketer-campaign-tab.md. Run the Laravel app locally against the MariaDB test setup in docs/plans/handoff/HANDOFF-marketer-campaigns.md with seeded data (use existing seeders / MarketplaceScenario fixtures), build assets (npm ci && npm run build in backend/), and click through with Playwright (preinstalled Chromium): Partner `/listings/{id}` Marketer Campaign tab (list, create with fee/commission preview, view, edit, invite more, cancel, delete); Admin campaign page (approve/reject, samples dispatch, transfer report, WhatsApp failures/resend, code history), admin marketer-listings approve/reject, promotion settings/fines; Marketer panel (listings CRUD + stock + inventory pages, orders tabs, invitations, samples, finance breakdown, quota dashboard, peer promotion pages). Screenshot errors (500s, raw lang keys, broken layout RTL/LTR), fix them, push to main with a short report.

### H4 — Live WhatsApp check (only when credentials exist)
> Repo el-joe/marketplace_platforms (pull main). Read the P10 section of docs/plans/marketer-campaign-tab.md. Configure whatsapp_provider=meta with WHATSAPP_META_PHONE_NUMBER_ID / WHATSAPP_META_ACCESS_TOKEN, create an approved template for the first-contact invitation message (outside Meta's 24h window free text is rejected) and adapt MetaWhatsAppGateway to send a template message with the signed accept/reject URLs; send a test invitation to a real number, verify accept/reject links, delivery status handling and failure banner; document the config in docs/plans.

## Regression triage (H2 run, main after 2c7a6b45..)
Each failing test was re-run on 3295064f (before the marketer work): all failed there too, so none was introduced by the campaign work. After the fixes below, the H2 suites plus Checkout, Cart, Commission, Admin, Panels, Vendor, Orders, Payout/Refund tests are green (500 tests).

| Test | Class | Action |
|---|---|---|
| CommissionsControllerTest (all), CommissionsInPageEditingTest (8), Coupon/Exclusive/MarketerCategory admin tests, PanelSmokeTest (7), PanelSmokeV2Test (~20) 500s | environment (no Vite manifest) | `withoutVite()` in tests/TestCase.php setUp; only the asset lookup is stubbed, so real 500s still surface (all other panel/admin tests kept their results) |
| MarketerProfileAdPriceTest (2), MarketerSpecialtyDirectoryTest (2 of 3), PanelSmokeV2 marketer_dashboard, CouponParticipationInvitationTest (2) | stale test | fixtures lacked `onboarding_completed_at` (MarketerAuth onboarding gate) |
| MarketerSpecialtyDirectoryTest directory/API | stale test | directory `?type=` filters by marketerJobs; fixture now attaches a MarketerJob |
| PaymentMethodMatrix wallet-full, CheckoutFixesF13 wallet, PayoutLedgerReconciliation (2) | real pre-existing bug, fixed | CheckoutController: `$order->total - $walletAmountToUse === 0` never true because orders.total is a decimal string; every wallet-only order was cancelled/rolled back. Now numeric compare |
| PaymentMethodMatrix partial wallet, CheckoutMoneySplitTest (coupon) | stale test | strict assertSame on decimal(19,4) strings; use assertEquals |
| CouponShippingTypePersistenceTest | real pre-existing bug, fixed | null `shipping_type_restriction` hit a NOT NULL column (500); admin/vendor CouponService normalise to 'all' |
| CommissionReconciliationTest qty>1 | stale test | formula ignored commission_vat; fixture mixed fbm + express_fbn (breakdown uses FBN pair, charge uses FBP pair, pinned by CommissionSnapshotInvariantTest) |

Known and left alone: the order-item rate/fixed display snapshot can disagree with commission_amount when a listing's global_system_type and fulfillment_model differ (deliberately characterised by CommissionSnapshotInvariantTest; also feeds sample-line commission via CheckoutCalculationService). Changing it is a product decision.
