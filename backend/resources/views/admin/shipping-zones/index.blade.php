@extends('layouts.admin')

@section('title', __('admin.shipping_section.zones_title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            saving: @json(__('admin.shipping_section.saving')),
            save: @json(__('common.save')),
            saveAssignment: @json(__('admin.shipping_section.save_assignment')),
            calculating: @json(__('admin.shipping_section.calculating')),
            calculate: @json(__('admin.shipping_section.calculate')),
            active: @json(__('common.active')),
            inactive: @json(__('common.inactive')),
            any: @json(__('admin.shipping_section.any_label')),
            edit: @json(__('common.edit')),
            failedToLoadRates: @json(__('admin.shipping_section.failed_to_load_rates')),
            bulkActionFailed: @json(__('admin.shipping_section.bulk_action_failed')),
            deleteRatesConfirmTitle: @json(__('admin.shipping_section.delete_rates_confirm_title')),
            actionCannotBeUndone: @json(__('admin.shipping_section.action_cannot_be_undone')),
            deleteAll: @json(__('admin.shipping_section.delete_all')),
            selectZoneFirst: @json(__('admin.shipping_section.select_zone_first')),
            addShippingRateTitle: @json(__('admin.shipping_section.add_shipping_rate_title')),
            editShippingRateTitle: @json(__('admin.shipping_section.edit_shipping_rate_title')),
            toggleFailed: @json(__('admin.shipping_section.toggle_failed')),
            deleteThisRateConfirm: @json(__('admin.shipping_section.delete_this_rate_confirm')),
            delete: @json(__('common.delete')),
            rateDeleted: @json(__('admin.shipping_section.rate_deleted')),
            deleteFailed: @json(__('admin.shipping_section.delete_failed')),
            addShippingZoneTitle: @json(__('admin.shipping_section.add_shipping_zone_title')),
            editZoneTitlePrefix: @json(__('admin.shipping_section.edit_zone_title_prefix')),
            zoneSaved: @json(__('admin.shipping_section.zone_saved')),
            deleteZoneConfirmTitle: @json(__('admin.shipping_section.delete_zone_confirm_title')),
            deleteZoneConfirmText: @json(__('admin.shipping_section.delete_zone_confirm_text')),
            deleteZoneLabel: @json(__('admin.shipping_section.delete_zone_label')),
            zoneDeleted: @json(__('admin.shipping_section.zone_deleted')),
            cannotDeleteZoneHasRates: @json(__('admin.shipping_section.cannot_delete_zone_has_rates')),
            failedToLoadCities: @json(__('admin.shipping_section.failed_to_load_cities')),
            failedToSaveAssignment: @json(__('admin.shipping_section.failed_to_save_assignment')),
            copyOfPrefix: @json(__('admin.shipping_section.copy_of_prefix')),
            enterAName: @json(__('admin.shipping_section.enter_a_name')),
            failedToDuplicate: @json(__('admin.shipping_section.failed_to_duplicate')),
            selectSourceZone: @json(__('admin.shipping_section.select_source_zone_warning')),
            sourceTargetSameWarning: @json(__('admin.shipping_section.source_target_same_warning')),
            failedToCopyRates: @json(__('admin.shipping_section.failed_to_copy_rates')),
            selectZoneAndMethod: @json(__('admin.shipping_section.select_zone_and_method')),
            noRateFound: @json(__('admin.shipping_section.no_rate_found')),
            noActiveRateConfigured: @json(__('admin.shipping_section.no_active_rate_configured')),
            freeLabel: @json(__('admin.shipping_section.free_label')),
            viaLabel: @json(__('admin.shipping_section.via_label')),
            dayLabel: @json(__('admin.shipping_section.day_label')),
            freeShippingAvailableFrom: @json(__('admin.shipping_section.free_shipping_available_from')),
            calculationFailed: @json(__('admin.shipping_section.calculation_failed')),
            noCitiesAssignedYet: @json(__('admin.shipping_section.no_cities_assigned_yet')),
            noAvailableCities: @json(__('admin.shipping_section.no_available_cities')),
            failedToSave: @json(__('admin.shipping_section.failed_to_save')),
        });
    </script>
    @vite('resources/js/admin/shipping-zones.js')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
