# Marketplace Platform — Enhancement Plan (Audit 2026-09-16)

Each block below is a **self-contained prompt**. Run them **one at a time, in order**. Every prompt says what is wrong (with file and line), what to build, and how to prove it works.

---

## 0. How to use this file

### 0.1 Rules for every prompt (copy them into each run if you need to)

1. **Money is stored as whole base-currency amounts** (`BIGINT`). Never add a `*100` or `/100` conversion. A `/100` is only correct when it turns a percentage (`DECIMAL(5,2)`) or a basis-points column into a fraction. `vendor_listings.price/compare_at_price/cost_price` are `DECIMAL(12,2)`, which does not match every other money column (see P-11).
2. **Change the database only through migrations.** Never edit `marketplace_platform.sql`. Every migration must work on a database that already holds data (backfill first, then add the constraint).
3. **One source of truth per rule.** If a prompt finds duplicated logic (two checkout calculators, three inventory decrement paths), merge it into one service and delete or delegate the copies.
4. **Tests are required.** Each prompt ends with *Acceptance criteria*. Write Pest/PHPUnit feature tests (backend) that encode those criteria, and run `php artisan test --filter=<new tests>`.
5. **Frontend:** read the locale with `import useLocale from "@/src/hooks/use-locale";` and get text from `locale/en.json` and `locale/ar.json`. Keep both files at exactly the same set of keys. Follow `frontend/CLAUDE.md` (feature folders, prefer RSC).
6. Do not touch unrelated code, and do not reformat files.

### 0.2 How the audit was done

- The dump `marketplace_platform.sql` (287 tables) was imported into a separate local database, **`marketplace_audit`**. The live DB `marketplace_platform_live` was not touched. Drop the audit database when you no longer need it: `DROP DATABASE marketplace_audit;`.
- The code was read end to end for checkout, payment, cancel, return, refund, warranty, delivery, payout, marketer campaigns, inventory and images.
- Storefront endpoints were replayed through the Laravel HTTP kernel against `marketplace_audit`, recording the query log.
- Every no-parameter `GET` page of every panel was loaded while logged in as a real user of that panel.
- Frontend: scanned for i18n problems, hardcoded strings, mock/static data and image fields.

### 0.3 Severity legend

| Tag | Meaning |
|---|---|
| 🔴 Critical | Loses money or stock, crashes checkout, or breaks a core flow |
| 🟠 High | Wrong numbers or a missing lifecycle step, with a workaround |
| 🟡 Medium | Performance, consistency or UX problem |
| 🟢 Low | Hygiene |

### 0.4 Execution order

| Phase | Prompts | Covers request item |
|---|---|---|
| A. Safety net | P-00 | all |
| B. Order lifecycle & money | P-01 → P-12 | 1 |
| C. Inventory | P-13 | 2 |
| D. Marketer | P-14 → P-16 | 3 |
| E. Images | P-17, P-18 | 8 |
| F. Performance | P-19 → P-22 | 7 |
| G. Panels | P-23, P-24 | 6 |
| H. Frontend i18n & dynamic | P-25, P-26 | 4, 5 |

### 0.5 Decisions the product owner must confirm (defaults are used if nobody answers)

| # | Question | Default used in the prompts |
|---|---|---|
| D1 | Is platform commission calculated on the price **before** or **after** the coupon discount? | **Before** (gross line price). A vendor-funded coupon share is deducted from vendor payout separately. |
| D2 | Is VAT calculated on the discounted amount? | **Yes**: `tax = round((line_subtotal − line_discount) × vat%)`. |
| D3 | When does platform warranty coverage start? | The day after the brand/vendor warranty ends (`delivered_at + vendors.warranty_months`), or at delivery if there is no brand warranty. This matches the DB comment on `warranty_plans.duration_months`. |
| D4 | Is the gateway fee charged to the vendor per sub-order or pro-rata? | **Pro-rata**: `fee_pct` on the sub-order's share of the card-paid amount, and `fee_fixed` charged **once per order**, split pro-rata. |
| D5 | Who pays marketer commission? | The campaign owner: the vendor for vendor-listing campaigns, the platform for admin-listing campaigns. It is deducted from that owner's payout / platform revenue. |
| D6 | Refund destination for card orders | Back to the original card through the gateway. Only `return_type = store_credit` goes to the wallet. |

---

# PHASE A — SAFETY NET

## P-00 🔴 Build an end-to-end order lifecycle test harness (do this first) --DONE

**Goal:** The later prompts change money and stock logic. Build a reusable scenario test suite first, so every later prompt can prove it did not break anything.

**Context:** `backend/tests` has almost no coverage of checkout, cancel, return, refund, warranty or payout. The same logic has been fixed again and again (git log shows `Fix`/`Fixes` repeatedly).

**Tasks:**
1. Create `tests/Support/MarketplaceScenario.php`, a fluent builder that seeds a minimal consistent world:
   - a country (AED, VAT 5%) and a city with a shipping zone;
   - a category with FBN/FBP commission (pct + fixed) and a brand;
   - a product with 2 variants, each with and without variant images;
   - a vendor listing (FBP) and a vendor listing (FBN), both with `warehouse_inventories`;
   - an admin listing with inventory;
   - a marketer with an accepted campaign invitation and a marketer listing;
   - a customer with an address and a customer wallet;
   - a coupon of each type (`percentage`, `fixed_amount`, `free_shipping`, `bogo`) with `funded_by` platform, vendor and shared;
   - a warranty plan (`flat` and `percentage`);
   - payment gateways `cod`, `wallet`, `stripe` (mocked), `bank_transfer`;
   - a delivery agent and a shipping company supervisor.
2. Add a fake gateway (bound in the container during tests) whose results you can script: success, decline, exception, refund success or failure.
3. Add assertion helpers:
   - `assertStock($listing, onHand, reserved)`
   - `assertMoneyBalanced($order)`: `order.total == subtotal − discount − loyalty + shipping + cod_fee + tax + warranty_total`, `Σ sub_orders.subtotal == order.subtotal`, `Σ order_items.line_discount == order.discount (coupon part)`, `Σ sub_orders.tax == order.tax`
   - `assertLedgerBalanced($transactionGroupId)`: Σdebit == Σcredit
4. Write one **pending/skipped** test per scenario listed in P-01…P-13 (named after the prompt ID). Each later prompt makes its own tests pass.

**Acceptance criteria:** `php artisan test --filter=Scenario` runs. The builder can create every entity above with no SQL errors. Skipped tests list every scenario ID.

---

# PHASE B — ORDER LIFECYCLE & MONEY (request item 1)

### Reference: the target lifecycle every prompt in this phase must respect

```
cart (add/update/remove, warranty selection, coupon, wallet toggle)
  → checkout/prepare → place-order
      ├─ stock RESERVED (per warehouse row) + coupon usage PENDING + loyalty/wallet HELD
      ├─ payment: wallet (captured) | cod (pending until delivery) | card/gateway (redirect → webhook/callback) | bank_transfer (pending manual)
      │     └─ payment failed/cancelled → full rollback (stock, coupon, wallet, loyalty, gift card, warranty purchases) → order cancelled
  → confirmed → processing/packed → shipped (stock COMMITTED: on_hand−, reserved−)
  → out_for_delivery → delivered (COD captured, return window, warranty ACTIVATED, marketer conversion APPROVED)
      └─ failed delivery ×N / refused → RTO → sub-order returned → stock RESTOCKED → refund if prepaid
  → completed (auto after N days) → payout eligible → vendor/marketer/agent paid, ledger closed
  ↘ cancelled (before ship): full/partial per sub-order, refund to source, stock released
  ↘ return request → approved → pickup → received → inspected → restock/dispose → refund (items only) / store credit / exchange
  ↘ warranty claim (brand or platform warranty) → review → repair | replace | refund | no_action
```

---

## P-01 🔴 Merge the two checkout calculators so the price shown equals the price charged --DONE

**Problem**
- Two independent calculators exist, and they have already drifted apart:
  - `app/Services/CheckoutCalculationService.php` is used by `Api/Customer/CheckoutController` for `POST checkout/calculate` and `coupon/validate`.
  - `app/Services/Customer/CheckoutCalculationService.php` is used by `Customer/CheckoutController` for `prepare` and `place-order`.
  - Each has its own `applyCoupon`, `resolveApplicableSubtotal`, `cheapestQualifyingItemPrice` and `resolveWarrantySelections`. The totals the customer sees can therefore differ from what is charged.
- Order tax does not add up:
  - `buildOrderSummary()` (`Services/Customer/CheckoutCalculationService.php:407`) taxes `subtotal − discount`.
  - Each sub-order taxes the full subtotal: `Customer/CheckoutController.php:713` (`$vendorTax = round($vendorSubtotal * vat)`).
  - Each item also taxes the full line (`:797`).
  - Result: `Σ sub_orders.tax ≠ orders.tax` whenever there is a discount.
- The warranty total is added to the order total (`:409`), but it is never taxed and never assigned to a sub-order.

**Tasks**
1. Create one `App\Services\Checkout\CheckoutPricingEngine`. It returns an immutable DTO, `PricedCart`, holding:
   - per line: `unit_price, qty, line_subtotal, line_discount (coupon allocation), line_loyalty_discount, taxable, line_tax, warranty_price, warranty_tax (per D2/tax rules), commission (pct/fixed/amount/category), marketer_commission, shipping_share`;
   - per sub-order group (vendor or platform + shipping method + warehouse): `subtotal, discount, shipping, admin_subsidy, vendor_contribution, cod_fee share, tax, gateway_fee (D4), platform_commission, marketer_commission, vendor_payout`;
   - order totals: `subtotal, discount, loyalty_discount, shipping, cod_fee, tax, warranty_total, gift_card, wallet_applied, amount_due_gateway, total`.
2. Allocate discounts to lines pro-rata by `line_subtotal`, using the largest-remainder method so the lines add up to the order discount exactly. Allocate only to the lines the coupon applies to (category/product/vendor scope). BOGO: allocate to the cheapest qualifying unit.
3. Point `prepare`, `calculate`, `coupon/validate` and `place-order` at the engine. Delete the duplicated methods, or make them thin delegates marked `@deprecated`.
4. `place-order` must persist the engine output as-is (no recalculation inside the controller). Write `order_items.line_discount`, `line_tax`, `line_total = line_subtotal − line_discount + line_tax`, and the matching `sub_orders.*`.
5. Compare the totals from `prepare` with the totals at placement. If any figure differs, return `409 price_changed` with both breakdowns so the frontend can show "prices updated".

**Acceptance criteria**
- For carts with and without a coupon, loyalty points, warranty, COD, wallet or gift card, the `prepare` total equals the `place-order` total, to the unit.
- `assertMoneyBalanced` (P-00) passes for every combination in the scenario matrix (coupon types × payment methods × warranty on/off × 1–3 vendors).
- Only one class contains coupon discount math (grep check in the test).

---

## P-02 🔴 Place-order crashes or refuses admin-listing and marketer-listing items --DONE

**Problem**
- The pre-check handles admin listings (`Customer/CheckoutController.php:447-481`). The transaction does not. Everything inside it uses `$item->vendorListing`:
  - grouping: `:696` `$item->vendorListing->vendor_id`;
  - commission: `:720`;
  - inventory: `:728` `where('vendor_listing_id', …)`;
  - `OrderItem::create` (`:813-836`) always writes `vendor_listing_id` and never `admin_listing_id`.
  - **Any cart that contains an admin (platform) listing fails with a null-property error.**
- Marketer listings are only resolved when their campaign points to a **vendor** listing (`resolveMarketerCartItems`, `:1084-1098`). These fail with `listing_not_available`:
  - marketer listings whose campaign uses an admin listing;
  - independent marketer listings created in `Marketer/ListingController@store` (`:125`).
- `order_items` has **no `marketer_listing_id` column**. The marketer link is kept only inside the `product_snapshot` JSON (`:807-811`), so attribution, reports and returns cannot join on it.
- `buildProductSnapshot(VendorListing $listing)` (`:1289`) is typed to vendor listings only.

