<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Page;
use App\Services\Ads\AdSlotBlockGuard;
use App\Services\PageBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PageSchedulerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function handle(PageBuilderService $service, AdSlotBlockGuard $adSlotGuard): void
    {
        $now = now();

        // Auto-publish: any draft (or scheduled) page whose publish_at is due.
        Page::whereIn('status', ['draft', 'scheduled'])
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', $now)
            ->get()
            ->each(function (Page $page) use ($service) {
                try {
                    $admin = $this->resolveAdmin($page->last_edited_by_admin_id ?? $page->published_by_admin_id);
                    if (! $admin) {
                        Log::warning('PageSchedulerJob: no admin to publish page', ['page_id' => $page->id]);
                        return;
                    }
                    $service->publishPage($page, $admin, 'Auto-published by scheduler');
                } catch (Throwable $e) {
                    Log::error('PageSchedulerJob publish failed', [
                        'page_id' => $page->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        // Auto-unpublish (archive): any published page whose unpublish_at has passed.
        // Iterated individually (rather than one bulk UPDATE) so each page's active ad
        // bookings can be notified that their ad just went dark, and one page's failure
        // doesn't stop the rest of the batch.
        Page::where('status', 'published')
            ->whereNotNull('unpublish_at')
            ->where('unpublish_at', '<=', $now)
            ->get()
            ->each(function (Page $page) use ($adSlotGuard) {
                try {
                    DB::transaction(function () use ($page) {
                        $page->update([
                            'status' => 'archived',
                            'is_default' => false,
                        ]);
                    });

                    // The scheduler runs outside any request-scoped transaction by the
                    // time we get here, so call the guard directly instead of via
                    // DB::afterCommit (there is nothing left to commit into).
                    $adSlotGuard->onPageArchived($page);
                } catch (Throwable $e) {
                    Log::error('PageSchedulerJob auto-archive failed', [
                        'page_id' => $page->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    private function resolveAdmin(?string $adminId): ?Admin
    {
        if ($adminId) {
            $admin = Admin::find($adminId);
            if ($admin) {
                return $admin;
            }
        }
        // Fallback: first super-admin / first admin.
        return Admin::orderBy('created_at')->first();
    }
}
