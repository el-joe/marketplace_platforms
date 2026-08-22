<?php

namespace App\Console\Commands;

use App\Services\GiftCardService;
use Illuminate\Console\Command;

class ExpireGiftCards extends Command
{
    protected $signature = 'gift-cards:expire';

    protected $description = 'Set expired gift cards to expired status and log expiry transactions';

    public function handle(GiftCardService $giftCardService): int
    {
        $count = $giftCardService->expireDueCards();

        $this->info("Expired {$count} gift card(s).");

        return self::SUCCESS;
    }
}
