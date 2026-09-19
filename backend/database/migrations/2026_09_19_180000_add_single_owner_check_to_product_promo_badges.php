<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE product_promo_badges ADD CONSTRAINT chk_promo_badge_single_owner CHECK ((vendor_listing_id IS NOT NULL) + (admin_listing_id IS NOT NULL) + (marketer_listing_id IS NOT NULL) <= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE product_promo_badges DROP CHECK chk_promo_badge_single_owner');
    }
};
