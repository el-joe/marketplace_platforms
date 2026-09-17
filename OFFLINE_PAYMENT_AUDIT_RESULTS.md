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
