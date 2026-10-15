<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R9, plan section 4.2 + owner answers Q9-Q15): PERMANENT removal of everything the new model replaced.
 *
 * Before anything is dropped:
 *   1. conversions approved before v3 get their old platform cut (+ VAT) folded into the new `commission_fee_amount`
 *      column (fee = gross - net), so every statement reads the same way after `platform_cut_*` is gone;
 *   2. every table / column that held money or fee history is written as JSON to storage/app/v3-removal/ (one file per
 *      object, only when it has rows) so finance can still audit what was removed. Take a database backup anyway.
 *
 * Dropped tables: legacy category-commission mirrors, tiered / category campaign rules, per-order fee accruals and
 * settlements, commission / influencer-fee country settings, promotion targets, fines and reminders.
 * Dropped columns: see COLUMNS below. Foreign keys are dropped before their columns / tables, and every step is
 * guarded by hasTable / hasColumn so the migration can be re-run safely.
 *
 * KEPT on purpose (still read by live code): marketer_campaigns.commission_type and marketer_commission_amount
 * (peer-promotion pricing in CheckoutPricingEngine), invitations' pre-v3 platform_fee_* fee + VAT history,
 * payout_items.marketing_fee_amount (carries the campaign fee line), marketer_sale_settlements (Q13).
 *
 * Irreversible: down() is a deliberate no-op.
 */
