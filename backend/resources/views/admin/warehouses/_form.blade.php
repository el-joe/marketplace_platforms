{{--
Warehouse form partial.
Include with: @include('admin.warehouses._form', ['mode' => 'create'])
@include('admin.warehouses._form', ['mode' => 'edit'])

Expected variables:
$countries — collection/array of [id => name_en]
$vendors — collection/array of [id => store_name]
$admins — collection/array of [id => name]
$warehouse — Warehouse model (edit mode only)
--}}
@php
    $warehouse = $warehouse ?? null;
    $isEdit = $mode === 'edit' && $warehouse !== null;

    $val = fn($field, $default = '') => old($field, $isEdit ? ($warehouse->{$field} ?? $default) : $default);

    // Storage rate is stored in cents; display as decimal
    $storageRateDisplay = old('storage_rate_per_m3_price') !== null
        ? old('storage_rate_per_m3_price')
        : ($isEdit && $warehouse->storage_rate_per_m3_price ? number_format($warehouse->storage_rate_per_m3_price , 2, '.', '') : '');

    $typeOptions = [
        'platform_fbn' => __('admin.warehouses_section.platform_fbn_full'),
        'seller_owned' => __('admin.warehouses_section.seller_owned'),
        'third_party' => __('admin.warehouses_section.third_party_logistics'),
    ];

    $countryOptions = ['' => __('admin.warehouses_section.select_country')] + $countries->toArray();
    $vendorOptions = ['' => __('admin.warehouses_section.none_platform_owned')] + $vendors->toArray();
    $adminOptions = ['' => __('admin.warehouses_section.no_manager')] + $admins->toArray();
    $currencyOptions = ['' => '— Select currency —'] + (($currencies ?? collect())->toArray());
@endphp

