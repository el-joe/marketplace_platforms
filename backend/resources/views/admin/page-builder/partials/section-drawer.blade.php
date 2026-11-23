{{-- Section create/edit slide-in drawer --}}
<div id="section-drawer"
     x-data="{ open: false }"
     x-show="open"
     x-cloak
     x-on:open-section-drawer.window="open = true"
     x-on:close-section-drawer.window="open = false"
     class="fixed inset-0 z-50"
     style="display: none;">

    <div class="absolute inset-0 bg-black/30" x-on:click="open = false"></div>

    <aside class="absolute right-0 top-0 h-full w-96 bg-white shadow-xl flex flex-col"
           x-transition:enter="transition transform ease-out duration-200"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition transform ease-in duration-150"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full">

        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 id="sd-title" class="text-sm font-semibold text-gray-900">{{ __('admin.static_text.admin_page_builder_partials_section_drawer.add_section') }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.static_text.admin_page_builder_partials_section_drawer.group_blocks_under_a_labeled_styled') }}</p>
            </div>
            <button type="button" id="sd-close" x-on:click="open = false"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-4">
            <input type="hidden" id="sd-section-id" value="">

            <x-form.lang-tabs id="sd-lang-tabs">
                <x-slot:en>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.page_builder.cf.name_en') }}</label>
                        <input type="text" id="sd-name-en" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="{{ __('admin.static_text.admin_page_builder_partials_section_drawer.e_g_featured_deals') }}">
                    </div>
                </x-slot:en>
                <x-slot:ar>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">الاسم (عربي)</label>
                        <input type="text" id="sd-name-ar" dir="rtl" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="{{ __('admin.static_text.admin_page_builder_partials_section_drawer.e_g_featured_deals_2') }}">
                    </div>
                </x-slot:ar>
            </x-form.lang-tabs>

            <x-form.media-field context="section-backgrounds" id="sd-background-media-field"
                :label="__('admin.static_text.admin_page_builder_partials_section_drawer.background_image')"
                :locales="['en', 'ar']" name-pattern="background_image_url_{locale}" id-pattern="sd-background-image-url-{locale}"
                aspect="aspect-[16/6]" />

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.static_text.admin_page_builder_partials_section_drawer.section_layout') }}</label>
                <select id="sd-layout" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="stack">{{ __('admin.page_builder.cf.layout_stack') }}</option>
                    <option value="columns">{{ __('admin.page_builder.cf.layout_columns') }}</option>
                </select>
            </div>

            <div id="sd-columns-config-row" class="hidden">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.static_text.admin_page_builder_partials_section_drawer.column_widths') }}</label>
                <select id="sd-columns-config" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value='{"columns":2,"widths":"1/2 1/2"}'>{{ __('admin.page_builder.cf.cols_equal_2') }}</option>
                    <option value='{"columns":2,"widths":"1/3 2/3"}'>{{ __('admin.page_builder.cf.cols_narrow_wide') }}</option>
                    <option value='{"columns":2,"widths":"2/3 1/3"}'>{{ __('admin.page_builder.cf.cols_wide_narrow') }}</option>
                    <option value='{"columns":3,"widths":"1/3 1/3 1/3"}'>{{ __('admin.page_builder.cf.cols_equal_3') }}</option>
                    <option value='{"columns":3,"widths":"1/4 1/2 1/4"}'>{{ __('admin.page_builder.cf.cols_nwn') }}</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="sd-is-visible" class="rounded border-gray-300" checked>
                <label for="sd-is-visible" class="text-xs font-medium text-gray-600">{{ __('admin.page_builder.cf.visible') }}</label>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.static_text.admin_page_builder_partials_section_drawer.background_color') }}</label>
                <input type="text" id="sd-background-color" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="#ffffff">
            </div>

            <div id="sd-bg-image-type-row">
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    {{ __('admin.static_text.admin_page_builder_partials_section_drawer.background_image_type') }}
                </label>
                <select id="sd-background-image-type"
                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="section">{{ __('admin.static_text.admin_page_builder_partials_section_drawer.section_background_covers_the_whole_sect') }}</option>
                    <option value="header">{{ __('admin.static_text.admin_page_builder_partials_section_drawer.header_background_covers_the_title_heade') }}</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.static_text.admin_page_builder_partials_section_drawer.max_width') }}</label>
                <input type="text" id="sd-max-width" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="{{ __('admin.page_builder.cf.max_width_example') }}">
            </div>
        </div>

        <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-between gap-2">
            <button type="button" id="sd-delete"
                    class="hidden px-3 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50 rounded-lg">
                Delete
            </button>
            <div class="flex-1"></div>
            <button type="button" id="sd-cancel" x-on:click="open = false"
                    class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">
                Cancel
            </button>
            <button type="button" id="sd-save"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">
                Save
            </button>
        </div>
    </aside>
</div>
