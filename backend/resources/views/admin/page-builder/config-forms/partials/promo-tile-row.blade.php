{{-- One promo tile row. $i = index (or "__IDX__" inside the add-tile <template>), $tile = saved tile config. --}}
@php
    $tile = $tile ?? [];
    $imageEn = $tile['image_url_en'] ?? $tile['image_url'] ?? '';
    $imageAr = $tile['image_url_ar'] ?? '';
@endphp
<div class="tile-row p-3 border border-gray-200 rounded-lg bg-gray-50 space-y-2">
    <x-form.media-field context="promo-tiles"
        :locales="['en', 'ar']" name-pattern="tiles[{{ $i }}][image_url_{locale}]"
        :values="['en' => $imageEn, 'ar' => $imageAr]" aspect="aspect-[4/3]" />

    <x-form.lang-tabs id="promo-tile-lang-tabs-{{ $i }}">
        <x-slot:en>
            <input type="text" name="tiles[{{ $i }}][label_en]"
                value="{{ $tile['label_en'] ?? '' }}" placeholder="{{ __('admin.page_builder.cf.label_en') }}" dir="ltr"
                class="w-full text-sm border border-gray-300 rounded px-2 py-1">
            <input type="text" name="tiles[{{ $i }}][badge_label_en]"
                value="{{ $tile['badge_label_en'] ?? '' }}" placeholder="{{ __('admin.page_builder.cf.badge_en') }}"
                class="w-full text-sm border border-gray-300 rounded px-2 py-1">
        </x-slot:en>
        <x-slot:ar>
            <input type="text" name="tiles[{{ $i }}][label_ar]"
                value="{{ $tile['label_ar'] ?? '' }}" placeholder="{{ __('admin.static_text.admin_page_builder_config_forms_promo_tiles.label') }}" dir="rtl"
                class="w-full text-sm border border-gray-300 rounded px-2 py-1">
            <input type="text" name="tiles[{{ $i }}][badge_label_ar]"
                value="{{ $tile['badge_label_ar'] ?? '' }}" placeholder="{{ __('admin.static_text.admin_page_builder_config_forms_promo_tiles.badge') }}" dir="rtl"
                class="w-full text-sm border border-gray-300 rounded px-2 py-1">
        </x-slot:ar>
    </x-form.lang-tabs>
    <input type="text" name="tiles[{{ $i }}][link_url]"
        value="{{ $tile['link_url'] ?? '' }}" placeholder="{{ __('admin.page_builder.cf.link_url_example') }}"
        class="w-full text-sm border border-gray-300 rounded px-2 py-1">
    <label class="flex items-center gap-2 text-sm text-gray-700 mt-1">
        <input type="checkbox" name="tiles[{{ $i }}][is_paid]" value="1"
            {{ !empty($tile['is_paid']) ? 'checked' : '' }}
            class="rounded border-gray-300 text-primary-600">
        <span>{{ __('admin.page_builder.cf.paid') }}</span>
    </label>
    <button type="button" class="text-xs text-rose-500 remove-tile">{{ __('admin.static_text.admin_page_builder_config_forms_promo_tiles.remove_tile') }}</button>
</div>
