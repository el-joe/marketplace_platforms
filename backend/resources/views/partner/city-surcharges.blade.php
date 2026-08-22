@extends('layouts.partner')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/select2.js'])
@endpush

@section('title', __('partner.city_surcharges.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.city_surcharges.heading') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.city_surcharges.subtitle') }}</p>
        </div>
        @if($hasFbpListings)
            <button type="button" id="btn-add-surcharge"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('partner.city_surcharges.add_button') }}
            </button>
        @endif
    </div>

    @if(!$hasFbpListings)
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            {{ __('partner.city_surcharges.no_fbp_listings') }}
        </div>
    @else
        <div class="mb-4 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800">
            {{ __('partner.city_surcharges.info_banner') }}
        </div>

        <x-card padding="none">
            <table id="city-surcharges-table" class="table-base w-full">
                <thead>
                    <tr>
                        <th>{{ __('partner.city_surcharges.table.warehouse') }}</th>
                        <th>{{ __('partner.city_surcharges.table.code') }}</th>
                        <th class="text-end">{{ __('partner.city_surcharges.table.extra_shipping') }}</th>
                        <th class="text-center">{{ __('partner.city_surcharges.table.active') }}</th>
                        <th class="text-center w-20">{{ __('partner.city_surcharges.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </x-card>

        <x-modal id="city-surcharge-modal" title="{{ __('partner.city_surcharges.modal_title') }}" size="md">
            <form id="city-surcharge-form" novalidate>
                @csrf
                <input type="hidden" id="surcharge-id">

                <div class="grid grid-cols-1 gap-4">
                    <x-form-select name="warehouse_id" label="{{ __('partner.city_surcharges.form.warehouse_label') }}" :select2="true" required>
                        <option value="">{{ __('partner.city_surcharges.form.select_warehouse_placeholder') }}</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                        @endforeach
                    </x-form-select>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.city_surcharges.form.extra_amount_label') }} <span class="text-danger-500">*</span></label>
                        <input type="number" id="surcharge-amount-display" step="0.01" min="0" placeholder="0.00"
                               class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                    </div>
                </div>

                <x-slot:footer>
                    <button type="button" data-modal-close class="btn-secondary">{{ __('partner.city_surcharges.form.cancel') }}</button>
                    <button type="submit" form="city-surcharge-form" class="btn-primary">{{ __('partner.city_surcharges.form.save') }}</button>
                </x-slot:footer>
            </form>
        </x-modal>
    @endif

@endsection

@push('scripts')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/select2.js', 'resources/js/partner/city-surcharges.js'])
    <script type="module">
        window.CITY_SURCHARGE_ROUTES = {
            index: @json(route('partner.city-surcharges.index')),
            store: @json(route('partner.city-surcharges.store')),
            update: @json(route('partner.city-surcharges.update', ['surcharge' => '__ID__'])),
            toggleActive: @json(route('partner.city-surcharges.toggle-active', ['surcharge' => '__ID__'])),
        };
    </script>
@endpush
