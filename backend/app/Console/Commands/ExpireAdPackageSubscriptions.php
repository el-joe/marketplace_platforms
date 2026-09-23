<?php

namespace App\Console\Commands;

use App\Services\Marketer\AdPackageService;
use Illuminate\Console\Command;

class ExpireAdPackageSubscriptions extends Command
{
    protected $signature = 'ad-packages:expire';
    protected $description = 'Mark active marketer ad package subscriptions past expires_at as expired';

    public function handle(AdPackageService $service): int
    {
        $this->info('Expired '.$service->expireDue().' subscription(s).');

        return self::SUCCESS;
    }
}
