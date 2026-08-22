@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.title_en') }}" :value="$config['title_en'] ?? ''" dir="ltr" />
        <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    <x-form.date-picker name="ends_at" label="{{ __('admin.page_builder.config_forms.countdown.ends_at') }}" :value="$config['ends_at'] ?? null" enableTime />

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.input name="background_color" type="color" label="{{ __('admin.page_builder.config_forms.countdown.background_color') }}" :value="$config['background_color'] ?? '#dc2626'" />
        <x-form.input name="text_color"       type="color" label="{{ __('admin.page_builder.config_forms.countdown.text_color') }}" :value="$config['text_color']       ?? '#ffffff'" />
    </div>

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
