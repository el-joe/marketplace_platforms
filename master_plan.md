# E-Commerce Master Execution Plan
**Repo:** `el-joe/marketplace_platforms` | **Commit:** `24342ff` | **Date:** 20 Sep 2026  
**Stack:** Laravel 11 (backend) · Next.js 15 App Router (frontend) · MySQL 8  
**Host pattern:** `admin.noon.codefanz.com` / `partner.noon.codefanz.com` / `marketer.noon.codefanz.com` / `noon.codefanz.com`

> **Ground truth:** Every formula, table, and route in this document was extracted from the live codebase — not assumed. Agents must read the referenced files before touching anything.

---

## 1. Business & Architectural Analysis

### 1.1 Money Invariant (NEVER VIOLATE)
All monetary values in the database and all API payloads are **BIGINT base-currency integers**.  
`500` = 500 AED (not 5.00, not 50000 cents).  
The `<Price>` React component and `number_format` in Blade handle display.  
**No `/100`, no `*100`, no `.toFixed(2)` on raw DB values.**

### 1.2 Identity & Source of Every Cart Line
```
cart_items
  ├── vendor_listing_id   (vendor's own listing)
  ├── admin_listing_id    (platform listing — admin-owned)
  └── marketer_listing_id (marketer listing — resolves via CartLineSource::resolve()
                           to its fulfilment listing for weight/inventory lookups)
```
`CartLineSource::resolve($cartItem)` returns the underlying `VendorListing` or `AdminListing` for any line type. Any agent touching inventory, shipping, or commission MUST call this.

### 1.3 Checkout Pricing Engine — Equations (from `CheckoutPricingEngine.php`)

```
Per line:
  line_subtotal  = unit_price × quantity
  commission_amt = FLOOR(line_subtotal × commission_rate_pct / 100) + commission_fixed × quantity
  vendor_payout_share = line_subtotal - commission_amt

Per vendor sub-order group:
  gross            = Σ line_subtotal
  raw_commission   = Σ commission_amt  (before any vendor discount)
  commission_after_discount = raw_commission - FLOOR(raw_commission × commission_discount_pct / 100)
                              - commission_discount_flat
  gateway_fee      = FLOOR(gross × gateway_fee_rate_pct / 100) + gateway_fee_fixed   (0 for COD)
  vendor_coupon_cost = vendor's share of coupon (per coupon.funded_by split)
  marketer_commission = tiered or flat per MarketerCommissionCountrySetting
  vendor_contribution = vendor_covers_delivery ? customer_shipping_fee : 0
  vendor_payout    = gross
                   - vendor_coupon_cost
                   - commission_after_discount
                   - gateway_fee
                   - vendor_contribution
                   - (marketer_commission IF marketer_commission_owner = 'vendor')

Order-level:
  order.subtotal   = Σ gross (all groups)
  order.discount   = coupon_discount
  order.shipping   = Σ customer_pays_shipping (all groups)
  order.tax        = FLOOR((subtotal - discount) × vat_rate / 100)
  order.cod_fee    = applicable if payment_method = 'cod'
  order.warranty_total = Σ warranty_plan.price × qty
  pre_deduction    = subtotal - discount + shipping + tax + cod_fee + warranty_total
  - gift_card_deduction = min(gift_card.balance, pre_deduction)
  - wallet_deduction    = min(wallet.balance, remaining_after_gift_card)
  order.total      = remaining_after_all_deductions

Platform net per order:
  platform_net = Σ commission_after_discount
               + Σ shipping_revenue (raw_fee - vendor_contribution)
               - Σ platform_coupon_cost
               - Σ admin_subsidy (exceptional zone)
               - Σ (marketer_commission WHERE owner = 'platform')
               - Σ gateway_fee (admin/platform sub-orders only)
               + cod_fee + warranty_total
```

