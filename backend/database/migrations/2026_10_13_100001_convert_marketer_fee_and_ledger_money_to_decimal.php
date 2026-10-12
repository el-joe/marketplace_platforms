<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money in the marketer fee/commission chain and the ledgers it settles into
 * becomes DECIMAL(19,4), the same as order money. Previously a 2.00 platform fee
 * with 5% VAT gave a 0.10 VAT that BIGINT rounded to 0.
 *
 * Existing values are preserved exactly (BIGINT -> DECIMAL is lossless); NULL-ability
 * and defaults are kept. Tables/columns that do not exist yet are skipped.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private const COLUMNS = [
        'marketer_influencer_fee_country_settings' => ['fee_per_influencer'],
        'marketer_commission_country_settings' => ['influencer_commission_amount', 'affiliate_commission_amount'],
        'marketer_commission_rules' => ['commission_flat_amount', 'commission_min_amount'],
        'marketer_category_commissions' => ['commission_flat_amount'],
        'marketer_campaigns' => ['max_commission_budget', 'commission_budget_spent', 'marketer_commission_amount', 'platform_commission_amount'],
        'marketer_campaign_tiered_rules' => ['commission_amount'],
        'marketer_campaign_conversions' => [
            'commission_amount', 'flash_sale_bonus_amount', 'platform_commission_amount', 'gross_commission_amount',
            'platform_cut_base_amount', 'platform_cut_discount_amount', 'platform_cut_amount', 'platform_cut_vat_amount',
            'net_commission_amount',
        ],
        'marketer_campaign_invitations' => ['platform_fee_amount', 'platform_fee_vat_amount', 'total_commission_earned'],
        'marketer_campaign_samples' => ['commission_amount'],
        'marketer_profiles' => ['story_price', 'post_price', 'video_price', 'ad_price', 'total_earnings', 'commission_discount_flat'],
        'marketers' => ['total_earnings'],
        'flash_sale_marketer_invitations' => ['extra_commission_flat_amount'],
        'promotion_category_targets' => ['penalty_amount'],
        'marketer_promotion_fines' => ['amount'],
    ];

    /** Ledger tables: every BIGINT money column is converted. */
    private const LEDGER_TABLES = [
        'wallets', 'wallet_transactions', 'wallet_withdrawal_requests',
        'payouts', 'payout_items', 'marketer_sale_settlements', 'ledger_entries',
    ];

    public function up(): void
    {
        foreach ($this->targets() as $table => $columns) {
            foreach ($columns as $column) {
                $this->modify($table, $column, 'DECIMAL(19,4)');
            }
        }
    }

    public function down(): void
    {
        foreach ($this->targets() as $table => $columns) {
            foreach ($columns as $column) {
                $this->modify($table, $column, 'BIGINT');
            }
        }
    }

    /** @return array<string, array<int, string>> */
    private function targets(): array
    {
        $targets = self::COLUMNS;

        foreach (self::LEDGER_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $targets[$table] = collect(DB::select(
                "SELECT column_name AS name FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ?
                   AND data_type IN ('bigint', 'decimal')
                   AND column_name NOT IN ('id') AND column_name NOT LIKE '%\\_id' AND column_name NOT LIKE '%count%'
                   AND column_name NOT LIKE '%rate' AND column_name NOT LIKE '%pct'",
                [$table]
            ))->pluck('name')->all();
        }

        return $targets;
    }

    private function modify(string $table, string $column, string $type): void
    {
        $info = DB::selectOne(
            'SELECT is_nullable AS nullable, column_default AS dflt, column_type AS ctype
             FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );

        $current = $info ? strtolower($info->ctype) : '';
        $convertible = $type === 'BIGINT' ? $current === 'decimal(19,4)' : str_starts_with($current, 'bigint');
        if (! $convertible) {
            return;
        }

        $nullable = $info->nullable === 'YES';
        $default = $info->dflt === null
            ? ($nullable ? ' DEFAULT NULL' : '')
            : ' DEFAULT '.(is_numeric($info->dflt) ? ($type === 'BIGINT' ? (int) $info->dflt : $info->dflt) : "'".addslashes($info->dflt)."'");

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} ".($nullable ? 'NULL' : 'NOT NULL').$default);
    }
};
