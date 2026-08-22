{{--
    Shared Country form partial.
    @include('admin.countries._form', ['mode' => 'create'])
    @include('admin.countries._form', ['mode' => 'edit', 'country' => $country])
--}}
@php
    $country = $country ?? null;
    $isEdit  = $country !== null;

    $val = function (string $field, $default = '') use ($isEdit, $country) {
        return old($field, $isEdit ? ($country->{$field} ?? $default) : $default);
    };

    $bool = function (string $field, bool $default = false) use ($isEdit, $country): bool {
        $raw = old($field, $isEdit ? ($country->{$field} ?? $default) : $default);
        return (bool) $raw;
    };
@endphp

<script>
    window.TRANSLATIONS = window.TRANSLATIONS || {};
    Object.assign(window.TRANSLATIONS, {
        launchCountryConfirm: @json(__('admin.geography.launch_country_confirm')),
        launchingEllipsis: @json(__('admin.geography.launching_ellipsis')),
        launchFailed: @json(__('admin.geography.launch_failed')),
        launch: @json(__('admin.geography.launch')),
        deactivateCountryConfirm: @json(__('admin.geography.deactivate_country_confirm')),
        deactivateFailed: @json(__('admin.geography.deactivate_failed')),
        reactivateFailed: @json(__('admin.geography.reactivate_failed')),
        deleteCountryTitle: @json(__('admin.geography.delete_country_title')),
        deleteCountryConfirm: @json(__('admin.geography.delete_country_confirm')),
        countryDeleteFailed: @json(__('admin.geography.country_delete_failed')),
        savingEllipsis: @json(__('admin.geography.saving_ellipsis')),
        saveBtn: @json(__('admin.geography.save_btn')),
        saveFailed: @json(__('admin.geography.save_failed')),
        saveShippingSettingsBtn: @json(__('admin.geography.save_shipping_settings_btn')),
    });
</script>

<div
    x-data="{ activeTab: 'general' }"
    class="space-y-6"
