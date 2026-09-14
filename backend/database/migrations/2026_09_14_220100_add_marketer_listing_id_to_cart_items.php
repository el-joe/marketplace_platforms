<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->char('marketer_listing_id', 36)->nullable()->after('admin_listing_id');
            $table->foreign('marketer_listing_id')
                  ->references('id')->on('marketer_listings')
                  ->nullOnDelete();
            $table->index('marketer_listing_id');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['marketer_listing_id']);
            $table->dropColumn('marketer_listing_id');
        });
    }
};