**Tasks**
1. Add a migration for `order_items.marketer_listing_id` (nullable FK, indexed). Backfill it from `product_snapshot->marketer_listing_id`.
2. Add a `CartLineSource` resolver that returns, for any cart item, one normalised object with these fields:
   - `sellable` (the listing the customer bought: vendor, admin or marketer);
   - `fulfilment_listing` (the listing that owns the stock: the vendor or admin listing; for a marketer listing, its source listing — see P-15);
   - `seller_party` (vendor_id, or `platform` for admin listings);
   - `price` (the marketer price, if marketer);
   - `fulfillment_model`, `warehouse_inventory rows`.
3. Rewrite the place-order transaction to use only `CartLineSource`:
   - group by `seller_party + shipping_method + warehouse`;
   - reserve stock on the `fulfilment_listing` (via P-13 `InventoryService`);
   - write `vendor_listing_id` / `admin_listing_id` / `marketer_listing_id` correctly.
4. Decide how an admin-listing sub-order records its `vendor_id` (`sub_orders.vendor_id` is `NOT NULL`):
   - either make it nullable and add `seller_type enum('vendor','platform')`,
   - or use a single seeded "platform vendor" row (`settings.platform_vendor_id`).
   - Document the choice. Update payouts so platform sub-orders are never paid to a vendor.
5. Make the snapshot builder accept any listing type.

**Acceptance criteria**
- A cart with an admin listing, a vendor listing, a campaign marketer listing and an independent marketer listing places successfully. Items and sub-orders carry the correct listing IDs and seller.
- Stock is reserved on the right inventory rows (P-13 assertions).

---

## P-03 🔴 Order money split: vendor, platform (admin), marketer and shipping amounts are wrong or missing --DONE

**Problem (evidence from `Customer/CheckoutController.php` place-order)**
1. **Vendor payout ignores:**
   - vendor-funded coupons (`coupons.funded_by`, `vendor_share_pct`; `line_discount` is hardcoded to `0` at `:825`);
   - `vendor_contribution_amount` (vendor covers delivery, `:779`);
   - marketer commission;
   - the warranty share.
   - The current formula is `vendor_payout = subtotal − commission − gateway_fee` (`:785`).
2. **Gateway fee** (`:764-767`): `fee_fixed` is added **once per sub-order**, so a 3-vendor order pays it three times. The fee is calculated on `vendorSubtotal`, ignoring any wallet/gift-card part that never passes through the gateway. For a full-wallet order a card fee is still charged unless the gateway is `wallet`.
3. **Commission:**
   - `calculateCommission` (`Services/Customer/CheckoutCalculationService.php:111-148`) ignores the `commissions` table (vendor/category/date-effective rules with min/max) and `vendors.commission_rate`. Only `applyCommissionDiscount` is applied, afterwards, to the **sub-order total** (`:759`), so `Σ order_items.commission_amount ≠ sub_orders.platform_commission` whenever a vendor discount exists.
   - FBN detection uses `global_system_type === ExpressFbn` (`:117`), while the sub-order stores `fulfillment_model` (`:775`). The two can disagree (e.g. `cross_dock`).
   - For marketer items, commission is taken on the marketer's price (`unit_price`), not the vendor's listing price.
4. **Platform (admin) income is never recorded:**
   - commission, gateway fee margin, shipping revenue, COD fee, warranty revenue, platform coupon cost, platform shipping subsidy (`admin_subsidy_amount`), marketer platform fee.
   - No `ledger_entries` are written at order, capture, delivery or refund time. `LedgerService` is only called from `Admin/PayoutController`.
5. **Shipping company amounts:**
   - `sub_orders.carrier_shipping_cost` and `shipping_gap` are never populated at checkout (both default 0).
   - `Shipment.shipping_cost_actual` is set to the **customer-paid** shipping (`Partner/OrderController.php:~323`), not the carrier cost.
   - Delivery agent earnings are created in `Services/Delivery/AssignmentService.php:231-257`. COD collected by agents is settled through `GenerateCodSettlementsJob`, but it is not reconciled against `orders.total − wallet_amount_used`.
6. `vendor_listings.price`, `compare_at_price` and `cost_price` are `DECIMAL(12,2)`, while `admin_listings`, `marketer_listings`, `cart_items`, `order_items` and every other money column are `BIGINT`. Decimal prices are silently truncated when cast to `int`.

**Tasks**
1. Implement the split inside `CheckoutPricingEngine` (P-01), per line and per sub-order, using decisions D1, D4 and D5:
   ```
   vendor_gross          = Σ line_subtotal (vendor lines)
   vendor_coupon_cost    = Σ line_discount × vendor_share (funded_by vendor=100%, shared=vendor_share_pct, platform=0)
   platform_coupon_cost  = Σ line_discount − vendor_coupon_cost
   platform_commission   = Σ resolved commission (commissions table → vendor override → country_category → category chain), min/max clamp, then vendor commission discount per line
   gateway_fee (vendor)  = pro-rata of (order gateway fee on amount_due_gateway)
   marketer_commission   = campaign commission for marketer lines (owner = vendor or platform)
   vendor_payout         = vendor_gross − vendor_coupon_cost − platform_commission − gateway_fee − vendor_contribution_amount − marketer_commission(if vendor-owned)
   platform_net          = platform_commission + shipping_revenue + cod_fee + warranty_revenue − platform_coupon_cost − admin_subsidy_amount − marketer_commission(if platform-owned) − gateway_fee(platform share)
   ```
2. Add columns (migration) so these values are stored, not recalculated later:
   - `sub_orders.vendor_coupon_cost`, `platform_coupon_cost`, `marketer_commission`, `warranty_revenue`;
   - `order_items.vendor_coupon_cost`, `marketer_commission`, `platform_commission_after_discount`.
3. Populate `carrier_shipping_cost` and `shipping_gap` from the shipping resolver at checkout. Set `shipments.shipping_cost_actual` from the carrier quote, or from the agent fee for in-house delivery.
4. Migrate `vendor_listings.price`, `compare_at_price` and `cost_price` to `BIGINT`: round existing values first, then check every consumer (Partner forms, `ProductQueryService` MIN/MAX, `BuyBoxService`, flash sales).
5. Write a per-order double-entry ledger through `LedgerService`, in the P-11 format:
   - on payment capture: `customer_payment` Dr / `seller_payable`, `platform_commission`, `shipping_revenue`, `tax_payable`, `gateway_fee` Cr;
   - reversals on refund or cancel.

**Acceptance criteria**
- For the scenario matrix: `Σ(vendor_payout) + platform_net + tax + marketer_commission + gateway_fee_total + carrier_cost_covered == amount paid by the customer (all tenders)`, to the unit.
- A 3-vendor card order charges `fee_fixed` once.
- A vendor-funded 10% coupon lowers that vendor's payout by exactly the discount on their lines.
- The ledger for each order group balances.

---

## P-04 🟠 Coupons: rules not enforced, free-shipping does nothing, usage never reverted --DONE

**Problem (`Services/Customer/CheckoutCalculationService.php:177-257`, `CouponService.php:318`)**
- A `free_shipping` coupon returns `discount = 0` (`:243`) and never zeroes shipping, so it has **no effect**.
- These columns are not checked at checkout:
  - `coupons.country_ids`;
  - `customer_eligibility` (`new_customers`, `specific_users` + `eligible_customer_ids`, `specific_segment`);
  - `max_orders_per_customer_per_month`;
  - `scope = vendor` + `vendor_id` (verify `resolveApplicableSubtotal`);
  - `is_stackable` (the affiliate promo code is stacked unconditionally, `CheckoutController.php:578-588`);
  - `coupon_products`.
- Usage limits are read without a lock (`CouponUsage::count()`), so concurrent orders can go over `usage_limit_total` or `usage_limit_per_customer`.
- `recordUsage()` runs inside place-order **before payment succeeds** (`CheckoutController.php:934`). The usage row and `times_used` are never reverted when:
  - the gateway declines, throws, or the customer cancels at the gateway (`PaymentCallbackController@cancel`);
  - the customer cancels the order (`Api/Customer/OrderController@cancel`);
  - an RTO happens.
- The coupon is looked up by `code` without `is_active` / soft-delete scoping (`CheckoutController.php:561`), and re-validated through a third path (`couponService->validate($coupon->code, $cart, …)`, `:927`).

**Tasks**
1. Create one `CouponEligibilityService::evaluate(Coupon, Customer, PricedCartDraft): CouponResult`. It enforces:
   - active, window, country, currency, min order, eligibility, per-customer, per-month, total limit, scope (platform, vendor, category incl. descendants, product via `coupon_products`), shipping type restriction, stackability with affiliate promo and loyalty.
   - `free_shipping` sets the shipping of the lines in scope to 0 (and caps with `max_discount` if set). The platform/vendor pays that shipping according to `funded_by`.
2. At placement, lock the coupon row (`SELECT … FOR UPDATE`), re-evaluate, increment `times_used`, and create `coupon_usages` with a new `status enum('reserved','consumed','released')`.
   - `consumed` on payment capture (wallet/card) or on delivery (COD).
   - `released` (and `times_used` decremented) on any rollback path. Use the P-06 cancellation service.
3. Return localized error messages (`lang/ar`, `lang/en`), not the hardcoded English strings.

**Acceptance criteria**
- Tests for each coupon type × scope × eligibility.
- Two parallel placements of a coupon with `usage_limit_total=1` result in exactly one success.
- A declined card payment leaves `times_used` unchanged.

---

## P-05 🔴 Payment methods (wallet, COD, gateways, bank transfer): broken branches --DONE

**Problem (`Customer/CheckoutController.php`, `PaymentService.php`, `Api/Customer/PaymentCallbackController.php`)**
1. `orders.payment_method` is `enum('card','wallet','cod','bnpl','bank_transfer')`, but place-order writes the **gateway code** (`'payment_method' => $gatewayCode`, `:663`), e.g. `paytabs` or `thawani`. **Confirmed:** the dump's gateways are `wallet, bank_transfer, cod, thawani, paytabs`, and Laravel runs MySQL in strict mode (`config/database.php` `'strict' => true`). **Every card-gateway order (Thawani, PayTabs) fails on insert.** The dump has only cod, wallet and bank_transfer orders, which fits this. Map gateway `type`/`code` to the method enum.
2. **Split wallet + card:**
   - The wallet pre-check rejects the order if `balance < total` only for the wallet gateway (`:619-633`).
   - For card + partial wallet, `applyWalletToOrder` debits the wallet, but `PaymentService::initiatePayment` still charges **`$order->total`** (`PaymentService.php:29`), so the customer pays twice for the wallet part.
3. **Idempotency:** the duplicate check looks only at `payment_transactions` (`:413-419`). Wallet-only orders create no transaction, so a retried request creates a duplicate wallet order (and a second wallet debit). `idempotency_keys` exists but is not used here.
4. **Failure cleanup:** on decline or exception (`:999-1023`) only `releaseReservedInventory` runs. The wallet debit, coupon usage, loyalty points, gift card and warranty purchases are **not** reverted. `cartService->clearCart($cart)` (`:1026`) runs **even when payment failed**, so the customer loses their cart.
5. **Callback security and correctness (`PaymentCallbackController`):**
   - `cancel` is unauthenticated and looks up the order only by `order_number`. Anyone who knows or guesses an order number can cancel any pending order, COD and bank-transfer orders included.
   - It decrements `quantity_reserved` on **all** inventory rows of the listing (bulk `->decrement`, every warehouse, no lock, no movement record, can go negative).
   - It does not check the gateway.
   - `success` trusts `verifyAndCapture`, which resolves the gateway config with `customer->country_id` (`PaymentService.php:62`), not `order->country_id`.
   - There is no webhook-first capture: if the customer closes the browser, the order stays `pending` forever. There is no expiry job for pending gateway orders or their stock reservations.
6. `amountCents: $order->total` is sent with `currency: $gatewayConfig->effective_currency` without conversion. If the effective currency differs from the order currency, the charged amount is wrong.
7. COD: `codAvailable` and `codValidationService` run, but the COD + partial wallet rule (`:904-908`) throws **after** the order rows and reservations were created inside the transaction. That is safe only because the whole transaction rolls back; keep it that way, and move the check before the transaction for clarity.
8. Bank transfer: there is no admin "confirm transfer received" action that captures the payment and confirms the order. Verify it in the admin panel; if missing, add it.

