<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            // Influencer deal system
            'influencer_deal_deliverables',
            'influencer_deals',
            'influencer_media_kits',

            // Celebrity store system
            'celebrity_open_market_promotions',
            'celebrity_store_products',
            'admin_influencer_open_market_products',

            // Vendor ↔ Influencer promotion request system
            'vendor_influencer_reassignment_log',
            'vendor_influencer_promotion_request_items',
            'vendor_influencer_promotion_requests',

            // Affiliate promo codes
            'affiliate_promo_codes',

            // Promotion country settings
            'promotion_country_settings',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // No rollback.
    }
};
