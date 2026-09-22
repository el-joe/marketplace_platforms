# Order Pricing Formulas

Source of truth: `app/Services/Checkout/CheckoutPricingEngine.php` (`priceCart()` + `computeMoneySplit()`), `app/Services/ShippingFeeCalculator.php`, `app/Services/MarketerCommissionRateService.php`.

All money is stored as **BIGINT base-currency units** (not cents-scaled). Rounding is `round()`/`floor()` per formula as noted — drift is always absorbed into `admin_subsidy_amount` or `platform_net`, never left unaccounted.

Legend: **[persisted]** = actual DB column. **[derived]** = computed on the fly, not stored.

---

## 1. Item total — `order_items.line_total`

```
line_subtotal   = unit_price * quantity
line_discount   = coupon discount allocated to this line (pro-rata, see §3)
line_tax        = round((line_subtotal - line_discount) * country.vat_rate / 100)

line_total      = line_subtotal - line_discount + line_tax
```

> Warranty is **not** folded into `line_total`. It's tracked separately via `order_items.warranty_purchase_id` → `warranty_purchases.price_paid`. (The in-engine `priceCart()` preview additionally nets out loyalty and adds warranty+its tax into a line-level total, but that shape is **not** what gets persisted to `order_items.line_total` — persistence in `CheckoutController` uses the simpler formula above. Flag this divergence if you touch either code path.)

## 2. Sub total — `orders.subtotal` / `sub_orders.subtotal`

```
order.subtotal    = Σ line_subtotal (all items)
sub_order.subtotal = Σ line_subtotal (items belonging to that vendor, or 'platform' group)
```

## 3. Discount

Two independent discount tracks — **do not add them together as "the" discount; they are separate columns.**

**a) Coupon discount** — `orders.discount`
```
percentage:    discount = round(applicable_subtotal * coupon.value / 100), capped at coupon.max_discount
fixed_amount:  discount = round(coupon.value), capped at coupon.max_discount
bogo:          discount = unit_price of the cheapest applicable line
free_shipping: discount = 0 (shipping fee waived instead)
```
Then capped again: `discount = min(discount, applicable_subtotal)`.
Allocated across lines pro-rata by `line_subtotal` weight (largest-remainder method — no line loses more than its fair share due to rounding).

Vendor/platform funding split (per line, → `order_items.vendor_coupon_cost`, `sub_orders.vendor_coupon_cost` / `platform_coupon_cost`):
```
vendor_share_pct = coupon.funded_by == 'vendor' ? 100
                  : coupon.funded_by == 'shared' ? coupon.vendor_share_pct
                  : 0   // funded_by == 'platform'

vendor_coupon_cost   = round(line_discount * vendor_share_pct / 100)
platform_coupon_cost = line_discount - vendor_coupon_cost
```

**b) Loyalty discount** — `orders.loyalty_discount` (separate column; points tracked in `loyalty_points_used`/`loyalty_points_earned`)
> ⚠️ The service that *computes* `loyalty_discount` from points wasn't located in this pass — only its allocation into `priceCart()` was confirmed. Verify before documenting its formula as final.

## 4. Warranty amount — `warranty_purchases.price_paid`

```
WarrantyPlan::resolvePrice(listingPrice):
  if price_type == 'percentage': floor(listingPrice * price_pct / 100)
  else (flat):                    price
```
Tax on warranty uses the same VAT formula as line tax (§5), tracked alongside but not merged into `line_total`.
`orders.warranty_total` / `sub_orders.warranty_revenue` = Σ warranty prices. **100% platform revenue** — never split to vendor.

## 5. Tax — `order_items.line_tax`, `orders.tax`, `sub_orders.tax`

```
line_tax  = round((line_subtotal - line_discount) * country.vat_rate / 100)
order.tax = Σ line_tax   (no independent recomputation at order level)
```
> ⚠️ There is a separate `tax_rules` table (`TaxRuleType`: vat/gst/sales_tax, `TaxRuleAppliesTo`: product/shipping/both) that does **not** appear wired into this calculation. Confirm whether it's legacy/dead or used elsewhere (e.g. `TaxInvoice`) before relying on it.

