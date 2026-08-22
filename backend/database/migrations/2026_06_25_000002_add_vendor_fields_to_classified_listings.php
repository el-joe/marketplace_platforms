<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classified_listings', function (Blueprint $table) {
            $table->tinyInteger('is_vendor_listing')->default(0)->after('marketer_promotion_enabled');
            // Optional vendor internal reference — drop this column if not needed
            $table->string('vendor_listing_reference', 100)->nullable()->after('is_vendor_listing');
        });
    }

    public function down(): void
    {
        Schema::table('classified_listings', function (Blueprint $table) {
            $table->dropColumn(['is_vendor_listing', 'vendor_listing_reference']);
        });
    }
};
