<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-12 task 2: max_commission_budget existed but nothing
 * tracked how much of it had actually been committed, so it was never
 * enforced. commission_budget_spent is incremented (with the campaign row
 * locked) every time a conversion is recorded against this campaign, and
 * decremented when a conversion is reversed — the same reserve/release
 * pattern P-04's CouponUsageService uses for coupons.times_used.
 *
 * Backfill: sum of existing non-reversed conversions' commission_amount +
 * flash_sale_bonus_amount per campaign (there is no `campaign_id` on rows
 * pre-dating this migration other than what's already there, so this is a
 * straight aggregate, safe on a populated table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->bigInteger('commission_budget_spent')->default(0)->after('max_commission_budget')
                ->comment('Base-currency integer sum of commission_amount + flash_sale_bonus_amount for this campaign\'s non-reversed conversions.');
        });

        $sums = DB::table('marketer_campaign_conversions')
            ->select('campaign_id', DB::raw('SUM(commission_amount + IFNULL(flash_sale_bonus_amount, 0)) as total'))
            ->where('status', '!=', 'reversed')
            ->groupBy('campaign_id')
            ->get();

        foreach ($sums as $row) {
            DB::table('marketer_campaigns')->where('id', $row->campaign_id)->update(['commission_budget_spent' => (int) $row->total]);
        }
    }

    public function down(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->dropColumn('commission_budget_spent');
        });
    }
};
