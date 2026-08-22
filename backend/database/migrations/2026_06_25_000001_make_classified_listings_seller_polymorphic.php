<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classified_listings', function (Blueprint $table) {
            $table->string('seller_type', 50)->after('listing_number')->default('');
            $table->char('seller_id', 36)->after('seller_type')->default('');
        });

        // Backfill from customer_id (defensive — table is empty in production,
        // but correct if test data exists in any environment)
        DB::statement("
            UPDATE classified_listings
            SET seller_type = 'App\\\\Models\\\\Customer',
                seller_id   = customer_id
            WHERE customer_id IS NOT NULL
        ");

        Schema::table('classified_listings', function (Blueprint $table) {
            // Remove defaults now that backfill is done
            $table->string('seller_type', 50)->nullable(false)->change();
            $table->char('seller_id', 36)->nullable(false)->change();

            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');

            $table->index(['seller_type', 'seller_id'], 'classified_listings_seller_index');
        });
    }

    public function down(): void
    {
        Schema::table('classified_listings', function (Blueprint $table) {
            $table->dropIndex('classified_listings_seller_index');

            $table->uuid('customer_id')->nullable()->after('listing_number');
        });

        DB::statement("
            UPDATE classified_listings
            SET customer_id = seller_id
            WHERE seller_type = 'App\\\\Models\\\\Customer'
        ");

        Schema::table('classified_listings', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->dropColumn(['seller_type', 'seller_id']);
        });
    }
};
