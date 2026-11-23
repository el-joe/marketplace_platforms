{{-- Add / edit slide modal (for hero_slider blocks) --}}
<x-modal id="slide-modal" title="{{ __('admin.page_builder.slide_modal.title') }}" size="lg">
    <form id="slide-form" class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
        @csrf
        <input type="hidden" name="id" id="slide-id">
        <input type="hidden" name="page_block_id" id="slide-block-id">

        <x-form.media-field context="slides" store="id" id="slide-media-field"
            :label="__('media_library.image')"
            :devices="['desktop', 'mobile']" :locales="['en', 'ar']"
            name-pattern="{device}_file_id_{locale}" id-pattern="slide-{device}-file-id-{locale}"
            :size-hints="['desktop' => '1920×600', 'mobile' => '800×800']" aspect="aspect-[16/7]" />

        <x-form.lang-tabs id="slide-lang-tabs">
            <x-slot:en>
                <x-form.input name="title_en" label="{{ __('admin.page_builder.slide_modal.title_en') }}" dir="ltr" />
                <x-form.textarea name="subtitle_en" label="{{ __('admin.page_builder.slide_modal.subtitle_en') }}" rows="2" dir="ltr" />
                <x-form.input name="cta_label_en" label="{{ __('admin.page_builder.slide_modal.cta_label_en') }}" dir="ltr" />
            </x-slot:en>
            <x-slot:ar>
                <x-form.input name="title_ar" label="{{ __('admin.page_builder.slide_modal.title_ar') }}" dir="rtl" />
                <x-form.textarea name="subtitle_ar" label="{{ __('admin.page_builder.slide_modal.subtitle_ar') }}" rows="2" dir="rtl" />
                <x-form.input name="cta_label_ar" label="{{ __('admin.page_builder.slide_modal.cta_label_ar') }}" dir="rtl" />
            </x-slot:ar>
        </x-form.lang-tabs>

        <x-form.input name="cta_url" label="{{ __('admin.page_builder.slide_modal.cta_url') }}" placeholder="{{ __('admin.page_builder.slide_modal.cta_url_placeholder') }}" />

        <div class="grid grid-cols-3 gap-4">
            <x-form.select name="text_position" label="{{ __('admin.page_builder.slide_modal.text_position') }}">
                <option value="left">{{ __('admin.page_builder.slide_modal.position_left') }}</option>
                <option value="center">{{ __('admin.page_builder.slide_modal.position_center') }}</option>
                <option value="right">{{ __('admin.page_builder.slide_modal.position_right') }}</option>
            </x-form.select>
            <x-form.input name="text_color" type="color" label="{{ __('admin.page_builder.slide_modal.text_color') }}" value="#ffffff" />
            <x-form.input name="overlay_opacity" type="number" label="{{ __('admin.page_builder.slide_modal.overlay_opacity') }}" value="0.30" step="0.05" min="0" max="1" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <x-form.date-picker name="visible_from" label="{{ __('admin.page_builder.slide_modal.visible_from') }}" enableTime />
            <x-form.date-picker name="visible_until" label="{{ __('admin.page_builder.slide_modal.visible_until') }}" enableTime />
        </div>

        <x-form.toggle name="is_active" label="{{ __('admin.page_builder.slide_modal.active') }}" :value="true" />
        <x-form.toggle name="is_paid" label="{{ __('admin.page_builder.slide_modal.is_paid') }}" />
        <x-form.toggle name="cta_open_new_tab" label="{{ __('admin.page_builder.slide_modal.open_new_tab') }}" />
    </form>

    <div class="px-6 py-3 border-t border-gray-200 flex justify-end gap-2 bg-gray-50 rounded-b-lg">
        <button type="button" data-modal-close
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
            {{ __('common.cancel') }}
        </button>
        <button type="submit" form="slide-form"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">
            {{ __('admin.page_builder.slide_modal.save_slide') }}
        </button>
    </div>
</x-modal>
