# Marketing fees + marketer payouts: final summary (P1-P9)

Date: 2026-10-14. Plan: `marketing-fees-and-marketer-payouts.md`. Every ambiguity resolved on the way: `marketing-fees-decisions-log.md`. Handoff for the next engineer: `handoff/HANDOFF-marketer-campaigns.md`.
Money: `DECIMAL(19,4)` base-currency numbers, rounded half up to 2 dp per invitation / accrual, currencies never summed.

## 1. Before / after

| Area | Before | After |
|---|---|---|
| Vendor charge for an influencer campaign | fee + VAT only (`platformFeeTotal()`), no platform marketing fee | fee + VAT + **marketing fees** (fixed per country + variable per country/category/type). UAE example: 18.00 + 0.90 + 3.79 = **22.69** |
| Quote on the vendor create form | fee, VAT, total; per-row commission estimate from the old amount columns | server-side breakdown: fees, VAT, subtotal, fixed, variable, marketing, grand total, type label, per-row marketer payout / platform keeps, wallet-vs-total warning. No client math |
| Campaign record | no fee snapshot | snapshot at creation (`fulfillment_type`, subtotal, fixed, variable, total, grand total); never recomputed |
| Who pays the influencer fee | setting `marketer_influencer_fee_payer` (vendor or marketer) | vendor always for vendor campaigns (D7). Marketer-pays branch survives only for campaigns with no vendor |
| Charge moment | on accept (wallet, else statement) | unchanged; each invitation carries its marketing share, the last acceptor takes the rounding remainder, all accepted = quote exactly |
| Influencer income from the fee | none (platform revenue) | `fee x category fee-share %` credited to the marketer wallet once the vendor charge is `paid` (1.80 of 9.00 at 20 %); hourly retry |
| Category "Marketers" tab | flat per-sale `influencer_commission_amount` / `affiliate_commission_amount` | `influencer_fee_share_pct` + affiliate default (mode fixed / percentage / both, rate, flat), saved per country by fetch |
| Affiliate commission source | marketer-type amount columns, then profile rule | marketer profile rule, else category affiliate default, else 0 (`AffiliateCommissionResolver`) |
| Marketing fees on affiliate campaigns | none | accrued per approved order on the commission (flat amounts once), history table, settled once when the campaign finishes / is cancelled / hits `max_commission_budget` |
| Cancelled / rejected campaign | fee stayed charged | every paid influencer invitation refunded to the vendor wallet, marketer credit reversed (or clawback pending); affiliate settlement refunds reversed percentage parts |
| Admin finance report | no marketing lines; `/reports/financial/data` threw a 500 | marketing block per country + currency + type, CSV (`?section=marketing`), platform margin, VAT liability; endpoint fixed |
| Admin pages | none for fees | Marketing Fees page (fixed, variable, report tabs + calculator), campaign page snapshot / payouts / affiliate history / "Settle now", marketer profile earnings |
| Vendor pages | fee + VAT only | separate influencer / marketing / affiliate / refund totals on transactions, statement, campaign list, finance summary API; affiliate history on the campaign page |
| Marketer panel | no fee-share income, no affiliate history | wallet labels, reports (fee share + affiliate totals), `marketer.campaigns.show` with own order history, API `/api/marketer/finance/reports` |
| Allowed fulfilment models | `fbn`, `fbm` (cross_dock listings could not start campaigns) | `fbn`, `fbm`, `cross_dock`; FBM = cross_dock, FBP = fbm (D8) |

## 2. Current end-to-end flow (numbers: UAE, fee 9.00, VAT 5 %, fixed 1.9 + 0 %, variable 0 + 10 %, fee share 20 %)

