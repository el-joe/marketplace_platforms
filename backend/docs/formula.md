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

## 16. Packaging fee — **does not exist yet**

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

Everything in §1–15 above, ungrouped and grouped by vendor/marketer/admin (§7). Specifically the full `orders` + all `sub_orders` rows + all `order_items` rows for that order, including:
- Full coupon funding split (vendor vs platform cost)
- Full shipping breakdown: charged fee, carrier cost, gap, admin subsidy, vendor contribution
- Gateway fee total and its per-group allocation
- Marketer commission and its owner (vendor-funded vs platform-funded)
- Platform net (derived — not a stored column today; compute from §7 formula for the finance view)

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
