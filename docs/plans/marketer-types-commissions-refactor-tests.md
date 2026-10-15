# Marketer Types, Commissions & Fees Refactor (v3) - Tests, Run Guide and QA

Phase R10. Companion to `marketer-types-commissions-refactor.md` (sections 1.3 and 9).
**Status: tests are written but have NOT been run** (owner rule: `php -l` only). Expect a first run to surface a few
fixture mistakes; the lists below say what each file asserts so a failure can be judged against intent.

---

## 1. How to run

All commands from `backend/`. The suite uses MariaDB/MySQL, not SQLite (`phpunit.xml`: `mysql`, `127.0.0.1:3306`,
database `marketplace_test`, user/pass `root`/`root`).

### 1.1 Test DB setup
```bash
mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS marketplace_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan config:clear
```
- `RefreshDatabase` runs every migration on `marketplace_test` the first time, including all `2026_10_16_*` files
  (broker job seed, `marketer_default_commissions`, conversions/invitations/campaign columns, classified/travel columns,
  `marketing_fee_vertical_settings`, offer-table drop, and the destructive `900001`). Do **not** rely on
  `database/schema/mysql-schema.sql`: it is stale (pre-v3) - see risks.
- If the test DB was migrated before v3, rebuild it: `php artisan migrate:fresh --env=testing` is destructive, so only run it
  against `marketplace_test`, never against staging or production.

### 1.2 Commands
```bash
# whole suite
php artisan test --compact            # or: vendor/bin/phpunit

# v3 groups (filters are class-name regexes)
vendor/bin/phpunit --filter 'MarketerCommissionCalculatorTest|CommissionFeeSplitTest|CommissionFeeServiceTest|MarketingFeeServiceTest'   # calculator + fees (R5)
vendor/bin/phpunit --filter 'MarketerSettingsPageTest|MarketerSettingsOverridesTest|MarketerCategoryCommissionStoreTest|AdminMarketingFeesPageTest'   # admin settings / profile (R3, R4)
vendor/bin/phpunit --filter 'CampaignSaleRecorderTest|MarketerCampaignV3LifecycleTest|MarketerCampaignOpenToAffiliatesTest|MarketerAttributionConversionTest'   # product flow (R6)
vendor/bin/phpunit --filter 'ClassifiedMarkSoldCampaignTest|ClassifiedVendorCampaignWizardTest'   # classified (R7)
vendor/bin/phpunit --filter 'TravelCampaignCommissionTest|TravelAgencyCampaignPagesTest'   # travel (R8)
vendor/bin/phpunit --filter 'MarketingFeeReportServiceV3Test|V3LegacyRemovalMigrationTest'   # reports + migration (R9)

# regression neighbours that were touched or share fixtures
vendor/bin/phpunit tests/Feature/Marketer tests/Feature/Campaigns tests/Feature/Commission tests/Feature/Travel tests/Feature/Reports
```
Single file: `vendor/bin/phpunit tests/Feature/Campaigns/CampaignSaleRecorderTest.php`.

### 1.3 Before any manual QA / staging rollout
1. Take a full DB backup (**mandatory before `2026_10_16_900001`**, it drops tables and columns permanently).
2. On **staging MySQL first**: `php artisan migrate --pretend` (review), then `php artisan migrate`. Confirm
   `storage/app/v3-removal/*.json` snapshots were written when legacy rows existed, and archive them.
3. Run the v3 groups above against the migrated staging copy if a staging test DB is available.
4. `npm ci && npm run build` (see risks: `resources/js/partner/classifieds.js` is not in the built manifest).

---

## 2. Test files