return new class extends Migration
{
    private const TABLES = [
        'marketer_campaign_fee_accruals',      // before settlements: accruals point at them
        'marketer_campaign_fee_settlements',
        'marketer_campaign_tiered_rules',
        'marketer_campaign_category_rules',
        'marketer_category_commissions',
        'open_market_category_commissions',
        'marketer_commission_country_settings',
        'marketer_influencer_fee_country_settings',
        'promotion_category_targets',
        'marketer_promotion_fines',
        'marketer_promotion_reminders',
    ];

    private const COLUMNS = [
        'marketer_profiles' => [
            'platform_cut_percentage',
            'commission_discount_type', 'commission_discount_flat', 'commission_discount_percentage', 'commission_discount_notes',
            'promotion_category', 'monthly_promotion_target',
        ],
        'admin_listings' => ['promotion_category'],
        'marketer_campaign_invitations' => [
            'platform_fee_marketing_amount', 'marketer_fee_share_pct', 'marketer_payout_amount',
            'marketer_credited_at', 'marketer_payout_wallet_tx_id',
            'platform_fee_refunded_at', 'platform_fee_refund_amount', 'platform_fee_refund_wallet_tx_id',
            'platform_fee_voided_at', 'platform_fee_refund_reason',
            'marketer_credit_reversed_at', 'marketer_credit_reversal_wallet_tx_id', 'marketer_clawback_amount',
        ],
        'marketer_campaign_conversions' => [
            'tiered_rule_id', 'sale_number_in_campaign', 'platform_commission_amount',
            'flash_sale_id', 'flash_sale_bonus_amount',
            'platform_cut_percentage', 'platform_cut_base_amount', 'platform_cut_discount_amount',
            'platform_cut_amount', 'platform_cut_vat_rate', 'platform_cut_vat_amount',
        ],
        'marketer_campaigns' => [
            'affiliate_fee_accrued', 'affiliate_fee_settled_at', 'marketing_fee_fixed_accrued',
            'product_category_selection_mode', 'classified_category_selection_mode',
            'platform_commission_amount',
        ],
        'payout_items' => ['affiliate_fee_settlement_id'],
        'flash_sale_marketer_invitations' => ['extra_commission_rate', 'extra_commission_mode', 'extra_commission_flat_amount'],
    ];

    private const SETTINGS = [
        'marketer_influencer_fee_payer',
        'marketer_promotion_reminder_days',
    ];

    public function up(): void
    {
        $this->foldLegacyPlatformCutIntoCommissionFee();
        $this->snapshotRemovedData();

        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::COLUMNS as $table => $columns) {
                $this->dropColumns($table, $columns);
            }
            foreach (self::TABLES as $table) {
                $this->dropTable($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->whereIn('key', self::SETTINGS)->delete();
            if (class_exists(\App\Models\Setting::class)) {
                \App\Models\Setting::flush();
            }
        }
    }

    public function down(): void
    {
        // Irreversible removal (v3 refactor R9): dropped tables and columns are not recreated.
    }

    /** Old conversions: commission_fee_amount = gross - net, so the platform cut + VAT keep showing as "platform fee". */
    private function foldLegacyPlatformCutIntoCommissionFee(): void
    {
        $t = 'marketer_campaign_conversions';
        if (! Schema::hasTable($t) || ! Schema::hasColumn($t, 'platform_cut_amount') || ! Schema::hasColumn($t, 'commission_fee_amount')) {
            return;
        }

        DB::table($t)
            ->whereNull('commission_fee_amount')
            ->whereNotNull('net_commission_amount')
            ->update([
                'commission_fee_amount' => DB::raw('COALESCE(gross_commission_amount, commission_amount) - net_commission_amount'),
            ]);
    }

    /** One JSON file per object that held money / fee history and still has rows. */
    private function snapshotRemovedData(): void
    {
        $directory = storage_path('app/v3-removal');
        $stamp = now()->format('Ymd_His');

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->snapshot($directory, $stamp, $table, DB::table($table));
        }

        $conversions = 'marketer_campaign_conversions';
        if (Schema::hasColumn($conversions, 'platform_cut_amount')) {
            $this->snapshot($directory, $stamp, $conversions, DB::table($conversions)->whereNotNull('platform_cut_amount')
                ->select(['id', 'platform_cut_percentage', 'platform_cut_base_amount', 'platform_cut_discount_amount', 'platform_cut_amount', 'platform_cut_vat_rate', 'platform_cut_vat_amount', 'gross_commission_amount', 'net_commission_amount']));
        }

        $invitations = 'marketer_campaign_invitations';
        $present = array_values(array_filter(self::COLUMNS[$invitations], fn ($c) => Schema::hasColumn($invitations, $c)));
        if ($present !== [] && Schema::hasColumn($invitations, 'marketer_payout_amount')) {
            $this->snapshot($directory, $stamp, $invitations, DB::table($invitations)
                ->where(fn ($q) => $q->where('marketer_payout_amount', '>', 0)->orWhere('platform_fee_marketing_amount', '>', 0)->orWhere('marketer_clawback_amount', '>', 0))
                ->select(array_merge(['id', 'campaign_id', 'marketer_id'], $present)));
        }

        $profiles = 'marketer_profiles';
        if (Schema::hasColumn($profiles, 'platform_cut_percentage')) {
            $this->snapshot($directory, $stamp, $profiles, DB::table($profiles)
                ->where(fn ($q) => $q->where('platform_cut_percentage', '>', 0)->orWhereNotNull('commission_discount_type'))
                ->select(['id', 'marketer_id', 'platform_cut_percentage', 'commission_discount_type', 'commission_discount_flat', 'commission_discount_percentage', 'commission_discount_notes']));
        }
    }

    private function snapshot(string $directory, string $stamp, string $name, \Illuminate\Database\Query\Builder $query): void
    {
        $rows = $query->get();
        if ($rows->isEmpty()) {
            return;
        }

        File::ensureDirectoryExists($directory);
        $path = "{$directory}/{$name}_{$stamp}.json";
        File::put($path, json_encode([
            'dumped_at' => now()->toIso8601String(),
            'note' => 'Removed by migration 2026_10_16_900001 (v3 marketer types / commissions refactor).',
            'rows' => $rows->map(fn ($r) => (array) $r)->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::info("v3 removal: {$rows->count()} row(s) of {$name} saved to {$path}");
    }

    /** @param array<int, string> $columns */
    private function dropColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
        if ($columns === []) {
            return;
        }

        // Foreign keys on these columns go first (MySQL refuses to drop a column that carries one).
        foreach (Schema::getForeignKeys($table) as $fk) {
            if (array_intersect($fk['columns'], $columns)) {
                Schema::table($table, fn ($t) => $t->dropForeign($fk['name']));
            }
        }

        // SQLite cannot drop an indexed column; MySQL removes the column from its indexes by itself.
        if (DB::getDriverName() === 'sqlite') {
            foreach (Schema::getIndexes($table) as $index) {
                if (! $index['primary'] && array_intersect($index['columns'], $columns)) {
                    Schema::table($table, fn ($t) => $t->dropIndex($index['name']));
                }
            }
        }

        Schema::table($table, fn ($t) => $t->dropColumn($columns));
    }

    private function dropTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        // Child tables that still hold a foreign key to this table lose it first.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $refs = DB::select(
                'SELECT TABLE_NAME AS t, CONSTRAINT_NAME AS c FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?',
                [$table]
            );
            foreach ($refs as $ref) {
                DB::statement("ALTER TABLE `{$ref->t}` DROP FOREIGN KEY `{$ref->c}`");
            }
        }

        Schema::drop($table);
    }
};
