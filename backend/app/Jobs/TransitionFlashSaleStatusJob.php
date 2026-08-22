<?php

namespace App\Jobs;

use App\Models\FlashSale;
use App\Services\FlashSaleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Automatically transitions flash sales through status stages
 * based on their configured dates.
 *
 * Runs every 5 minutes and handles:
 *   - draft → open        when submission_opens_at is reached
 *   - open  → review      when submission_closes_at is reached
 *   - scheduled → live    when sale_starts_at is reached
 *   - live  → ended       when sale_ends_at is reached
 */
class TransitionFlashSaleStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(FlashSaleService $service): void
    {
        $now     = now();
        $fakeAdmin = null; // system transitions use null (system-initiated)
        $count   = 0;

        // ── draft → open (submission window begins) ───────────────────────────
        FlashSale::where('status', 'draft')
            ->whereNotNull('submission_opens_at')
            ->where('submission_opens_at', '<=', $now)
            ->each(function (FlashSale $sale) use ($service, &$count) {
                try {
                    $sale->update(['status' => 'open', 'updated_by_admin_id' => null]);
                    $count++;
                    Log::info("FlashSale #{$sale->id} transitioned draft → open.");
                } catch (\Throwable $e) {
                    Log::error("FlashSale #{$sale->id} failed draft→open: " . $e->getMessage());
                }
            });

        // ── open → review (submission window closed) ──────────────────────────
        FlashSale::where('status', 'open')
            ->whereNotNull('submission_closes_at')
            ->where('submission_closes_at', '<=', $now)
            ->each(function (FlashSale $sale) use (&$count) {
                try {
                    $sale->update(['status' => 'review', 'updated_by_admin_id' => null]);
                    $count++;
                    Log::info("FlashSale #{$sale->id} transitioned open → review.");
                } catch (\Throwable $e) {
                    Log::error("FlashSale #{$sale->id} failed open→review: " . $e->getMessage());
                }
            });

        // ── scheduled → live (sale starts) ───────────────────────────────────
        FlashSale::where('status', 'scheduled')
            ->whereNotNull('sale_starts_at')
            ->where('sale_starts_at', '<=', $now)
            ->each(function (FlashSale $sale) use (&$count) {
                try {
                    $sale->update(['status' => 'live', 'updated_by_admin_id' => null]);
                    // Activate all approved submissions
                    $sale->submissions()->where('status', 'approved')->update(['status' => 'active']);
                    $count++;
                    Log::info("FlashSale #{$sale->id} transitioned scheduled → live.");
                } catch (\Throwable $e) {
                    Log::error("FlashSale #{$sale->id} failed scheduled→live: " . $e->getMessage());
                }
            });

        // ── live → ended (sale ends) ──────────────────────────────────────────
        FlashSale::where('status', 'live')
            ->whereNotNull('sale_ends_at')
            ->where('sale_ends_at', '<=', $now)
            ->each(function (FlashSale $sale) use (&$count) {
                try {
                    $sale->update(['status' => 'ended', 'updated_by_admin_id' => null]);
                    $sale->submissions()->where('status', 'active')->update(['status' => 'ended']);
                    $count++;
                    Log::info("FlashSale #{$sale->id} transitioned live → ended.");
                } catch (\Throwable $e) {
                    Log::error("FlashSale #{$sale->id} failed live→ended: " . $e->getMessage());
                }
            });

        if ($count > 0) {
            Log::info("TransitionFlashSaleStatusJob: processed {$count} transition(s).");
        }
    }
}
