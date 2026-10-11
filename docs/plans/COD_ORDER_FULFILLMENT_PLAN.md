# COD Order → Carrier → Delivery Agent: Fix Plan

Order: `NOON-20261010-ZGFVRS` (`01a12577-61cf-725c-a868-276065630518`), COD, AED 94,499, sub-order `...-01` (`01a12577-6325-72ca-87b5-5dfdd52ba3b1`).
Source of facts: `backups/marketplace_platform.sql` (Oct 11) + current code.

## 1. What the live DB shows

| Item | Value |
|---|---|
| `orders.status` / `payment_method` / `gateway_code` / `payment_status` | `placed` / `cod` / `cod` / `pending` |
| `sub_orders.status` | `confirmed` (vendor confirmed Oct 10 09:00), `fulfillment=fbn`, carrier = Aramex |
| `payment_gateways` row `cod` | `type = 'offline'` (same type as bank_transfer) |
| `payment_transactions` COD row | `pending`, **has a payment-proof PNG + note "wwwwwwwwwww"** (old frontend demanded proof for every `offline` gateway) |
| `shipments` row for this sub-order | **NONE** |
| `delivery_assignments` | none |
| `order_status_histories` | 2 duplicate "confirmed → confirmed / Carrier assigned: Aramex" rows |

## 2. Root causes

1. **The error.** `OrderInterventionService::guardOfflinePaymentApproval` blocks leaving `placed` when `PaymentGatewayFactory::isOffline(code)` is true and payment isn't `captured`. The `cod` gateway row is `type='offline'`, so COD was treated like a bank transfer → "Order payment is pending admin approval."
   - Commit `f05c6228` (Oct 10 18:22) already adds `if ($gatewayCode === 'cod') return false;` plus the frontend no-proof-for-COD fix. **The error still appears, so the live server is most likely not running that commit** (not deployed / opcache). Verify, don't assume.
2. **Wrong lever.** The admin modal changes the *order-level* status. The sub-order is already `confirmed`. That click does not make the carrier see the order.
3. **No `Shipment` exists, so the carrier sees nothing.** Carrier "Unassigned" = `Shipment whereDoesntHave(deliveryAssignment)` (`CarrierPortal/AssignmentController`). A Shipment is only created in `Partner/OrderController` (~L340, vendor ship step, which also sets sub-order `shipped`). Admin carrier reassignment only sets `carrier_id`.
4. **Possible FBN gap.** Sub-order is `fbn`; the only Shipment-creation paths found are vendor-driven. Unverified who creates it for FBN.
5. **Order status doesn't roll up.** Order stays `placed` while its only sub-order is `confirmed` (unverified why; Prompt 2).
6. **Smells:** `cod` type `offline` is semantically wrong; stale proof on a COD txn; duplicate history rows on reassign.

## 3. Target COD flow

```
placed ─► confirmed (no payment gate for COD)
  ─► processing ► packed ► shipped   (Shipment created, carrier notified)
  ─► carrier supervisor assigns agent (DeliveryAssignment + OTP)
  ─► out_for_delivery ► delivered     (agent collects cash)
  ─► CaptureCodOnDelivery: payment captured, txn succeeded, ledger, coupon consumed
  ─► agent COD settlement ─► carrier remit ─► vendor payout
```
Sub-order transitions are strict: `confirmed → shipped` is NOT allowed (`confirmed → processing → packed → shipped`).

## 4. Checklist of things you might have missed

- [ ] Is `f05c6228` deployed (backend + frontend)? Run `php artisan optimize:clear`, `queue:restart`, reload php-fpm.
- [ ] Which endpoint does the modal call (order vs sub-order)? Both hit the same guard.
- [ ] Who creates the Shipment for FBN sub-orders?
- [ ] Agent must be `active`, same country as the order, in Aramex's company; supervisor needs `assign_orders`.
- [ ] Other COD orders with pending payment + uploaded proof (Prompt 5 query).
- [ ] COD remittance math: agent collects 94,499; vendor payout 79,199 (commission 10,800).
- [ ] `cod_fee` is 0 on this order; confirm that's intended.

