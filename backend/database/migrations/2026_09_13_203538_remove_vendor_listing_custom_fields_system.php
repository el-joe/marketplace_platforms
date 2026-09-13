<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the order-side table first — it has FKs pointing at the
        // listing-side tables, must go before them.
        Schema::dropIfExists('order_item_custom_inputs');

        Schema::dropIfExists('vendor_listing_addon_options');
        Schema::dropIfExists('vendor_listing_addon_groups');
        Schema::dropIfExists('vendor_listing_custom_fields');

        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropColumn(['has_order_notes', 'size_guide_image_url']);
        });
    }

    public function down(): void
    {
        // Deliberately irreversible — this is a full-stack feature
        // removal, confirmed data-safe (0 rows existed in
        // order_item_custom_inputs at removal time), not a reversible
        // schema tweak. Restore from a pre-removal backup if genuinely
        // needed.
        throw new \RuntimeException(
            'This migration is not reversible. Restore from backup if needed.'
        );
    }
};
