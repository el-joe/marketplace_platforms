{{--
    Shared Product form partial.
    Include with: @include('admin.products._form', ['mode' => 'create'])
                  @include('admin.products._form', ['mode' => 'edit', 'product' => $product])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
--}}
@php
    $product = $product ?? null;
    $isEdit = $product !== null;

    /** Quick helper to resolve old() → product field → default. */
    $val = function (string $field, $default = '') use ($isEdit, $product) {
        return old($field, $isEdit ? ($product->{$field} ?? $default) : $default);
    };

    /** Boolean helper for checkboxes / toggles. */
    $bool = function (string $field, bool $default = false) use ($isEdit, $product): bool {
        $raw = old($field, $isEdit ? ($product->{$field} ?? $default) : $default);
        return (bool) $raw;
    };
@endphp

{{-- ─────────────────────────────────────────────────────────────────────────── --}}
{{-- Alpine root: one reactive component for the whole form                     --}}
{{-- ─────────────────────────────────────────────────────────────────────────── --}}
<div
    x-data="{
        activeTab:       'basic',
        hasVariants:     {{ $bool('has_variants') ? 'true' : 'false' }},
        isAgeRestricted: {{ $bool('is_age_restricted') ? 'true' : 'false' }},
        showDuplicate:   false,
        duplicateProduct: null,
    }"
    class="space-y-6"
