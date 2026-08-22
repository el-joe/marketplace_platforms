{{-- Shared visibility section used inside every block config form --}}
@php
    /** @var \App\Models\PageBlock|null $block */
    $vis = [
        'is_visible'    => $block?->is_visible ?? true,
        'visible_from'  => optional($block?->visible_from)->format('Y-m-d H:i'),
        'visible_until' => optional($block?->visible_until)->format('Y-m-d H:i'),
        'device_target' => $block?->device_target ?? 'all',
        'audience'      => $block?->audience ?? 'all',
    ];
@endphp

<section class="pt-4 mt-4 border-t border-gray-200 space-y-4" data-visibility-section>
    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.page_builder.config_forms.visibility.section_title') }}</h4>

    <x-form.toggle name="__vis_is_visible" label="{{ __('admin.page_builder.config_forms.visibility.visible') }}" :value="$vis['is_visible']" />

    <div class="grid grid-cols-2 gap-3">
        {{-- Visible From (optional) --}}
        <div class="space-y-1">
            <div class="flex items-center justify-between">
                <label for="__vis_visible_from" class="block text-sm font-medium text-gray-700">
                    {{ __('admin.page_builder.config_forms.visibility.visible_from') }}
                </label>
                <button type="button"
                        data-clear-date="__vis_visible_from"
                        class="text-xs text-gray-400 hover:text-gray-600 {{ $vis['visible_from'] ? '' : 'hidden' }}"
                        tabindex="-1">
                    ✕ {{ __('admin.page_builder.config_forms.visibility.clear') }}
                </button>
            </div>
            <x-form.date-picker name="__vis_visible_from" :value="$vis['visible_from']" enableTime
                placeholder="{{ __('admin.page_builder.config_forms.visibility.not_scheduled') }}" />
        </div>

        {{-- Visible Until (optional) --}}
        <div class="space-y-1">
            <div class="flex items-center justify-between">
                <label for="__vis_visible_until" class="block text-sm font-medium text-gray-700">
                    {{ __('admin.page_builder.config_forms.visibility.visible_until') }}
                </label>
                <button type="button"
                        data-clear-date="__vis_visible_until"
                        class="text-xs text-gray-400 hover:text-gray-600 {{ $vis['visible_until'] ? '' : 'hidden' }}"
                        tabindex="-1">
                    ✕ {{ __('admin.page_builder.config_forms.visibility.clear') }}
                </button>
            </div>
            <x-form.date-picker name="__vis_visible_until" :value="$vis['visible_until']" enableTime
                placeholder="{{ __('admin.page_builder.config_forms.visibility.not_scheduled') }}" />
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <x-form.select name="__vis_device_target" label="{{ __('admin.page_builder.config_forms.visibility.device_target') }}" :value="$vis['device_target']">
            <option value="all">{{ __('admin.page_builder.config_forms.visibility.all_devices') }}</option>
            <option value="desktop">{{ __('admin.page_builder.config_forms.visibility.desktop_only') }}</option>
            <option value="mobile">{{ __('admin.page_builder.config_forms.visibility.mobile_only') }}</option>
            <option value="app">{{ __('admin.page_builder.config_forms.visibility.app_only') }}</option>
        </x-form.select>

        <x-form.select name="__vis_audience" label="{{ __('admin.page_builder.config_forms.visibility.audience') }}" :value="$vis['audience']">
            <option value="all">{{ __('admin.page_builder.config_forms.visibility.all_visitors') }}</option>
            <option value="guest">{{ __('admin.page_builder.config_forms.visibility.guests_only') }}</option>
            <option value="logged_in">{{ __('admin.page_builder.config_forms.visibility.logged_in_users') }}</option>
            <option value="vip">{{ __('admin.page_builder.config_forms.visibility.vip_members') }}</option>
        </x-form.select>
    </div>
</section>