### Influencer
1. Admin sets (all editable; saved with `marketer_fee_settings.edit` / `marketer_commission_settings.edit`): fee per influencer 9.00 (Marketer Settings), fixed fee 1.9 per country and variable fee 10 % per category/type (Marketing Fees page), fee share 20 % (category edit, tab Marketers).
2. Vendor opens the create form for an FBN listing and picks 2 influencers. Server quote: fees 18.00, VAT 0.90, subtotal 18.90, fixed 1.90, variable 1.89 (10 % of 18.90), marketing 3.79, **grand total 22.69**; per row payout 1.80 / platform keeps 7.20.
3. Vendor submits. Campaign `pending_admin` with the 22.69 snapshot; nothing is charged. Client-posted numbers are ignored.
4. Admin approves (campaign page shows the snapshot). Invitations are created and sent.
5. Influencer 1 accepts: vendor wallet debit **11.35** (9.00 + 0.45 + 1.90 marketing share). Influencer 2 accepts: **11.34** (9.00 + 0.45 + 1.89, the remainder). Total 22.69. Not enough wallet: invoice stays `pending`, collected by the daily job or the weekly statement line.
6. When each invitation is `paid`, the influencer wallet is credited **1.80** (9.00 x 20 %, snapshotted on the invitation). Total 3.60. Platform keeps 14.40 of the fees plus 3.79 marketing = 18.19 revenue; VAT 0.90 is a liability.
7. Influencer withdraws from the marketer wallet as before.
8. Decline / timeout: nothing charged or credited. Cancel / reject after payment: vendor refunded fee + VAT + marketing to the wallet, credit reversed (wallet debit, or `marketer_clawback_amount` when short).

### Affiliate (category default 10 %, fixed 1.9 + 0 %, variable 0 + 10 %)
1. Admin sets the affiliate default (mode / rate / flat) per category and country, optionally a per-marketer rule in "Commission Rates by Category".
2. Vendor opens the campaign to affiliates: no upfront fee, the form explains the accrual.
3. Order 1, qty 2 (line 200): commission **20.00** (profile rule beats the category default). On approval after delivery: accrual fixed 1.90 + variable 2.00 = **3.90**, running 3.90.
4. Order 2, qty 1: commission 10.00, accrual **+1.00**, running **4.90** (flat amount accrued once). History visible on the vendor, admin and marketer campaign pages (the marketer sees only own orders and no vendor money).
5. Campaign finishes (or is cancelled): vendor debited **4.90 once** (wallet all-or-nothing, else a weekly statement line); accruals marked settled. Budget exhaustion: the auto-pause settles what is approved so far (4.90 once); the sale that crossed the budget accrues 1.00 at its approval and is settled on arrival as a separate settlement (see open decision 4).
6. The affiliate commission (30.00) follows the existing per-order path (deducted from the vendor payout, credited to the marketer after approval). Refund of an order: percentage parts of its accrual reversed; after settlement they are credited back to the vendor wallet.

## 3. What is new or changed

### Tables and columns
| Table | Change |
|---|---|
| `marketing_fee_country_settings` | new: country_id (unique), fixed_amount, fixed_percentage, updated_by_admin_id |
| `marketing_fee_category_settings` | new: country, category, fulfillment_type (fbm/fbn/fbp), fixed_amount, percentage; unique(country, category, type) |
| `marketer_commission_country_settings` | + influencer_fee_share_pct, affiliate_commission_mode / _rate / _flat; **dropped** influencer_commission_amount, affiliate_commission_amount |
| `marketer_campaigns` | + fulfillment_type, campaign_subtotal, marketing_fee_fixed / _variable / _total, campaign_grand_total, affiliate_fee_accrued, affiliate_fee_settled_at, marketing_fee_fixed_accrued |
| `marketer_campaign_invitations` | + platform_fee_marketing_amount, marketer_fee_share_pct, marketer_payout_amount, marketer_credited_at, marketer_payout_wallet_tx_id, platform_fee_refunded_at / _amount / _wallet_tx_id / _reason, platform_fee_voided_at, marketer_credit_reversed_at, marketer_credit_reversal_wallet_tx_id, marketer_clawback_amount; status `refunded` |
| `marketer_campaign_fee_accruals` | new: per-order affiliate history (quantity, line_total, commission_base, fixed/variable/total delta, running totals, status, entry_type accrual/reversal, settlement link); unique(conversion_id, entry_type) |
| `marketer_campaign_fee_settlements` | new: one row per charge or refund (kind, amount, currency, status, paid_via, paid_at) |
| `payout_items` | + marketing_fee_amount, + affiliate_fee_settlement_id (unique); item type `affiliate_marketing_fee` |