## 6. Order total — `orders.total`

```
total = max(0,
    subtotal
  - coupon_discount
  - loyalty_discount
  + shipping_fee
  + cod_fee
  + tax
  + warranty_total
  + customs_duty
  - gift_card_applied
  - wallet_amount_used
)
```

**Customs duty** — `CheckoutPricingEngine.php` ~line 138-183, per international line:
```
customs_duty += shipping_quote.customs_fee   // returned by the international shipping-quote call, summed across lines
```
Itemized and added to `total` (DDP design — never folded silently into shipping). Computed at two call sites (`CheckoutController.php:421` prepare, `:806` place-order) — same dual-path pattern as other pre-order-lock recalculations.

**Gift card** — straight 1:1 deduction, **no fee, no FX conversion**. `GiftCardService::redeem()` (lines 104-160) decrements balance and logs a `gift_card_transactions` row. Currency must match the order's currency exactly or it's rejected (`GiftCardCurrencyMismatchException`, `CheckoutController.php:1447`) — never silently converted.

**Wallet** — straight 1:1 deduction, **no fee, no FX conversion**. `CheckoutWalletService::applyWalletToOrder()` (lines 14-58) guards `wallet_amount_used <= order.total`, requires `wallet.currency === order.currency` (else throws), decrements balance, writes a `wallet_transactions` row. Refund-to-wallet is the same 1:1 credit-back, no fee.

## 7. Order total grouped by vendor / marketer / admin

Grouping key: `sub_orders.seller_type` (`vendor` | `platform`) + `vendor_id`. One `sub_order` row per vendor per order (plus one `platform` row for admin-fulfilled items).

**Vendor payout** (`sub_orders.vendor_payout`):
```
vendor_payout = gross
              - vendor_coupon_cost
              - platform_commission_after_discount
              - gateway_fee
              - vendor_contribution_amount        // §12, shipping-gap subsidy vendor pays
              - (marketer_commission_owner == 'vendor' ? marketer_commission : 0)
```

**Platform net** (derived, accumulated across all sub_order groups):
```
platform_net += platform_commission_after_discount
              + shipping_revenue                  // charged_shipping - carrier_shipping_cost
              - platform_coupon_cost
              - admin_subsidy_amount               // §11
              - (marketer_commission_owner == 'platform' ? marketer_commission : 0)
              - (is_platform_group ? gateway_fee : 0)

// once per order, not per group:
platform_net += cod_fee + warranty_total
```

## 8. Shipping fee — `sub_orders.shipping` (customer-facing charge)

`ShippingFeeCalculator::calculate()`:
```
Admin listings: fee = listing.shipping_cost   (flat, no further calc)

Vendor listings:
  billable_weight = max(declared_weight_grams, volumetric_weight)
  volumetric_weight = floor(L * W * H * 1000 / rate.volumetric_divisor)   // divisor default 5000

  fee = rate.base_fee
      + (billable_weight > rate.min_weight_grams
           ? floor((billable_weight - min_weight_grams) * rate.rate_per_kg / 1000)
           : 0)
      + weight_slab_surcharge(country, method, billable_weight)   // shipping_weight_slabs.extra_fee
      + warehouse_surcharge                                        // warehouse_shipping_surcharges.extra_amount_cents
      + vendor_warehouse_surcharge                                 // vendor_city_shipping_surcharges.extra_amount_cents

  if order_subtotal >= free_shipping_threshold (rate-level or country_shipping_settings):
      fee = 0   // slab/warehouse/vendor surcharges waived too

  fee += marketplace_rule_surcharge   // NOT waived by free-shipping threshold

  if listing.vendor_covers_delivery:
      vendor_contribution_amount += fee
      fee = 0   // customer sees "free delivery"
```

