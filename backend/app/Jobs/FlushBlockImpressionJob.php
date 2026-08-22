<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Increments block_analytics.impressions for one visible page-block render.
 * Dispatched fire-and-forget from PageRendererService so impression tracking
 * never blocks the customer-facing page render response — mirrors the upsert
 * pattern established by LogAbImpressionJob.
 */
class FlushBlockImpressionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $pageBlockId,
        private readonly string $pageId,
        private readonly string $countryId,
    ) {}

    public function handle(): void
    {
        $today = now()->toDateString();

        DB::table('block_analytics')
            ->upsert(
                [
                    'id' => (string) Str::uuid(),
                    'page_block_id' => $this->pageBlockId,
                    'page_id' => $this->pageId,
                    'country_id' => $this->countryId,
                    'date' => $today,
                    'impressions' => 1,
                ],
                ['page_block_id', 'date'],
                ['impressions' => DB::raw('block_analytics.impressions + 1')],
            );
    }
}
