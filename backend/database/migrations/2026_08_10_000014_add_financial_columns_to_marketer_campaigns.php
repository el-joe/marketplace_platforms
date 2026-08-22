<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->bigInteger('platform_fee_collected')
                  ->default(0)
                  ->after('marketer_commission_amount')
                  ->comment('Total platform fee charged to vendor. influencer_count × fee_per_influencer. BIGINT base-currency. No /100.');

            $table->unsignedTinyInteger('influencer_count_at_creation')
                  ->default(0)
                  ->after('platform_fee_collected')
                  ->comment('Number of influencers selected when campaign was created');

            $table->unsignedTinyInteger('affiliate_count_at_creation')
                  ->default(0)
                  ->after('influencer_count_at_creation')
                  ->comment('Number of affiliates selected when campaign was created');

            $table->bigInteger('total_commission_paid_out')
                  ->default(0)
                  ->after('affiliate_count_at_creation')
                  ->comment('Total commission paid to all marketers so far. BIGINT base-currency. No /100.');

            $table->bigInteger('total_conversions_amount')
                  ->default(0)
                  ->after('total_commission_paid_out')
                  ->comment('Total order value generated through this campaign. BIGINT base-currency. No /100.');

            $table->enum('fee_payment_status', ['pending', 'paid', 'waived'])
                  ->default('pending')
                  ->after('total_conversions_amount')
                  ->comment('Has the vendor paid the platform fee for this campaign?');

            $table->timestamp('fee_paid_at')->nullable()->after('fee_payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'platform_fee_collected',
                'influencer_count_at_creation',
                'affiliate_count_at_creation',
                'total_commission_paid_out',
                'total_conversions_amount',
                'fee_payment_status',
                'fee_paid_at',
            ]);
        });
    }
};
