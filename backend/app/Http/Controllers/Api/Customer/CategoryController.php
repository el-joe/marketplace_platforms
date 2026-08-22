<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Services\ListingModeResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $isNawyNow = ListingModeResolver::isNawyNow($request);

        $categories = $this->baseQuery($isNawyNow)->get();

        $data = $isNawyNow
            ? $categories->map(fn (Category $category) => $this->formatMarketplace($category))->values()
            : $this->buildTree($categories);

        return ApiResponse::success($data);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $isNawyNow = ListingModeResolver::isNawyNow($request);

        $category = Category::whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('is_visible', 1)
            ->where('slug', $slug)
            ->firstOrFail();

        $children = $this->baseQuery($isNawyNow)
            ->where('parent_id', $category->id)
            ->get();

        $data = $this->formatMarketplace($category);
        $data['children'] = $children->map(fn (Category $child) => $this->formatMarketplace($child))->values();

        return ApiResponse::success($data);
    }

    private function baseQuery($isNawyNow)
    {
        $query = Category::whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('is_visible', 1);

        $query->orderBy('sort_order');

        return $query;
    }

    /**
     * @param Collection<int, Category> $categories
     * @return array<int, array>
     */
    private function buildTree(Collection $categories): array
    {
        $byParent = $categories->groupBy('parent_id');

        $build = function (?string $parentId) use (&$build, $byParent) {
            return $byParent->get($parentId, collect())
                ->map(function (Category $category) use (&$build) {
                    $data = $this->formatMarketplace($category);
                    $data['children'] = $build($category->id);
                    return $data;
                })
                ->values()
                ->all();
        };

        return $build(null);
    }

    private function formatMarketplace(Category $category): array
    {
        return [
            'id' => $category->id,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'slug' => $category->slug,
            'parent_id' => $category->parent_id,
            'depth' => $category->depth,
            'is_featured' => (bool) $category->is_featured,
        ];
    }

}
