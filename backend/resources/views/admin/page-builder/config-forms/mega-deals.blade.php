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

    {{-- Tabs (category tabs) --}}
    <div class="mt-4">
        <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-gray-700">Category Tabs</label>
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
                    <select name="tabs[{{ $i }}][category_id]" data-async-select
                        data-config='{{ json_encode(["url" => route("admin.page-builder.search.categories"), "param" => "q", "minLength" => 0, "delay" => 300]) }}'
                        class="block w-full text-sm border border-gray-300 rounded px-2 py-1">
                        @if(!empty($tab['category_id']) && !empty($tab['category_label']))
                            <option value="{{ $tab['category_id'] }}" selected>{{ $tab['category_label'] }}</option>
                        @endif
                    </select>
                    <div class="flex items-center gap-2">
                        <input type="number" name="tabs[{{ $i }}][max_products]"
                            value="{{ $tab['max_products'] ?? 4 }}" min="2" max="20"
                            class="w-20 text-sm border border-gray-300 rounded px-2 py-1">
                        <span class="text-xs text-gray-400">max products</span>
                        <button type="button" class="ml-auto text-xs text-rose-500 remove-mega-tab">Remove</button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])

    <script>
    (function() {
        let idx = {{ count($tabs) }};
        const searchCategoriesUrl = @json(route('admin.page-builder.search.categories'));
        document.getElementById('add-mega-tab')?.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'mega-tab-row p-3 border border-gray-200 rounded-lg bg-gray-50 space-y-2';
            row.innerHTML = `
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="tabs[${idx}][label_en]" placeholder="Tab label (EN)" dir="ltr"
                        class="text-sm border border-gray-300 rounded px-2 py-1">
                    <input type="text" name="tabs[${idx}][label_ar]" placeholder="اسم التبويب" dir="rtl"
                        class="text-sm border border-gray-300 rounded px-2 py-1">
                </div>
                <select name="tabs[${idx}][category_id]" data-async-select
                    data-config='${JSON.stringify({url: searchCategoriesUrl, param: "q", minLength: 0, delay: 300})}'
                    class="block w-full text-sm border border-gray-300 rounded px-2 py-1">
                </select>
                <div class="flex items-center gap-2">
                    <input type="number" name="tabs[${idx}][max_products]" value="4" min="2" max="20"
                        class="w-20 text-sm border border-gray-300 rounded px-2 py-1">
                    <span class="text-xs text-gray-400">max products</span>
                    <button type="button" class="ml-auto text-xs text-rose-500 remove-mega-tab">Remove</button>
                </div>
            `;
            document.getElementById('mega-tabs-list').appendChild(row);
            if (window.initSelect2) window.initSelect2($(row));
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
