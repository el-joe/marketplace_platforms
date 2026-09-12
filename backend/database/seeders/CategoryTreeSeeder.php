<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Builds/updates the category tree from backend/categories.json.
 *
 * The JSON is a list of top-level nodes; each node has a `category` object
 * ({ar, en}) and a `children` array. Children may themselves be full nodes
 * (`category` + `children`) or leaves (just {ar, en}). Nodes are matched
 * against existing rows by (parent_id, name_en) so re-running this seeder
 * updates names/sort order on existing categories instead of duplicating them.
 */
class CategoryTreeSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('categories.json');

        if (!file_exists($path)) {
            $this->command?->warn("categories.json not found at {$path}, skipping.");
            return;
        }

        $tree = json_decode(file_get_contents($path), true) ?? [];

        Category::fixTree();

        foreach ($tree as $index => $node) {
            $this->upsertNode($node, null, $index);
        }

        Category::fixTree();
    }

    private function upsertNode(array $node, ?Category $parent, int $sortOrder): Category
    {
        $names = $node['category'] ?? $node;
        $nameEn = trim($names['en'] ?? '');
        $nameAr = trim($names['ar'] ?? '');
        $children = $node['children'] ?? [];

        if ($nameEn === '') {
            return $parent ?? new Category();
        }

        $category = Category::query()
            ->where('parent_id', $parent?->id)
            ->where('name_en', $nameEn)
            ->first();

        if ($category) {
            $category->fill([
                'name_ar' => $nameAr,
                'sort_order' => $sortOrder,
            ])->save();
        } else {
            $slug = $this->uniqueSlug($nameEn);

            $category = new Category([
                'id' => (string) Str::uuid(),
                'parent_id' => $parent?->id,
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'slug' => $slug,
                'commission_rate' => $parent?->commission_rate ?? 0,
                'commission_fbp_pct' => $parent?->commission_fbp_pct ?? 0,
                'commission_fbp_fixed' => $parent?->commission_fbp_fixed ?? 0,
                'commission_fbn_pct' => $parent?->commission_fbn_pct ?? 0,
                'commission_fbn_fixed' => $parent?->commission_fbn_fixed ?? 0,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'is_visible' => true,
            ]);

            if ($parent) {
                $category->appendToNode($parent)->save();
            } else {
                $category->save();
            }
        }

        Slug::upsertFor($category, $category->slug);

        foreach ($children as $childIndex => $childNode) {
            $this->upsertNode($childNode, $category, $childIndex);
        }

        return $category;
    }

    private function uniqueSlug(string $nameEn): string
    {
        $base = Str::slug($nameEn) ?: 'category';
        $slug = $base;
        $i = 1;

        while (Slug::isTaken($slug) || Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
