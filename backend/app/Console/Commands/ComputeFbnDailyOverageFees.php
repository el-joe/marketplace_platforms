<?php

namespace App\Console\Commands;

use App\Services\FbnDailyOverageFeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ComputeFbnDailyOverageFees extends Command
{
    protected $signature = 'fbn:compute-daily-overage {--date= : Date to compute for (YYYY-MM-DD, defaults to today)}';

    protected $description = 'Compute FBN daily storage overage fees for platform_fbn warehouses past their free storage period';

    public function handle(FbnDailyOverageFeeService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $service->computeOverageForDate($date);

        $this->info("Computed FBN daily overage fees for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
