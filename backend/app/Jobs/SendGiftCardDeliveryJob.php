<?php

namespace App\Jobs;

use App\Models\GiftCardPurchase;
use App\Services\GiftCardPurchaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendGiftCardDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $giftCardPurchaseId) {}

    public function handle(GiftCardPurchaseService $giftCardPurchaseService): void
    {
        $purchase = GiftCardPurchase::find($this->giftCardPurchaseId);

        if (! $purchase) {
            Log::warning('SendGiftCardDeliveryJob: purchase not found', ['id' => $this->giftCardPurchaseId]);
            return;
        }

        $giftCardPurchaseService->deliverCard($purchase);
    }
}