### 1.4 Shipping Subsidy & Exceptional Zone Formula
```
billable_weight_grams = MAX(actual_weight, volumetric_weight)
  where volumetric = L_cm × W_cm × H_cm / 5000 × 1000

raw_fee = base_fee + FLOOR((billable_weight - min_weight) / rate_unit × rate_per_unit)
        + weight_slab_surcharge

subsidy_cap = min(platform_shipping_subsidy.subsidy_cap, raw_fee)
customer_pays = max(0, raw_fee - subsidy_cap)
  if vendor_covers_delivery: customer_pays = 0, vendor_contribution = raw_fee - subsidy_cap

Exceptional zone gap:
  shipping_gap = carrier_actual_cost - customer_pays
  admin_subsidy_amount = FLOOR(shipping_gap × subsidy.platform_share_pct / 100)
  vendor_contribution_amount = shipping_gap - admin_subsidy_amount
```

### 1.5 Sponsored Ads — CPC/CPM Formula
```
CPC (Cost Per Click):
  cost_charged_per_click = campaign.bid
  charged when: click recorded AND campaign.type = 'cpc'
  budget check: budget_spent_total + bid ≤ budget_total
                budget_spent_today + bid ≤ budget_daily (if set)

CPM (Cost Per Mille Impressions):
  cost_charged_per_1000 = campaign.bid
  currently NOT deducted per impression — cost_charged = 0 at impression time
  ⚠️ GAP: CPM billing deduction is not automated. budget_spent_today never increments for CPM.

Ad Slot (Paid Ad Booking) — Fixed/CPM/CPC:
  fixed: charged at booking confirmation (one-shot from wallet/payout)
  cpm/cpc: charged daily via PaidAdSchedulerJob from paid_ad_charges
```

### 1.6 Payout Ledger Formula
```
payout.gross_sales         = Σ sub_orders.vendor_payout (delivered + completed, COD: + cod_remittance_confirmed)
payout.commission          = Σ sub_orders.platform_commission (already deducted in vendor_payout)
payout.gateway_fee_deducted= Σ sub_orders.gateway_fee
payout.refunds_deducted    = Σ refunds.amount WHERE vendor_charged_back = true
payout.ad_fees             = Σ paid_ad_charges.amount (settled)
payout.net_amount          = gross_sales - refunds_deducted - chargebacks_deducted
                           - storage_fees - ad_fees - other_adjustments
                           (commission and gateway_fee are already baked into gross_sales/vendor_payout)
```

### 1.7 Key Gaps Found in Codebase
| # | Gap | Severity | Location |
|---|---|---|---|
| G1 | CPM billing never deducts from budget | 🔴 | `SponsoredAdController` — `cost_charged = 0` for CPM |
| G2 | `carrier_shipping_cost` never populated | 🟠 | No carrier webhook or manual update flow |
| G3 | `storage_fees` and `other_adjustments` hardcoded 0 | 🟡 | `PayoutCalculationService` |
| G4 | `order.completed` auto-transition missing | 🟡 | `AutoCompleteOrdersJob` does not set `completed_at` |
| G5 | Invoice returns JSON not PDF | 🟢 | `OrderController::invoice()` |
| G6 | Admin/partner Blade pages show text currency code, not SVG symbol | 🟢 | ~480 `number_format` sites |

---

## 2. Global Rules

| Rule | Value |
|---|---|
| Money storage | BIGINT base-currency (no cents) |
| Money display | `<Price>` component (frontend) · `number_format($v, 2)` (Blade) |
| Primary key type | `char(36)` UUID via `HasUuids` — except `payouts.id`, `files.id` (BIGINT) |
| Auth guards | `customer` · `vendor` · `admin` · `marketer` · `delivery` · `carrier` · `travel_agency` |
| Ad injection order | AFTER renderer cache (never inside cached blocks) |
| Shipping fallback | If no `selected_shipping_method_id`, use `category_shipping_methods` fallback → cheapest active method |
| COD → International transition | If delivery address country ≠ vendor country → COD unavailable, force online gateway |
| Coupon stacking | One coupon OR one promo_code, never both. Checked in `CartService::applyCoupon()` |
| Commission snapshot | Snapshotted at order creation — changing category commission does NOT affect existing orders |