<div class="space-y-6" x-data="{ type: '{{ $val('type', 'platform_fbn') }}', ownerVendorId: '{{ $val('owner_vendor_id') }}', defaultLimitType: '{{ $val('default_limit_type', 'quantity') }}' }">

    {{-- ── Page Header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <nav class="text-sm text-gray-500 flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.warehouses.index') }}" class="hover:text-primary-600">{{ __('admin.warehouses_section.title') }}</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">
                    {{ $isEdit ? __('admin.warehouses_section.edit_warehouse_title', ['name' => e($warehouse->name)]) : __('admin.warehouses_section.new_warehouse') }}
                </span>
            </nav>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $isEdit ? __('admin.warehouses_section.edit_warehouse') : __('admin.warehouses_section.add_warehouse') }}
            </h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ $isEdit ? route('admin.warehouses.show', $warehouse->id) : route('admin.warehouses.index') }}"
                class="btn btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                {{ $isEdit ? __('admin.warehouses_section.save_changes') : __('admin.warehouses_section.create_warehouse') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="p-3 bg-green-50 border border-green-200 rounded text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
            <p class="font-medium mb-1">{{ __('admin.warehouses_section.fix_errors') }}</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Two-column layout ───────────────────────────────────────────── --}}
    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════ LEFT: Main Details ═══════ --}}
        <div class="flex-1 min-w-0 space-y-4">

            {{-- Basic Details --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warehouses_section.basic_details') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <x-form-input name="name" label="{{ __('admin.warehouses_section.warehouse_name') }}" type="text" :value="$val('name')"
                        placeholder="{{ __('admin.warehouses_section.warehouse_name_placeholder') }}" required />

                    <x-form-input name="code" label="{{ __('admin.warehouses_section.warehouse_code') }}" type="text" :value="$val('code')"
                        placeholder="{{ __('admin.warehouses_section.warehouse_code_placeholder') }}" maxlength="20" required
                        hint="{{ __('admin.warehouses_section.warehouse_code_hint') }}" />

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.warehouses_section.type_column') }} <span class="text-red-500">*</span>
                        </label>
                        <select name="type" x-model="type"
                            class="form-select w-full @error('type') border-red-400 @enderror" required>
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected($val('type', 'platform_fbn') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-form-select name="country_id" label="{{ __('common.country') }}" :options="$countryOptions"
                        :value="$val('country_id')" required select2 />

                </div>
            </div>

            {{-- Capacity & Rates --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warehouses_section.capacity_rates') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.warehouses_section.capacity_m3_hint') }}</p>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div class="grid grid-cols-2 gap-4">
                        <x-form-input name="total_capacity_m3" label="{{ __('admin.warehouses_section.total_capacity_m3') }}" type="number"
                            :value="$val('total_capacity_m3')" placeholder="0.00" step="0.01" min="0" />
                        <x-form-input name="used_capacity_m3" label="{{ __('admin.warehouses_section.used_capacity_m3') }}" type="number"
                            :value="$val('used_capacity_m3')" placeholder="0.00" step="0.01" min="0" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-form-input name="storage_rate_per_m3_price" label="{{ __('admin.warehouses_section.storage_rate_per_m3') }}" type="number"
                            :value="$storageRateDisplay" placeholder="0.00" step="0.01" min="0"
                            hint="{{ __('admin.warehouses_section.storage_rate_hint') }}" />
                        <x-form-input name="storage_currency" label="{{ __('admin.warehouses_section.currency_iso') }}" type="text"
                            :value="$val('storage_currency', 'EGP')" placeholder="EGP" maxlength="3"
                            hint="{{ __('admin.warehouses_section.currency_iso_hint') }}" />
                    </div>

                </div>
            </div>

            {{-- FBN Free Storage & Daily Overage Fee (platform_fbn warehouses only) --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm" x-show="type === 'platform_fbn'" x-cloak>
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">FBN Free Storage & Daily Overage Fee</h2>
                    <p class="text-xs text-gray-500 mt-0.5">After the free storage days expire, a daily fee per unit is charged.</p>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <x-form-input name="free_storage_days" label="أيام التخزين المجانية / Free Storage Days" type="number"
                        :value="$val('free_storage_days', 30)" min="0" placeholder="30" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-form-input name="daily_fee_per_unit" label="رسوم يومية لكل وحدة / Daily Fee Per Unit" type="number"
                            :value="$val('daily_fee_per_unit', 0)" min="0" placeholder="0"
                            hint="Whole base currency units — no decimals." />
                        <x-form-select name="daily_fee_currency" label="عملة الرسوم اليومية / Daily Fee Currency"
                            :options="$currencyOptions" :value="$val('daily_fee_currency')" select2 />
                    </div>

                </div>
            </div>

            {{-- GPS Location --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warehouses_section.gps_location') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.warehouses_section.gps_location_hint') }}
                    </p>
                </div>
                <div class="px-5 py-5">
                    <div class="grid grid-cols-2 gap-4">
                        <x-form-input name="latitude" label="{{ __('admin.warehouses_section.latitude') }}" type="number" :value="$val('latitude')"
                            placeholder="30.0444" step="0.0000001" min="-90" max="90" />
                        <x-form-input name="longitude" label="{{ __('admin.warehouses_section.longitude') }}" type="number" :value="$val('longitude')"
                            placeholder="31.2357" step="0.0000001" min="-180" max="180" />
                    </div>
                </div>
            </div>

        </div>

        {{-- ═══════ RIGHT: Sidebar ═══════ --}}
        <div class="w-full lg:w-72 shrink-0 space-y-4">

            {{-- Status --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('common.status') }}</h2>
                </div>
                <div class="px-5 py-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            @if($val('is_active', '1') == '1' || ($val('is_active', '1') === true)) checked @endif>
                        <span class="text-sm text-gray-700">{{ __('common.active') }}</span>
                    </label>
                    <p class="mt-1.5 text-xs text-gray-400">{{ __('admin.warehouses_section.inactive_hint') }}</p>
                </div>
            </div>

            {{-- Ownership & Management --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warehouses_section.ownership_management') }}</h2>
                </div>
                <div class="px-5 py-4 space-y-4">

                    {{-- Only relevant for seller_owned type, but available to all --}}
                    <div x-show="type === 'seller_owned'" x-cloak>
                        <x-form-select name="owner_vendor_id" label="{{ __('admin.warehouses_section.owner_vendor') }}" :options="$vendorOptions"
                            :value="$val('owner_vendor_id')" select2 x-model="ownerVendorId" />
                    </div>
                    <div x-show="type !== 'seller_owned'" x-cloak>
                        <p class="text-xs text-gray-400">{{ __('admin.warehouses_section.owner_vendor_hint') }}</p>
                    </div>

                    <x-form-select name="manager_admin_id" label="{{ __('admin.warehouses_section.manager_admin') }}" :options="$adminOptions"
                        :value="$val('manager_admin_id')" select2 />

                </div>
            </div>

            {{-- Default Per-Vendor Storage Limit (platform-owned warehouses only) --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm" x-show="!ownerVendorId" x-cloak>
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warehouses_section.default_vendor_storage_limit_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.warehouses_section.default_vendor_storage_limit_desc') }}</p>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">{{ __('admin.warehouses_section.limit_type_column') }}</label>
                        <div class="inline-flex rounded-lg border border-gray-200 p-0.5 bg-gray-50 w-full">
                            <button type="button" @click="defaultLimitType = 'quantity'"
                                :class="defaultLimitType === 'quantity' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500'"
                                class="flex-1 px-3 py-1.5 text-xs font-medium rounded-md transition-colors">
                                {{ __('admin.warehouses_section.quantity_type_label') }}
                            </button>
                            <button type="button" @click="defaultLimitType = 'capacity'"
                                :class="defaultLimitType === 'capacity' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500'"
                                class="flex-1 px-3 py-1.5 text-xs font-medium rounded-md transition-colors">
                                {{ __('admin.warehouses_section.capacity_m3_label') }}
                            </button>
                        </div>
                        <input type="hidden" name="default_limit_type" :value="defaultLimitType">
                    </div>
                    <div x-show="defaultLimitType === 'quantity'" x-cloak>
                        <x-form-input name="default_max_quantity" label="Max Quantity (units)" type="number"
                            :value="$val('default_max_quantity')" placeholder="e.g. 5000" min="1" />
                    </div>
                    <div x-show="defaultLimitType === 'capacity'" x-cloak>
                        <x-form-input name="default_max_capacity_m3" label="Max Capacity (m³)" type="number"
                            :value="$val('default_max_capacity_m3')" placeholder="e.g. 20.00" step="0.01" min="0.01" />
                    </div>

                    @if($isEdit)
                        <div class="pt-2 border-t border-gray-100">
                            <button type="button" id="btn-apply-default-limit"
                                data-url="{{ route('admin.warehouses.vendor-limits.apply-default', $warehouse->id) }}"
                                class="btn btn-secondary btn-sm w-full">
                                Apply Default to All Vendors
                            </button>
                            <p class="mt-1.5 text-xs text-gray-400">Fills the default limit in for any vendor in this warehouse who doesn't already have a custom limit. Save this default first.</p>
                        </div>
                    @endif
                </div>
            </div>

            @if($isEdit)
                {{-- Meta info in edit mode --}}
                <div class="bg-gray-50 rounded-xl border border-gray-200 px-5 py-4 text-xs text-gray-500 space-y-1.5">
                    <div class="flex justify-between">
                        <span>{{ __('admin.warehouses_section.created_meta') }}</span>
                        <span>{{ $warehouse->created_at->format('Y-m-d') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('admin.warehouses_section.updated_meta') }}</span>
                        <span>{{ $warehouse->updated_at->format('Y-m-d') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('admin.warehouses_section.id_meta') }}</span>
                        <span class="font-mono truncate ml-2">{{ $warehouse->id }}</span>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>

@if($isEdit)
    @push('scripts')
    <script>
    document.getElementById('btn-apply-default-limit')?.addEventListener('click', async function () {
        if (!confirm('Apply the default storage limit to every vendor in this warehouse who doesn\'t already have a custom limit?')) return;

        const btn = this;
        btn.disabled = true;

        try {
            const res = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({}),
            });
            const json = await res.json();
            if (!res.ok) throw json;
            window.Toast?.success(json.message ?? 'Default limit applied.');
        } catch (err) {
            window.Toast?.error(err.message ?? 'Request failed. Save the default limit first.');
        } finally {
            btn.disabled = false;
        }
    });
    </script>
    @endpush
@endif
