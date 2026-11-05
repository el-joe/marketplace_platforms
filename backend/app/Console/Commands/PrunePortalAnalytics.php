<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention for the portal analytics tables.
 *
 * Deletes page views older than the retention window and visitors that have been unseen for
 * that long and are not tied to a registration. Registration progress and step events are
 * tiny and are kept, so funnel history survives.
 */
class PrunePortalAnalytics extends Command
{
    protected $signature = 'portal-analytics:prune {--days= : Retention in days (default: portal_analytics.retention_days)}';

    protected $description = 'Delete portal page views and idle anonymous visitors older than the retention window';

    private const CHUNK = 5000;

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('portal_analytics.retention_days', 180));

        if ($days < 1) {
            $this->error('Retention must be at least 1 day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $pageViews = 0;
        do {
            $deleted = DB::table('portal_page_views')->where('viewed_at', '<', $cutoff)->limit(self::CHUNK)->delete();
            $pageViews += $deleted;
        } while ($deleted === self::CHUNK);

        $visitors = 0;
        do {
            $deleted = DB::table('portal_visitors')
                ->where('last_seen_at', '<', $cutoff)
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('registration_progress')
                    ->whereColumn('registration_progress.visitor_id', 'portal_visitors.id'))
                ->limit(self::CHUNK)
                ->delete();
            $visitors += $deleted;
        } while ($deleted === self::CHUNK);

        $this->info("Pruned {$pageViews} page view(s) and {$visitors} visitor(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