>
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- Header row with breadcrumb + action buttons                           --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between">
        <div></div>

        <div class="flex items-center gap-2">
            @if ($isEdit)
                {{-- Launch / Deactivate --}}
                @if (!$country->is_launched)
                    <button
                        type="button"
                        id="btn-launch"
                        data-country-id="{{ $country->id }}"
                        data-country-name="{{ $country->name_en }}"
                        data-url="{{ route('admin.countries.launch', $country->id) }}"
                        @if (count($launchErrors ?? []) > 0) disabled title="{{ implode(' | ', $launchErrors ?? []) }}" @endif
                        class="btn btn-success {{ count($launchErrors ?? []) > 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                    >
                        <x-heroicon name="rocket-launch" class="w-4 h-4" />
                        {{ __('admin.geography.launch') }}
                    </button>
                @elseif ($country->is_active)
                    <button
                        type="button"
                        id="btn-deactivate"
                        data-country-id="{{ $country->id }}"
                        data-url="{{ route('admin.countries.deactivate', $country->id) }}"
                        class="btn btn-ghost"
                    >
                        {{ __('admin.geography.deactivate') }}
                    </button>
                @else
                    <button
                        type="button"
                        id="btn-reactivate"
                        data-country-id="{{ $country->id }}"
                        data-url="{{ route('admin.countries.reactivate', $country->id) }}"
                        class="btn btn-secondary"
                    >
                        {{ __('admin.geography.reactivate') }}
                    </button>
                @endif

                @if ($canDelete ?? false)
                    <button
                        type="button"
                        id="btn-delete-country"
                        data-country-id="{{ $country->id }}"
                        data-country-name="{{ $country->name_en }}"
                        data-url="{{ route('admin.countries.destroy', $country->id) }}"
                        class="btn btn-danger-ghost"
                    >
                        <x-heroicon name="trash" class="w-4 h-4" />
                    </button>
                @endif
            @endif

            <button type="submit" class="btn btn-primary">
                <x-heroicon name="check" class="w-4 h-4" />
                {{ __('admin.geography.save_country') }}
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Launch prerequisite warnings (edit mode only) --}}
    @if ($isEdit && count($launchErrors ?? []) > 0 && !$country->is_launched)
        <div class="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-800">
            <strong class="block mb-1">{{ __('admin.geography.not_ready_to_launch') }}</strong>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($launchErrors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- Country status badge (edit mode)                                       --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if ($isEdit)
        <div class="flex items-center gap-3 text-sm text-gray-600">
            @if ($country->is_launched)
                <x-badge color="success">{{ __('admin.geography.launched') }}</x-badge>
                <span class="text-gray-400">{{ __('admin.geography.since') }} {{ $country->launched_at?->format('d M Y') ?? '—' }}</span>
            @else
                <x-badge color="gray">{{ __('admin.geography.draft') }}</x-badge>
            @endif

            @if (!$country->is_active)
                <x-badge color="warning">{{ __('admin.geography.inactive') }}</x-badge>
            @endif
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab nav                                                                --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-t-xl border border-gray-200 overflow-hidden">
        <nav class="flex overflow-x-auto border-b border-gray-100" aria-label="{{ __('admin.geography.country_form_tabs_aria') }}">
            @php
                $tabs = [
                    ['id' => 'general',   'label' => __('admin.geography.general_tab'),           'icon' => 'globe-alt',        'editOnly' => false],
                    ['id' => 'payment',   'label' => __('admin.geography.payment_gateways_tab'),   'icon' => 'credit-card',      'editOnly' => true],
                    ['id' => 'shipping',  'label' => __('admin.geography.shipping_tab'),          'icon' => 'truck',            'editOnly' => true],
                    ['id' => 'categories','label' => __('admin.geography.category_overrides_tab'),'icon' => 'tag',              'editOnly' => true],
                ];
            @endphp

            @foreach ($tabs as $tab)
                @if (!$tab['editOnly'] || $isEdit)
                    <button
                        type="button"
                        @click="activeTab = '{{ $tab['id'] }}'"
                        :class="activeTab === '{{ $tab['id'] }}'
                            ? 'border-b-2 border-primary-600 text-primary-700 bg-primary-50/50'
                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        class="flex items-center gap-1.5 px-4 py-3.5 text-sm font-medium -mb-px whitespace-nowrap transition-colors"
                    >
                        <x-heroicon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                        {{ $tab['label'] }}
                    </button>
                @endif
            @endforeach
        </nav>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: General                                                           --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div
        x-show="activeTab === 'general'"
        class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm space-y-5"
    >
        <div class="grid grid-cols-2 gap-4">
            <x-form.input
                name="name_en"
                label="{{ __('admin.geography.name_en_label') }}"
                :value="$val('name_en')"
                required
                dir="ltr"
            />
            <x-form.input
                name="name_ar"
                label="{{ __('admin.geography.name_ar_label') }}"
                :value="$val('name_ar')"
                required
                dir="rtl"
            />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <x-form.input
                name="iso_code_2"
                label="{{ __('admin.geography.iso2_code') }}"
                :value="$val('iso_code_2')"
                maxlength="2"
                placeholder="SA"
                required
                class="uppercase"
            />
            <x-form.input
                name="iso_code_3"
                label="{{ __('admin.geography.iso3_code') }}"
                :value="$val('iso_code_3')"
                maxlength="3"
                placeholder="SAU"
                class="uppercase"
            />
            <x-form.input
                name="phone_prefix"
                label="{{ __('admin.geography.phone_prefix') }}"
                :value="$val('phone_prefix')"
                maxlength="5"
                placeholder="+966"
            />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <x-form.input
                name="site_code"
                label="{{ __('admin.geography.site_code') }}"
                :value="$val('site_code')"
                maxlength="20"
                placeholder="sa"
                hint="{{ __('admin.geography.site_code_hint') }}"
            />
            <x-form.input
                name="site_domain"
                label="{{ __('admin.geography.site_domain') }}"
                :value="$val('site_domain')"
                maxlength="100"
                placeholder="noon.com"
            />
            <x-form.select
                name="currency_code"
                label="{{ __('admin.geography.currency') }}"
                :options="$currencies"
                :value="$val('currency_code')"
                :empty-option="__('admin.geography.select_currency')"
            />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <x-form.select
                name="default_locale"
                label="{{ __('admin.geography.default_locale') }}"
                :options="$locales"
                :value="$val('default_locale', 'en')"
            />
            <x-form.select
                name="timezone"
                label="{{ __('admin.geography.timezone') }}"
                :options="$timezones"
                :value="$val('timezone', 'UTC')"
            />
            <x-form.input
                name="vat_rate"
                label="{{ __('admin.geography.vat_rate') }}"
                type="number"
                step="0.01"
                min="0"
                max="50"
                :value="$val('vat_rate', '0.00')"
                required
            />
        </div>

        <div class="flex items-center gap-6 pt-2">
            <x-form.toggle
                name="cod_available"
                label="{{ __('admin.geography.cod_available') }}"
                :checked="$bool('cod_available')"
            />
            <x-form.toggle
                name="is_active"
                label="{{ __('admin.geography.is_active') }}"
                :checked="$bool('is_active', true)"
            />
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: Payment Methods (edit only)                                       --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if ($isEdit)
    <div
        x-show="activeTab === 'payment'"
        x-cloak
        class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm"
    >
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">
                    {{ __('admin.geography.payment_gateways_for', ['name' => $country->name_en]) }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ __('admin.geography.manage_gateways_hint') }}
                    <a href="{{ route('admin.payment-gateways.index') }}" class="text-primary-600 underline hover:text-primary-800">
                        {{ __('admin.geography.go_to_payment_gateways') }}
                    </a>
                </p>
            </div>
            <a href="{{ route('admin.payment-gateways.index') }}" class="btn btn-secondary btn-sm">
                <x-heroicon name="arrow-top-right-on-square" class="w-4 h-4" />
                {{ __('admin.geography.manage_gateways_btn') }}
            </a>
        </div>

        <div class="space-y-2">
            @forelse ($country->countryPaymentGateways()->with('gateway')->orderBy('sort_order')->get() as $cpg)
                <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 rounded-lg border border-gray-200">
                    @if($cpg->gateway?->image)
                        <img src="{{ $cpg->gateway->image }}" class="h-5 w-auto object-contain flex-shrink-0" alt="">
                    @endif
                    <span class="flex-1 text-sm font-medium text-gray-800">{{ $cpg->display_name_en }}</span>
                    <span class="text-xs text-gray-400">{{ $cpg->gateway?->name }}</span>
                    <span class="text-xs rounded px-1.5 py-0.5 {{ $cpg->environment === 'production' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                        {{ ucfirst($cpg->environment) }}
                    </span>
                    @if($cpg->is_configured)
                        <x-badge color="success" size="xs">{{ __('common.configured') }}</x-badge>
                    @else
                        <x-badge color="warning" size="xs">{{ __('common.no_credentials') }}</x-badge>
                    @endif
                    @if($cpg->is_active)
                        <x-badge color="success" size="xs">{{ __('common.active') }}</x-badge>
                    @else
                        <x-badge color="gray" size="xs">{{ __('admin.geography.inactive') }}</x-badge>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400 py-4 text-center">
                    {{ __('admin.geography.no_payment_gateways_configured') }}
                    <a href="{{ route('admin.payment-gateways.index') }}" class="text-primary-600 underline">
                        {{ __('admin.geography.add_gateway_link') }}
                    </a>
                </p>
            @endforelse
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: Shipping (edit only)                                              --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if ($isEdit)
    <div
        x-show="activeTab === 'shipping'"
        x-cloak
        class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm"
    >
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('admin.geography.shipping_methods_for', ['name' => $country->name_en]) }}</h3>
            <button
                type="button"
                id="btn-save-shipping"
                data-url="{{ route('admin.countries.shipping-settings.update', $country->id) }}"
                class="btn btn-primary btn-sm"
            >
                <x-heroicon name="check" class="w-4 h-4" />
                {{ __('admin.geography.save_shipping_settings') }}
            </button>
        </div>

        <div id="shipping-settings-form">
            <div id="shipping-methods-list" class="space-y-2">
                @foreach ($allShippingMethods as $method)
                    @php
                        $setting = $country->countryShippingSettings
                            ->firstWhere('shipping_method_id', $method->id);
                    @endphp
                    <div class="flex items-center gap-4 px-4 py-3 bg-gray-50 rounded-lg border border-gray-200">
                        <input type="hidden" name="settings[{{ $loop->index }}][shipping_method_id]" value="{{ $method->id }}">

                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-800">{{ $method->name }}</p>
                            <p class="text-xs text-gray-500">{{ $method->description ?? $method->code }}</p>
                        </div>

                        <x-form.toggle
                            name="settings[{{ $loop->index }}][is_active]"
                            label="{{ __('admin.geography.enabled') }}"
                            :checked="(bool) ($setting?->is_active)"
                        />

                        <x-form.input
                            name="settings[{{ $loop->index }}][free_shipping_threshold]"
                            label="{{ __('admin.geography.free_shipping_at', ['currency' => $country?->currency?->symbol ?? $country?->currency_code ?? 'cents']) }}"
                            type="number"
                            min="0"
                            :value="$setting?->free_shipping_threshold"
                            placeholder="{{ __('admin.geography.never_free') }}"
                            class="w-48"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: Category Overrides (edit only)                                    --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if ($isEdit)
    <div
        x-show="activeTab === 'categories'"
        x-cloak
        class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm"
    >
        <p class="text-sm text-gray-500 mb-4">
            {{ __('admin.geography.category_overrides_desc', ['name' => $country->name_en]) }}
        </p>

        @php
            $catColumns = [
                ['title' => __('admin.geography.category_col'),          'data' => 'name_en',         'name' => 'name_en'],
                ['title' => __('admin.geography.available_col'),          'data' => 'is_available',    'name' => 'is_available',   'searchable' => false, 'render' => 'Renderers.badge({true:{label:"' . __('common.yes') . '",color:"success"},false:{label:"' . __('common.no') . '",color:"danger"}})'],
                ['title' => __('admin.geography.commission_fbp_pct_col'), 'data' => 'commission_fbp_pct', 'name' => 'commission_fbp_pct','searchable' => false],
                ['title' => __('admin.geography.commission_fbp_fixed_col'), 'data' => 'commission_fbp_fixed', 'name' => 'commission_fbp_fixed','searchable' => false],
                ['title' => __('admin.geography.commission_fbn_pct_col'), 'data' => 'commission_fbn_pct', 'name' => 'commission_fbn_pct','searchable' => false],
                ['title' => __('admin.geography.commission_fbn_fixed_col'), 'data' => 'commission_fbn_fixed', 'name' => 'commission_fbn_fixed','searchable' => false],
                ['title' => __('admin.geography.override_reason_col'),    'data' => 'unavailable_reason', 'name' => 'unavailable_reason', 'orderable' => false, 'searchable' => false],
                ['title' => '',                   'data' => 'actions',         'name' => 'actions',         'orderable' => false, 'searchable' => false,
                 'render' => 'Renderers.actions([{type:"button",label:"' . __('admin.geography.edit_override') . '",id:"editCatOverride",class:"btn-ghost btn-sm"}])'],
            ];

            $catFilters = [
                ['type' => 'text',   'name' => 'search',       'label' => __('admin.geography.category_name_label')],
                ['type' => 'select', 'name' => 'is_available', 'label' => __('admin.geography.available_label'),
                 'options' => ['' => __('common.all'), '1' => __('common.yes'), '0' => __('common.no')]],
            ];
        @endphp

        <x-table.datatable
            id="categories-override-table"
            url="{{ route('admin.countries.categories.datatable', $country->id) }}"
            :columns="$catColumns"
            :filters="$catFilters"
            :page-length="25"
        />

    </div>
    @endif

