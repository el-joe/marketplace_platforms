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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('variant_name');
        });

        $this->populateSlugs();

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['product_id', 'slug'], 'product_variants_product_id_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('product_variants_product_id_slug_unique');
            $table->dropColumn('slug');
        });
    }

    /**
     * Populate the slug column for existing rows.
     *
     * Slug is built from the variant's attribute values (joined by "-"),
     * falling back to the variant_name, and finally the SKU. Duplicate
     * slugs within the same product_id get a numeric suffix.
     */
    private function populateSlugs(): void
    {
        $usedSlugsByProduct = [];

        DB::table('product_variants')
            ->select('id', 'product_id', 'sku', 'variant_name')
            ->orderBy('id')
            ->chunkById(500, function ($variants) use (&$usedSlugsByProduct) {
                foreach ($variants as $variant) {
                    $attributeValues = DB::table('product_variant_attributes')
                        ->join('attribute_values', 'attribute_values.id', '=', 'product_variant_attributes.attribute_value_id')
                        ->where('product_variant_attributes.product_variant_id', $variant->id)
                        ->orderBy('product_variant_attributes.attribute_id')
                        ->pluck('attribute_values.value_en')
                        ->all();

                    if (! empty($attributeValues)) {
                        $base = $this->slugify(implode('-', $attributeValues));
                    } elseif (! empty($variant->variant_name)) {
                        $base = $this->slugify($variant->variant_name);
                    } else {
                        $base = $this->slugify($variant->sku);
                    }

                    $usedSlugsByProduct[$variant->product_id] ??= [];
                    $slug = $this->uniqueSlug($base, $usedSlugsByProduct[$variant->product_id]);
                    $usedSlugsByProduct[$variant->product_id][$slug] = true;

                    DB::table('product_variants')
                        ->where('id', $variant->id)
                        ->update(['slug' => $slug]);
                }
            });
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');

        return $value !== '' ? $value : 'variant';
    }

    private function uniqueSlug(string $base, array $usedInProduct): string
    {
        if (! isset($usedInProduct[$base])) {
            return $base;
        }

        $suffix = 2;
        while (isset($usedInProduct["{$base}-{$suffix}"])) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }
};
