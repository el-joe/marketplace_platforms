# Marketing Fees + Marketer Payout Flow — Plan & Execution Prompts (v2)

Status: **PLAN ONLY — nothing here is built.** Decisions D1–D12 are settled below (client answers applied). Run the prompts in section 9 one at a time, in order.
Date: 2026-10-14. Stack: Laravel 11 (`backend/`), UUID PKs, money = `DECIMAL(19,4)` base-currency numbers (`App\Support\Money`, `DecimalMoney` cast), new migrations only, Blade + Alpine, ar + en lang keys for every string.

---

## 1. Business rules

### 1.1 Vendor pays the platform — INFLUENCER campaigns (money IN, charged when each influencer **accepts**, as today)
```
influencer_fee   = marketer settings fee per influencer (per country)                   e.g. 9.00 AED
campaign_fees    = influencer_fee x influencer_count                                    18.00
vat              = campaign_fees x country vat_rate                                     18 x 5% = 0.90
subtotal         = campaign_fees + vat                                                  18.90   ("total amount of campaign")

fixed_fee        = fixed_amount(country) + subtotal x fixed_percentage(country)/100
variable_fee     = amount(country, category, type) + subtotal x percentage(country, category, type)/100
marketing_fees   = fixed_fee + variable_fee
grand_total      = subtotal + marketing_fees                                            what the vendor is charged
```
UAE example (fixed 1.9 AED + 0 %, variable 0 AED + 10 %): fixed 1.90, variable 1.89, marketing fees **3.79**, grand total **22.69**.

### 1.2 Platform pays the INFLUENCER (money OUT, D1/D2)
Category edit page -> tab **Marketers** -> per country, **Influencer fee share %** (replaces the old flat amount):
```
marketer_payout = influencer_fee x influencer_fee_share_pct / 100     9 x 20 % = 1.80  -> marketer wallet (withdrawable)
platform_keeps  = influencer_fee - marketer_payout                    7.20 (80 %)
```
Credited when the vendor's charge for that invitation is `paid`. Marketing fees and VAT are not shared (VAT = tax liability).

### 1.3 AFFILIATE campaigns (D3, D4, D6) — a different mechanism
- Affiliates pay **no upfront fee** (invitation fee stays 0, no accept-time debit).
- An affiliate earns commission **per sold unit** = a percentage of the listing line amount, a flat amount per unit, or both:
  `commission = line_total x rate/100 + flat x qty` (same shape as `MarketerCategoryCommission`: mode `fixed|percentage|both`).
- **Which rate applies (in order):** (1) the marketer's own rule in the admin marketer profile card **"Commission Rates by Category"** for the campaign listing's category (ancestor fallback); (2) otherwise the **affiliate default** on the category edit page, tab Marketers (per country: mode/percentage/flat, ancestor fallback); (3) else 0.
- **Marketing fees on affiliate campaigns** are computed on the *accrued affiliate commission* of the campaign (same fixed + variable rules as 1.1, same type mapping) and **increase after every successful order** that includes the campaign listing. Fixed amount is accrued once (on the first accrued order); percentage parts accrue on each order's commission.
- A per-order **accrual history** is kept (order, qty, commission, marketing-fee delta, running totals) and shown on the campaign page in the **vendor, marketer and admin** panels.
- **Debit moment:** the vendor is charged the accumulated marketing fees when the **campaign finishes OR reaches its max commission budget** (not per order). The affiliate's commission itself keeps the existing per-order path (see 3, D10).

### 1.4 Fulfillment types (D8)
The three billing types are shown with both names everywhere (admin page, report, vendor form):
| Type | Label | Listing `fulfillment_model` |
|---|---|---|
| FBN | **FBN (Fulfilled by Nawy)** | `fbn` |
| FBM | **FBM (cross_dock)** | `cross_dock` |
| FBP | **FBP (fbm)** | `fbm` (and any unknown value) |
The enum has no `fbp` value; `cross_dock` is the third real value and the client defines it as FBM. The remaining `fbm` value is billed as FBP. `marketer_campaign_allowed_fulfilment_models` currently defaults to `['fbn','fbm']`, so **cross_dock listings cannot start campaigns today**: add `cross_dock` to the default (admin setting) so the FBM type exists. Keep the label constants in ONE place (`MarketingFeeType` enum with `label()`), reused by every view.

