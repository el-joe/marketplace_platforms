<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedAttributeDefinition;
use App\Models\ClassifiedCategoryAttributeMap;
use App\Models\ClassifiedCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassifiedAttributeController extends Controller
{
    // ── Global attribute definitions ──────────────────────────────────────────

    public function index(): View
    {
        $definitions = ClassifiedAttributeDefinition::orderBy('sort_order')->orderBy('label_en')->get();
        return view('admin.classified-attributes.index', compact('definitions'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'               => ['required','string','max:60','unique:classified_attribute_definitions,code','regex:/^[a-z0-9_]+$/'],
            'label_en'           => 'required|string|max:120',
            'label_ar'           => 'required|string|max:120',
            'input_type'         => 'required|in:text,number,select,boolean',
            'options'            => 'nullable|array',
            'options.*.value'    => 'required_with:options|string|max:100',
            'options.*.label_en' => 'required_with:options|string|max:100',
            'options.*.label_ar' => 'required_with:options|string|max:100',
            'unit_en'            => 'nullable|string|max:30',
            'unit_ar'            => 'nullable|string|max:30',
            'sort_order'         => 'integer|min:0',
            'is_active'          => 'boolean',
        ]);
        $definition = ClassifiedAttributeDefinition::create($data);
        return response()->json(['message' => __('admin.classified_attributes.created'), 'definition' => $definition], 201);
    }

    public function update(Request $request, ClassifiedAttributeDefinition $definition): JsonResponse
    {
        $data = $request->validate([
            'code'               => ['required','string','max:60','regex:/^[a-z0-9_]+$/','unique:classified_attribute_definitions,code,'.$definition->id],
            'label_en'           => 'required|string|max:120',
            'label_ar'           => 'required|string|max:120',
            'input_type'         => 'required|in:text,number,select,boolean',
            'options'            => 'nullable|array',
            'options.*.value'    => 'required_with:options|string|max:100',
            'options.*.label_en' => 'required_with:options|string|max:100',
            'options.*.label_ar' => 'required_with:options|string|max:100',
            'unit_en'            => 'nullable|string|max:30',
            'unit_ar'            => 'nullable|string|max:30',
            'sort_order'         => 'integer|min:0',
            'is_active'          => 'boolean',
        ]);
        $definition->update($data);
        return response()->json(['message' => __('admin.classified_attributes.updated'), 'definition' => $definition->fresh()]);
    }

    public function destroy(ClassifiedAttributeDefinition $definition): JsonResponse
    {
        if ($definition->categoryMaps()->exists()) {
            return response()->json(['message' => __('admin.classified_attributes.cannot_delete_in_use')], 422);
        }
        $definition->delete();
        return response()->json(['message' => __('admin.classified_attributes.deleted')]);
    }

    // ── Per-category attribute mapping ────────────────────────────────────────

    public function categoryAttributes(ClassifiedCategory $category): JsonResponse
    {
        $maps = $category->attributeMap()->get()->map(fn ($m) => [
            'id'                                 => $m->id,
            'classified_attribute_definition_id' => $m->classified_attribute_definition_id,
            'code'             => $m->definition?->code,
            'label_en'         => $m->definition?->label_en,
            'label_ar'         => $m->definition?->label_ar,
            'input_type'       => $m->definition?->input_type,
            'is_required'      => $m->is_required,
            'is_shown_on_card' => $m->is_shown_on_card,
            'is_filterable'    => $m->is_filterable,
            'sort_order'       => $m->sort_order,
        ]);
        return response()->json(['data' => $maps]);
    }

    public function attachToCategory(Request $request, ClassifiedCategory $category): JsonResponse
    {
        $data = $request->validate([
            'classified_attribute_definition_id' => 'required|uuid|exists:classified_attribute_definitions,id',
            'is_required'      => 'boolean',
            'is_shown_on_card' => 'boolean',
            'is_filterable'    => 'boolean',
            'sort_order'       => 'integer|min:0',
        ]);

        $map = ClassifiedCategoryAttributeMap::updateOrCreate(
            [
                'classified_category_id'             => $category->id,
                'classified_attribute_definition_id' => $data['classified_attribute_definition_id'],
            ],
            [
                'is_required'      => $data['is_required'] ?? false,
                'is_shown_on_card' => $data['is_shown_on_card'] ?? false,
                'is_filterable'    => $data['is_filterable'] ?? false,
                'sort_order'       => $data['sort_order'] ?? 0,
            ]
        );

        return response()->json(['message' => __('admin.classified_attributes.attached'), 'map' => $map]);
    }

    public function updateCategoryAttribute(
        Request $request,
        ClassifiedCategory $category,
        ClassifiedCategoryAttributeMap $map,
    ): JsonResponse {
        $data = $request->validate([
            'is_required'      => 'boolean',
            'is_shown_on_card' => 'boolean',
            'is_filterable'    => 'boolean',
            'sort_order'       => 'integer|min:0',
        ]);
        $map->update($data);
        return response()->json(['message' => __('admin.classified_attributes.map_updated'), 'map' => $map->fresh()]);
    }

    public function detachFromCategory(
        ClassifiedCategory $category,
        ClassifiedCategoryAttributeMap $map,
    ): JsonResponse {
        $map->delete();
        return response()->json(['message' => __('admin.classified_attributes.detached')]);
    }

    public function reorderCategoryAttributes(Request $request, ClassifiedCategory $category): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'uuid']);
        \DB::transaction(function () use ($request) {
            foreach ($request->ids as $order => $id) {
                ClassifiedCategoryAttributeMap::where('id', $id)->update(['sort_order' => $order]);
            }
        });
        return response()->json(['message' => __('admin.classified_attributes.reordered')]);
    }
}