</div>{{-- end x-data --}}

@if ($isEdit)
@push('modals')
    {{-- Edit Category Override Modal --}}
    <x-modal id="cat-override-modal" title="{{ __('admin.geography.edit_category_override_title') }}" size="lg">
        <form id="cat-override-form" novalidate>
            @csrf
            <input type="hidden" id="cat-category-id" name="overrides[0][category_id]">
            <div class="space-y-5 p-1 sm:p-4">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <p class="font-semibold text-gray-900 text-sm sm:text-base" id="cat-name-display"></p>
                    <x-form.toggle name="overrides[0][is_available]" label="{{ __('admin.geography.available_in_country') }}" id="cat-is-available" :checked="true" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
                    {{-- FBP panel --}}
                    <div class="bg-blue-50/60 border border-blue-200 rounded-xl p-4">
                        <h4 class="flex items-center gap-1.5 text-xs font-bold text-blue-700 uppercase tracking-wide mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            {{ __('admin.geography.fbp_panel_title') }}
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.geography.percentage_rate_label') }}</label>
                                <input type="number" name="overrides[0][commission_fbp_pct]" id="cat-commission-fbp-pct"
                                    step="0.01" min="0" max="100"
                                    placeholder="{{ __('admin.geography.commission_override_placeholder') }}"
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-offset-0 focus:border-primary-500 focus:ring-primary-200">
                                <p class="text-xs text-gray-400 mt-1">{{ __('admin.geography.fbp_pct_inherit_hint') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.geography.fixed_fee_label') }}</label>
                                <input type="number" name="overrides[0][commission_fbp_fixed]" id="cat-commission-fbp-fixed"
                                    step="1" min="0"
                                    placeholder="{{ __('admin.geography.commission_override_placeholder') }}"
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-offset-0 focus:border-primary-500 focus:ring-primary-200">
                                <p class="text-xs text-gray-400 mt-1">{{ __('admin.geography.fbp_fixed_inherit_hint') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- FBN panel --}}
                    <div class="bg-green-50/60 border border-green-200 rounded-xl p-4">
                        <h4 class="flex items-center gap-1.5 text-xs font-bold text-green-700 uppercase tracking-wide mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                            {{ __('admin.geography.fbn_panel_title') }}
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.geography.percentage_rate_label') }}</label>
                                <input type="number" name="overrides[0][commission_fbn_pct]" id="cat-commission-fbn-pct"
                                    step="0.01" min="0" max="100"
                                    placeholder="{{ __('admin.geography.commission_override_placeholder') }}"
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-offset-0 focus:border-primary-500 focus:ring-primary-200">
                                <p class="text-xs text-gray-400 mt-1">{{ __('admin.geography.fbn_pct_inherit_hint') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.geography.fixed_fee_label') }}</label>
                                <input type="number" name="overrides[0][commission_fbn_fixed]" id="cat-commission-fbn-fixed"
                                    step="1" min="0"
                                    placeholder="{{ __('admin.geography.commission_override_placeholder') }}"
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-offset-0 focus:border-primary-500 focus:ring-primary-200">
                                <p class="text-xs text-gray-400 mt-1">{{ __('admin.geography.fbn_fixed_inherit_hint') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-500 leading-relaxed border-t border-gray-100 pt-3">
                    {{ __('admin.geography.commission_formula_note') }}
                </p>

                <x-form.input
                    name="overrides[0][unavailable_reason]"
                    label="{{ __('admin.geography.reason_if_unavailable') }}"
                    id="cat-unavailable-reason"
                    maxlength="100"
                />
                <x-form.textarea
                    name="overrides[0][notes]"
                    label="{{ __('admin.geography.internal_notes') }}"
                    id="cat-notes"
                    rows="2"
                />
            </div>
            <div class="flex justify-end gap-2 px-4 pb-4">
                <button type="button" class="btn btn-ghost" data-modal-close="cat-override-modal">{{ __('common.cancel') }}</button>
                <button
                    type="submit"
                    id="cat-override-submit"
                    data-url="{{ route('admin.countries.category-overrides.update', $country->id) }}"
                    class="btn btn-primary"
                >{{ __('common.save') }}</button>
            </div>
        </form>
    </x-modal>
@endpush
@endif
