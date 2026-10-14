<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate marketing-fee settlement (P6): one row per debit of the accrued fees of a campaign. It is paid from
 * the vendor wallet at once, or stays `pending` until the weekly statement picks it up as an
 * `affiliate_marketing_fee` line (payout_items.affiliate_fee_settlement_id is unique: never on two statements).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketer_campaign_fee_settlements')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `marketer_campaign_fee_settlements` (
                    `id` CHAR(36) NOT NULL,
                    `campaign_id` CHAR(36) NOT NULL,
                    `vendor_id` CHAR(36) NOT NULL,
                    `currency` VARCHAR(3) NOT NULL,
                    `amount` DECIMAL(19,4) NOT NULL DEFAULT 0,
                    `status` VARCHAR(10) NOT NULL DEFAULT 'pending' COMMENT 'pending | paid',
                    `paid_via` VARCHAR(12) NULL DEFAULT NULL COMMENT 'wallet | statement | admin',
                    `paid_at` TIMESTAMP NULL DEFAULT NULL,
                    `trigger` VARCHAR(30) NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `mcfs_campaign_idx` (`campaign_id`),
                    KEY `mcfs_vendor_status_idx` (`vendor_id`,`status`),
                    CONSTRAINT `mcfs_campaign_fk` FOREIGN KEY (`campaign_id`) REFERENCES `marketer_campaigns` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (! Schema::hasColumn('marketer_campaign_fee_accruals', 'settlement_id')) {
            DB::statement('ALTER TABLE `marketer_campaign_fee_accruals` ADD `settlement_id` CHAR(36) NULL DEFAULT NULL AFTER `settled_at`, ADD KEY `mcfa_settlement_idx` (`settlement_id`), ADD CONSTRAINT `mcfa_settlement_fk` FOREIGN KEY (`settlement_id`) REFERENCES `marketer_campaign_fee_settlements` (`id`) ON DELETE SET NULL');
        }

        // A reversal is a second history row of the same conversion (negative delta): unique per (conversion, entry type).
        if (! Schema::hasColumn('marketer_campaign_fee_accruals', 'entry_type')) {
            DB::statement("ALTER TABLE `marketer_campaign_fee_accruals` ADD `entry_type` VARCHAR(10) NOT NULL DEFAULT 'accrual' COMMENT 'accrual | reversal' AFTER `conversion_id`");
            DB::statement('ALTER TABLE `marketer_campaign_fee_accruals` ADD UNIQUE KEY `mcfa_conversion_entry_uniq` (`conversion_id`,`entry_type`)');
            DB::statement('ALTER TABLE `marketer_campaign_fee_accruals` DROP INDEX `mcfa_conversion_uniq`');
        }

        if (! Schema::hasColumn('payout_items', 'affiliate_fee_settlement_id')) {
            DB::statement("ALTER TABLE `payout_items` MODIFY COLUMN `item_type` ENUM('sub_order','marketer_commission','promotion_sample','influencer_fee','affiliate_marketing_fee') NOT NULL DEFAULT 'sub_order'");
            DB::statement('ALTER TABLE `payout_items` ADD `affiliate_fee_settlement_id` CHAR(36) NULL DEFAULT NULL AFTER `invitation_id`, ADD UNIQUE KEY `payout_items_affiliate_fee_settlement_unique` (`affiliate_fee_settlement_id`), ADD CONSTRAINT `payout_items_affiliate_fee_settlement_fk` FOREIGN KEY (`affiliate_fee_settlement_id`) REFERENCES `marketer_campaign_fee_settlements` (`id`) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketer_campaign_fee_accruals', 'entry_type')) {
            DB::table('marketer_campaign_fee_accruals')->where('entry_type', 'reversal')->delete();
            DB::statement('ALTER TABLE `marketer_campaign_fee_accruals` ADD UNIQUE KEY `mcfa_conversion_uniq` (`conversion_id`)');
            DB::statement('ALTER TABLE `marketer_campaign_fee_accruals` DROP INDEX `mcfa_conversion_entry_uniq`, DROP COLUMN `entry_type`');
        }
        if (Schema::hasColumn('payout_items', 'affiliate_fee_settlement_id')) {
            DB::table('payout_items')->where('item_type', 'affiliate_marketing_fee')->delete();
            DB::statement('ALTER TABLE `payout_items` DROP FOREIGN KEY `payout_items_affiliate_fee_settlement_fk`, DROP INDEX `payout_items_affiliate_fee_settlement_unique`, DROP COLUMN `affiliate_fee_settlement_id`');
            DB::statement("ALTER TABLE `payout_items` MODIFY COLUMN `item_type` ENUM('sub_order','marketer_commission','promotion_sample','influencer_fee') NOT NULL DEFAULT 'sub_order'");
        }
        if (Schema::hasColumn('marketer_campaign_fee_accruals', 'settlement_id')) {
            DB::statement('ALTER TABLE `marketer_campaign_fee_accruals` DROP FOREIGN KEY `mcfa_settlement_fk`, DROP INDEX `mcfa_settlement_idx`, DROP COLUMN `settlement_id`');
        }
        Schema::dropIfExists('marketer_campaign_fee_settlements');
    }
};
