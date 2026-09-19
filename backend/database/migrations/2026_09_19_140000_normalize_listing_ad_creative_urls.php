<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rewrite legacy "/products/{sku}--{short}" links to "/products/{variant_id}--{listing_id}".
        DB::table('paid_ad_creatives')
            ->join('vendor_listings', 'vendor_listings.id', '=', 'paid_ad_creatives.destination_reference_id')
            ->where('paid_ad_creatives.destination_type', 'listing')
            ->update([
                'paid_ad_creatives.destination_url' => DB::raw(
                    "CONCAT('/products/', vendor_listings.product_variant_id, '--', vendor_listings.id)"
                ),
            ]);
    }

    public function down(): void
    {
        // Irreversible data normalization.
    }
};
