<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // Restrict coupon to specific countries. NULL = valid in all countries.
            $table->json('country_ids')
                  ->nullable()
                  ->after('scope')
                  ->comment('JSON array of country UUIDs. NULL = all countries.');

            // Who absorbs the discount cost
            $table->enum('funded_by', ['platform', 'vendor', 'shared'])
                  ->default('platform')
                  ->after('is_stackable');

            // Percentage the vendor covers when funded_by = shared (0–100 integer, NOT basis points)
            $table->unsignedTinyInteger('vendor_share_pct')
                  ->nullable()
                  ->after('funded_by')
                  ->comment('0–100 integer. Platform absorbs the remainder.');

            // For customer_eligibility = specific_users
            $table->json('eligible_customer_ids')
                  ->nullable()
                  ->after('customer_eligibility')
                  ->comment('JSON array of customer UUIDs. Used when customer_eligibility = specific_users.');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['eligible_customer_ids', 'vendor_share_pct', 'funded_by', 'country_ids']);
        });
    }
};
