<?php

namespace App\Services\Marketer;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Marketer;
use App\Models\MarketerPromotionFine;
use App\Services\WalletService;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Month close for the promotion quota: a marketer who ends the month below the minimum of his category
 * gets ONE fine row (unique marketer/category/month, so re-running the command is harmless).
 * Fines are debited from the marketer wallet as soon as it can cover them and, if it cannot,
 * stay pending and are collected right before the marketer's next withdrawal (collect()).
 */
class PromotionPenaltyService
{
    public function __construct(
        private readonly PromotionQuotaService $quota,
        private readonly WalletService $wallets,
    ) {}

    /** @return int fines created by this run */
    public function closeMonth(CarbonInterface $month): int
    {
        $created = 0;
        $period = $month->format('Y-m');

        Marketer::query()
            ->whereHas('marketerProfile', fn ($q) => $q->whereNotNull('promotion_category'))
            ->with(['marketerProfile', 'country'])
            ->chunkById(200, function ($marketers) use ($month, $period, &$created) {
                foreach ($marketers as $marketer) {
                    $progress = $this->quota->progressFor($marketer, $month);

                    if (! $progress['behind']) {
                        continue;
                    }

                    $amount = $progress['penalty'];

                    try {
                        $fine = MarketerPromotionFine::firstOrCreate(
                            ['marketer_id' => $marketer->id, 'promotion_category' => $progress['category']->value, 'period' => $period],
                            [
                                'target' => $progress['target'],
                                'done' => $progress['done'],
                                'amount' => $amount,
                                'currency' => $marketer->country?->currency_code,
                                // No penalty configured: keep the record for admin follow-up but there is nothing to collect.
                                'status' => $amount > 0 ? MarketerPromotionFine::PENDING : MarketerPromotionFine::WAIVED,
                                'note' => $amount > 0 ? null : 'no_penalty_configured',
                            ]
                        );
                    } catch (UniqueConstraintViolationException) {
                        continue;
                    }

                    if ($fine->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        // Pick up fines left pending by earlier months (wallet was short then).
        MarketerPromotionFine::where('status', MarketerPromotionFine::PENDING)
            ->distinct()->pluck('marketer_id')
            ->each(fn ($id) => ($m = Marketer::with('country')->find($id)) ? $this->collect($m) : null);

        return $created;
    }

    /**
     * Debit every pending fine the wallet can cover, oldest first. Idempotent and race safe:
     * the fine row is locked and re-checked inside the transaction that debits the wallet.
     *
     * @return int number of fines deducted
     */
    public function collect(Marketer $marketer): int
    {
        $marketer->loadMissing('country');
        $deducted = 0;

        MarketerPromotionFine::where('marketer_id', $marketer->id)
            ->where('status', MarketerPromotionFine::PENDING)
            ->where('amount', '>', 0)
            ->orderBy('period')
            ->pluck('id')
            ->each(function (string $id) use ($marketer, &$deducted) {
                try {
                    DB::transaction(function () use ($id, $marketer, &$deducted) {
                        $fine = MarketerPromotionFine::whereKey($id)->lockForUpdate()->first();

                        if (! $fine || $fine->status !== MarketerPromotionFine::PENDING) {
                            return;
                        }

                        $currency = $fine->currency ?: $marketer->country?->currency_code;
                        if (! $currency) {
                            return;
                        }

                        $wallet = $this->wallets->getOrCreateWallet('marketer', $marketer->id, $currency);
                        $tx = $this->wallets->debit(
                            $wallet,
                            $fine->amount,
                            'promotion_fine',
                            $fine->id,
                            "Promotion minimum fine {$fine->period} (category {$fine->promotion_category->value}: {$fine->done}/{$fine->target})",
                        );

                        $fine->update([
                            'status' => MarketerPromotionFine::DEDUCTED,
                            'wallet_transaction_id' => $tx->id,
                            'deducted_at' => now(),
                        ]);
                        $deducted++;
                    });
                } catch (InsufficientBalanceException|\RuntimeException) {
                    // wallet short or frozen: stays pending, collected at the next withdrawal/close
                }
            });

        return $deducted;
    }

    public function waive(MarketerPromotionFine $fine, string $adminId, ?string $note = null): bool
    {
        return DB::transaction(function () use ($fine, $adminId, $note) {
            $fine = MarketerPromotionFine::whereKey($fine->id)->lockForUpdate()->firstOrFail();

            if ($fine->status !== MarketerPromotionFine::PENDING) {
                return false;
            }

            $fine->update(['status' => MarketerPromotionFine::WAIVED, 'waived_by_admin_id' => $adminId, 'note' => $note]);

            return true;
        });
    }
}