### 2.1 New
| File | Covers |
|---|---|
| `tests/Support/MarketerV3Fixtures.php` | Builders: default settings rows, profile rules, brokers, product/classified/travel campaigns (create -> approve -> accept), classified listing, travel agency/member/package/booking. |
| `tests/Unit/Services/Marketer/CommissionFeeSplitTest.php` | Pure `CommissionFeeService::split`: pct, fixed, both, cap at gross, never negative, plan examples 11 / 10 / 8.5. |
| `tests/Feature/Marketer/MarketerCommissionCalculatorTest.php` | Section 9 numbers: influencer 200 -> fee 11 -> net 189 (same for 3 or 300 units); profile override 300; invitation snapshot beats later default change; affiliate 8% x 1,000 -> 80/10/70 (fixed part ignored); broker 15 + 5% -> 65/8.5/56.5, fixed x quantity, fixed-only / pct-only; broker beats affiliate; category rule beats vertical rule; profile fee override per field; net never negative; `withGross` (budget cap). |
| `tests/Feature/Marketer/CommissionFeeServiceTest.php` | Fee resolution profile-category -> profile-vertical -> default, field by field, explicit 0 is an override, no leakage across verticals, cap; `MarketerDefaultCommissionResolver` category -> vertical -> none, explicit-0 category row, per-country, null country. |
| `tests/Feature/Campaigns/CampaignSaleRecorderTest.php` | Snapshot of sale/gross/fee/net; idempotency per source; budget exhaustion -> `done` and no more conversions; last commission shortened; influencer gets no per-sale conversion; paused campaign records nothing; reversal frees budget, idempotent, paid conversion untouched, finished campaign not reopened. (Run on a trip campaign: cheap sources, many conversions.) |
| `tests/Feature/Campaigns/MarketerCampaignV3LifecycleTest.php` | Influencer amount reserved from budget at creation and frozen; over-budget creation refused; completion pays net of fee once (fee 11, net 189, wallet 189, `influencers_settled_at`); reject invitation / reject campaign / cancel release without paying; `checkStockAndUpdateStatus` never resumes a budget-exhausted or owner-paused campaign and leaves `done` alone. |
| `tests/Feature/Campaigns/ClassifiedMarkSoldCampaignTest.php` | Price required while a campaign is live; marketer must belong to the campaign; sale saves `sold_price/sold_at/sold_via_campaign_id`, conversion 10% of 5,000 = 500 / fee 52 / net 448, campaign `done`; no marketer -> no conversion; no campaign -> no price needed; unmark reverses and clears; paid commission blocks unmark; approval after the classified clearing days. |
| `tests/Feature/Campaigns/ClassifiedVendorCampaignWizardTest.php` | Classified vendor opens wizard and index; foreign / inactive listing -> 403; store with "open to all affiliates and brokers" reaches both; over-budget influencer rejected; mark-sold / unmark-sold HTTP endpoints. |
| `tests/Feature/Travel/TravelCampaignCommissionTest.php` | Pending booking earns nothing, confirm creates conversion (100 / 12 / 88); unattributed or foreign-marketer booking earns nothing; approval only after `completed_at` (nightly job), wallet pending = net; `CompleteTravelBookingsJob` only completes confirmed bookings of finished trips; cancellation reverses (also after approval). |
| `tests/Feature/Travel/TravelAgencyCampaignPagesTest.php` | Guest redirect; owner opens index/create/create-trip/show; member without `campaigns.view` turned away; view != create/manage; cross-agency isolation (404); quote `fits_budget`; store creates agency-owned campaign (no vendor) reserving the influencer amount; over-budget refused; cancel releases. |
| `tests/Feature/Admin/MarketerSettingsOverridesTest.php` | Per-category influencer override save/update/delete, validation, edit permission; classified/travel campaign-fee (`update-vertical`) save and product vertical rejected. |
| `tests/Feature/Reports/MarketingFeeReportServiceV3Test.php` | Report totals from snapshots: campaign fee 30 + 15 = 45, commissions by type/state (affiliate pending/approved/reversed 100 each, fee 12, net 88; influencer 100/12/88), total row 200 / 24 / 176, filters, empty range, campaign-fee report subtotal/collected. |
| `tests/Feature/Commission/V3LegacyRemovalMigrationTest.php` | Legacy tables/columns gone after migrate; v3 structures + broker job present; migration `900001` re-run twice is a no-op, removes only the stale settings keys. |

