@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <div class="space-y-1" x-data="{ previews: @js($bannerPreviews ?? []), current: '' }" x-init="current = $refs.banner.value">
        <label class="block text-sm font-medium text-gray-700">{{ __('admin.page_builder.config_forms.full_banner.banner') }}</label>
        <select name="banner_id" required x-ref="banner" @change="current = $event.target.value" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500">
            <option value="">{{ __('admin.page_builder.config_forms.full_banner.select_banner_placeholder') }}</option>
            @foreach ($banners as $banner)
                <option value="{{ $banner->id }}" {{ ($config['banner_id'] ?? '') == $banner->id ? 'selected' : '' }}>
                    {{ $banner->name }}
                </option>
            @endforeach
        </select>
        <div x-show="current" x-cloak class="pt-2">
            <span class="block text-xs font-medium text-gray-500 mb-1">{{ __('media_library.banner_preview') }}</span>
            <img x-show="previews[current]" :src="previews[current] || ''" alt="" class="w-full max-h-40 rounded-lg border border-gray-200 object-contain bg-gray-50">
            <p x-show="!previews[current]" class="text-xs text-gray-400">{{ __('media_library.banner_no_image') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.input name="aspect_ratio"         label="{{ __('admin.page_builder.config_forms.full_banner.desktop_ratio') }}" :value="$config['aspect_ratio']        ?? '4:1'" />
        <x-form.input name="mobile_aspect_ratio"  label="{{ __('admin.page_builder.config_forms.full_banner.mobile_ratio') }}"  :value="$config['mobile_aspect_ratio'] ?? '2:1'" />
    </div>

    <div class="mt-4 text-xs text-gray-500">
        {{ __('admin.page_builder.config_forms.full_banner.select_banner_help') }}
        <a href="{{ route('admin.banners.index') }}" target="_blank" class="text-primary-600 hover:underline">{{ __('admin.page_builder.config_forms.full_banner.banners_page_link') }}</a>.
        {{ __('admin.page_builder.config_forms.full_banner.link_managed_on_banner') }}
    </div>

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
