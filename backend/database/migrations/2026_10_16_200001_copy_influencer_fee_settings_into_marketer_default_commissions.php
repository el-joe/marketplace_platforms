<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * v3 refactor (R3): data migration into marketer_default_commissions.
 *
 * The old model charged the vendor a fee per influencer (marketer_influencer_fee_country_settings.fee_per_influencer,
 * per country) and credited the influencer a share of it (marketer_commission_country_settings.influencer_fee_share_pct,
 * per category x country). What an influencer actually earned from one campaign was therefore
 *
 *     fee_per_influencer x influencer_fee_share_pct / 100.
 *
 * That is the closest equivalent of the new "fixed amount per campaign" and is copied for the PRODUCT vertical only
 * (the old model never existed for classified or travel):
 *   - the country-wide row (category_id NULL) takes the most common share % of the country's categories;
 *   - a category whose share differs from that baseline becomes an optional category override row.
 * Countries with a fee but no share configured earned nothing under the old model, so nothing is copied for them.
 *
 * Existing rows in marketer_default_commissions are never overwritten (admin input wins). The old tables are NOT dropped
 * here; the final removal phase drops them. Affiliate defaults (affiliate_commission_*) have no equivalent in the new
 * model (affiliate percentages live on the marketer profile) and are not migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['marketer_default_commissions', 'marketer_influencer_fee_country_settings', 'marketer_commission_country_settings'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $fees = DB::table('marketer_influencer_fee_country_settings')->where('fee_per_influencer', '>', 0)->pluck('fee_per_influencer', 'country_id');
        if ($fees->isEmpty()) {
            return;
        }

        $sharesByCountry = DB::table('marketer_commission_country_settings')
            ->whereIn('country_id', $fees->keys())
            ->where('influencer_fee_share_pct', '>', 0)
            ->get(['country_id', 'category_id', 'influencer_fee_share_pct'])
            ->groupBy('country_id');

        foreach ($sharesByCountry as $countryId => $shares) {
            $fee = (float) $fees[$countryId];

            // Most common share % wins as the country-wide baseline (ties resolve to the higher %).
            $baseline = $shares->groupBy(fn ($s) => (string) round((float) $s->influencer_fee_share_pct, 4))
                ->map->count()
                ->sortByDesc(fn ($count, $pct) => [$count, (float) $pct])
                ->keys()->first();
            $baseline = (float) $baseline;

            $this->insertIfMissing($countryId, null, round($fee * $baseline / 100, 2));

            foreach ($shares as $share) {
                $pct = round((float) $share->influencer_fee_share_pct, 4);
                if ($share->category_id && abs($pct - $baseline) > 0.00005) {
                    $this->insertIfMissing($countryId, $share->category_id, round($fee * $pct / 100, 2));
                }
            }
        }
    }

    public function down(): void
    {
        // Irreversible data copy: rows may since have been edited by admins, so nothing is removed.
    }

    private function insertIfMissing(string $countryId, ?string $categoryId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $ruleKey = sha1(implode('|', [$countryId, 'product', $categoryId ?? 'default']));
        if (DB::table('marketer_default_commissions')->where('rule_key', $ruleKey)->exists()) {
            return;
        }

        DB::table('marketer_default_commissions')->insert([
            'id' => (string) Str::uuid(),
            'rule_key' => $ruleKey,
            'country_id' => $countryId,
            'category_type' => 'product',
            'category_id' => $categoryId,
            'influencer_fixed_amount' => $amount,
            'commission_fee_percentage' => 0,
            'commission_fee_fixed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