**Tasks**
1. Create a `PaymentOrchestrator` with an explicit method matrix:

   | Tender | Captured when | Gateway amount | Rollback on failure |
   |---|---|---|---|
   | wallet (full) | in transaction | 0 | n/a |
   | wallet (partial) + card | webhook/callback success | `total − wallet` | refund wallet |
   | card | webhook/callback | `total − wallet − giftcard` | release all |
   | cod | delivery (P-08) | 0 | RTO flow |
   | bank_transfer | admin confirmation | 0 | expiry job after N hours |

2. Map `payment_gateways.type/code` to `orders.payment_method`. Add a `payment_gateway_code` column to store the actual gateway.
3. Use `idempotency_keys` for place-order (key + customer + request hash → stored response).
4. Create a `CheckoutRollbackService` (reused by P-06) that releases stock, coupon, loyalty, wallet, gift card and warranty purchases. Clear the cart only after placement succeeds and the payment was captured or is legitimately pending (COD, bank transfer, redirect started).
5. Callbacks:
   - `cancel` must require either a signed URL (`URL::signedRoute`) or gateway verification that the transaction failed.
   - Make webhooks (`webhooks.payment`) the source of truth, idempotent via `payment_gateway_webhook_logs`.
   - Add `ExpirePendingPaymentsJob` (every 5 min) that runs the rollback for gateway orders pending longer than X minutes.
6. Convert amounts when the gateway currency differs from the order currency, using `UpdateExchangeRatesJob` rates. Store both amounts on `payment_transactions`.

**Acceptance criteria**
- Scenario tests for every row of the matrix: success, decline, exception, user cancel, webhook arrives before callback, duplicate webhook, duplicate place-order request.
- The wallet balance is correct in every case.
- An anonymous request to `checkout/payment/cancel/{orderNumber}` without a signature or a failed gateway state returns 403.

---

## P-06 🔴 Cancellation engine: customer, vendor, admin, payment failure and RTO cancellations do not reverse money correctly --DONE

**Problem**
- `Api/Customer/OrderController@cancel` (`:117-214`):
  - **Wallet refund never happens.** Checkout debits `customer_wallets` through `CheckoutWalletService` (`type='order_payment'`, `reference_type=Order::class`, `customer_id`, no `wallet_id`). Cancel looks for `wallets` with `owner_type='customer'`, `source_type='order'`, `type='debit'`, which never matches.
  - Captured card orders are cancelled with **no gateway refund** and no `refunds` row.
  - Loyalty points are not restored, coupon usage is not released, warranty purchases stay `pending`, and marketer conversions are not voided.
  - Stock is released on the **first** inventory row of the listing (`orderBy('id')->first()`), not the sub-order's warehouse row, and no `InventoryMovement` is written.
- `Vendor/OrderFulfillmentService::releaseInventory` and `OrderInterventionService` (admin) each implement their own partial version.
- RTO in `Services/Delivery/AssignmentService::fail` (`~:360-376`) sets the **whole order** to `cancelled` when one sub-order fails. It does not restock or refund prepaid tenders.
- There is no partial cancellation (a single sub-order or a single item). The frontend has `orders/[id]/cancel` with item selection (`cancel-items-form.tsx`). Verify what the API does with item IDs.

**Tasks**
1. Create `OrderCancellationService::cancel(Order|SubOrder|OrderItem[] $scope, CancelActor $actor, string $reason)`. It runs in one transaction and is idempotent:
   - checks that the status is cancellable (before `shipped` for customers; admin can force);
   - releases stock on the exact reserved rows (P-13);
   - calculates the refund per tender, in reverse order of use (gift card → wallet → card), pro-rata for partial scope;
   - card: creates a `refunds` row and dispatches `RefundProcessingJob` (fixed in P-07). Wallet: credits `customer_wallets` with the correct transaction type. Gift card: re-credits it;
   - restores loyalty points pro-rata, releases coupon usage when the whole order is cancelled (or recalculates if the coupon min amount is no longer met — document the rule), cancels warranty purchases for the cancelled items, and voids marketer conversions;
   - updates sub-order and item statuses, rolls up `orders.status` (P-08), writes `order_status_histories`, notifies customer, vendor and admin, and writes ledger reversals (P-11).
2. Make every existing cancel path delegate to this service:
   - customer API;
   - vendor reject/cancel;
   - admin intervention;
   - payment failure (P-05);
   - RTO (only the failed sub-order);
   - fraud detection.

**Acceptance criteria**
- Matrix tests: tender (wallet, card, COD, wallet+card, gift card) × scope (full order, one sub-order, one item) × actor.
- After cancellation, wallet, gift card, loyalty, coupon `times_used`, stock (`on_hand`/`reserved`), warranty status and conversions all return to their pre-order values. The ledger nets to 0.

---

## P-07 🔴 Refunds: double refund, COD refunds fail, return refunds refund the whole sub-order --DONE

**Problem**
- `Jobs/RefundProcessingJob.php:48-106` refunds through the **gateway**, and then **also** credits the same `net_refund` to the customer wallet (`:94-99`). **Every electronic refund is paid twice.**
- COD orders (`payment_method = cod`) go through `$paymentService->refund($originalTransaction, …)`. The COD transaction's gateway is `cod`, and `PaymentGatewayFactory` has no `cod` driver, so the job fails and the refund is marked `failed`. COD refunds must go to the wallet (or a manual bank payout).
- The deductions `gateway_fee_deducted`, `tax_deducted` and `GATEWAY_FEE_TAX_RATE` reduce what the customer gets back, even when the platform or vendor was at fault. There is no rule tying the deduction to `refund.reason` or `return_requests.liability`.
- `Admin/ReturnController::processReturnRefund` (`:371-405`) calls `processRefund($order, 'full', null, 'wrong_item', …, sub_order_id)`:
  - it refunds the **full sub-order** instead of the returned items and quantities;
  - the reason is always `wrong_item`;
  - for `store_credit` it credits the wallet **again**, on top of the refund.
- `refunds.initiated_by_customer_id` is `NOT NULL`, which blocks admin- or system-initiated refunds. `RefundProcessingJob` looks the customer up from that column.

**Tasks**
1. Create `RefundService::refund(Order, RefundScope items+qty|shipping|amount, reason, liability, destination original|wallet|bank)`:
   - The amount comes from the persisted P-03 line values: `line_total − line_discount share + line_tax share + warranty (if also cancelled) + shipping (only if all items of the sub-order are refunded, or liability is seller/platform/carrier)`.
   - The deduction policy is based on liability. Customer fault (`changed_mind`, `size_issue`) may deduct return shipping or gateway fee according to settings. Seller, platform or carrier fault means no deduction.
   - Destination: card → gateway only. COD → wallet (or bank payout request). `store_credit` → wallet only. Never both.
   - `vendor_charged_back` is set from liability. Update the vendor payout adjustment and the ledger (P-11).
2. Make `initiated_by_customer_id` nullable and add `initiated_by_type/id` (migration).
3. Fix `RefundProcessingJob`: remove the extra wallet credit, handle each destination, retry with backoff, and do not mark the refund `completed` until the gateway confirms (webhook).
4. Point return inspection (`ReturnController`) at `RefundService` using the return request items and quantities.

**Acceptance criteria**
- A card refund of 100 moves exactly 100 (minus any policy deduction) to the card and 0 to the wallet.
- A COD refund credits the wallet.
- Returning 1 of 3 units refunds exactly that unit's share.
- A store-credit return credits the wallet exactly once.

---

## P-08 🟠 Order and sub-order status state machine, delivery and COD capture --DONE

**Problem**
- There are two different "deliver" implementations:
  - `Http/Controllers/Delivery/AssignmentController.php` (web delivery panel) sets `orders.payment_status = captured` for COD (`:280`);
  - `Services/Delivery/AssignmentService::deliver` (used by the **delivery mobile app API**) does **not** capture COD, does not update the COD `payment_transactions` row, and does not roll up `orders.status`.
- On delivery only `sub_orders.status` is updated (`AssignmentService.php:217-220`). The following are never updated:
  - `order_items.fulfillment_status` (stays `pending`);
  - `orders.status` (never becomes `partially_delivered` or `delivered`);
  - there is no warranty activation (P-09) and no marketer conversion approval (P-12).
- `Partner/OrderController` ship action (`:300-380`) updates `sub_orders` to `shipped` but not `orders.status` (`partially_shipped`/`shipped`) or item `fulfillment_status`. The customer notification is a `TODO` (`:378`).
- `AutoCompleteOrdersJob` completes sub-orders 7 days after delivery (it is scheduled). `return_eligible_until` is hardcoded to 14 days in two places (`AssignmentService.php:261`, `Partner/OrderController.php:573`) instead of a category or country setting.
- Status transitions are not validated anywhere, so any controller can jump a status (e.g. `placed` → `delivered`).

**Tasks**
1. Create an `OrderStateMachine` with allowed transitions for `sub_orders` and `order_items`, and a pure function `rollupOrderStatus(Order)` (e.g. some shipped → `partially_shipped`, all delivered → `delivered`, all cancelled → `cancelled`, a mix of delivered and cancelled → `delivered`). Every status change must go through `transition($subOrder, $to, $actor, $meta)`, which:
   - validates the transition;
   - writes `order_status_histories`;
   - updates item statuses;
   - rolls up the order;
   - fires domain events (`SubOrderShipped`, `SubOrderDelivered`, `SubOrderReturned`, `OrderCompleted`).
2. Event listeners:
   - `SubOrderShipped` → inventory commit (P-13) and customer notification.
   - `SubOrderDelivered` → COD capture (payment transaction `succeeded`, order `captured` once all COD sub-orders are delivered), `return_eligible_until` from settings, warranty activation (P-09), conversion approval (P-12), agent earnings.
   - `OrderCompleted` → payout eligibility and loyalty points earned.
3. Remove the duplicated logic in `Delivery/AssignmentController`. Both web and API call `AssignmentService`.
4. RTO: move only the failed sub-order to `returned`, restock (P-13), refund prepaid tenders (P-07), and keep other sub-orders untouched.

**Acceptance criteria**
- Delivering through the mobile API and through the web panel gives identical DB state.
- A 2-vendor order moves `placed → partially_shipped → shipped → partially_delivered → delivered → completed`.
- An illegal transition throws a domain exception.

---

## P-09 🔴 Warranty lifecycle: purchase, activation, expiry and claims are incomplete

**Problem**
- **Warranties are never activated.** `Jobs/ActivateWarrantyPurchaseJob.php` exists, but the file itself says `// TODO: Dispatch this job…` and nothing dispatches it. Every `warranty_purchases` row stays `pending`, and `WarrantyController@purchases` filters `->active()`, so customers never see their warranties.
- Coverage starts `today()`. It should start after the brand warranty (D3; `warranty_plans.duration_months` comment; `vendors.warranty_months`).
- There is no job that expires warranties (`active` → `expired`).
- **Selection at checkout:**
  - Cart warranty is stored in `cart_items.warranty_plan_id` (`PATCH cart/items/{id}/warranty`), but place-order only reads `validated['warranty_selections']` (`CheckoutController.php:548-556`). If the frontend relies on the cart field, the warranty is shown in the cart and silently dropped at checkout (or the reverse).
  - `resolveWarrantySelections` matches only `vendor_listing_id` (`Services/Customer/CheckoutCalculationService.php:445`), so admin-listing and marketer items cannot get a warranty.
  - `warranty_plans.country_ids` and category applicability are resolved by `WarrantyPlanService`. Verify descendant categories.
  - For `percentage` plans, check that `price_pct` is applied to the unit price × qty and that one purchase is created per unit or per line (document which).
- **Post-purchase:** `GET warranty/plans/{orderItemId}` lists plans for an already-ordered item, but there is **no endpoint to buy** a warranty after the order (no payment flow).
- **Claims** (`Api/Customer/WarrantyController@claimsStore`, `:91-148`):
  - `$orderItem->warrantyPurchase->coverage_ends_at` crashes when the item has no platform warranty. A **brand-warranty claim** (the vendor warranty, `covered_by_platform_warranty = false`) is impossible.
  - Nothing checks that the warranty is `active`, that today ≤ `coverage_ends_at`, that the item was delivered, or that there is no open claim for the same item.
  - `listing_type` enum lacks `marketer_listing`. There is no `warranty_purchase_id` FK on `warranty_claims`.
  - Claim resolutions (`repair`, `replace`, `refund`, `no_action`) set a status only. Nothing creates the replacement order, the refund (P-07) or the repair pickup.
