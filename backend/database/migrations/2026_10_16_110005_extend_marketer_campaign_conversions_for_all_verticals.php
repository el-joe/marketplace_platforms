<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R2): a conversion can now come from a product order, a classified sale or a travel booking.
 *
 * Already present (reused, not re-added): travel_booking_id, classified_inquiry_id, conversion_type,
 * net_commission_amount, reversed_at.
 *
 * Changes:
 *  - order_id becomes nullable (classified / travel conversions have no order).
 *  - conversion_type enum gains 'classified_sale'.
 *  - new: classified_listing_id, sale_amount (commission basis), commission_fee_amount (platform fee taken from the
 *    marketer), triggered_at (sale marked / booking confirmed), marketer_type and rule_source snapshots.
 */
return new class extends Migration
{
    private const TABLE = 'marketer_campaign_conversions';

    public function up(): void
    {
        if (Schema::hasColumn(self::TABLE, 'order_id') && ! $this->isNullable('order_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->char('order_id', 36)->nullable()->change();
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'classified_listing_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreignUuid('classified_listing_id')->nullable()->constrained('classified_listings')->nullOnDelete();
            });
        }

        $decimals = ['sale_amount' => 'Basis the commission was computed on', 'commission_fee_amount' => 'Platform commission fee taken from the marketer'];
        foreach ($decimals as $column => $comment) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->decimal($column, 19, 4)->nullable()->comment($comment));
            }
        }

        if (! Schema::hasColumn(self::TABLE, 'triggered_at')) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->timestamp('triggered_at')->nullable()->comment('Order placed / classified marked sold / booking confirmed'));
        }
        if (! Schema::hasColumn(self::TABLE, 'marketer_type')) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->string('marketer_type', 20)->nullable()->comment('influencer | affiliate | broker at conversion time'));
        }
        if (! Schema::hasColumn(self::TABLE, 'rule_source')) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->string('rule_source', 40)->nullable()->comment('profile_rule | default | manual'));
        }

        if ($this->isMysql()) {
            if (Schema::hasColumn(self::TABLE, 'conversion_type')) {
                DB::statement('ALTER TABLE `'.self::TABLE."` MODIFY `conversion_type` ENUM('product_sale','travel_booking','classified_inquiry','classified_sale') NOT NULL DEFAULT 'product_sale'");
            } else {
                DB::statement('ALTER TABLE `'.self::TABLE."` ADD `conversion_type` ENUM('product_sale','travel_booking','classified_inquiry','classified_sale') NOT NULL DEFAULT 'product_sale'");
            }
        }
    }

    public function down(): void
    {
        if ($this->isMysql() && Schema::hasColumn(self::TABLE, 'conversion_type')) {
            DB::statement('UPDATE `'.self::TABLE."` SET `conversion_type` = 'classified_inquiry' WHERE `conversion_type` = 'classified_sale'");
            DB::statement('ALTER TABLE `'.self::TABLE."` MODIFY `conversion_type` ENUM('product_sale','travel_booking','classified_inquiry') NOT NULL DEFAULT 'product_sale'");
        }

        if (Schema::hasColumn(self::TABLE, 'classified_listing_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropConstrainedForeignId('classified_listing_id');
            });
        }

        foreach (['sale_amount', 'commission_fee_amount', 'triggered_at', 'marketer_type', 'rule_source'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        // order_id stays nullable on rollback: restoring NOT NULL would fail once non-order conversions exist.
    }

    private function isNullable(string $column): bool
    {
        foreach (Schema::getColumns(self::TABLE) as $col) {
            if ($col['name'] === $column) {
                return (bool) $col['nullable'];
            }
        }

        return true;
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
