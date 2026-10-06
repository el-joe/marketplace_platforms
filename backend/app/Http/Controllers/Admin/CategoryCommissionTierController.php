<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryCommissionTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryCommissionTierController extends Controller
{
    public function index(string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        return response()->json(['tiers' => $this->serialize($categoryModel)]);
    }

    public function store(Request $request, string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);
        $data = $this->validated($request, $categoryModel);

        $categoryModel->commissionTiers()->create($data);

        return response()->json(['tiers' => $this->serialize($categoryModel)]);
    }

    public function update(Request $request, string $category, string $tier): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);
        $tierModel = $categoryModel->commissionTiers()->findOrFail($tier);
        $data = $this->validated($request, $categoryModel, $tierModel);

        $tierModel->update($data);

        return response()->json(['tiers' => $this->serialize($categoryModel)]);
    }

    public function destroy(string $category, string $tier): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);
        $categoryModel->commissionTiers()->findOrFail($tier)->delete();

        return response()->json(['tiers' => $this->serialize($categoryModel)]);
    }

    /**
     * Validates one tier and rejects ranges that overlap a sibling tier
     * (ranges are [price_from, price_to) with NULL price_to = unbounded).
     */
    private function validated(Request $request, Category $category, ?CategoryCommissionTier $current = null): array
    {
        $data = $request->validate([
            'price_from' => ['required', 'integer', 'min:0'],
            'price_to' => ['nullable', 'integer', 'min:1', 'gt:price_from'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_commission' => ['nullable', 'integer', 'min:0'],
        ]);

        $from = (int) $data['price_from'];
        $to = isset($data['price_to']) ? (int) $data['price_to'] : null;

        $overlaps = $category->commissionTiers()
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->get()
            ->contains(fn (CategoryCommissionTier $t) => ($to === null || $t->price_from < $to)
                && ($t->price_to === null || $from < $t->price_to));

        if ($overlaps) {
            throw ValidationException::withMessages([
                'price_from' => __('admin.categories.tier_overlap'),
            ]);
        }

        return [
            'price_from' => $from,
            'price_to' => $to,
            'commission_rate' => $data['commission_rate'],
            'min_commission' => (int) ($data['min_commission'] ?? 0),
            'sort_order' => $from,
        ];
    }

    private function serialize(Category $category): array
    {
        return $category->commissionTiers()->get()->map(fn (CategoryCommissionTier $t) => [
            'id' => $t->id,
            'price_from' => $t->price_from,
            'price_to' => $t->price_to,
            'commission_rate' => $t->commission_rate,
            'min_commission' => $t->min_commission,
        ])->values()->all();
    }
}
