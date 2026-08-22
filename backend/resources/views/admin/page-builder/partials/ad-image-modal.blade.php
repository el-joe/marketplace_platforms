{{-- Add / edit ad image modal (for ad_images_2col / ad_images_4col blocks) --}}
<x-modal id="ad-image-modal" title="{{ __('admin.page_builder.ad_image_modal.title') }}" size="md">
    <form id="ad-image-form" class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
        @csrf
        <input type="hidden" name="id" id="ad-image-id">
        <input type="hidden" name="page_block_id" id="ad-image-block-id">

        <div class="space-y-1">
            <label class="block text-sm font-medium text-gray-700">{{ __('admin.page_builder.ad_image_modal.image') }}</label>
            <input type="hidden" name="file_id" id="ad-image-file-id">
            <div id="ad-image-preview" class="hidden mb-2">
                <img id="ad-image-preview-img" src="" alt="" class="w-full h-40 object-cover rounded border border-gray-200">
                <button type="button" data-clear-ad-image class="mt-1 text-xs text-rose-500 hover:text-rose-700">{{ __('admin.page_builder.ad_image_modal.remove') }}</button>
            </div>
            <label class="flex items-center justify-center gap-2 px-3 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition text-sm text-gray-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                <span>{{ __('admin.page_builder.ad_image_modal.upload') }}</span>
                <input type="file" accept="image/*" class="sr-only" data-ad-image-upload>
            </label>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <x-form.input name="title_en" label="{{ __('admin.page_builder.ad_image_modal.title_en') }}" dir="ltr" />
            <x-form.input name="title_ar" label="{{ __('admin.page_builder.ad_image_modal.title_ar') }}" dir="rtl" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <x-form.input name="subtitle_en" label="{{ __('admin.page_builder.ad_image_modal.subtitle_en') }}" dir="ltr" />
            <x-form.input name="subtitle_ar" label="{{ __('admin.page_builder.ad_image_modal.subtitle_ar') }}" dir="rtl" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <x-form.input name="badge_label_en" label="{{ __('admin.page_builder.ad_image_modal.badge_label_en') }}" dir="ltr" />
            <x-form.input name="badge_label_ar" label="{{ __('admin.page_builder.ad_image_modal.badge_label_ar') }}" dir="rtl" />
        </div>

        <x-form.input name="link_url" label="{{ __('admin.page_builder.ad_image_modal.link_url') }}" placeholder="https://…" />

        <div class="grid grid-cols-2 gap-4">
            <x-form.input name="alt_text_en" label="{{ __('admin.page_builder.ad_image_modal.alt_text_en') }}" dir="ltr" />
            <x-form.input name="alt_text_ar" label="{{ __('admin.page_builder.ad_image_modal.alt_text_ar') }}" dir="rtl" />
        </div>

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
