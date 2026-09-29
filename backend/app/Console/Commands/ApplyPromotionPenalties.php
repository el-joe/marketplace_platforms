<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientBalanceException;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplyPromotionPenalties extends Command
{
    protected $signature = 'promotion:apply-penalties';

    protected $description = 'Debit penalty fees from sellers whose promotions violated minimums in the previous month';

    public function handle(WalletService $walletService): int
    {
        Log::info('promotion:apply-penalties starting');

        $appliedCount = 0;
        $skippedCount = 0;

        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        /**
         * Target: expired promotions that ended within last month, had a penalty fee
         * configured, and have not yet had the penalty applied (penalty_applied = false).
         * Idempotent: penalty_applied flag prevents re-debiting on reruns.
         */
        DB::table('promotions')
            ->where('status', 'expired')
            ->where('below_minimum', true)
            ->where('penalty_fee', '>', 0)
            ->where('penalty_applied', false)
            ->whereBetween('end_date', [$previousMonthStart, $previousMonthEnd])
            ->chunkById(100, function ($promotions) use ($walletService, &$appliedCount, &$skippedCount) {
                foreach ($promotions as $promotion) {
                    try {
                        $wallet = $walletService->getOrCreateWallet(
                            'vendor',
                            $promotion->seller_id,
                            $promotion->currency ?? 'EGP'
                        );

                        $walletService->debit(
                            wallet: $wallet,
                            amountCents: $promotion->penalty_fee,
                            sourceType: 'promotion_penalty',
                            sourceId: $promotion->id,
                            description: "Promotion minimum-sales penalty for promotion {$promotion->id}",
                        );

                        DB::table('promotions')
                            ->where('id', $promotion->id)
                            ->update(['penalty_applied' => true]);

                        $appliedCount++;
                    } catch (InsufficientBalanceException $e) {
                        Log::warning('promotion:apply-penalties insufficient balance', [
                            'promotion_id' => $promotion->id,
                            'seller_id' => $promotion->seller_id,
                            'penalty_fee' => $promotion->penalty_fee,
                        ]);
                        $skippedCount++;
                    }
                }
            });

        $this->info("Applied {$appliedCount} penalty(ies), skipped {$skippedCount} (insufficient balance).");
        Log::info('promotion:apply-penalties done', ['applied' => $appliedCount, 'skipped' => $skippedCount]);

        return self::SUCCESS;
    }
}
