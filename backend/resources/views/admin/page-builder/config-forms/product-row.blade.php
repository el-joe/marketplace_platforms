@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}"
    x-data="{ source: @js($config['source'] ?? 'best_sellers') }">
    @csrf

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.title_en') }}" :value="$config['title_en'] ?? ''" dir="ltr" />
        <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    <x-form.select name="source" label="{{ __('admin.page_builder.config_forms.product_row.source') }}" :value="$config['source'] ?? 'best_sellers'" x-model="source" class="mt-3">
        <option value="best_sellers">{{ __('admin.page_builder.config_forms.product_row.source_best_sellers') }}</option>
        <option value="new_arrivals">{{ __('admin.page_builder.config_forms.product_row.source_new_arrivals') }}</option>
        <option value="top_rated">{{ __('admin.page_builder.config_forms.product_row.source_top_rated') }}</option>
        <option value="trending">{{ __('admin.page_builder.config_forms.product_row.source_trending') }}</option>
        <option value="category">{{ __('admin.page_builder.config_forms.product_row.source_category') }}</option>
        <option value="flash_sale">{{ __('admin.page_builder.config_forms.product_row.source_flash_sale') }}</option>
        <option value="manual">{{ __('admin.page_builder.config_forms.product_row.source_manual') }}</option>
        <option value="personalized">{{ __('admin.page_builder.config_forms.product_row.source_personalized') }}</option>
    </x-form.select>

    <div class="mt-3" x-show="source === 'category'" x-cloak>
        <x-form.async-select name="category_id"
            label="{{ __('admin.page_builder.config_forms.product_row.category') }}"
            search-url="{{ route('admin.page-builder.search.categories') }}"
            :value="$config['category_id'] ?? ''"
            :value-label="$config['category_label'] ?? null"
            :min-length="0" />
    </div>

    <div class="mt-3" x-show="source === 'flash_sale'" x-cloak>
        <x-form.async-select name="flash_sale_id"
            label="{{ __('admin.page_builder.config_forms.product_row.flash_sale') }}"
            search-url="{{ route('admin.page-builder.search.flash-sales') }}"
            :value="$config['flash_sale_id'] ?? ''"
            :value-label="$config['flash_sale_label'] ?? null"
            :min-length="0" />
    </div>

    <div class="grid grid-cols-3 gap-3 mt-3">
        <x-form.input name="items_per_row" type="number" label="{{ __('admin.page_builder.config_forms.product_row.items_per_row') }}" :value="$config['items_per_row'] ?? 4" min="1" max="8" />
        <x-form.input name="rows_count" type="number" label="Rows Count" :value="$config['rows_count'] ?? 1" min="1" max="4" title="Number of card rows. 1 = single horizontal scroll. 2+ = grid scroll." />
        <x-form.input name="max_products"  type="number" label="{{ __('admin.page_builder.config_forms.product_row.max_products') }}"  :value="$config['max_products']  ?? 12" min="1" max="50" />
    </div>

    <x-form.select name="card_style" label="Card Style" :value="$config['card_style'] ?? 'normal'" class="mt-3">
        <option value="normal">Normal — Standard product card with rating &amp; delivery badge</option>
        <option value="special">Special — Large image, category badge, strikethrough price</option>
    </x-form.select>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.toggle name="show_view_all"  label="{{ __('admin.page_builder.config_forms.product_row.view_all_link') }}" :value="$config['show_view_all'] ?? true" />
        <x-form.toggle name="scrollable_row" label="{{ __('admin.page_builder.config_forms.product_row.scrollable') }}"    :value="$config['scrollable_row'] ?? false" />
    </div>
    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.toggle name="show_ratings" label="{{ __('admin.page_builder.config_forms.product_row.ratings') }}" :value="$config['show_ratings'] ?? true" />
        <x-form.toggle name="show_discount_badge" label="{{ __('admin.page_builder.config_forms.product_row.discount_badge') }}" :value="$config['show_discount_badge'] ?? true" />
    </div>

    <div x-show="source === 'manual'" x-cloak>
        @include('admin.page-builder.config-forms.partials.manual-products-manager', ['block' => $block])
    </div>

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