Carrier cost (what the platform actually pays a carrier — internal, not customer-facing):
```
carrier_shipping_cost = rate.carrier_rate + floor(over_grams * rate.carrier_rate_per_kg / 1000)
```
"Exceptional lane" = `rate.carrier_rate > rate.base_fee`. The resulting gap feeds §10.

## 9. COD fee — `orders.cod_fee`

```
cod_fee = is_cod ? shipping_rates.cod_extra_fee : 0
```
Eligibility (not the fee itself) gated by `CodValidationService`: `country.cod_max_amount`, `country.cod_supermall_max_amount` (excludes admin/platform listings); COD is hard-blocked for international lines.
100% credited to `platform_net` (§7).

## 10. Shipping gap (feeds subsidy split, §11/§12)

```
shipping_gap = carrier_shipping_cost - shipping_fee   // sub_orders.shipping_gap
```
Only relevant on "exceptional lane" rates. Subsidy rule lookup: `platform_shipping_subsidies`, scoped by zone + method (+ optional warehouse/carrier, most specific wins), **skipped entirely** if parcel weight exceeds `max_subsidy_weight_grams` (gap then falls 100% on vendor — see §12 "no rule" case).

> ⚠️ There are **two separate, non-composing subsidy implementations** — don't confuse them:
> - `ShippingFeeCalculator.php` (lines 220-298) is the one that produces the **actually persisted** `sub_orders.shipping_gap` / `admin_subsidy_amount` / `vendor_contribution_amount` used everywhere in this doc (§10-12) and in `CheckoutPricingEngine`.
> - `app/Services/ShippingSubsidyService.php::resolve()` is a **separate class** used only by `CheckoutController` for pre-order-lock, checkout-time display (cart-level fee preview / free-shipping messaging), with its own field names (`raw_fee`, `subsidy_cap`, `platform_subsidy`, `customer_pays`). It does not call, and is not called by, `ShippingFeeCalculator`. Confirm both stay in sync if either is changed — they're not guaranteed to today.

## 11. Admin subsidy — `sub_orders.admin_subsidy_amount`

```
no matching rule:        admin_subsidy = 0                     (vendor absorbs full gap)

split_type = 'percentage': admin_subsidy = shipping_gap - vendor_contribution   // see §12
split_type = 'fixed':      admin_subsidy = min(admin_fixed_amount, shipping_gap - vendor_contribution)
                                          + rounding_residual    // zero-sum invariant

cap: if subsidy_cap > 0 and admin_subsidy > subsidy_cap:
       excess = admin_subsidy - subsidy_cap
       admin_subsidy -= excess
       vendor_contribution += excess
```
Reduces `platform_net` (§7).

## 12. Vendor/marketer subsidy → really "vendor contribution" — `sub_orders.vendor_contribution_amount`

> Note: there is **no separate "marketer subsidy."** Only a vendor-side shipping-gap contribution exists in the engine. If marketer-funded subsidy is a real product requirement, it does not exist yet — see Gaps section below.

```
no matching subsidy rule:  vendor_contribution = shipping_gap   (100%)

split_type = 'percentage': vendor_contribution = intdiv(shipping_gap * vendor_share_pct, 100)
split_type = 'fixed':      vendor_contribution = min(vendor_fixed_amount, shipping_gap)
```
Also includes the `vendor_covers_delivery` contribution from §8 when applicable.
Subtracted from `vendor_payout` (§7).

## 13. Marketer commission fee

Two **parallel, seemingly non-reconciled** paths — flag when documenting for finance:

**a) Campaign-based (fixed), used in `computeMoneySplit()` → `order_items.marketer_commission`, `sub_orders.marketer_commission`:**
```
if is_marketer AND commission_type == 'fixed':
    marketer_commission = commission_raw * quantity     // commission_raw from campaign.marketer_commission_amount
```
Base price for commission purposes is always the **vendor's own listing price**, never the marketer's resale price.
`marketer_commission_owner` = `'vendor'` if the campaign is tied to a vendor listing, else `'platform'` — determines whose payout absorbs the cost (§7).

