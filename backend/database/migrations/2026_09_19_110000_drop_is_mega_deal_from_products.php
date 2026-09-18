<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrects docs/plans/dynamic-badges-and-classified-actions.md Task A: that
 * task added a flat `products.is_mega_deal` boolean, but "Mega Deal" is
 * actually an existing Page Builder block type (`page_blocks.block_type =
 * 'mega_deals'`) with its own admin UI and product association via
 * `page_block_products`. The flat column was never written to by that real
 * flow and is redundant/misleading — drop it. `is_mega_deal` is now computed
 * live from Page Builder data (see App\Services\Shared\PageBuilderService::
 * activeMegaDealProductIds()).
 *
 * The `product_promo_badges` table created by the same original migration
 * (2026_09_19_100000_create_product_promo_badges_table.php) is unrelated
 * (rotating PDP/listing-card badge messages) and is untouched here.
 *
 * See docs/plans/mega-deal-page-builder-correction.md Task F.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_mega_deal');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_mega_deal')->default(false)->after('is_featured');
        });
    }
};
