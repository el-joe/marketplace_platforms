<?php

use App\Models\PortalContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: drops the per-card CTA copy and the "Selected" label from
 * the Portal Content CMS.
 *
 * The redesigned /join cards no longer render an internal text link — clicking
 * a card only selects it and navigation happens through the single Continue
 * button — so these fields would otherwise linger as dead admin inputs.
 * Rows an admin has explicitly edited are preserved.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const OBSOLETE_ROWS = [
        ['join', 'card_product_vendor', 'cta'],
        ['join', 'card_classified_vendor', 'cta'],
        ['join', 'card_marketer', 'cta'],
        ['join', 'actions', 'selected_label'],
    ];

    public function up(): void
    {
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
