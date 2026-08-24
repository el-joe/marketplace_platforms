@php
    /** @var \App\Models\PageBlock|null $block */
    $tabs = $config['tabs'] ?? [];
@endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="Title (EN)" :value="$config['title_en'] ?? 'Mega Deals'" dir="ltr" />
        <x-form.input name="title_ar" label="Title (AR)" :value="$config['title_ar'] ?? 'عروض ميجا'" dir="rtl" />
    </div>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.date-picker name="ends_at" label="Countdown ends at" enableTime :value="$config['ends_at'] ?? ''" />
        <x-form.select name="columns" label="Products per row"
            :value="$config['columns'] ?? 2"
            :options="['2' => '2 columns (2×2 grid)', '3' => '3 columns', '4' => '4 columns']" />
    </div>

    <label class="flex items-center gap-2 mt-3 text-sm cursor-pointer">
        <input type="checkbox" name="show_countdown" value="1"
            {{ ($config['show_countdown'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
        Show countdown timer
    </label>
    <label class="flex items-center gap-2 mt-2 text-sm cursor-pointer">
        <input type="checkbox" name="show_view_all" value="1"
            {{ ($config['show_view_all'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
        Show "View All" button
    </label>

    {{-- Tabs (each tab has its own manually-selected product list) --}}
    <div class="mt-4">
        <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-gray-700">Product Tabs</label>
            <button type="button" id="add-mega-tab"
                class="text-xs text-primary-600 hover:text-primary-700">+ Add Tab</button>
        </div>
        <div id="mega-tabs-list" class="space-y-2">
            @foreach($tabs as $i => $tab)
                <div class="mega-tab-row p-3 border border-gray-200 rounded-lg bg-gray-50 space-y-2"
                     data-index="{{ $i }}">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="tabs[{{ $i }}][label_en]"
                            value="{{ $tab['label_en'] ?? '' }}"
                            placeholder="Tab label (EN)" dir="ltr"
                            class="text-sm border border-gray-300 rounded px-2 py-1">
                        <input type="text" name="tabs[{{ $i }}][label_ar]"
                            value="{{ $tab['label_ar'] ?? '' }}"
                            placeholder="اسم التبويب" dir="rtl"
                            class="text-sm border border-gray-300 rounded px-2 py-1">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="number" name="tabs[{{ $i }}][max_products]"
                            value="{{ $tab['max_products'] ?? 4 }}" min="2" max="20"
                            class="w-20 text-sm border border-gray-300 rounded px-2 py-1">
                        <span class="text-xs text-gray-400">max products</span>
                        <button type="button" class="ml-auto text-xs text-rose-500 remove-mega-tab">Remove</button>
                    </div>

                    @include('admin.page-builder.config-forms.partials.mega-tab-products', ['block' => $block, 'tabIndex' => $i])
                </div>
            @endforeach
        </div>
    </div>

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])

    <script>
    (function() {
        let idx = {{ count($tabs) }};
        document.getElementById('add-mega-tab')?.addEventListener('click', function() {
            const i = idx;
            const row = document.createElement('div');
            row.className = 'mega-tab-row p-3 border border-gray-200 rounded-lg bg-gray-50 space-y-2';
            row.dataset.index = i;
            row.innerHTML = `
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="tabs[${i}][label_en]" placeholder="Tab label (EN)" dir="ltr"
                        class="text-sm border border-gray-300 rounded px-2 py-1">
                    <input type="text" name="tabs[${i}][label_ar]" placeholder="اسم التبويب" dir="rtl"
                        class="text-sm border border-gray-300 rounded px-2 py-1">
                </div>
                <div class="flex items-center gap-2">
                    <input type="number" name="tabs[${i}][max_products]" value="4" min="2" max="20"
                        class="w-20 text-sm border border-gray-300 rounded px-2 py-1">
                    <span class="text-xs text-gray-400">max products</span>
                    <button type="button" class="ml-auto text-xs text-rose-500 remove-mega-tab">Remove</button>
                </div>
                <section class="pt-3 mt-1 border-t border-gray-200" data-mega-tab-products data-tab-index="${i}" data-block-id="{{ $block?->id }}">
                    <div class="relative mb-2">
                        <input type="search"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            placeholder="Search products…" data-action="search-mega-tab-products" data-tab-index="${i}" data-block-id="{{ $block?->id }}" />
                        <svg class="w-4 h-4 absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                        </svg>
                    </div>
                    <div data-mega-tab-product-search-results data-tab-index="${i}" data-block-id="{{ $block?->id }}"
                        class="hidden mb-2 rounded-lg border border-gray-200 bg-white shadow-sm text-sm divide-y divide-gray-100 max-h-48 overflow-y-auto"></div>
                    <div data-mega-tab-products-list data-tab-index="${i}" data-block-id="{{ $block?->id }}" class="space-y-1 text-sm text-gray-500">
                        <div class="text-xs text-gray-400 px-2 py-3 text-center">No products added yet.</div>
                    </div>
                </section>
            `;
            document.getElementById('mega-tabs-list').appendChild(row);
            idx++;
        });
        document.getElementById('mega-tabs-list')?.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-mega-tab')) {
                e.target.closest('.mega-tab-row').remove();
            }
        });
    })();
    </script>
</form>