**b) Rate-based (percentage), `MarketerCommissionRateService::calculateCommissionAmount()`** — resolution: `marketer_category_commissions` row for (marketer, category) → marketer's category-null default → 0:
```
rate = resolveRate(marketer, category_id)
commission = floor(base_amount * rate / 100)
```
> ⚠️ Not confirmed wired into checkout at all — may be a different/legacy commission model or a post-sale reconciliation step. **Needs product/eng clarification before finance relies on it.**

Enum: `CommissionType` = `Percentage | FlatPerOrder | FlatPerClick`.

## 13b. Commission discounts & rate reductions (affect vendor/marketer payout, missing from §7/§13)

Three separate, real mechanisms — all reduce what's owed to the platform, i.e. increase vendor/marketer payout. Not to be confused with §13's marketer-commission-owed formulas.

**a) Vendor commission discount** — admin-granted, ad-hoc. `Vendor.php:120-122`, applied against `grossCommission` (the raw platform commission before discount, → `order_items.platform_commission_after_discount`, schema comment confirms this column exists specifically for this):
```
Flat:       discount = min(vendors.commission_discount_flat, grossCommission)
Percentage: discount = floor(grossCommission * vendors.commission_discount_percentage / 100)

commission_after_discount = grossCommission - discount
```

**b) Marketer commission discount** — identical shape, `MarketerProfile.php:113-115`, against `marketer_profiles.commission_discount_type/_flat/_percentage`.

**c) Subscription-plan commission-rate reduction** — different mechanism: reduces the **rate**, not a computed amount. `SubscriptionService.php:173-177`:
```
effective_commission_pct = round(base_commission_pct * (1 - subscription_plan.commission_discount_pct / 100), 4)
```
This feeds into §7's `resolveCommission()` rate resolution *before* the commission amount is computed — so (a)/(b) discount an already-computed amount, while (c) discounts the rate itself. Both can apply to the same vendor; verify whether they stack multiplicatively or whether one supersedes the other before using in finance calculations (not confirmed in this pass).

## 14. Payment fee / gateway fee — `sub_orders.gateway_fee`

```
gateway_fee_total = is_cod OR amount_due_gateway <= 0
    ? 0
    : floor(amount_due_gateway * gateway_fee_pct / 100) + gateway_fee_fixed
```
Charged **once per order** (fixed component not duplicated per vendor), then split pro-rata across sub_order groups by each group's share of gross.
- Vendor groups: already netted into `vendor_payout` (§7) — not subtracted from `platform_net` again.
- Platform/admin group: subtracted directly from `platform_net`.

Config source: `country_payment_gateways.fee_pct` / `fee_fixed`, `is_cod ? 0 : rate`.
`sub_orders.gateway_fee_rate` stores the effective rate snapshot for audit.

### Refund gateway-fee deduction (real fee, not in §14 originally)

`RefundService.php` (~line 79, 153) — for **customer-fault** refunds only, routed back to the gateway:
```
gateway_fee_deducted = original_transaction.gateway_fee * (refund_gross_amount / original_transaction.amount)
vat_on_fee_share      = gateway_fee_deducted * GATEWAY_FEE_TAX_RATE   // = 0.05, hardcoded constant
```
Both amounts are withheld from the refund (customer gets back less than the line total). **Seller/platform/carrier-fault refunds get zero deduction** — the fee is only passed through when the customer caused the return. No restocking fee exists anywhere in the schema (confirmed absent, not merely unimplemented — `RefundService.php:36-39` docblock explicitly notes this). Refunds otherwise **reverse persisted values, they do not recompute** — partial refunds proportionally scale every capture-time account via `reversePartialCapture()`, which is documented as an approximation.

## 14b. FX conversion — recorded for audit, never changes a charged amount

