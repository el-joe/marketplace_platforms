# Marketer Types, Commissions & Fees Refactor (v3) — Plan for Review

Status: **PLAN ONLY — nothing implemented.** Waiting for the owner's "run" order.
Base: `main` @ `0f576764`. Date: 2026-10-15.
Supersedes (where they conflict): `marketing-fees-and-marketer-payouts.md` (v2) decisions D1–D12, notably the influencer *fee-share %* model.

Execution rules (from the owner):
1. This file is reviewed first. No code until the run order.
2. On run: implement everything, **write test files but DO NOT run tests**, then produce `docs/plans/marketer-types-commissions-refactor-tests.md` (what to run, expected results).
3. Anything campaign-fee/commission related that is **not** in the spec is removed permanently (code, tables/columns, models, commands, lang, tests).

---

## 1. Target model in one page

### 1.1 Marketer types (3)
| Type | Earns | Basis | Per-category rule in marketer profile |
|---|---|---|---|
| **Influencer** | **Fixed amount per campaign** (one flat amount per influencer per campaign, not per sale) | Fixed, per country + category | Fixed only (percentage input disabled) |
| **Affiliate** | **Percentage** | Customer-visible product/listing price; classified = classified total price; travel = total of bookings attributed to him | Percentage only (fixed input disabled) |
| **Broker** | Fixed, percentage, or both | Same bases as affiliate, same flow in all 3 verticals | Fixed / percentage / both |

Verticals ("category types"): `product`, `classified`, `travel`.

### 1.2 Who creates which campaign
| Panel | Creates campaign for | Marketers |
|---|---|---|
| Partner (product vendor) | products | multi-select influencers (same country as product) + checkbox "open to all registered affiliates" |
| Partner (classified vendor) | classified listings | same |
| Travel agency | trips | same |
| Admin | any (existing admin flow) | any |

Broker joins the same way affiliates do (open checkbox) and via invitation where the existing flow already allows it (decision Q3).

### 1.3 Formulas
```
influencer_commission  = ONE fixed amount per influencer per campaign (not per sale/unit/order). Source: profile fixed rule (marketer, category type, category)  → else default influencer commission (marketer settings, category type [+ country])
affiliate_commission   = pct × basis            (basis per vertical, §1.1)
broker_commission      = fixed + pct × basis    (either part may be null/0)

campaign_fee_to_platform (paid by vendor/agency) = fixed_fee + variable_fee
        each = amount | % | both ; % taken from total campaign (as today)

commission_fee_to_platform (taken from marketer) = pct × marketer_commission + fixed
        configured in marketer settings (default per category type) and
        overridden per marketer profile per category
marketer_net = marketer_commission − commission_fee_to_platform   (never < 0)
```
Influencer amount is **locked (snapshotted) on the invitation at campaign creation** and counts against Max Commission Budget at that moment (reserved), so the budget can never be exceeded by influencers. It is earned by the influencer on delivery of the campaign (decision Q4/Q16). Open-to-affiliates campaigns stop (status `done`) when `commission_budget_spent ≥ max_commission_budget`.
Travel/classified affiliate/broker commissions accrue **after** sale/booking (classified marked sold; travel booking confirmed/completed) and are reversed on cancellation/refund.

---

## 2. Current state (what exists)

