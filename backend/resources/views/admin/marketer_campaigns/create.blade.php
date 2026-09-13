@extends('layouts.admin')

@section('title', __('admin.marketer_campaigns.new_campaign'))

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
                    <x-form.select name="vendor_listing_id" id="vendor_listing_id" label="{{ __('admin.marketer_campaigns.vendor_listing') }}" :select2="true"
                        placeholder="{{ __('admin.marketer_campaigns.select_vendor_first') }}"
                        data-search-url="{{ route('admin.marketer-campaigns.search-listings') }}" />
                    <p class="text-xs text-gray-500 mt-1">{{ __('admin.marketer_campaigns.vendor_listing_hint') }}</p>
                </div>
            </div>
        </x-card>

        <x-card title="{{ __('admin.marketer_campaigns.marketers') }}">
            <x-form.select name="marketer_ids" label="{{ __('admin.marketer_campaigns.select_marketers') }}" :select2="true" :multiple="true" required
                :options="$marketers->mapWithKeys(fn($m) => [$m->id => $m->name . ' (' . ($m->marketer_type === 'influencer' ? __('admin.marketer_campaigns.type_influencer') : __('admin.marketer_campaigns.type_affiliate')) . ')'])->toArray()" />
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
                    <input type="text" name="currency" id="currency" maxlength="3" required placeholder="EGP"
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500 uppercase" />
                </div>

                <div class="space-y-1">
                    <label for="max_commission_budget" class="block text-sm font-medium text-gray-700">{{ __('admin.marketer_campaigns.max_commission_budget') }} <span class="text-danger-500">*</span></label>
                    <input type="number" name="max_commission_budget" id="max_commission_budget" min="0" step="1" required
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
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
        if (!vendorSelect || !listingSelect || typeof jQuery === 'undefined') return;

        jQuery(vendorSelect).on('change', function () {
            jQuery(listingSelect).val(null).trigger('change');
        });

        if (jQuery.fn.select2) {
            jQuery(listingSelect).select2('destroy').select2({
                width: '100%',
                placeholder: listingSelect.dataset.placeholder || '',
                ajax: {
                    url: listingSelect.dataset.searchUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            vendor_id: vendorSelect.value,
                            search: params.term,
                        };
                    },
                    processResults: function (data) {
                        return data;
                    },
                },
                minimumInputLength: 0,
            });
        }
    });
</script>
@endpush

@endsection
