<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            // Fee charged to vendor when this influencer accepts
            $table->bigInteger('platform_fee_amount')
                  ->default(0)
                  ->after('total_commission_earned')
                  ->comment('Platform fee charged for this influencer slot. 0 for affiliate. BIGINT base-currency. No /100.');

            $table->char('platform_fee_currency', 3)
                  ->nullable()
                  ->after('platform_fee_amount');

            $table->enum('platform_fee_status', ['not_applicable', 'pending', 'paid', 'waived'])
                  ->default('not_applicable')
                  ->after('platform_fee_currency')
                  ->comment('not_applicable = affiliate type (always free)');

            $table->timestamp('platform_fee_recorded_at')
                  ->nullable()
                  ->after('platform_fee_status');
        });

        // Remove old campaign-level fee columns — fee is now per invitation
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $columns = [
                'platform_fee_collected',
                'influencer_count_at_creation',
                'affiliate_count_at_creation',
                'total_commission_paid_out',
                'total_conversions_amount',
                'fee_payment_status',
                'fee_paid_at',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('marketer_campaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->dropColumn([
                'platform_fee_amount',
                'platform_fee_currency',
                'platform_fee_status',
                'platform_fee_recorded_at',
            ]);
        });
    }
};
