<?php

use App\Models\PortalContent;
use Database\Seeders\PortalContentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: makes every field on the portal /join landing page
 * (hero, logo, the three account-section cards and the Continue action)
 * fully editable through the Portal Content CMS in the admin panel.
 *
 * It inserts the newly CMS-managed join rows (card titles/descriptions/CTAs
 * and the illustrative images) via the non-destructive PortalContentSeeder,
 * then drops the legacy single-screen "form" rows that the redesigned page
 * no longer renders. Rows an admin has edited are always preserved.
 */
return new class extends Migration
{
    /**
     * Rows that the redesigned /join page no longer reads.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const OBSOLETE_ROWS = [
        ['join', 'form', 'country_label'],
        ['join', 'form', 'country_placeholder'],
        ['join', 'form', 'section_label'],
        ['join', 'form', 'specialization_label'],
        ['join', 'form', 'specialization_placeholder'],
        ['join', 'form', 'marketer_type_label'],
        ['join', 'form', 'submit'],
        ['join', 'form', 'note'],
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

        PortalContent::flush('join');
    }

    public function down(): void
    {
        // Data-only, non-destructive sync: nothing to roll back.
    }
};