@endpush

@section('content')

    {{-- Page header --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.shipping_section.zones_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __('admin.shipping_section.zones_desc') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-export-dropdown />
            <button id="open-add-zone-btn" class="btn btn-primary btn-sm">
                {{ __('admin.shipping_section.add_zone') }}
            </button>
            <button id="open-rate-calc-btn" class="btn btn-ghost btn-sm">
                &#x1F5A9; {{ __('admin.shipping_section.rate_calculator') }}
            </button>
        </div>
    </div>

    {{-- Search filter --}}
    <form method="GET" action="{{ route('admin.shipping-zones.index') }}" class="flex items-end gap-3 mb-4">
        <input type="hidden" name="country" value="{{ $activeCountryId }}">
        <div class="space-y-1">
            <label for="zone-search" class="block text-xs font-medium text-gray-600 uppercase tracking-wide">
                {{ __('common.search') }}
            </label>
            <input type="text" id="zone-search" name="search" value="{{ request('search') }}"
                placeholder="{{ __('admin.shipping_section.zones_label') }}" class="form-input text-sm w-64">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.search') }}</button>
    </form>

    {{-- Country tab switcher --}}
    <div class="flex gap-1 mb-6 border-b border-gray-200 overflow-x-auto">
        @foreach($countries as $country)
            <a href="{{ route('admin.shipping-zones.index') }}?country={{ $country->id }}" class="flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 -mb-px transition
                                  {{ $activeCountryId === $country->id
                ? 'border-primary-600 text-primary-700'
                : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                <span>{{ $country->flag_emoji ?? '🌍' }}</span>
                {{ $country->name_en }}
                <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full
                                         {{ $activeCountryId === $country->id
                ? 'bg-primary-100 text-primary-700'
                : 'bg-gray-100 text-gray-500' }}">
                    {{ \App\Models\ShippingZone::where('country_id', $country->id)->whereNull('deleted_at')->count() }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- Two-panel layout --}}
    <div class="grid grid-cols-12 gap-6 pb-12" x-data="{ selectedZone: null, activeTab: 'rates' }">

        {{-- ══ LEFT: Zones list (4/12) ══ --}}
        <div class="col-span-12 lg:col-span-4">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-900">
                        {{ __('admin.shipping_section.zones_label') }}
                        <span class="text-gray-400 font-normal ml-1">({{ $zones->count() }})</span>
                    </p>
                    @if($unassignedCities->count() > 0)
                        <span class="text-xs text-yellow-700 bg-yellow-50 px-2 py-0.5 rounded-full">
                            {{ __('admin.shipping_section.unassigned_count', ['count' => $unassignedCities->count()]) }}
                        </span>
                    @endif
                </div>

                <div id="zone-list" class="divide-y divide-gray-100 max-h-[620px] overflow-y-auto">
                    @forelse($zones as $zone)
                        <div class="zone-card px-4 py-3 cursor-pointer hover:bg-gray-50 transition group relative"
                            data-zone-id="{{ $zone->id }}" data-country-id="{{ $zone->country_id }}"
                            :class="selectedZone === '{{ $zone->id }}' ? 'bg-primary-50 border-r-2 border-primary-500' : ''"
                            @click="selectedZone = '{{ $zone->id }}'">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-sm font-semibold text-gray-900 truncate {{ !$zone->is_active ? 'opacity-60' : '' }}">
                                        {{ $zone->name }}
                                    </p>
                                    @if($zone->description)
                                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ $zone->description }}</p>
                                    @endif
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="text-xs text-gray-400">
                                            <span class="font-medium text-gray-700">{{ $zone->cities_count }}</span> {{ __('admin.shipping_section.cities_label') }}
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            <span class="font-medium text-gray-700 zone-rate-count" data-zone="{{ $zone->id }}">
                                                {{ $zone->active_rate_count }}
                                            </span> {{ __('admin.shipping_section.shipping_rates_tab') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    @if(!$zone->is_active)
                                        <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ __('common.inactive') }}</span>
                                    @endif
                                    <div x-data="{ open: false }" class="relative" @click.stop>
                                        <button @click="open = !open"
                                            class="p-1 rounded hover:bg-gray-200 opacity-0 group-hover:opacity-100 transition text-gray-500 text-base leading-none">
                                            &#8942;
                                        </button>
                                        <div x-show="open" @click.away="open = false" x-transition
                                            class="absolute right-0 top-full z-20 bg-white border border-gray-200 rounded-lg shadow-lg py-1 w-44">
                                            <button
                                                class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 edit-zone-btn"
                                                data-zone='{{ json_encode(["id" => $zone->id, "name" => $zone->name, "country_id" => $zone->country_id, "description" => $zone->description, "is_active" => $zone->is_active]) }}'
                                                @click="open=false">
                                                ✏️ {{ __('admin.shipping_section.edit_zone') }}
                                            </button>
                                            <button
                                                class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 assign-cities-btn"
                                                data-zone-id="{{ $zone->id }}" data-zone-name="{{ $zone->name }}"
                                                data-country-id="{{ $zone->country_id }}" @click="open=false">
                                                📍 {{ __('admin.shipping_section.assign_cities') }}
                                            </button>
                                            <button
                                                class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 duplicate-zone-btn"
                                                data-zone-id="{{ $zone->id }}" data-zone-name="{{ $zone->name }}"
                                                @click="open=false">
                                                📋 {{ __('admin.shipping_section.duplicate') }}
                                            </button>
                                            <button
                                                class="flex items-center gap-2 w-full px-3 py-1.5 text-sm toggle-zone-btn {{ $zone->is_active ? 'text-yellow-600' : 'text-green-600' }} hover:bg-gray-50"
                                                data-zone-id="{{ $zone->id }}" data-active="{{ (int) $zone->is_active }}"
                                                @click="open=false">
                                                {{ $zone->is_active ? '🚫 ' . __('admin.shipping_section.disable') : '✅ ' . __('admin.shipping_section.enable') }}
                                            </button>
                                            <div class="border-t my-1"></div>
                                            <button
                                                class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 delete-zone-btn"
                                                data-zone-id="{{ $zone->id }}" data-zone-name="{{ $zone->name }}"
                                                @click="open=false">
                                                🗑️ {{ __('admin.shipping_section.delete_zone') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center text-gray-400">
                            <p class="text-3xl mb-3">🗺️</p>
                            <p class="text-sm">{{ __('admin.shipping_section.no_zones_for_country') }}</p>
                            <button id="first-zone-btn" class="btn btn-sm btn-secondary mt-3">{{ __('admin.shipping_section.create_first_zone') }}</button>
                        </div>
                    @endforelse
                </div>

                @if($unassignedCities->count() > 0)
                    <div class="border-t border-gray-200 px-4 py-3 bg-yellow-50">
                        <p class="text-xs font-semibold text-yellow-800 mb-2">
                            {{ __('admin.shipping_section.cities_not_in_zone', ['count' => $unassignedCities->count()]) }}
                        </p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($unassignedCities->take(6) as $city)
                                <span class="text-xs bg-white border border-yellow-200 text-yellow-700 px-2 py-0.5 rounded-full">
                                    {{ $city->name_en }}
                                </span>
                            @endforeach
                            @if($unassignedCities->count() > 6)
                                <span class="text-xs text-yellow-600">{{ __('admin.shipping_section.more_count', ['count' => $unassignedCities->count() - 6]) }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ══ RIGHT: Detail panel (8/12) ══ --}}
        <div class="col-span-12 lg:col-span-8">

            {{-- Empty state --}}
            <div class="bg-white rounded-xl border border-gray-200 flex flex-col items-center justify-center h-64 text-gray-400"
                x-show="!selectedZone">
                <p class="text-5xl mb-4 opacity-30">🗺️</p>
                <p class="text-sm">{{ __('admin.shipping_section.select_zone_to_view') }}</p>
            </div>

            {{-- Zone detail panel --}}
            <div x-show="selectedZone" class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200">

                    {{-- Tabs --}}
                    <div class="flex items-center border-b px-4">
                        <div class="flex gap-0 -mb-px">
                            <button @click="activeTab = 'rates'"
                                :class="activeTab === 'rates' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap">
                                {{ __('admin.shipping_section.shipping_rates_tab') }}
                            </button>
                            <button @click="activeTab = 'cities'"
                                :class="activeTab === 'cities' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap">
                                {{ __('admin.shipping_section.assigned_cities_tab') }}
                            </button>
                        </div>
                        <div class="{{ app()->getLocale() == 'ar' ? 'mr' : 'ml' }}-auto flex items-center gap-2 py-2">
                            <button id="add-rate-btn" class="btn btn-primary btn-sm" x-show="activeTab === 'rates'">
                                {{ __('admin.shipping_section.add_rate') }}
                            </button>
                            <button id="copy-rates-btn" class="btn btn-ghost btn-sm" x-show="activeTab === 'rates'"
                                title="{{ __('admin.shipping_section.copy_from_zone') }}">
                                📋 {{ __('admin.shipping_section.copy_from_zone') }}
                            </button>
                        </div>
                    </div>

                    {{-- ── Rates tab ── --}}
                    <div x-show="activeTab === 'rates'" class="p-0">
                        <div class="px-4 py-2 border-b bg-gray-50 flex flex-wrap items-center gap-3">
                            <select id="rate-filter-method" class="form-input text-sm py-1.5 w-40">
                                <option value="">{{ __('admin.shipping_section.all_methods') }}</option>
                                @foreach($methods as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                            <select id="rate-filter-carrier" class="form-input text-sm py-1.5 w-36">
                                <option value="">{{ __('admin.shipping_section.all_carriers') }}</option>
                                @foreach($carriers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <select id="rate-filter-active" class="form-input text-sm py-1.5 w-32">
                                <option value="">{{ __('admin.shipping_section.all_status') }}</option>
                                <option value="1">{{ __('common.active') }}</option>
                                <option value="0">{{ __('common.inactive') }}</option>
                            </select>
                            <div id="bulk-actions" class="hidden flex items-center gap-2 ml-auto">
                                <span class="text-xs text-gray-500"><span id="selected-count">0</span> {{ __('admin.shipping_section.selected_count', ['count' => '']) }}</span>
                                <button id="bulk-activate" class="btn btn-xs btn-ghost text-green-600">{{ __('admin.shipping_section.activate') }}</button>
                                <button id="bulk-deactivate"
                                    class="btn btn-xs btn-ghost text-yellow-600">{{ __('admin.shipping_section.deactivate') }}</button>
                                <button id="bulk-delete" class="btn btn-xs btn-ghost text-red-600">{{ __('common.delete') }}</button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table id="rates-table" class="w-full text-sm">
                                <thead class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                                    <tr>
                                        <th class="w-8 px-4 py-2">
                                            <input type="checkbox" id="select-all-rates" class="form-checkbox" />
                                        </th>
                                        <th class="px-4 py-2 text-start">{{ __('admin.shipping_section.method_col') }}</th>
                                        <th class="px-4 py-2 text-start">{{ __('admin.shipping_section.carrier_col') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.shipping_section.base_fee_col') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.shipping_section.per_kg_col') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.shipping_section.free_threshold_col') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.shipping_section.cod_fee_col') }}</th>
                                        <th class="px-4 py-2 text-center">{{ __('common.status') }}</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody id="rates-tbody" class="divide-y divide-gray-100"></tbody>
                            </table>
                            <div id="rates-empty" class="hidden px-4 py-12 text-center text-gray-400 text-sm">
                                {{ __('admin.shipping_section.no_rates_for_zone') }}
                            </div>
                            <div id="rates-loading" class="hidden px-4 py-8 text-center">
                                <div
                                    class="animate-spin w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full mx-auto">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── Cities tab ── --}}
                    <div x-show="activeTab === 'cities'" class="p-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-800 mb-2">{{ __('admin.shipping_section.assigned_cities_label') }}</p>
                                <div id="assigned-cities-list"
                                    class="border border-gray-200 rounded-lg min-h-32 max-h-80 overflow-y-auto bg-gray-50 p-2 space-y-1">
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-semibold text-gray-800">{{ __('admin.shipping_section.available_cities_label') }}</p>
                                    <input type="text" id="city-search" placeholder="{{ __('admin.shipping_section.search_placeholder') }}"
                                        class="form-input text-xs w-32 py-1" />
                                </div>
                                <div id="available-cities-list"
                                    class="border border-dashed border-gray-300 rounded-lg min-h-32 max-h-80 overflow-y-auto p-2 space-y-1">
                                </div>
                            </div>
                        </div>
                        <div class="border-t mt-4 pt-3 flex items-center justify-between">
                            <p class="text-xs text-gray-500">{{ __('admin.shipping_section.drag_cities_hint') }}</p>
                            <button id="save-city-assignment-btn" class="btn btn-primary btn-sm">{{ __('admin.shipping_section.save_assignment') }}</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════
    MODALS
    ════════════════════════════════════════════════ --}}

    {{-- Rate Calculator --}}
    <div id="rate-calc-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
        <div class="modal-panel modal-md">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.shipping_section.rate_calc_title') }}</h3>
                <button type="button" data-modal-close
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">✕</button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.destination_zone') }}</label>
                        <select name="calc_zone_id" class="form-input w-full text-sm">
                            <option value="">{{ __('admin.shipping_section.select_zone_placeholder') }}</option>
                            @foreach($zones as $z)
                                <option value="{{ $z->id }}">{{ $z->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.shipping_method_required') }}</label>
                        <select name="calc_method_id" class="form-input w-full text-sm">
                            <option value="">{{ __('admin.shipping_section.select_method_placeholder') }}</option>
                            @foreach($methods as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.package_weight_grams') }}</label>
                        <input type="number" id="calc-weight" value="500" min="1" class="form-input w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.order_value', ['currency' => $countries->firstWhere('id', $activeCountryId)?->currency_code ?? 'EGP']) }}
                        </label>
                        <input type="number" id="calc-order-value" step="0.01" value="0" min="0"
                            class="form-input w-full" />
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="calc-is-cod" class="form-checkbox" />
                    <span class="text-sm text-gray-700">{{ __('admin.shipping_section.cod_order_checkbox') }}</span>
                </label>
                <button id="run-estimate-btn" class="btn btn-primary w-full">{{ __('admin.shipping_section.calculate') }}</button>
                <div id="calc-result" class="hidden p-4 bg-gray-50 rounded-xl">
                    <div class="text-center">
                        <p class="text-xs text-gray-500 mb-1">{{ __('admin.shipping_section.shipping_cost') }}</p>
                        <p id="calc-cost" class="text-3xl font-bold text-primary-700">—</p>
                        <p id="calc-meta" class="text-sm text-gray-500 mt-1">—</p>
                        <p id="calc-free-hint" class="text-sm text-green-600 mt-1 hidden">🎉 {{ __('admin.shipping_section.free_shipping_eligible') }}</p>
                    </div>
                    <div id="calc-breakdown" class="mt-3 border-t pt-3 text-xs text-gray-600 space-y-1 hidden"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add/Edit Zone --}}
    <div id="zone-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
        <div class="modal-panel modal-md">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 id="zone-modal-title" class="text-base font-semibold text-gray-900">{{ __('admin.shipping_section.zone_modal_title') }}</h3>
                <button type="button" data-modal-close
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">✕</button>
            </div>
            <div class="px-6 py-4">
                <form id="zone-form" class="space-y-4">
                    <input type="hidden" name="zone_id" id="zone-id">
                    <x-form.input name="name" label="{{ __('admin.shipping_section.zone_name_label') }}" required placeholder="{{ __('admin.shipping_section.zone_name_placeholder') }}" />
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-gray-700">{{ __('admin.shipping_section.country_required') }} <span
                                class="text-red-500">*</span></label>
                        <select name="country_id" id="zone-country-id" class="form-input w-full text-sm" required>
                            <option value="">{{ __('admin.shipping_section.select_country_placeholder') }}</option>
                            @foreach($countries as $c)
                                <option value="{{ $c->id }}">{{ $c->name_en }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form.textarea name="description" label="{{ __('admin.shipping_section.description_optional_zone') }}" :rows="2"
                        placeholder="{{ __('admin.shipping_section.description_placeholder_zone') }}" />
                    <x-form.toggle name="is_active" label="{{ __('common.active') }}" :checked="true" />

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
                        <button type="submit" id="zone-save-btn" class="btn btn-primary">{{ __('admin.shipping_section.save_zone') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add/Edit Rate --}}
    <div id="rate-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
        <div class="modal-panel modal-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 id="rate-modal-title" class="text-base font-semibold text-gray-900">{{ __('admin.shipping_section.rate_modal_title') }}</h3>
                <button type="button" data-modal-close
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">✕</button>
            </div>
            <div class="px-6 py-4">
                <form id="rate-form" class="space-y-4">
                    <input type="hidden" name="rate_id" id="rate-id">
                    <input type="hidden" name="destination_zone_id" id="rate-zone-id">

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.shipping_section.shipping_method_required') }} <span
                                    class="text-red-500">*</span></label>
                            <select name="shipping_method_id" class="form-input w-full text-sm" required>
                                <option value="">{{ __('admin.shipping_section.select_method_placeholder') }}</option>
                                @foreach($methods as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.shipping_section.carrier_optional') }}</label>
                            <select name="carrier_id" class="form-input w-full text-sm">
                                <option value="">{{ __('admin.shipping_section.any_carrier') }}</option>
                                @foreach($carriers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-gray-700">{{ __('admin.shipping_section.origin_zone_optional_rate') }}</label>
                        <select name="origin_zone_id" class="form-input w-full text-sm">
                            <option value="">{{ __('admin.shipping_section.any_origin_lower') }}</option>
                            @foreach(\App\Models\ShippingZone::orderBy('name')->get() as $oz)
                                <option value="{{ $oz->id }}">{{ $oz->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400">{{ __('admin.shipping_section.origin_note') }}</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                        <p class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.shipping_section.pricing_label') }}</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('admin.shipping_section.base_fee_required') }} <span class="text-gray-400 font-normal">(e.g. 15.00)</span>
                                </label>
                                <div class="flex">
                                    <input type="number" name="base_fee" id="rate-base-fee" step="0.01" min="0" required
                                        class="form-input rounded-r-none flex-1" />
                                    <span
                                        class="inline-flex items-center px-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">
                                        {{ $countries->firstWhere('id', $activeCountryId)?->currency_code ?? 'EGP' }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.rate_per_kg_required') }}</label>
                                <div class="flex">
                                    <input type="number" name="rate_per_kg" id="rate-per-kg" step="0.01" min="0" value="0"
                                        class="form-input rounded-r-none flex-1" />
                                    <span
                                        class="inline-flex items-center px-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">/kg</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.free_shipping_threshold_label') }}
                                    <span class="font-normal text-gray-400">({{ __('admin.shipping_section.description_optional') }})</span></label>
                                <input type="number" name="free_shipping_threshold" step="0.01" min="0"
                                    class="form-input w-full" placeholder="e.g. 200.00" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.cod_extra_fee') }}</label>
                                <input type="number" name="cod_extra_fee" step="0.01" min="0" value="0"
                                    class="form-input w-full" />
                            </div>
                        </div>
                    </div>

                    <div class="bg-amber-50 rounded-lg border border-amber-200 p-4"
                        x-data="{ customerRate: 0, carrierRate: 0, get gap() { return (this.carrierRate - this.customerRate).toFixed(2); } }"
                        x-init="
                            customerRate = parseFloat($el.closest('form').querySelector('[name=base_fee]')?.value) || 0;
                            carrierRate = parseFloat($el.closest('form').querySelector('[name=carrier_rate]')?.value) || 0;
                        "
                        @input="
                            customerRate = parseFloat($el.closest('form').querySelector('[name=base_fee]')?.value) || 0;
                            carrierRate = parseFloat($el.closest('form').querySelector('[name=carrier_rate]')?.value) || 0;
                        ">
                        <p class="text-sm font-semibold text-amber-800 mb-1">{{ __('admin.shipping_section.carrier_cost_label') }}</p>
                        <p class="text-xs text-amber-700 mb-3">{{ __('admin.shipping_section.carrier_cost_hint') }}</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.carrier_rate_label') }}</label>
                                <div class="flex">
                                    <input type="number" name="carrier_rate" id="rate-carrier-rate" step="0.01" min="0"
                                        class="form-input rounded-r-none flex-1" />
                                    <span
                                        class="inline-flex items-center px-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">
                                        {{ $countries->firstWhere('id', $activeCountryId)?->currency_code ?? 'EGP' }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.carrier_rate_per_kg_label') }}</label>
                                <div class="flex">
                                    <input type="number" name="carrier_rate_per_kg" id="rate-carrier-rate-per-kg" step="0.01" min="0"
                                        class="form-input rounded-r-none flex-1" />
                                    <span
                                        class="inline-flex items-center px-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">/kg</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-amber-200 text-xs text-amber-800 flex flex-wrap items-center gap-4">
                            <span>{{ __('admin.shipping_section.gap_preview_customer') }}: <strong x-text="customerRate.toFixed(2)"></strong></span>
                            <span>{{ __('admin.shipping_section.gap_preview_carrier') }}: <strong x-text="carrierRate.toFixed(2)"></strong></span>
                            <span>{{ __('admin.shipping_section.gap_preview_gap') }}: <strong :class="gap > 0 ? 'text-amber-900' : 'text-gray-500'" x-text="gap"></strong></span>
                        </div>
                    </div>

                    <details class="bg-gray-50 rounded-lg border border-gray-200">
                        <summary class="px-4 py-3 text-sm font-medium text-gray-700 cursor-pointer select-none">{{ __('admin.shipping_section.advanced_settings') }}</summary>
                        <div class="px-4 pb-4 pt-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.min_weight_grams') }}</label>
                                <input type="number" name="min_weight_grams" min="0" value="0" class="form-input w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.volumetric_divisor') }}</label>
                                <input type="number" name="volumetric_divisor" min="1" value="5000"
                                    class="form-input w-full" />
                                <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.shipping_section.volumetric_hint') }}</p>
                            </div>
                        </div>
                    </details>

                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
                        <p class="text-xs font-medium text-blue-700 mb-2">{{ __('admin.shipping_section.live_cost_preview') }}</p>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-blue-600">{{ __('admin.shipping_section.weight_label') }}</label>
                                <input type="number" id="preview-weight" value="500" min="1"
                                    class="w-20 text-xs border border-blue-200 rounded px-2 py-1" />
                            </div>
                            <div class="text-sm font-bold text-blue-800">{{ __('admin.shipping_section.shipping_cost') }}: <span id="rate-cost-preview">—</span></div>
                            <div class="text-xs text-blue-600">{{ __('admin.shipping_section.free_from_label') }} <span id="rate-free-preview">—</span></div>
                        </div>
                    </div>

                    <x-form.toggle name="is_active" label="{{ __('common.active') }}" :checked="true" />

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
                        <button type="submit" id="rate-save-btn" class="btn btn-primary">{{ __('admin.shipping_section.save_rate') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Assign Cities --}}
    <div id="assign-cities-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
        <div class="modal-panel modal-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.shipping_section.assign_cities_to_zone_label') }} <span
                        id="assign-zone-name">—</span></h3>
                <button type="button" data-modal-close
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">✕</button>
            </div>
            <div class="px-6 py-4">
                <input type="hidden" id="assign-zone-id">
                <input type="hidden" id="assign-country-id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-semibold text-gray-700">{{ __('admin.shipping_section.in_this_zone_label') }}<span id="assigned-count">0</span>)
                            </p>
                            <button id="remove-all-btn" class="text-xs text-red-600 hover:underline">{{ __('admin.shipping_section.remove_all') }}</button>
                        </div>
                        <div id="assigned-cities-sortable"
                            class="min-h-48 max-h-80 overflow-y-auto border border-gray-200 rounded-lg bg-gray-50 p-2 space-y-1">
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-semibold text-gray-700">{{ __('admin.shipping_section.available_label') }}</p>
                            <input type="text" id="assign-city-search" placeholder="{{ __('admin.shipping_section.search_placeholder') }}"
                                class="form-input text-xs py-1 w-28" />
                        </div>
                        <div id="available-cities-sortable"
                            class="min-h-48 max-h-80 overflow-y-auto border-2 border-dashed border-gray-300 rounded-lg p-2 space-y-1">
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <p class="text-xs text-gray-500">{{ __('admin.shipping_section.drag_cities_hint') }}</p>
                <div class="flex gap-2">
                    <button data-modal-close class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                    <button id="save-assign-btn" class="btn btn-primary btn-sm">{{ __('common.save') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Copy Rates --}}
    <div id="copy-rates-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
        <div class="modal-panel modal-sm">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.shipping_section.copy_rates_title') }}</h3>
                <button type="button" data-modal-close
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">✕</button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-gray-700">{{ __('admin.shipping_section.copy_rates_from') }}</label>
                    <select id="copy-source-zone" class="form-input w-full text-sm">
                        <option value="">{{ __('admin.shipping_section.select_source_zone') }}</option>
                        @foreach(\App\Models\ShippingZone::where('is_active', 1)->orderBy('name')->get() as $sz)
                            <option value="{{ $sz->id }}">{{ $sz->name }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-xs text-gray-500">{{ __('admin.shipping_section.copy_rates_note') }}</p>
                <div class="flex justify-end gap-2">
                    <button data-modal-close class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                    <button id="confirm-copy-rates-btn" class="btn btn-primary btn-sm">{{ __('admin.shipping_section.copy_rates_btn') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Duplicate Zone --}}
    <div id="duplicate-zone-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
        <div class="modal-panel modal-sm">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.shipping_section.duplicate_zone_title') }}</h3>
                <button type="button" data-modal-close
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">✕</button>
            </div>
            <div class="px-6 py-4 space-y-3">
                <x-form.input name="new_zone_name" label="{{ __('admin.shipping_section.new_zone_name') }}" required id="new-zone-name" />
                <p class="text-xs text-gray-500">{{ __('admin.shipping_section.duplicate_zone_note') }}</p>
                <div class="flex justify-end gap-2">
                    <button data-modal-close class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                    <button id="confirm-duplicate-btn" class="btn btn-primary btn-sm">{{ __('admin.shipping_section.duplicate') }}</button>
                </div>
            </div>
        </div>
    </div>

@endsection
