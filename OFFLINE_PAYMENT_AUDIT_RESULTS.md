# Offline Payment Approval — Task 07 Codebase-Wide Sweep Results

Date: 2026-09-18

## Method

Grepped the whole repo (`backend/`, `frontend/`, `carrier_app/`, `delivery_app/`,
`partner_app/`, `travel_app/`) for: `payment_method`, `country_payment_gateway`,
`payment_gateway`, `PaymentGatewayFactory`, `gateway_code`, "bank transfer",
"pay later", "deposit", "top up"/"topup", "recharge", "wholesale", "B2B",
"subscription", "invoice payment". Traced every real payable flow found and
checked whether it goes through `PaymentGatewayFactory`/`payment_gateways`
(and is therefore already covered by Tasks 01-03/06) or is a bespoke path.

## Findings

### Confirmed already covered (no changes needed)

- **Checkout orders** (`CheckoutController`) — Task 01-04. `isOffline()` guard
  in `ExpirePendingPaymentsJob` and `OrderInterventionService` verified: COD
  (`type=internal`) and wallet (`type=internal`) are correctly excluded from
  both the expiry-exemption and the fulfillment-approval-gate logic; only
  `type=offline` gateways (bank transfer) are gated. ✅
- **Gift card purchases** (`GiftCardPurchaseService`) — Task 05. Now routes
  through `PaymentService`/`PaymentGatewayFactory`; delivery job gated on
  `isOffline()` + `payment_status`. ✅
- **Paid ad bookings, `Offline` settlement** (`AdBookingService`) — Task 06.
  Has its own minimal proof-upload + admin-approval flow since ad bookings
  don't share `payment_transactions`. ✅
- **Customer wallet** (`Customer/WalletController.php`) — this is a
  *payout-only* wallet (redemption of gift cards/vouchers, cashback, bank
  withdrawal requests). There is no customer "top-up via payment gateway"
  endpoint anywhere in the codebase — confirmed by grepping
  `PaymentGatewayFactory`/`payment_gateway` call sites repo-wide; only
  checkout, gift cards, and ads invoke it. No gap here.
- **`travel_app/`, `carrier_app/`, `partner_app/`, `delivery_app/`** — grepped
  for any payment-gateway/bank-transfer usage; none found. These apps don't
  take customer payment directly (bookings/deliveries are fulfillment-side,
  billed through the existing order/vendor-payout system). No gap here.

### Flagged but intentionally NOT changed (out of scope of this plan)

Two vendor-facing billing systems exist that **never touch
`payment_gateways`/`PaymentGatewayFactory` at all** — they are pure manual
admin invoicing, not a "customer selects a payment method and pays" flow:

- **Vendor subscriptions** (`SubscriptionService`, `VendorSubscriptionInvoice`,
  `Partner/SubscriptionController`, `Admin/SubscriptionController`): a vendor
  clicks "Subscribe", the subscription is created `Active` immediately (plan
  benefits like free shipping / commission discount / listing limits apply
  right away), and an `Open` `VendorSubscriptionInvoice` is created. There is
  no gateway selection UI and no online capture path at all — the invoice
  only ever moves to `Paid` when an admin manually clicks "Mark Paid" in
  `Admin/SubscriptionController::markInvoicePaid()`. This is structurally
  already "pending until admin says so" for every invoice, always — there's
  no online/instant path it could bypass. However, note the business-logic
  observation: **the vendor receives subscription benefits before the
  invoice is ever paid**, which is a revenue-collection gap, not an
  offline-payment-approval gap. Fixing that would mean changing the
  subscription activation model (e.g., holding benefits until first invoice
  paid) — a product decision outside this plan's remit (invoice-upload +
  approve/reject on a payment method selection). Not changed.
- **Vendor ad subscriptions** (`Partner/AdSubscriptionController::subscribe()`,
  `VendorAdSubscription`): same shape — `amount_paid` is recorded on the
  model but no gateway or payout-deduction call ever actually collects it;
  the ad boost activates for 30 days immediately. Also has no payment-method
  selection UI. Flagged for product/eng follow-up as a separate money-leak
  bug; not part of this offline-payment-approval sweep. Not changed.

### COD verification

Explicitly re-verified across all three gating points added by Tasks 01-03:
- `PaymentGatewayFactory::isOffline('cod')` → `false` (COD's `payment_gateways.type = internal`).
- `ExpirePendingPaymentsJob` excludes `cod`/`wallet` by literal string *and* by `isOffline()`.
- `OrderInterventionService::guardOfflinePaymentApproval()` only fires when `isOffline($gatewayCode)` is true — COD orders pass through untouched, continuing to rely on `CaptureCodOnDelivery`.
- No proof-upload requirement or admin-approval gate is applied anywhere to COD orders, gift cards, or ad bookings.

## Fixes applied

None required — the sweep found no uncovered "customer selects payment
method and pays" flow. The two vendor-billing systems above are flagged as
separate, pre-existing gaps outside this plan's scope (no payment-gateway
involvement at all, so there is no "offline vs. online vs. COD" distinction
for them to be missing) and are documented for follow-up rather than
modified, to avoid making an unrequested change to vendor billing/revenue
behavior.

