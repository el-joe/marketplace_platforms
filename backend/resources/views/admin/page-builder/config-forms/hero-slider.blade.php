@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf

    <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
        <x-form.toggle
            name="is_announcement"
            label="Announcement Banner Mode"
            :value="$config['is_announcement'] ?? false"
        />
        <p class="text-xs text-amber-700 mt-1">
            Renders this slider as a full-width banner strip above the header (like bank offer banners).
            Only the first active slide is shown — no arrows, no dots.
        </p>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="height_desktop" label="{{ __('admin.page_builder.config_forms.hero_slider.desktop_height') }}" :value="$config['height_desktop'] ?? '480px'" />
        <x-form.input name="autoplay_seconds" type="number" label="{{ __('admin.page_builder.config_forms.hero_slider.autoplay_seconds') }}" :value="$config['autoplay_seconds'] ?? 4" min="0" />
    </div>

    <div class="grid grid-cols-3 gap-3 mt-3">
        <x-form.toggle name="show_dots"   label="{{ __('admin.page_builder.config_forms.hero_slider.dots') }}"   :value="$config['show_dots']   ?? true" />
        <x-form.toggle name="show_arrows" label="{{ __('admin.page_builder.config_forms.hero_slider.arrows') }}" :value="$config['show_arrows'] ?? true" />
        <x-form.toggle name="loop"        label="{{ __('admin.page_builder.config_forms.hero_slider.loop') }}"   :value="$config['loop']        ?? true" />
    </div>

    <x-form.select name="transition" label="{{ __('admin.page_builder.config_forms.hero_slider.transition') }}" :value="$config['transition'] ?? 'slide'" class="mt-3">
        <option value="slide">{{ __('admin.page_builder.config_forms.hero_slider.transition_slide') }}</option>
        <option value="fade">{{ __('admin.page_builder.config_forms.hero_slider.transition_fade') }}</option>
    </x-form.select>

    @include('admin.page-builder.config-forms.partials.slides-manager', ['block' => $block])
    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility',     ['block' => $block])
</form>
