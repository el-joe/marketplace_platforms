<?php

use App\Models\PortalContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Client retest R6 (#34): the portal /fulfillment FBN and FBP sections read «مثالي لـ:»; the copy is «مثالي لك:».
 *
 * Only rows (every country) whose Arabic value still holds the old shipped default are rewritten,
 * so wording an admin already changed in Portal Content is kept.
 */
return new class extends Migration
{
    private const OLD_AR = 'مثالي لـ:';

    private const NEW_AR = 'مثالي لك:';

    private const BLOCKS = ['fbn', 'fbp'];

    public function up(): void
    {
        $this->rewrite(self::OLD_AR, self::NEW_AR);
    }

    public function down(): void
    {
        $this->rewrite(self::NEW_AR, self::OLD_AR);
    }

    private function rewrite(string $from, string $to): void
    {
        PortalContent::query()
            ->where('page_key', 'fulfillment')
            ->whereIn('block_key', self::BLOCKS)
            ->where('field_key', 'ideal_label')
            ->where('value_ar', $from)
            ->update(['value_ar' => $to]);

        PortalContent::flush('fulfillment');
    }
};
