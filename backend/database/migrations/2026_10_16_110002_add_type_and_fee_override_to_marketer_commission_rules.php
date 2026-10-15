<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R2): per-marketer commission rules become type-aware and carry the commission-fee override.
 *
 *  - marketer_type: influencer | affiliate | broker. Constraints by type (influencer = flat only,
 *    affiliate = percentage only, broker = any) are enforced in the application layer (R4) and, on MySQL,
 *    the value domain is guarded by a CHECK.
 *  - fee_percentage / fee_fixed: override of the default commission fee for this marketer + category
 *    (NULL = use marketer_default_commissions).
 *
 * Existing rows keep marketer_type NULL (legacy, type resolved from the marketer's job at read time).
 */
return new class extends Migration
{
    private const CHECK = 'chk_mcr_marketer_type';

    public function up(): void
    {
        if (! Schema::hasColumn('marketer_commission_rules', 'marketer_type')) {
            Schema::table('marketer_commission_rules', function (Blueprint $table) {
                $table->string('marketer_type', 20)->nullable()->after('marketer_id')->comment('influencer | affiliate | broker');
                $table->index('marketer_type', 'mcr_marketer_type_idx');
            });
        }

        if (! Schema::hasColumn('marketer_commission_rules', 'fee_percentage')) {
            Schema::table('marketer_commission_rules', function (Blueprint $table) {
                $table->decimal('fee_percentage', 7, 4)->nullable()->comment('Commission-fee % override; NULL = default');
            });
        }

        if (! Schema::hasColumn('marketer_commission_rules', 'fee_fixed')) {
            Schema::table('marketer_commission_rules', function (Blueprint $table) {
                $table->decimal('fee_fixed', 19, 4)->nullable()->comment('Commission-fixed-fee override; NULL = default');
            });
        }

        if ($this->isMysql() && ! $this->checkExists()) {
            DB::statement('ALTER TABLE `marketer_commission_rules` ADD CONSTRAINT `'.self::CHECK."` CHECK (`marketer_type` IS NULL OR `marketer_type` IN ('influencer','affiliate','broker'))");
        }
    }

    public function down(): void
    {
        if ($this->isMysql() && $this->checkExists()) {
            DB::statement('ALTER TABLE `marketer_commission_rules` DROP CONSTRAINT `'.self::CHECK.'`');
        }

        if (Schema::hasColumn('marketer_commission_rules', 'marketer_type')) {
            Schema::table('marketer_commission_rules', function (Blueprint $table) {
                $table->dropIndex('mcr_marketer_type_idx');
                $table->dropColumn('marketer_type');
            });
        }

        foreach (['fee_percentage', 'fee_fixed'] as $column) {
            if (Schema::hasColumn('marketer_commission_rules', $column)) {
                Schema::table('marketer_commission_rules', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function checkExists(): bool
    {
        return ! empty(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['marketer_commission_rules', self::CHECK]
        ));
    }
};
