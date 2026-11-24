<?php

namespace Tests\Feature\Portal;

use App\Models\PortalContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client retest R6: «مثالي لـ:» → «مثالي لك:» on the portal fulfillment page, keeping admin-edited copy.
 */
class FulfillmentIdealLabelCopyMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $block, string $ar, string $country = '*'): PortalContent
    {
        return PortalContent::query()->updateOrCreate(
            ['country_code' => $country, 'page_key' => 'fulfillment', 'block_key' => $block, 'field_key' => 'ideal_label'],
            ['type' => 'text', 'value_en' => 'Ideal for:', 'value_ar' => $ar, 'sort_order' => 1, 'is_active' => true],
        );
    }

    public function test_rewrites_only_rows_that_still_hold_the_old_default(): void
    {
        $fbn = $this->row('fbn', 'مثالي لـ:');
        $fbpOman = $this->row('fbp', 'مثالي لـ:', 'om');
        $edited = $this->row('fbp', 'مناسب لـ:');

        (require database_path('migrations/2026_11_24_120000_fix_fulfillment_ideal_label_arabic_copy.php'))->up();

        $this->assertSame('مثالي لك:', $fbn->fresh()->value_ar);
        $this->assertSame('مثالي لك:', $fbpOman->fresh()->value_ar);
        $this->assertSame('مناسب لـ:', $edited->fresh()->value_ar);
        $this->assertSame('Ideal for:', $fbn->fresh()->value_en);
    }
}