### 2.2 Already present from earlier phases (kept, still part of the v3 set)
`tests/Feature/Admin/MarketerSettingsPageTest.php` (four tabs, influencer defaults, commission fees, general settings,
resolver, fee service), `tests/Feature/Admin/MarketerCategoryCommissionStoreTest.php` (type constraints: influencer rejects %,
affiliate rejects fixed, broker any, fee override, type-not-held, profile heading), `tests/Feature/AdminMarketingFeesPageTest.php`.

### 2.3 Changed
| File | Change |
|---|---|
| `tests/Feature/MarketingFeeServiceTest.php` | Rewritten: fixed + variable, each amount/%/both; plan example 500 -> 20 + 10 = 30; ancestor fallback; per fulfilment type; classified/travel vertical fee. |
| `tests/Feature/Campaigns/MarketerCampaignOpenToAffiliatesTest.php` | Wizard v3: budget in every payload; removed fee/VAT preview and category-tab quote tests; added cents budget, influencer snapshot + reserve + over-budget, quote `fits_budget`. |
| `tests/Feature/MarketerAttributionConversionTest.php` | Wallet amounts = net; exhausted budget now ends `done` with a capped last commission (was auto-pause); added fee/net snapshot, idempotent attribution, influencer-gets-no-conversion. |
| `tests/Feature/Marketer/MarketerLifecycleTest.php` | Registered affiliate gets a 10% profile rule (campaign amount no longer exists); wallet compared to net. |
| `tests/Support/MarketplaceScenario.php` | Scenario affiliate gets a 10% products profile rule (otherwise his conversions are 0). |
| `tests/Feature/Campaigns/PeerPromotionServiceTest.php` | Removed the removed-model setup; test renamed `..._and_zero_without_one`. |
| `tests/Feature/MarketerPromotionFlowTest.php` | Removed the two influencer-fee collection tests and their imports. |
| `tests/Unit/CommissionDiscountTest.php` | Removed the marketer discount test (enum deleted); vendor discount test kept. |

### 2.4 Deleted (feature removed in v3, nothing to port)
`AffiliateFeeAccrualAndSettlementTest`, `AffiliateCommissionResolverTest`, `CampaignInfluencerFeePanelsTest`,
`CampaignInfluencerFeePayoutTest`, `CampaignInfluencerFeeServiceTest`, `InfluencerChargeAndMarketerCreditTest`,
`CancellationRefundSymmetryTest`, `MarketingFeeQuoteSnapshotTest`, `MarketerCampaignDefaultCommissionTest`,
`MarketerPerSaleCommissionTest`, `MarketingFeesEndToEndTest` (all `tests/Feature/Campaigns`); `CommissionRuleResolverTest`,
`FlashSaleBonusModeTest`, `LegacyMarketerCommissionAmountsMigrationTest`, `VendorCampaignPricingTest` (`tests/Feature/Commission`);
`MarketerFeeShareResolverTest` (`tests/Feature`); `MarketingFeeFinancialReportTest`, `MarketingFeePanelReportsTest`
(`tests/Feature/Reports`) and `tests/Support/MarketingFeeReportCase.php`.
Replaced by the new files above:
`ConversionStatementLedgerTest` + `MarketerNetCommissionStatementTest` -> calculator/fee/recorder/lifecycle tests;
`MarketerCampaignLifecycleTest` -> `MarketerCampaignV3LifecycleTest`; `MarketingFeesEndToEndTest` -> recorder + report tests.

---

## 3. Expected results

- Every file above should pass on a clean `marketplace_test` migrated with all migrations. Section 9 figures to expect:
  influencer 200 / 11 / 189; affiliate 80 / 10 / 70; broker 65 / 8.5 / 56.5; campaign fee 20 + 10 = 30.
