<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->after('value_en');
        });

        $this->populateSlugs();

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->unique('slug', 'attribute_values_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropUnique('attribute_values_slug_unique');
            $table->dropColumn('slug');
        });
    }

    /**
     * Populate the slug column for existing rows.
     *
     * slug = lowercased value_en, non-alphanumeric characters (except "-")
     * stripped, spaces collapsed to "-". Global duplicates get a numeric
     * suffix since attribute_values_slug_unique is a global unique index.
     */
    private function populateSlugs(): void
    {
        $usedSlugs = [];

        DB::table('attribute_values')
            ->select('id', 'value_en')
            ->orderBy('id')
            ->chunkById(500, function ($values) use (&$usedSlugs) {
                foreach ($values as $value) {
                    $base = $this->slugify($value->value_en);
                    $slug = $this->uniqueSlug($base, $usedSlugs);
                    $usedSlugs[$slug] = true;

                    DB::table('attribute_values')
                        ->where('id', $value->id)
                        ->update(['slug' => $slug]);
                }
            });
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        $value = trim($value, '-');

        return $value !== '' ? $value : 'value';
    }

    private function uniqueSlug(string $base, array $usedSlugs): string
    {
        if (! isset($usedSlugs[$base])) {
            return $base;
        }

        $suffix = 2;
        while (isset($usedSlugs["{$base}-{$suffix}"])) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }
};