- **Revenue:** `warranty_total` is collected but never assigned to a sub-order, the ledger or a platform revenue report.

**Tasks**
1. Checkout: create one warranty source of truth (`cart_items.warranty_plan_id`, with `warranty_selections` accepted only as an override that also updates the cart). Support every listing type. Price via `WarrantyPlanService`. Create `warranty_purchases` per order item with `status=pending`.
2. Activation: on `SubOrderDelivered` (P-08), dispatch activation with `coverage_starts_at = delivered_at + vendor/brand warranty months` (or `delivered_at` if none) and `coverage_ends_at = starts + duration_months`. Cancel warranty purchases on cancel, RTO, or a full refund of the item (with refund of `price_paid`, P-07).
3. Add a scheduled `ExpireWarrantyPurchasesJob` (daily).
4. Post-purchase buy flow: `POST warranty/purchases` → order-like payment (wallet or gateway) → purchase `active` immediately if the item is already delivered. Allow it only within X days of delivery (setting).
5. Claims:
   - add a migration for `warranty_claims.warranty_purchase_id` (nullable), `claim_type enum('brand','platform')`, and `marketer_listing` added to the `listing_type` enum;
   - validate ownership, delivery, coverage window (brand window = `delivered_at + vendors.warranty_months`, platform window from the purchase) and one open claim per item;
   - resolution actions: `replace` creates a zero-price replacement sub-order (stock reservation) plus a pickup return; `refund` calls `RefundService`; `repair` creates a pickup and a status timeline. Notify the vendor for brand claims and the admin for platform claims.
6. Ledger: warranty revenue goes to the platform, with tax per D2.
7. Frontend (`src/features/noon/profile/warranties`, `warranty`, `warranty-claims`):
   - show `pending` warranties as "activates on delivery";
   - show active ones with coverage dates;
   - claim creation lets the customer choose between brand and platform coverage;
   - add a "buy extended warranty" CTA on delivered order items.

**How a customer activates a warranty (document this in the Help Center once done)**
1. Choose a warranty plan on the product page, in the cart, or at checkout. Status: `pending`.
2. The item is delivered, the warranty activates automatically, and coverage dates are shown under *My Warranties*.
3. Or, after delivery, open the order and click *Add warranty* (within X days), pay, and it is active immediately.
4. To claim: *My Warranties / Order item* → *Submit claim* → choose issue and upload evidence → track status and messages → resolution (repair, replace, refund).

**Acceptance criteria**
- A delivered item with a plan has an `active` purchase with correct dates.
- A cancelled item's purchase is `cancelled` and refunded.
- A claim without a platform warranty but inside the brand window succeeds as `brand`.
- A claim outside every window returns 422.
- The replace resolution creates a replacement sub-order.

---

## P-10 🟠 Return (listing return) lifecycle: no eligibility checks, duplicate endpoints, wrong restock

**Problem**
- There are two customer create endpoints with different logic:
  - `Customer/ReturnController@store` → `Services/Customer/ReturnService::store` (used by the frontend: `POST /orders/{order_number}/returns`, `orders.actions.ts:49`);
  - `Api/Customer/ReturnRequestController@store`.
- Neither checks that:
  - the sub-order or item is `delivered`;
  - `today ≤ return_eligible_until`;
  - the item is not already in an open or completed return;
  - the requested quantity ≤ purchased − already returned (`quantity` is always the full item quantity);
  - all items belong to the **same sub-order** (`sub_order_id` is taken from the first item, `ReturnService.php:25-35`);
  - the category is returnable (non-returnable categories, hygiene items).
- `ReturnService::store` takes `$items->first()` without checking that the IDs belong to the order. If no IDs match, `$items->first()` is null and the service crashes.
- Admin restock (`Admin/ReturnController::restockInventory`, `:407-437`):
  - picks the **first** inventory row of the listing, not the original warehouse;
  - uses `reference_type = 'adjustment'`;
  - ignores admin listings (`vendor_listing_id` only);
  - ignores `return_request_items.restock_decision` and `condition_received`.
- The `exchange` return type has no implementation (no replacement order).
- Vendor-side return handling (partner panel `/returns`) currently throws a 500 (see P-23). Vendor approval for FBM returns, and whether the vendor or the admin inspects, is undefined.

**Tasks**
1. Keep one `ReturnRequestService` with `create`, `approve`, `reject`, `schedulePickup`, `markReceived`, `inspect(items: condition, restock_decision)`, `complete` and `cancel`. Enforce every eligibility rule above, with per-item quantities and a separate return request per sub-order (the API may accept a mixed list and split it).
2. Add `return_window_days` and `is_returnable` on categories (inherited) with a country override. Set `return_eligible_until` from them on delivery (P-08).
3. Inspection: restock to the original sub-order warehouse row via `InventoryService::restock` (P-13) according to `restock_decision`. `damage` → `quantity_damaged`. Set liability, then call `RefundService` (P-07) with the returned items only, or create an exchange replacement sub-order.
4. Route the frontend and both API routes to the single service. Delete the duplicate controller logic.
5. Customer frontend (`src/features/noon/profile/returns`, orders detail): show per-item eligibility and remaining days, a quantity selector, and hide the return button when not eligible.

**Acceptance criteria**
- A return before delivery, after the window, for a quantity above what was purchased, or for an already-returned item → 422.
- A good-condition inspection restocks the original warehouse row and writes a `return` movement referencing the return request.
- The refund equals the returned items' share.

---

## P-11 🟠 Ledger and payouts: vendor, admin, marketer and shipping amounts reconciliation

**Problem (`Services/PayoutCalculationService.php`, `LedgerService.php`)**
- Sub-orders are selected with `delivered_at BETWEEN … OR created_at BETWEEN …` (`:30-33`), and **already-paid sub-orders are not excluded** (no `payout_items` check). Consecutive payout periods pay the same sub-order twice.
- The payout ignores:
  - `vendor_contribution_amount` (delivery the vendor covers);
  - the vendor coupon share;
  - marketer commissions for vendor campaigns;
  - FBN storage fees (`'storage_fees' => 0` hardcoded, while `fbn_storage_fees` and `fbn_daily_overage_fees` are calculated);
  - packaging supply requests;
  - vendor subscription invoices.
- The refund deduction sums `refunds.amount` (gross), not the vendor-liable share.
- `GenerateVendorPayoutsJob` exists but is **not scheduled** in `routes/console.php`.
- Ledger entries are written only when an admin pays out. There is no ledger for order capture, commission, tax, shipping revenue, subsidies, refunds, COD clearing, agent earnings or marketer commission. The `account_type` enum lacks `marketer_payable`, `carrier_payable`, `warranty_revenue`, `coupon_expense`, `shipping_subsidy_expense` and `wallet_liability`.
- Marketers: `MarketerCampaignService::markConversionsPaid` exists, but there is no scheduled payout run, and the marketer wallet (`wallets.owner_type = marketer`) is not credited when a conversion is approved.
- `vendors.total_sales` is `DECIMAL(10,2)`; the other money columns are `BIGINT`.

**Tasks**
1. `LedgerService::post(groupId, entries[])` must assert balance. Add posting rules for each event: `OrderPlaced` (wallet hold), `PaymentCaptured`, `SubOrderDelivered` (recognise commission, shipping, subsidy, marketer commission, agent fee), `RefundCompleted`, `CodSettled`, `PayoutPaid`, `MarketerPayoutPaid`, `WarrantySold`. Extend the `account_type` enum (migration).
2. Rewrite `PayoutCalculationService` to sum persisted P-03 fields for eligible sub-orders:
   - `status in (completed)`, or `delivered` + return window passed (setting);
   - COD remittance confirmed;
   - **not already in a `payout_items` row**.
   - Deduct vendor-liable refunds (share), storage and overage fees, ad charges, subscription invoices and packaging.
   - Create `payout_items` per sub-order.
3. Schedule `GenerateVendorPayoutsJob` according to `vendors.payout_schedule`. Add `GenerateMarketerPayoutsJob` and `GenerateAgentPayoutsJob` (if missing).
4. Admin financial report (`FinancialReportService`): per period and country, show platform commission, shipping revenue, COD fees, warranty revenue, coupon cost (platform), shipping subsidy, gateway fees, marketer commissions (platform-owned), refunds (platform-liable), and net. Check it against the ledger totals.
5. Migrate `vendors.total_sales` to `BIGINT`.

**Acceptance criteria**
- Running payouts for two consecutive periods never pays the same sub-order twice.
- The ledger trial balance is 0 for every transaction group.
- The admin report net equals the ledger platform accounts for the scenario data set.

---

## P-12 🔴 Marketer attribution and commission never reach the order

**Problem**
- `Order::create([... 'marketer_id' => $attribution['marketer_id'] ?? null, 'marketer_campaign_id' => ...])` (`CheckoutController.php:671-672`) writes columns that **do not exist** on `orders`, and they are not in `$fillable`, so they are silently dropped.
- `$attribution = session('marketer_attribution', [])` (`:635`) is always empty on the stateless JWT API.
- Attribution only happens afterwards, in `LastClickAttributionService::resolveAndRecordConversion($order, X-Session-Id)`, and only through a click recorded by `ReferralTrackingController`. Customers who add to cart from a **marketer listing page** (cart item `marketer_listing_id`) are not attributed unless they also clicked a referral link.
- The campaign's `marketer_commission_amount` and `platform_commission_amount` are created as `0` ("set by admin later", `MarketerCampaignService.php:127-128`), and auto-approval (`autoApproveCampaign`) activates the campaign **with 0 commission**, so conversions earn 0.
- Conversions are not reversed on cancel, RTO or return (P-06/P-10). `max_commission_budget` is not enforced. `tiered` rules and `flash_sale_bonus_amount` need verifying.
- `marketer_campaign_conversions` appears to be defined twice in the dump output (verify there is no duplicate migration).

**Tasks**
1. Attribution priority, recorded per **order item** (`order_items.marketer_listing_id` from P-02, plus a new `order_items.marketer_campaign_invitation_id`):
   1. a cart item bought from a marketer listing;
   2. last-click referral (session ID / customer ID, within the attribution window setting);
   3. none.
   Remove the dead `session()` attribution and the non-existent columns from `Order::create`.
2. On placement, create conversions with `status enum('pending','approved','reversed','paid')` (migration) and `commission_amount` from the campaign type:
   - `fixed` → `marketer_commission_amount × qty`;
   - `tiered` → by `sale_number_in_campaign`;
   - flash sale bonus.
   Enforce the remaining `max_commission_budget` with a row lock. When the budget is exhausted, auto-pause the campaign.
3. On `SubOrderDelivered` + return window passed → `approved` → credit the marketer wallet as `pending_balance`, moving to `balance` after the window. On cancel or return → `reversed`, with wallet adjustment.
4. Campaign approval (admin) must require the commission amounts. Auto-approve must use category/country defaults (`marketer_category_commissions`, `marketer_commission_country_settings`) and never 0. If there are no defaults, keep the campaign in `pending_admin` and notify.
5. Ledger (P-11): marketer commission expense against the vendor or platform (D5).

**Acceptance criteria**
- Buying from a marketer listing creates an approved conversion after delivery with a non-zero commission, and the marketer wallet increases.
- Cancelling the order reverses it.
- A campaign whose budget is exhausted pauses.

---

# PHASE C — INVENTORY (request item 2)

## P-13 🔴 Listing quantities: one inventory service for every increment and decrement

**Problem — there are at least 7 independent stock-mutation paths, and they disagree:**