`sub_orders.fx_rate_numerator` / `fx_rate_denominator` / `fx_rate_captured_at` — for international sub-orders, `CurrencyConversionService::convert()` is called and the resulting rate is snapshotted (`CheckoutController.php:1249-1301`). This is **audit/recalculation transparency only** — the customer is always charged in the order's own currency using the listing's normal price; nothing reads these columns back into any pricing formula in this doc. Don't re-investigate this as a missing formula — it's confirmed out of scope for charged amounts.

## 15. Flash sale & mega deal pricing

### Mega Deal — not a pricing mechanism

There is **no `MegaDeal` model, no dedicated table, and no discount fields.** `mega_deals` is a Page Builder block type (`PageBuilderService.php:130`) used purely for merchandising — curating which products get a "Mega Deal" badge/placement on listing pages via `page_block_products`. `PageBuilderService::activeMegaDealProductIds()` (line 832) just resolves membership for display.

A `products.is_mega_deal` boolean column briefly existed and was dropped (`database/migrations/2026_09_19_110000_drop_is_mega_deal_from_products.php`) — its own docblock confirms it was redundant/misleading, since the real Page Builder flow never wrote to it. `is_mega_deal` is now computed live from Page Builder data, not stored.

**No discount, no funding split, no order impact. Do not build reporting around "mega deal revenue" — that revenue is indistinguishable from normal-price revenue today.**

### Flash sale — has a full data model, but ⚠️ **is not wired into checkout pricing**

Data model exists and is real:
- `flash_sales` — the campaign (status: draft → submission_open → submission_closed → under_review → approved → live → ended/cancelled), `min_discount_pct`, `max_products_per_seller`, `eligible_categories`/`eligible_seller_tiers` (JSON), `commission_override_pct`, `max_total_slots`.
- `flash_sale_submissions` — the actual vendor-submitted offer: `flash_price`, `original_price`, `calculated_discount_pct`, `max_quantity_total`, `max_quantity_per_customer` (default 1), `quantity_sold`, `quantity_remaining` (**generated column**: `max_quantity_total - quantity_sold`). `vendor_listing_id` XOR `admin_listing_id`.
- `flash_sale_orders` — *intended* per-order-item record: `flash_sale_submission_id`, `order_item_id`, `quantity`, `flash_price`, `original_price`, `discount_amount`.
- No `funded_by`/`vendor_share_pct`/subsidy columns anywhere in the flash-sale tables — unlike coupons, there's no funding-split model; the discount is simply the vendor cutting their own listed price.

**What's actually broken (confirmed by full grep of the checkout path):**
1. `CheckoutPricingEngine`, `CheckoutController`, `CartLineSource`, and both `CheckoutCalculationService` variants have **zero references to flash sales** — no discount type, no price override.
2. `CartService::recalculateCart()` (`app/Services/Customer/CartService.php:528-577`) always re-syncs a cart line's `unit_price` from the listing's normal live `price` (`$listing->price`), never from `flash_sale_submissions.flash_price`. Nothing anywhere copies `flash_price` onto the listing when a flash sale goes live (`TransitionFlashSaleStatusJob` only flips status enums).
3. `flash_price`/`discount_pct`/`quantity_remaining` only reach the customer as **UI badge data** via `CartItemEnrichmentService::buildFlashSaleShape()` (lines 392-394, 437, 713-728) — informational only.
4. `FlashSaleOrder::create()` / `new FlashSaleOrder` — **zero occurrences anywhere in `app/`.** The `OrderItem hasOne FlashSaleOrder` relationship exists in code but the table is never populated. There is no per-order flash-sale discount record.
5. `max_quantity_per_customer` / `quantity_remaining` are **not enforced** at checkout — `quantity_sold` is never incremented anywhere, so stock limits are display-only today.
6. Consequently there's no real stacking/exclusivity behavior with coupons or loyalty to document — coupons/loyalty operate on whatever `unit_price` is, which is always the normal price.

**Bottom line: a flash sale customer is charged the normal listing price, not `flash_price`.** `flash_sale_analytics` (gross_revenue, discount_given, platform_commission, vendor_payout) is populated by a separate rollup job (`FlashSaleAnalyticsJob`) disconnected from the live order pipeline — so even analytics don't reflect real transactions accurately.

