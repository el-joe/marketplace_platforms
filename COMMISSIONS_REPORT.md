# Marketplace Commission Report

Scope: `backend` (Laravel: admin, partner/vendor, marketer, travel-agency, carrier, delivery panels + APIs), `frontend` (Next.js), and the Flutter apps (`partner_app`, `delivery_app`, `carrier_app`, `travel_app`).
Money is stored as base-currency integers. A `/100` in the code is percentage math, and `/10000` is basis points.

---

## 0. Summary (all commission types)

| # | Commission type | Who pays | Who receives | Configured in |
|---|---|---|---|---|
| 1 | Platform commission (category / FBP-FBN / country / price tiers) | Vendor | Platform | Admin → Categories |
| 2 | Platform commission, vendor-specific (`commissions` table) | Vendor | Platform | DB only, no UI (see §1.2) |
| 3 | Vendor flat rate override (`vendors.commission_rate`) | Vendor | Platform | Admin → Vendors |
| 4 | Vendor commission discount (flat / %) | n/a (reduces #1–#3) | Vendor | Admin → Vendors |
| 5 | Subscription-plan commission discount | n/a | Vendor | Admin → Subscription plans (**not applied**, see §1.6) |
| 6 | Admin-listing (platform-as-seller) "commission" | n/a | Platform keeps 100% | Automatic |
| 7 | Marketer campaign commission: fixed / percentage / last_click / tiered | Campaign owner (vendor or platform) | Marketer | Admin / Vendor / Marketer panels |
| 8 | Marketer per-category rules (`marketer_commission_rules`, plus legacy `marketer_category_commissions` and `open_market_category_commissions`) | Platform default or owner | Marketer | Admin → Marketers, Categories |
| 9 | Marketer country × category amounts (influencer vs affiliate) | Owner | Marketer | Admin → Categories → Marketer commission |
| 10 | Marketer commission discount (flat / %) | n/a | Platform (reduces payout) | Admin → Marketer profile |
| 11 | Flash-sale marketer bonus (extra commission) | Owner | Marketer | Admin → Flash Sales → invite marketer |
| 12 | Flash-sale `commission_override_pct` | n/a | n/a | Admin → Flash Sales (**analytics only**, see §2.6) |
| 13 | Vendor listing influencer % / affiliate % | Vendor | Marketer | Vendor listing form (min enforced by settings) |
| 14 | Platform share of a campaign (`platform_commission_amount`) | Owner | Platform | Campaign approval (admin / vendor) |
| 15 | Vendor acquisition-agent commission (admin staff earns on vendor sales) | Platform | Admin employee | Admin → Vendors → Acquisition agent |
| 16 | Travel platform commission (flat 10%) | Travel agency | Platform | `config/travel.php` / `.env` |
| 17 | Travel agency campaign offer commission (rate + per-package override) | Travel agency | Marketer | Travel-agency panel |
| 18 | Marketplace shipping rule "commission" (`fixed` / `percentage` / `mixed`) | n/a | n/a | Admin listing → shipping rules (**stored only**) |

Related fees that are not called commission but flow through the same money split are in §6. Carrier panel and Next.js storefront findings are in §4 and §5.

---

## 1. Platform commission on product sales (vendors)

### 1.1 Where it is calculated

- Engine: `app/Services/Checkout/CheckoutPricingEngine.php`, `computeMoneySplit()` (~L440-620) and `resolveCommission()` (~L637-790).
- Math helper: `app/Services/Shared/CommissionCalculator.php`.
- Persisted by `app/Http/Controllers/Customer/CheckoutController.php` (~L1180-1390) into:
  - `order_items`: `commission_rate_pct`, `commission_fixed`, `commission_amount`, `commission_category_id`, `platform_commission_after_discount`, `marketer_commission`
  - `sub_orders`: `platform_commission`, `marketer_commission`, `marketer_commission_owner`
- Commission is computed on the gross line price (vendor listing price × qty), before any coupon (decision D1).
- `app/Services/Customer/CheckoutCalculationService::calculateCommission()` is a display-only snapshot. The engine's result overwrites `commission_amount`.

### 1.2 Resolution order (first match wins)

1. **`commissions` table** (`app/Models/Commission.php`) holds rules with `vendor_id` and `category_id`, both nullable. Other columns: `rate_pct`, `rate_type` (flat/tiered), `min_commission`, `max_commission`, `effective_from/until`, `priority`.
   - Scoring: vendor-specific (+2), category-specific (+1, walking up the parent chain, nearest ancestor wins), then `priority`.
   - Formula: `round(base × rate_pct/100)`, then clamped to `[min_commission, max_commission]`.
   - Read paths are the checkout engine and `Vendor\FinanceService::resolveCommissionRates()` (the "commission rates" screen in the partner panel, the vendor API, and `partner_app`).
   - **Finding:** no admin UI or code creates rows in this table. I found no `Commission::create` and no seeder. It only matters if rows are inserted manually or by an import.
2. **`vendors.commission_rate`** (per-vendor override). It applies only when it is greater than 0 and uses a simple `%` of the base. Admin edits it in the vendor form (`UpdateVendorRequest`).
3. **Category chain** (self → parents, up to 6 levels), taking the first node with a non-zero value:
   - Per-country override first: `country_categories.commission_{fbp|fbn}_{pct|fixed}`.
   - Then the base category: `categories.commission_{fbp|fbn}_{pct|fixed}`.
   - FBN or FBP comes from the sub-order's `fulfillment_model`.
4. **Price tiers** (`category_commission_tiers`: `price_from`, `price_to`, `commission_rate`, `min_commission`, `country_id`). They are checked on the resolved category node. A country-specific tier beats a global tier. A matching tier replaces the `%` and adds a per-unit minimum. The fixed per-unit amount (`commission_*_fixed`) is still added.
   - Formula in `CommissionCalculator`: `floor(base × rate/100) + fixed × qty`, with `max(…, min × qty)`.
5. If nothing matches, commission is 0.

`categories.commission_rate` is a legacy single rate. The checkout engine does not read it. `CategoryService` and the bulk-commission action still write it and log changes.

### 1.3 Admin screens

- Categories: base FBP/FBN % and fixed amounts, per-country commission (`CategoryCountryCommissionController`), price tiers (`CategoryCommissionTierController`, routes `categories/{id}/commission-tiers`), and bulk commission (`CategoryController::bulkCommission`).
- Views: `resources/views/admin/categories/_commission_tier*.blade.php`, `_form.blade.php`, `countries/_form.blade.php`.
- Vendors: `admin/vendors/show.blade.php`, `UpdateVendorRequest`.

### 1.4 Vendor commission discount

- `Vendor::applyCommissionDiscount()` uses the columns `commission_discount_type` (`none`/`flat`/`percentage`), `commission_discount_flat`, `commission_discount_percentage` and `commission_discount_notes`.
- It is applied to each vendor group's `raw_commission` after the commission is calculated. This yields `platform_commission_after_discount`, which is what the vendor is actually charged and what ends up in the vendor payout.
- Enum: `VendorCommissionDiscountType`.
- Test: `tests/Unit/CommissionDiscountTest.php`.

### 1.5 Admin-listing (platform is the seller)

The whole line subtotal is treated as platform "commission". There is no vendor payout (see the comment about P-03 in the engine). Admin listings also carry `influencer_commission_percentage` and `affiliate_commission_percentage`. The conversion calculation does not read them. They are only stored, and the vendor-listing validation enforces the minimums.

### 1.6 Subscription-plan commission discount (unused)

- `subscription_plans.commission_discount_pct` is editable in Admin → Subscriptions (validated 0-100).
- `SubscriptionService::applyCommissionDiscount()` exists and its docblock says "Usage in CheckoutService…".
- **Finding:** nothing calls it. The only references are the definition and the docblock. Plans advertise a commission discount, but checkout does not apply it.

### 1.7 Payout and reporting

- `PayoutCalculationService` sums `sub_orders.platform_commission`. `Payout.commission` and `PayoutItem.commission` are shown in the vendor payouts UI (`partner/payouts`) and admin payout detail.
- The vendor's `vendor_payout` formula is: `gross − vendor_coupon_cost − platform_commission_after_discount − gateway_fee − vendor_contribution − (marketer commission, if owner = vendor)`.
- Other consumers: `FinancialReportService`, `LedgerService`, `RefundService`, `OrderCancellationService`, `ReturnRequestService` and `WarrantyClaimResolutionService` (the last three zero out or reverse commission).
- Platform net: `platform_commission_after_discount + shipping revenue − platform coupon cost − admin subsidy − (marketer commission, if owner = platform) + COD fee + warranty`.

---

## 2. Marketer commissions (influencer / affiliate)

Marketer types are `influencer` and `affiliate`. Panels: the marketer web panel (`routes/marketer.php`), the marketer API (`routes/api_marketer.php`), the admin panel and the partner panel.

### 2.1 Campaign commission types

`marketer_campaigns.commission_type` can be `fixed`, `percentage`, `last_click` or `tiered`. The admin validation accepts `fixed,percentage,last_click,tiered`. The enum `CommissionType` has `percentage`, `flat_per_order` and `flat_per_click`. `AdminInvitationCommissionType` maps `flat_per_conversion` to `flat_per_order`.

Related columns:
- `marketer_commission_amount`
- `platform_commission_amount`
- `max_commission_budget`
- `commission_budget_spent`
- `marketer_campaign_tiered_rules` (`from_sale_number`, `commission_amount`)

**Calculation:** `app/Services/LastClickAttributionService::recordConversion()` (~L130-250).
- **tiered:** the amount comes from the rule with the highest `from_sale_number` that is ≤ the marketer's sale number on that invitation.
- **fixed / last_click / default:**
  1. `campaign.marketer_commission_amount × qty`.
  2. If that is 0, use `marketer_commission_country_settings` (influencer vs affiliate amount, per country and category).
  3. If that is still 0, use the `MarketerCommissionRateService` rule.
- Then `MarketerProfile::applyCommissionDiscount()` (§2.5).
- Then the flash-sale bonus, if any (§2.6).
- The conversion is stored in `marketer_campaign_conversions`: `commission_amount`, `platform_commission_amount`, `flash_sale_bonus_amount`, `status`, `commissioned`.
- If `commission_budget_spent ≥ max_commission_budget`, the campaign is auto-paused.

**Who pays:** `CampaignOwner`. The vendor pays for vendor-listing campaigns, and the platform pays for admin-listing campaigns. The result is stored in `sub_orders.marketer_commission_owner`. The pricing engine deducts it from the vendor payout or from the platform net.

> **Observation:** `CheckoutPricingEngine` sets `marketer_commission` on the sub-order only when `commission_type === 'fixed'`. For `tiered`, `percentage` and `last_click` campaigns, the sub-order shows 0 even though the conversion row has the real amount. Check this if you reconcile payouts against `sub_orders.marketer_commission`.

**Defaults when a campaign is created:** `MarketerCampaignService::resolveDefaultCommission()` and `resolveDefaultOpenMarketCommission()` fill `marketer_commission_amount` and `platform_commission_amount` from the country settings (§2.3) and the category rules (§2.2). Approval is refused when a non-tiered campaign has a zero marketer commission.

**Approval lifecycle (wallet):**
- `ApproveMarketerConversionsJob` (daily 03:15) approves a conversion once the order is delivered or completed and the return window has passed. The commission goes to the marketer wallet's `pending_balance`.
- `ReleaseMarketerPendingCommissionJob` (daily 03:30) moves it to `balance` after `marketer_payout_clearing_days` (default 3).
- `MarketerConversionReversalService` reverses conversions on cancel, return or refund.

### 2.2 Category rules per marketer (`marketer_commission_rules`)

- Model `MarketerCommissionRule` with scopes `products`, `open_market` and `travel`.
- `marketer_id = NULL` means a platform default. A `NULL` category means the scope default. `excluded_category_ids` lets a default skip some categories.
- Modes are `fixed`, `percentage` and `both`, plus `commission_min_amount` as a per-unit floor.
- `CommissionRuleResolver` order: marketer + category (walking up parents) → marketer default → platform category → platform default.
- `MarketerCommissionRateService` wraps it. The API is `GET /api/marketer/commission-rules` (`Api\Marketer\CommissionRuleController`).
- The older tables `marketer_category_commissions` (products) and `open_market_category_commissions` (classifieds) are still written by `Admin\MarketerController::storeCategoryCommission`, and they have the same modes. `CommissionRuleResolver` reads `marketer_commission_rules`.
- Admin routes: `POST marketers/{id}/category-commissions` and `DELETE …/{commission}`. The marketer-side view is `marketer/finance/commissions.blade.php`.
- The travel scope is defined here, but I did not trace which travel flow consumes it.

### 2.3 Country × category fixed amounts

`marketer_commission_country_settings` holds `influencer_commission_amount` and `affiliate_commission_amount` per category and country. They are edited in Admin → Category → "Marketer commission" (`CategoryController::updateMarketerCommission`, route `categories/{id}/marketer-commission`). The partner listing form (`Partner\ListingController`) also reads them.

### 2.4 Vendor listing influencer % and affiliate %

The vendor sets `influencer_commission_percentage` and `affiliate_commission_percentage` on a listing (`Vendor\ListingService`, `CreateListingRequest`). Minimums come from the settings `min_influencer_commission_percentage` and `min_affiliate_commission_percentage`, both seeded at 5.00. I confirmed validation and storage. I did not find the conversion calculation reading these values; it uses the campaign amount, then the country settings, then the rules.

### 2.5 Marketer commission discount

`MarketerProfile::applyCommissionDiscount()` takes flat or percentage off the marketer's computed commission (enum `MarketerCommissionDiscountType`). It is configured in the admin marketer profile (`admin/marketers/show.blade.php`).

### 2.6 Flash sales

- **Bonus:** `flash_sale_marketer_invitations` has `extra_commission_rate`, `extra_commission_mode` (`percentage`/`fixed`/both) and `extra_commission_flat_amount`. The bonus is computed in `FlashSaleMarketerInvitation::calculateBonus()`. It is paid when the marketer has an accepted invitation to a live flash sale. The amount goes to `flash_sale_bonus_amount` on the conversion and is part of the total commission.
- **`flash_sales.commission_override_pct`:** it is in the admin create and edit forms and in the vendor API resource. The only consumer I found is `FlashSaleAnalyticsJob`, which uses `commission_override_pct ?? 10.0`. It does not change the commission actually charged at checkout.

### 2.7 Admin and vendor flows for campaigns

- Admin: `Admin\MarketerCampaignController` (`updateCommission` at `PATCH marketer-campaigns/{id}/commission`, plus approve and request-approval, with `marketer_commission_amount` and `platform_commission_amount` required).
- Vendor: `Partner\MarketerCampaignController` (the same two fields) and `partner/marketer_campaigns/*`.
- Marketer panel: campaigns, orders, reports, `finance/commissions`, and `flash-sales` / `invitations`.

---

## 3. Vendor acquisition-agent commission (internal staff)

- Models: `VendorAcquisitionCommission` and `VendorAcquisitionCommissionEarning`. The earner is an admin user (`admin_id`).
- Fields: `commission_rate` (integer in basis points), `monthly_min_sales`, `monthly_max_sales`, `valid_from/until`, `status` (`active`/`expired`/`revoked`) and `total_earned`.
- Job: `ProcessAcquisitionCommissionsJob` runs monthly on the 1st at 02:00. For each active deal it takes the previous month's `Completed` sub-orders for that vendor. If the count ≥ `monthly_min_sales`, it pays on up to `monthly_max_sales` orders at `subtotal × rate / 10000`, and sets the status to `expired` after `valid_until`.
- Admin UI: Admin → Vendors → "Acquisition agent" (`VendorAcquisitionController`), the global list at `/acquisition-commissions`, and the agent's own view at `/my-acquisition-commissions`.
- It is separate from the platform commission and is not deducted from the vendor.

---

## 4. Travel, carrier and delivery panels

### 4.1 Travel

- **Platform commission:** `config/travel.php` → `platform_commission_rate` (env `TRAVEL_PLATFORM_COMMISSION_RATE`, default `0.10`, a flat 10%).
- It is computed on the fly (`total_price × rate`) in the travel-agency Finance and Report controllers, both web and API. No commission column exists on `travel_bookings`. The panel reports show commission and net revenue/payout (`travel-agency/finance/*`, `reports/revenue`). I did not find a ledger entry or payout deduction for this commission, so treat it as a reporting figure unless that is verified elsewhere.
- **Agency campaign offers to marketers:** `TravelAgencyCampaignOffer` has `offered_commission_rate` (0-100) and `commission_type` (`CommissionType`). `TravelAgencyCampaignOfferPackage.commission_override` gives a per-package rate. The form is in the travel-agency panel (`TravelAgencyPortal\CampaignController`, `travel-agency/campaigns/create.blade.php`). Admin approves the offers, and marketers get invitations.
- `travel_app` (Flutter) has no commission code.

### 4.2 Carrier panel and carrier_app

No commission logic. A search of the carrier routes, views and Flutter code found nothing. Carrier money items are rates, not commissions:
- `shipping_rates.base_fee`, `carrier_rate`, `rate_per_kg`, `cod_extra_fee`.
- `ShippingRate::getGapAttribute()` is `carrier_rate − base_fee`, the amount the platform absorbs. It shows in the engine as shipping revenue, `shipping_gap`, and `admin_subsidy`.

### 4.3 Delivery agents and delivery_app

Agents get fees, not percentage commissions:
- `delivery_agents.per_delivery_fee` becomes a `delivery_agent_earnings` row (`earning_type = base_fee`) on delivery.
- `delivery_zones.cod_fee` becomes a `cod_handling` earning for COD orders.
- Both are in `Delivery\AssignmentService` (~L235-290). Shift totals use the same figures.
- `delivery_app` has no commission code.

---

## 5. Frontend (Next.js `frontend/`)

There is no commission logic and no reference to the word "commission" in `src`, `app`, `types` or `locale/en.json`. The storefront has only the referral and marketer parts:
- Referral link and QR: `/[locale]/r/[code]` (`app/[locale]/r/[code]/page.tsx`), and `profile/qr-code`.
- The `referral_code` is sent at signup (`src/services/auth.ts`).
- Marketer type badges: influencer vs affiliate (`marketers-list`, `marketer-profile`).
- Attribution happens on the backend (`Api\ReferralTrackingController`, `LastClickAttributionService`).

All commission amounts are computed and shown only in the backend panels and in `partner_app`.

## 5b. Partner Flutter app (`partner_app`)

It shows vendor commission data from `GET …/finance/commission-rates` and payout details: `features/finance/commission_rates_screen.dart`, `finance_screen.dart`, `payout_detail_screen.dart`, `orders/order_detail_screen.dart`. It is read-only.

---

## 6. Related fees (not "commission" but in the same split)

- **Gateway fee:** `gateway_fee_pct` + fixed, split pro-rata across groups, and borne by the vendor on vendor groups.
- **COD fee** and **warranty revenue:** order-level and added to platform net.
- **Shipping:** `chargedShipping − carrierShippingCost` is shipping revenue. Vendor contribution and admin subsidy cover shortfalls (`PlatformShippingSubsidy`).
- **Marketplace shipping rules:** `marketplace_shipping_rules` has `commission_type` (`fixed`/`percentage`/`mixed`, enum `MarketplaceShippingRuleCommissionType`), `commission_value` and `extra_delivery_fee`. Admin edits it on the admin listing (`AdminListingController` ~L536). It is stored and displayed (`commissionLabel()`), and I found no use in the checkout calculation.
- **Influencer fee:** `MarketerInfluencerFeeCountrySetting.fee_per_influencer` (Admin → Marketer settings). It is a per-country fee setting, not a commission.
- **FBN fees:** inbound and overage jobs (`fbn:compute-daily-overage`). These are separate from commission.
- **Paid ads:** ad slot bookings, with ROI computed from marketer commission earned versus ad spend (`AdAttributionService`).

---

## 7. Gaps and things to verify

1. **Subscription discount not wired.** `commission_discount_pct` is stored and editable but nothing applies it (§1.6).
2. **`commissions` table has no writer.** The highest-priority rule layer cannot be managed from the UI (§1.2).
3. **Flash-sale `commission_override_pct`** only affects analytics, with a hardcoded 10% fallback (§2.6).
4. **Sub-order `marketer_commission`** is non-zero only for `fixed` campaigns (§2.1).
5. **Two parallel marketer rule stores:** `marketer_commission_rules` (read by the resolver) versus `marketer_category_commissions` / `open_market_category_commissions` (still written by the admin controller). Confirm that edits made in the admin UI actually reach the table the resolver reads.
6. **Vendor listing influencer/affiliate %** and **marketplace shipping rule commission** are stored but I found no consumer in the commission calculation.
7. **Travel commission** is a flat env-driven report figure with no stored ledger or payout deduction found.
8. **Not traced:** which flow consumes the `travel` scope of `marketer_commission_rules`.

Not covered: the `worktrees/` copies (excluded as duplicates), tests (except where cited), and runtime database values. I read code only and did not query the database.
