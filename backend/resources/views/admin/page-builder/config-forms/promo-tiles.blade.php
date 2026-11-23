@php
    /** @var \App\Models\PageBlock|null $block */
    $tiles = $config['tiles'] ?? [];
@endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf

    <x-form.lang-tabs id="promo-tiles-block-lang-tabs">
        <x-slot:en>
            <x-form.input name="title_en" :label="__('admin.page_builder.cf.title_en')" :value="$config['title_en'] ?? ''" dir="ltr" />
        </x-slot:en>
        <x-slot:ar>
            <x-form.input name="title_ar" :label="__('admin.page_builder.cf.title_ar')" :value="$config['title_ar'] ?? ''" dir="rtl" />
        </x-slot:ar>
    </x-form.lang-tabs>

    {{-- Grid layout controls --}}
    <div class="grid grid-cols-2 gap-3 mt-3">

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">
                {{ __('admin.static_text.admin_page_builder_config_forms_promo_tiles.grid_columns') }} <span class="text-gray-400">{{ __('admin.page_builder.cf.across') }}</span>
            </label>
            <select name="grid_cols"
                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500">
                @for ($c = 1; $c <= 8; $c++)
                    <option value="{{ $c }}"
                        {{ ($config['grid_cols'] ?? 2) == $c ? 'selected' : '' }}>
                        {{ trans_choice('admin.page_builder.cf.columns_count', $c) }}
                    </option>
                @endfor
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">
                {{ __('admin.static_text.admin_page_builder_config_forms_promo_tiles.grid_rows') }} <span class="text-gray-400">{{ __('admin.page_builder.cf.down') }}</span>
            </label>
            <select name="grid_rows"
                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500">
                @for ($r = 1; $r <= 8; $r++)
                    <option value="{{ $r }}"
                        {{ ($config['grid_rows'] ?? 2) == $r ? 'selected' : '' }}>
                        {{ trans_choice('admin.page_builder.cf.rows_count_option', $r) }}
                    </option>
                @endfor
            </select>
        </div>

    </div>

    <div class="mt-4">
        <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-gray-700">{{ __('admin.page_builder.cf.tiles') }}</label>
            <button type="button" id="add-promo-tile" class="text-xs text-primary-600">{{ __('admin.static_text.admin_page_builder_config_forms_promo_tiles.add_tile') }}</button>
        </div>
        <template id="promo-tile-template">
            @include('admin.page-builder.config-forms.partials.promo-tile-row', ['i' => '__IDX__', 'tile' => []])
        </template>
        <div id="promo-tiles-list" class="space-y-2">
            @foreach($tiles as $i => $tile)
                @include('admin.page-builder.config-forms.partials.promo-tile-row', ['i' => $i, 'tile' => $tile])
            @endforeach
        </div>
    </div>

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])

    <script>
    (function() {
        let idx = {{ count($tiles) }};
        document.getElementById('add-promo-tile')?.addEventListener('click', function() {
            const tpl = document.getElementById('promo-tile-template');
            const holder = document.createElement('div');
            holder.innerHTML = tpl.innerHTML.replaceAll('__IDX__', String(idx));
            const row = holder.firstElementChild;
            document.getElementById('promo-tiles-list').appendChild(row);
            idx++;
        });
        document.getElementById('promo-tiles-list')?.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-tile')) e.target.closest('.tile-row').remove();
        });
    })();
    </script>
</form>
