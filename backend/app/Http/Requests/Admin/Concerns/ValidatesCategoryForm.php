<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Category;
use App\Models\Slug;
use App\Services\CategoryCountryAvailabilityService;
use App\Services\CategoryService;
use App\Services\Vendor\ContractAssignmentService;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One rule set for the whole category form (create and edit): general fields,
 * attributes, shipping methods, per-country commissions, commission tiers,
 * country availability, SEO, marketer samples, status flags and image. Error
 * keys follow the input names (e.g. `tiers.2.price_from`) so the form can show
 * each message next to its field.
 */
trait ValidatesCategoryForm
{
    /**
     * @return array<string, mixed>
     */
    protected function categoryFormRules(?string $categoryId): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', $categoryId
                ? Rule::exists('categories', 'id')->whereNot('id', $categoryId)->whereNull('deleted_at')
                : Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', $categoryId
                ? Rule::unique('categories', 'slug')->ignore($categoryId)
                : Rule::unique('categories', 'slug')],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'commission_rate' => ['nullable', 'numeric', 'between:0,100'],
            'commission_fbp_pct' => ['nullable', 'numeric', 'between:0,100'],
            'commission_fbp_fixed' => ['nullable', 'numeric', 'min:0'],
            'commission_fbm_pct' => ['nullable', 'numeric', 'between:0,100'],
            'commission_fbm_fixed' => ['nullable', 'numeric', 'min:0'],
            'commission_fbn_pct' => ['nullable', 'numeric', 'between:0,100'],
            'commission_fbn_fixed' => ['nullable', 'numeric', 'min:0'],
            ...app(ContractAssignmentService::class)->rules('product'),
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_visible' => ['boolean'],
            'is_featured' => ['boolean'],
            'has_filters' => ['boolean'],
            'seo_title_en' => ['nullable', 'string', 'max:70'],
            'seo_title_ar' => ['nullable', 'string', 'max:70'],
            'seo_description_en' => ['nullable', 'string', 'max:160'],
            'seo_description_ar' => ['nullable', 'string', 'max:160'],
            'influencer_sample_qty' => ['nullable', 'integer', 'min:0'],
            'affiliate_sample_qty' => ['nullable', 'integer', 'min:0'],
            'platform_sample_qty' => ['nullable', 'integer', 'min:0'],
            'min_stock_for_campaign' => ['nullable', 'integer', 'min:0'],

            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],

            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['nullable', 'string', 'exists:attributes,id'],
            'attributes.*.is_required' => ['nullable', 'boolean'],
            'attributes.*.sort_order' => ['nullable', 'integer'],

            'shipping_methods' => ['nullable', 'array'],
            'shipping_methods.*.shipping_method_id' => ['required', 'uuid', 'distinct', 'exists:shipping_methods,id'],
            'shipping_methods.*.is_default' => ['nullable', 'boolean'],
            'shipping_methods.*.is_available_for_express_fbn' => ['nullable', 'boolean'],
            'shipping_methods.*.is_available_for_merchant_fbp' => ['nullable', 'boolean'],

            'country_commissions' => ['nullable', 'array'],
            'country_commissions.*.commission_fbp_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'country_commissions.*.commission_fbp_fixed' => ['nullable', 'numeric', 'min:0'],
            'country_commissions.*.commission_fbm_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'country_commissions.*.commission_fbm_fixed' => ['nullable', 'numeric', 'min:0'],
            'country_commissions.*.commission_fbn_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'country_commissions.*.commission_fbn_fixed' => ['nullable', 'numeric', 'min:0'],

            'tiers' => ['nullable', 'array'],
            'tiers.*.id' => ['nullable', 'uuid'],
            'tiers.*.country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'tiers.*.price_from' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'tiers.*.price_to' => ['nullable', 'numeric', 'decimal:0,2', 'min:1', 'gt:tiers.*.price_from'],
            'tiers.*.commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tiers.*.min_commission' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],

            'availability_mode' => ['nullable', Rule::in(CategoryCountryAvailabilityService::MODES)],
            'availability_countries' => ['nullable', 'array'],
            'availability_countries.*' => ['uuid', 'exists:countries,id'],
        ];
    }

    /**
     * Cross-field checks that single-field rules cannot express.
     *
     * @return list<callable(Validator): void>
     */
    protected function categoryFormAfter(?string $categoryId): array
    {
        return [
            function (Validator $validator) use ($categoryId): void {
                $this->validateParentIsNotADescendant($validator, $categoryId);
                $this->validateSlugIsFree($validator, $categoryId);
                $this->validateFeaturedLimit($validator, $categoryId);
                $this->validateSingleDefaultShippingMethod($validator);
                $this->validateTierRangesDoNotOverlap($validator);
                $this->validateAvailabilitySelection($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_en' => __('admin.name_en'),
            'name_ar' => __('admin.name_ar'),
            'commission_rate' => __('category_form.attr.commission_rate'),
            'parent_id' => __('admin.categories.parent_category'),
            'tiers.*.price_from' => __('category_form.attr.price_from'),
            'tiers.*.price_to' => __('category_form.attr.price_to'),
            'tiers.*.commission_rate' => __('category_form.attr.commission_rate'),
            'tiers.*.min_commission' => __('category_form.attr.min_commission'),
            'shipping_methods.*.shipping_method_id' => __('category_form.attr.shipping_method'),
            'availability_countries' => __('category_form.attr.countries'),
            'country_commissions.*.commission_fbp_pct' => __('category_form.attr.percentage'),
            'country_commissions.*.commission_fbm_pct' => __('category_form.attr.percentage'),
            'country_commissions.*.commission_fbn_pct' => __('category_form.attr.percentage'),
            'country_commissions.*.commission_fbp_fixed' => __('category_form.attr.fixed_fee'),
            'country_commissions.*.commission_fbm_fixed' => __('category_form.attr.fixed_fee'),
            'country_commissions.*.commission_fbn_fixed' => __('category_form.attr.fixed_fee'),
        ];
    }

    private function validateParentIsNotADescendant(Validator $validator, ?string $categoryId): void
    {
        $parentId = $this->input('parent_id');

        if (! $categoryId || ! $parentId || $validator->errors()->has('parent_id')) {
            return;
        }

        $category = Category::find($categoryId);

        if ($category && in_array($parentId, $category->descendants()->pluck('id')->all(), true)) {
            $validator->errors()->add('parent_id', __('category_form.errors.parent_is_descendant'));
        }
    }

    private function validateSlugIsFree(Validator $validator, ?string $categoryId): void
    {
        $slug = $this->input('slug');

        if (! $categoryId || ! $slug || $validator->errors()->has('slug')) {
            return;
        }

        $current = Category::whereKey($categoryId)->value('slug');

        if ($slug !== $current && Slug::isTaken($slug, Category::class, $categoryId)) {
            $validator->errors()->add('slug', __('category_form.errors.slug_taken'));
        }
    }

    private function validateFeaturedLimit(Validator $validator, ?string $categoryId): void
    {
        if (! $this->boolean('is_featured')) {
            return;
        }

        $others = Category::where('is_featured', true)
            ->when($categoryId, fn ($q) => $q->whereKeyNot($categoryId))
            ->count();

        if ($others >= CategoryService::MAX_FEATURED) {
            $validator->errors()->add('is_featured', __('category_form.errors.featured_limit', ['max' => CategoryService::MAX_FEATURED]));
        }
    }

    private function validateSingleDefaultShippingMethod(Validator $validator): void
    {
        $defaults = collect($this->input('shipping_methods', []))
            ->filter(fn ($row) => is_array($row) && filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN));

        if ($defaults->count() > 1) {
            $validator->errors()->add('shipping_methods', __('category_form.errors.one_default_shipping'));
        }
    }

    /**
     * Same rule as the per-tier endpoint: within one country scope (none = global)
     * no two price ranges may overlap; an empty "to" means open-ended.
     */
    private function validateTierRangesDoNotOverlap(Validator $validator): void
    {
        $tiers = collect($this->input('tiers', []))->filter(fn ($row) => is_array($row));

        foreach ($tiers->groupBy(fn ($row) => ($row['country_id'] ?? '') ?: 'global') as $group) {
            $rows = $group->map(fn ($row, $index) => [
                'index' => $index,
                'from' => is_numeric($row['price_from'] ?? null) ? Money::number($row['price_from']) : null,
                'to' => is_numeric($row['price_to'] ?? null) ? Money::number($row['price_to']) : null,
            ])->filter(fn ($row) => $row['from'] !== null)->values();

            foreach ($rows as $i => $a) {
                foreach ($rows->slice($i + 1) as $b) {
                    $aEndsAfterBStarts = $a['to'] === null || $a['to'] >= $b['from'];
                    $aStartsBeforeBEnds = $b['to'] === null || $a['from'] <= $b['to'];

                    if ($aEndsAfterBStarts && $aStartsBeforeBEnds) {
                        $validator->errors()->add("tiers.{$b['index']}.price_from", __('admin.categories.tier_overlap'));
                    }
                }
            }
        }
    }

    private function validateAvailabilitySelection(Validator $validator): void
    {
        $mode = $this->input('availability_mode');

        if (in_array($mode, [CategoryCountryAvailabilityService::MODE_ONLY, CategoryCountryAvailabilityService::MODE_EXCEPT], true)
            && empty($this->input('availability_countries'))) {
            $validator->errors()->add('availability_countries', __('category_form.errors.pick_countries'));
        }
    }
}