- No test should reference the removed names: `grep -rnE "CommissionRuleResolver|AffiliateCommissionResolver|MarketerFeeShareResolver|MarketerFeePayoutService|MarketerCommissionCountrySetting|MarketerInfluencerFee|platform_cut|update-fee" tests` returns nothing except
  the removal-migration test, which names dropped columns on purpose.
- First-run failures are most likely fixture details, not logic: the travel/classified fixtures
  (`MarketerV3Fixtures`) were written from model fillables without running; wallet money columns are decimal (use
  `assertEquals`); `Notification::fake()`/`Queue::fake()` are expected in campaign tests.
- Sanity check of the "no old money model" invariant: `marketer_campaign_conversions.commission_fee_amount` and
  `net_commission_amount` are populated on every conversion created through `CampaignSaleRecorder`.

---

## 4. Manual QA checklist (per panel)

### Admin: Marketer Settings and marketer profile
- [ ] Marketer Settings has four tabs (influencer defaults, commission fees, campaign fees, general); each saves and flashes; a view-only admin sees them read-only and gets 403 on save.
- [ ] Influencer grid: fixed amount per country x product/classified/travel; blank leaves no row; per-category override add/edit/delete.
- [ ] Commission fees: pct + fixed per country x vertical; live example text updates.
- [ ] Campaign fees: country fixed (amount/%), product variable by category x FBM/FBN/FBP, classified/travel variable per vertical.
- [ ] Marketer profile (influencer): only the fixed input is enabled, percentage disabled with tooltip; saving a % is rejected server-side.
- [ ] Marketer profile (affiliate): only % enabled; (broker): fixed, %, both; per-row commission-fee override and the "if empty -> default" preview; heading and currency correct.
- [ ] Category edit "Marketers" tab no longer has influencer share % or affiliate fields.
- [ ] Marketer registration/onboarding offers Broker; existing brokers were moved to the broker type.

### Partner: product vendor
- [ ] Campaign wizard: influencer multi-select (same country) shows each influencer's fixed amount; "Open to all registered affiliates & brokers" checkbox; Max Commission Budget accepts cents.
- [ ] Live cost summary: campaign fee fixed + variable, influencer total reserved, remaining for affiliates/brokers, grand total; over-budget influencers block submit with the message.
- [ ] Created campaign: budget meter shows reserved + spent; fee charged to the vendor wallet on approval (or pending on the next payout statement as a `campaign_fee` line).
- [ ] Affiliate/broker sale creates a pending conversion with gross/fee/net; when the budget hits the max the campaign turns `done`, listing no longer promoted; restocking does not reopen it.
- [ ] Cancelling or admin-rejecting returns reserved influencer money; completing pays influencers net of the fee.
- [ ] Order cancellation/return reverses the conversion and frees the budget.

### Partner: classified vendor
- [ ] Classified vendor sees Marketer Campaigns; wizard opens only for own, active listings; classified wizard checkbox now links to the real campaign page.
- [ ] Mark sold with a live campaign requires the sale price and offers the marketers of the campaign (with inquiry counts); without a campaign price stays optional.
- [ ] After marking sold: conversion = % of sold price, campaign `done`, influencers paid; Unmark sold reverses it (blocked once the commission is paid out).
- [ ] Referral link on `/classified/{slug}` keeps the campaign (check the Next.js route passes `ref`).

### Travel agency
- [ ] "Campaigns" menu visible to owner and members with `campaigns.view`; create/manage need `campaigns.create` / `campaigns.manage`; another agency's campaign id is a 404.
- [ ] Trip wizard identical to partner (influencers, open checkbox, budget, cost summary with wallet balance); campaign fee debited from the agency wallet (retried nightly if short).
- [ ] Booking via a referral link carries marketer/campaign/referral code; confirming it creates the pending conversion; cancelling reverses it.
- [ ] After the trip's return/departure date the nightly job completes the booking; only then the commission is approved.
- [ ] The old "campaign offer" pages and routes are gone (404).

