<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate marketing-fee accrual history: one row per approved conversion (unique = idempotent), with the
 * fee delta and the running totals. Reversal rows are negative deltas (status = reversed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketer_campaign_fee_accruals')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE `marketer_campaign_fee_accruals` (
                `id` CHAR(36) NOT NULL,
                `campaign_id` CHAR(36) NOT NULL,
                `invitation_id` CHAR(36) NOT NULL,
                `conversion_id` CHAR(36) NOT NULL,
                `order_id` CHAR(36) NOT NULL,
                `order_item_id` CHAR(36) NULL,
                `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
                `line_total` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `commission_base` DECIMAL(19,4) NOT NULL DEFAULT 0 COMMENT 'affiliate commission of the order item',
                `marketing_fixed_delta` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `marketing_variable_delta` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `marketing_total_delta` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `running_base` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `running_marketing_total` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `status` VARCHAR(10) NOT NULL DEFAULT 'accrued' COMMENT 'accrued | settled | reversed',
                `settled_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `mcfa_conversion_uniq` (`conversion_id`),
                KEY `mcfa_campaign_status_idx` (`campaign_id`,`status`),
                KEY `mcfa_invitation_idx` (`invitation_id`),
                KEY `mcfa_order_idx` (`order_id`),
                CONSTRAINT `mcfa_campaign_fk` FOREIGN KEY (`campaign_id`) REFERENCES `marketer_campaigns` (`id`) ON DELETE CASCADE,
                CONSTRAINT `mcfa_invitation_fk` FOREIGN KEY (`invitation_id`) REFERENCES `marketer_campaign_invitations` (`id`) ON DELETE CASCADE,
                CONSTRAINT `mcfa_conversion_fk` FOREIGN KEY (`conversion_id`) REFERENCES `marketer_campaign_conversions` (`id`) ON DELETE CASCADE,
                CONSTRAINT `mcfa_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_campaign_fee_accruals');
    }
};
