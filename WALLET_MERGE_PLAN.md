# Wallet Systems Merge Plan

## Background

Two parallel, non-communicating customer wallet systems exist:

- **`Wallet`** (`wallets` table, polymorphic `owner_type`/`owner_id`, multi-currency) — used today for vendor/marketer/delivery-agent balances + bank withdrawals. Customer-facing "Available balance" UI reads this, but nothing credits it for customers (checkout/gift cards/refunds never touch it).
- **`CustomerWallet`** (`customer_wallets` table, one row per customer, single-currency) — credited by gift card redemption and vouchers, debited/credited by checkout and refunds. Has no bank-withdrawal capability.

Symptom: a customer redeems a gift card, `CustomerWallet.balance` goes up correctly, but "Available balance" (bound to `Wallet`) still shows 0.

**Decision (confirmed with user):** merge into `Wallet` (the polymorphic model already used for vendors/marketers/delivery agents — customers become `owner_type = 'customer'` there too). `CustomerWallet` is retired. `wallet_transactions` already has both `wallet_id` and `customer_id` columns and is the shared ledger — it becomes the unification anchor.

**Scope of this wave:** backend only (migration, models, services, controllers, routes). Admin panel (Blade) and frontend (Next.js) are **out of scope** and will be planned as a second wave after backend is reviewed. Do not touch `backend/resources/views/admin/**` or anything under `frontend/**` in this wave.

**Constraint on all agents:** this touches real money. No silent data loss — the migration must be additive/reversible (backfill, don't drop `customer_wallets` yet; leave it as a deprecated/read-only table this wave, actual DROP happens in a later cleanup wave after verification). Every agent must run the existing test suite (`backend/tests/Feature/Checkout/PaymentMethodMatrixTest.php`, `GiftCardPurchaseAndRedemptionTest.php`, `RefundServiceTest.php`, `OrderCancellationServiceTest.php`) after their change and must not leave the suite red. Each agent commits its own change separately with a clear message — do not squash phases together.

## Phases (sequential — each depends on the previous being committed)

### Phase 1 — Migration & data backfill
Add a migration that, for every `customer_wallets` row, creates/updates the matching `Wallet` row (`owner_type='customer'`, `owner_id=customer_id`, `currency=currency_code`, `balance=balance`), and backfills `wallet_transactions.wallet_id` for rows where `customer_id` is set but `wallet_id` is null (join via the newly created customer Wallet row's id, matching currency). Idempotent, wrapped in a DB transaction, chunked for large tables. Do not delete `customer_wallets` or its data.

### Phase 2 — Service layer consolidation
Point `GiftCardService::redeemToWallet`, `VoucherService::redeem`, `CheckoutWalletService` (`applyWalletToOrder`/`refundToWallet`) at `WalletService`/`Wallet` (owner_type=customer) instead of `CustomerWallet`. Preserve currency-matching and insufficient-balance behavior. Update `wallet_transactions` writes to always set `wallet_id` (stop writing bare `customer_id`-only rows going forward — `customer_id` can stay populated for backward compat with old rows/queries, but is no longer load-bearing).

### Phase 3 — Controller & route consolidation
Collapse the three customer-facing wallet route groups/controllers (`Customer\WalletController`, `Api\Customer\WalletController`, `CustomerWalletController`) into one canonical controller backed by `Wallet`, covering: balance, transactions, bank withdrawal, gift-card redemption, voucher redemption, gift-card-balance lookup. Update `CartController::toggleWallet`/`resolveWalletInfo` and both `CheckoutController`s (`Customer\` and `Api\Customer\`) to read/write `Wallet` instead of `CustomerWallet`. Keep old route paths working (thin aliases to the new controller) so the out-of-scope frontend wave isn't broken mid-migration — do not change route URLs yet.

### Phase 4 — Backend test alignment
Update `backend/tests/Support/MarketplaceScenario.php` and the four test files listed above to construct/assert against `Wallet` (owner_type=customer) instead of `CustomerWallet`, without changing what behavior they assert. Run the full backend test suite and fix any regressions caused by phases 1-3. This agent runs last and gates the wave — if it finds a real bug introduced by phases 1-3, fix it in this same commit and note it in the commit message.

## Agent execution

Run Phase 1 → commit → Phase 2 → commit → Phase 3 → commit → Phase 4 → commit, strictly in order (each phase's agent must `git pull`/inspect the prior commit before starting, since they run sequentially in the same working tree). Do not parallelize phases 1-4; they touch overlapping files and have a strict dependency order. After Phase 4 commits, stop and report back for review before any admin-panel or frontend wave is planned.
