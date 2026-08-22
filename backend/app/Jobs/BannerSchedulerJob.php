<?php

namespace App\Jobs;

use App\Models\Banner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BannerSchedulerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = now();

        // Activate scheduled banners whose start date has passed
        Banner::where('status', 'scheduled')
            ->where('starts_at', '<=', $now)
            ->update(['status' => 'active']);

        // Expire active banners whose end date has passed
        Banner::where('status', 'active')
            ->where('ends_at', '<=', $now)
            ->update(['status' => 'expired']);
    }
}