Migrations (all new, dated 2026_10_14_13xxxx to 18xxxx); the legacy dump `2026_10_14_150001` writes `storage/app/legacy/marketer_commission_amounts_<date>.json` before the drop `150002`.

### Services, jobs, models, enum
- Services: `MarketingFeeService`, `MarketerFeeShareResolver`, `AffiliateCommissionResolver`, `MarketerFeePayoutService`, `AffiliateFeeAccrualService`, `AffiliateFeeSettlementService`, `MarketerInvitationRefundService`, `MarketingFeeReportService`. Rewritten: `MarketerCampaignQuoteService`, `MarketerCampaignService`, `LastClickAttributionService`, `FinancialReportService`, `PayoutCalculationService`, `TransactionFeedService`, `Vendor\FinanceService`.
- Jobs (hourly unless noted): `RetryMarketerFeeCreditsJob`, `RetryInvitationFeeRefundsJob`, `AffiliateFeeSettlementJob`; existing `CollectPendingInfluencerFeesJob` (daily) and `GenerateVendorPayoutsJob` handle the new statement lines.
- Models: `MarketingFeeCountrySetting`, `MarketingFeeCategorySetting`, `MarketerCampaignFeeAccrual`, `MarketerCampaignFeeSettlement`. Enum `MarketingFeeType` (labels FBM (cross_dock), FBN (Fulfilled by Nawy), FBP (fbm)).

### Routes and pages
| Panel | Route / page |
|---|---|
| Admin | `admin.marketing-fees.index|calculate|categories|report.export` (view), `update-fixed|update-variable|destroy-variable` (edit); `admin.categories.marketer-commission.update` (tab Marketers); `admin.marketer-campaigns.settle-affiliate-fees`; `admin.reports.financial.*` (`section=marketing`); admin marketer profile earnings blocks |
| Vendor | create form breakdown (`partner.listings.marketer-fee` JSON), campaign show (snapshot, invitations, affiliate history), campaign list / finance transactions / payout statement totals |
| Marketer | `marketer.campaigns.show`, `marketer.reports.index`, `marketer.finance.wallet`, API `GET /api/marketer/finance/reports` (+ `source_label` on wallet transactions, `stats` on `/api/marketer/reports`) |

### Permissions
No new permissions (D9): page = `marketer_commission_settings.view`; writes = `marketer_fee_settings.edit`; category tab = `marketer_commission_settings.edit`; "Settle now" = `marketer_campaigns.approve`; finance report = `analytics.view`.

## 4. Removed
- Columns `marketer_commission_country_settings.influencer_commission_amount` and `affiliate_commission_amount` and every reader (grep over `app resources routes tests database/seeders` is empty apart from the add/drop migrations).
- The marketer-payer branch of influencer fee acceptance for vendor campaigns (setting `marketer_influencer_fee_payer` is now ignored there).
- The per-country nested `<form>` in the category Marketers tab (browsers dropped it; replaced by fetch saves).
- Per-sale default commission for influencers (D11) and the amount-based peer promotion commission (D12): both now need a profile rule.
- The non-existent `MarketerPayout` reference in `FinancialReportService` (caused a 500) and the `PayoutSchedule` enum crash in `FinanceService::resolveNextPayoutDate()`.
- The "stub" Report tab text of P2 (real report in P8).

