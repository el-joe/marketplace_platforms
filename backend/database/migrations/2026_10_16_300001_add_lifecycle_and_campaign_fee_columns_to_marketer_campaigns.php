<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R6): campaign-level columns for the product vertical.
 *
 *  - commission_type: the tiered / last_click / fixed variants are gone. The column is relaxed from ENUM to VARCHAR
 *    and every new campaign writes the single constant 'per_marketer_type' (commission is decided per marketer type
 *    by MarketerCommissionCalculator). Existing rows keep their old value; phase F drops the column.
 *  - ends_at: optional campaign end date. When it passes, the campaign completes and the influencers' fixed
 *    amounts are paid (CompleteEndedMarketerCampaignsJob).
 *  - influencer_fixed_snapshot: {marketer_id: {amount, source}} frozen at creation, copied onto each invitation
 *    when it is dispatched, so settings changes never rewrite what the vendor agreed to.
 *  - campaign_fee_*: the platform campaign fee (fixed + variable, columns marketing_fee_* already hold the snapshot)
 *    is charged once when the campaign goes live: wallet first, otherwise the next vendor statement.
 *  - influencers_settled_at: the fixed amounts were paid / released at campaign completion (idempotency stamp).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->isMysql()) {
            DB::statement("ALTER TABLE `marketer_campaigns` MODIFY `commission_type` VARCHAR(40) NOT NULL DEFAULT 'per_marketer_type' COMMENT 'v3: always per_marketer_type (legacy: fixed | tiered | last_click)'");
        }

        Schema::table('marketer_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('marketer_campaigns', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->comment('Optional campaign end date');
            }
            if (! Schema::hasColumn('marketer_campaigns', 'influencer_fixed_snapshot')) {
                $table->json('influencer_fixed_snapshot')->nullable()->comment('marketer_id => {amount, source} frozen at creation');
            }
            if (! Schema::hasColumn('marketer_campaigns', 'campaign_fee_status')) {
                $table->string('campaign_fee_status', 20)->default('not_applicable')->comment('not_applicable | pending | paid | voided');
            }
            if (! Schema::hasColumn('marketer_campaigns', 'campaign_fee_paid_at')) {
                $table->timestamp('campaign_fee_paid_at')->nullable();
            }
            if (! Schema::hasColumn('marketer_campaigns', 'campaign_fee_paid_via')) {
                $table->string('campaign_fee_paid_via', 12)->nullable()->comment('wallet | statement');
            }
            if (! Schema::hasColumn('marketer_campaigns', 'influencers_settled_at')) {
                $table->timestamp('influencers_settled_at')->nullable()->comment('Influencer fixed amounts paid / released at completion');
            }
        });

        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->index(['status', 'ends_at'], 'mc_status_ends_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->dropIndex('mc_status_ends_at_idx');
        });

        foreach (['ends_at', 'influencer_fixed_snapshot', 'campaign_fee_status', 'campaign_fee_paid_at', 'campaign_fee_paid_via', 'influencers_settled_at'] as $column) {
            if (Schema::hasColumn('marketer_campaigns', $column)) {
                Schema::table('marketer_campaigns', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        // commission_type stays VARCHAR on rollback: narrowing back to the ENUM would fail for 'per_marketer_type' rows.
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
