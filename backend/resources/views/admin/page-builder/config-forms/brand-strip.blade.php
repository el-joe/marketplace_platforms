@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <x-form.lang-tabs id="brand-strip-lang-tabs">
        <x-slot:en>
            <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.title_en') }}" :value="$config['title_en'] ?? ''" dir="ltr" />
        </x-slot:en>
        <x-slot:ar>
            <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
        </x-slot:ar>
    </x-form.lang-tabs>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.input name="max_items"      type="number" label="{{ __('admin.page_builder.config_forms.brand_strip.max_brands') }}"   :value="$config['max_items'] ?? 10" min="1" max="40" />
        <x-form.toggle name="show_logo_only" label="{{ __('admin.page_builder.config_forms.brand_strip.logos_only') }}" :value="$config['show_logo_only'] ?? true" />
    </div>

    @include('admin.page-builder.config-forms.partials.brands-manager', ['block' => $block])

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
