<?php

use App\Models\PortalContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Point the product-seller (/vendors) "Sign Up Now" calls to action at the
     * relocated registration form (/vendors/register) instead of the /join chooser.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private array $links = [
        ['home', 'hero', 'cta_button'],
        ['cta_footer', 'main', 'button'],
    ];

    public function up(): void
    {
        foreach ($this->links as [$page, $block, $field]) {
            DB::table('portal_contents')
                ->where('page_key', $page)
                ->where('block_key', $block)
                ->where('field_key', $field)
                ->where('value_url', '/join')
                ->update([
                    'value_url' => '/vendors/register',
                    'updated_at' => now(),
                ]);

            PortalContent::flush($page);
        }
    }

    public function down(): void
    {
        foreach ($this->links as [$page, $block, $field]) {
            DB::table('portal_contents')
                ->where('page_key', $page)
                ->where('block_key', $block)
                ->where('field_key', $field)
                ->where('value_url', '/vendors/register')
                ->update([
                    'value_url' => '/join',
                    'updated_at' => now(),
                ]);

            PortalContent::flush($page);
        }
    }
};