- **Types:** only `influencer` and `affiliate` jobs (`MarketerJobSeeder`). "broker" = alias to affiliate (`MarketerOnboardingService::KEY_ALIASES`), plus broker specialization columns on `marketer_profiles` (`broker_category_id`, `broker_city_id`, `broker_serves_all_cities`) used for special-request routing, and `target_type=broker` in `MarketerAdPackageController`.
- **Admin marketer settings** (`MarketerSettingsController`, `admin/marketer_settings/index.blade.php`): influencer fee per country, referral grace days, peer min stock, promotion targets/fines.
- **Per-category country settings** (`marketer_commission_country_settings`): `influencer_fee_share_pct`, `affiliate_commission_mode/rate/flat` — edited in category edit "Marketers" tab (conflicts with new spec).
- **Marketer profile "Commission Rates by Category"** (`admin/marketers/show.blade.php` ~L591-690): `marketer_commission_rules` with scope products/open_market/travel, rate/flat/min, mode inferred. Legacy mirrors `marketer_category_commissions`, `open_market_category_commissions` still written.
- **Resolver chain:** `CommissionRuleResolver` → `MarketerCommissionRule` → `MarketerCommissionRateService`, `AffiliateCommissionResolver`, `MarketerCommissionStatementService` (platform cut % → profile discount → VAT → net).
- **Fees built earlier:** `MarketingFeeService` (fixed+variable per country/category/fbm|fbn|fbp), `MarketerFeeShareResolver`, `MarketerFeePayoutService`, `AffiliateFeeAccrualService`, `AffiliateFeeSettlementService`, `MarketerInvitationRefundService`, `MarketingFeeReportService`.
- **Campaign lifecycle:** statuses pending_admin / marketer_requested / active / auto_approved / rejected / paused / done / cancelled; budget auto-pause in `LastClickAttributionService`.
- **Verticals:** product fully wired. Classified and travel are schema-only (no controller/route, no attribution, no conversion insert — `marketer_campaign_conversions.order_id` is NOT NULL; `travel_booking_id`, `classified_inquiry_id`, `conversion_type` exist in DB but not in the model).
- **Travel agency panel** has a separate, broken "campaign offer" system (`TravelAgencyCampaignOffer*`, never creates a `MarketerCampaign`, missing notification class and permission).
- **Classified vendors:** no campaign UI; `marketer_promotion_enabled` checkbox in classified wizard is a dead end.
- **Triggers missing:** classified `markSold` captures no price/buyer/`sold_at`; `TravelBooking` has no marketer/campaign columns; nothing sets `completed`.

---

## 3. Design decisions to confirm (please answer before "run")

Recommended answer in **bold**.

| # | Question | Recommendation |
|---|---|---|
| Q1 | Influencer fixed amount: "per country and category" — one default per (country, category type) in marketer settings, with optional per-category override? | **Settings: per (country × category type) default. Marketer profile: per category (and category type default) override. Category-edit "Marketers" tab influencer-share % removed.** |
| Q2 | Travel/classified "commission due after sale": which event? | **Classified: seller taps "Mark sold" and enters sale price (required when a campaign is attached). Travel: booking `confirmed` and paid, released on trip `completed` (new nightly job marks past trips completed).** |
| Q3 | Can brokers be invited by vendors like influencers, or only join via "open to all"? | **Open-checkbox label becomes "Open to all registered affiliates & brokers"; no per-broker invite.** |
| Q4 | ~~Per sale unit~~ **Owner decision: influencer fixed amount is PER CAMPAIGN, not per sale.** | Settled. |
| Q16 | When is the per-campaign influencer amount released to the influencer? | **Reserved from campaign budget when the influencer accepts; paid (net of commission fee) when the campaign ends `done` or on campaign end date, if the influencer fulfilled it; refunded to vendor budget if rejected/cancelled/not fulfilled. Alternative: pay on acceptance.** |
| Q17 | Does the influencer amount count in Max Commission Budget? | **Yes (reserved), so remaining budget funds affiliate/broker % commissions only.** |
| Q5 | Affiliate/broker % basis for product: price shown to the customer — incl. VAT or excl.? | **Excl. VAT, after discounts/coupons ("customer-visible item price before tax").** |
| Q6 | Commission fee (platform cut on marketer) — replaces old `platform_cut_percentage` + profile discount + VAT-on-cut? | **Yes: replace with `pct + fixed`; VAT on the fee kept only if currently required by tax settings (reuse existing VAT line).** |
| Q7 | Campaign fee % "taken from total campaign as now": keep current `campaign_subtotal` meaning? | **Yes, keep.** |
| Q8 | Existing data (active campaigns, conversions, accruals) | **Migrate in place: backfill defaults, close nothing; old rows keep stored snapshots, new rules apply to new conversions.** |
| Q9 | Reviving the TravelAgencyCampaignOffer layer vs. replacing with `MarketerCampaign` | **Remove it; travel agencies create `MarketerCampaign` directly (same wizard as vendors).** |
| Q10 | Tiered rules / flash-sale bonus / last-click flat types | **Remove (not in spec). Campaign commission type simplifies to "per marketer type rule".** |
| Q11 | Max commission budget for influencer-only campaigns | **Budget applies to both; reaching it → `done`.** |
| Q12 | Influencer fee payer (vendor vs platform) setting | **Remove; vendor always funds commission via campaign budget; platform earns campaign fees + commission fees.** |

---

## 4. Data model changes

