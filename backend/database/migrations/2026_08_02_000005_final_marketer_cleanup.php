<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. admin_product_listings — remove remaining marketer/influencer columns
        Schema::table('admin_product_listings', function (Blueprint $table) {
            foreach ([
                'available_for_marketers',
                'influencer_commission_percentage',
                'affiliate_commission_percentage',
                'influencer_sample_quota',
                'affiliate_sample_quota',
            ] as $col) {
                if (Schema::hasColumn('admin_product_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // 2. countries — remove marketer_withholding_tax_rate
        if (Schema::hasColumn('countries', 'marketer_withholding_tax_rate')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->dropColumn('marketer_withholding_tax_rate');
            });
        }

        // 3. order_items — remove marketer_product_id
        if (Schema::hasColumn('order_items', 'marketer_product_id')) {
            $fk = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
                 AND COLUMN_NAME = 'marketer_product_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
            )?->CONSTRAINT_NAME;

            Schema::table('order_items', function (Blueprint $table) use ($fk) {
                if ($fk) {
                    $table->dropForeign($fk);
                }
                $table->dropColumn('marketer_product_id');
            });
        }

        // 4. permissions — delete all marketer/influencer/celebrity/promotion permissions
        DB::table('permissions')->whereIn('guard_name', ['marketer'])
            ->delete();
        DB::table('permissions')
            ->where(function($q) {
                $q->where('name', 'like', 'marketers%')
                  ->orWhere('name', 'like', 'marketer%')
                  ->orWhere('name', 'like', 'influencer%')
                  ->orWhere('name', 'like', 'celebrity%')
                  ->orWhere('name', 'like', 'promotion%')
                  ->orWhere('name', 'like', 'open_market%')
                  ->orWhere('name', 'admin_can_manage_influencer_promotions')
                  ->orWhere('name', 'admin_can_manage_marketer_quotas')
                  ->orWhere('name', 'campaigns.manage_marketers');
            })
            ->delete();

        // 5. model_has_permissions — remove orphaned Marketer model rows
        DB::table('model_has_permissions')
            ->where('model_type', 'App\\Models\\Marketer')
            ->delete();
        DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\Marketer')
            ->delete();

        // 6. jobs queue — clear stale marketer/influencer jobs
        DB::table('jobs')
            ->where('payload', 'like', '%Marketer%')
            ->orWhere('payload', 'like', '%marketer%')
            ->orWhere('payload', 'like', '%InfluencerPromotion%')
            ->orWhere('payload', 'like', '%VendorInfluencerPromotion%')
            ->delete();
    }

    public function down(): void {}
};
