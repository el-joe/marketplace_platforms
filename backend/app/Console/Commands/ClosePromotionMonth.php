<?php

namespace App\Console\Commands;

use App\Services\Marketer\PromotionPenaltyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ClosePromotionMonth extends Command
{
    protected $signature = 'promotion:close-month {--month= : Month to close as Y-m (default: previous month)}';

    protected $description = 'Close a month of marketer promotion quotas: fine every marketer who ended below the minimum of his category (once per marketer/category/month) and collect pending fines';

    public function handle(PromotionPenaltyService $penalties): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m-d', $this->option('month').'-01')->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $created = $penalties->closeMonth($month);

        $this->info("Closed {$month->format('Y-m')}: {$created} new fine(s).");

        return self::SUCCESS;
    }
}
