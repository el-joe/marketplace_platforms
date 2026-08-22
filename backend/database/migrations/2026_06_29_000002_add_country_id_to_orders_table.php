<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Shipping-destination country — this is authoritative for VAT, fulfillment routing,
            // and currency-scoped reporting. It matches the country_id already passed to the
            // tax/shipping engine at checkout (from the selected delivery address).
            // Nullable to handle any historical rows where the snapshot lacks country_id.
            $table->uuid('country_id')->nullable()->after('customer_id');
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->index('country_id');
        });

        // Backfill: pull country_id from the shipping_address_snapshot JSON field.
        // All rows placed after the snapshot schema was established should have it;
        // rows missing it remain NULL and are excluded from country-scoped reports.
        DB::statement("
            UPDATE orders
            SET country_id = JSON_UNQUOTE(JSON_EXTRACT(shipping_address_snapshot, '$.country_id'))
            WHERE JSON_UNQUOTE(JSON_EXTRACT(shipping_address_snapshot, '$.country_id')) IS NOT NULL
              AND JSON_UNQUOTE(JSON_EXTRACT(shipping_address_snapshot, '$.country_id')) != 'null'
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
