<?php

namespace App\Services;

use App\Jobs\RecomputeListingShippingMethodsJob;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Category;
use App\Models\CategoryShippingMethod;
use App\Models\CountryCategory;
use App\Models\File;
use App\Models\Slug;
use App\Services\Vendor\ContractAssignmentService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saves the whole category form — general fields, attributes, shipping
 * methods, per-country commissions, commission tiers, country availability,
 * contract requirement, marketer sample settings and image — in one database
 * transaction, for both create and edit. Either everything is stored or nothing.
 *
 * Sections that are lists (attributes, shipping methods, tiers) are replaced by
 * what the form sent; the form marks each list it rendered in `sync[]`, so an
 * emptied list is cleared while a client that omits a section leaves it alone.
 */
class CategoryFormService
{
    public const SYNC_ATTRIBUTES = 'attributes';

    public const SYNC_SHIPPING = 'shipping_methods';

    public const SYNC_TIERS = 'tiers';

    public const SYNC_COUNTRY_COMMISSIONS = 'country_commissions';

    public const SYNC_AVAILABILITY = 'availability';

    public function __construct(
        private readonly CategoryService $categories,
        private readonly ContractAssignmentService $contracts,
        private readonly CategoryCountryAvailabilityService $availability,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request input (raw input for the contract partial)
     * @return array{category: Category, contract_gaps: Collection<int, mixed>}
     */
    public function save(?Category $category, array $input, ?UploadedFile $image, ?string $adminId): array
    {
        $this->contracts->assertConfigurable('product', $input, $category);

        $sync = array_values((array) ($input['sync'] ?? []));
        $storedPaths = [];

        try {
            $result = DB::transaction(function () use ($category, $input, $image, $adminId, $sync, &$storedPaths) {
                $category = $category
                    ? $this->updateCore($category, $input, $adminId)
                    : $this->createCore($input);

                Slug::upsertFor($category, $category->slug);

                $gaps = $this->contracts->apply($category, 'product', $input);

                if ($this->shouldSync(self::SYNC_ATTRIBUTES, $input, $sync)) {
                    $this->categories->syncAttributes($category, $this->attributeRows($input));
                }

                if ($this->shouldSync(self::SYNC_SHIPPING, $input, $sync)) {
                    $this->syncShippingMethods($category, (array) ($input['shipping_methods'] ?? []));
                }

                if ($this->shouldSync(self::SYNC_COUNTRY_COMMISSIONS, $input, $sync)) {
                    $this->syncCountryCommissions($category, (array) ($input['country_commissions'] ?? []), $adminId);
                }

                if ($this->shouldSync(self::SYNC_TIERS, $input, $sync)) {
                    $this->syncTiers($category, (array) ($input['tiers'] ?? []));
                }

                if (in_array(self::SYNC_AVAILABILITY, $sync, true) || array_key_exists('availability_mode', $input)) {
                    $this->availability->sync(
                        $category,
                        (string) ($input['availability_mode'] ?? CategoryCountryAvailabilityService::MODE_ALL),
                        array_values((array) ($input['availability_countries'] ?? [])),
                        $adminId,
                    );
                }

                if (filter_var($input['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN) && ! $image) {
                    $this->deleteImage($category);
                }

                if ($image) {
                    $storedPaths[] = $this->storeImage($category, $image)->path;
                }

                return ['category' => $category->fresh(), 'contract_gaps' => $gaps];
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        if ($this->shouldSync(self::SYNC_SHIPPING, $input, $sync)) {
            dispatch(new RecomputeListingShippingMethodsJob([$result['category']->id]));
        }

        return $result;
    }

    /**
     * Replaces the category's image (used by the form and the legacy upload endpoint).
     */
    public function storeImage(Category $category, UploadedFile $uploadedFile): File
    {
        $this->deleteImage($category);

        $extension = $uploadedFile->getClientOriginalExtension() ?: $uploadedFile->guessExtension();
        $path = $uploadedFile->storeAs('categories/'.$category->id, 'image_'.Str::random(8).'.'.$extension, 'public');

        return File::create([
            'key' => 'categories/'.$category->id.'/image',
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'category_image',
            'mime_type' => $uploadedFile->getMimeType(),
            'extension' => $extension,
            'size' => $uploadedFile->getSize(),
            'model_type' => Category::class,
            'model_id' => $category->id,
            'is_primary' => 1,
            'position' => 0,
        ]);
    }

    public function deleteImage(Category $category): bool
    {
        $existing = $category->primaryImage()->first() ?? $category->files()->orderBy('position')->first();

        if (! $existing) {
            return false;
        }

        Storage::disk($existing->storage_type ?: 'public')->delete($existing->path);
        $existing->delete();

        return true;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $sync
     */
    private function shouldSync(string $section, array $input, array $sync): bool
    {
        return in_array($section, $sync, true) || array_key_exists($section, $input);
    }

    /**
     * Plain columns of the form. A field the request does not carry keeps the
     * current value (on create: active + visible default on, the rest off/zero);
     * the form itself always sends every field, with hidden "0" fallbacks for
     * unchecked toggles.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function coreAttributes(array $input, ?Category $current): array
    {
        $flag = fn (string $key, bool $default): bool => array_key_exists($key, $input)
            ? filter_var($input[$key], FILTER_VALIDATE_BOOLEAN)
            : (bool) ($current?->{$key} ?? $default);

        $int = fn (string $key): int => array_key_exists($key, $input)
            ? (int) $input[$key]
            : (int) ($current?->{$key} ?? 0);

        return [
            'name_en' => $input['name_en'],
            'name_ar' => $input['name_ar'],
            'description_en' => ($input['description_en'] ?? null) ?: null,
            'description_ar' => ($input['description_ar'] ?? null) ?: null,
            'sort_order' => $int('sort_order'),
            'is_active' => $flag('is_active', true),
            'is_visible' => $flag('is_visible', true),
            'is_featured' => $flag('is_featured', false),
            'has_filters' => $flag('has_filters', false),
            'contract_template_id' => ($input['contract_template_id'] ?? null) ?: null,
            'seo_title_en' => ($input['seo_title_en'] ?? null) ?: null,
            'seo_title_ar' => ($input['seo_title_ar'] ?? null) ?: null,
            'seo_description_en' => ($input['seo_description_en'] ?? null) ?: null,
            'seo_description_ar' => ($input['seo_description_ar'] ?? null) ?: null,
            'influencer_sample_qty' => $int('influencer_sample_qty'),
            'affiliate_sample_qty' => $int('affiliate_sample_qty'),
            'platform_sample_qty' => $int('platform_sample_qty'),
            'min_stock_for_campaign' => $int('min_stock_for_campaign'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createCore(array $input): Category
    {
        $slug = ($input['slug'] ?? null) ?: Str::slug($input['name_en']).'-'.Str::lower(Str::random(5));
        $baseSlug = $slug;
        $i = 1;
        // Categories and custom pages share the `slugs` table.
        while (Slug::isTaken($slug)) {
            $slug = $baseSlug.'-'.$i++;
        }

        $parent = ! empty($input['parent_id']) ? Category::findOrFail($input['parent_id']) : null;

        $category = new Category([
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            ...$this->coreAttributes($input, null),
            'commission_rate' => $input['commission_rate'] ?? $parent?->commission_rate ?? 0,
            'commission_fbp_pct' => (float) ($parent->commission_fbp_pct ?? 0),
            'commission_fbp_fixed' => (float) ($parent->commission_fbp_fixed ?? 0),
            'commission_fbn_pct' => (float) ($parent->commission_fbn_pct ?? 0),
            'commission_fbn_fixed' => (float) ($parent->commission_fbn_fixed ?? 0),
        ]);

        if ($parent) {
            if (! $parent->getLft() || ! $parent->getRgt()) {
                Category::fixTree();
                $parent->refresh();
            }
            $category->appendToNode($parent)->save();
        } else {
            $category->save();
            Category::fixTree();
            $category->refresh();
        }

        return $category;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateCore(Category $category, array $input, ?string $adminId): Category
    {
        $oldRate = (float) $category->commission_rate;

        $data = $this->coreAttributes($input, $category);
        $data['commission_rate'] = $input['commission_rate'] ?? $category->commission_rate;

        if (! empty($input['slug'])) {
            $data['slug'] = $input['slug'];
        }

        $parentId = ($input['parent_id'] ?? null) ?: null;
        if ($parentId && $parentId !== $category->parent_id) {
            $parent = Category::findOrFail($parentId);
            if (! $parent->getLft() || ! $parent->getRgt()) {
                Category::fixTree();
                $parent->refresh();
            }
            $category->appendToNode($parent);
        } elseif (! $parentId && $category->parent_id) {
            $category->makeRoot();
        }

        $category->fill($data)->save();

        $newRate = (float) $category->commission_rate;
        if (abs($oldRate - $newRate) > 0.001) {
            Activity::query()->insert([
                'id' => (string) Str::uuid(),
                'log_name' => 'categories',
                'description' => 'commission_changed',
                'subject_type' => Category::class,
                'subject_id' => $category->id,
                'causer_type' => $adminId ? Admin::class : null,
                'causer_id' => $adminId,
                'properties' => json_encode(['old' => $oldRate, 'new' => $newRate]),
                'event' => 'commission_changed',
                'created_at' => now(),
            ]);
        }

        return $category;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{attribute_id: string, is_required: bool, sort_order: int}>
     */
    private function attributeRows(array $input): array
    {
        return collect((array) ($input['attributes'] ?? []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['attribute_id']))
            ->values()
            ->map(fn (array $row, int $i) => [
                'attribute_id' => $row['attribute_id'],
                'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($row['sort_order'] ?? $i),
            ])
            ->all();
    }

    /**
     * Replace the category's shipping methods with the submitted list (order = priority).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncShippingMethods(Category $category, array $rows): void
    {
        $rows = collect($rows)->filter(fn ($row) => is_array($row) && ! empty($row['shipping_method_id']))->values();

        CategoryShippingMethod::query()
            ->where('category_id', $category->id)
            ->whereNotIn('shipping_method_id', $rows->pluck('shipping_method_id'))
            ->delete();

        foreach ($rows as $priority => $row) {
            CategoryShippingMethod::updateOrCreate(
                ['category_id' => $category->id, 'shipping_method_id' => $row['shipping_method_id']],
                [
                    'is_default' => filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_available_for_express_fbn' => filter_var($row['is_available_for_express_fbn'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_available_for_merchant_fbp' => filter_var($row['is_available_for_merchant_fbp'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'display_priority' => $priority,
                ],
            );
        }
    }

    /**
     * Per-country FBP/FBM/FBN overrides on the shared country_categories row;
     * empty means "inherit". Availability on the same row is left untouched.
     *
     * @param  array<string, array<string, mixed>>  $rows  keyed by country id
     */
    private function syncCountryCommissions(Category $category, array $rows, ?string $adminId): void
    {
        $fields = ['commission_fbp_pct', 'commission_fbp_fixed', 'commission_fbm_pct', 'commission_fbm_fixed', 'commission_fbn_pct', 'commission_fbn_fixed'];

        $existing = CountryCategory::query()->where('category_id', $category->id)->get()->keyBy('country_id');

        foreach ($rows as $countryId => $row) {
            $values = [];
            foreach ($fields as $field) {
                $raw = is_array($row) ? ($row[$field] ?? null) : null;
                $values[$field] = ($raw === null || $raw === '')
                    ? null
                    : (str_ends_with($field, '_fixed') ? round((float) $raw, 4) : (float) $raw);
            }

            $current = $existing->get($countryId);
            $hasValues = collect($values)->filter(fn ($v) => $v !== null)->isNotEmpty();

            if (! $current && ! $hasValues) {
                continue;
            }

            if ($current && collect($fields)->every(fn ($f) => $this->sameNumber($current->{$f}, $values[$f]))) {
                continue;
            }

            CountryCategory::updateOrCreate(
                ['country_id' => $countryId, 'category_id' => $category->id],
                $values + ['updated_by_admin_id' => $adminId],
            );
        }
    }

    private function sameNumber(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }

        return abs((float) $a - (float) $b) < 0.00005;
    }

    /**
     * Replace the category's commission tiers with the submitted list; rows that
     * carry an id of an existing tier are updated in place.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncTiers(Category $category, array $rows): void
    {
        $rows = collect($rows)->filter(fn ($row) => is_array($row))->values();
        $existingIds = $category->commissionTiers()->pluck('id');
        $keptIds = $rows->pluck('id')->filter(fn ($id) => $id && $existingIds->contains($id))->values();

        $category->commissionTiers()->whereNotIn('id', $keptIds)->delete();

        foreach ($rows as $row) {
            $from = Money::number($row['price_from']);
            $attributes = [
                'country_id' => ($row['country_id'] ?? null) ?: null,
                'price_from' => $from,
                'price_to' => isset($row['price_to']) && $row['price_to'] !== '' ? Money::number($row['price_to']) : null,
                'commission_rate' => $row['commission_rate'],
                'min_commission' => Money::number($row['min_commission'] ?? 0),
                'sort_order' => (int) floor((float) $from),
            ];

            if (! empty($row['id']) && $keptIds->contains($row['id'])) {
                $category->commissionTiers()->whereKey($row['id'])->first()?->update($attributes);
            } else {
                $category->commissionTiers()->create($attributes);
            }
        }
    }
}