| Path | File | Bug |
|---|---|---|
| Checkout reserve | `Customer/CheckoutController.php:728-753` | The pre-check sums **all** warehouse rows (`:474`), but the reservation locks only the **first** row (`orderBy('id')->first()`), so a multi-warehouse listing fails or over-reserves one row. Admin listings are never reserved. The movement's `quantity_after` stores `on_hand` for a reservation. |
| Payment failed release | `CheckoutController::releaseReservedInventory` `:1210` | OK per warehouse, but relies on `sub_order.warehouse_id` = the first item's warehouse (`:756`) even when items come from different warehouses. |
| Gateway cancel callback | `PaymentCallbackController@cancel` | Bulk `decrement` on **all** rows of the listing, no lock, no movement, can go negative. |
| Customer cancel | `Api/Customer/OrderController@cancel` `:141-149` | Releases the first row, not the reserved row. No movement. |
| Vendor ship (service) | `Services/Vendor/OrderFulfillmentService::decrementInventory` `:90-123` | Decrements `on_hand` but **not `reserved`**, so reserved stock leaks forever and available stock shrinks twice. Finds the listing by `vendor_id + product_variant_id` instead of `order_item.vendor_listing_id` (wrong when a vendor has the same variant in several countries or listings). |
| Vendor ship (controller) | `Partner/OrderController.php:338-366` | A second implementation (decrements both, clamps with `max(0)`, which hides errors), with no lock. |
| Vendor cancel | `OrderFulfillmentService::releaseInventory` `:125-160` | Movement `quantity_delta` has the wrong sign or meaning. |
| Admin intervention restock | `OrderInterventionService.php:~276` | Increments `on_hand` for items that were never shipped (should release `reserved`). |
| Return restock | `Admin/ReturnController.php:407-437` | First row, `reference_type='adjustment'`, admin listings ignored. |

**Other gaps**
- Listing `status` is never synced with stock. There is no automatic `out_of_stock` when available hits 0, or `active` when restocked. Customer queries filter `status = active` in some places and `IN (active, out_of_stock)` in others (`ProductDetailController.php:261,469`, `VariantResolutionService.php:42`).
- `cart_inventory_locks` supports only `vendor_listing_id`, is never created on add-to-cart (only deleted on guest merge, `CartService.php:137`), yet `ReleaseExpiredLocksJob` runs every minute. Either implement cart holds or remove them.
- `inventory_movements.reference_type` enum lacks `sub_order`, `return_request`, `campaign_sample`, `warranty_replacement`, `rto`. `created_by_user_id` is `NOT NULL` in the schema but is written as `null` in `OrderFulfillmentService`.
- `warehouse_inventories` has no unique key on `(warehouse_id, vendor_listing_id)` or `(warehouse_id, admin_listing_id)`, so duplicate rows are possible. `quantity_available` has an index only for vendor listings.
- `total_sold` on vendor, admin and marketer listings, and `products.total_sold`, are not incremented on delivery (verify), which breaks bestseller rankings.
- Campaign samples (`marketer_campaign_samples`) and flash sale allocations do not reserve stock.
- Marketer listings have no stock of their own; stock must come from the source listing (P-15).

**Tasks**
1. Create `App\Services\Inventory\InventoryService` with these operations. Each one: runs in a transaction; takes a row lock; asserts no negatives (throws); writes an `InventoryMovement` (`movement_type`, `quantity_delta` signed, `on_hand_after`, `reserved_after`, `reference_type`/`id`, actor type/id); and dispatches `ListingStockChanged`.
   - `reserve(listing, qty, ref)`: allocate across warehouse rows (priority: listing warehouse → default warehouse → most available). Returns the allocations `[{warehouse_inventory_id, qty}]`.
   - `release(allocations, ref)`
   - `commit(allocations, ref)` on ship: `on_hand −= qty`, `reserved −= qty`
   - `restock(warehouse_inventory_id, qty, condition, ref)`
   - `adjust(row, delta, reason)`, `damage(row, qty)`, `transfer(from, to, qty)`
2. Add a migration for `order_item_allocations (order_item_id, warehouse_inventory_id, quantity, status reserved|committed|released|returned)`, so every later step works on the exact rows. Remove the single `sub_orders.warehouse_id` assumption, or split sub-orders per warehouse.
3. Replace every path in the table above with calls to the service. Delete the duplicate code in `Partner/OrderController` ship and make it call `OrderFulfillmentService`, which calls `InventoryService`.
4. Add a `ListingStockChanged` listener that recalculates availability, switches listing `status` between `active` and `out_of_stock` (never touches `paused`, `draft`, `rejected` or `archived`), sends a low-stock notification (`low_stock_threshold`), and invalidates listing caches.
5. On `SubOrderDelivered`, increment `total_sold` (listing + product); decrement it on return.
6. Add a migration: extend the `reference_type` enum, make `created_by_user_id` nullable and add `actor_type`, and add unique keys (after deduplicating existing rows by merging quantities).
7. Cart holds: implement a soft hold (reserve on checkout `prepare` for N minutes) through the same service, or drop `cart_inventory_locks` and its job. Document which.
8. Add an artisan command `inventory:reconcile` that rebuilds `quantity_reserved` from open allocations and reports drift (current dump: several rows have `quantity_reserved > 0` with no open orders to justify them — verify with this command).

**Scenario tests**
- add to cart → place → pay → ship → deliver;
- place → gateway fail;
- place → customer cancel;
- place → vendor cancel one sub-order;
- ship → RTO;
- deliver → return good / damaged;
- multi-warehouse split;
- admin listing;
- marketer listing (source stock);
- concurrent last-unit purchase (only one succeeds);
- manual adjust / damage / transfer;
- campaign sample reservation.

**Acceptance criteria**
- After every scenario, `on_hand`, `reserved` and `available` match the expected values, and `inventory:reconcile` reports zero drift.
- Listing status follows stock automatically.

---

# PHASE D — MARKETER (request item 3)

## P-14 🟠 Campaign sources: campaigns cannot be created from admin or marketer listings

**Problem**
- `marketer_campaigns.vendor_id` is `NOT NULL`, and `MarketerCampaignService::createCampaign(Vendor $vendor, …)` is the only creation path.
  - Admin campaigns (`Admin/MarketerCampaignController.php:195`) must borrow a vendor, and are restricted to FBN **vendor** listings (`:217`).
  - An admin-listing campaign has no stock check (the check at `:83-95` only runs for `vendor_listing_id`), gets no `campaign_enabled` flag, and has no sample category snapshot.
- Vendor campaigns are limited to `fulfillment_model = fbn` (`:85`). Confirm this is the business rule; if FBP is allowed, it needs a setting.
- `createMarketerListingFromInvitation` switches on `$campaign->campaign_category`, which is **not a column**, so travel and classified campaigns can never be created.
- A marketer-originated campaign (a marketer proposing to promote a vendor, admin or own listing) does not exist.
- Invitations are dispatched at creation time, **before** admin approval (`:171-173`). Marketers can accept, and get live referral links, for campaigns that are later rejected. Rejection does not cancel invitations or archive the marketer listings.
- `MarketerCampaign` status `done` / `markCampaignDone` does not archive the marketer listings.

**Tasks**
1. Migration:
   - `marketer_campaigns.owner_type enum('vendor','platform','marketer')`, `owner_id`;
   - `vendor_id` nullable;
   - `campaign_category enum('product','travel','classified')`;
   - `travel_package_id`, `classified_listing_id` (nullable);
   - a CHECK/validation that exactly one source is set.
2. Refactor to `createCampaign(CampaignOwner $owner, CampaignSource $source, array $data)`. Sources: vendor listing (any fulfilment allowed by setting), admin listing (platform owner, stock check against admin inventory), travel package, classified listing. Admin UI can create platform campaigns without choosing a vendor.
3. Marketer-originated request: a marketer requests to promote a listing → owner (vendor or admin) approves and sets commission → invitation auto-accepted.
4. Lifecycle:
   - invitations are sent **after** approval (or after auto-approve with non-zero defaults, P-12);
   - rejection, cancel or done cancels pending invitations and pauses/archives marketer listings;
   - a stock drop below `min_stock_for_campaign` (`MonitorCampaignStockJob`) pauses the campaign and its listings.
5. Tests: vendor, admin and marketer-originated campaigns, each from creation → approval → invitation → accept → listing active → sale → done.

**Acceptance criteria:** a campaign can be created from a vendor listing, an admin listing, and via a marketer request, and each produces purchasable marketer listings (P-15) with correct commission ownership (D5).

---

## P-15 🔴 Marketer listings must always resolve to a sellable source (stock, fulfilment, price)

**Problem**
- `marketer_listings` stores only `product_variant_id`, `price` and `invitation_id`.
  - Independent listings (`Marketer/ListingController@store`, no invitation) have **no seller, no stock and no fulfilment**, yet they are shown in listings (`ProductQueryService` `$ml` subqueries) and can be added to the cart, then fail at checkout (P-02).
  - Campaign listings resolve their source via `invitation → campaign → vendor_listing`. The `admin_listing` source is not handled at checkout.
- The price is copied once at invitation acceptance (`createProductListing`). It is not validated against the source price, and changes to the source price or status are not propagated.
- Marketer listing `status` does not follow source listing status or stock.

**Tasks**
1. Migration: `marketer_listings.source_type enum('vendor_listing','admin_listing')`, `source_listing_id` (indexed). Backfill from the invitation's campaign.
2. Business rule (confirm with the owner): an independent marketer listing must pick an existing active source listing for the same variant and country (choose a seller). Without a source, the listing is not purchasable and is hidden from the storefront.
3. Price rule: `min_price ≤ marketer price ≤ max_price` from a setting (e.g. source price ± X%). Re-validate when the source price changes (observer), and auto-pause if out of bounds.
4. Observers on vendor and admin listings (status, stock via P-13 `ListingStockChanged`, price) keep marketer listing availability in sync.
5. `CartLineSource` (P-02) uses `source_listing_id`. Storefront queries (`ProductQueryService`, `ListingQueryService`, `SearchService`, PDP) exclude marketer listings whose source is unavailable.

**Acceptance criteria:** every visible marketer listing can be bought, and stock is reserved on its source. Pausing the source listing hides the marketer listing within the same request cycle (cache invalidated).

---

## P-16 🟠 Marketer profile and portal: end-to-end lifecycle and API parity

**Problem / verify list**
- **Registration → approval → contract:** `marketers.global_status` flows (`pending` → `active` / `rejected` / `suspended`), `marketer_contracts` and `marketer_contract_acceptances`. Verify that a marketer cannot access campaigns before approval and contract acceptance.
- **API parity:** `routes/api_marketer.php` (mobile/partner app) has invitations, campaigns (active/finished), reports and ad bookings, but **no** endpoints for listings (CRUD, toggle, price), finance/wallet/withdrawals, samples, flash-sale invitations, special requests, support or profile public preview. The web portal (`routes/marketer.php`) has all of these.
- **Public profile** (`GET api/public/...MarketerProfileController`, frontend `app/[locale]/(noon)/(pages-with-footer)/marketer/[slug]/page.tsx`): the page contains hardcoded Arabic (P-25). Verify that it lists only purchasable marketer listings (P-15) and that `MarketerProfileCache` is invalidated on listing changes.
- **Referral link and QR:** `referral_code` is unique on `marketer_listings`, and invitations have `referral_link` and `qr_code_path`. Verify that the frontend route `app/[locale]/r/[code]/page.tsx` records the click (`ReferralTrackingController`), sets `X-Session-Id`, and redirects to the right listing (marketer listing ID, not the vendor listing).
- **Samples:** `marketer_campaign_samples` are created, but there is no stock reservation (P-13) and no shipping/delivery tracking to the marketer.
- **Timeout/replace:** `ProcessInvitationTimeoutJob` → `replaceMarketer`. Verify the replacement respects campaign status (not rejected or done) and the budget.

**Tasks**
1. Write a lifecycle test covering: register → admin approve → accept contract → receive invitation → accept (listing + referral + QR) → customer clicks the referral → buys → delivered → conversion approved → wallet pending → window passes → balance → withdrawal request → admin pays → ledger.
2. Add the missing marketer API endpoints (JWT `marketer_api`) mirroring the web controllers. Share service classes; no logic in controllers.
3. Fix whatever the lifecycle test exposes in the portal (web) controllers.
4. Frontend marketer profile page: only dynamic data, translations (P-25/P-26), purchasable listings, share/QR actions.

**Acceptance criteria:** the lifecycle test passes end to end, and every portal action has an API equivalent.

---

# PHASE E — IMAGES (request item 8)

### Required rule (applies everywhere)
> **Listing image = the images of the listing's variant (`product_images.product_variant_id = variant.id`), ordered by `is_primary DESC, position ASC`. Only if the variant has no images, fall back to product-level images (`product_id = product.id AND product_variant_id IS NULL`). Never mix the two sets, and never show another variant's images.**

