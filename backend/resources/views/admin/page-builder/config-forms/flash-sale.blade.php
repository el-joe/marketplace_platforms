@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <div class="space-y-1">
        <label class="block text-sm font-medium text-gray-700">{{ __('admin.page_builder.config_forms.flash_sale.flash_sale') }}</label>
        <select name="flash_sale_id" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500">
            <option value="">{{ __('admin.page_builder.config_forms.flash_sale.select_placeholder') }}</option>
            @foreach ($flashSales as $fs)
                <option value="{{ $fs->id }}" {{ ($config['flash_sale_id'] ?? '') == $fs->id ? 'selected' : '' }}>
                    {{ $fs->name_en }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.input name="max_items_shown" type="number" label="{{ __('admin.page_builder.config_forms.flash_sale.max_items') }}" :value="$config['max_items_shown'] ?? 8" min="1" />
        <x-form.input name="items_per_row"   type="number" label="{{ __('admin.page_builder.config_forms.flash_sale.per_row') }}"   :value="$config['items_per_row']   ?? 4" min="1" max="8" />
    </div>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.toggle name="show_countdown" label="{{ __('admin.page_builder.config_forms.flash_sale.show_countdown') }}" :value="$config['show_countdown'] ?? true" />
        <x-form.toggle name="show_stock_bar" label="{{ __('admin.page_builder.config_forms.flash_sale.show_stock_bar') }}" :value="$config['show_stock_bar'] ?? true" />
    </div>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.input name="badge_label_en" label="{{ __('admin.page_builder.config_forms.flash_sale.badge_label_en') }}" :value="$config['badge_label_en'] ?? ''" dir="ltr" />
        <x-form.input name="badge_label_ar" label="{{ __('admin.page_builder.config_forms.flash_sale.badge_label_ar') }}" :value="$config['badge_label_ar'] ?? ''" dir="rtl" />
    </div>

    <x-form.input name="background_color" type="color" label="{{ __('admin.page_builder.config_forms.flash_sale.background_color') }}" :value="$config['background_color'] ?? '#fef3c7'" class="mt-3" />

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
