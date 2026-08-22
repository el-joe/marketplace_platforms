@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.title_en') }}" :value="$config['title_en'] ?? ''" dir="ltr" />
        <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.input name="max_items"            type="number" label="{{ __('admin.page_builder.config_forms.category_pills.max_items') }}" :value="$config['max_items'] ?? 12" min="1" max="40" />
        <x-form.toggle name="show_product_count"  label="{{ __('admin.page_builder.config_forms.category_pills.show_product_count') }}"      :value="$config['show_product_count'] ?? true" />
    </div>

    @include('admin.page-builder.config-forms.partials.categories-manager', ['block' => $block])

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
