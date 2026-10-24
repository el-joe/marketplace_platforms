<?php

use App\Models\PortalContent;
use Database\Seeders\PortalContentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: brings the Portal Content CMS in line with the live
 * marketer landing pages, the section navigation bar, the country/language
 * selector and the shared portal footer.
 *
 * - inserts the newly CMS-managed rows (all countries) via the non-destructive
 *   PortalContentSeeder; rows an admin has edited are always preserved;
 * - removes the legacy /marketers choice-screen rows no Blade view reads anymore
 *   (only when no admin ever edited them).
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const OBSOLETE_ROWS = [
        ['marketers', 'hero', 'back'],
        ['marketers', 'actions', 'continue'],
        ['marketers', 'actions', 'select_hint'],
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
        }

        foreach (['marketers', 'marketer-type', 'nav', 'footer'] as $pageKey) {
            PortalContent::flush($pageKey);
        }
    }

    public function down(): void
    {
        // Data-only, non-destructive sync: nothing to roll back.
    }
};