### 4.1 Add
- `marketer_jobs`: seed **broker** (`MarketerJobSeeder` + migration backfill). Remove alias in `KEY_ALIASES`; `Marketer::isBroker()`. Migrate existing marketers whose onboarding chose "broker" (those with `broker_category_id` set) to type broker.
- `marketer_default_commissions` (new, replaces `marketer_influencer_fee_country_settings` + commission-country settings): `country_id`, `category_type` (product|classified|travel), `influencer_fixed_amount`, `commission_fee_percentage`, `commission_fee_fixed` , optional `category_id`.
- `marketer_commission_rules` (reuse): add `marketer_type` check, constraint rules by type (influencer: flat only; affiliate: rate only; broker: any), plus `fee_percentage`, `fee_fixed` (commission-fee override per marketer/category).
- `marketer_campaigns`: `owner_type` support for `travel_agency`; `listing_type` (product|classified|travel); `open_to_brokers` merged into `open_to_affiliates`.
- `marketer_campaign_conversions`: make `order_id` nullable; add `travel_booking_id`, `classified_listing_id` (fix to model fillable/relations), `conversion_type`, `commission_fee_amount`, `net_commission_amount`; `sale_amount`, `triggered_at`, `reversed_at`.
- `classified_listings`: `sold_price`, `sold_at`, `sold_via_campaign_id`.
- `travel_bookings`: `marketer_campaign_id`, `marketer_id`, `referral_code`, `completed_at`.
- `classified_inquiries`: `marketer_id`, `marketer_campaign_id`, `referral_code`.

### 4.2 Remove permanently (not in spec)
Tables/columns (each with a down-migration that is a no-op "irreversible removal" note and the migration deleting the data):
- `marketer_category_commissions`, `open_market_category_commissions` (legacy mirrors) + models/writes.
- `marketer_campaign_tiered_rules`, `marketer_campaign_category_rules` + models, UI, services.
- `marketer_campaigns`: `commission_type` variants tiered/last_click/flat_per_order/flat_per_click (collapse), `marketer_commission_amount`/`platform_commission_amount` if redundant with conversions (decide on execution after usage grep).
- `marketer_campaign_invitations`: `marketer_fee_share_pct`, `marketer_payout_amount`, credit/refund/clawback columns tied to the influencer fee-share model; `platform_fee_marketing_amount` kept only if still the campaign fee carrier.
- `marketer_campaign_fee_accruals`, `marketer_campaign_fee_settlements`, `payout_items.affiliate_fee_settlement_id` (affiliate per-order fee-accrual model replaced by commission fee).
- `marketer_profiles`: `platform_cut_percentage`, `commission_discount_type/flat/percentage/notes`.
- `marketer_commission_country_settings`: `influencer_fee_share_pct`, `affiliate_commission_mode/rate/flat` (table dropped; data migrated to `marketer_default_commissions` where possible).
- `marketer_influencer_fee_country_settings` (replaced), settings keys: `marketer_influencer_fee_payer`, other unused fee keys.
- `flash_sale_marketer_invitations` extra commission bonus columns (keep invitation if used for non-commission purposes).
- `TravelAgencyCampaignOffer`, `TravelAgencyCampaignOfferPackage`, `TravelAgencyCampaignInvitation` tables/models/controllers/views/routes/permissions.
- `marketer_sale_settlements` (own-listing marketer sales) — **kept unless owner confirms removal** (not in spec; flagged Q13).

### 4.3 Keep as-is (not marketer fee/commission)
`category_commission_tiers` (platform product commission), `vendor_acquisition_*`, `marketer_ad_packages`, `affiliate_promo_codes`, `coupon_marketers`, `marketing_fee_*` campaign-fee settings (campaign fee = fixed + variable; kept and simplified to amount/%/both).

---

## 5. Code architecture (target)

