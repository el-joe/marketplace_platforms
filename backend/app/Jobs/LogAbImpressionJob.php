<?php

namespace App\Jobs;

use App\Models\AbTestResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class LogAbImpressionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $abTestId,
        private readonly string $variant,
    ) {}

    public function handle(): void
    {
        $today = now()->toDateString();

        DB::table('ab_test_results')
            ->upsert(
                [
                    'id'          => (string) \Illuminate\Support\Str::uuid(),
                    'ab_test_id'  => $this->abTestId,
                    'variant'     => $this->variant,
                    'date'        => $today,
                    'impressions' => 1,
                ],
                ['ab_test_id', 'variant', 'date'],
                ['impressions' => DB::raw('ab_test_results.impressions + 1')],
            );
    }
}
