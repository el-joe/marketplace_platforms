<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'classified_listing_marketers',
            'marketer_whatsapp_links',
            'marketer_qr_codes',
            'marketer_sample_items',
            'marketer_sample_requests',
            'marketer_secret_promotions',
            'marketer_monthly_quota_progress',
            'marketer_monthly_quotas',
            'marketer_category_commissions',
            'marketer_commission_tiers',
            'marketer_conversions',
            'marketer_clicks',
            'marketer_campaign_products',
            'marketer_campaigns',
            'marketer_products',
            'marketer_payouts',
            'marketer_password_resets',
            'admin_marketer_invitations',
            'marketers',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // No rollback.
    }
};
