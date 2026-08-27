@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="Title (EN)" :value="$config['title_en'] ?? ''" dir="ltr" />
        <x-form.input name="title_ar" label="Title (AR)" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    {{-- Row 1: Columns + Rows --}}
    <div class="grid grid-cols-2 gap-3 mt-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Columns per row</label>
            <select name="columns" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                @foreach([2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 10 => '10', 12 => '12'] as $val => $label)
                    <option value="{{ $val }}" {{ ($config['columns'] ?? 5) == $val ? 'selected' : '' }}>
                        {{ $label }} columns
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Rows visible</label>
            <select name="rows" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="1" {{ ($config['rows'] ?? 1) == 1 ? 'selected' : '' }}>1 row</option>
                <option value="2" {{ ($config['rows'] ?? 1) == 2 ? 'selected' : '' }}>2 rows</option>
                <option value="3" {{ ($config['rows'] ?? 1) == 3 ? 'selected' : '' }}>3 rows</option>
            </select>
        </div>
    </div>

    {{-- Row 2: Image shape + Card size (replaces aspect ratio with friendly presets) --}}
    <div class="grid grid-cols-2 gap-3 mt-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Image shape</label>
            <select name="image_shape" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="rounded" {{ ($config['image_shape'] ?? 'rounded') === 'rounded' ? 'selected' : '' }}>Rounded rectangle</option>
                <option value="circle"  {{ ($config['image_shape'] ?? 'rounded') === 'circle'  ? 'selected' : '' }}>Circle</option>
                <option value="square"  {{ ($config['image_shape'] ?? 'rounded') === 'square'  ? 'selected' : '' }}>Square (equal sides)</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Card height / size</label>
            <select name="size_preset" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="small"   {{ ($config['size_preset'] ?? 'medium') === 'small'   ? 'selected' : '' }}>Small — compact icons (like category pills)</option>
                <option value="medium"  {{ ($config['size_preset'] ?? 'medium') === 'medium'  ? 'selected' : '' }}>Medium — standard cards</option>
                <option value="large"   {{ ($config['size_preset'] ?? 'medium') === 'large'   ? 'selected' : '' }}>Large — tall offer/coupon cards</option>
            </select>
            <p class="mt-1 text-xs text-gray-400">
                Small = 80px | Medium = 140px | Large = 200px height per card
            </p>
        </div>
    </div>

    {{-- Aspect ratio (advanced, shown below size preset for advanced users) --}}
    <div class="mt-3">
        <label class="block text-xs font-medium text-gray-600 mb-1">
            Aspect ratio
            <span class="text-gray-400 font-normal ml-1">(overrides size preset for fixed proportions)</span>
        </label>
        <select name="aspect_ratio" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
            <option value=""    {{ ($config['aspect_ratio'] ?? '') === ''    ? 'selected' : '' }}>Auto (use size preset)</option>
            <option value="1:1" {{ ($config['aspect_ratio'] ?? '') === '1:1' ? 'selected' : '' }}>1:1 — Square</option>
            <option value="4:3" {{ ($config['aspect_ratio'] ?? '') === '4:3' ? 'selected' : '' }}>4:3 — Landscape</option>
            <option value="3:4" {{ ($config['aspect_ratio'] ?? '') === '3:4' ? 'selected' : '' }}>3:4 — Portrait (tall offer cards)</option>
            <option value="2:3" {{ ($config['aspect_ratio'] ?? '') === '2:3' ? 'selected' : '' }}>2:3 — Very tall</option>
            <option value="16:9"{{ ($config['aspect_ratio'] ?? '') === '16:9'? 'selected' : '' }}>16:9 — Wide banner</option>
            <option value="3:2" {{ ($config['aspect_ratio'] ?? '') === '3:2' ? 'selected' : '' }}>3:2 — Landscape wide</option>
        </select>
    </div>

    {{-- Toggles --}}
    <div class="flex flex-wrap gap-4 mt-3">
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="scrollable" value="1"
                {{ ($config['scrollable'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
            Horizontal scroll (slider)
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="show_label" value="1"
                {{ ($config['show_label'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
            Show label below image
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="show_badge" value="1"
                {{ ($config['show_badge'] ?? false) ? 'checked' : '' }} class="rounded border-gray-300">
            Show badge on image
        </label>
    </div>

    {{-- Images list --}}
    <div data-ad-images-panel data-block-id="{{ $block?->id }}" class="mt-4"></div>

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
