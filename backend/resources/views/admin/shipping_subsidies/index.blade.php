@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/select2.js'])
@endpush

@section('title', __('admin.shipping_subsidies_section.page_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.shipping_subsidies_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.shipping_subsidies_section.subtitle') }}</p>
        </div>
        <button type="button" id="btn-add-subsidy"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.shipping_subsidies_section.create_new_subsidy') }}
        </button>
    </div>

    <x-card padding="none">
        <table id="shipping-subsidies-table" class="table-base w-full">
            <thead>
                <tr>
                    <th>{{ __('admin.shipping_subsidies_section.warehouse_col') }}</th>
                    <th>{{ __('admin.shipping_subsidies_section.zone_col') }}</th>
                    <th>{{ __('admin.shipping_subsidies_section.country_col') }}</th>
                    <th>{{ __('admin.shipping_subsidies_section.method_col') }}</th>
                    <th class="text-end">{{ __('admin.shipping_subsidies_section.subsidy_cap_col') }}</th>
                    <th class="text-end">{{ __('admin.shipping_subsidies_section.max_subsidy_weight_col') }}</th>
                    <th>{{ __('admin.shipping_subsidies_section.currency_col') }}</th>
                    <th>{{ __('admin.shipping_subsidies_section.split_col') }}</th>
                    <th class="text-center">{{ __('admin.shipping_subsidies_section.status_col') }}</th>
                    <th class="text-center w-20">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </x-card>

    <x-modal id="shipping-subsidy-modal" title="{{ __('admin.shipping_subsidies_section.subsidy_configuration') }}" size="md">
        <form id="shipping-subsidy-form" novalidate x-data="{
                splitType: 'percentage',
                vendorSharePct: 50,
                vendorFixedAmount: 0,
                adminFixedAmount: 0,
                get adminSharePct() { return 100 - (parseInt(this.vendorSharePct) || 0); },
            }">
            @csrf
            <input type="hidden" id="subsidy-id">

            @if($exceptionalZones->isEmpty())
                <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                    {{ __('admin.shipping_subsidies_section.no_exceptional_zones_warning') }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4">
                <x-form.select name="warehouse_id" label="{{ __('admin.shipping_subsidies_section.warehouse_label') }}" :select2="true" required>
                    <option value="">{{ __('admin.shipping_subsidies_section.select_warehouse_placeholder') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                    @endforeach
                </x-form.select>

                <x-form.select name="shipping_zone_id" label="{{ __('admin.shipping_subsidies_section.zone_label') }}" :select2="true" required>
                    <option value="">{{ __('admin.shipping_subsidies_section.select_zone_placeholder') }}</option>
                    @foreach($exceptionalZones->groupBy(fn($z) => $z->country?->name_en ?? __('admin.shipping_subsidies_section.other_country')) as $countryName => $zonesInCountry)
                        <optgroup label="{{ $countryName }}">
                            @foreach($zonesInCountry as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-form.select>
                <p class="text-xs text-gray-500 -mt-3">{{ __('admin.shipping_subsidies_section.exceptional_zones_only_hint') }}</p>

                <x-form.select name="shipping_method_id" label="{{ __('admin.shipping_subsidies_section.method_label') }}" :select2="true" required>
                    <option value="">{{ __('admin.shipping_subsidies_section.select_method_placeholder') }}</option>
                    @foreach($methods as $method)
                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                    @endforeach
                </x-form.select>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_subsidies_section.subsidy_cap_label') }} <span class="text-danger-500">*</span></label>
                    <input type="number" name="subsidy_cap" step="1" min="0" placeholder="{{ __('admin.shipping_subsidies_section.subsidy_cap_placeholder') }}"
                           class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                    <p id="subsidy-cap-hint" class="text-xs text-gray-500 mt-1">{{ __('admin.shipping_subsidies_section.subsidy_cap_hint') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_subsidies_section.max_subsidy_weight_label') }}</label>
                    <input type="number" name="max_subsidy_weight_grams" step="1" min="0" placeholder="{{ __('admin.shipping_subsidies_section.max_subsidy_weight_placeholder') }}"
                           class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">{{ __('admin.shipping_subsidies_section.volumetric_hint') }}</p>
                </div>

                <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                    <label class="block text-sm font-medium text-gray-700">{{ __('admin.shipping_subsidies_section.split_type_label') }} <span class="text-danger-500">*</span></label>
                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="split_type" value="percentage" x-model="splitType" class="form-radio" />
                            <span class="text-sm text-gray-700">{{ __('admin.shipping_subsidies_section.split_type_percentage') }}</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="split_type" value="fixed" x-model="splitType" class="form-radio" />
                            <span class="text-sm text-gray-700">{{ __('admin.shipping_subsidies_section.split_type_fixed') }}</span>
                        </label>
                    </div>

                    <div x-show="splitType === 'percentage'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_subsidies_section.vendor_share_pct_label') }}</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="vendor_share_pct" x-model.number="vendorSharePct" min="0" max="100" step="1"
                                   class="block w-28 rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                            <span class="text-sm text-gray-500">%</span>
                            <span class="text-xs text-gray-500">{{ __('admin.shipping_subsidies_section.admin_share_computed_prefix') }} <strong x-text="adminSharePct"></strong>%</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ __('admin.shipping_subsidies_section.vendor_share_pct_hint') }}</p>
                        <div class="mt-2 p-2 bg-primary-50 border border-primary-100 rounded text-xs text-primary-700">
                            {{ __('admin.shipping_subsidies_section.gap_example_prefix') }}
                            <strong x-text="((vendorSharePct || 0) / 100).toFixed(2)"></strong> {{ __('admin.shipping_subsidies_section.gap_example_vendor') }},
                            <strong x-text="(adminSharePct / 100).toFixed(2)"></strong> {{ __('admin.shipping_subsidies_section.gap_example_admin') }}
                        </div>
                    </div>

                    <div x-show="splitType === 'fixed'" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_subsidies_section.vendor_fixed_amount_label') }}</label>
                            <input type="number" name="vendor_fixed_amount" x-model.number="vendorFixedAmount" min="0" step="1"
                                   class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_subsidies_section.admin_fixed_amount_label') }}</label>
                            <input type="number" name="admin_fixed_amount" x-model.number="adminFixedAmount" min="0" step="1"
                                   class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-500" />
                        </div>
                        <p class="text-xs text-gray-500 col-span-2">{{ __('admin.shipping_subsidies_section.fixed_amount_hint') }}</p>
                    </div>
                </div>

                <x-form.select name="currency" label="{{ __('admin.shipping_subsidies_section.currency_label') }}" required>
                    <option value="">{{ __('admin.shipping_subsidies_section.select_currency_placeholder') }}</option>
                    @foreach($currencies as $currency)
                        <option value="{{ $currency->code }}">{{ $currency->code }} — {{ $currency->name }}</option>
                    @endforeach
                </x-form.select>

                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium text-gray-700">{{ __('admin.shipping_subsidies_section.active_label') }}</label>
                    <input type="checkbox" name="is_active" value="1" checked
                           class="h-5 w-9 rounded-full appearance-none bg-gray-300 checked:bg-primary-600 relative transition-colors cursor-pointer" />
                </div>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn-secondary">{{ __('common.cancel') }}</button>
                <button type="submit" form="shipping-subsidy-form" class="btn-primary">{{ __('common.save') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

@endsection

@push('scripts')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/select2.js', 'resources/js/admin/shipping-subsidies.js'])
    <script type="module">
        window.SHIPPING_SUBSIDY_ROUTES = {
            index: @json(route('admin.shipping-subsidies.index')),
            store: @json(route('admin.shipping-subsidies.store')),
            update: @json(route('admin.shipping-subsidies.update', ['subsidy' => '__ID__'])),
            destroy: @json(route('admin.shipping-subsidies.destroy', ['subsidy' => '__ID__'])),
        };
        window.CURRENCY_SYMBOLS = @json($currencies->pluck('symbol', 'code'));
    </script>
@endpush
