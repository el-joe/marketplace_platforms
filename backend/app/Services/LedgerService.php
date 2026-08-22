<?php

namespace App\Services;

use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    /**
     * Record a set of balanced double-entry ledger entries.
     *
     * All entries in a group must satisfy: SUM(debit) === SUM(credit).
     * Throws if the entries do not balance.
     *
     * @param  string  $groupId  UUID linking all entries in this transaction
     * @param  array<int, array{
     *   account_type: string,
     *   account_holder_type: string|null,
     *   account_holder_id: string|null,
     *   debit: int,
     *   credit: int,
     *   currency: string,
     *   reference_type: string,
     *   reference_id: string,
     *   description: string
     * }>  $entries
     *
     * @throws \InvalidArgumentException if the entries do not balance
     */
    public function record(string $groupId, array $entries): void
    {
        $totalDebit = array_sum(array_column($entries, 'debit'));
        $totalCredit = array_sum(array_column($entries, 'credit'));

        if ($totalDebit !== $totalCredit) {
            throw new \InvalidArgumentException(
                "Ledger entries must balance. Debit total ({$totalDebit}) ≠ credit total ({$totalCredit})."
            );
        }

        DB::transaction(function () use ($groupId, $entries) {
            foreach ($entries as $entry) {
                LedgerEntry::create([
                    'transaction_group_id' => $groupId,
                    'account_type' => $entry['account_type'],
                    'account_holder_type' => $entry['account_holder_type'] ?? null,
                    'account_holder_id' => $entry['account_holder_id'] ?? null,
                    'debit' => $entry['debit'] ?? 0,
                    'credit' => $entry['credit'] ?? 0,
                    'currency' => $entry['currency'],
                    'reference_type' => $entry['reference_type'],
                    'reference_id' => $entry['reference_id'],
                    'description' => $entry['description'],
                ]);
            }
        });
    }

    /**
     * Generate a new transaction group UUID.
     */
    public function newGroupId(): string
    {
        return (string) Str::uuid();
    }
}
