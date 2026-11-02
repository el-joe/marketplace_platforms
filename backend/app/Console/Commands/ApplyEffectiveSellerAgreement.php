<?php

namespace App\Console\Commands;

use App\Services\Vendor\SellerAgreementService;
use Illuminate\Console\Command;

class ApplyEffectiveSellerAgreement extends Command
{
    protected $signature = 'contracts:apply-effective';

    protected $description = 'Retire replaced Seller Agreement versions and record implicit acceptance once a notice period ends';

    public function handle(SellerAgreementService $agreements): int
    {
        $recorded = $agreements->applyEffective();

        $this->info("Implicit acceptances recorded: {$recorded}");

        return self::SUCCESS;
    }
}
