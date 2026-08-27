@php
    /** @var \App\Models\PageBlock|null $block */
    $savedType = !empty($config['admin_listing_id']) ? 'admin' : 'vendor';
@endphp

<form data-config-form data-block-id="{{ $block?->id }}"
      x-data="{ listingType: @js($savedType) }">
    @csrf

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="title_en" label="{{ __('admin.page_builder.config_forms.title_en') }}" :value="$config['title_en'] ?? 'Deal of the Day'" dir="ltr" />
        <x-form.input name="title_ar" label="{{ __('admin.page_builder.config_forms.title_ar') }}" :value="$config['title_ar'] ?? ''" dir="rtl" />
    </div>

    {{-- Listing type selector --}}
    <div class="mt-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('admin.page_builder.config_forms.deal_of_day.listing_type') }}
        </label>
        <div class="flex gap-3">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="_listing_type" value="vendor"
                       x-model="listingType" class="text-primary-600">
                {{ __('admin.page_builder.config_forms.deal_of_day.type_vendor') }}
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="_listing_type" value="admin"
                       x-model="listingType" class="text-primary-600">
                {{ __('admin.page_builder.config_forms.deal_of_day.type_platform') }}
            </label>
        </div>
    </div>

    {{-- Vendor listing (shown when type = vendor) --}}
    <div class="mt-3" x-show="listingType === 'vendor'" x-cloak>
        <x-form.async-select name="vendor_listing_id"
            label="{{ __('admin.page_builder.config_forms.deal_of_day.vendor_listing') }}"
            search-url="{{ route('admin.page-builder.search.vendor-listings') }}"
            :value="$savedType === 'vendor' ? ($config['vendor_listing_id'] ?? '') : ''"
            :value-label="$savedType === 'vendor' ? ($config['vendor_listing_label'] ?? null) : null"
            :min-length="0"
            help-text="{{ __('admin.page_builder.config_forms.deal_of_day.vendor_listing_help') }}" />
    </div>

    {{-- Admin / Platform listing (shown when type = admin) --}}
    <div class="mt-3" x-show="listingType === 'admin'" x-cloak>
        <x-form.async-select name="admin_listing_id"
            label="{{ __('admin.page_builder.config_forms.deal_of_day.admin_listing') }}"
            search-url="{{ route('admin.page-builder.search.admin-listings') }}"
            :value="$savedType === 'admin' ? ($config['admin_listing_id'] ?? '') : ''"
            :value-label="$savedType === 'admin' ? ($config['admin_listing_label'] ?? null) : null"
            :min-length="0"
            help-text="{{ __('admin.page_builder.config_forms.deal_of_day.admin_listing_help') }}" />
    </div>

    <x-form.date-picker name="ends_at"
        label="{{ __('admin.page_builder.config_forms.deal_of_day.deal_ends_at') }}"
        enableTime :value="$config['ends_at'] ?? ''" class="mt-3" />

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
