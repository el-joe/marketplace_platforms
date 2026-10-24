<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-country Portal Content: adds portal_contents.country_code.
 *
 * '*' is the global base layer (every row that existed before this migration,
 * kept as fallback and as the template copied to each country). The unique key
 * becomes (country_code, page_key, block_key, field_key).
 *
 * Idempotent: safe to re-run on a partially-migrated database.
 */
return new class extends Migration
{
    private const OLD_UNIQUE = 'portal_contents_page_key_block_key_field_key_unique';

    private const NEW_UNIQUE = 'portal_contents_country_page_block_field_unique';

    private const NEW_INDEX = 'portal_contents_country_code_page_key_index';

    public function up(): void
    {
        if (! Schema::hasColumn('portal_contents', 'country_code')) {
            Schema::table('portal_contents', function (Blueprint $table) {
                $table->string('country_code', 20)->default('*')->after('id');
            });
        }

        if ($this->hasIndex(self::OLD_UNIQUE)) {
            Schema::table('portal_contents', fn (Blueprint $t) => $t->dropUnique(self::OLD_UNIQUE));
        }

        if (! $this->hasIndex(self::NEW_UNIQUE)) {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                // 4 x varchar(255) in utf8mb4 would exceed InnoDB's 3072-byte key limit,
                // so the three long keys are prefix-indexed (150 chars is far above any real key).
                DB::statement('ALTER TABLE `portal_contents` ADD UNIQUE `'.self::NEW_UNIQUE.'` (`country_code`, `page_key`(150), `block_key`(150), `field_key`(150))');
            } else {
                Schema::table('portal_contents', fn (Blueprint $t) => $t->unique(['country_code', 'page_key', 'block_key', 'field_key'], self::NEW_UNIQUE));
            }
        }

        if (! $this->hasIndex(self::NEW_INDEX)) {
            Schema::table('portal_contents', fn (Blueprint $t) => $t->index(['country_code', 'page_key'], self::NEW_INDEX));
        }
    }

    public function down(): void
    {
        // Country rows would violate the old unique key; drop them first.
        if (Schema::hasColumn('portal_contents', 'country_code')) {
            DB::table('portal_contents')->where('country_code', '!=', '*')->delete();
        }

        if ($this->hasIndex(self::NEW_INDEX)) {
            Schema::table('portal_contents', fn (Blueprint $t) => $t->dropIndex(self::NEW_INDEX));
        }
        if ($this->hasIndex(self::NEW_UNIQUE)) {
            Schema::table('portal_contents', fn (Blueprint $t) => $t->dropUnique(self::NEW_UNIQUE));
        }
        if (! $this->hasIndex(self::OLD_UNIQUE)) {
            Schema::table('portal_contents', fn (Blueprint $t) => $t->unique(['page_key', 'block_key', 'field_key'], self::OLD_UNIQUE));
        }
        if (Schema::hasColumn('portal_contents', 'country_code')) {
            Schema::table('portal_contents', fn (Blueprint $t) => $t->dropColumn('country_code'));
        }
    }

    private function hasIndex(string $name): bool
    {
        return Schema::hasIndex('portal_contents', $name);
    }
};
