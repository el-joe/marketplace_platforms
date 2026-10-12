# Handoff — Marketer campaigns & marketer-as-seller (what is done, what is left)

Repo: `el-joe/marketplace_platforms`, branch `main`, Laravel app in `backend/`.
Read first: `docs/plans/marketer-campaign-tab.md` (requirements R1-R12, plan, FINAL gap report in section 5).
All feature work is implemented and pushed. **What remains is test coverage, verification and QA.**

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

Pre-existing failures observed (fail with and without the campaign work): `CommissionReconciliationTest::test_two_item_qty_gt_one…`, `CheckoutMoneySplitTest`,
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
