@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/select2.js'])
@endpush

@section('title', __('admin.warehouses_section.surcharges_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.warehouses_section.surcharges_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.warehouses_section.surcharges_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.warehouses.index') }}" class="btn-secondary btn-sm">{{ __('admin.warehouses_section.back_to_warehouses') }}</a>
            <button type="button" id="btn-add-surcharge"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('admin.warehouses_section.add_surcharge') }}
            </button>
        </div>
    </div>

    <div class="mb-4 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800">
        {{ __('admin.warehouses_section.surcharges_info_banner') }}
    </div>

    <x-card padding="none">
        <table id="warehouse-shipping-surcharges-table" class="table-base w-full">
            <thead>
                <tr>
                    <th>{{ __('admin.warehouses_section.warehouse_column') }}</th>
                    <th>{{ __('admin.warehouses_section.code_column') }}</th>
                    <th class="text-end">{{ __('admin.warehouses_section.extra_shipping_column') }}</th>
                    <th class="text-center">{{ __('admin.warehouses_section.active_column') }}</th>
                    <th class="text-center w-20">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </x-card>

    <x-modal id="warehouse-surcharge-modal" title="{{ __('admin.warehouses_section.surcharge_modal_title') }}" size="md">
        <form id="warehouse-surcharge-form" novalidate>
            @csrf
            <input type="hidden" id="surcharge-id">

            <div class="grid grid-cols-1 gap-4">
                <x-form-select name="warehouse_id" label="{{ __('admin.warehouses_section.warehouse_column') }}" :select2="true" required>
                    <option value="">{{ __('admin.warehouses_section.select_warehouse_placeholder') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                    @endforeach
                </x-form-select>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.extra_shipping_amount_label') }} <span class="text-danger-500">*</span></label>
                    <input type="number" id="surcharge-amount-display" step="0.01" min="0" placeholder="0.00"
                           class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                </div>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn-secondary">{{ __('common.cancel') }}</button>
                <button type="submit" form="warehouse-surcharge-form" class="btn-primary">{{ __('common.save') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

@endsection

@push('scripts')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/select2.js', 'resources/js/admin/warehouse-shipping-surcharges.js'])
    <script type="module">
        window.WAREHOUSE_SURCHARGE_ROUTES = {
            index: @json(route('admin.warehouses.shipping-surcharges.index')),
            store: @json(route('admin.warehouses.shipping-surcharges.store')),
            update: @json(route('admin.warehouses.shipping-surcharges.update', ['surcharge' => '__ID__'])),
            toggleActive: @json(route('admin.warehouses.shipping-surcharges.toggle-active', ['surcharge' => '__ID__'])),
        };
    </script>
@endpush
