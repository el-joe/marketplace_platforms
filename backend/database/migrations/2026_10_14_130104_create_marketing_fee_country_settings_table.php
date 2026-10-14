<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing fees, fixed part (per country): fixed_amount + subtotal x fixed_percentage / 100.
 * Money columns are DECIMAL(19,4) like the rest of the marketer fee chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_fee_country_settings')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE `marketing_fee_country_settings` (
                `id` CHAR(36) NOT NULL,
                `country_id` CHAR(36) NOT NULL,
                `fixed_amount` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `fixed_percentage` DECIMAL(7,4) NOT NULL DEFAULT 0,
                `updated_by_admin_id` CHAR(36) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `marketing_fee_country_uniq` (`country_id`),
                KEY `marketing_fee_country_admin_idx` (`updated_by_admin_id`),
                CONSTRAINT `marketing_fee_country_country_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
                CONSTRAINT `marketing_fee_country_admin_fk` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_fee_country_settings');
    }
};
