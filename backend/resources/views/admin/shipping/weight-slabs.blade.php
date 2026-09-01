@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/select2.js'])
@endpush

@section('title', __('admin.weight_slabs.title'))

@section('content')

    {{-- ─── Header ─────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.weight_slabs.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.weight_slabs.subtitle') }}</p>
        </div>
        <button type="button" id="btn-add-slab"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.weight_slabs.add_slab') }}
        </button>
    </div>

    {{-- ─── Filter Bar ─────────────────────────────────────────────────────── --}}
    <x-card padding="sm" class="mb-4">
        <form id="weight-slabs-table-filter-form" class="flex flex-wrap items-end gap-3">
            <div class="w-56">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.weight_slabs.shipping_method') }}</label>
                <select name="shipping_method_id" id="filter-shipping-method" data-select2-init
                        class="block w-full rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm text-gray-900 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500">
                    <option value="">{{ __('admin.weight_slabs.all_methods') }}</option>
                    @foreach($methods as $method)
                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-56">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.weight_slabs.country') }}</label>
                <select name="country_id" id="filter-country" data-select2-init
                        class="block w-full rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm text-gray-900 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500">
                    <option value="">{{ __('admin.weight_slabs.all_countries') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" data-currency="{{ $country->currency_code }}">{{ $country->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" id="btn-apply-slab-filters"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <x-heroicon name="funnel" class="w-4 h-4" />
                    {{ __('admin.weight_slabs.apply') }}
                </button>
            </div>
        </form>
    </x-card>

    {{-- ─── DataTable ──────────────────────────────────────────────────────── --}}
    <x-card padding="none">
        <table id="weight-slabs-table" class="table-base w-full">
            <thead>
                <tr>
                    <th>{{ __('admin.weight_slabs.shipping_method') }}</th>
                    <th>{{ __('admin.weight_slabs.country') }}</th>
                    <th>{{ __('admin.weight_slabs.col_weight_range') }}</th>
                    <th class="text-end">{{ __('admin.weight_slabs.col_extra_fee') }}</th>
                    <th class="text-center">{{ __('admin.weight_slabs.col_status') }}</th>
                    <th class="text-center w-24">{{ __('admin.weight_slabs.col_actions') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </x-card>

    {{-- ─── Reference Table ────────────────────────────────────────────────── --}}
    <div class="mt-8">
        <h2 class="text-base font-semibold text-gray-900 mb-2">{{ __('admin.weight_slabs.reference_title') }}</h2>
        <p class="text-sm text-gray-500 mb-3">{{ __('admin.weight_slabs.reference_subtitle') }}</p>
        <x-card padding="none">
            <div class="overflow-x-auto">
                <table class="table-base w-full">
                    <thead>
                        <tr>
                            <th>{{ __('admin.weight_slabs.shipping_method') }}</th>
                            <th>{{ __('admin.weight_slabs.country') }}</th>
                            <th>{{ __('admin.weight_slabs.col_weight_range') }}</th>
                            <th class="text-end">{{ __('admin.weight_slabs.col_extra_fee') }}</th>
                        </tr>
                    </thead>
                    <tbody id="weight-slabs-reference-body">
                        <tr><td colspan="4" class="text-center text-sm text-gray-400 py-6">{{ __('admin.weight_slabs.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         Add / Edit Modal
         ════════════════════════════════════════════════════════════════════ --}}
    <x-modal id="slab-modal" title="{{ __('admin.weight_slabs.slab_modal_title') }}" size="lg">
        <form id="slab-form" novalidate x-data="{ openEnded: false }">
            @csrf
            <input type="hidden" id="slab-id">
            <input type="hidden" id="slab-http" value="POST">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-form-select name="shipping_method_id" label="{{ __('admin.weight_slabs.shipping_method') }}" :select2="true" required>
                        <option value="">{{ __('admin.weight_slabs.select_method') }}</option>
                        @foreach($methods as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </x-form-select>
                </div>
                <div>
                    <x-form-select name="country_id" id="slab-country" label="{{ __('admin.weight_slabs.country') }}" :select2="true" required>
                        <option value="">{{ __('admin.weight_slabs.select_country') }}</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" data-currency="{{ $country->currency_code }}">{{ $country->name_en }}</option>
                        @endforeach
                    </x-form-select>
                </div>

                <div>
                    <x-form-input name="min_weight_grams_kg" id="slab-min-weight-kg" type="number" step="0.001" min="0"
                                  label="{{ __('admin.weight_slabs.min_weight_kg') }}" placeholder="0.000" required />
                    <input type="hidden" name="min_weight_grams" id="slab-min-weight" />
                </div>
                <div>
                    <x-form-input name="max_weight_grams_kg" id="slab-max-weight-kg" type="number" step="0.001" min="0"
                                  label="{{ __('admin.weight_slabs.max_weight_kg') }}" placeholder="5.000" x-bind:disabled="openEnded" />
                    <input type="hidden" name="max_weight_grams" id="slab-max-weight" />
                    <label class="mt-1.5 flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" id="slab-open-ended" x-model="openEnded"
                               class="rounded border-gray-300 text-primary-600 focus:ring-primary-200">
                        {{ __('admin.weight_slabs.open_ended') }}
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.weight_slabs.extra_fee') }} <span class="text-danger-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="number" id="slab-fee-display" step="1" min="0" placeholder="0"
                               class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                        <span id="slab-currency-label" class="inline-flex items-center px-3 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-500">—</span>
                    </div>
                    <input type="hidden" name="extra_fee" id="slab-fee-input" />
                </div>

                <div>
                    <x-form-toggle name="is_active" label="{{ __('admin.weight_slabs.active') }}" :checked="true" />
                </div>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn-secondary">{{ __('admin.weight_slabs.cancel') }}</button>
                <button type="submit" form="slab-form" class="btn-primary">{{ __('admin.weight_slabs.save') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- Confirm Delete Modal --}}
    <x-modal id="delete-slab-modal" title="{{ __('admin.weight_slabs.delete_slab_title') }}" size="sm">
        <p class="text-sm text-gray-600">{{ __('admin.weight_slabs.delete_confirm') }}</p>
        <input type="hidden" id="delete-slab-id" />
        <x-slot:footer>
            <button type="button" data-modal-close class="btn-secondary">{{ __('admin.weight_slabs.cancel') }}</button>
            <button type="button" id="btn-confirm-delete-slab" class="btn-danger">{{ __('admin.weight_slabs.delete') }}</button>
        </x-slot:footer>
    </x-modal>

@endsection

@push('scripts')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/select2.js', 'resources/js/admin/shipping-weight-slabs.js'])
    <script type="module">
        window.SHIPPING_WEIGHT_SLABS_ROUTES = {
            datatable: @json(route('admin.shipping.weight-slabs.datatable')),
            store: @json(route('admin.shipping.weight-slabs.store')),
            update: @json(route('admin.shipping.weight-slabs.update', ['slab' => '__ID__'])),
            destroy: @json(route('admin.shipping.weight-slabs.destroy', ['slab' => '__ID__'])),
        };
    </script>
@endpush