---

## 3. Sub-Agent Execution Prompts

> **How to use:** Copy each prompt into the VS Code Claude Code extension chat. Run them in order. Each agent MUST read the files listed under "Read first" before writing a single line.

---

### Task 1: Cart — Buy-Together & Fallback Shipping Method

**Context:** The `cart_items` table has `selected_shipping_method_id` (nullable). If null, checkout must fall back to the cheapest available method from `category_shipping_methods` or `shipping_fallback_rules`. Buy-Together items are added via `POST /cart/items/bulk`.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL before writing anything:
- backend/app/Http/Controllers/Customer/CartController.php (addItems method ~line 359)
- backend/app/Services/Customer/CartService.php (addItem, addMarketerItem, resolveMarketerCartItems)
- backend/app/Models/CartItem.php
- backend/database/migrations/*cart* (all cart migrations)
- backend/app/Http/Requests/Customer/AddCartItemsRequest.php

TASK: Audit the Buy-Together bulk add flow (POST /cart/items/bulk) and the shipping method fallback.

CHECK LIST:
1. When a customer adds a Buy-Together bundle, does addItems() correctly handle mixed listing types (vendor + admin + marketer)?
2. If `selected_shipping_method_id` is null on a cart_item at checkout, verify that CheckoutController::shippingMethods() selects the correct fallback (cheapest active method from category_shipping_methods → shipping_fallback_rules).
3. Verify that `CartLineSource::resolve()` is called — not `$item->vendorListing` directly — for any weight/inventory lookup involving marketer listings.
4. Confirm inventory lock is created in `cart_inventory_locks` for all listing types.

INVARIANTS (NEVER VIOLATE):
- All prices are BIGINT base-currency integers. No /100, no *100.
- Use CartLineSource::resolve($cartItem) for any weight/shipping/inventory access on cart lines.

OUTPUT: Report any bugs found with the exact file, line number, and the corrected code. If no bugs, confirm each checkpoint passed.
```

---

### Task 2: Checkout — Address, COD Eligibility & International Shipping Transition

**Context:** When the delivery address country differs from the vendor's country, COD must be blocked and international shipping rates applied. `CodValidationService` handles limits. `InternationalShippingEligibilityController` marks listings as ship-to eligible.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL before writing anything:
- backend/app/Services/Customer/CodValidationService.php
- backend/app/Http/Controllers/Customer/CheckoutController.php (shippingMethods() ~line 242, prepare() ~line 312)
- backend/app/Services/Checkout/CheckoutPricingEngine.php
- backend/app/Services/ShippingSubsidyService.php
- backend/app/Models/InternationalShippingEligibility.php
- backend/app/Models/InternationalShippingRate.php

TASK: Validate the COD-to-international-shipping transition and the exceptional zone subsidy split.

CHECK LIST:
1. When `order_address.country_id ≠ sub_order.origin_country_id`, confirm COD is automatically removed from available payment options in the prepare() response.
2. Verify that `cod_global_max_amount` and `cod_supermall_max_amount` from content-settings are correctly enforced in `CodValidationService`. Admin listings (admin_listing_id IS NOT NULL) must be exempt from COD limits.
3. Confirm that international_shipping_eligibility is checked per listing before allowing cross-border checkout.
4. Verify the exceptional zone shipping_gap formula:
   shipping_gap = carrier_actual_cost - customer_pays
   admin_subsidy = FLOOR(shipping_gap × platform_share_pct / 100)
   vendor_contribution = shipping_gap - admin_subsidy
   These must be stored on sub_orders.admin_subsidy_amount and vendor_contribution_amount.
5. Confirm FX rate is captured at order placement time (fx_rate_numerator/denominator on sub_orders).

OUTPUT: Report exact file + line for any violation. Fix if found.
```

---

### Task 3: Checkout Financial Engine — Vendor Payout, Commission & Gateway Fee

**Context:** The exact vendor payout formula lives in `CheckoutPricingEngine::buildSubOrderSplits()`. Gateway fee is deducted from vendor_payout, not added to order.total.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL before writing anything:
- backend/app/Services/Checkout/CheckoutPricingEngine.php (ENTIRE FILE — critical)
- backend/app/Http/Controllers/Customer/CheckoutController.php (placeOrder ~line 611)
- backend/app/Models/CountryPaymentGateway.php
- backend/app/Services/CheckoutCalculationService.php
- backend/tests/Feature/ScenarioTest.php (p03_order_money_split test)

VERIFY these exact formulas are implemented correctly:

vendor_payout = gross
              - vendor_coupon_cost
              - commission_after_discount
              - gateway_fee
              - vendor_contribution
              - (marketer_commission IF marketer_commission_owner = 'vendor')

gateway_fee = FLOOR(gross × gateway_fee_rate_pct / 100) + gateway_fee_fixed
  → 0 for COD orders
  → gateway_fee is deducted from VENDOR, not added to customer total

platform_net_per_group = commission_after_discount
                       + shipping_revenue
                       - platform_coupon_cost
                       - admin_subsidy
                       - (marketer_commission IF owner = 'platform')
                       - gateway_fee (admin/platform groups ONLY)

CHECK LIST:
1. Is gateway_fee correctly set to 0 for COD orders?
2. Is gateway_fee NOT added to order.total?
3. Is commission_discount (flat or percentage) applied before gateway_fee deduction?
4. For marketer listings: is marketer_commission_owner correctly set (vendor vs platform)?
5. Run the test: php artisan test --filter=test_p03_order_money_split_vendor_platform_marketer_shipping

OUTPUT: Exact lines for any formula deviation. Fix and confirm test passes.
```

---

### Task 4: Payment — Wallet, Gift Card & Bank Transfer

**Context:** Wallet is handled internally (never through PaymentGatewayFactory). Gift card deduction is pre-wallet. Bank transfer shows payment instructions in order detail permanently.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL before writing anything:
- backend/app/Http/Controllers/Customer/CheckoutController.php (placeOrder, wallet handling ~line 950+)
- backend/app/Services/CheckoutWalletService.php
- backend/app/Http/Controllers/Customer/ApiOrderController.php (show method — bank_transfer_details)
- backend/app/Services/PaymentService.php
- backend/app/Services/Payments/PaymentGatewayFactory.php

CHECK LIST:
1. Wallet gateway: confirm `elseif ($isWallet)` block exists BEFORE the `else { initiatePayment() }` block. Wallet must NEVER go through PaymentGatewayFactory.
2. If wallet_amount_to_use = 0 or null with gateway_code = 'wallet', confirm a 422 is returned BEFORE any DB transaction begins (no orphan cancelled orders).
3. Gift card: deducted BEFORE wallet. Verify order: gift_card → wallet → remaining to gateway.
4. Bank transfer: confirm `bank_transfer_details` (bank name, IBAN, reference = order_number, amount) is returned in GET /api/customer/v1/{country}/orders/{order_number} — not just on the success page.
5. Idempotency: placing the same order twice with same idempotency_key returns the existing order without double-charging.

Run: php artisan test --filter=test_p05_payment_methods_wallet_cod_gateway_bank_transfer

OUTPUT: Exact file + line for any issue. Fix and confirm test passes.
```

---

### Task 5: Sponsored Ads — CPC Billing & CPM Gap Fix

**Context:** CPC billing is implemented. CPM billing is NOT — `cost_charged = 0` for all impressions and budget never decrements for CPM campaigns. This is Gap G1.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL before writing anything:
- backend/app/Http/Controllers/Customer/SponsoredAdController.php
- backend/app/Services/Customer/SponsoredProductService.php
- backend/app/Models/AdCampaign.php (type enum: cpc|cpm)
- backend/app/Models/AdImpression.php
- backend/app/Models/AdDailyStat.php

CONFIRMED EXISTING BEHAVIOR:
- CPC: cost_charged = campaign.bid per click. budget_spent_total and budget_spent_today increment. ✅
- CPM: cost_charged = 0 always. budget never decrements. ❌ GAP

TASK: Implement CPM billing.

CPM FORMULA:
cost_per_impression = FLOOR(campaign.bid / 1000)
  → Charged at impression time (not click time) for CPM campaigns
  → Deduct: campaign.budget_spent_total += cost_per_impression
  → Deduct: campaign.budget_spent_today += cost_per_impression
  → Update: ad_impressions.cost_charged = cost_per_impression
  → Update: ad_daily_stats: spend += cost_per_impression, impressions += 1

IMPLEMENTATION STEPS:
1. In SponsoredProductService::recordImpression() (the dispatch closure), add:
   if ($campaign->type === 'cpm') {
       $cost = (int) floor($campaign->bid / 1000);
       if ($cost > 0) {
           // Check budget before recording
           if ($campaign->budget_spent_total + $cost > $campaign->budget_total) { return; }
           if ($campaign->budget_daily && $campaign->budget_spent_today + $cost > $campaign->budget_daily) { return; }
           AdImpression fields: cost_charged = $cost
           $campaign->increment('budget_spent_total', $cost);
           $campaign->increment('budget_spent_today', $cost);
       }
   }
2. In SponsoredAdController::click(): CPC click cost stays unchanged.
3. Add a daily reset job for budget_spent_today at midnight (if not already present — check Kernel.php).
4. Write a test in tests/Feature/Ads/ verifying CPM budget decrements on impression.

INVARIANTS: All amounts BIGINT. No floats in DB.

OUTPUT: Show the exact diff. Run php artisan test --filter Ads after applying.
```

---

### Task 6: Paid Ad Slots — Booking, Approval & Billing

**Context:** Paid Ad Slots (banner positions) are separate from Sponsored Ads (product cards). They use `paid_ad_bookings`, `paid_ad_charges`, `paid_ad_slots`. The `PaidAdSchedulerJob` runs every 5 minutes.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL:
- backend/app/Services/Ads/AdBookingService.php
- backend/app/Services/Ads/AdBillingService.php
- backend/app/Jobs/PaidAdSchedulerJob.php
- backend/app/Models/PaidAdSlot.php
- backend/app/Models/PaidAdBooking.php
- backend/app/Models/PaidAdCharge.php

VERIFY this full booking lifecycle:
1. Vendor submits booking → status = 'pending_review'
2. Admin approves creative + booking → status = 'approved' → 'scheduled'
3. PaidAdSchedulerJob (every 5 min) transitions 'scheduled' → 'active' on booked_from date
4. For fixed_daily/weekly/monthly: PaidAdCharge is created at approval (one-shot debit from vendor wallet)
5. For CPM/CPC booked slots: PaidAdCharge is created daily by the scheduler
6. On booked_until date: PaidAdSchedulerJob transitions 'active' → 'completed'
7. Budget exhaustion: transitions 'active' → 'completed' mid-flight

CHECK LIST:
1. Does PaidAdSchedulerJob correctly handle timezone (country timezone, not UTC) for activation?
2. For CPM paid slots: is cost_charged per 1000 impressions tracked in paid_ad_daily_stats?
3. Is vendor wallet debited BEFORE the ad goes live (not after)?
4. Page builder guard: when admin tries to delete/hide a block with active bookings, does AdSlotBlockGuard return 409?
5. Does bound_item_id correctly remap item_position when slides are reordered?

OUTPUT: Report any lifecycle gap with file + line. Fix and confirm php artisan test --filter=AdBooking passes.
```

---

### Task 7: Marketer Commission — Attribution, Tiered Rules & Wallet Credit

**Context:** Attribution uses last-click (mkt_ref cookie, 30-day window). Tiered rules in `marketer_campaign_tiered_rules` allow different commission rates per sale number. Wallet credited after order is confirmed.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL:
- backend/app/Services/LastClickAttributionService.php
- backend/app/Jobs/MonitorCampaignConversionJob.php  (or wherever conversion is processed)
- backend/app/Models/MarketerCampaignConversion.php
- backend/app/Models/MarketerCampaignTieredRule.php
- backend/app/Models/MarketerCategoryCommission.php
- backend/app/Services/WalletService.php
- backend/tests/Feature/MarketerAttributionConversionTest.php

VERIFY this full marketer commission flow:
1. Customer visits /r/{referral_code} → mkt_ref cookie set (30-day expiry)
2. Customer places order → attribution recorded in marketer_campaign_conversions
3. Commission calculated:
   IF tiered rules exist:
     commission = tiered_rule.commission_amount WHERE from_sale_number ≤ conversion.total_sales ≤ to_sale_number
   ELSE:
     commission = marketer_category_commission.commission_amount (flat, by category)
   ELSE fallback:
     commission = marketer_commission_country_settings.commission_amount
4. Wallet credited: WalletService::credit(marketer_wallet, commission_amount, 'marketer_campaign_conversion', conversion.id)
5. If marketer_commission_owner = 'vendor': deducted from vendor_payout. If 'platform': from platform_net.

CHECK LIST:
1. Is the attribution service correctly reading the mkt_ref cookie (not just the query param)?
2. Does the tiered rule lookup correctly use total conversions as the "sale number"?
3. Is the influencer platform fee debited from the marketer's wallet at campaign ACCEPTANCE (not just recorded)?
   Check: MarketerCampaignService::acceptInvitation() — must call WalletService::debit() for influencer fee.
4. Run: php artisan test --filter=MarketerAttributionConversionTest

OUTPUT: Exact line for any gap. Fix and confirm test passes.
```

---

### Task 8: Warranty & Returns — Full Lifecycle

**Context:** Warranty purchase happens at cart time. Claim is only possible after delivery within the return window. Return request can be approved (restock) or rejected. Refund is created automatically on approved return.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL:
- backend/app/Services/ReturnRequestService.php
- backend/app/Http/Controllers/Admin/ReturnController.php (inspect() method)
- backend/app/Services/OrderInterventionService.php (processRefund() ~line 345)
- backend/app/Models/WarrantyPurchase.php
- backend/app/Models/WarrantyClaim.php
- backend/tests/Feature/WarrantyLifecycleTest.php
- backend/tests/Feature/ReturnRequestLifecycleTest.php

VERIFY this full warranty + return lifecycle:
1. WARRANTY PURCHASE: warranty_plan selected at cart → warranty_purchase created at order placement → activated when sub_order.status = 'delivered'
2. WARRANTY CLAIM: only after delivery, within warranty window → warranty_claims created → admin reviews
3. RETURN REQUEST: customer creates → admin inspects → if 'accepted':
   a. ReturnController::inspect() calls OrderInterventionService::processRefund()
   b. Refund row is created (status: approved)
   c. vendor_charged_back determines if deducted from vendor payout
   d. Inventory is restocked (WarehouseInventory::increment)
4. REFUND FORMULA:
   net_refund = amount - gateway_fee_deducted - tax_deducted  (GENERATED column in DB)

CHECK LIST:
1. Does ReturnController::inspect() create a Refund row automatically on acceptance? (Known gap — verify fix was applied)
2. Is warranty claim blocked before delivery? Test: create claim before sub_order.delivered_at is set.
3. Is return_eligible_until correctly calculated at order item creation?
4. Run: php artisan test --filter="WarrantyLifecycleTest|ReturnRequestLifecycleTest"

OUTPUT: Report any gap. Fix and confirm both test suites pass.
```

---

### Task 9: Frontend Checkout — Payment Summary, Coupon & Wallet Display

**Context:** `payment-summary.tsx` was fixed to show all line items. The coupon is now auto-loaded from `cart.coupon` in `prepare()`. Wallet gateway returns 422 before order creation if amount is 0.

**Prompt for VS Code:**

```
You are a Senior Next.js 15 engineer on the noon.codefanz.com marketplace.

Read these files IN FULL:
- frontend/src/features/noon/checkout/payment-summary.tsx
- frontend/src/features/noon/checkout/index.tsx
- frontend/src/features/noon/checkout/types/checkout.type.ts
- frontend/src/features/noon/checkout/helpers/use-checkout.ts
- frontend/locale/en.json (checkout section)
- frontend/locale/ar.json (checkout section)

VERIFY the checkout payment summary shows ALL of these line items when non-zero:
✅ subtotal
✅ discount (coupon)
✅ loyalty_discount
✅ shipping (shows "Free" badge when 0)
✅ cod_fee (only when payment_method = COD)
✅ warranty_total
✅ tax
✅ gift_card_applied (green, deduction)
✅ wallet_deduction (green, deduction)
✅ total (bold, always shown)

CHECK LIST:
1. Does the checkout summary show the COD fee line only when COD is selected?
2. Does the coupon discount appear without re-entering the code (auto-loaded from cart)?
3. Is the shipping formatted with <Price> component (not raw number)?
4. Does changing payment method from COD to card remove the cod_fee line dynamically?
5. Are all locale keys present in both en.json and ar.json: codFee, warrantyTotal, giftCardApplied, walletUsed, loyaltyDiscount, free?
6. Run: npx tsc --noEmit from frontend/ — zero errors in changed files

OUTPUT: Report missing items with exact component and line. Fix and confirm.
```

---

### Task 10: Payout Ledger — Vendor Earnings Reconciliation

**Context:** `PayoutCalculationService` aggregates `sub_orders.vendor_payout` for delivered/completed orders. COD orders: only after `cod_remittance_confirmed = true`. Ad fees from `paid_ad_charges`.

**Prompt for VS Code:**

```
You are a Senior Laravel engineer on the noon.codefanz.com marketplace.

Read these files IN FULL:
- backend/app/Services/PayoutCalculationService.php
- backend/app/Models/Payout.php
- backend/app/Models/PayoutItem.php
- backend/app/Http/Controllers/Admin/PayoutController.php
- backend/tests/Feature/PayoutLedgerReconciliationTest.php

VERIFY the payout formula:
payout.net_amount = gross_sales
                  - refunds_deducted       (vendor_charged_back = true refunds only)
                  - chargebacks_deducted
                  - storage_fees           (currently 0 — acceptable)
                  - ad_fees                (settled paid_ad_charges)
                  - other_adjustments      (currently 0 — acceptable)

NOTE: commission and gateway_fee are already BAKED INTO vendor_payout (not deducted separately here).

CHECK LIST:
1. Are COD sub_orders excluded from gross_sales until cod_remittance_confirmed = true?
2. Are refunds with vendor_charged_back = false correctly EXCLUDED (platform bears them)?
3. Is ad_fees correctly summed from paid_ad_charges WHERE status = 'settled' AND vendor_id matches?
4. Does the admin receive a notification when a vendor's payout is generated?
5. Run: php artisan test --filter=PayoutLedgerReconciliationTest

OUTPUT: Any deviation from formula = critical bug. Fix and confirm test passes.
```

---

### Task 11: Admin Panel — Order Detail & Financial Transparency

**Context:** Admin order detail at `admin.noon.codefanz.com/orders/{id}` must show: vendor breakdown, commission per item, gateway fee, marketer commission, custom attribute values, and sub-order shipping gap.

**Prompt for VS Code:**

```
You are a Senior Laravel/Blade engineer on the noon.codefanz.com marketplace.

Read these files IN FULL:
- backend/app/Http/Controllers/Admin/OrderController.php (show() method)
- backend/resources/views/admin/orders/show.blade.php
- backend/app/Http/Controllers/Admin/SubOrderController.php (if exists)

VERIFY the admin order show page displays:
1. Per sub-order: subtotal, shipping, platform_commission, gateway_fee, vendor_payout, marketer_commission, marketer_commission_owner
2. Per order_item: commission_amount, commission_rate_pct, vendor_coupon_cost, custom attribute values
3. Exceptional zone: shipping_gap, admin_subsidy_amount, vendor_contribution_amount
4. International: fx_rate_numerator/denominator, origin_country_id vs delivery country
5. Custom attributes: order_item_custom_attribute_values displayed as "Label: Value (unit)"
6. Warranty: warranty_purchase_id linked to warranty details

CHECK LIST:
1. Is `$order->load(['subOrders.items.customAttributeValues', ...])` eager-loading ALL needed relations (no N+1)?
2. Is FX rate shown on international sub-orders?
3. Are all BIGINT amounts displayed via number_format($value, 2) (not divided by 100)?

OUTPUT: Report missing data, N+1 queries, or wrong formatting. Fix and confirm.
```

---

### Task 12: QC — Full Test Suite Health Check

**Context:** 380 feature tests, 1,319 assertions. Pre-existing failures: `ListingDetailPerformanceTest` (62 queries vs budget 20) and `ExampleTest` (404 on GET /). These are known and acceptable.

**Prompt for VS Code:**

```
You are a QC engineer on the noon.codefanz.com marketplace.

Run the full backend test suite:
php artisan test --parallel 2>&1 | tee /tmp/test-results.txt

EXPECTED RESULTS:
- Total: ~380+ tests
- Pre-existing failures (IGNORE): 
  * Customer\ListingDetailPerformanceTest::test_pdp_query_count_is_within_budget
  * ExampleTest::test_the_application_returns_a_successful_response
- All other tests: MUST PASS

IF ANY UNEXPECTED FAILURE:
1. Read the failing test to understand what it verifies
2. Read the service/controller it tests
3. Fix the root cause (not the test)
4. Re-run to confirm green

ALSO run frontend type-check:
cd frontend && npx tsc --noEmit 2>&1 | grep -v ".next/types" | head -20

ACCEPTABLE: Only .next/types/validator.ts errors (pre-existing, unrelated to our code)
UNACCEPTABLE: Any error in src/ files

OUTPUT: Paste the test summary line "X tests, Y assertions, Z failures". List any unexpected failure with the fix applied.
```

---

## 4. Architecture Diagrams (Text)

### Order Lifecycle State Machine
```
PLACED → CONFIRMED → PARTIALLY_SHIPPED → SHIPPED → PARTIALLY_DELIVERED → DELIVERED → COMPLETED
                                                                              ↓
                                                                         CANCELLED (any stage, admin only)

Sub-order states:
PLACED → CONFIRMED → PROCESSING → PACKED → SHIPPED → OUT_FOR_DELIVERY → DELIVERED
                                                                              ↓
                                                               RETURNED / CANCELLED
```

### Payment Gateway Selection Logic
```
1. Is country_payment_gateway.is_active = true for this country? → show it
2. Is gateway_code = 'wallet'? → handle INTERNALLY (never through PaymentGatewayFactory)
3. Is delivery address international? → BLOCK 'cod', force online
4. Does cart_total > cod_global_max_amount? → HIDE 'cod' (unless admin_listing only)
5. Is gateway_code = 'bank_transfer'? → no real-time processing, show instructions only
```

### Shipping Fee Waterfall
```
billable_weight → raw_fee from shipping_rates table
                → + weight_slab_surcharge
                → - platform_subsidy_cap (PlatformShippingSubsidy)
                → - vendor_contribution (if vendor_covers_delivery = true)
                → = customer_pays (stored in order.shipping)
                
Exceptional zone (post-order):
carrier_actual_cost - customer_pays = shipping_gap
admin_subsidy = FLOOR(gap × platform_share_pct / 100)
vendor_contribution = gap - admin_subsidy
```

### Ad Auction Ranking
```
SELECT vendor_listings.*
  FROM ad_campaign_products acp
  JOIN ad_campaigns ac ON ac.id = acp.ad_campaign_id
ORDER BY 
  ac.quality_score DESC,  -- CTR × relevance × landing × seller (0-10)
  ac.bid DESC             -- BIGINT base currency
LIMIT 3 (positions 1, 5, 9 in search/category grid)
```