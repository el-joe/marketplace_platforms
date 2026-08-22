<?php

namespace App\Jobs;

use App\Mail\GiftCardPurchasedMail;
use App\Models\GiftCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendGiftCardNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $giftCardId) {}

    public function handle(): void
    {
        $giftCard = GiftCard::find($this->giftCardId);

        if (! $giftCard || ! $giftCard->recipient_email) {
            Log::warning('SendGiftCardNotificationJob: gift card or recipient email not found', ['id' => $this->giftCardId]);
            return;
        }

        Mail::to($giftCard->recipient_email)->send(new GiftCardPurchasedMail($giftCard));
    }
}
