@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf

    <x-form.input name="video_url" label="{{ __('admin.page_builder.config_forms.video_banner.video_url') }}" :value="$config['video_url'] ?? ''"
        placeholder="{{ __('admin.page_builder.config_forms.video_banner.video_url_placeholder') }}"
        helpText="{{ __('admin.page_builder.config_forms.video_banner.video_url_help') }}" />

    @php
        $posterEn = $config['poster_url_en'] ?? $config['poster_url'] ?? '';
        $posterAr = $config['poster_url_ar'] ?? '';
    @endphp
    <x-form.lang-tabs id="video-banner-lang-tabs">
        <x-slot:en>
            <x-form.input name="poster_url_en" label="{{ __('admin.page_builder.config_forms.video_banner.thumbnail_url') }} (EN)" :value="$posterEn"
                placeholder="{{ __('admin.page_builder.config_forms.video_banner.thumbnail_url_placeholder') }}" helpText="{{ __('admin.page_builder.config_forms.video_banner.thumbnail_url_help') }}" />
            <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.video_banner.overlay_title_en') }}" :value="$config['title_en'] ?? ''" dir="ltr" />
            <x-form.input name="cta_label_en" label="{{ __('admin.page_builder.config_forms.video_banner.cta_label_en') }}" :value="$config['cta_label_en'] ?? ''" dir="ltr" />
        </x-slot:en>
        <x-slot:ar>
            <x-form.input name="poster_url_ar" label="{{ __('admin.page_builder.config_forms.video_banner.thumbnail_url') }} (AR)" :value="$posterAr"
                placeholder="{{ __('admin.page_builder.config_forms.video_banner.thumbnail_url_placeholder') }}" helpText="{{ __('admin.page_builder.config_forms.video_banner.thumbnail_url_help') }}" />
            <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.video_banner.overlay_title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
            <x-form.input name="cta_label_ar" label="{{ __('admin.page_builder.config_forms.video_banner.cta_label_ar') }}" :value="$config['cta_label_ar'] ?? ''" dir="rtl" />
        </x-slot:ar>
    </x-form.lang-tabs>

    <x-form.input name="cta_url" label="{{ __('admin.page_builder.config_forms.video_banner.cta_url') }}" :value="$config['cta_url'] ?? ''" class="mt-3"
        placeholder="{{ __('admin.page_builder.config_forms.video_banner.cta_url_placeholder') }}" />

    <x-form.select name="aspect_ratio" label="{{ __('admin.page_builder.config_forms.aspect_ratio') }}" :value="$config['aspect_ratio'] ?? '16:9'" class="mt-3">
        <option value="16:9">{{ __('admin.page_builder.config_forms.video_banner.ratio_widescreen') }}</option>
        <option value="4:3">{{ __('admin.page_builder.config_forms.video_banner.ratio_4_3') }}</option>
        <option value="1:1">{{ __('admin.page_builder.config_forms.video_banner.ratio_square') }}</option>
        <option value="21:9">{{ __('admin.page_builder.config_forms.video_banner.ratio_cinematic') }}</option>
    </x-form.select>

    <div class="grid grid-cols-3 gap-3 mt-3">
        <x-form.toggle name="autoplay" label="{{ __('admin.page_builder.config_forms.video_banner.autoplay') }}" :value="$config['autoplay'] ?? false" />
        <x-form.toggle name="muted" label="{{ __('admin.page_builder.config_forms.video_banner.muted') }}" :value="$config['muted'] ?? true" />
        <x-form.toggle name="loop" label="{{ __('admin.page_builder.config_forms.video_banner.loop') }}" :value="$config['loop'] ?? false" />
    </div>

    <p class="mt-2 text-xs text-amber-600">
        {{ __('admin.page_builder.config_forms.video_banner.autoplay_muted_warning') }}
    </p>

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>