# Offline Payment → Admin Approval + Invoice Upload — Implementation Plan

## Context (read this before running any sub-agent)

Stack: Laravel monorepo at `/var/www/marketplace`. Backend = `backend/` (Laravel, blade admin + JSON APIs). Frontend = `frontend/` (Next.js/React, `frontend/src/features/noon/...`).

**Goal:** For every payable flow in the app, any payment method that is *not* an online gateway (no redirect/webhook auto-confirmation) and *not* COD must:
1. Require the customer to upload an **invoice / proof-of-payment document** (required field) and allow an **optional note**, before the order/purchase is allowed to proceed past placement.
2. Leave the order/purchase in a **pending-payment-approval** state that blocks any further processing (fulfillment, shipping, gift-card code delivery, ad activation, etc.) until an **admin explicitly approves** the payment from the admin panel.
3. Give admins a clear approve/reject action per payable type, mirroring the existing bank-transfer confirmation pattern.

### What already exists (do not rebuild — extend/generalize it)

A bank-transfer offline-payment flow is already implemented end-to-end for checkout orders:
- Gateway classification: `payment_gateways.type` enum = `redirect|direct|offline|internal`. Only `bank_transfer` currently uses `offline`; `cod`/`wallet` are `internal`; `thawani`/`paytabs` are `redirect`. **`type='offline'` is the correct generic signal for "not online, not COD" — use it instead of hardcoded `=== 'bank_transfer'` string checks wherever you find them.**
- `backend/app/Services/Payments/PaymentGatewayFactory.php` — gateway code → class map, `redirectCodes()`, `internalCodes()`.
- `backend/app/Services/Payments/BankTransferGateway.php` — offline gateway impl (no external API, admin must confirm manually).
- Checkout order creation: `backend/app/Http/Controllers/Customer/CheckoutController.php::placeOrder()` (~line 516, order created ~896, branch logic ~1391-1456). Order created with `status: placed`, `payment_status: pending`.
- Auto-expiry exemption: `backend/app/Jobs/ExpirePendingPaymentsJob.php` exempts `cod`, `bank_transfer`, `wallet` from auto-cancellation — this is the "hold until manual confirmation" pattern; **currently hardcoded by string, must become type-driven**.
- Proof upload (customer): route `POST {order_number}/bank-transfer-proof` in `backend/routes/api_customer_v1.php:460` → `Customer/OrderController.php:62-80::uploadBankTransferProof` (currently guarded by `payment_method === 'bank_transfer'`) → `backend/app/Services/Customer/BankTransferProofService.php` → `backend/app/Http/Requests/Customer/Order/UploadBankTransferProofRequest.php` (`file: required|file|mimes:jpg,jpeg,png,pdf|max:10240`). Storage: `$file->store("orders/{id}/payment-proofs", 'public')`. Columns `proof_file_path`/`proof_uploaded_at` on `payment_transactions` (migration `2026_09_17_090000_add_proof_fields_to_payment_transactions.php`). **No `note` column exists yet.**
- Frontend upload UI: `frontend/src/features/noon/checkout/success/bank-transfer-card.tsx` + `.../success/helpers/use-bank-transfer-proof.ts`, called from the **post-order success page** (i.e. today the upload happens after order placement, not gating it — this needs tightening per the "before submit or continue process" requirement).
- Admin approval (the precedent to mirror everywhere else): `backend/app/Http/Controllers/Admin/TransactionController.php::confirmBankTransfer()` (~line 195-225), route `POST /transactions/{transaction}/confirm-bank-transfer` in `backend/routes/admin.php:1048`, view `backend/resources/views/admin/transactions/show.blade.php` (~154-186) shows the proof file + "Confirm Bank Transfer" button. On confirm: transaction → `succeeded`, order `payment_status: captured` + `status: confirmed`, ledger entry via `LedgerService::postOrderCapture`, coupon usage consumed. **Currently hardcoded to `gateway === 'bank_transfer'`, must become type-driven (`confirmOfflinePayment`).**