### Marketer panel
- [ ] Campaign cards/details show "your commission" per type (fixed / % / both), expected net after the platform fee, and budget-closed notices.
- [ ] Earnings and wallet show gross, platform fee, net per conversion; pending -> approved -> released flow; influencer fixed amount appears once at completion (net).
- [ ] Open campaigns can be joined by affiliates/brokers, never by influencers.

### Finance, reports and withdrawals
- [ ] Marketing Fees report tab: rows by country + currency + vertical + marketer type, total row; currencies never blended; CSV export matches the screen.
- [ ] Reports reflect campaign fees earned/pending, commissions pending/approved/reversed, commission fees earned, net paid.
- [ ] Vendor finance page / payout statement: `campaign_fee` and `campaign_budget_commission` lines; agency totals via the report service.
- [ ] Wallet source labels: `marketer_commission`, `marketer_commission_fee`, `marketer_campaign_fixed`; marketer withdrawal request still works end to end.
- [ ] Both Arabic and English render all new strings.

---

## 5. Known risks and owner decisions

1. **Unbuilt JS**: `resources/js/partner/classifieds.js` (mark-sold modal with price/marketer) is not in the built Vite manifest. Run `npm run build` (tests use `withoutVite()`, so they will not catch this).
2. **Stale schema dump**: `database/schema/mysql-schema.sql` predates v3. Laravel loads it first on a fresh DB and then runs only newer migrations, so a fresh test DB may start from pre-v3 columns and still reach v3 through migrations - but regenerate it (`php artisan schema:dump`, **without `--prune`**) once v3 is stable, and never trust it as documentation.
3. **Irreversible drops**: `900001` has a no-op `down()`. JSON snapshots of removed money/fee rows are written to `storage/app/v3-removal/` (git-ignored; copy them off the server). Back up the database first and run on staging MySQL before production.
4. **Removed `promotion:*` commands** (promotion targets, fines, reminders, and the vendor promotion expiry that rode on them): removed per the "not in spec" rule (plan Q14). **Owner decision**: confirm that vendor promotion expiry is meant to be gone; if not, restore it as a separate non-commission feature.
5. **Peer promotion (influencer promotes another influencer's listing) probably yields no commission**: `CampaignSaleRecorder` skips every conversion whose marketer resolves to the influencer type, and a peer promoter is an influencer. `PeerPromotionConversionTest` (kept unchanged) is expected to fail until the owner decides: keep peer commissions (needs a peer exception in the recorder, or promoters acting as affiliates) or retire the peer feature. Not fixed in R10 (test phase).
6. **Remaining legacy tests not re-verified**: suites that use `CampaignSampleFixtures::campaign()` (samples, referral-code, WhatsApp, sale-settlement tests) were checked only for references to removed classes/columns/routes, not for behaviour changes (for example campaign fee charging at approval).
7. **Money precision**: conversions, wallets and invitations store decimals; two tests compare floats with `assertEquals`. If a wallet column is still integer minor units anywhere, those assertions need `assertEqualsWithDelta`.
8. **Legacy markers still present on purpose** (not bugs): `marketer_campaigns.commission_type` / `marketer_commission_amount` (peer-promotion pricing), invitations' pre-v3 `platform_fee_*` history, `payout_items.marketing_fee_amount`, `marketer_sale_settlements` (Q13).

## Test run result (2026-10-15)
Full suite run on MariaDB `marketplace_test`: **841 tests, 838 passed, 3 skipped, 0 failures** (5077 assertions).
Fixes made during the run: enum-to-string bugs, optional `marketer_classified_approval_days`, peer promotion commissions kept (per-sale for peer campaigns), restored `promotion:expire-items` (vendor promotion expiry). Fines/minimums/penalties stay removed. No migration files were deleted; the schema dump was not regenerated.