**This is a functional gap, not a documentation gap.** If flash sales are meant to actually discount checkout price, engineering needs to:
- Wire `flash_price` into `CartLineSource`/`CartService` so `unit_price` reflects an active, in-window, in-stock `flash_sale_submissions` row at cart time and at order-lock time (race condition risk: price must be re-validated at checkout, not just cart add).
- Have `CheckoutPricingEngine` record the discount (`original_price - flash_price`) somewhere auditable — either a new `order_items` column or by actually populating `flash_sale_orders`.
- Enforce `max_quantity_per_customer` and atomically increment `quantity_sold` (currently a pure race condition even if wired up naively).
- Decide funding: is the flash discount 100% vendor-absorbed (implied by the schema having no funding-split columns), or should platform be able to co-fund it like `coupons.funded_by`?

## 16. Vendor monthly payout — `payouts.net_amount`

`PayoutCalculationService::calculateForVendor()` (`app/Services/PayoutCalculationService.php:84-206`). This is the settlement layer on top of everything above — it does not recompute order pricing, it aggregates already-persisted `sub_orders.vendor_payout` and nets out post-order deductions.

Eligibility per sub_order: `status = 'completed'`, OR (`delivered` AND return window has passed for all items); COD sub_orders additionally require `cod_remittance_confirmed = true`; excluded if already claimed by a prior `payout_items` row.

```
gross_sales          = Σ sub_orders.subtotal
vendor_payout_total   = Σ sub_orders.vendor_payout        // already nets coupon cost, commission, gateway fee, shipping contribution, vendor-owed marketer commission

refunds_deducted      = Σ refunds.amount WHERE vendor_charged_back = true AND status = 'completed'
chargebacks_deducted  = Σ payment_transactions.amount WHERE type = 'chargeback'
storage_fees          = Σ fbn_storage_fees + fbn_daily_overage_fees (unsettled)
packaging_total       = Σ packaging_supply_requests.(total_cost + delivery_fee)
subscription_total    = Σ vendor_subscription_invoices.amount WHERE status = 'Open'

net_before_ads = vendor_payout_total - refunds_deducted - chargebacks_deducted
               - storage_fees - packaging_total - subscription_total
```

**Ad fees are not a simple subtraction** — `selectAdCharges()` (lines 216-245):
```
cap = max(0, net_before_ads)
1. Apply all negative (refund-type) PaidAdCharge rows first, unconditionally.
2. Apply positive ad charges oldest-first, but only up to `cap`.
3. Any ad charges that would push the payout negative are left unsettled — they roll forward to the NEXT payout run, not deducted now.

net_amount = max(0, net_before_ads - ad_fees_applied)
```

## 17. Marketer commission → wallet flow (settlement layer on top of §13)

Two-stage clearing, confirmed accurate end-to-end:

```
1. Purchase happens → marketer_campaign_conversions row created, status = 'pending'.

2. ApproveMarketerConversionsJob (daily) — app/Jobs/ApproveMarketerConversionsJob.php:36-80
   Picks conversions where the sub_order is delivered/completed AND
   (return window passed for all returnable items OR nothing returnable).
   wallet.pending_balance += commission_amount + flash_sale_bonus_amount
   conversion.status = 'approved'; wallet_credited_at = now()

3. ReleaseMarketerPendingCommissionJob (daily) — app/Jobs/ReleaseMarketerPendingCommissionJob.php:27-67
   After `marketer_payout_clearing_days` (setting, default 3) have passed since approved_at:
   wallet.pending_balance -= amount
   wallet.balance         += amount   ← now withdrawable
```

