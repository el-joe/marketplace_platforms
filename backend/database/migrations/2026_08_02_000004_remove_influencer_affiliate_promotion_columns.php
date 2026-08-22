<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExists(string $table, string $column): ?string
    {
        $row = \DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$table, $column]
        );

        return $row->CONSTRAINT_NAME ?? null;
    }

    public function up(): void
    {
        // -------------------------------------------------------------------
        // vendor_listings — remove all influencer/affiliate/promotion columns
        // -------------------------------------------------------------------
        Schema::table('vendor_listings', function (Blueprint $table) {
            $toDrop = [
                'available_for_marketers',
                'influencer_commission_pct',
                'affiliate_commission_pct',
                'promotion_fee_per_influencer_override',
                'min_stock_for_promotion',
                'influencer_commission_percentage',
                'affiliate_commission_percentage',
                'influencer_sample_quota',
                'affiliate_sample_quota',
            ];
            foreach ($toDrop as $col) {
                if (Schema::hasColumn('vendor_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // -------------------------------------------------------------------
        // categories — remove all influencer/affiliate/promotion columns
        // -------------------------------------------------------------------
        Schema::table('categories', function (Blueprint $table) {
            $toDrop = [
                'marketer_sample_quota',
                'admin_sample_quota',
                'influencer_commission_pct',
                'admin_cut_from_influencer_pct',
                'affiliate_commission_pct',
                'admin_cut_from_affiliate_pct',
                'promotion_fee_per_influencer',
                'min_stock_for_promotion',
                'influencer_monthly_quota',
            ];
            foreach ($toDrop as $col) {
                if (Schema::hasColumn('categories', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // -------------------------------------------------------------------
        // carts — remove affiliate_promo_code_id
        // -------------------------------------------------------------------
        if (Schema::hasColumn('carts', 'affiliate_promo_code_id')) {
            $fk = $this->foreignKeyExists('carts', 'affiliate_promo_code_id');
            Schema::table('carts', function (Blueprint $table) use ($fk) {
                if ($fk) {
                    $table->dropForeign($fk);
                }
                $table->dropColumn('affiliate_promo_code_id');
            });
        }

        // -------------------------------------------------------------------
        // orders — remove affiliate and marketer columns
        // -------------------------------------------------------------------
        $toDrop = [
            'affiliate_promo_code_id',
            'affiliate_commission_amount',
            // marketer_id and marketer_campaign_id already handled in Part 1
            // but drop again safely in case Part 1 was not run:
            'marketer_id',
            'marketer_campaign_id',
        ];
        $fks = [];
        foreach ($toDrop as $col) {
            if (Schema::hasColumn('orders', $col)) {
                $fks[$col] = $this->foreignKeyExists('orders', $col);
            }
        }
        Schema::table('orders', function (Blueprint $table) use ($fks) {
            foreach ($fks as $col => $fk) {
                if ($fk) {
                    $table->dropForeign($fk);
                }
                $table->dropColumn($col);
            }
        });

        // -------------------------------------------------------------------
        // payout_items — clean up promotion_fee and promotion_sample item_type
        // The item_type enum currently has: 'sub_order','promotion_fee','promotion_sample'
        // Change it back to just 'sub_order' (its original value).
        // -------------------------------------------------------------------
        if (Schema::hasColumn('payout_items', 'promotion_request_id')) {
            $fk = $this->foreignKeyExists('payout_items', 'promotion_request_id');
            Schema::table('payout_items', function (Blueprint $table) use ($fk) {
                if ($fk) {
                    $table->dropForeign($fk);
                }
                $table->dropColumn('promotion_request_id');
            });
        }
        // Modify the enum to remove promotion_fee and promotion_sample
        if (Schema::hasColumn('payout_items', 'item_type')) {
            \DB::statement("ALTER TABLE payout_items MODIFY COLUMN item_type ENUM('sub_order') NOT NULL DEFAULT 'sub_order'");
        }
    }

    public function down(): void
    {
        // No rollback.
    }
};
