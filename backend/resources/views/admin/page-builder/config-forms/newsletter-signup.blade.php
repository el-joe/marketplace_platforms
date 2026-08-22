@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.title_en') }}" :value="$config['title_en'] ?? __('admin.page_builder.config_forms.newsletter_signup.title_default')" dir="ltr" />
        <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.textarea name="subtitle_en" label="{{ __('admin.page_builder.config_forms.newsletter_signup.subtitle_en') }}" rows="2" :value="$config['subtitle_en'] ?? ''" dir="ltr" />
        <x-form.textarea name="subtitle_ar" label="{{ __('admin.page_builder.config_forms.newsletter_signup.subtitle_ar') }}" rows="2" :value="$config['subtitle_ar'] ?? ''" dir="rtl" />
    </div>

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
