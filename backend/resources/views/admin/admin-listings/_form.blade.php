{{--
    Shared form partial for create/edit AdminListing (Platform / FBN Express listing).
    Requires: $countries, $warehouses, $shippingMethods
    Optional: $listing (edit mode), $selectedVariant
--}}
@php
    $listing = $listing ?? null;
    $isEdit  = $listing !== null;
    $selectedVariant = $selectedVariant ?? null;
    $val = fn(string $f, $d = '') => old($f, $isEdit ? (($listing->{$f} ?? null) instanceof \BackedEnum ? $listing->{$f}->value : ($listing->{$f} ?? $d)) : $d);
    $bool = fn(string $f, bool $d = false): bool => (bool) old($f, $isEdit ? ($listing->{$f} ?? $d) : $d);

    $platformWarehouses = $warehouses->filter(fn($w) => ($w->type?->value ?? $w->type) === 'platform_fbn')->values();
@endphp

<div
    x-data="{
        activeTab: 'basic',
        condition: '{{ $val('condition', 'new') }}',
        isDailyDeal: {{ $bool('is_daily_deal') ? 'true' : 'false' }},
    }"
    class="space-y-6"
>
    {{-- ═══════════════════════════════════════════════ --}}
    {{-- TOP BANNER — always visible, not a tab           --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-6 flex items-center gap-3">
        <span class="text-2xl">⚡</span>
        <div>
            <p class="text-sm font-semibold text-yellow-800">
                {{ __('admin.admin_listings.platform_banner_title') }}
            </p>
            <p class="text-xs text-yellow-700 mt-0.5">
                {{ __('admin.admin_listings.platform_banner_body') }}
            </p>
        </div>
    </div>

    {{-- Tab navigation --}}
    <div class="bg-white rounded-t-xl border border-gray-200 overflow-hidden">
        <nav class="flex overflow-x-auto border-b border-gray-100">
            @foreach([
                ['id' => 'basic',       'label' => __('admin.admin_listings.tab_basic_info'),        'icon' => 'information-circle'],
                ['id' => 'fulfillment', 'label' => __('admin.admin_listings.tab_fulfillment'),        'icon' => 'archive-box'],
                ['id' => 'dimensions',  'label' => __('admin.admin_listings.tab_dimensions'),         'icon' => 'cube'],
                ['id' => 'identity',    'label' => __('admin.admin_listings.tab_platform_identity'),  'icon' => 'sparkles'],
                ['id' => 'settings',    'label' => __('admin.admin_listings.tab_settings'),           'icon' => 'cog-6-tooth'],
            ] as $tab)
            <button
                type="button"
                @click="activeTab = '{{ $tab['id'] }}'"
                :class="activeTab === '{{ $tab['id'] }}'
                    ? 'border-b-2 border-primary-600 text-primary-700 bg-primary-50/50'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                class="flex items-center gap-1.5 px-3 sm:px-4 py-3 sm:py-3.5 text-sm font-medium -mb-px whitespace-nowrap transition-colors"
            >
                <x-heroicon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                {{ $tab['label'] }}
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ─────────────────────────────────────────────── --}}
    {{-- TAB: Basic Info                                  --}}
    {{-- ─────────────────────────────────────────────── --}}
    <div x-show="activeTab === 'basic'" class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5 -mt-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.product_variant_required') }} <span class="text-red-500">*</span></label>

            @if($isEdit)
                <input type="text" readonly disabled
                       value="{{ $selectedVariant?->product?->name_en }} ({{ $selectedVariant?->variant_name }}) — {{ $selectedVariant?->sku }}"
                       class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500 focus:outline-none">
                <input type="hidden" name="product_variant_id" value="{{ $listing->product_variant_id }}">
                <p class="mt-1 text-xs text-gray-400">{{ __('admin.admin_listings.variant_locked_hint') }}</p>
            @else
                <select name="product_variant_id" required data-async-select
                        data-config='{{ json_encode(["url" => route("admin.admin-listings.search-variants"), "param" => "q", "minLength" => 2, "delay" => 300]) }}'
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @if($selectedVariant)
                        <option value="{{ $selectedVariant->id }}" selected>
                            {{ $selectedVariant->product?->name_en }} ({{ $selectedVariant->variant_name }}) [{{ $selectedVariant->slug }}] — {{ $selectedVariant->sku }}
                        </option>
                    @elseif(old('product_variant_id'))
                        {{-- Restore after validation failure — variant UUID preserved via old() --}}
                        <option value="{{ old('product_variant_id') }}" selected>
                            {{ old('product_variant_id') }}
                        </option>
                    @endif
                </select>
            @endif
            @error('product_variant_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.country_required') }} <span class="text-red-500">*</span></label>

            @if($isEdit)
                <input type="text" readonly disabled
                       value="{{ $listing->country?->name_en }}"
                       class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500 focus:outline-none">
                <input type="hidden" name="country_id" value="{{ $listing->country_id }}">
                <p class="mt-1 text-xs text-gray-400">{{ __('admin.admin_listings.country_locked_hint') }}</p>
            @else
                <select name="country_id" required data-select2-init
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="">{{ __('admin.admin_listings.select_country') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" {{ $val('country_id') === $country->id ? 'selected' : '' }}>
                            {{ $country->name_en }}
                        </option>
                    @endforeach
                </select>
            @endif
            @error('country_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.price_base_units_label') }} <span class="text-red-500">*</span></label>
                <input type="number" name="price" min="0" step="1" required
                       value="{{ $val('price', 0) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.compare_at_price') }}</label>
                <input type="number" name="compare_at_price" min="0" step="1"
                       value="{{ $val('compare_at_price') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <p class="mt-1 text-xs text-gray-400">{{ __('admin.admin_listings.compare_at_price_hint') }}</p>
                @error('compare_at_price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.internal_cost_price_label') }}</label>
                <input type="number" name="cost_price" min="0" step="1"
                       value="{{ $val('cost_price') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('cost_price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.condition_required') }} <span class="text-red-500">*</span></label>
                <select name="condition" required x-model="condition"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @foreach([
                        'new' => __('admin.admin_listings.condition_new'),
                        'like_new' => __('admin.admin_listings.condition_like_new'),
                        'good' => __('admin.admin_listings.condition_good'),
                        'acceptable' => __('admin.admin_listings.condition_acceptable'),
                        'refurbished' => __('admin.admin_listings.condition_refurbished'),
                    ] as $v => $l)
                        <option value="{{ $v }}" {{ $val('condition', 'new') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
                @error('condition')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-show="condition !== 'new'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.condition_notes') }}</label>
                <textarea name="condition_notes" rows="1"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">{{ $val('condition_notes') }}</textarea>
                @error('condition_notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────── --}}
    {{-- TAB: Fulfillment & Shipping                      --}}
    {{-- ─────────────────────────────────────────────── --}}
    <div x-show="activeTab === 'fulfillment'" class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5 -mt-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.warehouse') }}</label>
            <select name="warehouse_id" data-select2-init
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('admin.admin_listings.no_warehouse_option') }}</option>
                @foreach($platformWarehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" data-country-id="{{ $warehouse->country_id }}"
                            {{ $val('warehouse_id') === $warehouse->id ? 'selected' : '' }}>
                        {{ $warehouse->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-400">{{ __('admin.admin_listings.platform_fbn_warehouses_only') }}</p>
            <div id="no-warehouses-warning" class="hidden mt-2 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-700">
                {{ __('admin.admin_listings.no_platform_fbn_warehouses') }}
            </div>
            @error('warehouse_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.primary_shipping_method') }}</label>
            <select name="primary_shipping_method_id" data-select2-init
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ $isEdit ? __('admin.admin_listings.shipping_method_use_category_default') : __('admin.admin_listings.no_shipping_method') }}</option>
                @foreach($shippingMethods as $method)
                    <option value="{{ $method->id }}" {{ $val('primary_shipping_method_id') === $method->id ? 'selected' : '' }}>
                        {{ $method->name }}
                    </option>
                @endforeach
            </select>
            @error('primary_shipping_method_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="hidden" name="vendor_covers_delivery" value="0">
            <input type="checkbox" name="vendor_covers_delivery" value="1"
                   {{ $bool('vendor_covers_delivery') ? 'checked' : '' }}
                   class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
            <span class="text-sm text-gray-700">{{ __('admin.admin_listings.platform_covers_delivery') }}</span>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.max_order_quantity') }}</label>
                <input type="number" name="max_order_quantity" min="1" step="1"
                       value="{{ $val('max_order_quantity') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('max_order_quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.low_stock_threshold') }}</label>
                <input type="number" name="low_stock_threshold" min="0" step="1"
                       value="{{ $val('low_stock_threshold', 5) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('low_stock_threshold')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────── --}}
    {{-- TAB: Dimensions & Handling                       --}}
    {{-- ─────────────────────────────────────────────── --}}
    <div x-show="activeTab === 'dimensions'" class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5 -mt-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.weight_class') }}</label>
                <select name="weight_class"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="">—</option>
                    @foreach(['light' => __('admin.admin_listings.weight_class_light'), 'medium' => __('admin.admin_listings.weight_class_medium'), 'heavy' => __('admin.admin_listings.weight_class_heavy')] as $v => $l)
                        <option value="{{ $v }}" {{ $val('weight_class') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
                @error('weight_class')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.handling_class_required') }} <span class="text-red-500">*</span></label>
                <select name="handling_class" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @foreach([
                        'standard' => __('admin.admin_listings.handling_standard'),
                        'refrigerated' => __('admin.admin_listings.handling_refrigerated'),
                        'fragile' => __('admin.admin_listings.handling_fragile'),
                        'special_tech' => __('admin.admin_listings.handling_special_tech'),
                    ] as $v => $l)
                        <option value="{{ $v }}" {{ $val('handling_class', 'standard') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
                @error('handling_class')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.declared_weight_grams') }}</label>
            <input type="number" name="declared_weight_grams" min="0" step="1"
                   value="{{ $val('declared_weight_grams') }}"
                   class="w-full sm:w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            @error('declared_weight_grams')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.declared_length_cm') }}</label>
                <input type="number" name="declared_length_cm" min="0" step="0.01"
                       value="{{ $val('declared_length_cm') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('declared_length_cm')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.declared_width_cm') }}</label>
                <input type="number" name="declared_width_cm" min="0" step="0.01"
                       value="{{ $val('declared_width_cm') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('declared_width_cm')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.declared_height_cm') }}</label>
                <input type="number" name="declared_height_cm" min="0" step="0.01"
                       value="{{ $val('declared_height_cm') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('declared_height_cm')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────── --}}
    {{-- TAB: Platform Identity                           --}}
    {{-- ─────────────────────────────────────────────── --}}
    <div x-show="activeTab === 'identity'" class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5 -mt-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.sold_by_en') }}</label>
                <input type="text" name="sold_by_label_en" dir="ltr" maxlength="150"
                       value="{{ $val('sold_by_label_en', 'Nawy') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('sold_by_label_en')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.sold_by_ar') }}</label>
                <input type="text" name="sold_by_label_ar" dir="rtl" maxlength="150"
                       value="{{ $val('sold_by_label_ar', 'نوي') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('sold_by_label_ar')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.express_badge_label_en') }}</label>
                <input type="text" name="express_badge_label_en" dir="ltr" maxlength="100"
                       value="{{ $val('express_badge_label_en', 'Noon Express') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('express_badge_label_en')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.express_badge_label_ar') }}</label>
                <input type="text" name="express_badge_label_ar" dir="rtl" maxlength="100"
                       value="{{ $val('express_badge_label_ar', 'نون إكسبرس') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('express_badge_label_ar')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.search_boost') }}</label>
            <input type="number" name="search_boost" min="0" max="20" step="1"
                   value="{{ $val('search_boost', 10) }}"
                   class="w-full sm:w-1/3 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="mt-1 text-xs text-gray-400">{{ __('admin.admin_listings.search_boost_hint') }}</p>
            @error('search_boost')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="hidden" name="is_daily_deal" value="0">
            <input type="checkbox" name="is_daily_deal" value="1" x-model="isDailyDeal"
                   {{ $bool('is_daily_deal') ? 'checked' : '' }}
                   class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
            <span class="text-sm text-gray-700">{{ __('admin.admin_listings.is_daily_deal') }}</span>
        </label>

        <div x-show="isDailyDeal" x-cloak>
            <x-form.date-picker
                name="daily_deal_ends_at"
                label="{{ __('admin.admin_listings.daily_deal_ends_at') }}"
                :value="$val('daily_deal_ends_at')"
                :enable-time="true"
            />
        </div>
    </div>

    {{-- ─────────────────────────────────────────────── --}}
    {{-- TAB: Settings                                    --}}
    {{-- ─────────────────────────────────────────────── --}}
    <div x-show="activeTab === 'settings'" class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 shadow-sm space-y-5 -mt-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.status_required') }} <span class="text-red-500">*</span></label>
            <select name="status" required
                    class="w-full sm:w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                @foreach([
                    'active' => __('admin.admin_listings.active'),
                    'paused' => __('admin.admin_listings.paused'),
                    'out_of_stock' => __('admin.admin_listings.out_of_stock_status'),
                    'archived' => __('admin.admin_listings.archived'),
                ] as $v => $l)
                    <option value="{{ $v }}" {{ $val('status', 'active') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
            @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="hidden" name="buy_box_eligible" value="0">
            <input type="checkbox" name="buy_box_eligible" value="1"
                   {{ $bool('buy_box_eligible', true) ? 'checked' : '' }}
                   class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
            <span class="text-sm text-gray-700">{{ __('admin.admin_listings.buy_box_eligible') }}</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="hidden" name="campaign_enabled" value="0">
            <input type="checkbox" name="campaign_enabled" value="1"
                   {{ $bool('campaign_enabled') ? 'checked' : '' }}
                   class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
            <span class="text-sm text-gray-700">{{ __('admin.admin_listings.campaign_enabled') }}</span>
        </label>
    </div>

    {{-- Errors summary + submit --}}
    <div class="flex items-center gap-3 pt-2">
        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-lg hover:bg-primary-700 transition-colors">
            <x-heroicon name="check" class="w-4 h-4" />
            {{ $isEdit ? __('admin.admin_listings.save_changes') : __('admin.admin_listings.create_listing') }}
        </button>
        <a href="{{ route('admin.admin-listings.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700">{{ __('admin.admin_listings.cancel') }}</a>
    </div>

    @if($errors->any())
    <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-700 space-y-1">
        @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
        @endforeach
    </div>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var warehousesData = @json($platformWarehouses->map(fn($w) => ['id' => $w->id, 'name' => $w->name, 'country_id' => $w->country_id])->values());
        var selectedWarehouseId = @json($val('warehouse_id'));
        var $warehouseSelect = window.jQuery ? jQuery('[name="warehouse_id"]') : null;
        var $warningBox = document.getElementById('no-warehouses-warning');

        function renderWarehouseOptions(countryId) {
            if (!$warehouseSelect) return;

            var matches = warehousesData.filter(function (w) { return w.country_id === countryId; });

            $warehouseSelect.empty();
            $warehouseSelect.append(new Option('{{ __('admin.admin_listings.no_warehouse_option') }}', '', false, false));
            matches.forEach(function (w) {
                $warehouseSelect.append(new Option(w.name, w.id, false, w.id === selectedWarehouseId));
            });
            $warehouseSelect.trigger('change');

            if ($warningBox) {
                $warningBox.classList.toggle('hidden', !countryId || matches.length > 0);
            }
        }

        var $countrySelect = window.jQuery ? jQuery('[name="country_id"]') : null;
        var initialCountryId = @json($val('country_id'));

        if ($countrySelect && $countrySelect.length) {
            $countrySelect.on('select2:select change', function () {
                renderWarehouseOptions(this.value);
            });
        }

        if (initialCountryId) {
            renderWarehouseOptions(initialCountryId);
        }
    });
</script>
