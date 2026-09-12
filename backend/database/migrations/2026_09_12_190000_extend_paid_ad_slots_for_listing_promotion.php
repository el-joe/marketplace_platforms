<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `paid_ad_slots`
            MODIFY COLUMN `target_type`
            ENUM('placement','page_block','listing_promotion')
            NOT NULL DEFAULT 'placement'
        ");

        Schema::table('paid_ad_slots', function (Blueprint $table) {
            $table->boolean('shows_popup')
                  ->default(false)
                  ->after('target_type')
                  ->comment(
                      'false = listing_promotion tier 1 (boost only). '.
                      'true  = listing_promotion tier 2 (boost + storefront popup).'
                  );
        });
    }

    public function down(): void
    {
        Schema::table('paid_ad_slots', function (Blueprint $table) {
            $table->dropColumn('shows_popup');
        });

        DB::statement("
            ALTER TABLE `paid_ad_slots`
            MODIFY COLUMN `target_type`
            ENUM('placement','page_block')
            NOT NULL DEFAULT 'placement'
        ");
    }
};
