@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.title_en') }}" :value="$config['title_en'] ?? ''" dir="ltr" />
        <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    <x-form.input name="max_terms" type="number" label="{{ __('admin.page_builder.config_forms.search_trends.max_items') }}" :value="$config['max_terms'] ?? 10" min="1" max="50"
        class="mt-3" />

    <x-form.select name="source" label="{{ __('admin.page_builder.config_forms.search_trends.source') }}" :value="$config['source'] ?? 'auto'" class="mt-3">
        <option value="auto">{{ __('admin.page_builder.config_forms.search_trends.source_auto') }}</option>
        <option value="manual">{{ __('admin.page_builder.config_forms.search_trends.source_manual') }}</option>
    </x-form.select>

    <x-form.textarea name="manual_keywords" label="{{ __('admin.page_builder.config_forms.search_trends.manual_keywords') }}" rows="4"
        :value="$config['manual_keywords'] ?? ''" class="mt-3" helpText="{{ __('admin.page_builder.config_forms.search_trends.manual_keywords_help') }}" />

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.toggle name="show_category_filter" label="{{ __('admin.page_builder.config_forms.search_trends.category_filter') }}" :value="$config['show_category_filter'] ?? false" />
        <x-form.toggle name="show_icons" label="{{ __('admin.page_builder.config_forms.search_trends.show_icons') }}" :value="$config['show_icons'] ?? true" />
    </div>

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>