>
    <input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- LEFT COLUMN: tabbed panels                                         --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="w-full">

            {{-- Tab navigation bar --}}
            <div class="bg-white rounded-t-xl border border-gray-200 overflow-hidden">
                <nav class="flex overflow-x-auto border-b border-gray-100" aria-label="{{ __('admin.products.form_tabs_aria') }}">
                    @foreach([
                        ['id' => 'basic',     'label' => __('admin.products.tab_basic_info'),  'icon' => 'information-circle'],
                        ['id' => 'content',   'label' => __('admin.products.tab_content'),     'icon' => 'document-text'],
                        ['id' => 'variants',  'label' => __('admin.products.tab_variants'),    'icon' => 'cube'],
                        ['id' => 'images',    'label' => __('admin.products.tab_images'),      'icon' => 'photo'],
                        ['id' => 'fbt',       'label' => __('admin.products.tab_fbt'),          'icon' => 'squares-plus'],
                        ['id' => 'countries', 'label' => __('admin.products.tab_countries'),   'icon' => 'globe-alt'],
                        ['id' => 'seo',       'label' => __('admin.products.tab_seo'),          'icon' => 'magnifying-glass'],
                    ] as $tab)
                    <button
                        type="button"
                        @click="activeTab = '{{ $tab['id'] }}'"
                        :class="activeTab === '{{ $tab['id'] }}'
                            ? 'border-b-2 border-primary-600 text-primary-700 bg-primary-50/50'
                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-3 sm:py-3.5 text-sm font-medium -mb-px whitespace-nowrap transition-colors"
                    >
                        <x-heroicon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                        {{ $tab['label'] }}
                    </button>
                    @endforeach
                    @if($isEdit && auth('admin')->user()?->hasPermissionTo('products.cost_data.view'))
                    <button
                        type="button"
                        @click="activeTab = 'cost'"
                        :class="activeTab === 'cost'
                            ? 'border-b-2 border-red-500 text-red-700 bg-red-50/50'
                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-3 sm:py-3.5 text-sm font-medium -mb-px whitespace-nowrap transition-colors"
                    >
                        <x-heroicon name="lock-closed" class="w-4 h-4" />
                        {{ __('admin.products.cost_tab') }}
                    </button>
                    @endif
                </nav>
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- TAB: Basic Info                                 --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div
                x-show="activeTab === 'basic'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5"
            >
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-form.input
                        name="name_en"
                        label="{{ __('admin.name_en') }}"
                        :value="$val('name_en')"
                        required
                        maxlength="255"
                        dir="ltr"
                        placeholder="{{ __('admin.products.name_en_placeholder') }}"
                    />
                    <x-form.input
                        name="name_ar"
                        label="{{ __('admin.products.name_ar_label') }}"
                        :value="$val('name_ar')"
                        required
                        maxlength="255"
                        dir="rtl"
                        placeholder="{{ __('admin.products.name_ar_placeholder') }}"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-form.async-select
                        name="category_id"
                        label="{{ __('admin.category') }}"
                        required
                        search-url="{{ route('admin.categories.search') }}"
                        placeholder="{{ __('admin.products.category_search_placeholder') }}"
                        :min-length="0"
                        :value="$isEdit ? $product->category_id : null"
                        :value-label="$isEdit && $product->category_id ? ($categories[$product->category_id] ?? '') : null"
                    />
                    <x-form.async-select
                        name="brand_id"
                        label="{{ __('admin.brand') }}"
                        search-url="{{ route('admin.brands.search') }}"
                        placeholder="{{ __('admin.products.brand_search_placeholder') }}"
                        :min-length="0"
                        :value="$isEdit ? $product->brand_id : null"
                        :value-label="$isEdit && $product->brand_id ? ($brands[$product->brand_id] ?? '') : null"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-form.input
                            name="gtin"
                            id="gtin-input"
                            label="{{ __('admin.products.barcode_label') }}"
                            :value="$val('gtin')"
                            maxlength="13"
                            dir="ltr"
                            placeholder="{{ __('admin.products.barcode_placeholder') }}"
                            help="{{ __('admin.products.barcode_help') }}"
                        />
                        {{-- Duplicate warning injected by JS --}}
                        <div id="gtin-warning" class="hidden mt-2 rounded-lg bg-warning-50 border border-warning-200 p-3 text-sm text-warning-800"></div>
                    </div>
                    <x-form.input
                        name="model_number"
                        label="{{ __('admin.products.model_number') }}"
                        :value="$val('model_number')"
                        maxlength="100"
                        dir="ltr"
                        placeholder="{{ __('admin.products.model_number_placeholder') }}"
                    />
                </div>
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- TAB: Content                                    --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div
                x-show="activeTab === 'content'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5"
            >
                <x-form.rich-editor
                    name="description_en"
                    label="{{ __('admin.products.description_en') }}"
                    :value="$val('description_en')"
                />
                <x-form.rich-editor
                    name="description_ar"
                    label="{{ __('admin.products.description_ar') }}"
                    :value="$val('description_ar')"
                />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="form-label">{{ __('admin.products.short_description_en') }}</label>
                            <span class="text-xs text-gray-400" data-char-counter="short_desc_en" data-max="500">0 / 500</span>
                        </div>
                        <textarea name="short_desc_en" id="short_desc_en"
                            maxlength="500" rows="4" dir="ltr"
                            class="form-textarea w-full @error('short_desc_en') is-invalid @enderror"
                            placeholder="{{ __('admin.products.short_desc_en_placeholder') }}">{{ $val('short_desc_en') }}</textarea>
                        @error('short_desc_en') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="form-label" dir="rtl">{{ __('admin.products.short_description_ar') }}</label>
                            <span class="text-xs text-gray-400" data-char-counter="short_desc_ar" data-max="500">0 / 500</span>
                        </div>
                        <textarea name="short_desc_ar" id="short_desc_ar"
                            maxlength="500" rows="4" dir="rtl"
                            class="form-textarea w-full @error('short_desc_ar') is-invalid @enderror"
                            placeholder="{{ __('admin.products.short_desc_ar_placeholder') }}">{{ $val('short_desc_ar') }}</textarea>
                        @error('short_desc_ar') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Highlights --}}
                <div class="pt-2 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label">{{ __('admin.products.highlights_label') }}</label>
                        <button type="button" id="add-highlight-row" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                            + {{ __('admin.products.add_highlight') }}
                        </button>
                    </div>
                    <div id="highlights-rows" class="space-y-2">
                        @foreach(($highlights ?? []) as $i => $highlight)
                        <div class="highlight-row flex gap-3 items-start">
                            <input type="hidden" name="highlights[{{ $i }}][id]" value="{{ $highlight->id }}">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">{{ __('admin.products.highlight_en_label') }}</label>
                                <input type="text" name="highlights[{{ $i }}][text_en]" value="{{ old("highlights.$i.text_en", $highlight->text_en) }}"
                                    dir="ltr" maxlength="500" placeholder="{{ __('admin.products.highlight_en_placeholder') }}"
                                    class="form-input text-sm py-1.5 w-full" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">{{ __('admin.products.highlight_ar_label') }}</label>
                                <input type="text" name="highlights[{{ $i }}][text_ar]" value="{{ old("highlights.$i.text_ar", $highlight->text_ar) }}"
                                    dir="rtl" maxlength="500" placeholder="{{ __('admin.products.highlight_ar_placeholder') }}"
                                    class="form-input text-sm py-1.5 w-full" />
                            </div>
                            <button type="button" class="remove-highlight-row mt-5 shrink-0 text-gray-400 hover:text-red-600 transition-colors p-2" title="{{ __('admin.remove') }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Specifications --}}
                <div class="pt-2 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label">{{ __('admin.products.specifications_label') }}</label>
                        <button type="button" id="add-specification-row" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                            + {{ __('admin.products.add_specification') }}
                        </button>
                    </div>
                    <div id="specifications-rows" class="space-y-2">
                        @foreach(($specifications ?? []) as $i => $spec)
                        <div class="flex gap-3 items-start specification-row">
                            <input type="hidden" name="specifications[{{ $i }}][id]" value="{{ $spec->id }}">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Key (EN)</label>
                                <input type="text" name="specifications[{{ $i }}][key_en]" value="{{ old("specifications.$i.key_en", $spec->key_en) }}"
                                    dir="ltr" maxlength="255" placeholder="e.g. Material"
                                    class="w-full border-gray-300 rounded-md text-sm" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Key (AR)</label>
                                <input type="text" name="specifications[{{ $i }}][key_ar]" value="{{ old("specifications.$i.key_ar", $spec->key_ar) }}"
                                    dir="rtl" maxlength="255" placeholder="مثال: المادة"
                                    class="w-full border-gray-300 rounded-md text-sm" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Value (EN)</label>
                                <input type="text" name="specifications[{{ $i }}][value_en]" value="{{ old("specifications.$i.value_en", $spec->value_en) }}"
                                    dir="ltr" maxlength="500" placeholder="e.g. 100% Cotton"
                                    class="w-full border-gray-300 rounded-md text-sm" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Value (AR)</label>
                                <input type="text" name="specifications[{{ $i }}][value_ar]" value="{{ old("specifications.$i.value_ar", $spec->value_ar) }}"
                                    dir="rtl" maxlength="500" placeholder="مثال: قطن 100%"
                                    class="w-full border-gray-300 rounded-md text-sm" />
                            </div>
                            <button type="button" class="remove-specification-row mt-5 shrink-0 text-gray-400 hover:text-red-600 transition-colors p-2" title="{{ __('admin.remove') }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- TAB: Variants                                   --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div
                x-show="activeTab === 'variants'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5"
            >
                <div x-show="!hasVariants" class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-700 flex items-start gap-2">
                    <x-heroicon name="information-circle" class="w-5 h-5 flex-shrink-0 mt-0.5" />
                    <span>{{ __('admin.products.has_variants_hint', ['field' => __('admin.products.has_variants_field')]) }}</span>
                </div>

                <div x-show="hasVariants" class="space-y-5">
                    {{-- Attribute checkboxes --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.products.variant_attributes') }}</h4>
                        <div id="variant-attributes-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($categoryAttributes ?? [] as $attr)
                            <div class="variant-attr-group" data-attr-id="{{ $attr->id }}">
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ $attr->name_en }}</label>
                                <select multiple data-select2-init class="variant-attr-values w-full" data-attr-id="{{ $attr->id }}">
                                    @foreach($attr->values ?? [] as $val)
                                    <option value="{{ $val->id }}" {{ in_array($val->id, $existingAttrValues ?? []) ? 'selected' : '' }}>{{ $val->value_en }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                        @if(empty($categoryAttributes))
                        <p class="text-sm text-gray-400 italic" id="no-attrs-msg">
                            {{ __('admin.products.select_category_first') }}
                        </p>
                        @endif
                    </div>

                    {{-- Generate / bulk-upload buttons --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="generate-variants-btn"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary-300 bg-primary-50 text-primary-700 text-sm font-medium hover:bg-primary-100 transition-colors">
                            <x-heroicon name="cube" class="w-4 h-4" />
                            {{ __('admin.products.generate_combinations') }}
                        </button>
                        <button type="button" id="bulk-upload-variant-images-btn"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50 transition-colors">
                            <x-heroicon name="photo" class="w-4 h-4" />
                            {{ __('admin.products.bulk_upload_images') ?? 'Bulk upload images' }}
                        </button>
                    </div>

                    {{-- Variants table --}}
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full text-sm" id="variants-table">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.products.variant_column') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">{{ __('admin.sku') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">{{ __('admin.products.slug_column') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">{{ __('admin.products.barcode') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">{{ __('admin.products.weight_grams_column') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider w-56">{{ __('admin.products.customer_url_column') ?? 'Customer URL' }}</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-20">{{ __('admin.products.default_column') }}</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-16">{{ __('admin.products.active_column') }}</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">{{ __('admin.products.images_column') ?? 'Images' }}</th>
                                    <th class="px-4 py-3 w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="variants-tbody" class="divide-y divide-gray-100">
                                @if($isEdit)
                                @foreach($variants ?? [] as $vi => $variant)
                                <tr class="variant-row hover:bg-gray-50" data-row-index="{{ $vi }}">
                                    <td class="px-4 py-3 font-medium text-gray-800">
                                        <button type="button" class="view-variant-detail hover:underline hover:text-primary-700 text-start"
                                            data-variant-id="{{ $variant->id }}"
                                            title="{{ __('admin.products.view_variant_detail') ?? 'View attributes, listings & UUID' }}">
                                            {{ $variant->name ?? __('admin.products.default_variant') }}
                                        </button>
                                        <input type="hidden" name="variants[{{ $vi }}][id]" value="{{ $variant->id }}" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="variants[{{ $vi }}][sku]"
                                            value="{{ $variant->sku }}"
                                            class="form-input text-sm py-1.5 w-full" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1">
                                            <input type="text" name="variants[{{ $vi }}][slug]"
                                                value="{{ $variant->slug }}" maxlength="255"
                                                title="{{ __('admin.products.slug_help') }}"
                                                class="form-input text-sm py-1.5 w-full variant-slug-input" />
                                            <button type="button"
                                                class="regenerate-variant-slug flex-shrink-0 p-1.5 text-gray-400 hover:text-primary-600 transition-colors"
                                                data-variant-id="{{ $variant->id }}"
                                                title="{{ __('admin.products.regenerate_slug') }}">
                                                <x-heroicon name="arrow-path" class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="variants[{{ $vi }}][barcode]"
                                            value="{{ $variant->barcode }}"
                                            class="form-input text-sm py-1.5 w-full" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="variants[{{ $vi }}][weight_grams]"
                                            value="{{ $variant->weight_grams }}" min="0"
                                            class="form-input text-sm py-1.5 w-full" />
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($variant->customer_url)
                                            <div class="flex items-center gap-1.5">
                                                <a href="{{ $variant->customer_url }}" target="_blank"
                                                    class="font-mono text-xs text-primary-700 hover:underline truncate max-w-[10rem]"
                                                    title="{{ $variant->customer_url }}">
                                                    {{ $variant->customer_url }}
                                                </a>
                                                <button type="button" class="copy-variant-url flex-shrink-0 p-1 text-gray-400 hover:text-primary-600 transition-colors"
                                                    data-url="{{ $variant->customer_url }}"
                                                    title="{{ __('admin.products.copy_url') ?? 'Copy URL' }}">
                                                    <x-heroicon name="clipboard-document-list" class="w-4 h-4" />
                                                </button>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono text-xs text-gray-400 truncate max-w-[8rem]" title="{{ $variant->id }}">{{ $variant->id }}</span>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">
                                                    {{ __('admin.products.no_active_listing') ?? 'No active listing' }}
                                                </span>
                                                <button type="button" class="copy-variant-url flex-shrink-0 p-1 text-gray-400 hover:text-primary-600 transition-colors"
                                                    data-url="{{ $variant->id }}"
                                                    title="{{ __('admin.products.copy_id') ?? 'Copy variant ID' }}">
                                                    <x-heroicon name="clipboard-document-list" class="w-4 h-4" />
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="radio" name="variants_default" value="{{ $vi }}"
                                            class="text-primary-600 border-gray-300"
                                            {{ $variant->is_default ? 'checked' : '' }} />
                                        <input type="hidden" name="variants[{{ $vi }}][is_default]"
                                            value="{{ $variant->is_default ? '1' : '0' }}" class="default-flag" />
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" name="variants[{{ $vi }}][is_active]" value="1"
                                            class="rounded text-primary-600 border-gray-300"
                                            {{ $variant->is_active ? 'checked' : '' }} />
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" class="manage-variant-images inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-gray-200 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-700 transition-colors"
                                            data-variant-id="{{ $variant->id }}"
                                            data-variant-name="{{ $variant->name ?? __('admin.products.default_variant') }}"
                                            data-images-url="{{ route('admin.products.variants.images', [$product->id, $variant->id]) }}"
                                            data-reorder-url="{{ route('admin.products.variants.reorder-images', [$product->id, $variant->id]) }}"
                                            data-upload-url="{{ route('admin.products.upload-image') }}">
                                            <x-heroicon name="photo" class="w-4 h-4" />
                                            <span class="variant-images-count">{{ $variant->images_count ?? 0 }}</span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button" class="remove-variant-row text-gray-400 hover:text-red-600 transition-colors" title="{{ __('admin.products.remove') }}">
                                            <x-heroicon name="x-circle" class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
                        <div id="no-variants-msg" class="{{ ($isEdit && count($variants ?? []) > 0) ? 'hidden' : '' }} px-4 py-6 text-center text-sm text-gray-400">
                            {{ __('admin.products.no_variants_yet', ['action' => __('admin.products.generate_combinations')]) }}
                        </div>
                    </div>
                    <p class="text-xs text-gray-400">{{ __('admin.products.slug_help') }}</p>
                    <script>
                        window.__initialVariantAttributes = @json(
                            collect($variants ?? [])->mapWithKeys(fn ($variant, $vi) => [$vi => $variant->attribute_values ?? []])
                        );
                    </script>
                </div>
            </div>

            {{-- Variant detail (read-only) panel --}}
            <div
                x-data="variantDetailPanel()"
                x-show="open"
                x-cloak
                @click.self="open = false"
                @open-variant-detail.window="onOpen($event.detail)"
                class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
            >
                <div @click.stop class="bg-white rounded-xl shadow-xl w-full max-w-md p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.products.variant_column') }}</h3>
                        <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
                            <x-heroicon name="x-mark" class="w-5 h-5" />
                        </button>
                    </div>
                    <div x-show="loading" class="text-sm text-gray-400">{{ __('admin.loading') ?? 'Loading…' }}</div>
                    <dl x-show="!loading" class="text-sm space-y-2">
                        <div class="flex justify-between gap-4 items-center">
                            <dt class="text-gray-500">{{ __('admin.products.variant_id') ?? 'Variant UUID' }}</dt>
                            <dd class="flex items-center gap-1.5">
                                <span class="font-mono text-xs text-gray-800 truncate max-w-[10rem]" x-text="data.variant_id"></span>
                                <button type="button" class="copy-variant-url p-1 text-gray-400 hover:text-primary-600" :data-url="data.variant_id" title="{{ __('admin.products.copy_id') ?? 'Copy variant ID' }}">
                                    <x-heroicon name="clipboard-document-list" class="w-4 h-4" />
                                </button>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('admin.products.slug_column') }}</dt><dd class="font-mono text-gray-800" x-text="data.slug"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('admin.sku') }}</dt><dd class="font-mono text-gray-800" x-text="data.sku"></dd></div>
                        <div>
                            <dt class="text-gray-500 mb-1">{{ __('admin.products.variant_attributes') }}</dt>
                            <dd class="text-gray-700" x-text="data.attribute_summary || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('admin.products.images') ?? 'Images' }}</dt><dd class="text-gray-800" x-text="data.images_count"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('admin.products.listings') ?? 'Listings' }}</dt><dd class="text-gray-800" x-text="`${data.listing_count} (${data.vendor_listing_count} vendor / ${data.admin_listing_count} admin)`"></dd></div>
                        <div class="pt-2 border-t border-gray-100">
                            <dt class="text-gray-500 mb-1">{{ __('admin.products.customer_url_column') ?? 'Customer URL' }}</dt>
                            <dd class="flex items-center gap-1.5" x-show="data.customer_url">
                                <a :href="data.customer_url" target="_blank" class="font-mono text-xs text-primary-700 hover:underline truncate max-w-[14rem]" x-text="data.customer_url"></a>
                                <button type="button" class="copy-variant-url p-1 text-gray-400 hover:text-primary-600" :data-url="data.customer_url" title="{{ __('admin.products.copy_url') ?? 'Copy URL' }}">
                                    <x-heroicon name="clipboard-document-list" class="w-4 h-4" />
                                </button>
                            </dd>
                            <dd x-show="!data.customer_url" class="text-xs text-amber-700 italic">
                                {{ __('admin.products.pending_listing_hint') ?? 'No active listing yet — create a vendor listing to make this variant shoppable.' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Variant images slide-over panel --}}
            <div
                id="variant-images-panel"
                x-data="variantImagesPanel()"
                x-show="open"
                x-cloak
                @open-variant-images.window="open_panel($event.detail)"
                class="fixed inset-0 z-50 flex justify-end"
            >
                <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                <div @click.stop class="relative bg-white w-full max-w-md h-full shadow-xl flex flex-col">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800" x-text="variantName"></h3>
                        <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
                            <x-heroicon name="x-mark" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-5 space-y-4">
                        <div x-show="loading" class="text-sm text-gray-400">{{ __('admin.loading') ?? 'Loading…' }}</div>

                        <div x-show="!loading">
                            <input type="file" x-ref="fileInput" multiple accept="image/jpeg,image/png,image/webp"
                                class="hidden" @change="uploadFiles($event.target.files)" />
                            <button type="button" @click="$refs.fileInput.click()"
                                class="btn btn-outline btn-sm w-full justify-center mb-4">
                                <x-heroicon name="plus" class="w-4 h-4 mr-1" />
                                {{ __('admin.products.upload_images') ?? 'Upload images' }}
                            </button>

                            <div id="variant-images-list" class="space-y-2">
                                <template x-for="image in images" :key="image.id">
                                    <div class="variant-image-item flex items-center gap-3 p-2 border border-gray-200 rounded-lg bg-white" :data-id="image.id">
                                        <span class="drag-handle cursor-move text-gray-300">
                                            <x-heroicon name="bars-3" class="w-4 h-4" />
                                        </span>
                                        <img :src="image.url" class="w-12 h-12 object-cover rounded border border-gray-100" />
                                        <span class="flex-1 text-xs text-gray-400" x-text="image.is_primary ? '{{ __('admin.products.primary_image') ?? 'Primary' }}' : ''"></span>
                                        <button type="button" class="text-xs text-red-500 hover:underline" @click="removeImage(image.id)">
                                            {{ __('common.remove') }}
                                        </button>
                                    </div>
                                </template>
                                <p x-show="images.length === 0" class="text-sm text-gray-400 italic">
                                    {{ __('admin.products.no_variant_images') ?? 'No images for this variant yet.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Bulk upload variant images modal --}}
            <div id="bulk-upload-modal" class="fixed inset-0 z-50 hidden items-center justify-center">
                <div id="bulk-upload-backdrop" class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white w-full max-w-md rounded-xl shadow-xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.products.bulk_upload_images') ?? 'Bulk upload images' }}</h3>
                        <button type="button" id="bulk-upload-close" class="text-gray-400 hover:text-gray-600">
                            <x-heroicon name="x-mark" class="w-5 h-5" />
                        </button>
                    </div>

                    <p class="text-xs text-gray-500">
                        {{ __('admin.products.bulk_upload_hint') ?? 'Apply the same images to every variant, or only to variants matching a specific attribute value (e.g. all "Black" variants).' }}
                    </p>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.products.apply_to') ?? 'Apply to' }}</label>
                        <select id="bulk-upload-target" class="form-select text-sm w-full">
                            <option value="all">{{ __('admin.products.all_variants') ?? 'All variants' }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.products.images') ?? 'Images' }}</label>
                        <input type="file" id="bulk-upload-files" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" />
                    </div>

                    <p id="bulk-upload-status" class="text-xs text-gray-400 hidden"></p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" id="bulk-upload-cancel" class="btn btn-outline btn-sm">{{ __('admin.cancel') ?? 'Cancel' }}</button>
                        <button type="button" id="bulk-upload-apply" class="btn btn-primary btn-sm">{{ __('admin.products.upload_and_apply') ?? 'Upload & apply' }}</button>
                    </div>
                </div>
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- TAB: Images                                     --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div
                x-show="activeTab === 'images'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-4"
            >
                <p class="text-sm text-gray-500">
                    {{ __('admin.products.upload_images_hint', ['primary' => __('admin.products.primary_image')]) }}
                </p>

                {{-- FilePond mount point — initialized in products.js --}}
                <input
                    type="file"
                    id="product-images-filepond"
                    name="images[]"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    data-process-field="file"
                    data-upload-url="{{ route('admin.products.upload-image') }}"
                    data-revert-base="{{ Str::beforeLast(route('admin.products.delete-image', ['mediaId' => '__id__']), '/__id__') }}"
                />
                <script>
                    {{-- FilePond.create() replaces the input above, so cache its upload/delete URLs before that happens --}}
                    window.__productImagesUploadUrl = @json(route('admin.products.upload-image'));
                    window.__productImagesDeleteUrlBase = @json(Str::beforeLast(route('admin.products.delete-image', ['mediaId' => '__id__']), '/__id__'));
                </script>
                {{-- Existing images (edit mode) rendered as FilePond mock files via JS --}}
                @if($isEdit && isset($images) && count($images) > 0)
                @php
                    $imagesMap = $images->map(fn($img) => [
                        'id'   => $img->id,
                        'url'  => \Illuminate\Support\Facades\Storage::url($img->path),
                        'name' => basename($img->path),
                        'size' => $img->size_bytes,
                        'mime_type' => $img->mime_type,
                    ])->values()->all();
                @endphp
                <script>
                    window.existingProductImages = @json($imagesMap ?? []);
                </script>
                @else
                <script>window.existingProductImages = [];</script>
                @endif
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- TAB: Frequently bought together                 --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div
                x-show="activeTab === 'fbt'"
                x-data="fbtManager(
                    {{ $isEdit ? "'{$product->id}'" : 'null' }},
                    @js($isEdit ? route('admin.products.frequently-bought-together.index', $product->id) : null),
                    @js($isEdit ? route('admin.products.frequently-bought-together.search', $product->id) : null),
                    @js($isEdit ? route('admin.products.frequently-bought-together.add', $product->id) : null),
                    @js($isEdit ? Str::beforeLast(route('admin.products.frequently-bought-together.remove', [$product->id, '__id__']), '/__id__') : null),
                    @js($isEdit ? route('admin.products.frequently-bought-together.reorder', $product->id) : null),
                )"
                x-init="init()"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-4"
            >
                <template x-if="!productId">
                    <p class="text-sm text-gray-500">{{ __('admin.products.fbt_save_first') }}</p>
                </template>

                <template x-if="productId">
                    <div class="space-y-4">
                        <p class="text-sm text-gray-500">{{ __('admin.products.fbt_hint') }}</p>

                        <div class="relative">
                            <input type="search"
                                x-model="query"
                                @input.debounce.300ms="search()"
                                placeholder="{{ __('admin.products.fbt_search_placeholder') }}"
                                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:outline-none focus:ring-2 focus:ring-primary-500" />

                            <div x-show="results.length > 0" @click.outside="results = []"
                                class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-sm text-sm divide-y divide-gray-100 max-h-48 overflow-y-auto">
                                <template x-for="r in results" :key="r.id">
                                    <button type="button" @click="add(r)" class="w-full text-left px-3 py-2 hover:bg-gray-50" x-text="r.text"></button>
                                </template>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <template x-if="items.length === 0">
                                <p class="text-xs text-gray-400 px-2 py-3 text-center">{{ __('admin.products.fbt_empty') }}</p>
                            </template>
                            <template x-for="(item, idx) in items" :key="item.id">
                                <div class="flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2">
                                    <span class="text-sm text-gray-700" x-text="item.text"></span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="move(idx, -1)" :disabled="idx === 0" class="text-gray-400 hover:text-gray-600 disabled:opacity-30 px-1">&uarr;</button>
                                        <button type="button" @click="move(idx, 1)" :disabled="idx === items.length - 1" class="text-gray-400 hover:text-gray-600 disabled:opacity-30 px-1">&darr;</button>
                                        <button type="button" @click="remove(item)" class="text-red-400 hover:text-red-600 px-1">&times;</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- TAB: Countries                                  --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div
                x-show="activeTab === 'countries'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 shadow-sm"
            >
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 sm:px-6 py-4 border-b border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-700">{{ __('admin.products.country_availability') }}</h4>
                    <div class="flex gap-2 flex-wrap">
                        <button type="button" id="enable-all-countries"
                            class="text-xs px-3 py-1.5 rounded-md border border-gray-200 bg-white hover:bg-gray-50 text-gray-600 font-medium">
                            {{ __('admin.products.enable_all') }}
                        </button>
                        <button type="button" id="disable-all-countries"
                            class="text-xs px-3 py-1.5 rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 font-medium">
                            {{ __('admin.products.disable_all') }}
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.products.country_column') }}</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">{{ __('admin.products.available_column') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.products.name_override_en_column') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.products.name_override_ar_column') }}</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">{{ __('admin.products.cert_required_column') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($countries ?? [] as $country)
                            @php
                                $cs = isset($countrySettings) ? ($countrySettings[$country->id] ?? null) : null;
                                $cAvailable      = (bool) old("countries.{$country->id}.is_available",  $cs?->is_available  ?? true);
                                $cNameOverrideEn = old("countries.{$country->id}.name_override_en", $cs?->name_override_en ?? '');
                                $cNameOverrideAr = old("countries.{$country->id}.name_override_ar", $cs?->name_override_ar ?? '');
                                $cCert           = (bool) old("countries.{$country->id}.requires_local_cert", $cs?->requires_local_cert ?? false);
                            @endphp
                            <tr class="hover:bg-gray-50 country-row" x-data="{ avail: {{ $cAvailable ? 'true' : 'false' }} }">
                                <td class="px-6 py-3 font-medium text-gray-900">
                                    <input type="hidden" name="countries[{{ $country->id }}][country_id]" value="{{ $country->id }}">
                                    {{ $country->name_en }}
                                    <span class="text-xs text-gray-400 font-normal ml-1">
                                        ({{ $country->iso_code_2 ?? $country->iso_code_3 ?? '' }})
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <input type="hidden" name="countries[{{ $country->id }}][is_available]" value="0" class="country-avail-hidden">
                                    <input type="checkbox"
                                        name="countries[{{ $country->id }}][is_available]"
                                        value="1"
                                        x-model="avail"
                                        class="rounded text-primary-600 border-gray-300 w-4 h-4 country-avail-cb"
                                        {{ $cAvailable ? 'checked' : '' }}
                                    />
                                </td>
                                <td class="px-6 py-3">
                                    <input type="text"
                                        name="countries[{{ $country->id }}][name_override_en]"
                                        value="{{ $cNameOverrideEn }}"
                                        :disabled="!avail"
                                        placeholder="{{ __('admin.product_form.countries_placeholder.same_as_default') }}"
                                        class="form-input text-sm py-1.5 w-full disabled:opacity-40 disabled:cursor-not-allowed"
                                    />
                                </td>
                                <td class="px-6 py-3">
                                    <input type="text"
                                        name="countries[{{ $country->id }}][name_override_ar]"
                                        value="{{ $cNameOverrideAr }}"
                                        dir="rtl"
                                        :disabled="!avail"
                                        placeholder="{{ __('admin.product_form.countries_placeholder.same_as_default_ar') }}"
                                        class="form-input text-sm py-1.5 w-full disabled:opacity-40 disabled:cursor-not-allowed"
                                    />
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <input type="hidden" name="countries[{{ $country->id }}][requires_local_cert]" value="0">
                                    <input type="checkbox"
                                        name="countries[{{ $country->id }}][requires_local_cert]"
                                        value="1"
                                        class="rounded text-primary-600 border-gray-300 w-4 h-4"
                                        {{ $cCert ? 'checked' : '' }}
                                    />
                                    @if($cCert)
                                        @php
                                            $counts = ($vendorCertCounts ?? collect())[$country->id] ?? collect();
                                            $approved = $counts->where('status', 'approved')->sum('total');
                                            $pending  = $counts->where('status', 'pending')->sum('total');
                                            $rejected = $counts->where('status', 'rejected')->sum('total');
                                        @endphp
                                        <div class="text-xs mt-1 space-x-2">
                                            @if($approved) <span class="text-green-600">✓ {{ $approved }} approved</span> @endif
                                            @if($pending)  <span class="text-yellow-600">⏳ {{ $pending }} pending</span>  @endif
                                            @if($rejected) <span class="text-red-600">✗ {{ $rejected }} rejected</span>   @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400 italic">
                                    {{ __('admin.products.no_countries_configured') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- TAB: SEO                                        --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div
                x-show="activeTab === 'seo'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5"
            >
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="seo_title" class="form-label">{{ __('admin.product_form.seo_title') }}</label>
                        <span class="text-xs text-gray-400" data-char-counter="seo_title" data-max="70">0 / 70</span>
                    </div>
                    <input type="text" name="seo_title" id="seo_title"
                        value="{{ $val('seo_title') }}" maxlength="70"
                        class="form-input w-full @error('seo_title') is-invalid @enderror"
                        placeholder="{{ __('admin.product_form.seo_placeholder.title') }}"
                    />
                    @error('seo_title') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="seo_description" class="form-label">{{ __('admin.product_form.seo_description') }}</label>
                        <span class="text-xs text-gray-400" data-char-counter="seo_description" data-max="160">0 / 160</span>
                    </div>
                    <textarea name="seo_description" id="seo_description"
                        maxlength="160" rows="3"
                        class="form-textarea w-full @error('seo_description') is-invalid @enderror"
                        placeholder="{{ __('admin.product_form.seo_placeholder.description') }}">{{ $val('seo_description') }}</textarea>
                    @error('seo_description') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <x-form.slug-input
                    name="slug"
                    label="{{ __('admin.product_form.seo_slug') }}"
                    source-field="name_en"
                    :value="$val('slug')"
                    :prefix="rtrim(config('app.url'), '/') . '/products/'"
                />

                {{-- SEO preview card --}}
                <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ __('admin.product_form.search_preview') }}</p>
                    <p id="seo-preview-title" class="text-base font-medium text-blue-700 hover:underline cursor-pointer truncate">
                        {{ $val('seo_title') ?: $val('name_en') ?: __('admin.products.product_title_placeholder') }}
                    </p>
                    <p class="text-xs text-green-700 mb-1">{{ rtrim(config('app.url'), '/') }}/products/<span id="seo-preview-slug">{{ $val('slug') ?: __('admin.products.product_slug_placeholder') }}</span></p>
                    <p id="seo-preview-desc" class="text-sm text-gray-600 line-clamp-2">
                        {{ $val('seo_description') ?: __('admin.product_form.seo_placeholder.search_preview_placeholder') }}
                    </p>
                </div>
            </div>

            @if($isEdit)
                @include('admin.products._cost_tab', ['product' => $product])
            @endif

        </div>{{-- /left column --}}

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT SIDEBAR: sticky                                              --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-72 flex-shrink-0 lg:sticky lg:top-20 space-y-4">

            {{-- Status card --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.product_form.status') }}</h3>
                <x-form.select
                    name="status"
                    label=""
                    :value="$val('status', 'draft')"
                    :options="[
                        'draft'        => __('admin.product_form.Draft'),
                        'active'       => __('admin.product_form.Active'),
                        'discontinued' => __('admin.product_form.Discontinued'),
                        'restricted'   => __('admin.product_form.Restricted'),
                    ]"
                />
                @if($isEdit)
                <div class="pt-2 border-t border-gray-100 space-y-1.5 text-xs text-gray-500">
                    <div class="flex justify-between">
                        <span>{{ __('admin.products.created_label') }}</span>
                        <span>{{ \Carbon\Carbon::parse($product->created_at)->format('M j, Y') }}</span>
                    </div>
                    @if($product->updated_at && $product->updated_at !== $product->created_at)
                    <div class="flex justify-between">
                        <span>{{ __('admin.products.updated_label') }}</span>
                        <span>{{ \Carbon\Carbon::parse($product->updated_at)->format('M j, Y') }}</span>
                    </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Classification card --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-1">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('admin.classification') }}</h3>

                {{-- has_variants — synced to Alpine hasVariants --}}
                <label class="flex items-center gap-3 py-2 cursor-pointer select-none w-full group">
                    <input type="hidden" name="has_variants" value="0">
                    <input type="checkbox" name="has_variants" id="has_variants" value="1"
                        class="sr-only" x-model="hasVariants"
                        {{ $bool('has_variants') ? 'checked' : '' }}>
                    <div class="relative w-11 h-6 rounded-full transition-colors duration-200 flex-shrink-0"
                        :class="hasVariants ? 'bg-primary-600' : 'bg-gray-200'">
                        <span class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                            :class="hasVariants ? 'translate-x-5' : 'translate-x-0'"></span>
                    </div>
                    <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ __('admin.has_variant') }}</span>
                </label>

                <x-form.toggle name="is_featured"         label="{{ __('admin.featured_on_homepage') }}" :value="$bool('is_featured')" />
                <x-form.toggle name="is_hazardous"        label="{{ __('admin.is_hazardous') }}"       :value="$bool('is_hazardous')" />

                {{-- is_age_restricted — synced to Alpine isAgeRestricted --}}
                <label class="flex items-center gap-3 py-2 cursor-pointer select-none w-full group">
                    <input type="hidden" name="is_age_restricted" value="0">
                    <input type="checkbox" name="is_age_restricted" id="is_age_restricted" value="1"
                        class="sr-only" x-model="isAgeRestricted"
                        {{ $bool('is_age_restricted') ? 'checked' : '' }}>
                    <div class="relative w-11 h-6 rounded-full transition-colors duration-200 flex-shrink-0"
                        :class="isAgeRestricted ? 'bg-primary-600' : 'bg-gray-200'">
                        <span class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                            :class="isAgeRestricted ? 'translate-x-5' : 'translate-x-0'"></span>
                    </div>
                    <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ __('admin.age_restricted') }}</span>
                </label>

                <div x-show="isAgeRestricted" x-cloak class="mt-1 pl-14">
                    <x-form.input
                        name="min_age"
                        label="{{ __('admin.product_form.minimum_age') }}"
                        type="number"
                        :value="$val('min_age')"
                        min="1" max="99"
                    />
                </div>
            </div>

            {{-- Save actions card --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-2">
                @if($errors->any())
                <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-700 space-y-1">
                    @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                    @endforeach
                </div>
                @endif

                <button type="submit" id="submit-btn"
                    class="btn btn-primary w-full justify-center">
                    {{ $isEdit ? __('admin.product_form.save_changes') : __('admin.product_form.create_product') }}
                </button>
                <a href="{{ route('admin.products.index') }}"
                    class="btn btn-ghost w-full justify-center text-center block">
                    {{ __('admin.product_form.cancel') }}
                </a>
            </div>

        </div>{{-- /right sidebar --}}

    </div>{{-- /flex row --}}

    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            duplicateBarcodePrefix: @json(__('admin.products.duplicate_barcode_prefix')),
            viewProduct: @json(__('admin.products.view_product')),
            noVariantAttrsForCategory: @json(__('admin.products.no_variant_attrs_for_category')),
            selectVariantAttributeFirst: @json(__('admin.products.select_variant_attribute_first')),
            generatingEllipsis: @json(__('admin.products.generating_ellipsis')),
            generateCombinations: @json(__('admin.products.generate_combinations')),
            generateVariantsFailed: @json(__('admin.products.generate_variants_failed')),
            skuAutoGeneratePlaceholder: @json(__('admin.products.sku_auto_generate_placeholder')),
            removeLabel: @json(__('admin.products.remove')),
            highlightEnLabel: @json(__('admin.products.highlight_en_label')),
            highlightArLabel: @json(__('admin.products.highlight_ar_label')),
            highlightEnPlaceholder: @json(__('admin.products.highlight_en_placeholder')),
            highlightArPlaceholder: @json(__('admin.products.highlight_ar_placeholder')),
            regenerateSlugSuccess: @json(__('admin.products.regenerate_slug_success')),
            regenerateSlugFailed: @json(__('admin.products.regenerate_slug_failed')),
            productTitlePlaceholder: @json(__('admin.products.product_title_placeholder')),
            productSlugPlaceholder: @json(__('admin.products.product_slug_placeholder')),
            seoSearchPreviewPlaceholder: @json(__('admin.product_form.seo_placeholder.search_preview_placeholder')),
            imageUrlNotFound: @json(__('admin.products.image_url_not_found')),
            imagePreviewLoadFailed: @json(__('admin.products.image_preview_load_failed')),
            imageRevertFailed: @json(__('admin.products.image_revert_failed')),
            filepondLabelIdle: @json(__('admin.products.filepond_label_idle')),
            savingEllipsis: @json(__('admin.products.saving_ellipsis')),
            validatingEllipsis: @json(__('admin.products.validating_ellipsis')),
            productSaved: @json(__('admin.products.product_saved')),
            validationError: @json(__('admin.products.validation_error')),
            saveFailedRetry: @json(__('admin.products.save_failed_retry')),
            saveChangesBtn: @json(__('admin.product_form.save_changes')),
            urlCopied: @json(__('admin.products.url_copied') ?? 'Copied to clipboard'),
            urlCopyFailed: @json(__('admin.products.url_copy_failed') ?? 'Could not copy to clipboard'),
            pendingUrlHint: @json(__('admin.products.pending_url_hint') ?? 'Save the product to generate a URL'),
            selectImagesFirst: @json(__('admin.products.select_images_first')),
            noMatchingVariants: @json(__('admin.products.no_matching_variants')),
            bulkUploadProgress: @json(__('admin.products.bulk_upload_progress')),
            bulkUploadSuccess: @json(__('admin.products.bulk_upload_success')),
            bulkUploadFailed: @json(__('admin.products.bulk_upload_failed')),
        });

        function variantDetailPanel() {
            return {
                open: false,
                loading: false,
                data: { variant_id: '', slug: '', sku: '', attributes: [], attribute_summary: '', customer_url: null, images_count: 0, listing_count: 0, vendor_listing_count: 0, admin_listing_count: 0 },
                onOpen(detail) {
                    this.open = true;
                    this.loading = true;
                    fetch(detail.url, { headers: { 'Accept': 'application/json' } })
                        .then((res) => res.json())
                        .then((res) => {
                            this.data = res.data;
                        })
                        .finally(() => {
                            this.loading = false;
                        });
                },
            };
        }

        function syncPendingVariantImageInputs(index) {
            const images = (window.__pendingVariantImages && window.__pendingVariantImages[index]) || [];
            const $container = $(`.variant-image-ids-container[data-index="${index}"]`);
            $container.empty();
            images.forEach((img) => {
                $container.append($('<input>', { type: 'hidden', name: `variants[${index}][image_ids][]`, value: img.id }));
            });
            $(`.manage-variant-images[data-variant-index="${index}"] .variant-images-count`).text(images.length);
        }

        function variantImagesPanel() {
            return {
                open: false,
                loading: false,
                pending: false,
                variantId: null,
                variantIndex: null,
                variantName: '',
                imagesUrl: '',
                reorderUrl: '',
                uploadUrl: '',
                deleteUrlBase: '',
                images: [],
                sortable: null,

                open_panel(detail) {
                    this.open = true;
                    this.pending = !!detail.pending;
                    this.variantName = detail.variantName;

                    if (this.pending) {
                        this.loading = false;
                        this.variantIndex = detail.variantIndex;
                        this.uploadUrl = window.__productImagesUploadUrl;
                        this.deleteUrlBase = window.__productImagesDeleteUrlBase;
                        window.__pendingVariantImages = window.__pendingVariantImages || {};
                        this.images = window.__pendingVariantImages[this.variantIndex] || [];
                        this.$nextTick(() => this.initSortable());
                        return;
                    }

                    this.loading = true;
                    this.variantId = detail.variantId;
                    this.imagesUrl = detail.imagesUrl;
                    this.reorderUrl = detail.reorderUrl;
                    this.uploadUrl = detail.uploadUrl;

                    fetch(this.imagesUrl, { headers: { 'Accept': 'application/json' } })
                        .then((res) => res.json())
                        .then((res) => {
                            this.images = res.images ?? [];
                        })
                        .finally(() => {
                            this.loading = false;
                            this.$nextTick(() => this.initSortable());
                        });
                },

                initSortable() {
                    const list = document.getElementById('variant-images-list');
                    if (!list || !window.Sortable) return;
                    this.sortable?.destroy();
                    this.sortable = new window.Sortable(list, {
                        handle: '.drag-handle',
                        animation: 150,
                        onEnd: () => this.persistOrder(),
                    });
                },

                persistOrder() {
                    const orderedIds = Array.from(document.querySelectorAll('#variant-images-list .variant-image-item'))
                        .map((el) => el.dataset.id);

                    if (this.pending) {
                        const byId = new Map((window.__pendingVariantImages[this.variantIndex] || []).map((img) => [img.id, img]));
                        window.__pendingVariantImages[this.variantIndex] = orderedIds.map((id) => byId.get(id)).filter(Boolean);
                        this.images = window.__pendingVariantImages[this.variantIndex];
                        syncPendingVariantImageInputs(this.variantIndex);
                        return;
                    }

                    $.ajax({
                        url: this.reorderUrl,
                        method: 'POST',
                        data: JSON.stringify({ ordered_ids: orderedIds }),
                        contentType: 'application/json',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    }).fail(() => {
                        window.Toast?.error(window.TRANSLATIONS?.variantImagesReorderFailed || 'Failed to save image order.');
                    });
                },

                uploadFiles(fileList) {
                    if (!fileList || fileList.length === 0) return;

                    const formData = new FormData();
                    Array.from(fileList).forEach((file) => formData.append('images[]', file));
                    if (!this.pending) {
                        formData.append('variant_id', this.variantId);
                    }

                    $.ajax({
                        url: this.uploadUrl,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    }).done((res) => {
                        if (this.pending) {
                            const ids = res.ids || [];
                            const urls = res.urls || [];
                            window.__pendingVariantImages[this.variantIndex] = window.__pendingVariantImages[this.variantIndex] || [];
                            ids.forEach((id, idx) => {
                                window.__pendingVariantImages[this.variantIndex].push({ id, url: urls[idx], is_primary: false });
                            });
                            this.images = window.__pendingVariantImages[this.variantIndex];
                            syncPendingVariantImageInputs(this.variantIndex);
                            this.$nextTick(() => this.initSortable());
                        } else {
                            this.refreshImages();
                        }
                    }).fail(() => {
                        window.Toast?.error(window.TRANSLATIONS?.variantImagesUploadFailed || 'Failed to upload image.');
                    });
                },

                removeImage(imageId) {
                    const deleteUrl = this.pending
                        ? this.deleteUrlBase + '/' + imageId
                        : window.location.pathname.replace(/\/(create|[^/]+\/edit).*/, '') + '/delete-image/' + imageId;

                    $.ajax({
                        url: deleteUrl,
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    }).done(() => {
                        if (this.pending) {
                            window.__pendingVariantImages[this.variantIndex] = (window.__pendingVariantImages[this.variantIndex] || [])
                                .filter((img) => img.id !== imageId);
                            this.images = window.__pendingVariantImages[this.variantIndex];
                            syncPendingVariantImageInputs(this.variantIndex);
                            this.$nextTick(() => this.initSortable());
                        } else {
                            this.refreshImages();
                        }
                    }).fail(() => {
                        window.Toast?.error(window.TRANSLATIONS?.variantImagesDeleteFailed || 'Failed to delete image.');
                    });
                },

                refreshImages() {
                    fetch(this.imagesUrl, { headers: { 'Accept': 'application/json' } })
                        .then((res) => res.json())
                        .then((res) => {
                            this.images = res.images ?? [];
                            const $btn = $(`.manage-variant-images[data-variant-id="${this.variantId}"]`);
                            $btn.find('.variant-images-count').text(this.images.length);
                            this.$nextTick(() => this.initSortable());
                        });
                },
            };
        }

        document.addEventListener('DOMContentLoaded', function () {
            $(document).on('click', '.copy-variant-url', function () {
                const T = window.TRANSLATIONS || {};
                const value = $(this).attr('data-url');
                if (!value) return;

                const done = () => window.Toast && window.Toast.success(T.urlCopied || 'Copied to clipboard');
                const fail = () => window.Toast && window.Toast.error(T.urlCopyFailed || 'Could not copy to clipboard');

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(value).then(done).catch(fail);
                } else {
                    const tmp = document.createElement('textarea');
                    tmp.value = value;
                    tmp.style.position = 'fixed';
                    tmp.style.opacity = '0';
                    document.body.appendChild(tmp);
                    tmp.select();
                    try {
                        document.execCommand('copy');
                        done();
                    } catch (e) {
                        fail();
                    } finally {
                        document.body.removeChild(tmp);
                    }
                }
            });

            $(document).on('click', '.view-variant-detail', function () {
                const variantId = $(this).data('variant-id');
                const basePath = window.location.pathname.replace(/\/(create|[^/]+\/edit).*/, '');
                window.dispatchEvent(new CustomEvent('open-variant-detail', {
                    detail: { url: basePath + '/variants/' + variantId },
                }));
            });

            $(document).on('click', '.manage-variant-images', function () {
                if ($(this).data('pending')) {
                    window.dispatchEvent(new CustomEvent('open-variant-images', {
                        detail: {
                            pending: true,
                            variantIndex: $(this).data('variant-index'),
                            variantName: $(this).data('variant-name'),
                        },
                    }));
                    return;
                }

                window.dispatchEvent(new CustomEvent('open-variant-images', {
                    detail: {
                        variantId: $(this).data('variant-id'),
                        variantName: $(this).data('variant-name'),
                        imagesUrl: $(this).data('images-url'),
                        reorderUrl: $(this).data('reorder-url'),
                        uploadUrl: $(this).data('upload-url'),
                    },
                }));
            });
        });
    </script>

    <script>
        function fbtManager(productId, indexUrl, searchUrl, addUrl, removeUrlBase, reorderUrl) {
            return {
                productId, indexUrl, searchUrl, addUrl, removeUrlBase, reorderUrl,
                query: '',
                results: [],
                items: [],

                init() {
                    if (this.productId) {
                        this.load();
                    }
                },

                async load() {
                    try {
                        const res = await fetch(this.indexUrl, { headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                        const data = await res.json();
                        this.items = data.results ?? [];
                    } catch (e) {
                        console.error(e);
                    }
                },

                async search() {
                    if (!this.query) { this.results = []; return; }
                    try {
                        const res = await fetch(`${this.searchUrl}?q=${encodeURIComponent(this.query)}`, {
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        });
                        const data = await res.json();
                        this.results = data.results ?? [];
                    } catch (e) {
                        console.error(e);
                    }
                },

                async add(result) {
                    try {
                        const res = await fetch(this.addUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ related_product_id: result.id }),
                        });
                        const data = await res.json();
                        if (data.item) {
                            this.items.push(data.item);
                        }
                        this.query = '';
                        this.results = [];
                    } catch (e) {
                        window.Toast?.error?.(window.TRANSLATIONS?.networkError ?? 'Network error');
                    }
                },

                async remove(item) {
                    try {
                        await fetch(`${this.removeUrlBase}/${item.id}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        });
                        this.items = this.items.filter((i) => i.id !== item.id);
                    } catch (e) {
                        window.Toast?.error?.(window.TRANSLATIONS?.networkError ?? 'Network error');
                    }
                },

                move(idx, direction) {
                    const newIdx = idx + direction;
                    if (newIdx < 0 || newIdx >= this.items.length) { return; }
                    const items = [...this.items];
                    [items[idx], items[newIdx]] = [items[newIdx], items[idx]];
                    this.items = items;
                    this.persistOrder();
                },

                async persistOrder() {
                    const payload = this.items.map((item, position) => ({ id: item.id, position }));
                    try {
                        await fetch(this.reorderUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ items: payload }),
                        });
                    } catch (e) {
                        window.Toast?.error?.(window.TRANSLATIONS?.networkError ?? 'Network error');
                    }
                },
            };
        }
    </script>

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- GTIN Duplicate warning modal                                   --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    <div x-show="showDuplicate" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="showDuplicate = false"></div>
        <div class="relative bg-white rounded-xl shadow-2xl p-6 max-w-md w-full">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                    <x-heroicon name="exclamation-triangle" class="w-5 h-5 text-amber-600" />
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">{{ __('admin.products.duplicate_barcode_found') }}</h3>
                    <p class="text-sm text-gray-600 mt-1">{{ __('admin.products.duplicate_gtin_used_by') }}</p>
                    <div class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-sm font-medium text-gray-900" x-text="duplicateProduct?.name_en"></p>
                        <p class="text-xs text-gray-500 mt-0.5" x-text="'{{ __('admin.products.duplicate_status_label') }}: ' + (duplicateProduct?.status ?? '')"></p>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button type="button" @click="showDuplicate = false"
                            class="btn btn-ghost btn-sm flex-1">
                            {{ __('admin.products.continue_anyway') }}
                        </button>
                        <a :href="duplicateProduct?.url" target="_blank"
                            class="btn btn-primary btn-sm flex-1 text-center">
                            {{ __('admin.products.view_product') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>{{-- /x-data root --}}