Data reality in the dump: 137 images. 116 are product-level, 9 are variant-level (only **3 of 106 variants** have their own images), and **12 rows have both `product_id` and `product_variant_id` NULL** (orphans). The fallback is therefore used for most listings today, and it must work.

## P-17 🔴 Backend: one image resolver, applied to every API

**Problem (evidence)**

| Place | File | Bug |
|---|---|---|
| Relation | `Models/Product.php:95` `images()` | `hasMany(ProductImage)` on `product_id` also returns variant-specific rows that have `product_id` set (9 rows), so "product images" include other variants' photos. `primaryImage()` is `HasMany`. |
| **Cart** | `Services/Customer/CartService.php:618-638` | Returns `'primary_image' => $primaryImage?->path`, a **raw storage path** (e.g. `products/x.jpg`), not a URL. The frontend `getImageURL()` turns anything that is not `http…` or `/storage/…` into the placeholder, so **cart images never appear.** |
| **Cart enrichment** | `Services/CartItemEnrichmentService.php:55-66, 844` | Eager-loads only `productVariant.images`. `primaryImageUrl()` has **no product fallback**, so the image is null for 103/106 variants. Marketer items are not enriched at all. |
| **Checkout items** | `Http/Resources/Customer/CheckoutItemResource.php:30` | Uses **product** images only (incl. other variants'). |
| **Order snapshot** | `Customer/CheckoutController.php:1293` `buildProductSnapshot` | `thumbnail_url` from product images only, so order history, cancel form and return screens show the wrong variant. |
| Product detail | `Api/Customer/ProductDetailController.php:544-572` `buildImages` and `ListingQueryService::buildImagesSlider` `:525-547` | **Merges** variant + product images instead of fallback-only. |
| Category/search cards | `Services/Customer/ProductQueryService.php:189-230, 275-283` | The image subquery only JOINs variant images of the buy-box listing, else NULL. `ProductListResource.php:59-63` then falls back to `$this->images` (all product images incl. other variants'), and `images` slider = all product images. |
| Search | `Services/Customer/SearchService.php:43-44, 136-137` | `with(['productVariant.images' => fn($q)=>$q->limit(1)])`: a limit inside an eager-load constraint (verify on Laravel 13; it must be per-parent). Suggestions (`:222-229`) pick **any** variant's image of the product (`ORDER BY product_variant_id IS NOT NULL DESC`) instead of the suggested listing's variant. |
| Wishlist | `Http/Resources/Customer/WishlistResource.php:34` | Product images only. |
| Page builder | `Services/Shared/PageBuilderService.php:86`, `Customer/PageRendererService.php:447` | Loads `productVariant.product.images`. Card image not resolved by variant (verify the product_row / flash_sale / deal blocks). |
| Catalog listings | `Http/Resources/Api/Customer/VendorListingResource.php:18-23` | Fallback includes other variants' images. **Endpoint currently 500s** (`GET {country}/catalog-listings`) with "Attempt to read property product on null" when a listing's variant is soft-deleted. |
| Order tracking / account dashboard / reviews / flash sale item resources | `OrderTrackingService`, `AccountDashboardService`, `FlashSaleItemResource`, `ReviewResource` | Verify each; route them through the resolver. |

**Tasks**
1. Create `App\Services\Media\ListingImageResolver`:
   - `forVariants(Collection $variantIds): array<variantId, ImageDTO[]>`: **one query** for `product_images WHERE product_variant_id IN (...)`, plus one query for product-level images `WHERE product_id IN (...) AND product_variant_id IS NULL`. Applies the rule. Returns absolute URLs (`Storage::disk($disk)->url($path)`, forced https in production).
   - `primary(variantId): ?string`, `gallery(variantId): ImageDTO[]`.
   - A request-scoped memo, plus a `Cache::remember` per variant (tagged `variant-images:{id}`), invalidated by a `ProductImage` observer.
2. Fix the relations:
   - `Product::images()` → `->whereNull('product_variant_id')`;
   - add `Product::allImages()` for the admin editor;
   - `primaryImage()` → `HasOne` (`ofMany`/`latestOfMany` pattern or `oneOfMany` by position).
3. Replace every place in the table with the resolver. The API contract for every card, line or snapshot is:
   ```json
   "image": { "url": "https://…", "alt": {"ar": "…", "en": "…"} },
   "images": [ { "id": "…", "url": "…", "alt": {…}, "is_primary": true, "position": 0 } ]
   ```
   Keep the existing keys (`primary_image`, `variant_image`, `thumbnail`, `primary_image_url`, `thumbnail_url`) as **aliases holding the same absolute URL** for one release, so the frontend migrates safely (P-18).
4. `ProductQueryService`: replace the correlated image subqueries with the resolver after pagination (see P-19). The card image = the resolver's primary for `buy_box_variant_id`.
5. Order snapshot: store the resolved absolute URL **and** `variant_id`. Add an artisan command `orders:backfill-snapshot-images` for existing orders.
6. Data hygiene: an artisan command `images:audit` that lists orphan rows (both FKs null), rows whose variant belongs to a different product than `product_id`, and missing files on disk, with a `--fix` option (delete orphans after confirmation). Add the missing FK `product_images.product_variant_id → product_variants.id ON DELETE CASCADE` (migration, after cleanup).
7. Fix the `catalog-listings` 500: filter out listings whose variant or product is soft-deleted, and null-safe the resource.

**Acceptance criteria**
- Feature tests cover: a variant with images (only its images are returned); a variant without images (only product-level images); a product with variant A and B images (the A card never shows B's images).
- Cart, checkout, order detail, wishlist, search results, search suggestions, category cards, page-builder product blocks, flash sale and PDP all return absolute URLs following the rule.
- Query count does not grow with the number of items (at most 2 image queries per request).

---

## P-18 🔴 Frontend: consume the resolved image in every product card, PDP, cart, checkout and search

**Problem**
- `src/features/noon/cart/cart-item.tsx:64` and `src/features/noon/checkout/items-list.tsx:58` read `item.primary_image`, which today is a raw path. `getImageURL` (`src/helpers/get-image-url.ts`) returns the placeholder for any relative path that does not start with `/storage/`, **so cart and checkout images never show.**
- `checkout.type.ts:222` types `primary_image: null | string`. There is no shared image type.
- Every card or section reads a different key:
  - `spotlight-card.tsx:34-35` uses `primary_image || thumbnail`;
  - `mega-deals-card.tsx:20` and `flash-sale.tsx:74` use `thumbnail`;
  - `variants.tsx:28,72` uses `variant_image`;
  - `floating-product-summary.tsx:38` and `more-offers-sheet.tsx:44` use `images[0]`;
  - `order-item-row.tsx:29` and `cancel-items-form.tsx:52` use `thumbnail`;
  - `search-field.tsx:318` uses `product.primary_image` with a raw `<Image src>` (no `getImageURL`).
- PDP gallery: verify that switching variant swaps the gallery to the new variant's images (with fallback) instead of keeping the merged list.

**Tasks**
1. Add `src/types/media.ts` (`ImageDTO`) and a `getListingImage(entity)` helper that reads the new `image.url` contract (P-17) with a temporary alias fallback. Update `getImageURL` to accept absolute URLs, `/storage/...` and bare storage paths (`NEXT_PUBLIC_STORAGE_URL`), plus the placeholder.
2. Update every component listed above, plus any other card (`grep -rn "primary_image\|thumbnail\|variant_image\|images\[0\]" src`), to use the helper. Check `next.config.ts` `images.remotePatterns` includes the storage host.
3. PDP: the gallery is driven by the selected variant's `images` from the API. When the variant changes, reset the gallery index. Show a skeleton while it loads.
4. Search results page and suggestions dropdown: use the helper, with an `alt` from the localized name.
5. Remove the aliases from the TS types after the backend removes them (leave a TODO linked to P-17).

**Acceptance criteria:** manual check on these pages — home blocks, category, search results, suggestions, PDP (variant switch), cart, checkout, checkout success, orders list/detail, cancel items, returns, wishlist, seller page, marketer profile. Every page shows the variant image, or the product image when the variant has none, and never the placeholder when an image exists.

---

# PHASE F — SQL PERFORMANCE (request item 7) — target: < 500 ms p95 with realistic data volume

### Baseline measured on `marketplace_audit` (small data: 44 products, 106 variants, 178 categories, 287 blocks)

| Endpoint (country `uae`) | Time (cold) | Queries | Main issue |
|---|---|---|---|
| `GET home` | 173 ms | **304** | N+1: `files` ×114, `categories` ×43, `brands` ×42 |
| `GET categories` | 164 ms | **861** | N+1: `files` ×328, nested-set `lft/rgt` ×170, `brands … exists` ×170 (the second call is cached: 1 query) |
| `GET products?category=electronics` | 83 ms | 148 | 1 heavy aggregate query (17–26 ms at 44 products), `files` ×46, `brands` ×26 |
| `GET products?category_slug=…` | 47 ms | 43 | Heavy aggregate query 26 ms |
| `GET search?q=sony` | 39 ms | 28 | Same heavy query 26 ms |
| `GET l/{listing}` (PDP) | 64 ms | 83 | Synchronous `UPDATE products SET view_count` on GET, N+1 categories/brands |
| `GET catalog-listings` | — | — | **500 error** (P-17) |
| `GET categories/{slug}` | — | — | Returns **301** redirect (verify intended) |
| `GET pages/home` | — | — | **404** "Page not found" although 8 `home` pages exist (verify `is_default`/country/publish filters) |

These timings are small **only because the dataset is tiny**. Query count grows linearly with blocks, categories and products, and the aggregate query grows with listings × inventory rows. At 50k products and 100 blocks these endpoints will exceed 500 ms by a large margin.

## P-19 🔴 Rewrite the category products / search / listing query (`ProductQueryService::baseQuery`)

**Problem (`app/Services/Customer/ProductQueryService.php:170-420`)**
- About 20 `selectRaw` expressions, each a `COALESCE` of 3 correlated subqueries (admin / vendor / marketer), so **around 60 correlated subqueries per product row**. They are evaluated over the whole grouped set before `LIMIT`, because of `GROUP BY products.id` and sorting.
- **Aggregation fan-out bug:** `vl` is joined to `wi` (warehouse_inventories) in the same `GROUP BY`. `SUM(vl.rating_avg*vl.rating_count)`, `SUM(vl.rating_count)` and `COUNT` are multiplied by the number of inventory rows per listing, so `rating_count` and `rating_avg` weighting are **wrong** for multi-warehouse listings. `min_price`/`max_price` come only from vendor listings (ignoring admin and marketer).
- Buy-box columns are computed independently. Each column's subquery can pick a **different** row on price ties (no deterministic tie-breaker), so the name, slug, image and listing ID can mismatch.
- Filtering by condition or fulfillment on the joined `vl` changes the aggregates.

**Tasks**
1. Two-phase query:
   - **Phase 1 (IDs):** a lean query on an indexed buy-box table (below) → `SELECT product_id … WHERE country_id=? AND category_id IN (…descendants via lft/rgt) AND filters … ORDER BY … LIMIT/OFFSET` (or keyset pagination for infinite scroll), plus `COUNT` via the same filtered table.
   - **Phase 2 (hydrate):** batch-load products, buy-box listing rows, shipping method badges, brand/category names (one query each), and images via `ListingImageResolver` (P-17).
2. Create a `product_country_buybox` read model: `(product_id, country_id)` PK, `listing_type`, `listing_id`, `variant_id`, `price`, `compare_at_price`, `min_price`, `max_price`, `seller_count`, `total_stock`, `rating_avg`, `rating_count`, `fulfillment_model`, `shipping_method_id`, `is_express`, `category_id`, `brand_id`, `total_sold`, `score`, `updated_at`.
   - Maintain it through `ListingStockChanged`, listing observers (price/status), rating updates and `BuyBoxService`.
   - Build it with a `buybox:rebuild {--country}` command.
   - Choose rows deterministically: admin > vendor by (FBN, price, score, id) > marketer.
   - Indexes: `(country_id, category_id, score)`, `(country_id, category_id, price)`, `(country_id, brand_id)`, `(country_id, total_sold)`.
3. Search: use a MySQL FULLTEXT index on `products(name_en, name_ar, short_desc_en, short_desc_ar)` (ngram parser for Arabic) joined to the read model, or Laravel Scout (Meilisearch) if available. Keep the `LIKE` fallback only for queries under 3 characters.
4. Keep the API response identical (a contract test compares old and new JSON for the audit dataset, apart from the corrected aggregates).

**Acceptance criteria**
- On a generated dataset (P-22 seeder: 50k products, 150k variants, 200k listings, 8 countries), `products?category=` and `search` run at p95 < 300 ms server time, with ≤ 15 queries per request.
- Aggregates are verified against a brute-force calculation in tests.

---

## P-20 🟠 Home / page builder: remove the N+1 and cache correctly

**Problem:** `GET {country}/home` runs 304 queries (`files` ×114, `categories` ×43, `brands` ×42) through `Customer/HomeController`, `Customer/PageRendererService.php` and `Shared/PageBuilderService.php`.
- Block hydrators load relations per block (`hydrateAdImages`, `hydrateImageSlider`, category pills, brand strips, product rows at `:447`, `blockProducts.productVariant.product.images` at `:86`).
- `File` models are fetched by ID one at a time (`select * from files where id = ? limit 1`), from accessors on `Banner`/`SliderSlide`/`Category`/`Brand` images.
- Product rows re-resolve the buy-box per variant (`vendor_listings where product_variant_id = ? …` and `admin_listings where product_variant_id = ?`).
- The home response is 162 KB.

**Tasks**
1. Two-pass renderer:
   - pass 1 collects every ID needed by visible blocks (file IDs, category IDs, brand IDs, variant IDs, vendor IDs, flash sale IDs, custom page IDs);
   - pass 2 bulk-loads each type **once** (`whereIn`) and hydrates from maps.
   - Use `ListingImageResolver` and the P-19 buy-box read model for product blocks.
2. Replace per-row `File::find` accessors with eager relations (`with('file')`) or bulk maps. Add `Model::preventLazyLoading(!app()->isProduction())` in `AppServiceProvider` to catch regressions in dev and tests.
3. Cache the rendered page per `(page_id, version, country, locale, device_target, audience)` with tags `page:{id}`, `block:{id}`, `product:{id}`, `category:{id}`.
   - Invalidate from the page builder publish, block save, `PageSchedulerJob`, and listing/price/stock events (a short TTL for product blocks, e.g. 60 s, is acceptable).
   - Respect `page_blocks.cache_ttl_seconds`, `visible_from`/`visible_until`, and A/B variant.
   - Personalised or dynamic fragments (sponsored/paid ads injected by `PaidAdInjector`, wishlist state) are hydrated after the cached skeleton.
4. Slim the payload: return only fields the frontend components use (check against `src/components/shared/page-builder/sections/*`).
5. Fix `GET pages/home` returning 404 (a `page_type`/`is_default`/country filter mismatch) or remove the endpoint if the frontend does not use it (the frontend uses `/home`).

**Acceptance criteria:** home cold ≤ 25 queries and < 300 ms on the P-22 dataset with 100 blocks; warm < 50 ms; the content is identical to before (snapshot test).

---

## P-21 🟠 Categories list and tree: 861 queries

**Problem:** `GET {country}/categories` (`Api/Customer/CategoryController` → `UnifiedCategoryService`/`CategoryService`, `CategoryTreeResource`, `BrowseCategoryResource`) runs, per category:
- a `files` lookup (×328);
- a nested-set descendants query `select id from categories where lft between ? and ?` (×170);
- a `brands where exists (… products …)` query (×170).

It also loads `category_attributes` pivots and classified categories. It is cached after the first call (1 query), but a cold cache or invalidation is expensive, and the cache key/invalidation rules need checking (country, locale).

**Tasks**
1. Load all active and visible categories for the country in **one** query (join `country_categories`), and build the tree in PHP from `parent_id` (or `lft`/`rgt`).
2. Images: one bulk `files` query keyed by model ID (or the `categories.image_path` column).
3. Brands per category: precomputed `category_brand_counts (category_id, country_id, brand_id, product_count)`, maintained by `RecalculateCategoryStatsJob`, or one grouped query over the P-19 read model: `SELECT category_id, brand_id, COUNT(*) … GROUP BY …`, mapped to ancestors in PHP.
4. `product_count` per category from the same grouped query (descendants rolled up in PHP), not per-category subqueries.
5. Cache per `(country, locale)` with the tag `categories`. Invalidate on category CRUD, country_category changes, and product category change.
6. Separate the lightweight header nav tree (`categoriesTree` used by `src/layout/noon/header`) from the heavy browse payload.

**Acceptance criteria:** cold ≤ 6 queries and < 200 ms with 2,000 categories.

---

## P-22 🟡 PDP, indexes, write-on-read, and a performance harness

**Problem**
- The PDP (`GET {country}/l/{identifier}`, `ListingDetailController`) runs 76–83 queries, including a synchronous `UPDATE products SET view_count = view_count + 1` on every GET (5 ms, row lock contention under load; `ProductViewLogJob` already exists), plus N+1 on categories and brands (related/recommended products).
- Missing or weak indexes found in the schema:
  - `product_images`: no FK on `product_variant_id`; needs `(product_variant_id, is_primary, position)` and `(product_id, product_variant_id, position)`.
  - `warehouse_inventories`: no unique `(warehouse_id, vendor_listing_id)` / `(warehouse_id, admin_listing_id)`; no `(admin_listing_id, quantity_available)`.
  - `order_items`: needs `(sub_order_id, fulfillment_status)`, plus `marketer_listing_id` (P-02).
  - `sub_orders`: needs `(vendor_id, status, delivered_at)` for payouts, and `(order_id, status)`.
  - `marketer_listings`: `(source_listing_id)` (P-15).
  - `coupons`: `(code, is_active)`; `coupon_usages`: `(coupon_id, customer_id)`.
  - `categories`: `(parent_id, is_active, is_visible, sort_order)`.
  - `products`: FULLTEXT (P-19).
  - `page_blocks`: `(page_id, section_id, is_visible, position)`.
  - Verify each with `EXPLAIN` before adding; do not add duplicate indexes (`page_blocks` already has 13 keys, some redundant, e.g. `page_id_index` alongside `(page_id, position)`).
- No realistic data volume exists to test against.

**Tasks**
1. Move view counting to `ProductViewLogJob` (queued) with batched increments (Redis counter flushed every minute). The GET becomes read-only.
2. PDP: eager-load and bulk-load related products through the P-19 read model and `ListingImageResolver`. Target ≤ 20 queries.
3. An index migration based on `EXPLAIN ANALYZE` of the top queries (from the query log of P-19/P-20/P-21/PDP/cart/checkout/orders list). Remove redundant indexes.
4. Performance harness:
   - `database/seeders/PerformanceDatasetSeeder` (volumes in P-19);
   - an artisan command `perf:profile` that replays a list of endpoints (the `scratchpad/profile.php` approach from this audit: HTTP kernel + query log) and prints time, queries, slowest SQL and duplicate queries;
   - fail CI if any endpoint exceeds its budget (p95 500 ms total; query budgets per endpoint).
5. Turn on `DB::whenQueryingForLongerThan(500, …)` logging in production, and `preventLazyLoading` in non-production.

**Acceptance criteria:** `perf:profile` report attached to the PR shows every storefront endpoint (home, categories, category products, search, suggestions, PDP, cart, checkout prepare, orders list) under 500 ms p95 on the performance dataset.

---

# PHASE G — PANELS (request item 6)

### Smoke test result: every no-parameter GET page, logged in with a real account of each panel, on `marketplace_audit`

| Panel | Pages tested | OK | Failing |
|---|---|---|---|
| Admin (super_admin `admin@admin.com`) | 240 | 236 | 4 |
| Vendor / partner (product vendor) | 88 | 83 | 3 errors + 2 expected 403 (classified section) |
| Vendor — **classified** | not testable | — | The dump has **no `classified_vendor`** vendor |
| Marketer | 30 | 30 | 0 |
| Travel agency | 35 | 35 | 0 |
| Supervisor (carrier) | 21 | 21 | 0 |
| Delivery agent | 13 | 13 | Fatal when the layout renders twice in one process (see below) |

Not covered: routes with parameters (show/edit pages), POST/PUT/DELETE actions, and JSON APIs of the mobile apps.

## P-23 🔴 Fix the confirmed panel errors

1. **Admin `GET /analytics/flash-sales` → 500:** `Unknown column 'fs.title'` (raw SQL in the flash-sale analytics controller/service). `flash_sales` has no `title` column; use the real name columns (`name_en`/`name_ar` or similar; check the schema) and localize.
2. **Admin `GET /shipping-companies/fallback-rules` → 404:** route order conflict. `shipping-companies/{shippingCompany}` is declared before `fallback-rules`, so `fallback-rules` is resolved as a model ID. Move the static route above the resource route, or constrain `{shippingCompany}` with `whereUuid`.
3. **Admin `GET /docs` → 500:** `Route [admin.docs.panels.marketer] not defined` (`resources/views/admin/docs/index.blade.php`). **`GET /docs/features/finance` → 500:** `Route [admin.marketers.payouts.index] not defined`. Add the missing routes/pages, or fix the links.
4. **Partner `GET /wallet` → 500:** `Call to undefined relationship [country] on model VendorAdmin`. Use `vendorAdmin->vendor->country`, or add the relation.
5. **Partner `GET /returns` → 500:** `Unknown column 'first_name'` selecting from `customers` (the customer name column differs). Fix the select and all similar selects (`grep -rn "first_name" app resources`).
6. **Partner `GET /ads/categories` → 500:** `Call to undefined method Category::vendorListings()`. Query through products → variants → vendor listings, or add a `hasManyThrough`/deep relation.
7. **Delivery panel layout:** `function navActive()` is declared inline in the layout Blade (compiled view, `resources/views/delivery/layouts/*.blade.php`). This causes a fatal `Cannot redeclare navActive()` whenever the layout is rendered twice in one PHP process (Octane, queued mail rendering, tests). Move it to a helper, a view composer or `@php $navActive = fn(...)`.
   - Same layout: the COD remittance banner is `dir="rtl"` for English users, and the amount uses a hardcoded `ر.س` symbol (agent in AED country). Use locale direction and currency formatting.
8. **Customer API `GET {country}/catalog-listings` → 500** (covered in P-17, verify fixed).
9. **Customer API `GET {country}/pages/home` → 404** (P-20) and `categories/{slug}` → 301 (confirm this is intended).

**Acceptance criteria:** re-run the panel smoke test (add it as `tests/Feature/Panels/PanelSmokeTest.php`, parameterised by panel guard and user factory). It reports 0 failures for these routes.

---

## P-24 🟠 Panel coverage: parameterised pages, actions and missing lifecycle actions

**Goal:** extend the smoke test and close the functional gaps the lifecycles (P-01…P-16) require in each panel. First **verify** each item below (some may exist under another name), then implement what is missing.

**Tasks**
1. **Smoke test v2:**
   - for every route with parameters, resolve a real ID from the factory scenario (P-00) through the route's model binding;
   - for every POST/PUT/DELETE, assert validation errors (422/redirect with errors) on an empty payload, not 500;
   - run for: admin (super admin + a limited role, to verify permission gates), vendor product, **vendor classified** (seed a `classified_vendor`), marketer, travel agency owner and member, supervisor, delivery agent;
   - also run each panel's JSON API (`api_partner`, `api_vendor`, `api_marketer`, `api_travel_agency`, `api_carrier`, `api_delivery`) with JWT.
2. **Lifecycle action checklist — verify and implement:**
   - **Admin:**
     - confirm bank transfer payment (P-05);
     - order intervention uses the cancellation and refund services (P-06/P-07);
     - return inspection per item with restock decision (P-10);
     - warranty purchases list with activate, cancel and refund, and warranty claim resolution actions (P-09);
     - marketer campaign approval requiring commission amounts, and platform campaign creation from admin listings (P-12/P-14);
     - payout run preview with a per-sub-order breakdown (P-11);
     - financial report (P-11);
     - inventory reconcile report (P-13);
     - buy-box rebuild trigger (P-19).
   - **Vendor (product):**
     - sub-order state actions through the state machine (confirm → pack → ship → handover), inventory through `InventoryService`;
     - return requests (approve/reject for FBM, view inspection);
     - brand warranty claims response;
     - campaign creation from own listings plus marketer requests inbox (P-14);
     - payout statements with line items (P-11);
     - low-stock alerts.
   - **Vendor (classified):**
     - classified listing CRUD, inquiries inbox, contract templates;
     - verify the `vendor_type` gate hides product-only menus and vice versa.
   - **Marketer:** P-16 checklist (listings, samples, finance, withdrawals, referral/QR, reports).
   - **Travel agency:**
     - packages CRUD with pricing tiers and media;
     - bookings lifecycle (inquiry → booking → payment → confirmation → cancellation/refund);
     - campaign invitations/offers;
     - finance and bank accounts;
     - member roles.
   - **Supervisor (carrier):**
     - unassigned shipments queue — currently **all supervisors of all companies are notified for every shipment** (`Partner/OrderController.php:~330`, "no shipping_company_id on shipments yet"); add `shipments.shipping_company_id` and route by zone or company;
     - agent roster, assignment and reassignment, live map, COD settlements approval, carrier claims.
   - **Delivery agent:**
     - accept → pickup → OTP → deliver (COD capture via P-08) / fail → RTO;
     - shifts, earnings, COD settlement submission, documents.
3. Record every item verified as already working in a checklist table at the top of the PR description, with the test that proves it.

**Acceptance criteria:** smoke test v2 passes with 0 × 500 for all panels and APIs, and every checklist item has a passing test or a documented "exists at …" reference.

---

# PHASE H — FRONTEND i18n & DYNAMIC CONTENT (request items 4 & 5)

## P-25 🟠 Every frontend page translated (ar/en) and using `useLocale` from `@/src/hooks/use-locale`

**Findings**
1. **11 files import `useLocale` directly from `next-intl`** instead of `import useLocale from "@/src/hooks/use-locale";`:
   - `src/features/noon/profile/orders/cancel-items/components/cancel-items-form.tsx`
   - `src/features/noon/live-streams/StreamDetailFeature.tsx`
   - `src/features/noon/live-streams/StreamsListFeature.tsx`
   - `src/features/noon/gift-cards/view/components/theme-selector.tsx`
   - `src/features/noon/shop/filter/desktop-view.tsx`
   - `src/features/noon/cart/coupon-input-card.tsx`
   - `src/features/flights/booking-details/index.tsx`
   - `src/features/flights/bookings-list/components/booking-card.tsx`
   - `src/layout/noon/profile/user-summary.tsx`
   - `src/hooks/use-handle-locale.ts` and `src/hooks/use-region.ts` are low-level hooks; they may keep `next-intl`, but they must be documented as the only exceptions besides `use-locale.ts` itself.
2. **Hardcoded Arabic** in `.tsx`:
   - `app/[locale]/(noon)/(pages-with-footer)/marketer/[slug]/page.tsx`
   - `src/layout/noon/header/categories-nav.tsx`
   - `src/features/classified/classifiedList/top-filter-bar.tsx`
   - `src/features/flights/package-details/index.tsx`
3. **Hardcoded English JSX text / attributes** (non-exhaustive; the scan catches single-line text only):
   - `live-streams/StreamsListFeature.tsx`: "Live Streams", "Watch live, upcoming, and past broadcasts", "No streams yet", "Check back soon…", status labels `LIVE`/`Scheduled`/`Ended`.
   - `live-streams/StreamDetailFeature.tsx`: "Stream not found", "No comments yet. Be the first!".
   - `classified/classified-view/classified-sidebar.tsx`: Following/Follow/Rating/General Tips/3 safety tips/Add New Listing/Explore More.
   - `classified/classified-view/classified-inquiry.tsx`: "Send Message".
   - `classified/classified-view/classified-gallery.tsx`: 6 aria-labels/alt.
   - `classified/classifiedList/index.tsx`, `classified-card.tsx`, `classified-recommended.tsx`: alt/aria.
   - `noon/seller/seller-info-sidebar.tsx`: "Seller Rating", "What do these mean?", "Seller Since".
   - `noon/seller/seller-reviews-list.tsx`: "Report", "View All Reviews".
   - `noon/shop/index.tsx`: "Try adjusting your filters".
   - `noon/cart/cart-item.tsx:157`: "Free shipping".
   - `noon/productView/more-offers-sheet.tsx:35`: "Scrollable Content" (debug text).
   - `noon/productView/dialogs/extended-warranty-sheet.tsx`: aria "Close".
   - `noon/home/index.tsx`: alt "Page under construction".
   - `noon/marketer-profile/components/profile-actions.tsx`: alt "QR Code".
   - `help-sheet/*`: "Need more help?", "No items found", "Choose applicable items", "Search Items", aria Back/Close.
   - `flights/package-details/components/booking-sidebar.tsx`: "Sold Out".
   - `components/shared/dialogs/confirm-dialog/index.tsx`: "Close".
   - `components/shared/page-builder/sections/section-title.tsx`: "View All".
   - `components/shared/page-builder/sections/hero-slider.tsx`: alt "Announcement".
   - `components/shared/Logo.tsx`: alt "Logo".
   - `layout/noon/header/search-field.tsx`: "Enter", "Oops", aria "Clear input"/"Remove item".
   - `layout/noon/header/Header.tsx`: alt "Cart", and **" Dubai" hardcoded location** (see P-26).
   - `hooks/use-wishlist.ts:121`: toast "Add to …".
4. **Locale key parity:**
   - `en.json` has 1088 keys and `ar.json` has 1084.
   - Missing in `ar`: `classified.home`, `classified.classifieds`, `classified.globalPageTitle`, `classified.for`, `classified.recommendedForYou`.
   - 1 key exists only in `ar`.
5. Backend-driven text must use `{ar, en}` pairs from the API (e.g. `Bilingual::pair`). Verify that components do not render `name_en` regardless of locale (grep `_en}` / `.name_en` without a locale switch).

**Tasks**
1. Replace the direct imports with `import useLocale from "@/src/hooks/use-locale";`. For server components, use the existing server helper (`src/helpers/getLocale.ts`) consistently.
2. Move every string above, and every other string found by a full scan, into `locale/en.json` and `locale/ar.json` under the feature namespace. Include multi-line JSX text, `placeholder`, `title`, `aria-label`, `alt`, toast messages, zod validation messages and `metadata` (`generateMetadata` titles/descriptions per page).
3. Add ESLint guards:
   - `no-restricted-imports` for `useLocale` from `next-intl` (except the hook files);
   - `i18next/no-literal-string` (or `eslint-plugin-react/jsx-no-literals` with an allowlist) for `src/**` and `app/**`.
4. Add a CI script `scripts/check-locale-parity.mjs` that fails when en/ar keys differ or when an `ar` value equals the `en` value (allowlist brand names such as `OpenSooq`).
5. RTL check: every page renders correctly with `dir="rtl"` (icons flip, `ms/me` instead of `ml/mr`).
6. Go through all 43 routes under `app/[locale]` in both locales and record the checklist in the PR.

**Acceptance criteria:** ESLint and the parity script pass with 0 violations, and a visual pass of all 43 routes in `/ar` and `/en` shows no untranslated text.

---

## P-26 🟠 Remove static and mock sections from frontend pages; make them API-driven

**Findings**

| Page / component | Static source | Required backend data |
|---|---|---|
| `app/[locale]/(noon)/(profile)/disputes/page.tsx` | `getDisputes()` from `src/features/noon/profile/support/data.ts` (mock with unsplash images). The detail page already uses `support.actions` | `GET disputes` (exists: `NotifyVendorDisputeOpenedJob`, `Dispute` model; verify the customer route) |
| `src/features/noon/profile/support/data.ts` | Mock tickets and disputes (incl. "Never arrived") | Delete once disputes use the API; the tickets pages already use `support.actions` |
| `src/features/help-sheet/api/fetchHelpTree.ts` → `mockData.ts` `helpTree` | Static help tree | `help_center_categories`/`help_center_articles`/`faqs` exist in the DB; add `GET help-center/tree?context=order\|return\|…` |
| `src/features/classified/classified-view/index.tsx:20` | `initialData = MOCK_CLASSIFIED_DETAIL` default | Classified detail API (`ClassifiedDetailService`); remove the mock default and show a skeleton or 404 |
| `src/features/classified/classifiedList/classified-card.tsx:23-33` | Hardcoded `specs` array ("2026", "Kia", "Sportage", …, **"Dummy data"**) shown on every card | Listing attributes from the API (`classified_listings` attributes) |
| `src/features/classified/classifiedList/mock-data.ts` | `MOCK_CATEGORY_TAGS`, `MOCK_SIDEBAR_CATEGORIES`, `MOCK_SEE_ALSO_LINKS`, `MOCK_LISTINGS` | Verify none are imported; delete, or wire to `classified_categories` |
| `src/features/noon/gift-cards/data.ts` | `giftCardCategories` (placeholder images), `giftCardAmounts`, `giftCardBrandLogos` (used by `brands-strip.tsx`, `amount-selector.tsx`, `gift-card-form.tsx`, `gift-cards/[slug]/page.tsx` via `getGiftCardCategory`) | `gift_card_batches` / settings: themes, denominations, brands per country (`CustomerGiftCardStoreController`) |
| `src/layout/noon/header/Header.tsx:105,272` | `" Dubai"` hardcoded delivery location | Customer default address city, or selected city (cookie), localized |
| `src/features/noon/shop/filter/filter-data.ts` | Static categories and colors | Appears unused (no importers); delete, and make filters come from the `products` API facets |
| `src/features/noon/live-streams/StreamsListFeature.tsx:11-13` | Status labels | Translations (P-25) |
| `src/features/noon/productView/more-offers-sheet.tsx:35` | "Scrollable Content" placeholder | Remove |
| `src/features/noon/home/index.tsx` | "Under construction" image when there are no sections | Acceptable fallback, but translate the alt text and show a localized empty state |
| Constants files (`profile/orders/helpers/constants.ts`, `addresses/helpers/constants.ts`, `security-settings/helpers/constants.ts`, `marketers-list/helpers/constants.ts`, `flights/*/constants.ts`, `support/case-list/constants.ts`) | Enumerations/options | Verify each: UI enums may stay if they use translation keys; business data (reasons, countries, cities, statuses from the backend) must come from the API |

**Tasks**
1. For each row, add or confirm the backend endpoint (keep the API response format consistent: `ApiResponse::success`, `{ar, en}` pairs), then replace the static import with a feature `api/` fetch per `frontend/CLAUDE.md` (RSC fetch + `Suspense` skeleton + error/empty states).
2. Delete the mock files once they have no importers (`mock-data.ts` ×2, `mockData.ts`, `support/data.ts`, `filter-data.ts`), and make `grep -rn "mock\|dummy" src app` return nothing in production code.
3. Page-by-page audit of all 43 routes: list every section and mark it dynamic or static-by-design (e.g. legal copy from CMS `custom_pages`). Everything customer-visible that an admin should be able to change must be CMS or API driven.

**Acceptance criteria:** no mock/data file imports remain, every section of the 43 routes is backed by an API or an explicitly approved static UI element, and changing the data in the admin changes the page.

---

## Appendix A — Other findings to fold into the related prompts

- `APP_DEBUG=true` in `backend/.env` (local). Make sure production has `APP_DEBUG=false`, since API 500 responses expose stack traces and file paths (seen during the audit).
- `backend/.env` points at `marketplace_platform_live` with `root/root`. Use a dedicated least-privilege DB user outside local development.
- `sub_orders.gateway_fee` comment says "In cents", which contradicts the integer money convention. Fix the comment in the P-03 migration.
- `delivery_assignments`/`shipments` both have `delivery_otp`. Keep a single source.
- `refunds.net_refund` is a generated column (`amount − gateway_fee_deducted − tax_deducted`) and is `UNSIGNED`. A deduction larger than the amount will error; guard in `RefundService` (P-07).
- `marketer_campaigns` table definition in the dump ends without `ENGINE=` (CHECK constraints). Verify that the migration runs on MySQL 8 and on the test DB (SQLite would ignore it).

## Appendix B — Audit artefacts

- The audit database `marketplace_audit` (local MySQL) holds the imported dump. Drop it when no longer needed.
- Profiling and smoke scripts used for the baselines live in the session scratchpad (not in the repo). P-22 and P-23 turn them into committed artisan commands and tests.