New / reworked services:
- `MarketerType` enum (influencer|affiliate|broker) and `CommissionVertical` enum (product|classified|travel).
- `MarketerCommissionCalculator::compute(Marketer, Campaign, Sale)` — single entry; returns `{gross, fee, net, basis, rule_source}`. Replaces `CommissionRuleResolver` chains, `AffiliateCommissionResolver`, `MarketerCommissionRateService`, `MarketerCommissionStatementService`, `MarketerFeeShareResolver`.
- `MarketerDefaultCommissionResolver` (settings default by country × vertical).
- `CommissionFeeService` — platform commission fee (pct + fixed) from settings/profile.
- `MarketingFeeService` — simplified to campaign fee fixed + variable (amount|%|both).
- `CampaignSaleRecorder` — one place that records a conversion for product order, classified sold, travel booking; handles reversal and budget (`done` at max budget). Replaces order-only path in `LastClickAttributionService`.
- Attribution: extend `ReferralCodeResolver` + landing for `/classified/{slug}` and `/travel/{slug}`; cookie carries campaign + marketer.
- Jobs: keep `ApproveMarketerConversionsJob` (generalise to all verticals, clearing days), `ReleaseMarketerPendingCommissionJob`; add `CompleteTravelBookingsJob`; remove `SettleMarketerSalesJob`/`CollectPendingInfluencerFeesJob`/`RetryMarketerFeeCreditsJob`/`RetryInvitationFeeRefundsJob`/`AffiliateFeeSettlementJob` and the tiered/flash code unless still required; remove `promotion:*` commands only if owner confirms (flagged Q14 — not in spec).
- Budget guard: `checkStockAndUpdateStatus` must not resume a budget-`done` campaign (existing gap).

---

## 6. Panel-by-panel changes

### Admin
- **Marketer Settings** (redesign, tabbed): *Default influencer commission* (grid country × product/classified/travel, fixed amount), *Commission fees* (pct + fixed per vertical), *Campaign fees* (link/summary to marketing fees), general (grace days, clearing days, attribution window, peer min stock). Each field with helper text and a live example ("10.00 EGP per product sold").
- **Marketer profile "Commission Rates by Category"** renamed "Influencer Fixed Commissions" / "Affiliate Commission Percentages" / "Broker Commissions" by type; disabled inputs with tooltip explaining why; includes per-row commission-fee override and effective-value preview ("if empty → default 5.00").
- **Category edit "Marketers" tab:** remove influencer share % and affiliate fields.
- **Campaign create/details:** show fee breakdown (fixed + variable), commission summary, budget meter.
- **Finance/reports/withdrawals:** reports by vertical & marketer type: campaign fees earned, commission fees earned, commissions paid, pending/approved/reversed; withdrawal queue unchanged except source-type labels.
- Remove admin pages/permissions for removed entities.

### Partner (product + classified vendors)
- Campaign wizard: influencer multi-select (country-filtered, shows each influencer's fixed amount for the listing's category), checkbox "Open to all registered affiliates & brokers", Max Commission Budget, **live cost summary** (campaign fee fixed + variable, budget, total).
- Allow `vendor.type:classified_vendor` access to campaigns; classified wizard checkbox replaced by real campaign link.
- Classified "Mark sold" modal requires sale price when campaign attached.
- Campaign page explains how every amount is calculated.

### Travel agency
- New `Campaigns` section (create/list/details) using shared wizard for trips; booking attribution and commission on confirmed bookings; remove old offer layer.

### Marketer panel
- Campaign cards show *your commission* formula for the listing (fixed / % / both), expected net after platform commission fee, status of each earning (pending → approved → paid), budget-closed notices.
- Earnings/wallet pages show gross, platform fee, net per conversion.

### Wallet / finance
- Wallet source types: keep `marketer_commission`; add `marketer_commission_fee` (platform income ledger entry); remove source types for removed models. Vendor payouts: campaign fee line stays; `influencer_fee`/`affiliate_marketing_fee` items replaced by `campaign_fee` + `campaign_budget_commission`.

---

## 7. Execution phases (each = one agent prompt; run sequentially, no tests run)

**R0 – Baseline & inventory freeze.** Re-grep every reference listed in §4.2; produce `docs/plans/handoff/v3-removal-inventory.md` with file:line list. Prompt: *"Grep the repo for every reference to each artefact in §4.2 of marketer-types-commissions-refactor.md and write the inventory; change no code."*

**R1 – Marketer types & enums.** Seed broker job, `Marketer::isBroker()`, remove alias, backfill existing marketers, update onboarding/registration/ad-package targets/lang. Prompt: *"Implement §4.1 broker type + MarketerType enum; keep every existing call site compiling; write feature tests (do not run)."*

**R2 – Schema migrations (additive then destructive).** All §4.1 additions, then §4.2 removals in a separate migration after code stops using them (end of R9). Prompt: *"Write migrations for §4.1; make order_id nullable; update models/fillable/relations. Do not run migrations in tests environment."*

