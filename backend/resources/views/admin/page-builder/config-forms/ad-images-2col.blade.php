@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.title_en') }}" :value="$config['title_en'] ?? ''" dir="ltr" />
        <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    <x-form.select name="aspect_ratio" label="{{ __('admin.page_builder.config_forms.aspect_ratio') }}" :value="$config['aspect_ratio'] ?? '4:3'" class="mt-3">
        <option value="1:1">{{ __('admin.page_builder.config_forms.ratio_square') }}</option>
        <option value="4:3">{{ __('admin.page_builder.config_forms.ratio_4_3') }}</option>
        <option value="16:9">{{ __('admin.page_builder.config_forms.ratio_16_9') }}</option>
        <option value="2:1">{{ __('admin.page_builder.config_forms.ratio_2_1') }}</option>
        <option value="3:4">{{ __('admin.page_builder.config_forms.ratio_3_4') }}</option>
    </x-form.select>

    <div data-ad-images-panel data-block-id="{{ $block?->id }}" class="mt-4"></div>

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
