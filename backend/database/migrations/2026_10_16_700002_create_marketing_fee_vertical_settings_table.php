<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign fee, variable part for the verticals that have no product category tree: classified and travel.
 * Product campaigns keep using marketing_fee_category_settings (country x category x fulfilment type).
 *
 * variable_fee = fixed_amount + budget x percentage / 100, per country and vertical. The country-wide fixed part
 * (marketing_fee_country_settings) is shared by all verticals.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_fee_vertical_settings')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE `marketing_fee_vertical_settings` (
                `id` CHAR(36) NOT NULL,
                `country_id` CHAR(36) NOT NULL,
                `vertical` VARCHAR(12) NOT NULL COMMENT 'classified | travel',
                `fixed_amount` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `percentage` DECIMAL(7,4) NOT NULL DEFAULT 0,
                `updated_by_admin_id` CHAR(36) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `marketing_fee_vertical_uniq` (`country_id`,`vertical`),
                KEY `marketing_fee_vertical_admin_idx` (`updated_by_admin_id`),
                CONSTRAINT `marketing_fee_vertical_country_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
                CONSTRAINT `marketing_fee_vertical_admin_fk` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_fee_vertical_settings');
    }
};
