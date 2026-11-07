<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassifiedPlacement;
use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\Country;
use App\Models\File;
use App\Services\ActivityLoggerService;
use App\Services\Vendor\ContractAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin management of classified categories (the storefront sections).
 *
 * Deactivating is the everyday way to take a category off the site: it and everything below it disappear from
 * the storefront while every listing, signed contract and enrolment stays exactly as it was, and activating it
 * again restores all of it. Deleting is only for unused categories; anything that references the category
 * (children, listings, wanted ads, signatures, enrolments, exclusive contracts, prices) blocks it.
 */
class ClassifiedCategoryController extends Controller
{
    /** Slugs that would collide with fixed storefront routes under /classified. */
    private const RESERVED_SLUGS = ['all', 'find', 'sections', 'cities', 'map-pins'];

    public function __construct(private readonly ActivityLoggerService $activity) {}

    public function index(): View
    {
        $roots = ClassifiedCategory::with([
            'primaryImage', 'countries:id,name_en,name_ar,iso_code_2',
            'children.contractTemplate', 'children.primaryImage', 'children.countries:id,name_en,name_ar,iso_code_2',
            'contractTemplate', 'contractAssignments', 'children.contractAssignments',
            'contractAssignments.contractTemplate:id,name,version,is_published,is_active',
            'contractAssignments.country:id,name_en,name_ar',
            'children.contractAssignments.contractTemplate:id,name,version,is_published,is_active',
            'children.contractAssignments.country:id,name_en,name_ar',
        ])
            ->withCount(['listings as active_listing_count' => fn ($q) => $q->where('status', 'active')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $roots->each(function ($root) {
            $root->children->each(function ($child) {
                $child->loadCount(['listings as active_listing_count' => fn ($q) => $q->where('status', 'active')]);
            });
        });

        $templates = ClassifiedContractTemplate::forScope('classified')->enforceable()->orderBy('name')->get();
        $contractData = app(ContractAssignmentService::class)->formData('classified');
        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'iso_code_2']);
        $placements = ClassifiedPlacement::cases();
        $countryDefaultTemplates = ClassifiedContractTemplate::forScope('classified')
            ->enforceable()
            ->where('is_country_default', true)
            ->whereNotNull('country_id')
            ->with('country:id,name_en,name_ar')
            ->orderByDesc('version')
            ->get(['id', 'name', 'version', 'country_id', 'is_published', 'is_active'])
            ->unique('country_id')
            ->keyBy('country_id');

        return view('admin.classified-categories.index', compact('roots', 'templates', 'contractData', 'countries', 'placements', 'countryDefaultTemplates'));
    }

    public function store(Request $request): JsonResponse
    {
        $contractAssignments = app(ContractAssignmentService::class);
        $validated = $request->validate($this->rules());

        $contractInput = Arr::only($validated, ['requires_contract', 'contract_template_id', 'country_contracts']);
        $contractAssignments->assertConfigurable('classified', $contractInput);

        $attributes = $this->attributes($validated, $request);
        $attributes['slug'] = $validated['slug'] ?? $this->uniqueSlug($validated['name_en']);

        if (! empty($validated['parent_id']) && $this->wouldCreateCycle(null, $validated['parent_id'])) {
            return response()->json(['message' => __('admin.classified_categories.parent_cycle')], 422);
        }

        $category = DB::transaction(function () use ($request, $validated, $attributes) {
            $category = ClassifiedCategory::create($attributes);
            $this->syncCountries($category, $request, $validated);
            $this->storeImage($category, $request);

            return $category;
        });

        $gaps = $contractAssignments->apply($category, 'classified', $contractInput);
        ClassifiedCategory::flushStorefrontCaches();
        $this->audit('classified_category.created', $category, ['slug' => $category->slug]);

        return response()->json([
            'message' => __('admin.classified_categories.created'),
            'warning' => $this->gapWarning($gaps),
            'category' => $category->load('contractTemplate'),
        ], 201);
    }

