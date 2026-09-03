<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    private const MAX_FEATURED = 12;

    // ─── Commission Rate ──────────────────────────────────────────────────────

    public function updateCommissionRate(Category $category, float $newRate, ?string $adminId = null): void
    {
        $oldRate = (float) $category->commission_rate;

        $category->update(['commission_rate' => $newRate]);

        if (abs($oldRate - $newRate) > 0.001) {
            $this->logActivity('commission_changed', $category, $adminId, [
                'old' => $oldRate,
                'new' => $newRate,
            ]);
        }
    }

    public function bulkUpdateCommission(array $ids, float $rate, ?string $adminId = null): int
    {
        $categories = Category::whereIn('id', $ids)->get();

        foreach ($categories as $category) {
            $this->updateCommissionRate($category, $rate, $adminId);
        }

        return $categories->count();
    }

    // ─── Featured Toggle ──────────────────────────────────────────────────────

    public function setFeatured(Category $category, bool $featured, ?string $adminId = null): void
    {
        if ($featured) {
            $currentCount = Category::where('is_featured', true)->where('id', '!=', $category->id)->count();
            if ($currentCount >= self::MAX_FEATURED) {
                throw new \RuntimeException("Maximum of " . self::MAX_FEATURED . " featured categories allowed.");
            }
        }

        $category->update(['is_featured' => $featured]);

        $this->logActivity($featured ? 'featured' : 'unfeatured', $category, $adminId);
    }

    // ─── Visible Toggle ───────────────────────────────────────────────────────

    public function setVisible(Category $category, bool $visible, ?string $adminId = null): void
    {
        $category->update(['is_visible' => $visible]);
        $this->logActivity($visible ? 'made_visible' : 'hidden', $category, $adminId);
    }

    // ─── Delete Validation ────────────────────────────────────────────────────

    public function canDelete(Category $category): array
    {
        $descendantIds = $category->descendants()->pluck('id')->push($category->id);

        $productCount = DB::table('products')
            ->whereIn('category_id', $descendantIds)
            ->count();

        if ($productCount > 0) {
            return [
                'allowed' => false,
                'reason' => "Cannot delete: {$productCount} product(s) are assigned to this category or its subcategories.",
            ];
        }

        return ['allowed' => true];
    }

    public function delete(Category $category, ?string $adminId = null): void
    {
        $check = $this->canDelete($category);
        if (!$check['allowed']) {
            throw new \RuntimeException($check['reason']);
        }

        // Soft-delete all descendants first
        $category->descendants()->each(fn($child) => $child->delete());

        $category->delete();

        $this->logActivity('deleted', $category, $adminId);
    }

    // ─── Reorder ─────────────────────────────────────────────────────────────

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                DB::table('categories')
                    ->where('id', $item['id'])
                    ->update(['sort_order' => (int) ($item['sort_order'] ?? 0), 'updated_at' => now()]);
            }
        });
    }

    // ─── Attribute Assignment ─────────────────────────────────────────────────

    public function syncAttributes(Category $category, array $assignments): void
    {
        // $assignments: [['attribute_id' => '...', 'is_required' => true/false, 'sort_order' => 0], ...]
        DB::transaction(function () use ($category, $assignments) {
            DB::table('category_attributes')->where('category_id', $category->id)->delete();

            foreach ($assignments as $i => $assignment) {
                DB::table('category_attributes')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'category_id' => $category->id,
                    'attribute_id' => $assignment['attribute_id'],
                    'is_required' => (bool) ($assignment['is_required'] ?? false),
                    'sort_order' => (int) ($assignment['sort_order'] ?? $i),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function logActivity(string $event, Category $category, ?string $adminId, array $properties = []): void
    {
        DB::table('activity_log')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'log_name' => 'categories',
            'description' => $event,
            'subject_type' => Category::class,
            'subject_id' => $category->id,
            'causer_type' => $adminId ? \App\Models\Admin::class : null,
            'causer_id' => $adminId,
            'properties' => json_encode($properties),
            'event' => $event,
            'created_at' => now(),
        ]);
    }
}