### Known gaps this plan must close
- **Gift cards** (`backend/app/Services/GiftCardPurchaseService.php`, `CustomerGiftCardStoreController::purchase`): payment method is selected (`country_payment_gateway_id`) but **no gateway is ever invoked**, and `SendGiftCardDeliveryJob` is dispatched unconditionally regardless of `payment_status`. No proof-upload/approval flow exists for gift card orders at all.
- **Paid ads** (`backend/app/Enums/PaidAdPaymentMethod.php` has an `Offline` case; `backend/app/Services/Ads/AdBookingService.php` ~line 401): offline settlement concept exists but has no proof-upload + admin-approval UI, and ad booking may activate before payment is confirmed.
- Proof upload happens **after** order placement (success page), not as a gate **before** the order can proceed — needs a required note field and needs to visibly block "processing" (see below) until approved.
- `payment_transactions` has no `note`/`proof_note` column.
- A few checks hardcode `bank_transfer` by string instead of checking `payment_gateways.type === 'offline'` (see file list in each task below).

### Ground rules for every sub-agent
- Laravel conventions already used in this repo: FormRequest validation classes, Service classes for business logic, thin controllers, migrations for schema changes, Enums in `app/Enums`.
- File upload convention: `mimes:jpg,jpeg,png,pdf|max:10240`, stored on the `public` disk under a `{entity}/{id}/payment-proofs` path.
- Do not touch `StripeGateway` (unwired, out of scope) or the legacy duplicate `app/Contracts/PaymentGatewayInterface.php` (dead code, out of scope) — leave both alone unless a task below says otherwise.
- Every sub-agent must run relevant tests/linters it can find for the files it touched, then **create a git commit** when done. The commit message must:
  - Summarize what changed and why (1-2 sentences, imperative mood).
  - Include a `Task:` line quoting the sub-agent prompt name from this file (e.g. `Task: 02-generalize-offline-gateway-detection`).
  - End with:
    ```
    Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
    ```
- Do not `git push`. Local commit only.
- Work only within `/var/www/marketplace`. If a task's assumptions turn out wrong after reading the actual current code, adapt sensibly and note the deviation in the commit message rather than blocking.

---

## Sub-agent tasks (run in this order — later tasks depend on earlier schema/service changes)

### Task 01 — Add `note` column + generalize offline detection in backend core

Prompt to run as a sub-agent:

> You're working in the Laravel backend at `/var/www/marketplace/backend`. This is part of a larger effort to require admin approval + invoice upload for any *offline* (non-COD, non-online-gateway) payment method, generalized beyond the single `bank_transfer` case that exists today.
>
> Do the following:
> 1. Create a migration adding a nullable `note` (text) column to `payment_transactions`, next to the existing `proof_file_path`/`proof_uploaded_at` columns added in `database/migrations/2026_09_17_090000_add_proof_fields_to_payment_transactions.php`. Follow that migration's naming/style. Update `database/schema/mysql-schema.sql` accordingly if this repo expects the dump kept in sync (check how the existing proof-fields migration did it).
> 2. In `app/Services/Payments/PaymentGatewayFactory.php`, add a public helper `isOffline(string $gatewayCode): bool` that looks up the gateway's `type` from the `payment_gateways` table (or existing cached config, follow existing patterns like `redirectCodes()`/`internalCodes()`) and returns true if `type === 'offline'`. Keep `redirectCodes()`/`internalCodes()` working as before.
> 3. In `app/Jobs/ExpirePendingPaymentsJob.php`, replace the hardcoded string list exemption (`cod`, `bank_transfer`, `wallet`) with a check that exempts `cod`, `wallet`, and anything for which `PaymentGatewayFactory::isOffline()` is true.
> 4. Read `app/Http/Controllers/Customer/OrderController.php` lines ~62-80 (`uploadBankTransferProof`) and `app/Services/Customer/BankTransferProofService.php`. Generalize: rename the guard from `payment_method === 'bank_transfer'` to check `PaymentGatewayFactory::isOffline($transaction->gateway)` (or equivalent using the order's associated `PaymentTransaction`). Keep the route/method names as-is for backward compatibility (frontend already calls them) but make the underlying logic gateway-type-driven, not string-driven. Add support for an optional `note` field in `UploadBankTransferProofRequest` (`note: nullable|string|max:2000`) and persist it to the new `note` column via the service.
> 5. Run `php artisan migrate` (or the repo's normal way of validating migrations, check `composer.json`/`CLAUDE.md` for testing scripts) to confirm the migration is valid, and run any existing PHPUnit/Pest tests that cover `BankTransferProofService` or `ExpirePendingPaymentsJob` if they exist (grep `tests/` first).
> 6. Commit with a message summarizing the change, include `Task: 01-note-column-and-offline-detection`, and end with the required `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>` line.

### Task 02 — Generalize admin approval endpoint (`confirmBankTransfer` → offline payment confirmation)

Prompt to run as a sub-agent (run after Task 01 is committed — read its diff first for the new `PaymentGatewayFactory::isOffline()` helper and `note` column):

> You're working in the Laravel backend at `/var/www/marketplace/backend`. Task 01 (already committed — read its diff with `git log` / `git show` to see exactly what it added) introduced `PaymentGatewayFactory::isOffline()` and a `note` column on `payment_transactions`. Now generalize the admin approval action.
>
> 1. Read `app/Http/Controllers/Admin/TransactionController.php::confirmBankTransfer()` (~line 195-225) fully, and the route in `routes/admin.php:1048`.
> 2. Change the guard from `gateway === 'bank_transfer'` to `PaymentGatewayFactory::isOffline($transaction->gateway)`. Keep the existing route/method name `confirmBankTransfer` for backward compatibility with the existing frontend button (`js-confirm-bank-transfer` in `resources/views/admin/transactions/show.blade.php`), but the guard logic must now be type-driven so any future offline gateway (not just bank transfer) works through this same action without code changes.
> 3. Add a **reject** counterpart: `rejectOfflinePayment(Transaction $transaction, Request $request)` — validates an optional `reason` string, sets the transaction to `failed` (check `PaymentTransactionStatus` enum for the right value), leaves the order `payment_status` as-is or sets to `failed` per what makes sense given `OrderPaymentStatus` enum, and does NOT touch the ledger. Add a route `POST /transactions/{transaction}/reject-offline-payment` next to the confirm route in `routes/admin.php`, gated by the same `admin.permission:transactions.edit` middleware as the confirm route.
> 4. Update `resources/views/admin/transactions/show.blade.php` (~154-186) to show the `note` field (if present) alongside the proof file/image link, and add a "Reject" button next to "Confirm Bank Transfer" wired to the new reject route, following the same JS pattern (`js-confirm-bank-transfer`) already used — e.g. `js-reject-offline-payment`.
> 5. Verify `LedgerService::postOrderCapture` and coupon-consumption logic in the existing confirm flow are untouched by your refactor (regression-test by reading the diff before committing).
> 6. Commit: summarize, `Task: 02-generalize-admin-approval-endpoint`, end with the required Co-Authored-By line.

### Task 03 — Gate order fulfillment/processing on payment approval for offline methods

Prompt to run as a sub-agent:

> You're working in the Laravel backend at `/var/www/marketplace/backend`. Part of a larger effort: for offline payment methods (gateway `type === 'offline'`, e.g. bank transfer), an order must NOT be picked up for vendor fulfillment/processing/shipping until an admin has approved the payment (i.e. `payment_status` has moved from `pending` to `captured` via `Admin/TransactionController::confirmBankTransfer`, generalized in a prior task — check `git log` for a commit mentioning `PaymentGatewayFactory::isOffline`).
>
> 1. Find where vendors/admin can start "processing" or accept/ship an order — search `app/Http/Controllers/Vendor/OrderController.php` and `app/Http/Controllers/Admin/OrderController.php` for status-transition actions (e.g. accept order, mark ready, mark shipped). Also check `app/Services/Order/*` for a central order-status-transition service if one exists — prefer adding the guard there over duplicating it in each controller.
> 2. Add a guard: if the order's payment gateway is offline (`PaymentGatewayFactory::isOffline()`) and `order.payment_status !== 'captured'` (still `pending`), reject any transition that would move the order out of `placed`/into fulfillment, with a clear error message like "Order payment is pending admin approval." COD orders must NOT be affected by this guard (they already have their own capture-on-delivery flow via `CaptureCodOnDelivery`) — make sure your condition excludes `cod`.
> 3. Confirm `SubOrderDelivered`/`CaptureCodOnDelivery` (`app/Listeners/CaptureCodOnDelivery.php`) and the wallet/online-gateway paths are unaffected — read them before editing to be sure your new guard only fires for offline-type gateways.
> 4. If existing feature/integration tests cover order status transitions (grep `tests/` for `OrderController` or order status), add a test case verifying an offline-payment order can't transition until captured, and that COD/online orders are unaffected.
> 5. Commit: summarize, `Task: 03-gate-fulfillment-on-payment-approval`, end with the required Co-Authored-By line.

### Task 04 — Frontend: require invoice upload + optional note at checkout before order can be placed with an offline method

Prompt to run as a sub-agent:

> You're working in the Next.js frontend at `/var/www/marketplace/frontend`. Today, when a customer selects `bank_transfer` at checkout, the order is placed first and the proof-of-payment upload happens afterward on the success page (`frontend/src/features/noon/checkout/success/bank-transfer-card.tsx`). The requirement is that for any offline (non-COD, non-online) payment method, the invoice/proof document must be **required** and a note **optional**, and the customer should not be able to complete checkout without providing the file.
>
> 1. Read `frontend/src/features/noon/checkout/payment-methods-card.tsx` (payment method selection UI) and `frontend/src/features/noon/checkout/types/{checkout.type.ts,place-order.type.ts}` to see how payment methods are typed/flagged (look for a way to know a method is "offline" — check if the API response from `GET /checkout/payment-options`, in `backend/app/Http/Controllers/Api/Customer/CheckoutController.php::paymentOptions()` line ~60, already exposes the gateway `type`; if not, coordinate by adding `type` to that response — note in your commit if you had to touch the backend).
> 2. When an offline-type method is selected, render a required file input (accept `.jpg,.jpeg,.png,.pdf`, matching backend's `mimes:jpg,jpeg,png,pdf|max:10240`) and an optional note textarea directly in the checkout payment step (not the success page), and disable the "Place Order" submit action until a file is attached.
> 3. Update the place-order submission flow: after the order is created (existing `placeOrder` API call), immediately call the existing bank-transfer-proof upload endpoint (`frontend/src/features/noon/checkout/api/post.ts`'s `uploadBankTransferProofService`, backed by `POST {order_number}/bank-transfer-proof`) with the collected file + note, as part of the same submit action (sequential calls, show a combined loading state), instead of requiring a separate step on the success page. Extend the upload service call to send the new `note` field (added to the backend in a prior task — check `git log` for a migration adding a `note` column and a request-validation change in `UploadBankTransferProofRequest`).
> 4. Keep `bank-transfer-card.tsx` on the success page as a fallback/retry path (e.g. if the upload call failed after order placement) rather than deleting it, but it should no longer be the primary intended path.
> 5. Manually verify in a dev server run (`npm run dev` or equivalent per this repo's README) that: selecting bank transfer requires a file before submit is enabled, order placement + proof upload both succeed, and selecting an online gateway or COD is unaffected.
> 6. Commit: summarize, `Task: 04-frontend-checkout-require-invoice-upload`, end with the required Co-Authored-By line.

### Task 05 — Gift cards: wire payment gateway + block code delivery until offline payment approved

Prompt to run as a sub-agent:

> You're working in the Laravel backend (and its Next.js frontend counterpart) for gift card purchases. Today, `backend/app/Services/GiftCardPurchaseService.php::purchase()` (lines ~41-140) creates an order (`status: placed`, `payment_status: pending`) and sets `payment_method` to the raw gateway code, but **never actually invokes any payment gateway**, and `CustomerGiftCardStoreController::purchase()` unconditionally dispatches `SendGiftCardDeliveryJob` right after, regardless of payment status. This is a real gap: a gift card could be delivered before payment is even attempted or approved.
>
> 1. Read `backend/app/Services/GiftCardPurchaseService.php`, `backend/app/Http/Controllers/Api/Customer/CustomerGiftCardStoreController.php::purchase()` (line ~66), `backend/app/Http/Requests/Api/Customer/PurchaseGiftCardRequest.php`, and — for comparison — how `Customer/CheckoutController.php` invokes `PaymentService::initiatePayment()` / `PaymentGatewayFactory` for regular orders (~line 1391-1456).
> 2. Update `GiftCardPurchaseService::purchase()` to actually call the payment gateway via the same `PaymentService`/`PaymentGatewayFactory` used by checkout, running the `payment_method` through `PaymentMethodMapper` (it currently isn't) so the order's `payment_method` enum is correct.
> 3. Update `CustomerGiftCardStoreController::purchase()` (or move this logic into the service/a listener, follow whatever pattern checkout uses) so that `SendGiftCardDeliveryJob` is only dispatched immediately for online/instant-capture methods (wallet, successful online gateway). For offline-type gateways, the job must NOT fire yet — the gift card purchase order should sit pending, same as a checkout order, until admin approval.
> 4. Reuse the existing proof-upload route (`POST {order_number}/bank-transfer-proof`, generalized in an earlier task — check `git log` for the `isOffline()`-driven guard) — confirm it works for gift-card orders (it's keyed by `order_number` generically) and add a test if none exists.
> 5. Hook `SendGiftCardDeliveryJob` dispatch into whatever event/listener fires when an admin approves an offline payment (mirror `CaptureCodOnDelivery`'s listener pattern, or dispatch it directly from `Admin/TransactionController`'s offline-confirm action from a prior task if the order is a gift-card order — check `order.orderable_type` or however gift card orders are distinguished from regular orders in this schema).
> 6. Frontend: read `frontend/src/features/noon/gift-cards/view/components/payment-method-selector.tsx` and `frontend/src/features/noon/gift-cards/api/gift-cards.actions.ts`. Apply the same UX as Task 04 (required invoice file + optional note, submitted at purchase time, disabled submit until file attached) when an offline gateway is selected for a gift card purchase.
> 7. Commit: summarize, `Task: 05-gift-card-offline-payment-approval`, end with the required Co-Authored-By line.

### Task 06 — Paid ads: offline settlement requires invoice upload + admin approval before activation

Prompt to run as a sub-agent:

> You're working in the Laravel backend at `/var/www/marketplace/backend`. `app/Enums/PaidAdPaymentMethod.php` already has a `Wallet | PayoutDeduction | Offline` set of cases, and `app/Services/Ads/AdBookingService.php` (~line 401) has an `Offline` settlement path, but there is currently no proof-upload or admin-approval gate before an ad using offline settlement goes live.
>
> 1. Read `app/Services/Ads/AdBookingService.php` and any related `AdBillingService`/ad-booking controllers fully, to understand how a booking currently transitions to "active"/"live" and where payment/settlement status is tracked (find the model/table involved, e.g. `AdBooking`, and its status enum).
> 2. Determine whether ad bookings paid via `Offline` settlement reuse the same `payment_transactions` table/gateway machinery as orders, or have their own separate tracking. If separate, add an equivalent minimal proof-upload requirement (file required, note optional) using the same validation convention (`mimes:jpg,jpeg,png,pdf|max:10240`) and storage convention (`{entity}/{id}/payment-proofs` on the `public` disk) established for orders. If they DO reuse `payment_transactions`/`PaymentGatewayFactory`, wire them through the same `isOffline()` check and admin-approval endpoint from Task 02 instead of building a parallel system.
> 3. Ensure an ad booking with `Offline` settlement cannot transition to "active"/start serving impressions until an admin has approved its payment proof, mirroring the order-fulfillment gate from Task 03.
> 4. Add/locate an admin UI action to approve or reject the ad's offline payment proof, mirroring `admin/transactions/show.blade.php`'s confirm/reject buttons from Task 02 — reuse that same admin page/controller pattern if ad payments share the `payment_transactions` table, or add an equivalent minimal view under the ads admin section if they don't.
> 5. Commit: summarize, `Task: 06-paid-ads-offline-payment-approval`, end with the required Co-Authored-By line.

### Task 07 — Codebase-wide sweep for any other missed payable/payment-method-selection page

Prompt to run as a sub-agent:

> You're doing a verification sweep across the whole repo at `/var/www/marketplace` (backend Laravel + frontend Next.js + `carrier_app`/`delivery_app`/`partner_app`/`travel_app` if they exist and are relevant). Tasks 01-06 (already committed — read `git log --oneline -20` and the diffs of commits tagged `Task: 0X-...` for full context) covered checkout orders, gift cards, and paid ads. Your job is to find anything they missed.
>
> 1. Grep the whole repo (backend + frontend + any other app directories) for: `payment_method`, `country_payment_gateway`, `payment_gateway`, `PaymentGatewayFactory`, `gateway_code`, "bank transfer", "pay later", "deposit", "top up", "topup", "recharge", "wholesale", "B2B", "subscription", "invoice payment" — to find any payable flow not already covered (wallet top-up, subscriptions, marketer/vendor payouts requiring a payment-in, travel/carrier app bookings if they take customer payment, etc.).
> 2. For each match, determine: is it a real "customer selects a payment method and pays" flow? If yes, does it already go through `PaymentGatewayFactory`/`PaymentService` (in which case Task 01-03's generalized `isOffline()` guard + admin-approval endpoint already cover it, and you just need to verify + add proof-upload UI following the Task 04 pattern) or is it a fully separate/bespoke payment path (in which case it needs its own proof-upload + admin-approval wiring, mirroring Task 02/06's approach)?
> 3. Explicitly verify COD paths are untouched everywhere (COD should never require an invoice upload or admin approval — only require it for non-COD, non-online/redirect/direct gateways).
> 4. Produce a short markdown checklist of what you found and fixed (or confirmed already covered) as `/var/www/marketplace/OFFLINE_PAYMENT_AUDIT_RESULTS.md`, then apply any missing fixes directly following the established patterns (don't just report — fix what you find, using the same conventions as prior tasks).
> 5. Commit: summarize what was found/fixed, `Task: 07-codebase-wide-sweep`, end with the required Co-Authored-By line.

### Task 08 — End-to-end regression pass

Prompt to run as a sub-agent (run last, after Tasks 01-07 are all committed):

> You're doing final verification at `/var/www/marketplace` after a series of commits (read `git log --oneline -30` for the `Task: 0X-...` commits from this effort) that generalized offline-payment handling (bank transfer + any newly-added offline methods) to require invoice-upload + admin approval before an order/gift-card/ad proceeds, across checkout, gift cards, and paid ads.
>
> 1. Run the backend's full test suite (`php artisan test` or repo's configured command — check `composer.json`) and the frontend's typecheck/build (`npm run build`/`npm run typecheck`, check `package.json`) and fix any regressions caused by the prior tasks' changes.
> 2. Manually trace through (by reading code, not just running tests) these three end-to-end scenarios and confirm each holds:
>    - Checkout with bank transfer: order stays un-fulfillable until admin approves; COD and online-gateway orders are unaffected.
>    - Gift card purchase with an offline gateway: gift card code is not delivered until admin approves.
>    - COD checkout: entirely unaffected by any of this (no upload required, no approval gate, captures on delivery as before).
> 3. Confirm the admin transactions list/detail views correctly surface pending offline payments needing approval, with proof file + note visible, and that reject leaves the order in a sane, recoverable state (not stuck, not silently lost).
> 4. Fix anything broken. If everything checks out with no changes needed, still commit a short note confirming the regression pass (empty commits are not allowed by repo policy, so make sure there's at least a trivial documented change — e.g. a completion note appended to `OFFLINE_PAYMENT_AUDIT_RESULTS.md` — if no code fix was needed).
> 5. Commit: summarize, `Task: 08-end-to-end-regression-pass`, end with the required Co-Authored-By line.

---

## Notes for whoever runs these

- Run Tasks 01 → 02 → 03 → 04 sequentially (each depends on the prior's schema/service changes). Tasks 05 and 06 can run in parallel with each other once 01-03 are committed, since they touch different subsystems (gift cards vs. ads) but both depend on `PaymentGatewayFactory::isOffline()` and the generalized admin-approval endpoint. Task 07 must run after 01-06. Task 08 must run last.
- Each prompt above is self-contained (includes file paths, current behavior, and what to change) so it can be pasted directly as a sub-agent's task with no additional context needed.
- If a task's sub-agent finds that reality diverges from this plan's assumptions (e.g. a file has moved, a column already exists), it should adapt and note the deviation in its commit message rather than stall.
