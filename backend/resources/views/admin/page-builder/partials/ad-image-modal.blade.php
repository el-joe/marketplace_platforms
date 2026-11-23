{{-- Add / edit ad image modal (for ad_images_2col / ad_images_4col blocks) --}}
<x-modal id="ad-image-modal" title="{{ __('admin.page_builder.ad_image_modal.title') }}" size="md">
    <form id="ad-image-form" class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
        @csrf
        <input type="hidden" name="id" id="ad-image-id">
        <input type="hidden" name="page_block_id" id="ad-image-block-id">

        <x-form.media-field context="ad-images" store="id" id="ad-image-media-field"
            :label="__('admin.page_builder.ad_image_modal.image')"
            :locales="['en', 'ar']" name-pattern="file_id_{locale}" id-pattern="ad-image-file-id-{locale}" aspect="aspect-[4/3]" />

        <x-form.lang-tabs id="ad-image-lang-tabs">
            <x-slot:en>
                <x-form.input name="title_en" label="{{ __('admin.page_builder.ad_image_modal.title_en') }}" dir="ltr" />
                <x-form.input name="subtitle_en" label="{{ __('admin.page_builder.ad_image_modal.subtitle_en') }}" dir="ltr" />
                <x-form.input name="badge_label_en" label="{{ __('admin.page_builder.ad_image_modal.badge_label_en') }}" dir="ltr" />
                <x-form.input name="alt_text_en" label="{{ __('admin.page_builder.ad_image_modal.alt_text_en') }}" dir="ltr" />
            </x-slot:en>
            <x-slot:ar>
                <x-form.input name="title_ar" label="{{ __('admin.page_builder.ad_image_modal.title_ar') }}" dir="rtl" />
                <x-form.input name="subtitle_ar" label="{{ __('admin.page_builder.ad_image_modal.subtitle_ar') }}" dir="rtl" />
                <x-form.input name="badge_label_ar" label="{{ __('admin.page_builder.ad_image_modal.badge_label_ar') }}" dir="rtl" />
                <x-form.input name="alt_text_ar" label="{{ __('admin.page_builder.ad_image_modal.alt_text_ar') }}" dir="rtl" />
            </x-slot:ar>
        </x-form.lang-tabs>

        <x-form.input name="link_url" label="{{ __('admin.page_builder.ad_image_modal.link_url') }}" placeholder="https://…" />

        <x-form.toggle name="link_open_new_tab" label="{{ __('admin.page_builder.ad_image_modal.open_new_tab') }}" />
        <x-form.toggle name="show_title_overlay" label="{{ __('admin.page_builder.ad_image_modal.show_title_overlay') }}" />
        <x-form.toggle name="is_active" label="{{ __('admin.page_builder.ad_image_modal.active') }}" :value="true" />
        <x-form.toggle name="is_paid" label="{{ __('admin.page_builder.ad_image_modal.is_paid') }}" />
    </form>

    <div class="px-6 py-3 border-t border-gray-200 flex justify-end gap-2 bg-gray-50 rounded-b-lg">
        <button type="button" data-modal-close
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
            {{ __('common.cancel') }}
        </button>
        <button type="submit" form="ad-image-form"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">
            {{ __('admin.page_builder.ad_image_modal.save_image') }}
        </button>
    </div>
</x-modal>
