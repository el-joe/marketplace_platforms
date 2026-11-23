<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the distance-based delivery decision (client item #19) taken at place-order time.
 * Nullable: existing sub-orders and sub-orders without a distance rule stay NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->json('distance_pricing')->nullable()->after('shipping_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn('distance_pricing');
        });
    }
};
