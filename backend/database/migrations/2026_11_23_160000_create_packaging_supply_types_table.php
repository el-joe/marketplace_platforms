<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Client item #12: packaging types become admin-managed rows instead of a fixed enum.
 *
 * - Creates packaging_supply_types, seeded with the former fixed codes/labels.
 * - Adds packaging_supplies.packaging_supply_type_id and maps every existing item by its current code.
 * - Keeps packaging_supplies.type (now VARCHAR instead of ENUM) holding the type code, so nothing that
 *   still reads it loses data. The old ENUM never contained 'bubble_wrap' although the UI offered it.
 */
return new class extends Migration
{
    /** @var list<array{code: string, name_en: string, name_ar: string}> */
    private const SEED_TYPES = [
        ['code' => 'box', 'name_en' => 'Box', 'name_ar' => 'صندوق'],
        ['code' => 'bag', 'name_en' => 'Bag', 'name_ar' => 'كيس'],
        ['code' => 'tape', 'name_en' => 'Tape', 'name_ar' => 'شريط لاصق'],
        ['code' => 'label', 'name_en' => 'Label', 'name_ar' => 'ملصق'],
        ['code' => 'bubble_wrap', 'name_en' => 'Bubble Wrap', 'name_ar' => 'فقاعات تغليف'],
        ['code' => 'other', 'name_en' => 'Other', 'name_ar' => 'أخرى'],
    ];

    public function up(): void
    {
        Schema::create('packaging_supply_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (self::SEED_TYPES as $index => $type) {
            DB::table('packaging_supply_types')->insert($type + [
                'id' => (string) Str::uuid(),
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('packaging_supplies', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });

        Schema::table('packaging_supplies', function (Blueprint $table) {
            $table->foreignUuid('packaging_supply_type_id')->nullable()->after('type')
                ->constrained('packaging_supply_types')->restrictOnDelete();
        });

        // Any code outside the seed list (defensive; the ENUM made this impossible) gets its own type row.
        $unknownCodes = DB::table('packaging_supplies')
            ->whereNotIn('type', array_column(self::SEED_TYPES, 'code'))
            ->distinct()
            ->pluck('type');

        foreach ($unknownCodes as $code) {
            DB::table('packaging_supply_types')->insert([
                'id' => (string) Str::uuid(),
                'code' => $code,
                'name_en' => Str::headline($code),
                'name_ar' => Str::headline($code),
                'is_active' => true,
                'sort_order' => 1000,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('packaging_supplies')
            ->join('packaging_supply_types', 'packaging_supply_types.code', '=', 'packaging_supplies.type')
            ->update(['packaging_supplies.packaging_supply_type_id' => DB::raw('packaging_supply_types.id')]);
    }

    public function down(): void
    {
        Schema::table('packaging_supplies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('packaging_supply_type_id');
        });

        Schema::dropIfExists('packaging_supply_types');

        // packaging_supplies.type intentionally stays VARCHAR: narrowing back to the ENUM could truncate custom codes.
    }
};
