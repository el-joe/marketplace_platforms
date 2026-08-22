<?php

namespace App\Jobs;

use App\Enums\FlashSaleStatus;
use App\Enums\FlashSaleSubmissionStatus;
use App\Models\FlashSale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched when a flash sale transitions to 'live'.
 * Notifies all vendors with approved submissions that the sale is now live.
 */
class FlashSaleLiveNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly string $flashSaleId)
    {
    }

    public function handle(): void
    {
        $sale = FlashSale::find($this->flashSaleId);

        if (!$sale || $sale->status?->value !== FlashSaleStatus::Live->value) {
            Log::info('[FlashSaleLiveNotificationJob] Skipping — sale not found or not live.', [
                'flash_sale_id' => $this->flashSaleId,
            ]);
            return;
        }

        $approvedSubmissions = $sale->submissions()
            ->where('status', FlashSaleSubmissionStatus::Approved->value)
            ->with('vendor')
            ->get();

        foreach ($approvedSubmissions as $submission) {
            $vendor = $submission->vendor;
            if (!$vendor)
                continue;

            // TODO: dispatch per-vendor notification (email / push)
            // Example: \App\Notifications\FlashSaleLiveNotification::send($vendor, $sale);
            Log::info('[FlashSaleLiveNotificationJob] Notifying vendor of live sale.', [
                'vendor_id' => $vendor->id,
                'flash_sale_id' => $sale->id,
                'submission_id' => $submission->id,
            ]);
        }
    }
}
