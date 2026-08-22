<?php

use App\Jobs\AutoCompleteOrdersJob;
use App\Jobs\GenerateCodSettlementsJob;
use App\Jobs\ReleaseExpiredLocksJob;
use App\Jobs\CheckSlaBreachJob;
use App\Jobs\GenerateVendorPayoutsJob;
use App\Jobs\BannerSchedulerJob;
use App\Jobs\FlashSaleSchedulerJob;
use App\Jobs\TransitionFlashSaleStatusJob;
use App\Jobs\GenerateFbnStorageFeesJob;
use App\Jobs\FbnInboundReminderJob;
use App\Jobs\PublishScheduledBlogPostsJob;
use App\Jobs\RecalculateBestSellerRankingsJob;
use App\Jobs\ProcessAcquisitionCommissionsJob;
use App\Jobs\MonitorCampaignStockJob;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ReleaseExpiredLocksJob)->everyMinute()->withoutOverlapping()->name('release-expired-locks');
Schedule::job(new CheckSlaBreachJob)->everyFifteenMinutes();
Schedule::job(new AutoCompleteOrdersJob)->dailyAt('02:00');
Schedule::job(new TransitionFlashSaleStatusJob)->everyFiveMinutes();
Schedule::job(new FlashSaleSchedulerJob)->everyFiveMinutes()->withoutOverlapping()->name('flash-sale-scheduler');
Schedule::job(new BannerSchedulerJob)->everyFiveMinutes();
Schedule::job(new \App\Jobs\PageSchedulerJob)->everyFiveMinutes()->name('page-scheduler');
Schedule::job(new PublishScheduledBlogPostsJob)->everyFiveMinutes()->name('publish-scheduled-blog-posts');
Schedule::job(new MonitorCampaignStockJob)->hourly()->name('monitor-campaign-stock');

// Process vendor acquisition agent commissions for the previous month
Schedule::job(new ProcessAcquisitionCommissionsJob)->monthlyOn(1, '02:00')->name('process-acquisition-commissions');

// Generate vendor payouts every Monday at 06:00 for the previous week (Mon–Sun)
Schedule::call(function () {
    $periodEnd = Carbon::now()->startOfWeek(Carbon::MONDAY)->subDay()->endOfDay();  // last Sunday
    $periodStart = $periodEnd->copy()->startOfWeek(Carbon::MONDAY)->subWeek();        // Monday before last
    GenerateVendorPayoutsJob::dispatch($periodStart->startOfDay(), $periodEnd);
})->weeklyOn(Carbon::MONDAY, '06:00');

Schedule::job(new \App\Jobs\AggregateAnalyticsCacheJob)->hourly()->name('aggregate-analytics-cache');

// FBN: generate storage fees on the 1st of each month at 07:00
Schedule::call(function () {
    GenerateFbnStorageFeesJob::dispatch(now()->format('Y-m'));
})->monthlyOn(1, '07:00')->name('fbn-generate-storage-fees');

// FBN: remind admins of pending inbound requests every Monday at 08:00
Schedule::job(new FbnInboundReminderJob)->weeklyOn(1, '08:00')->name('fbn-inbound-reminder');

// FBN: compute daily storage overage fees for platform_fbn warehouses past their free period
Schedule::command('fbn:compute-daily-overage')->dailyAt('01:00')->name('fbn-compute-daily-overage');

// Generate COD settlements for delivery agents nightly at 23:30
Schedule::job(new GenerateCodSettlementsJob)->dailyAt('23:30')->name('generate-cod-settlements');

// Mark active gift cards past their expiry date as expired
Schedule::command('gift-cards:expire')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('expire-gift-cards');

// Deactivate coupons whose valid_until has passed
Schedule::command('coupons:deactivate-expired')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('deactivate-expired-coupons');

// Recalculate best-seller rankings per category/country
Schedule::job(new RecalculateBestSellerRankingsJob, 'rankings')
    ->everySixHours()
    ->withoutOverlapping(30)
    ->name('recalculate-bestseller-rankings');

// Expire overdue promotion request items and trigger auto-reassignment
Schedule::command('promotion:expire-items')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Send alerts to celebrities below monthly promotion minimum
Schedule::command('promotion:check-minimums')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();

// Apply month-end penalties to celebrities who missed minimums (runs on the 1st for the previous month)
Schedule::command('promotion:apply-penalties')
    ->monthlyOn(1, '01:00')
    ->withoutOverlapping()
    ->runInBackground();
