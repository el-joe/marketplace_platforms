@php
    /** @var \App\Models\PageBlock|null $block */
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

    @include('admin.page-builder.config-forms.partials.manual-products-manager', ['block' => $block])

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
