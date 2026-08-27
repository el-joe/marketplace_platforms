<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeRequest;
use App\Http\Requests\Admin\UpdateAttributeRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\CategoryAttribute;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Services\VariantSlugService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttributeController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index / Datatable
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.attributes.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.attributes')],
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = $this->columnDefinitions();

        $query = Attribute::query()->select([
            'id',
            'name_en',
            'name_ar',
            'code',
            'type',
            'unit',
            'is_variant_attribute',
            'is_filterable',
            'is_required',
            'sort_order',
            'created_at',
        ]);

        $query = $this->applyFilters($query, $request, [
            'type' => fn($q, $v) => $q->where('type', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'name_en' => e($row->name_en),
                'name_ar' => e($row->name_ar ?? ''),
                'code' => e($row->code),
                'type' => $row->type?->value,
                'unit' => e($row->unit ?? '—'),
                'is_variant_attribute' => (bool) $row->is_variant_attribute,
                'is_filterable' => (bool) $row->is_filterable,
                'created_at' => $row->created_at,
                'edit_url' => route('admin.attributes.edit', $row->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.attributes.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.attributes'), 'url' => route('admin.attributes.index')],
                ['label' => __('admin.attributes_section.new_attribute')],
            ],
        ]);
    }

    public function store(StoreAttributeRequest $request): JsonResponse|RedirectResponse
    {
        DB::beginTransaction();
        try {
            $id = (string) Str::uuid();

            Attribute::query()->insert([
                'id' => $id,
                'name_en' => $request->name_en,
                'name_ar' => $request->name_ar,
                'code' => $request->code,
                'type' => $request->type,
                'unit' => $request->unit ?: null,
                'is_variant_attribute' => $request->boolean('is_variant_attribute'),
                'is_filterable' => $request->boolean('is_filterable'),
                'is_required' => $request->boolean('is_required'),
                'sort_order' => (int) ($request->sort_order ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Seed initial values for types that require them
            if (in_array($request->type, ['select', 'multi_select', 'color'], true)) {
                foreach ($request->input('values', []) as $value) {
                    if (empty($value['value_en']))
                        continue;
                    $providedSlug = trim($value['slug'] ?? '');
                    $slug = ($providedSlug !== '' && preg_match('/^[a-z0-9-]+$/', $providedSlug) && ! AttributeValue::query()->where('slug', $providedSlug)->exists())
                        ? $providedSlug
                        : $this->uniqueSlug(Str::slug($value['value_en']));

                    AttributeValue::query()->insert([
                        'id' => (string) Str::uuid(),
                        'attribute_id' => $id,
                        'value_en' => $value['value_en'],
                        'value_ar' => $value['value_ar'] ?? null,
                        'slug' => $slug,
                        'code_hex' => $value['code_hex'] ?? null,
                        'sort_order' => (int) ($value['sort_order'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect' => route('admin.attributes.index'),
                ]);
            }

            return redirect()->route('admin.attributes.index')
                ->with('success', __('admin.attributes_section.created_success'));

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Attribute creation failed', ['error' => $e->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json(['message' => __('admin.attributes_section.create_failed')], 500);
            }

            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $attribute): View
    {
        $attributeModel = Attribute::with('values')->findOrFail($attribute);

        return view('admin.attributes.edit', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.attributes'), 'url' => route('admin.attributes.index')],
                ['label' => e($attributeModel->name_en)],
            ],
            'attribute' => $attributeModel,
        ]);
    }

    public function update(UpdateAttributeRequest $request, string $attribute): JsonResponse
    {
        Attribute::query()->where('id', $attribute)->firstOrFail();

        DB::beginTransaction();
        try {
            Attribute::query()->where('id', $attribute)->update([
                'name_en' => $request->name_en,
                'name_ar' => $request->name_ar,
                'unit' => $request->unit ?: null,
                'is_variant_attribute' => $request->boolean('is_variant_attribute'),
                'is_filterable' => $request->boolean('is_filterable'),
                'is_required' => $request->boolean('is_required'),
                'sort_order' => (int) ($request->sort_order ?? 0),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => __('admin.attributes_section.updated_success')]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Attribute update failed', ['id' => $attribute, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('admin.attributes_section.update_failed')], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(string $attribute): JsonResponse
    {
        $inUse = ProductVariantAttribute::query()
            ->from('product_variant_attributes as pva')
            ->join('attribute_values as av', 'av.id', '=', 'pva.attribute_value_id')
            ->where('av.attribute_id', $attribute)
            ->count();

        if ($inUse > 0) {
            return response()->json([
                'message' => trans_choice('admin.attributes_section.cannot_delete_in_use', $inUse, ['count' => $inUse]),
            ], 422);
        }

        AttributeValue::query()->where('attribute_id', $attribute)->delete();
        CategoryAttribute::query()->where('attribute_id', $attribute)->delete();
        Attribute::query()->where('id', $attribute)->delete();

        return response()->json(['success' => true, 'message' => __('admin.attributes_section.deleted_success')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Attribute Values (inline CRUD)
    // ─────────────────────────────────────────────────────────────────────────

    public function storeValue(Request $request, string $attribute): JsonResponse
    {
        $request->validate([
            'value_en' => 'required|string|max:255',
            'value_ar' => 'nullable|string|max:255',
            'code_hex' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('attribute_values', 'slug')],
        ]);

        Attribute::query()->where('id', $attribute)->firstOrFail();

        $id = (string) Str::uuid();
        $slug = $request->filled('slug')
            ? $request->slug
            : $this->uniqueSlug(Str::slug($request->value_en));

        AttributeValue::query()->insert([
            'id' => $id,
            'attribute_id' => $attribute,
            'value_en' => $request->value_en,
            'value_ar' => $request->value_ar ?: null,
            'slug' => $slug,
            'code_hex' => $request->code_hex ?: null,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'value' => [
                'id' => $id,
                'value_en' => $request->value_en,
                'value_ar' => $request->value_ar,
                'slug' => $slug,
                'color_hex' => $request->color_hex,
                'sort_order' => (int) ($request->sort_order ?? 0),
                'upload_swatch_url' => route('admin.attributes.values.upload-swatch', [$attribute, $id]),
                'delete_swatch_url' => route('admin.attributes.values.delete-swatch', [$attribute, $id]),
            ],
        ]);
    }

    public function updateValue(Request $request, string $attribute, string $value): JsonResponse
    {
        $request->validate([
            'value_en' => 'required|string|max:255',
            'value_ar' => 'nullable|string|max:255',
            'color_hex' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('attribute_values', 'slug')->ignore($value)],
        ]);

        $attributeValue = AttributeValue::query()
            ->where('id', $value)
            ->where('attribute_id', $attribute)
            ->firstOrFail();

        $slugChanged = $request->filled('slug') && $request->slug !== $attributeValue->slug;
        $slug = $request->filled('slug')
            ? $request->slug
            : ($attributeValue->slug ?: $this->uniqueSlug(Str::slug($request->value_en)));

        $attributeValue->update([
            'value_en' => $request->value_en,
            'value_ar' => $request->value_ar ?: null,
            'slug' => $slug,
            'color_hex' => $request->color_hex ?: null,
            'sort_order' => (int) ($request->sort_order ?? 0),
        ]);

        $affectedVariants = $slugChanged
            ? ProductVariantAttribute::query()->where('attribute_value_id', $value)->distinct('product_variant_id')->count('product_variant_id')
            : 0;

        return response()->json([
            'success' => true,
            'message' => __('admin.attributes_section.value_updated'),
            'slug' => $slug,
            'slug_changed' => $slugChanged,
            'affected_variants' => $affectedVariants,
        ]);
    }

    public function regenerateVariantSlugs(VariantSlugService $variantSlugService, string $attribute, string $value): JsonResponse
    {
        AttributeValue::query()
            ->where('id', $value)
            ->where('attribute_id', $attribute)
            ->firstOrFail();

        $variantIds = ProductVariantAttribute::query()
            ->where('attribute_value_id', $value)
            ->distinct()
            ->pluck('product_variant_id');

        $variants = ProductVariant::query()->whereIn('id', $variantIds)->get();

        $count = 0;
        foreach ($variants as $variant) {
            $newSlug = $variantSlugService->buildSlug($variant);
            if ($newSlug !== '') {
                $variant->update(['slug' => $newSlug]);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => trans_choice('admin.attributes_section.variant_slugs_regenerated', $count, ['count' => $count]),
            'count' => $count,
        ]);
    }

    public function destroyValue(string $attribute, string $value): JsonResponse
    {
        $inUse = ProductVariantAttribute::query()
            ->where('attribute_value_id', $value)
            ->count();

        if ($inUse > 0) {
            return response()->json([
                'message' => trans_choice('admin.attributes_section.cannot_delete_value_in_use', $inUse, ['count' => $inUse]),
            ], 422);
        }

        AttributeValue::query()
            ->where('id', $value)
            ->where('attribute_id', $attribute)
            ->delete();

        return response()->json(['success' => true, 'message' => __('admin.attributes_section.value_deleted')]);
    }

    public function reorderValues(Request $request, string $attribute): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|string',
            'items.*.sort_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($request, $attribute) {
            foreach ($request->input('items') as $item) {
                AttributeValue::query()
                    ->where('id', $item['id'])
                    ->where('attribute_id', $attribute)
                    ->update(['sort_order' => $item['sort_order'], 'updated_at' => now()]);
            }
        });

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Value Swatch Image Upload / Delete
    // ─────────────────────────────────────────────────────────────────────────

    public function uploadValueSwatch(Request $request, string $attribute, string $value): JsonResponse
    {
        $attributeValue = AttributeValue::query()
            ->where('id', $value)
            ->where('attribute_id', $attribute)
            ->firstOrFail();

        $request->validate([
            'swatch_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($attributeValue->swatch_image_path && str_contains($attributeValue->swatch_image_path, '/')) {
            Storage::disk('public')->delete($attributeValue->swatch_image_path);
        }

        $file = $request->file('swatch_image');
        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $path = $file->storeAs(
            'attribute-values/' . $attributeValue->id,
            'swatch_' . Str::random(8) . '.' . $ext,
            'public'
        );

        $attributeValue->update(['swatch_image_path' => $path]);

        return response()->json([
            'success' => true,
            'message' => __('admin.attributes_section.swatch_uploaded'),
            'swatch_image_url' => $attributeValue->fresh()->swatch_image_url,
        ]);
    }

    public function deleteValueSwatch(string $attribute, string $value): JsonResponse
    {
        $attributeValue = AttributeValue::query()
            ->where('id', $value)
            ->where('attribute_id', $attribute)
            ->firstOrFail();

        if (!$attributeValue->swatch_image_path) {
            return response()->json(['message' => __('admin.attributes_section.no_swatch_to_remove')], 404);
        }

        if (str_contains($attributeValue->swatch_image_path, '/')) {
            Storage::disk('public')->delete($attributeValue->swatch_image_path);
        }

        $attributeValue->update(['swatch_image_path' => null]);

        return response()->json(['success' => true, 'message' => __('admin.attributes_section.swatch_removed')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'value';
        $slug = $base;
        $counter = 1;

        while (AttributeValue::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function columnDefinitions(): array
    {
        return [
            ['name' => 'name_en', 'data' => 'name_en', 'title' => __('common.name')],
            ['name' => 'code', 'data' => 'code', 'title' => __('admin.attributes_section.code')],
            ['name' => 'type', 'data' => 'type', 'title' => __('admin.attributes_section.type'), 'searchable' => false],
            ['name' => 'unit', 'data' => 'unit', 'title' => __('admin.attributes_section.unit_col'), 'searchable' => false],
            ['name' => 'created_at', 'data' => 'created_at', 'title' => __('admin.created_at'), 'searchable' => false],
            ['name' => 'actions', 'data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }
}
