# Migration Plan: Order Money Columns BIGINT → DECIMAL(19,4)

**Status: IN PROGRESS — Slice 1 of N landed, NOT SAFE TO DEPLOY YET.** See "What's done" vs "What's blocking" below before running this migration anywhere.

## Why

All order-money columns are currently `BIGINT` storing whole base-currency units (no fractional support — see `docs/formula.md` header). Moving to `DECIMAL(19,4)` adds fractional-currency support (KWD/BHD/OMR 3-decimal subunits, precise intermediate percentage rounding for commission/tax) without precision loss.

## Scope: 194 money-shaped columns across the schema

Grepped `database/schema/mysql-schema.sql` for `bigint` columns matching `amount|price|fee|total|subtotal|discount|tax|payout|cost|commission|balance|net` — **194 hits**. Too large to safely convert in one blind pass on a live financial system, so this is staged in slices, each independently reviewable and deployable.

## Slice 1 — Core checkout pricing tables (landed, code only, NOT migrated/deployed)

**Tables:** `orders`, `order_items`, `sub_orders` — the tables written by `CheckoutPricingEngine` and `ShippingFeeCalculator`.

Done:
- `database/migrations/2026_09_22_181832_convert_core_order_money_columns_to_decimal.php` — additive migration (per project convention: never edit/remove existing migrations), raw `ALTER TABLE ... MODIFY` SQL (doctrine/dbal isn't installed, so `Blueprint::change()` wasn't used — avoids adding a dependency for a one-off type change). Has a working `down()` that reverts to `BIGINT`.
- Model casts updated to `decimal:4` on `Order`, `OrderItem`, `SubOrder` for every converted column (`app/Models/Order.php`, `OrderItem.php`, `SubOrder.php`). `fx_rate_numerator`/`fx_rate_denominator` deliberately left as `integer` — they're ratio components, not money.

**⚠️ NOT run against any database yet.** Do not run `php artisan migrate` with this file until the arithmetic layer below is fixed — see "What's blocking."

## What's blocking Slice 1 from being deployable

Laravel's `decimal:4` cast returns attributes as **strings** (e.g. `"123.4500"`), not `int`/`float` — this is intentional, to avoid float precision loss. Any code that reads an already-persisted `Order`/`OrderItem`/`SubOrder` attribute (not the in-memory `priceCart()` computation itself, which operates on plain PHP values before persistence) and feeds it into `intdiv()` will fatal with a `TypeError`, since `intdiv()` requires strict `int` params and rejects numeric strings.

Grepped the whole `app/` tree specifically for `intdiv()` on the Slice-1-converted column names. **One real crash point found and fixed:**

```
app/Jobs/ProcessAcquisitionCommissionsJob.php:54
  was:  intdiv($order->subtotal * $commission->commission_rate, 10000)   // $order is a SubOrder; subtotal now decimal:4 string → TypeError
  now:  bcdiv(bcmul($order->subtotal, (string) $commission->commission_rate, 4), '10000', 4)
```

The `ShippingFeeCalculator.php` `intdiv()` calls (lines 135, 148, 198, 253) were checked and are **not** at risk from Slice 1 — they operate on `shipping_rates` columns (`rate_per_kg`, `carrier_rate_per_kg`, `volumetric_divisor`), which are **not** in Slice 1's scope. They'll need the same audit when `shipping_rates` is converted in a later slice.

Broader grep for `intdiv`/modulo/bit-shift on Slice-1 column names across `PayoutCalculationService.php`, `RefundService.php`, and all Jobs found nothing else. This does **not** mean the arithmetic layer is fully safe — `round()`/`floor()` accept numeric strings fine and won't crash, but they return native `float`, and writing that back into a money field silently reintroduces the exact precision-loss problem this migration exists to fix. Every `round()`/`floor()` in `CheckoutPricingEngine.php`, `ShippingFeeCalculator.php`, `PayoutCalculationService.php`, `RefundService.php`, `MarketerCommissionRateService.php`, `AdBillingService.php` that touches a converted column still needs auditing and, where it feeds a persisted money field, moving to `bcmath` (`bcmul`/`bcdiv`/`bcadd`/`bcsub`) — that audit has **not** been done yet for Slice 1's three tables. Treat "no crash" as necessary, not sufficient, before running this migration on production data.

## Remaining slices (not started)

1. **Arithmetic rewrite** for the four services above — convert every money-bearing calculation to `bcmath`/`brick/math`, decide rounding mode per formula (floor vs round vs bankers' rounding — currently inconsistent even in the BIGINT version, e.g. `floor()` on warranty/commission vs `round()` on tax/coupon), write regression tests pinning known-good outputs from the current BIGINT implementation before touching arithmetic (protects against silent drift).
2. **Vendor/marketer/subscription tables** — `vendors.commission_discount_flat`, `marketer_profiles.commission_discount_flat`, `subscription_plans.*`, `commissions.min_commission/max_commission` (§13b in formula.md).
3. **Payout tables** — `payouts.*`, `payout_items.*`, `fbn_storage_fees`, `fbn_daily_overage_fees`, `packaging_supply_requests.total_cost/delivery_fee`, `vendor_subscription_invoices.amount` (§16).
4. **Marketer wallet/commission tables** — `wallets.balance/pending_balance`, `marketer_campaign_conversions.commission_amount`, `marketer_campaign_tiered_rules.*` (§17).
5. **Ad billing tables** — `paid_ad_charges.amount`, `ad_campaigns.budget_*`, `ad_impressions.cost_charged` (§18).
6. **Refunds/gift cards/coupons** — `refunds.amount`, `gift_cards.balance`, `coupons.value/max_discount` (§3, §14, §16 in formula.md).
7. **Everything else** in the 194-column list not covered above (warranty plans, shipping rates, tax rules, etc.) — needs its own grep-and-triage pass; not all 194 are necessarily in scope (some may be internal counters, not customer/vendor-facing money — verify each before converting).

## Rollout order recommendation

Each slice: migration (additive, reversible) → cast updates → arithmetic audit/rewrite for that slice's services → regression tests comparing against pre-change fixtures → deploy → next slice. Do not batch multiple slices into one deploy given the blast radius of a rounding regression in live financial data.

## Test coverage before deploying Slice 1

None added yet. Before running the migration anywhere:
1. Fix the four confirmed `intdiv()` crash points in `ShippingFeeCalculator.php`.
2. Audit every `floor()`/`round()` in `CheckoutPricingEngine.php` for float-precision leakage back into a money field.
3. Add regression tests asserting current BIGINT-era outputs match post-migration outputs for representative carts (normal, coupon, loyalty, warranty, exceptional-zone shipping, marketer campaign).