**Tiered commission** (an alternative to §13's flat/percentage): `marketer_campaign_tiered_rules`, ordered by `from_sale_number` — commission amount depends on the marketer's cumulative sale count within the campaign, via `MarketerCampaignConversion belongsTo MarketerCampaignTieredRule`.

**One-time influencer campaign acceptance fee** — `MarketerCampaignService.php:681-710`: when an influencer accepts a campaign invitation, `fee_per_influencer` (from `marketer_influencer_fee_country_settings`, scoped by country) is deducted immediately from the marketer's wallet balance (not pending — real balance), recorded as a `marketer_campaign_invitation_platform_fee` transaction, `invitation.platform_fee_status = 'paid'`.

## 18. Sponsored ads & paid ad slots — vendor/marketer-paid, platform revenue

Two independent systems, both ultimately deducted from vendor payout via §16's `selectAdCharges()`.

**a) Sponsored Products (self-serve CPC/CPM campaigns)** — `app/Services/Customer/SponsoredProductService.php`:
```
Auction ranking: ORDER BY quality_score DESC, bid DESC   (lines 131-132, 261-262)

CPM: cost_per_impression = floor(bid / 1000)             (line 327)
     charged to AdImpression.cost_charged, campaign.budget_spent_total/_today incremented
     when cost > 0 and budget caps aren't exceeded (chargeCpmImpression, lines 333-353)

Budget guard before any charge: budget_spent_total + charge <= budget_total
                                  budget_spent_today + charge <= budget_daily (if set)
```
> Note: an earlier pass of this doc flagged "CPM always charges 0" as a known bug — **that's incorrect**, verified against current code. CPM billing is implemented exactly as above; `cost_charged = 0` only occurs legitimately for non-CPM campaigns, zero-bid campaigns, or when a charge would exceed budget. CPC's own per-click charge function wasn't pinned to an exact line in this pass — if you need it, grep `PaidAdChargeType::Cpc` usage in `AdBillingService.php` (confirmed present around line 164) as a starting point.

**b) Paid Ad Slots** (banner/placement bookings, separate from self-serve campaigns) — `PaidAdSlot`, `PaidAdBooking`, `PaidAdCharge` models, `app/Jobs/Ads/PaidAdSchedulerJob.php` drives `AdBookingService` (activate/expire/complete) and `AdBillingService.php:40-270` (Fixed/Cpm/Cpc/BudgetReserve charge types) writes `PaidAdCharge` rows.

```
payout.ad_fees = AdBillingService::unsettledForPayout()   // feeds §16's selectAdCharges() cap logic
```

## 19. Packaging fee — **does not exist yet**

Grepped the full backend: no `packaging_fee` column or calculation anywhere in order pricing. `PackagingSupply*` models exist but are inventory/procurement (vendors requesting physical packaging materials), unrelated to order-level charges.

### Proposed design (for review before implementation)

| Decision point | Recommendation | Rationale |
|---|---|---|
| Who sets it | Per-listing flat fee (`vendor_listings.packaging_fee`, `admin_listings.packaging_fee`), mirroring `shipping_cost` on admin listings | Consistent with existing per-listing fee pattern |
| Who charges it | Vendor for vendor-fulfilled items; platform for admin-fulfilled | Same actor split as shipping |
| Where in order total | Add as its own line item alongside shipping in §6: `+ packaging_fee` | Keeps it auditable separately from shipping, avoids conflating with carrier cost |
| Taxable? | Yes — include in `taxable` base like shipping, if `tax_rules.applies_to` treats it as product/shipping-adjacent | Match local VAT rules |
| Split logic | 100% to whichever actor fulfills (no subsidy concept needed) — no exceptional-zone-style gap exists for packaging | Packaging cost isn't carrier-negotiated, so no gap to subsidize |
| New columns | `orders.packaging_fee`, `sub_orders.packaging_fee`, `order_items.packaging_fee` (if item-level) | Mirrors shipping/warranty pattern |

This needs a product decision before implementation — flagging here rather than guessing at a shipped feature.

---

# What appears where

## Admin: Order / Order Details / Finance

