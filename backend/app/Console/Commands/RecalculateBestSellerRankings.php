<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateBestSellerRankingsJob;
use Illuminate\Console\Command;

class RecalculateBestSellerRankings extends Command
{
    protected $signature = 'rankings:recalculate';
    protected $description = 'Recalculate best-seller product rankings per category/country synchronously.';

    public function handle(): int
    {
        $this->info('Recalculating best-seller rankings...');

        (new RecalculateBestSellerRankingsJob($this->output))->handle();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
