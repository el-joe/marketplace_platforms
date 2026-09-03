@php
    /** @var \App\Models\PageBlock|null $block */
    $tiles = $config['tiles'] ?? [];
@endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="Title (EN)" :value="$config['title_en'] ?? ''" dir="ltr" />
        <x-form.input name="title_ar" label="Title (AR)" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    {{-- Grid layout controls --}}
    <div class="grid grid-cols-2 gap-3 mt-3">

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">
                Grid Columns <span class="text-gray-400">(across)</span>
            </label>
            <select name="grid_cols"
                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500">
                @for ($c = 1; $c <= 8; $c++)
                    <option value="{{ $c }}"
                        {{ ($config['grid_cols'] ?? 2) == $c ? 'selected' : '' }}>
                        {{ $c }} {{ $c === 1 ? 'column' : 'columns' }}
                    </option>
                @endfor
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">
                Grid Rows <span class="text-gray-400">(down)</span>
            </label>
            <select name="grid_rows"
                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500">
                @for ($r = 1; $r <= 8; $r++)
                    <option value="{{ $r }}"
                        {{ ($config['grid_rows'] ?? 2) == $r ? 'selected' : '' }}>
                        {{ $r }} {{ $r === 1 ? 'row' : 'rows' }}
                    </option>
                @endfor
            </select>
        </div>

    </div>

    <div class="mt-4">
        <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-gray-700">Tiles</label>
            <button type="button" id="add-promo-tile" class="text-xs text-primary-600">+ Add Tile</button>
        </div>
        <div id="promo-tiles-list" class="space-y-2">
            @foreach($tiles as $i => $tile)
                <div class="tile-row p-3 border border-gray-200 rounded-lg bg-gray-50 space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="tiles[{{ $i }}][label_en]"
                            value="{{ $tile['label_en'] ?? '' }}" placeholder="Label (EN)" dir="ltr"
                            class="text-sm border border-gray-300 rounded px-2 py-1">
                        <input type="text" name="tiles[{{ $i }}][label_ar]"
                            value="{{ $tile['label_ar'] ?? '' }}" placeholder="التسمية" dir="rtl"
                            class="text-sm border border-gray-300 rounded px-2 py-1">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Image (EN) 🇬🇧</label>
                            <input type="hidden" data-tile-image-url name="tiles[{{ $i }}][image_url]"
                                value="{{ $tile['image_url'] ?? '' }}">
                            <div data-tile-image-preview class="{{ empty($tile['image_url']) ? 'hidden' : '' }} mb-2">
                                <img data-tile-image-img src="{{ $tile['image_url'] ?? '' }}" alt=""
                                    class="w-full h-20 object-cover rounded border border-gray-200">
                                <button type="button" data-clear-tile-image class="mt-1 text-xs text-rose-500">Remove</button>
                            </div>
                            <label class="flex items-center justify-center gap-1 px-2 py-1.5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 text-xs text-gray-500">
                                <span>Upload EN</span>
                                <input type="file" accept="image/*" class="sr-only" data-tile-image-upload>
                            </label>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Image (AR) 🇦🇪</label>
                            <input type="hidden" data-tile-image-ar-url name="tiles[{{ $i }}][image_url_ar]"
                                value="{{ $tile['image_url_ar'] ?? '' }}">
                            <div data-tile-image-ar-preview class="{{ empty($tile['image_url_ar']) ? 'hidden' : '' }} mb-2">
                                <img data-tile-image-ar-img src="{{ $tile['image_url_ar'] ?? '' }}" alt=""
                                    class="w-full h-20 object-cover rounded border border-gray-200">
                                <button type="button" data-clear-tile-image-ar class="mt-1 text-xs text-rose-500">Remove</button>
                            </div>
                            <label class="flex items-center justify-center gap-1 px-2 py-1.5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 text-xs text-gray-500">
                                <span>Upload AR</span>
                                <input type="file" accept="image/*" class="sr-only" data-tile-image-ar-upload>
                            </label>
                        </div>
                    </div>
                    <input type="text" name="tiles[{{ $i }}][link_url]"
                        value="{{ $tile['link_url'] ?? '' }}" placeholder="Link URL (e.g. /browse/product/cat-id)"
                        class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700 mt-1">
                        <input type="checkbox" name="tiles[{{ $i }}][is_paid]" value="1"
                            {{ !empty($tile['is_paid']) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-primary-600">
                        <span>Paid / <span dir="rtl">مدفوع</span></span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="tiles[{{ $i }}][badge_label_en]"
                            value="{{ $tile['badge_label_en'] ?? '' }}" placeholder="Badge (EN)"
                            class="text-sm border border-gray-300 rounded px-2 py-1">
                        <input type="text" name="tiles[{{ $i }}][badge_label_ar]"
                            value="{{ $tile['badge_label_ar'] ?? '' }}" placeholder="الشارة"
                            class="text-sm border border-gray-300 rounded px-2 py-1">
                    </div>
                    <button type="button" class="text-xs text-rose-500 remove-tile">Remove tile</button>
                </div>
            @endforeach
        </div>
    </div>

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])

    <script>
    (function() {
        let idx = {{ count($tiles) }};
        document.getElementById('add-promo-tile')?.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'tile-row p-3 border border-gray-200 rounded-lg bg-gray-50 space-y-2';
            row.innerHTML = `
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="tiles[${idx}][label_en]" placeholder="Label (EN)" dir="ltr" class="text-sm border border-gray-300 rounded px-2 py-1">
                    <input type="text" name="tiles[${idx}][label_ar]" placeholder="التسمية" dir="rtl" class="text-sm border border-gray-300 rounded px-2 py-1">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Image (EN) 🇬🇧</label>
                        <input type="hidden" data-tile-image-url name="tiles[${idx}][image_url]" value="">
                        <div data-tile-image-preview class="hidden mb-2">
                            <img data-tile-image-img src="" alt="" class="w-full h-20 object-cover rounded border border-gray-200">
                            <button type="button" data-clear-tile-image class="mt-1 text-xs text-rose-500">Remove</button>
                        </div>
                        <label class="flex items-center justify-center gap-1 px-2 py-1.5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 text-xs text-gray-500">
                            <span>Upload EN</span><input type="file" accept="image/*" class="sr-only" data-tile-image-upload>
                        </label>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Image (AR) 🇦🇪</label>
                        <input type="hidden" data-tile-image-ar-url name="tiles[${idx}][image_url_ar]" value="">
                        <div data-tile-image-ar-preview class="hidden mb-2">
                            <img data-tile-image-ar-img src="" alt="" class="w-full h-20 object-cover rounded border border-gray-200">
                            <button type="button" data-clear-tile-image-ar class="mt-1 text-xs text-rose-500">Remove</button>
                        </div>
                        <label class="flex items-center justify-center gap-1 px-2 py-1.5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 text-xs text-gray-500">
                            <span>Upload AR</span><input type="file" accept="image/*" class="sr-only" data-tile-image-ar-upload>
                        </label>
                    </div>
                </div>
                <input type="text" name="tiles[${idx}][link_url]" placeholder="Link URL" class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                <label class="flex items-center gap-2 text-sm text-gray-700 mt-1">
                    <input type="checkbox" name="tiles[${idx}][is_paid]" value="1" class="rounded border-gray-300 text-primary-600">
                    <span>Paid / <span dir="rtl">مدفوع</span></span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="tiles[${idx}][badge_label_en]" placeholder="Badge (EN)" class="text-sm border border-gray-300 rounded px-2 py-1">
                    <input type="text" name="tiles[${idx}][badge_label_ar]" placeholder="الشارة" class="text-sm border border-gray-300 rounded px-2 py-1">
                </div>
                <button type="button" class="text-xs text-rose-500 remove-tile">Remove tile</button>
            `;
            document.getElementById('promo-tiles-list').appendChild(row);
            idx++;
        });
        document.getElementById('promo-tiles-list')?.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-tile')) e.target.closest('.tile-row').remove();
        });
    })();
    </script>
</form>