## 5. QA result (P9)
- `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php`: 3 tests, 216 assertions (influencer flow through every report; affiliate finish; affiliate budget exhaustion).
- Browser smoke: 8 screens (admin Marketing Fees 3 tabs, category Marketers tab, vendor create form with quote, vendor/admin/marketer campaign pages with affiliate history, admin finance report, marketer reports/wallet) x en/ar: no 500, no raw lang keys, no horizontal overflow, RTL correct. No application bug was found in P9.

## 6. Open decisions for the owner
Items marked **RISK** change money or live behaviour and deserve an explicit yes.

1. **RISK, D10 (derived, unconfirmed).** The affiliate commission itself is still deducted from the vendor payout per order; only the marketing fees on that commission are charged at finish / limit. If you want the commission charged to the vendor only at the end, the per-order deduction must go (separate, risky prompt).
2. **RISK, D11/D12.** Influencers lose any default per-sale commission; peer promotions use the promoter's profile rule and otherwise get 0 commission with a warning. The dropped legacy values are in `storage/app/legacy/marketer_commission_amounts_<date>.json` on each environment: finance should review them before relying on the new behaviour.
3. **RISK, new in P9: commission base includes customer VAT.** Affiliate commission is computed on `order_items.line_total`, which includes VAT (100 AED listing at 5 % VAT = 105 line, 10 % = 10.50). This is the old behaviour, unchanged; the plan examples assume a VAT-free line. Decide whether the base should be the VAT-exclusive subtotal (it also drives the fee accrual base).
4. Budget exhaustion settles in two steps. The budget is spent at order placement, the fee accrues at approval, so the sale that exhausts the budget is charged as a second small settlement when it is approved (never double, never missed). Alternative: hold settlement until all pending conversions are approved or rejected.
5. **RISK, refunds.** A cancelled or rejected campaign refunds every vendor-paid influencer invitation even if the influencer already posted or received a sample (no work-done exception); `done`/`completed` never refunds. Fees paid through a weekly statement are refunded to the wallet (issued statements are immutable). Confirm both rules.
6. Marketer clawback is all-or-nothing and never makes the wallet negative; the amount stays "clawback pending" until the wallet is funded (hourly job). Confirm no partial debit / no negative balance.
7. Affiliate refunds: only the percentage parts of an accrual are reversed; the flat amounts stay accrued. After settlement the reversal is credited to the vendor wallet.
8. **RISK, accounting.** Marketing figures are NOT in `platformNetForPeriod()` / `summaryForPeriod()` (the ledger reconciliation anchor): influencer and marketing fees are wallet / statement movements with no ledger entries. They live in a separate block with their own margin. Decide whether they should post ledger entries.
9. Platform margin formula: influencer fees - fee-share payouts (credited - reversed - clawback) + marketing fees (influencer + affiliate) - refunds excluding refunded VAT; VAT liability = VAT collected - refunded VAT.
10. No VAT on marketing fees (D5); the fee-share is on the fee only, not VAT or marketing.
11. Campaigns without a snapshot (admin-owned, peer, marketer-requested, legacy) and vendor-less campaigns carry no marketing share and no fee share; vendor-less campaigns keep the marketer-pays branch.
12. Affiliate-only and zero-influencer campaigns have grand total 0 at creation; their fees accrue per order. The flash-sale bonus is not part of the fee base.
13. A per-unit amount typed on a campaign (admin approval, peer) still wins over the resolver at sale time; an amount equal to the stored category default is re-resolved on the real line.
14. An influencer-only campaign is approvable with a 0 per-unit commission; any affiliate in the list keeps the old refusal.
15. Unknown or `fbm` listing models bill as FBP; the stored setting `marketer_campaign_allowed_fulfilment_models`, if an environment has a row, must include `cross_dock` by hand.
16. Per-invitation marketing share is the snapshot total / N (rounded), N = non-replaced influencer invitations; replacements do not change the charge.
17. D9 reuse of existing permissions means anyone who can edit commission settings can also see the Marketing Fees page; no dedicated permission was added.
18. Pre-existing, outside this work: the admin category edit breadcrumb/title double-escapes quotes (`Women&#039;s`).