## 5. Execution plan (token-efficient)

Use sonnet for all. 1–2 are read-only; 3–4 edit code; 5 is data (ask before running on live).

### Prompt 1 – Verify deploy state (no edits)
> In /var/www/marketplace/backend confirm commit f05c6228 is on the deployed branch and `PaymentGatewayFactory::isOffline('cod')` returns false. Find what the admin Update Status modal posts to (grep callers of `updateOrderStatus` / `updateSubOrderStatus` in app/Http/Controllers/Admin). List every caller of `guardOfflinePaymentApproval` or `isOffline`, and any other place (checkout, vendor confirm, OrderStateMachine) that blocks COD. Reply file:line list, <= 15 lines.

### Prompt 2 – Find the shipment/roll-up gap (no edits)
> Read app/Services/OrderStateMachine*, OrderFulfillmentService.php, Partner/OrderController.php (ship method ~L280-380) and Models/Order.php::syncStatusFromSubOrders. Answer: (a) why orders.status stays `placed` when all sub-orders are `confirmed`; (b) for `fbn` sub-orders, what creates a Shipment and moves the sub-order to `shipped`; (c) is there any admin action that can create a Shipment. Reply with findings + minimal proposed change, <= 20 lines.

### Prompt 3 – Backend fixes
> In /var/www/marketplace/backend, matching existing style, no new abstractions:
> 1. Add `PaymentGatewayFactory::requiresProofApproval(string $code): bool` (false for `cod`; true only for `type=offline` non-COD) and use it in `guardOfflinePaymentApproval` and every place Prompt 1 listed. COD must never be blocked on payment approval.
> 2. `syncStatusFromSubOrders`: advance `placed → confirmed` when every non-cancelled sub-order is confirmed or later.
> 3. Per Prompt 2, add an admin/FBN path that creates the Shipment, reusing the Shipment::create + NewUnassignedShipmentArrived logic from Partner/OrderController (extract into one service method used by both).
> 4. Carrier reassignment writes no history row when carrier_id is unchanged.
> 5. Checkout ignores `payment_proof` when payment_method=cod.
> Add tests: COD placed→confirmed passes; bank_transfer pending still blocked; roll-up; shipment visible to carrier. Run only those tests. Reply with a diff summary.

### Prompt 4 – Frontend/admin UX
> In /var/www/marketplace/frontend (checkout) and the admin order page: confirm COD never shows proof upload; in the admin Update Status modal for COD show "Cash is collected on delivery" and a "Create shipment / hand to carrier" action (Prompt 3.3); list only valid next statuses. Minimal changes; lint/typecheck touched files only.

### Prompt 5 – Repair data (dry-run only; ask before live)
> Write (do not run on live) an artisan/tinker script that: (a) lists COD orders with payment_status=pending and a proof path on the COD txn; (b) for order 01a12577-61cf-725c-a868-276065630518 clears the proof/note on the COD txn, moves order to confirmed via the service (so history is written), creates the Aramex Shipment; (c) prints a dry-run summary first. Include an equivalent SQL for review.

### Prompt 6 – End-to-end verification
> On a local copy of the DB walk: COD order → confirmed → shipment → Aramex supervisor assigns agent → out_for_delivery → delivered with OTP → check payment_status=captured, txn succeeded, ledger entry, COD settlement row. Report pass/fail per step, <= 15 lines.

## 6. Immediate workaround on live (no code)
1. Deploy `f05c6228`, clear caches, restart workers.
2. Retry Update Status → Confirmed.
3. If still no Shipment: move the sub-order `processing → packed → shipped` (vendor, or new admin action). It then shows in Aramex → Unassigned → assign agent.
