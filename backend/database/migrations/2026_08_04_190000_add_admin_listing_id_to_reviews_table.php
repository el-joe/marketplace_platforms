<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignUuid('admin_listing_id')->nullable()->after('vendor_listing_id')
                  ->constrained('admin_listings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['admin_listing_id']);
            $table->dropColumn('admin_listing_id');
        });
    }
};
