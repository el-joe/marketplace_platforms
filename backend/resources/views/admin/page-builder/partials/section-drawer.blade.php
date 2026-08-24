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
                <h3 id="sd-title" class="text-sm font-semibold text-gray-900">Add section</h3>
                <p class="text-xs text-gray-500 mt-0.5">Group blocks under a labeled, styled section.</p>
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

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                <input type="text" id="sd-name" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="e.g. Featured Deals">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Section Layout</label>
                <select id="sd-layout" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="stack">Stack (blocks on top of each other)</option>
                    <option value="columns">Columns (blocks side by side)</option>
                </select>
            </div>

            <div id="sd-columns-config-row" class="hidden">
                <label class="block text-xs font-medium text-gray-600 mb-1">Column widths</label>
                <select id="sd-columns-config" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value='{"columns":2,"widths":"1/2 1/2"}'>2 columns — equal (50/50)</option>
                    <option value='{"columns":2,"widths":"1/3 2/3"}'>2 columns — narrow/wide (33/67)</option>
                    <option value='{"columns":2,"widths":"2/3 1/3"}'>2 columns — wide/narrow (67/33)</option>
                    <option value='{"columns":3,"widths":"1/3 1/3 1/3"}'>3 columns — equal (33/33/33)</option>
                    <option value='{"columns":3,"widths":"1/4 1/2 1/4"}'>3 columns — narrow/wide/narrow</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="sd-is-visible" class="rounded border-gray-300" checked>
                <label for="sd-is-visible" class="text-xs font-medium text-gray-600">Visible</label>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Background color</label>
                <input type="text" id="sd-background-color" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="#ffffff">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Background image</label>
                <input type="hidden" id="sd-background-image-url" name="background_image_url">
                <div id="sd-background-image-preview" class="hidden mb-2">
                    <img id="sd-background-image-img" src="" alt="Background image" class="w-full h-28 object-cover rounded border border-gray-200">
                    <button type="button" data-clear-section-bg-image class="mt-1 text-xs text-rose-500 hover:text-rose-700">Remove</button>
                </div>
                <label class="flex items-center justify-center gap-2 px-3 py-2 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition text-sm text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                    <span>Upload background image</span>
                    <input type="file" accept="image/*" class="sr-only" data-section-bg-upload>
                </label>
            </div>

            <div id="sd-bg-image-type-row">
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Background image type
                </label>
                <select id="sd-background-image-type"
                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="section">Section background — covers the whole section</option>
                    <option value="header">Header background — covers the title / header area only</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Padding top</label>
                    <input type="number" min="0" id="sd-padding-top" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Padding bottom</label>
                    <input type="number" min="0" id="sd-padding-bottom" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Max width</label>
                <input type="text" id="sd-max-width" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="e.g. 1200px">
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
