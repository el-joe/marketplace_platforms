<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->foreignUuid('primary_shipping_method_id')->nullable()
                ->constrained('shipping_methods')->nullOnDelete()
                ->comment('Resolved default shipping method for this listing, cached from category rules + fulfillment type. Recomputed when category rules change.');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropForeign(['primary_shipping_method_id']);
            $table->dropColumn('primary_shipping_method_id');
        });
    }
};
