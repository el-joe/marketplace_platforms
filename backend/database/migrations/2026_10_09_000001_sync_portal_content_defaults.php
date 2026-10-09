<?php

use App\Models\PortalContent;
use Database\Seeders\PortalContentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: aligns the Portal Content CMS with the current Blade
 * fallbacks (see docs/plans/portal-content-cms-audit.md).
 *
 * - inserts every newly CMS-managed field (nav sub-menus, logos, page meta,
 *   layout defaults, videos, icons, blog labels, …);
 * - refreshes rows no admin has ever edited (old "noon" copy/imagery, the
 *   help-center language toggle that was pinned to /language/ar, …);
 * - leaves admin-edited values untouched (SyncsPortalContent).
 */
return new class extends Migration
{
    /**
     * Rows that no Blade view reads anymore.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const OBSOLETE_ROWS = [
        // Replaced by the home.testimonials.video link row (label = iframe title, url = embed).
        ['home', 'testimonials', 'video_title'],
    ];

    public function up(): void
    {
        (new PortalContentSeeder)->run();

        foreach (self::OBSOLETE_ROWS as [$pageKey, $blockKey, $fieldKey]) {
            PortalContent::where('page_key', $pageKey)
                ->where('block_key', $blockKey)
                ->where('field_key', $fieldKey)
                ->whereNull('updated_by_admin_id')
                ->delete();

            PortalContent::flush($pageKey);
        }
    }

    public function down(): void
    {
        // Data-only, non-destructive sync: nothing to roll back.
    }
};