Everything in §1–19 above, ungrouped and grouped by vendor/marketer/admin (§7). Specifically the full `orders` + all `sub_orders` rows + all `order_items` rows for that order, including:
- Full coupon funding split (vendor vs platform cost)
- Full shipping breakdown: charged fee, carrier cost, gap, admin subsidy, vendor contribution
- Gateway fee total and its per-group allocation
- Marketer commission and its owner (vendor-funded vs platform-funded), including which of §13's two commission models actually fired
- Commission discounts/rate reductions applied (§13b: vendor, marketer, subscription-tier)
- Refund gateway-fee deductions (§14) when applicable
- Platform net (derived — not a stored column today; compute from §7 formula for the finance view)
- Vendor payout settlement view (§16) and marketer wallet clearing status (§17) for orders that have reached that stage
- Ad fees (§18) charged against this order's vendor, if any

## Vendor / Marketer / Admin listing-scoped views: Order / Order Details / Reports

Scoped to **their own `sub_order` row(s) + the `order_items` rows belonging to them** (`sub_orders.vendor_id = :self` or `seller_type = 'platform'` for admin's own listings):

- **Item total**: their `order_items.line_total` rows only (§1)
- **Sub total**: their `sub_orders.subtotal` (§2)
- **Discount**: their `sub_orders.vendor_coupon_cost` (what it actually cost them) — do **not** show `platform_coupon_cost`, that's not theirs
- **Shipping fee**: their `sub_orders.shipping` (customer-charged amount for their items)
- **Order total (their portion)**: `subtotal - vendor_coupon_cost + shipping - vendor_contribution_amount + tax` (their own sub_order's total contribution — not the platform's `admin_subsidy` or `platform_coupon_cost`)
- **Subsidy (if any)**: their `sub_orders.vendor_contribution_amount` (§12) — labeled as their shipping-gap contribution, not "subsidy" language which implies they're giving money away for someone else
- **Marketer commission**: shown when `marketer_commission_owner` matches them (§13)
- **Payment fee**: their share of `sub_orders.gateway_fee` (§14) — only shown to whoever's payout actually absorbs it
- **Packaging fee**: not yet implemented — see §15 proposal; once built, scoped the same way as shipping fee

**Never expose to vendor/marketer**: `admin_subsidy_amount`, `platform_coupon_cost`, `platform_net`, other vendors' `sub_orders` rows, carrier's real cost (`carrier_shipping_cost`) unless that vendor is FBN and it's their own storage/fulfillment billing context.

---

# Open items requiring engineering/product follow-up before this doc is treated as fully authoritative

1. `line_total` formula divergence between `priceCart()` preview and persisted `CheckoutController` value (warranty/loyalty handling differs) — confirm which is correct and align.
2. Loyalty discount computation source not located — find the service that turns points into `loyalty_discount`.
3. `tax_rules`/`TaxRuleType` table appears unused by the live checkout tax calculation (`country.vat_rate` is used instead) — confirm dead code vs. used elsewhere (tax invoices?).
4. Two non-reconciled marketer commission models (§13a campaign-fixed vs §13b category-rate-percentage) — clarify which is authoritative, or whether they serve different purposes (e.g. b is a fallback/legacy or used for a different commission surface).
5. Customs duty and gift-card-applied amounts are pass-through params into `priceCart()` — their computation source wasn't located in this pass.
6. Packaging fee — proposed design above needs product sign-off; nothing exists in code today.
7. No stored `platform_net` column was found — it's derived at report-build time. Confirm the finance view is expected to compute it live rather than read a persisted value.
8. **Flash sale pricing is not applied at checkout** — customers are charged normal price, not `flash_price`; `flash_sale_orders` is never populated; stock limits unenforced. This is a real product bug, not a modeling choice — needs a decision on priority/fix (see §15).
9. "Mega Deal" has no discount mechanism at all — confirm with product whether it's *meant* to be a pricing feature (in which case it needs to be built) or purely merchandising (in which case naming it alongside "flash sale" in requirements may be a misunderstanding worth clarifying with whoever requested this doc).