---

## 2. Decisions (settled)

| # | Decision | Impact |
|---|---|---|
| D1 | Marketer credit = `influencer_fee x share %` (Part 2 governs). | New credit flow (P5). |
| D2 | Replace the Marketers-tab flat amounts with **percentages**: `influencer_fee_share_pct` (share of the 9 AED fee). **Remove the old amount columns everywhere** (`influencer_commission_amount`, `affiliate_commission_amount`). | Migration drops both columns after data copy; all readers rewritten (section 4.1, removal checklist 7). |
| D3 | Affiliate commission = % and/or amount on the listing line per qty sold; marketer-profile "Commission Rates by Category" first, else category-tab affiliate default. | New affiliate default fields replace `affiliate_commission_amount`: `affiliate_commission_mode`, `affiliate_commission_rate`, `affiliate_commission_flat`. |
| D4 | Influencer debited on accept (unchanged). Affiliate: calculated on each successful order, debited when campaign **finishes or hits max limit**; history on the campaign page in vendor / marketer / admin panels. | New accrual table + settlement job + 3 history views (P6). |
| D5 | No VAT on marketing fees. | — |
| D6 | Affiliate marketing fees follow D3 + D4 and increase after every successful order including the campaign listing. | Accrual ledger (4.1). |
| D7 | Vendor always pays the influencer fee (setting `marketer_influencer_fee_payer = marketer` is ignored by this flow). | Remove the marketer-payer branch from campaign acceptance. |
| D8 | FBM = `cross_dock`; one combined title "FBM (cross_dock)". | Section 1.4. |
| D9 | Reuse `marketer_commission_settings.view` (page) / `marketer_fee_settings.edit` (writes). | No new permissions. |
| **D10** | *(derived — confirm)* Sale commission of an affiliate order is **already deducted from the vendor payout per order** (`CheckoutPricingEngine` vendor-owned `marketer_commission` -> `vendor_payout`; credited to the marketer ledger after approval and the platform cut in `MarketerCommissionStatementService`). To avoid double charging, the "debit at finish/limit" applies to the **marketing fees accrued on affiliate commission**, not to the commission itself. | If you actually want the commission itself charged to the vendor only at the end, say so: it requires removing the per-order vendor deduction (risky; separate prompt). |
| **D11** | *(derived — confirm)* After D2, **influencers have no per-sale default commission**; they earn the fee share. Per-sale commission for an influencer only exists if the admin set a rule in that marketer's "Commission Rates by Category". | Peer/other flows reading `influencer_commission_amount` fall back to the profile rule (else 0). |
| **D12** | *(derived — confirm)* Peer promotions (influencer promotes another influencer's FBN listing) read their per-unit commission from `influencer_commission_amount` today. After the removal they use the promoter's profile rule (D11), else 0, and the campaign shows a "no commission configured" warning at creation. | `resolvePeerCommission` rewritten (P3). |

---

## 3. Current behaviour (verified in code)

| Area | What exists today | Where |
|---|---|---|
| Influencer fee settings | `marketer_influencer_fee_country_settings.fee_per_influencer` per country | `Admin\MarketerSettingsController::updateInfluencerFee`, `MarketerInfluencerFeeCountrySetting` |
| VAT | `countries.vat_rate`, `vatOn()` (half up, 2 dp) | `MarketerCampaignQuoteService` |
| Quote | `quote($listing, $ids)` -> fee, VAT, total_fee_with_vat, per-row commission estimate (uses the amount columns), samples, stock | `MarketerCampaignQuoteService`; endpoint `partner.listings.marketer-fee` (`Partner\ListingController::getInfluencerFee`); form `partner/marketer_campaigns/create.blade.php` |
| Campaign create | no fee snapshot; default commission from `resolveDefaultCommission` (amount columns) | `MarketerCampaignService::createCampaign` |
| Influencer fee charge | on accept: `platform_fee_*` on the invitation; wallet debit else weekly statement (`GenerateVendorPayoutsJob`, `PayoutCalculationService::selectInfluencerFees`), retry `CollectPendingInfluencerFeesJob` | services/jobs above |
| `platformFeeTotal()` | fee + VAT; used by wallet debit, statements, `TransactionFeedService`, `Partner\FinanceController` | `MarketerCampaignInvitation` |
| Influencer is never credited for the fee | fee is platform revenue only | — |
| Per-sale commission (all marketers) | `LastClickAttributionService`: campaign amount x qty -> `marketer_commission_country_settings` amount (by marketer type) -> marketer profile rule (`MarketerCommissionRateService` / `MarketerCategoryCommission`, mode fixed/percentage/both) | `LastClickAttributionService` ~L170-205 |
| Who pays the sale commission | vendor-owned marketer commission is deducted in `vendor_payout` per sub-order; conversion approved after delivery (`ApproveMarketerConversionsJob`), platform cut/VAT/net in `MarketerCommissionStatementService`, credited to the marketer wallet; reversal on refunds `MarketerConversionReversalService` | `CheckoutPricingEngine`, `PayoutCalculationService`, jobs/services |
| Campaign cap | `max_commission_budget` vs `commission_budget_spent`; campaign **auto-pauses** when exhausted (`LastClickAttributionService` ~L254-265) | — |
| Readers of the amount columns (to rewrite/remove) | `Admin\CategoryController` (4), `Partner\ListingController` (4), `MarketerCommissionCountrySetting` (3), `LastClickAttributionService` (3), `MarketerCampaignQuoteService` (1), `MarketerCampaignService` (4), `admin/categories/_form.blade.php` (4), `admin/marketer_campaigns/show.blade.php` (2), tests `MarketerCampaignOpenToAffiliatesTest` (2), `tests/Support/PeerPromotionFixtures.php` (1) | grep `influencer_commission_amount|affiliate_commission_amount` |
| Admin marketer profile | "Commission Rates by Category" card (`marketers.commission_by_category`) editing `marketer_category_commissions` | `admin/marketers/show.blade.php`, `Admin\MarketerController` |
| Finance reports | `FinancialReportService` / `Admin\FinancialReportController` (revenue, commission, gateway fee, VAT, ads, net); no marketing-fee or marketer-fee line | — |
| Marketer panel | `marketer/reports/index`, `marketer/finance/{wallet,commissions}`, `Marketer\FinanceController`, `Api\Marketer\FinanceController` | — |
| Nav | Admin sidebar "Marketing" group in `NavigationService` (~L391) | — |

---

## 4. Target design

### 4.1 Data model (new migrations only; existing migrations untouched)
1. `marketing_fee_country_settings`: `country_id` unique, `fixed_amount`, `fixed_percentage`, `updated_by_admin_id`.
2. `marketing_fee_category_settings`: `country_id`, `category_id`, `fulfillment_type` (`fbm|fbn|fbp`), `fixed_amount`, `percentage`; unique(country, category, type); ancestor lookup.
3. `marketer_commission_country_settings` (category x country; the category-edit "Marketers" tab):
   - ADD `influencer_fee_share_pct` DECIMAL(7,4) default 0; `affiliate_commission_mode` (`fixed|percentage|both`), `affiliate_commission_rate` DECIMAL(7,4), `affiliate_commission_flat` DECIMAL(19,4).
   - DATA STEP: copy old `affiliate_commission_amount > 0` into `affiliate_commission_flat` with mode `fixed` (no data loss); old `influencer_commission_amount` has no equivalent and is dropped (log the dropped values in the migration output / a `*_legacy_dump` JSON file under `storage/app` so finance can review).
   - DROP `influencer_commission_amount`, `affiliate_commission_amount` (D2) in a LATER migration in the same prompt, after the code no longer reads them.
4. `marketer_campaigns` ADD `fulfillment_type`, `campaign_subtotal`, `marketing_fee_fixed`, `marketing_fee_variable`, `marketing_fee_total`, `campaign_grand_total` (influencer snapshot at creation; never recomputed), `affiliate_fee_accrued`, `affiliate_fee_settled_at`, `marketing_fee_fixed_accrued` (bool/amount, fixed charged once).
5. `marketer_campaign_invitations` ADD `platform_fee_marketing_amount`, `marketer_fee_share_pct`, `marketer_payout_amount`, `marketer_credited_at`, `marketer_payout_wallet_tx_id`.
6. **`marketer_campaign_fee_accruals`** (affiliate history, D4/D6): `campaign_id`, `invitation_id`, `conversion_id` (unique), `order_id`, `order_item_id`, `quantity`, `line_total`, `commission_base` (affiliate commission of the order item), `marketing_fixed_delta`, `marketing_variable_delta`, `marketing_total_delta`, `running_base`, `running_marketing_total`, `status` (`accrued|settled|reversed`), `settled_at`, `created_at`. Reversed rows when the conversion is reversed on refund.

### 4.2 Services
- `MarketingFeeType` enum (fbm|fbn|fbp) with `label()` ("FBM (cross_dock)", "FBN (Fulfilled by Nawy)", "FBP (fbm)") and `fromListingModel()`.
- `MarketingFeeService::calculate($subtotal, $countryId, $categoryId, $type, bool $applyFixed = true)` pure/rounded half-up 2 dp; zero subtotal -> 0.
- `MarketerFeeShareResolver` (influencer share %, ancestor fallback) and `AffiliateCommissionResolver::forConversion($marketer, $campaign, $categoryId, $lineTotal, $qty)` implementing 1.3 order (profile rule -> category default -> 0) and `FlashSale` bonus untouched.
- `MarketerCampaignQuoteService::quote()` returns `fulfillment_type(+label)`, `marketing{fixed_fee,variable_fee,total}`, `campaign_subtotal`, `campaign_grand_total`, per-row `marketer_payout`/`platform_keeps`, and for affiliates the commission estimate **per unit** from the new resolver. No client-side math.
- `AffiliateFeeAccrualService::accrue(MarketerCampaignConversion $c)` (called when the conversion is approved/credited, idempotent on `conversion_id`), `reverse($c)`, `settle(MarketerCampaign $campaign)`.
- `AffiliateFeeSettlementJob` (scheduled) + immediate trigger from the campaign status transitions: when a campaign becomes `completed/finished/expired/cancelled-after-sales` OR is auto-paused because the commission budget is exhausted, debit the vendor (wallet first, else weekly statement line `affiliate_marketing_fee`, same machinery as influencer fees) and mark accruals `settled`.
- `MarketerFeePayoutService::creditForInvitation()` (influencer share), hooked into BOTH paid paths (wallet + statement); idempotent via `marketer_credited_at`.

### 4.3 Influencer per-invitation allocation (accept-time, D4)
```
inv_subtotal = platform_fee_amount + platform_fee_vat_amount
inv_fixed    = fixed_amount/N  + inv_subtotal x fixed_pct/100
inv_variable = variable_amount/N + inv_subtotal x variable_pct/100
platform_fee_marketing_amount = round(inv_fixed + inv_variable, 2)      (last accepted invitation gets the rounding remainder)
platformFeeTotal() = fee + vat + marketing
```
All N accept => sum equals the campaign quote exactly. Decline/timeout => nothing charged. `platformFeeTotal()` is shared by wallet debit, statements, vendor feed, vendor finance, so they charge the grand total automatically; statement/feed lines add a separate "marketing fees" figure.

### 4.4 Affiliate accrual on each successful order (D4/D6)
On conversion approval (order delivered/completed — same event as `ApproveMarketerConversionsJob`), for conversion `c` of campaign `K`:
```
base   = c.commission_amount                                  (affiliate commission for that order item, D3 resolver)
fixed  = (K has no accrual yet) ? fixed_amount(country) : 0
var    = variable_amount(country, category, type) [first order only] + base x variable_pct/100
pctFix = base x fixed_pct/100
delta  = fixed + pctFix + var
```
Insert an accrual row (running totals), `K.affiliate_fee_accrued += delta`. Refund/reversal -> `reverse()` writes a negative/`reversed` row and reduces the running total (fixed amounts stay). Settlement (finish/limit) debits `K.affiliate_fee_accrued` once.

### 4.5 Admin page "Marketing Fees" (Marketing group in the sidebar)
Route `admin.marketing-fees.*`, permissions per D9.
- **Fixed fees** tab (per country): amount + percentage.
- **Variable fees** tab: country x category x type (FBM (cross_dock) / FBN / FBP) with amount + percentage, category tree select, "copy to all types".
- **Report** tab: per country/currency and per **type** — campaigns, influencers, subtotal, fixed, variable, marketing fees, grand total, collected vs pending; affiliate accruals (accrued vs settled); CSV export.
- Live calculator widget (subtotal + country + category + type) to verify the formula, with an influencer/affiliate toggle.

### 4.6 Where it must appear
| Panel | Change |
|---|---|
| Vendor create form | Breakdown (influencer fees, VAT, subtotal, fixed, variable, **marketing fees**, **grand total**, type label); affiliate block explains "commission per unit + marketing fees accrue per order and are charged when the campaign finishes". Wallet balance vs grand total warning. |
| Vendor campaign page | Snapshot, per-invitation charged amounts, **Affiliate fee history** table (order, qty, commission, marketing fee delta, running total, status). Vendor finance/transactions/statement: separate "Marketing fees" and "Affiliate marketing fees" lines. |
| Admin campaign page + financials | Snapshot, per-invitation marketer payout/platform share, **Affiliate fee history** with settle status and a manual "settle now" action (permission-gated). |
| Admin marketer profile | Fee-share earnings per campaign (share %, credited amount/date), affiliate commissions, wallet; the existing "Commission Rates by Category" card shows which rule beats the category default. |
| Admin finance reports (`FinancialReportService`) | Lines per country+currency and per type: influencer fees collected, marketing fees collected (influencer + affiliate), marketer fee-share payouts, affiliate commissions, platform margin, VAT liability; CSV. |
| Marketer panel | Wallet shows `marketer_campaign_fee_share` credits; Reports: fee-share earnings per campaign, **affiliate campaign page with per-order history**; per-sale commission page unchanged. API `Api\Marketer\FinanceController` exposes the same types. |

---

## 5. Lifecycle after the change
**Influencer campaign:** 1 admin sets fee, marketing fees (fixed per country, variable per category/type) and the category "Marketers" fee-share % -> 2 vendor picks influencers, server quote shows grand total -> 3 submit: campaign snapshot saved, admin sees requested marketers + numbers -> 4 admin approves -> invitations -> 5 influencer accepts: invitation stores fee, VAT, its marketing share, share %, payout; vendor charged `platformFeeTotal()` (wallet, else statement) -> 6 on `paid`: influencer wallet credited fee x share% (once) -> 7 influencer withdraws via wallet -> 8 reports read the snapshots. Decline/timeout: nothing charged/credited; refund of a paid invitation reverses the vendor charge and the credit (P7).

**Affiliate campaign:** 1 admin sets the affiliate default (mode/%/flat) per category+country and optionally per-marketer rules -> 2 vendor opens the campaign to affiliates (no upfront fee; marketing-fee explanation shown) -> 3 affiliate sells: each successful order creates a conversion with commission from the D3 resolver (profile rule > category default) and, on approval, a **fee accrual row** (history) -> 4 vendor/marketer/admin campaign pages show the running history -> 5 campaign finishes or hits `max_commission_budget` (auto-pause): accrued marketing fees are debited from the vendor once and the accruals are marked settled -> 6 commission payout to the affiliate continues through the existing statement/wallet path.

---

## 6. Worked numbers
Influencer (UAE, 2 influencers, share 20 %):
| Line | Value |
|---|---|
| influencer fees 2 x 9 | 18.00 |
| VAT 5 % | 0.90 |
| subtotal | 18.90 |
| fixed (1.9 + 0 %) | 1.90 |
| variable (0 + 10 %) | 1.89 |
| marketing fees | 3.79 |
| **vendor pays** | **22.69** |
| marketer credits 2 x 9 x 20 % | 3.60 (1.80 each) |
| platform keeps from fees 2 x 9 x 80 % | 14.40 |
| platform marketing fees | 3.79 -> revenue 18.19, VAT 0.90 liability |

Per invitation (N = 2, each 9.45): 0.95 fixed share + 9.45 x 10 % = 0.945 -> 1.895 -> **1.90**; the second takes the remainder **1.89**; charged 11.35 + 11.34 = 22.69.

Affiliate (UAE, listing price 100 AED, category default 10 % + 0 flat, fixed fee 1.9 + 0 %, variable 0 + 10 %): order 1, qty 2 -> line 200, commission 20.00 -> accrual: fixed 1.90 + variable 20 x 10 % = 2.00 -> 3.90 (running 3.90). Order 2, qty 1 -> commission 10.00 -> delta 1.00 (running 4.90). Campaign finishes -> vendor debited 4.90; affiliate commission 30.00 follows the existing per-order path. If the affiliate has a profile rule of 15 % for that category, commission uses 15 % instead.

---

## 7. Removal checklist for the old amount columns (D2) — must be zero hits at the end
`grep -rn "influencer_commission_amount\|affiliate_commission_amount" app resources routes tests database/seeders` returns nothing, except the two migrations that add/drop them. Files to rewrite: `Admin\CategoryController` (tab form + `updateMarketerCommission`), `admin/categories/_form.blade.php` (Marketers tab inputs, labels, JS), `Partner\ListingController` (create/edit commission preview), `MarketerCommissionCountrySetting`, `LastClickAttributionService`, `MarketerCampaignQuoteService::commissionFor`, `MarketerCampaignService` (`resolveDefaultCommission`, `resolvePeerCommission`, open-market/others), `admin/marketer_campaigns/show.blade.php`, vendor/marketer campaign views that echo the estimate, lang keys (ar+en) for the old labels, tests and `PeerPromotionFixtures`. Also search the Flutter/Next.js apps and docs for the field names.

---

## 8. Test matrix (every prompt adds tests; Campaigns, Commission, Checkout, Admin, Vendor suites stay green)
- `MarketingFeeServiceTest`: 22.69 example; pct only; amount only; both; zero subtotal; ancestor fallback; each type incl. `cross_dock -> FBM`; snapshot immune to later setting changes.
- Quote/create: breakdown on the form, snapshot == quote, client numbers ignored, allowed models include cross_dock.
- Accept flow: wallet debit = fee + VAT + marketing; statement path equal; insufficient wallet -> pending -> retry job; all accept = campaign quote; decline = 0.
- Influencer credit: exactly once (wallet, statement, retry), share %, frozen wallet, affiliate gets none.
- Affiliate: resolver order (profile rule > category default > 0, both/pct/flat), per-order accrual with running totals, fixed accrued once, reversal on refund, settlement on finish and on budget exhaustion, idempotent, debit via wallet/statement, history visible in vendor/marketer/admin campaign pages.
- Removal: the old columns are gone from the schema and from every reader; peer promotion falls back per D12.
- Reports: admin finance lines/per type/CSV, marketer reports, vendor lines, admin profile, using the section 6 fixtures.

---

## 9. Execution prompts (run in order; each ends with tests + `vendor/bin/pint --dirty --format agent` + commit + push to main)

**Preamble for every prompt:** "Repo el-joe/marketplace_platforms, pull main first. Read docs/plans/marketing-fees-and-marketer-payouts.md (v2) and docs/plans/handoff/HANDOFF-marketer-campaigns.md (test env: MariaDB + patched schema, never commit the patch). New migrations only, ar+en lang keys, explicit `git add` paths, commit trailer from the session. Decisions D1–D12 in the plan are final."

**P1 — Data model + calculators.** Migrations 4.1 (1, 2, 4, 5, 6 and the ADD half of 3; do NOT drop the old columns yet), models, `MarketingFeeType` enum, `MarketingFeeService`, `MarketerFeeShareResolver`. Add `cross_dock` to the `marketer_campaign_allowed_fulfilment_models` default everywhere it is read (service, controllers) and its admin setting. Unit tests for the section 6 numbers. No flow changes.

**P2 — Admin "Marketing Fees" page.** Controller/routes/views for fixed + variable fee tabs with the combined type labels, live calculator (influencer + affiliate toggle), nav item in the Marketing sidebar group (ar+en), permissions per D9, validation (amounts `numeric|min:0|decimal:0,4`, percentages 0–100). Report tab is stubbed until P8. Feature tests.

**P3 — Category "Marketers" tab rewrite + removal of the old amounts (D2/D3/D11/D12).** Replace the tab fields with `influencer_fee_share_pct` and the affiliate default (mode/rate/flat); rewrite `CategoryController::updateMarketerCommission`, `_form.blade.php`, `AffiliateCommissionResolver` (profile rule -> category default -> 0, ancestor fallback), `LastClickAttributionService`, `resolveDefaultCommission`, `resolvePeerCommission` (D12), `MarketerCampaignQuoteService::commissionFor`, `Partner\ListingController`, admin/vendor/marketer views and lang. Data-copy migration then the DROP migration for both old columns. Finish with the section 7 grep = zero hits. Tests incl. a regression for per-sale commission of influencers (profile rule only) and affiliates (both modes).

**P4 — Quote + vendor create form + influencer campaign snapshot.** Extend the quote (4.2), render the full breakdown/grand total/type label on `partner/marketer_campaigns/create.blade.php` (no client math), snapshot at `createCampaign`, show on vendor campaign list/show and admin campaign show/financials/requested-marketers partial. Affiliate block text per 4.6. Tests.

**P5 — Influencer charge + marketer credit.** Implement 4.3 in `acceptInvitation` (vendor always pays, D7), widen `platformFeeTotal()`, update `GenerateVendorPayoutsJob`, `PayoutCalculationService`, `TransactionFeedService`, `Partner\FinanceController`, payout item descriptions (separate marketing line), `MarketerFeePayoutService` hooked into both paid paths. Tests per section 8.

**P6 — Affiliate accrual, history and settlement (D4/D6).** `AffiliateFeeAccrualService` (accrue on conversion approval, reverse on refund), `AffiliateFeeSettlementJob` + triggers for campaign finish and budget exhaustion, vendor debit (wallet then statement line), history tables on the campaign page in vendor, marketer and admin panels, admin "settle now". Tests: running totals, fixed once, reversal, both settlement triggers, idempotency, section 6 affiliate numbers.

**P7 — Cancellation/refund symmetry.** Cancelled/removed paid influencer invitation: reverse the credit (or negative adjustment if the wallet is short), refund the vendor, never double-credit; cancelled affiliate campaign settles what was accrued. Tests.

**P8 — Reports everywhere.** `FinancialReportService` + admin financial report view + CSV (influencer fees, marketing fees, marketer fee-share payouts, affiliate commissions, platform margin, VAT liability; per country/currency and per type), Marketing Fees "Report" tab, admin marketer profile earnings blocks, marketer panel reports/wallet/API, vendor transaction/statement lines. Tests asserting the section 6 numbers.

**P9 — End-to-end QA + handoff.** One feature test per flow (influencer and affiliate) from vendor create to reports in every panel; run the Campaigns, Commission, Checkout, Admin, Vendor suites; Playwright pass over the admin page, vendor form/campaign page, marketer wallet/campaign page; update `HANDOFF-marketer-campaigns.md`; produce a before/after summary.

---

## 10. Risks / notes
- `platformFeeTotal()` is shared by statements, feed and wallet; marketing term defaults to 0 so old rows are unchanged until P5.
- Dropping the amount columns changes live commission behavior for influencers (D11) and peer promotions (D12): P3 writes the dropped values to a legacy dump for finance review before the DROP.
- Affiliate commission is already vendor-deducted per order (D10); P6 charges only the marketing fees at the end. Confirm D10 before P6.
- Rounding: round half-up to 2 dp per invitation/accrual; last invitation gets the remainder.
- Settings never rewrite history: campaigns, invitations and accrual rows store their own snapshots.
- Local MariaDB test env needs the two local-only patches from the handoff (schema parser; `2026_10_13_*` migrations reading column default `NULL` as a string); never commit them.
