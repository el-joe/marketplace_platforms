@extends('layouts.admin')

@section('title', __('admin.marketer_campaigns.new_campaign'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.marketer_campaigns.new_campaign') }}</h1>
    <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.marketer_campaigns.new_campaign_subtitle') }}</p>
</div>

@if(session('error'))
    <div class="mb-4 rounded-lg bg-danger-100 text-danger-800 px-4 py-3 text-sm">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('admin.marketer-campaigns.store') }}" class="flex flex-col lg:flex-row gap-6 items-start">
    @csrf

    <div class="flex-1 min-w-0 space-y-6">
        <x-card title="{{ __('admin.marketer_campaigns.vendor_and_product') }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-form.select name="vendor_id" id="vendor_id" label="{{ __('admin.marketer_campaigns.vendor_column') }}" :select2="true" required
                    :options="$vendors->mapWithKeys(fn($v) => [$v->id => $v->store_name])->toArray()"
                    placeholder="{{ __('admin.marketer_campaigns.select_vendor') }}" />

                <x-form.select name="country_id" label="{{ __('admin.marketer_campaigns.country_column') }}" :select2="true" required
                    :options="$countries->mapWithKeys(fn($c) => [$c->id => $c->name_en])->toArray()"
                    placeholder="{{ __('admin.marketer_campaigns.select_country') }}" />

                <div class="sm:col-span-2">
                    <label for="vendor_listing_id" class="block text-sm font-medium text-gray-700">{{ __('admin.marketer_campaigns.vendor_listing') }}</label>
                    <select name="vendor_listing_id" id="vendor_listing_id" data-async-select @if(!old('vendor_id')) disabled @endif
                        data-config='{{ json_encode(["url" => route("admin.marketer-campaigns.search-listings"), "param" => "search", "minLength" => 0, "delay" => 250, "vendor_id" => old('vendor_id')]) }}'
                        placeholder="{{ __('admin.marketer_campaigns.select_vendor_first') }}"
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500">
                        @if($oldVendorListing)
                            <option value="{{ $oldVendorListing->id }}" selected>
                                {{ $oldVendorListing->productVariant?->product?->name_en ?? '—' }} — {{ $oldVendorListing->id }}
                            </option>
                        @endif
                    </select>
                    <p class="text-xs text-gray-500 mt-1">{{ __('admin.marketer_campaigns.vendor_listing_hint') }}</p>
                </div>
            </div>
        </x-card>

        <x-card title="{{ __('admin.marketer_campaigns.marketers') }}">
            <x-form.select name="marketer_ids" label="{{ __('admin.marketer_campaigns.select_marketers') }}" :select2="true" :multiple="true" required
                :options="$marketers->mapWithKeys(fn($m) => [$m->id => $m->name . ' (' . ($m->isInfluencer() ? __('admin.marketer_campaigns.type_influencer') : __('admin.marketer_campaigns.type_affiliate')) . ')'])->toArray()" />
        </x-card>

        <x-card title="{{ __('admin.marketer_campaigns.commission_terms') }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-form.select name="commission_type" label="{{ __('admin.marketer_campaigns.commission_type_column') }}" required
                    :options="[
                        'fixed' => __('admin.marketer_campaigns.commission_type_fixed'),
                        'percentage' => __('admin.marketer_campaigns.commission_type_percentage'),
                        'last_click' => __('admin.marketer_campaigns.commission_type_last_click'),
                        'tiered' => __('admin.marketer_campaigns.commission_type_tiered'),
                    ]" />

                <div class="space-y-1">
                    <label for="currency" class="block text-sm font-medium text-gray-700">{{ __('admin.marketer_campaigns.currency') }} <span class="text-danger-500">*</span></label>
                    <input type="text" name="currency" id="currency" readonly required
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm bg-gray-50 cursor-not-allowed uppercase" />
                    <p class="text-xs text-gray-500 mt-1">
                        Automatically set from the selected country.
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="max_commission_budget" class="block text-sm font-medium text-gray-700">{{ __('admin.marketer_campaigns.max_commission_budget') }} <span class="text-danger-500">*</span></label>
                    <input type="number" name="max_commission_budget" id="max_commission_budget" min="0" step="1" required
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                </div>
            </div>
        </x-card>

        <x-card title="نطاق الأقسام (منتجات / سوق مفتوح)">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="space-y-2">
                    <x-form.select name="product_category_selection_mode" id="product_category_selection_mode"
                        label="أقسام المنتجات"
                        :options="['all' => 'كل الأقسام', 'include' => 'أقسام محددة (تضمين)', 'exclude' => 'كل الأقسام باستثناء']" />
                    <x-form.select name="product_category_ids" id="product_category_ids" label="اختر الأقسام" :select2="true" :multiple="true"
                        :options="$categories->mapWithKeys(fn($c) => [$c->id => $c->name_ar])->toArray()" />
                </div>

                <div class="space-y-2">
                    <x-form.select name="classified_category_selection_mode" id="classified_category_selection_mode"
                        label="أقسام السوق المفتوح"
                        :options="['all' => 'كل الأقسام', 'include' => 'أقسام محددة (تضمين)', 'exclude' => 'كل الأقسام باستثناء']" />
                    <x-form.select name="classified_category_ids" id="classified_category_ids" label="اختر الأقسام" :select2="true" :multiple="true"
                        :options="$classifiedCategories->mapWithKeys(fn($c) => [$c->id => $c->name_ar])->toArray()" />
                </div>
            </div>
        </x-card>

        <x-card title="{{ __('admin.marketer_campaigns.notes') }}">
            <div class="grid grid-cols-1 gap-5">
                <x-form-input name="title" label="{{ __('admin.marketer_campaigns.campaign_title') }}" />
                <x-form-textarea name="notes" label="{{ __('admin.marketer_campaigns.internal_notes') }}" rows="3" />
            </div>
        </x-card>
    </div>

    <div class="w-full lg:w-72 flex-shrink-0 space-y-4 lg:sticky lg:top-20">
        <x-card>
            <div class="space-y-2">
                <button type="submit" class="btn btn-primary w-full justify-center">
                    {{ __('admin.marketer_campaigns.create_campaign') }}
                </button>
                <a href="{{ route('admin.marketer-campaigns.index') }}" class="btn btn-ghost w-full justify-center">
                    {{ __('common.cancel') }}
                </a>
            </div>
        </x-card>
    </div>
