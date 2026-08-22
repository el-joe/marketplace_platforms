<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RecomputeListingShippingMethodsJob;
use App\Models\Category;
use App\Models\CategoryShippingMethod;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryShippingMethodController extends Controller
{
    public function index(string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $assigned = CategoryShippingMethod::query()
            ->where('category_id', $categoryModel->id)
            ->with('shippingMethod')
            ->orderBy('display_priority')
            ->orderBy('created_at')
            ->get()
            ->map(fn (CategoryShippingMethod $csm) => [
                'id' => $csm->id,
                'shipping_method_id' => $csm->shipping_method_id,
                'name' => $csm->shippingMethod->name,
                'code' => $csm->shippingMethod->code,
                'is_default' => $csm->is_default,
                'is_available_for_express_fbn' => $csm->is_available_for_express_fbn,
                'is_available_for_merchant_fbp' => $csm->is_available_for_merchant_fbp,
                'display_priority' => $csm->display_priority,
            ]);

        $available = ShippingMethod::query()
            ->where('is_active', true)
            ->whereNotIn('id', $assigned->pluck('shipping_method_id'))
            ->orderBy('display_priority')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'methods' => $assigned,
            'available' => $available,
        ]);
    }

    public function store(Request $request, string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $data = $request->validate([
            'shipping_method_id' => [
                'required', 'uuid', 'exists:shipping_methods,id',
            ],
            'is_default' => 'boolean',
            'is_available_for_express_fbn' => 'boolean',
            'is_available_for_merchant_fbp' => 'boolean',
        ]);

        $exists = CategoryShippingMethod::query()
            ->where('category_id', $categoryModel->id)
            ->where('shipping_method_id', $data['shipping_method_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => __('admin.categories.shipping_method_already_assigned')], 422);
        }

        $isDefault = (bool) ($data['is_default'] ?? false);

        $csm = DB::transaction(function () use ($categoryModel, $data, $isDefault) {
            if ($isDefault) {
                CategoryShippingMethod::query()
                    ->where('category_id', $categoryModel->id)
                    ->update(['is_default' => false]);
            }

            $nextPriority = (int) (CategoryShippingMethod::query()
                ->where('category_id', $categoryModel->id)
                ->max('display_priority')) + 1;

            return CategoryShippingMethod::create([
                'category_id' => $categoryModel->id,
                'shipping_method_id' => $data['shipping_method_id'],
                'is_default' => $isDefault,
                'is_available_for_express_fbn' => $data['is_available_for_express_fbn'] ?? true,
                'is_available_for_merchant_fbp' => $data['is_available_for_merchant_fbp'] ?? false,
                'display_priority' => $nextPriority,
            ]);
        });

        $this->recompute($categoryModel);

        return response()->json(['success' => true, 'id' => $csm->id]);
    }

    public function update(Request $request, string $category, string $csm): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $record = CategoryShippingMethod::query()
            ->where('category_id', $categoryModel->id)
            ->findOrFail($csm);

        $data = $request->validate([
            'is_default' => 'boolean',
            'is_available_for_express_fbn' => 'boolean',
            'is_available_for_merchant_fbp' => 'boolean',
        ]);

        DB::transaction(function () use ($categoryModel, $record, $data) {
            if (array_key_exists('is_default', $data) && $data['is_default']) {
                CategoryShippingMethod::query()
                    ->where('category_id', $categoryModel->id)
                    ->where('id', '!=', $record->id)
                    ->update(['is_default' => false]);
            }

            $record->fill($data)->save();
        });

        $this->recompute($categoryModel);

        return response()->json(['success' => true]);
    }

    public function destroy(string $category, string $csm): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $record = CategoryShippingMethod::query()
            ->where('category_id', $categoryModel->id)
            ->findOrFail($csm);

        $record->delete();

        $this->recompute($categoryModel);

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid',
        ]);

        DB::transaction(function () use ($categoryModel, $data) {
            foreach ($data['ids'] as $priority => $id) {
                CategoryShippingMethod::query()
                    ->where('category_id', $categoryModel->id)
                    ->where('id', $id)
                    ->update(['display_priority' => $priority]);
            }
        });

        return response()->json(['success' => true]);
    }

    private function recompute(Category $category): void
    {
        if (class_exists(RecomputeListingShippingMethodsJob::class)) {
            dispatch(new RecomputeListingShippingMethodsJob([$category->id]));
        }
    }
}
