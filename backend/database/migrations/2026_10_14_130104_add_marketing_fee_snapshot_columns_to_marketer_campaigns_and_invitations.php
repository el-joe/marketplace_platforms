<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing fee snapshots. Campaign: the influencer quote frozen at creation (never recomputed) and the
 * affiliate accrual totals. Invitation: its marketing-fee share and the marketer fee-share payout.
 * Money = DECIMAL(19,4). Defaults keep every existing row unchanged (all zero / NULL).
 */
return new class extends Migration
{
    private const CAMPAIGN_COLUMNS = [
        'fulfillment_type' => "VARCHAR(10) NULL COMMENT 'fbm | fbn | fbp, snapshot at creation'",
        'campaign_subtotal' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
        'marketing_fee_fixed' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
        'marketing_fee_variable' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
        'marketing_fee_total' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
        'campaign_grand_total' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
        'affiliate_fee_accrued' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
        'affiliate_fee_settled_at' => 'TIMESTAMP NULL DEFAULT NULL',
        'marketing_fee_fixed_accrued' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
    ];

    private const INVITATION_COLUMNS = [
        'platform_fee_marketing_amount' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
        'marketer_fee_share_pct' => 'DECIMAL(7,4) NOT NULL DEFAULT 0',
        'marketer_payout_amount' => 'DECIMAL(19,4) NOT NULL DEFAULT 0',
        'marketer_credited_at' => 'TIMESTAMP NULL DEFAULT NULL',
        'marketer_payout_wallet_tx_id' => 'CHAR(36) NULL',
    ];

    public function up(): void
    {
        $this->add('marketer_campaigns', self::CAMPAIGN_COLUMNS);
        $this->add('marketer_campaign_invitations', self::INVITATION_COLUMNS);
    }

    public function down(): void
    {
        $this->drop('marketer_campaigns', self::CAMPAIGN_COLUMNS);
        $this->drop('marketer_campaign_invitations', self::INVITATION_COLUMNS);
    }

    /** @param array<string, string> $columns */
    private function add(string $table, array $columns): void
    {
        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
            }
        }
    }

    /** @param array<string, string> $columns */
    private function drop(string $table, array $columns): void
    {
        foreach (array_keys($columns) as $column) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
            }
        }
    }
};
