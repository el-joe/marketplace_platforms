{{-- Add / edit slide modal (for hero_slider blocks) --}}
<x-modal id="slide-modal" title="{{ __('admin.page_builder.slide_modal.title') }}" size="lg">
    <form id="slide-form" class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
        @csrf
        <input type="hidden" name="id" id="slide-id">
        <input type="hidden" name="page_block_id" id="slide-block-id">

        <x-form.lang-tabs id="slide-lang-tabs">
            <x-slot:en>
                <x-form.input name="title_en" label="{{ __('admin.page_builder.slide_modal.title_en') }}" dir="ltr" />
                <x-form.textarea name="subtitle_en" label="{{ __('admin.page_builder.slide_modal.subtitle_en') }}" rows="2" dir="ltr" />
                <x-form.input name="cta_label_en" label="{{ __('admin.page_builder.slide_modal.cta_label_en') }}" dir="ltr" />

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-gray-700">{{ __('admin.page_builder.slide_modal.desktop_image') }}</label>
                        <input type="hidden" name="desktop_file_id_en" id="slide-desktop-file-id-en">
                        <div id="slide-desktop-preview-en" class="hidden mb-2">
                            <img id="slide-desktop-img-en" src="" alt="{{ __('admin.page_builder.slide_modal.desktop_image') }}" class="w-full h-28 object-cover rounded border border-gray-200">
                            <button type="button" data-clear-image="desktop" data-locale="en" class="mt-1 text-xs text-rose-500 hover:text-rose-700">{{ __('admin.page_builder.slide_modal.remove') }}</button>
                        </div>
                        <label class="flex items-center justify-center gap-2 px-3 py-2 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition text-sm text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            <span>{{ __('admin.page_builder.slide_modal.upload_desktop') }}</span>
                            <input type="file" accept="image/*" class="sr-only" data-slide-upload="desktop" data-locale="en">
                        </label>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-gray-700">{{ __('admin.page_builder.slide_modal.mobile_image') }}</label>
                        <input type="hidden" name="mobile_file_id_en" id="slide-mobile-file-id-en">
                        <div id="slide-mobile-preview-en" class="hidden mb-2">
                            <img id="slide-mobile-img-en" src="" alt="{{ __('admin.page_builder.slide_modal.mobile_image') }}" class="w-full h-28 object-cover rounded border border-gray-200">
                            <button type="button" data-clear-image="mobile" data-locale="en" class="mt-1 text-xs text-rose-500 hover:text-rose-700">{{ __('admin.page_builder.slide_modal.remove') }}</button>
                        </div>
                        <label class="flex items-center justify-center gap-2 px-3 py-2 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition text-sm text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            <span>{{ __('admin.page_builder.slide_modal.upload_mobile') }}</span>
                            <input type="file" accept="image/*" class="sr-only" data-slide-upload="mobile" data-locale="en">
                        </label>
                    </div>
                </div>
            </x-slot:en>
            <x-slot:ar>
                <x-form.input name="title_ar" label="{{ __('admin.page_builder.slide_modal.title_ar') }}" dir="rtl" />
                <x-form.textarea name="subtitle_ar" label="{{ __('admin.page_builder.slide_modal.subtitle_ar') }}" rows="2" dir="rtl" />
                <x-form.input name="cta_label_ar" label="{{ __('admin.page_builder.slide_modal.cta_label_ar') }}" dir="rtl" />

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-gray-700">{{ __('admin.page_builder.slide_modal.desktop_image') }}</label>
                        <input type="hidden" name="desktop_file_id_ar" id="slide-desktop-file-id-ar">
                        <div id="slide-desktop-preview-ar" class="hidden mb-2">
                            <img id="slide-desktop-img-ar" src="" alt="{{ __('admin.page_builder.slide_modal.desktop_image') }}" class="w-full h-28 object-cover rounded border border-gray-200">
                            <button type="button" data-clear-image="desktop" data-locale="ar" class="mt-1 text-xs text-rose-500 hover:text-rose-700">{{ __('admin.page_builder.slide_modal.remove') }}</button>
                        </div>
                        <label class="flex items-center justify-center gap-2 px-3 py-2 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition text-sm text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            <span>{{ __('admin.page_builder.slide_modal.upload_desktop') }}</span>
                            <input type="file" accept="image/*" class="sr-only" data-slide-upload="desktop" data-locale="ar">
                        </label>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-gray-700">{{ __('admin.page_builder.slide_modal.mobile_image') }}</label>
                        <input type="hidden" name="mobile_file_id_ar" id="slide-mobile-file-id-ar">
                        <div id="slide-mobile-preview-ar" class="hidden mb-2">
                            <img id="slide-mobile-img-ar" src="" alt="{{ __('admin.page_builder.slide_modal.mobile_image') }}" class="w-full h-28 object-cover rounded border border-gray-200">
                            <button type="button" data-clear-image="mobile" data-locale="ar" class="mt-1 text-xs text-rose-500 hover:text-rose-700">{{ __('admin.page_builder.slide_modal.remove') }}</button>
                        </div>
                        <label class="flex items-center justify-center gap-2 px-3 py-2 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition text-sm text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            <span>{{ __('admin.page_builder.slide_modal.upload_mobile') }}</span>
                            <input type="file" accept="image/*" class="sr-only" data-slide-upload="mobile" data-locale="ar">
                        </label>
                    </div>
                </div>
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