    public function update(Request $request, ClassifiedCategory $category): JsonResponse
    {
        $contractAssignments = app(ContractAssignmentService::class);
        $validated = $request->validate($this->rules($category));

        if (! empty($validated['parent_id']) && $this->wouldCreateCycle($category->id, $validated['parent_id'])) {
            return response()->json(['message' => __('admin.classified_categories.parent_cycle')], 422);
        }

        $contractInput = Arr::only($validated, ['requires_contract', 'contract_template_id', 'country_contracts']);
        $contractAssignments->assertConfigurable('classified', $contractInput, $category);

        $attributes = $this->attributes($validated, $request);

        if (empty($validated['slug'])) {
            unset($attributes['slug']);
        }

        $before = Arr::only($category->getAttributes(), ['slug', 'is_active', 'parent_id', 'placements']);

        DB::transaction(function () use ($request, $validated, $attributes, $category) {
            $category->update($attributes);
            $this->syncCountries($category, $request, $validated);
            $this->storeImage($category, $request);
        });

        $gaps = $contractAssignments->apply($category, 'classified', $contractInput);
        ClassifiedCategory::flushStorefrontCaches();
        $this->audit('classified_category.updated', $category, ['before' => $before, 'after' => Arr::only($category->fresh()->getAttributes(), array_keys($before))]);

        return response()->json([
            'message' => __('admin.classified_categories.updated'),
            'warning' => $this->gapWarning($gaps),
            'category' => $category->load('contractTemplate'),
        ]);
    }

    public function destroy(ClassifiedCategory $category): JsonResponse
    {
        $blockers = $category->deletionBlockers();

        if ($blockers !== []) {
            return response()->json([
                'message' => $this->blockedMessage($blockers),
                'blockers' => $blockers,
            ], 422);
        }

        DB::transaction(function () use ($category) {
            $category->files()->get()->each->delete();
            $category->countries()->detach();
            $category->delete();
        });

        $this->audit('classified_category.deleted', $category, ['slug' => $category->slug]);

        return response()->json(['message' => __('admin.classified_categories.deleted')]);
    }

    /**
     * Shows or hides the category (and everything below it) on the storefront. Nothing is deleted.
     */
    public function toggleActive(ClassifiedCategory $category): JsonResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        $statusLabel = $category->is_active
            ? __('admin.classified_categories.activated')
            : __('admin.classified_categories.deactivated');

        $this->audit($category->is_active ? 'classified_category.activated' : 'classified_category.deactivated', $category);