</form>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var vendorSelect = document.getElementById('vendor_id');
        var listingSelect = document.getElementById('vendor_listing_id');
        var countrySelect = document.getElementById('country_id');
        var vendorCountries = @json($vendorCountries);
        var countryCurrencies = @json($countryCurrencies);
        var currencyInput = document.getElementById('currency');
        if (!vendorSelect || !listingSelect || typeof jQuery === 'undefined') return;

        // Enables/reinits the async listing selector for the given vendor. Pass
        // resetValue=false to preserve an already-selected listing (e.g. on
        // validation-error redisplay); true clears it (user actively switched vendors).
        function syncListingSelect(vendorId, resetValue) {
            var config = JSON.parse(listingSelect.getAttribute('data-config') || '{}');
            config.vendor_id = vendorId;
            listingSelect.setAttribute('data-config', JSON.stringify(config));

            jQuery(listingSelect).prop('disabled', !vendorId);

            if (vendorId) {
                jQuery(listingSelect).select2('destroy');
                listingSelect.setAttribute('placeholder', '{{ __("admin.marketer_campaigns.click_to_search_listings") }}');
                // Scope reinit to just this field — re-running initSelect2 on the whole
                // form would destroy/recreate vendor_id's own Select2 mid-handler (we're
                // inside its 'change' callback), breaking that element's DOM.
                initSelect2(jQuery(listingSelect).parent());
            }

            if (resetValue) {
                jQuery(listingSelect).val(null).trigger('change');
            }
        }

        // select2 (data-select2-init/data-async-select) fires jQuery 'change', not native DOM 'change'
        jQuery(vendorSelect).on('change', function () {
            var vendorId = vendorSelect.value;
            syncListingSelect(vendorId, true);

            if (countrySelect && vendorId && vendorCountries[vendorId]) {
                jQuery(countrySelect).val(vendorCountries[vendorId]).trigger('change');
            }
        });

        // On validation-error redisplay, the vendor is already selected but no
        // 'change' event fires for it — without this the listing selector stays
        // disabled/placeholder and the previously chosen listing is unreachable.
        if (vendorSelect.value) {
            syncListingSelect(vendorSelect.value, false);
        }

        if (countrySelect) {
            jQuery(countrySelect).on('change', function () {
                var countryId = countrySelect.value;
                if (currencyInput && countryId && countryCurrencies[countryId]) {
                    currencyInput.value = countryCurrencies[countryId];
                } else if (currencyInput) {
                    currencyInput.value = '';
                }
            });

            // Also fire once if country is already selected on page load
            // (e.g. validation error redisplay)
            if (countrySelect.value) {
                jQuery(countrySelect).trigger('change');
            }
        }
    });
</script>
@endpush

@endsection