**R3 – Default commissions & commission fee settings (admin).** New `marketer_default_commissions`, settings UI redesign, category-edit tab cleanup, data migration from old tables. Prompt: *"Rebuild Marketer Settings per §6 Admin; add MarketerDefaultCommissionResolver; remove influencer fee-share % from category edit."*

**R4 – Profile commission rules by type.** Type-aware validation (server + UI disabling), renamed headings, per-row commission-fee override, remove legacy mirror tables/writes. Prompt: *"Refactor storeCategoryCommission per §1.1/§4.1; enforce type constraints in FormRequest and model; remove legacy mirror writes."*

**R5 – Calculator & fee services.** `MarketerCommissionCalculator`, `CommissionFeeService`, simplified `MarketingFeeService`; delete superseded services. Prompt: *"Implement §1.3 formulas exactly; unit tests with the worked examples in §9; replace callers; delete dead services."*

**R6 – Product campaign flow update.** Influencer amount is per campaign: stored on the invitation (`influencer_fixed_amount` snapshot), reserved from budget, released per Q16; no per-sale influencer conversions — only affiliate/broker sales create commission conversions. Wizard (influencer multi-select with amounts, open-to-affiliates/brokers, max budget, cost summary), conversion recording through `CampaignSaleRecorder`, budget → `done`, fix resume gap, payouts/wallet postings, remove tiered/flash/fee-accrual code. Prompt: *"Update partner campaign wizard and LastClickAttributionService→CampaignSaleRecorder for product vertical per §6."*

**R7 – Classified vertical.** Sold price/`sold_at`, inquiry/landing attribution, classified vendor campaign routes/views/permissions, conversion on sold, reversal on un-sold. Prompt: *"Wire classified campaigns end-to-end per §6; replace marketer_promotion_enabled dead checkbox."*

**R8 – Travel vertical.** Travel agency campaign section, booking attribution columns, confirmed/completed trigger, `CompleteTravelBookingsJob`, remove TravelAgencyCampaignOffer layer. Prompt: *"Wire travel campaigns per §6 and delete the offer layer."*

**R9 – Marketer panel, reports, finance, withdrawals, lang.** Earnings breakdown (gross/fee/net), admin reports by vertical/type, wallet source labels, EN/AR lang for every new string, explanatory help text everywhere. Then run destructive removal migration, delete leftover models/commands/jobs/lang/routes/permissions, update `routes/console.php`. Prompt: *"Finish UI/reports and permanent removal per §4.2 using the R0 inventory; verify no references remain via grep."*

**R10 – Tests (written, not run) + docs.** Feature/unit tests per phase, test md `marketer-types-commissions-refactor-tests.md` with commands, expected outcomes, manual QA script; update handoff docs. Prompt: *"Write tests for R1–R9 without running; produce the tests md."*

---

## 8. Risks
- Large destructive migrations on live data → stage: backfill → switch code → drop. Take DB backup before R9 drop.
- Open payouts/accruals under old models → settle or snapshot before removal (Q8).
- Travel/classified attribution depends on referral cookie reaching those flows (separate frontends) → verify Next.js routes pass `ref`.
- Existing tests referencing removed models will fail; R10 rewrites/deletes them (not run now).

## 9. Worked examples (for test md)
- Influencer: default fixed 200 EGP per campaign (product, Egypt), no profile override; 3 units sold or 300 sold — still 200 gross; commission fee 5% + 1 fixed = 11 → net 189.
- Affiliate: 8% of item price 1,000 = 80; fee 10% + 2 = 10 → net 70.
- Broker: fixed 15 + 5% of 1,000 = 65; fee 10% + 2 = 8.5 → net 56.5.
- Campaign fee: subtotal 500; fixed fee 10 + 2% = 20; variable 5 + 1% = 10 → platform earns 30.
- Budget 200: conversions hit 200 → campaign `done`, no further conversions recorded.

## 10. Additional items needing owner confirmation
- **Q13:** keep `marketer_sale_settlements` (marketer's own listing sales)? Recommended: keep (not campaign commission).
- **Q14:** keep `promotion:*` commands, promotion targets & fines (marketer settings section 4)? Not in spec → recommended **remove** per the "remove anything not mentioned" rule; say "keep" if you want them.
- **Q15:** Flash-sale marketer invitations: remove entire marketer-invite feature or only commission bonus? Recommended: only the bonus.