---

## Task 08 — End-to-end regression pass (2026-09-18)

Re-ran the regression pass after the earlier Task 05/06 concurrent-test-suite
contention (shared `marketplace_test` MySQL DB, `RefreshDatabase`
deadlocks/duplicate-migration errors) had cleared, with nothing else running.

### Test suite

- Full backend suite: `cd backend && php artisan test` → **252 tests, 247
  passed, 1071 assertions**. Failures:
  - `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response`
    — pre-existing, unrelated to this effort (default Laravel scaffold test
    hitting `/` which 404s because this app has no bare `/` route).
  - `Tests\Feature\Ads\AdBookingServiceTest` × 3 — `There is no permission
    named ad_bookings.review for guard admin`. This is the pre-existing
    permission-seeding gap already identified and documented by Task 06; not
    a regression from this pass.
  - No other failures/errors. No deadlocks or duplicate-migration errors on
    this clean re-run — the earlier contention was confirmed to be a
    concurrency artifact, not a real bug.
- Targeted re-run: `tests/Feature/Customer/GiftCardPurchaseAndRedemptionTest.php`
  + `tests/Feature/Checkout/PaymentMethodMatrixTest.php` → **18/18 passed**,
  clean.
- Frontend: `cd frontend && npx tsc --noEmit` → clean (no errors).
  `npm run build` → succeeds (Next.js production build compiles, typechecks,
  and generates all routes without error).

No regressions found; no code changes were required.

### Scenarios manually traced (reading current code, not commit messages)

1. **Checkout with bank transfer**: `OrderInterventionService::
   guardOfflinePaymentApproval()` (`backend/app/Services/OrderInterventionService.php`)
   blocks any `updateOrderStatus`/`updateSubOrderStatus` transition out of
   `placed` while `PaymentGatewayFactory::isOffline($gatewayCode)` is true and
   `payment_status !== 'captured'`, except cancellation (always allowed).
   COD/wallet (`type=internal`) and redirect gateways never match `isOffline()`
   so they pass through untouched, confirmed unaffected via
   `CheckoutController` (`$isCod = $gatewayCode === 'cod'`) and
   `CaptureCodOnDelivery`.
2. **Gift card purchase, offline gateway**: `Admin/TransactionController::
   confirmBankTransfer()` is the only place `GiftCardPurchaseService::
   dispatchPendingDeliveries($order->fresh())` is called for offline-gateway
   orders (after transaction is marked `succeeded` and order `captured`).
   `rejectOfflinePayment()` deliberately does not call it. Confirmed no other
   unconditional dispatch of the gift-card delivery job remains.
3. **COD checkout**: unaffected end-to-end — no proof-upload guard fires
   (`isOffline('cod')` is false), no admin-approval gate applies, and
   `CaptureCodOnDelivery` still captures + posts ledger + consumes coupon
   usage on delivery exactly as before.
4. **Paid ad offline settlement**: `AdBookingService::markOfflinePaid()`
   requires a non-empty `$proofFilePath` (throws `proof_required` otherwise)
   and is the only path that moves an `Offline`-settlement booking out of its
   unpaid/unsettled state; nothing else activates it.
5. **Admin transactions show view**
   (`backend/resources/views/admin/transactions/show.blade.php`): displays
   the proof file/image (or "no proof uploaded"), the customer `note` when
   present, a "Confirm Bank Transfer" button (`js-confirm-bank-transfer`) and
   a "Reject" button (`js-reject-offline-payment`) wired to
   `TransactionController::rejectOfflinePayment()`.
6. **Reject recoverability**: `rejectOfflinePayment()` sets the transaction
   `status` to `failed` and the order `payment_status` to `failed`; it does
   **not** cancel the order or block re-upload. `BankTransferProofService::
   upload()` and `OrderController::uploadBankTransferProof()` guard only on
   `isOffline($order->payment_gateway_code)`, with no check on
   transaction/payment status — so the customer can re-upload a corrected
   proof on the same transaction row at any time. `confirmBankTransfer()`
   only rejects re-confirmation when `status === 'succeeded'`, so a
   previously-rejected (`failed`) transaction can still be approved later
   once the customer resubmits valid proof. The order is left in a sane,
   recoverable state — not stuck, not silently lost.

### Residual known gaps (unchanged from Task 07, out of scope)

- Vendor subscriptions (`SubscriptionService`, `Partner/SubscriptionController`)
  and vendor ad subscriptions (`Partner/AdSubscriptionController`) activate
  benefits before invoice payment is collected/confirmed. Neither goes
  through `PaymentGatewayFactory` at all (pure manual admin invoicing), so
  there's no offline/online/COD distinction for this plan to close — flagged
  as a separate revenue-collection gap for product/eng follow-up, not
  touched here.

### Conclusion

No regressions from Tasks 01-07. No code changes were required for this
regression pass; this note is the only change.
