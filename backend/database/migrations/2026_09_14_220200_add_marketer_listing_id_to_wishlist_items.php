<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->char('marketer_listing_id', 36)->nullable()->after('classified_listing_id');
            $table->foreign('marketer_listing_id')
                  ->references('id')->on('marketer_listings')
                  ->nullOnDelete();
            $table->index('marketer_listing_id');
            $table->unique(['wishlist_group_id', 'marketer_listing_id'], 'uq_wg_marketer_listing');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropUnique('uq_wg_marketer_listing');
            $table->dropForeign(['marketer_listing_id']);
            $table->dropColumn('marketer_listing_id');
        });
    }
};
