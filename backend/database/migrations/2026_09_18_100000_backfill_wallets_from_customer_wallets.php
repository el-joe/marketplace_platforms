<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * WALLET_MERGE_PLAN.md Phase 1: backfill data from the legacy
 * `customer_wallets` table into the polymorphic `wallets` table
 * (owner_type='customer') that the rest of the platform already uses for
 * vendor/marketer/delivery-agent balances.
 *
 * This migration is purely additive:
 *   - `customer_wallets` is left completely untouched (no writes, no drop).
 *     It stays the read/write system of record until Phase 2+ repoint the
 *     services, and is only dropped in a later cleanup wave.
 *   - For every `customer_wallets` row we ensure a matching `wallets` row
 *     exists (owner_type='customer', owner_id=customer_id, currency=
 *     currency_code) and credit it with the customer_wallets balance. If a
 *     `wallets` row already existed for that customer+currency (e.g. it was
 *     already touched by another wallet feature), we ADD to its balance
 *     rather than overwrite it, since overwriting could silently destroy a
 *     balance that has nothing to do with customer_wallets.
 *   - `wallet_transactions.wallet_id` is backfilled for any row that has a
 *     `customer_id` but no `wallet_id`, by looking up that customer's
 *     wallet (matching currency_code when the transaction row has one).
 *
 * Idempotency: re-running this migration (e.g. after a manual re-run, not
 * just Laravel's own "already ran" bookkeeping) must not double-credit
 * balances. We can't mark `customer_wallets` rows as "already merged"
 * because the plan forbids modifying that table, so instead each backfill
 * credit writes a marker `wallet_transactions` row
 * (reference_type='customer_wallet_backfill', reference_id=<customer_wallets.id>).
 * Before crediting a wallet we check whether that marker already exists for
 * the row and skip it if so. This also gives the merge a proper audit trail
 * in the shared ledger.
 */
return new class extends Migration
{
    private const MARKER_REFERENCE_TYPE = 'customer_wallet_backfill';

    public function up(): void
    {
        DB::table('customer_wallets')
            ->orderBy('id')
            ->select('id', 'customer_id', 'balance', 'currency_code')
            ->chunkById(500, function ($customerWallets) {
                foreach ($customerWallets as $customerWallet) {
                    DB::transaction(function () use ($customerWallet) {
                        $this->backfillWallet($customerWallet);
                    });
                }
            });

        DB::table('wallet_transactions')
            ->whereNotNull('customer_id')
            ->whereNull('wallet_id')
            ->orderBy('id')
            ->select('id', 'customer_id', 'currency_code')
            ->chunkById(500, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $this->backfillTransactionWalletId($transaction);
                }
            });
    }

    private function backfillWallet(object $customerWallet): void
    {
        // Idempotency guard: skip if we already merged this customer_wallets
        // row in a previous run of this migration.
        $alreadyMerged = DB::table('wallet_transactions')
            ->where('reference_type', self::MARKER_REFERENCE_TYPE)
            ->where('reference_id', $customerWallet->id)
            ->exists();

        if ($alreadyMerged) {
            return;
        }

        $wallet = DB::table('wallets')
            ->where('owner_type', 'customer')
            ->where('owner_id', $customerWallet->customer_id)
            ->where('currency', $customerWallet->currency_code)
            ->lockForUpdate()
            ->first();

        $now = now();

        if ($wallet) {
            $newBalance = $wallet->balance + $customerWallet->balance;

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update([
                    'balance' => $newBalance,
                    'updated_at' => $now,
                ]);

            $walletId = $wallet->id;
        } else {
            $walletId = (string) Str::uuid();
            $newBalance = $customerWallet->balance;

            DB::table('wallets')->insert([
                'id' => $walletId,
                'owner_type' => 'customer',
                'owner_id' => $customerWallet->customer_id,
                'balance' => $newBalance,
                'pending_balance' => 0,
                'currency' => $customerWallet->currency_code,
                'is_frozen' => false,
                'frozen_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Marker + audit trail row so a re-run of this migration (or a
        // manual replay of its logic) knows this customer_wallets row was
        // already folded into the wallet balance above.
        DB::table('wallet_transactions')->insert([
            'id' => (string) Str::uuid(),
            'wallet_id' => $walletId,
            'customer_id' => $customerWallet->customer_id,
            'type' => 'wallet_merge_backfill',
            'direction' => 'credit',
            'amount' => $customerWallet->balance,
            'balance_after' => $newBalance,
            'currency_code' => $customerWallet->currency_code,
            'reference_type' => self::MARKER_REFERENCE_TYPE,
            'reference_id' => $customerWallet->id,
            'source_type' => 'migration',
            'source_id' => null,
            'description' => 'Backfilled from customer_wallets during wallet systems merge (Phase 1)',
            'note' => null,
            'performed_by_admin_id' => null,
            'created_at' => $now,
        ]);
    }

    private function backfillTransactionWalletId(object $transaction): void
    {
        $query = DB::table('wallets')
            ->where('owner_type', 'customer')
            ->where('owner_id', $transaction->customer_id);

        if ($transaction->currency_code) {
            $query->where('currency', $transaction->currency_code);
        }

        $wallet = $query->first();

        if (! $wallet) {
            // No matching wallet (e.g. currency mismatch, or the customer
            // never had a customer_wallets row) — nothing safe to backfill.
            return;
        }

        DB::table('wallet_transactions')
            ->where('id', $transaction->id)
            ->whereNull('wallet_id')
            ->update(['wallet_id' => $wallet->id]);
    }

    public function down(): void
    {
        // This is intentionally a no-op.
        //
        // The balance additions made in up() are lossy to reverse: once a
        // customer_wallets balance has been added into a `wallets` row, we
        // cannot tell which portion of that wallet's current balance came
        // from this backfill vs. from other activity that may have happened
        // against the same wallet since (e.g. Phase 2+ services writing to
        // it, or a pre-existing balance we intentionally added to instead
        // of overwriting). Subtracting the original customer_wallets amount
        // back out could take the wallet negative or undo unrelated credits.
        //
        // `customer_wallets` itself was never modified by up(), so it is
        // already in its original state and needs no reversal. If a true
        // rollback of the wallet balances is ever needed, it must be done
        // as a deliberate, reviewed data-fix using the
        // `customer_wallet_backfill` marker rows in `wallet_transactions`
        // (inserted by up()) to identify exactly what was added, not via
        // this down() method.
    }
};
