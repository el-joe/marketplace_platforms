<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing fees, variable part: amount + subtotal x percentage / 100 per country x category x fulfilment
 * type (fbm = cross_dock, fbn, fbp = fbm). Lookup falls back to the nearest ancestor category.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_fee_category_settings')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE `marketing_fee_category_settings` (
                `id` CHAR(36) NOT NULL,
                `country_id` CHAR(36) NOT NULL,
                `category_id` CHAR(36) NOT NULL,
                `fulfillment_type` VARCHAR(10) NOT NULL COMMENT 'fbm | fbn | fbp',
                `fixed_amount` DECIMAL(19,4) NOT NULL DEFAULT 0,
                `percentage` DECIMAL(7,4) NOT NULL DEFAULT 0,
                `updated_by_admin_id` CHAR(36) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `marketing_fee_cat_uniq` (`country_id`,`category_id`,`fulfillment_type`),
                KEY `marketing_fee_cat_category_idx` (`category_id`),
                KEY `marketing_fee_cat_admin_idx` (`updated_by_admin_id`),
                CONSTRAINT `marketing_fee_cat_country_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
                CONSTRAINT `marketing_fee_cat_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
                CONSTRAINT `marketing_fee_cat_admin_fk` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_fee_category_settings');
    }
};
