<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackagingSupplyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-managed packaging types (client item #12). Gated by `packaging.manage` via the route group.
 */
class PackagingSupplyTypeController extends Controller
{
    public function index(): View
    {
        $types = PackagingSupplyType::withCount('supplies')->ordered()->get();

        return view('admin.packaging.types', compact('types'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('packaging_supply_types', 'code')],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $data['code'] = $data['code'] ?? $this->uniqueCodeFrom($data['name_en']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? ((int) PackagingSupplyType::max('sort_order') + 10);

        $type = PackagingSupplyType::create($data);

        return response()->json([
            'success' => true,
            'message' => __('admin.packaging_types.created', ['name' => $type->localizedName()]),
            'data' => $this->present($type),
        ]);
    }

    public function update(Request $request, PackagingSupplyType $packagingSupplyType): JsonResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? $packagingSupplyType->sort_order;

        $packagingSupplyType->update($data);

        return response()->json([
            'success' => true,
            'message' => __('admin.packaging_types.updated', ['name' => $packagingSupplyType->localizedName()]),
            'data' => $this->present($packagingSupplyType),
        ]);
    }

    public function toggle(PackagingSupplyType $packagingSupplyType): JsonResponse
    {
        $packagingSupplyType->update(['is_active' => ! $packagingSupplyType->is_active]);

        return response()->json(['success' => true, 'is_active' => $packagingSupplyType->is_active]);
    }

    public function destroy(PackagingSupplyType $packagingSupplyType): JsonResponse
    {
        $inUse = $packagingSupplyType->supplies()->count();

        if ($inUse > 0) {
            return response()->json([
                'success' => false,
                'message' => __('admin.packaging_types.in_use', ['count' => $inUse]),
            ], 422);
        }

        $packagingSupplyType->delete();

        return response()->json(['success' => true, 'message' => __('admin.packaging_types.deleted')]);
    }

    /**
     * @return array{id: string, code: string, name_en: string, name_ar: string, name: string, is_active: bool, sort_order: int}
     */
    private function present(PackagingSupplyType $type): array
    {
        return [
            'id' => $type->id,
            'code' => $type->code,
            'name_en' => $type->name_en,
            'name_ar' => $type->name_ar,
            'name' => $type->localizedName(),
            'is_active' => $type->is_active,
            'sort_order' => $type->sort_order,
        ];
    }

    private function uniqueCodeFrom(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_'), 40, '') ?: 'type';
        $code = $base;
        $suffix = 2;

        while (PackagingSupplyType::where('code', $code)->exists()) {
            $code = $base.'_'.$suffix++;
        }

        return $code;
    }
}