        return response()->json([
            'message' => __('admin.classified_categories.status_toggled', ['status' => $statusLabel]),
            'is_active' => $category->is_active,
            'hint' => $category->is_active ? null : __('classified.admin.deactivated_hint'),
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $order => $id) {
                ClassifiedCategory::where('id', $id)->update(['sort_order' => $order]);
            }
        });

        ClassifiedCategory::flushStorefrontCaches();

        return response()->json(['message' => __('admin.classified_categories.order_saved')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?ClassifiedCategory $category = null): array
    {
        return [
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'slug' => [
                'nullable', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('classified_categories', 'slug')->ignore($category?->id),
                Rule::notIn(self::RESERVED_SLUGS),
                fn (string $attribute, mixed $value, \Closure $fail) => Str::isUuid((string) $value) ? $fail(__('classified.admin.slug_uuid')) : null,
            ],
            'icon' => 'nullable|string|max:50',
            'description_en' => 'nullable|string|max:500',
            'description_ar' => 'nullable|string|max:500',
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => 'nullable|boolean',
            'placements' => 'nullable|array',
            'placements.*' => ['string', Rule::in(ClassifiedPlacement::values())],
            'country_ids' => 'nullable|array',
            'country_ids.*' => ['uuid', Rule::exists('countries', 'id')],
            'parent_id' => [
                'nullable', 'uuid', 'exists:classified_categories,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($category): void {
                    // Two levels only (section > sub-category): every storefront surface renders exactly that.
                    if (ClassifiedCategory::whereKey($value)->whereNotNull('parent_id')->exists()) {
                        $fail(__('classified.admin.parent_must_be_top_level'));
                    }

                    if ($category && $category->children()->exists()) {
                        $fail(__('classified.admin.has_children_cannot_nest'));
                    }
                },
            ],
            'requires_location_map' => 'boolean',
            'requires_sketch_upload' => 'boolean',
            ...app(ContractAssignmentService::class)->rules('classified'),
            'required_attachment_types' => 'nullable|array',
            'required_attachment_types.*' => 'string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }

    /**
     * Columns written to the category (placements only when the form sent them, so API-style callers that omit
     * them keep what is stored; a new category without placements gets the legacy default).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, Request $request): array
    {
        $attributes = Arr::except($validated, [
            'requires_contract', 'country_contracts', 'image', 'remove_image', 'country_ids', 'placements',
        ]);

        if ($request->has('placements') || $request->boolean('placements_submitted')) {
            $attributes['placements'] = array_values(array_unique($validated['placements'] ?? []));
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncCountries(ClassifiedCategory $category, Request $request, array $validated): void
    {
        if ($request->has('country_ids') || $request->boolean('countries_submitted')) {
            $category->countries()->sync($validated['country_ids'] ?? []);
        }
    }

    /**
     * Stores the uploaded image (replacing the current one) or removes it. Same storage as product categories:
     * a polymorphic `files` row on the public disk.
     */
    private function storeImage(ClassifiedCategory $category, Request $request): void
    {
        if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
            $category->files()->get()->each->delete();

            return;
        }

        if (! $request->hasFile('image')) {
            return;
        }

        $category->files()->get()->each->delete();

        $uploaded = $request->file('image');
        $extension = $uploaded->getClientOriginalExtension() ?: $uploaded->guessExtension();
        $path = $uploaded->storeAs('classified-categories/'.$category->id, 'image_'.Str::random(8).'.'.$extension, 'public');

        File::create([
            'key' => 'classified-categories/'.$category->id.'/image',
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'classified_category_image',
            'mime_type' => $uploaded->getMimeType(),
            'extension' => $extension,
            'size' => $uploaded->getSize(),
            'model_type' => ClassifiedCategory::class,
            'model_id' => $category->id,
            'is_primary' => 1,
            'position' => 0,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true) || ClassifiedCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, int>  $blockers
     */
    private function blockedMessage(array $blockers): string
    {
        $reasons = collect($blockers)
            ->map(fn (int $count, string $key) => __('classified.admin.blocker_'.$key, ['count' => $count]))
            ->implode(' ');

        return __('classified.admin.cannot_delete', ['reasons' => $reasons]);
    }

    /**
     * @param  Collection<int, Country>  $gaps
     */
    private function gapWarning($gaps): ?string
    {
        return $gaps->isEmpty() ? null : __('contracts.admin.gap_warning', ['countries' => $gaps->pluck('name_en')->implode(', ')]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(string $event, ClassifiedCategory $category, array $properties = []): void
    {
        $this->activity->log(
            description: $event.': '.$category->name_en,
            subject: $category,
            causer: auth('admin')->user(),
            properties: $properties,
            logName: 'classifieds',
            event: $event,
        );
    }

    private function wouldCreateCycle(?string $categoryId, string $proposedParentId): bool
    {
        // Walk up the tree from proposedParent; if we hit $categoryId, it's a cycle
        $current = $proposedParentId;
        $visited = [];

        while ($current !== null) {
            if ($categoryId && $current === $categoryId) {
                return true;
            }
            if (isset($visited[$current])) {
                break; // existing cycle in data — stop
            }
            $visited[$current] = true;
            $current = ClassifiedCategory::where('id', $current)->value('parent_id');
        }

        return false;
    }
}
