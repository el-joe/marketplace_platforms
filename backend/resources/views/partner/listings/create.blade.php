@extends('layouts.partner')

@section('title', __('partner.listings.add_product_listing_title'))
@section('page-title', __('partner.listings.add_product_listing_title'))

@push('scripts')
    @vite(['resources/js/components/select2.js', 'resources/js/partner/listings.js', 'resources/js/partner/listing-create-campaign.js'])
    <script>
        window.LISTINGS_CREATE = {
            productSearchUrl: '{{ route("partner.listings.product-search") }}',
            slugPreviewUrlTemplate: '{{ route("partner.listings.slug-preview", ["product" => "__PRODUCT__", "variant" => "__VARIANT__"]) }}',
            urlInfoUrlTemplate: '{{ route("partner.listings.variants.url-info", ["variant" => "__VARIANT__"]) }}',
            warehousesByCountryUrl: '{{ route("partner.listings.warehouses-by-country") }}',
            availableShippingMethodsUrl: '{{ route("partner.listings.available-shipping-methods") }}',
            categorySamplesUrl: '{{ route("partner.listings.category-samples") }}',
            campaignPricingUrl: '{{ route("partner.listings.campaign-pricing") }}',
            influencerFeeUrl: '{{ route("partner.listings.marketer-fee") }}',
            storeUrl: '{{ route("partner.listings.store") }}',
            csrf: '{{ csrf_token() }}',
        };
    </script>
@endpush

@section('content')

    <div class="mb-4">
        <a href="{{ route('partner.listings.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            {{ __('partner.listings.back_to_listings') }}
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- LEFT: Step 1 — Product search --}}
        <div class="lg:col-span-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 sticky top-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ __('partner.listings.step1_search_product') }}</h3>
                <p class="text-xs text-gray-500 mb-4">{{ __('partner.listings.search_product_desc') }}</p>

                <div class="relative mb-4">
                    <input type="text" id="product-search-input" placeholder="{{ __('partner.listings.search_placeholder') }}"
                        autocomplete="off"
                        class="w-full border border-gray-200 rounded-xl pr-4 pl-10 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                    <svg class="w-4 h-4 text-gray-400 absolute top-3 left-3 pointer-events-none" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                {{-- Search results --}}
                <div id="product-search-results" class="space-y-2 max-h-96 overflow-y-auto">
                    <p class="text-xs text-gray-400 text-center py-6">{{ __('partner.listings.start_typing_to_search') }}</p>
                </div>
            </div>
        </div>

        {{-- RIGHT: Step 2 — Listing form (shown after selecting a product+variant) --}}
        <div class="lg:col-span-7">

            {{-- Placeholder before selection --}}
            <div id="listing-form-placeholder"
                class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 flex flex-col items-center justify-center text-center">
                <div class="w-14 h-14 bg-gray-100 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10" />
                    </svg>
                </div>
                <p class="text-gray-500 text-sm font-medium">{{ __('partner.listings.select_product_variant') }}</p>
                <p class="text-gray-400 text-xs mt-1">{{ __('partner.listings.form_will_appear') }}</p>
            </div>

            {{-- Actual form (hidden until product selected) --}}
            <div id="listing-form-container" class="hidden">
                <form id="listing-create-form" class="space-y-5">
                    {{-- Selected product info card --}}
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div id="selected-product-info" class="flex items-start gap-4">
                            <div id="selected-img"
                                class="w-14 h-14 rounded-xl border border-gray-100 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="selected-product-name" class="font-semibold text-gray-900 text-sm"></p>
                                <p id="selected-variant-name" class="text-xs text-gray-500 mt-0.5"></p>
                                <p id="selected-sku" class="text-xs font-mono text-gray-400 mt-0.5"></p>
                            </div>
                            <button type="button" id="change-product-btn"
                                class="text-xs text-blue-600 hover:underline shrink-0">{{ __('partner.listings.change') }}</button>
                        </div>
                        <input type="hidden" id="form-product-variant-id" name="product_variant_id">

                        <div id="customer-url-preview-wrap" class="hidden mt-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('partner.listings.customer_url') ?? 'Customer URL' }}</label>
                            <p class="text-xs text-gray-400 mb-1">{{ __('partner.listings.customer_url_hint') ?? 'This is the URL customers will see for your listing.' }}</p>
                            <p class="text-xs text-gray-500 mb-1">
                                {{ __('partner.listings.attribute_summary') ?? 'Variant' }}:
                                <span id="customer-url-attribute-summary" class="font-medium text-gray-700">—</span>
                            </p>
                            <input type="text" id="customer-url-preview" readonly
                                class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-xs font-mono text-gray-500 focus:outline-none">
                        </div>
                    </div>

                    {{-- Pricing & Details --}}
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                        <h4 class="font-semibold text-gray-800 text-sm mb-2">{{ __('partner.listings.step2_listing_data') }}</h4>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('common.price') }} <span
                                        class="text-red-500">*</span></label>
                                <input type="number" name="price" step="0.01" min="0.01" required
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                                    placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('common.country') }} <span
                                        class="text-red-500">*</span></label>
                                <select disabled
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-100 text-gray-500 cursor-not-allowed focus:outline-none">
                                    <option value="">{{ __('common.select') }}...</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ $country->id === $countryId ? 'selected' : '' }}>
                                            {{ $country->name_ar ?: $country->name_en }} ({{ $country->currency_code }})
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="country_id" value="{{ $countryId }}">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.condition') }} <span
                                        class="text-red-500">*</span></label>
                                <select name="condition" required
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                                    @foreach($conditions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.fulfillment_model') }} <span
                                        class="text-red-500">*</span></label>
                                <select name="fulfillment_model" required
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                                    @foreach($fulfillmentModels as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.vendor_sku') }}</label>
                                <input type="text" name="vendor_sku" maxlength="100"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                                    placeholder="{{ __('common.optional') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.max_order_quantity') }}</label>
                                <input type="number" name="max_order_quantity" min="1" max="9999"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                                    placeholder="{{ __('partner.listings.no_limit_placeholder') }}">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.low_stock_threshold') }}</label>
                            <input type="number" name="low_stock_threshold" min="0" value="5"
                                class="w-32 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            <p class="text-xs text-gray-400 mt-1">{{ __('partner.listings.low_stock_threshold_hint') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('common.notes') }}</label>
                            <textarea name="vendor_notes" rows="2" maxlength="1000"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40 resize-none"
                                placeholder="{{ __('partner.listings.internal_notes_placeholder') }}"></textarea>
                        </div>

                        <div id="vendor-covers-delivery-field" class="hidden">
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" name="vendor_covers_delivery" value="1"
                                    class="mt-1 rounded border-gray-300 text-yellow-500 focus:ring-yellow-400/40">
                                <span class="text-sm text-gray-700">
                                    أتحمل تكاليف التوصيل المتبقية / I cover remaining delivery costs
                                    <span class="block text-xs text-gray-400 mt-0.5">
                                        إذا فعّلت هذا الخيار، سيظهر التوصيل مجانياً للعميل حتى لو كانت هناك فجوة بعد دعم المنصة. /
                                        If enabled, delivery appears free to customer even if there's a gap after platform subsidy.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    {{-- Shipping & Dimensions --}}
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4"
                        x-data="{
                            l: 0, w: 0, h: 0, actual: 0,
                            get volumetric() { return (this.l && this.w && this.h) ? Math.ceil((this.l * this.w * this.h) / 5) : 0; },
                            get billable() { return Math.max(this.actual, this.volumetric); },
                            get weightClass() {
                                if (this.billable <= 1000) return 'خفيف / Light';
                                if (this.billable <= 5000) return 'متوسط / Medium';
                                return 'ثقيل / Heavy';
                            }
                        }">
                        <h4 class="font-semibold text-gray-800 text-sm mb-1">الشحن والأبعاد / Shipping &amp; Dimensions</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                وزن المنتج (جرام) / Product Weight (grams) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="declared_weight_grams" min="1" step="1" required
                                x-model.number="actual"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                                placeholder="0">
                            <p class="text-xs text-gray-400 mt-1">Enter the packed/boxed weight including packaging</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                أبعاد التغليف / Packaged Dimensions (cm)
                            </label>
                            <div class="grid grid-cols-3 gap-3">
                                <input type="number" name="declared_length_cm" min="0.1" step="0.1" x-model.number="l"
                                    placeholder="L"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                                <input type="number" name="declared_width_cm" min="0.1" step="0.1" x-model.number="w"
                                    placeholder="W"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                                <input type="number" name="declared_height_cm" min="0.1" step="0.1" x-model.number="h"
                                    placeholder="H"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            </div>
                            <p class="text-xs text-gray-400 mt-1">L × W × H ÷ 5 = وزن حجمي بالجرام (Length × Width × Height ÷ 5 = volumetric grams)</p>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4 space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">الوزن الحجمي / Volumetric Weight</span>
                                <span class="font-medium text-gray-800" x-text="volumetric + ' g'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">الوزن الفعلي / Actual Weight</span>
                                <span class="font-medium text-gray-800" x-text="actual + ' g'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">الوزن القابل للفوترة / Billable Weight</span>
                                <span class="font-bold text-gray-900" x-text="billable + ' g'"></span>
                            </div>
                            <div class="flex justify-between pt-1.5 border-t border-gray-100">
                                <span class="text-gray-500">تصنيف الوزن / Weight Class</span>
                                <span class="font-semibold text-yellow-600" x-text="weightClass"></span>
                            </div>
                        </div>

                        <a href="{{ route('partner.tools.weight-calculator') }}" target="_blank"
                           class="text-sm text-blue-600 hover:underline">
                            📐 فتح حاسبة الوزن / Open Weight Calculator
                        </a>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                فئة المناولة / Handling Class <span class="text-red-500">*</span>
                            </label>
                            <select name="handling_class" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                                <option value="standard">عادي / Standard</option>
                                <option value="refrigerated">يحتاج تبريد / Requires Refrigeration</option>
                                <option value="fragile">هش - يحتاج حرص / Fragile - Handle with Care</option>
                                <option value="special_tech">يحتاج تقنية خاصة / Requires Special Handling</option>
                            </select>
                        </div>
                    </div>

                    {{-- Preferred Shipping Method --}}
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-3">
                        <h4 class="font-semibold text-gray-800 text-sm mb-1">{{ __('partner.listings.preferred_shipping_method') ?? 'Primary Shipping Method (optional — overrides category default)' }}</h4>
                        <select name="primary_shipping_method_id" id="primary-shipping-method-select"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            <option value="">{{ __('partner.listings.shipping_method_default_option') ?? 'Use category default' }}</option>
                        </select>
                        <p class="text-xs text-gray-400">{{ __('partner.listings.preferred_shipping_method_hint') ?? "If not set, the category default method will be used automatically" }}</p>
                    </div>

                    {{-- Warehouse & Initial Stock --}}
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                        <h4 class="font-semibold text-gray-800 text-sm mb-2">{{ __('partner.listings.step3_warehouse_stock') }}</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.warehouse') }} <span
                                    class="text-red-500">*</span></label>
                            <select name="warehouse_id" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                                <option value="">{{ __('partner.listings.select_warehouse_placeholder') }}</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">
                                        {{ $warehouse->name }}
                                        @if($warehouse->code) ({{ $warehouse->code }}) @endif
                                        —
                                        {{ match ($warehouse->type?->value) { 'seller_owned' => __('partner.listings.vendor_warehouse_type'), 'platform_fbn' => __('partner.listings.platform_warehouse_type'), default => $warehouse->type?->value} }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.initial_quantity') }} <span
                                    class="text-red-500">*</span></label>
                            <input type="number" name="initial_quantity" min="0" value="0" required
                                class="w-40 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            <p class="text-xs text-gray-400 mt-1">{{ __('partner.listings.initial_quantity_hint') }}</p>
                        </div>
                    </div>

                    {{-- Marketer Campaign --}}
                    <div class="bg-white rounded-2xl border border-purple-200 p-6 space-y-4" id="campaign-section">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="campaign_enabled" value="1" id="campaign-enabled-checkbox"
                                class="mt-1 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm text-gray-700">
                                <i class="fas fa-bullhorn text-purple-500 mr-1"></i>
                                تفعيل حملة ماركتر لهذا المنتج
                                <span class="block text-xs text-gray-400 mt-0.5">
                                    متاح فقط لقوائم FBN — يتيح لك دعوة ماركترز للترويج مقابل عمولة.
                                </span>
                            </span>
                        </label>

                        <p class="text-xs text-amber-600 bg-amber-50 rounded-lg p-3 hidden" id="campaign-not-fbn-warning">
                            يجب اختيار نموذج التنفيذ FBN لتفعيل حملة الماركتر.
                        </p>

                        <div class="space-y-4 hidden" id="campaign-details">
                            @if($marketerVendors->isEmpty())
                                <p class="text-xs text-amber-600 bg-amber-50 rounded-lg p-3">
                                    لا يوجد ماركترز متاحين في بلدك حالياً. يمكن للأدمن تفعيل ماركترز من لوحة التحكم.
                                </p>
                            @else
                            <x-form.select
                                name="marketer_vendor_ids"
                                label="اختر الماركترز"
                                :multiple="true"
                                :select2="true"
                                placeholder="ابحث واختر الماركترز..."
                            >
                                @foreach($marketerVendors as $m)
                                    <option value="{{ $m->id }}" data-type="{{ $m->marketer_type }}"
                                            data-name="{{ $m->name ?? $m->business_name }}">
                                        {{ $m->name ?? $m->business_name }} — {{ $m->marketer_type === 'influencer' ? 'مؤثر' : 'أفلييت' }}
                                    </option>
                                @endforeach
                            </x-form.select>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="campaign-pricing-cards">
                                <div class="p-4 rounded-lg border bg-gray-50 border-gray-200" id="fee-card">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="fas fa-receipt text-orange-500 text-sm"></i>
                                        <span class="text-xs font-semibold text-gray-700">رسوم المنصة (إنفلوينسر فقط)</span>
                                    </div>
                                    <div class="text-sm text-gray-800" id="fee-breakdown-text">اختر منتجاً وماركترز لرؤية الرسوم</div>
                                    <div class="mt-2 text-base font-bold text-orange-700 hidden" id="fee-total-wrap">
                                        الإجمالي: <span id="fee-total-value">0</span> <span id="fee-currency"></span>
                                    </div>
                                </div>
                                <div class="p-4 rounded-lg border bg-green-50 border-green-200">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="fas fa-percentage text-green-500 text-sm"></i>
                                        <span class="text-xs font-semibold text-gray-700">كوميشن الماركتر لكل بيعة</span>
                                    </div>
                                    <div class="text-sm text-gray-800" id="commission-breakdown-text">سيتم تحديده بعد اختيار المنتج</div>
                                </div>
                            </div>

                            {{-- Per-marketer fee breakdown table --}}
                            <div class="hidden" id="marketer-fee-table-wrap">
                                <h5 class="text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-receipt text-orange-500 mr-1"></i>
                                    تفاصيل الرسوم لكل ماركتر
                                </h5>
                                <div class="rounded-lg border border-gray-200 overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="text-right px-4 py-2 text-gray-600 font-medium">الماركتر</th>
                                                <th class="text-center px-4 py-2 text-gray-600 font-medium">النوع</th>
                                                <th class="text-center px-4 py-2 text-gray-600 font-medium">الرسوم</th>
                                            </tr>
                                        </thead>
                                        <tbody id="marketer-fee-table-body"></tbody>
                                        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                            <tr>
                                                <td colspan="2" class="px-4 py-2 font-semibold text-gray-700 text-right">إجمالي رسوم المنصة</td>
                                                <td class="px-4 py-2 text-center font-bold" id="marketer-fee-table-total"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">نوع الكوميشن</label>
                                <select name="commission_type" id="commission-type-select"
                                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40">
                                    <option value="fixed">{{ __('partner.listings.commission_type_fixed') }}</option>
                                    <option value="tiered">{{ __('partner.listings.commission_type_tiered') }}</option>
                                    <option value="last_click">{{ __('partner.listings.commission_type_last_click') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    أقصى ميزانية كوميشن
                                    <span class="text-xs text-gray-400">({{ auth()->guard('vendor')->user()->vendor->country->currency_code ?? '' }})</span>
                                </label>
                                <input type="number" name="max_commission_budget" min="0"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40"
                                       placeholder="0">
                            </div>

                            <div class="hidden" id="tiered-rules-section">
                                <label class="block text-sm font-medium text-gray-700 mb-2">قواعد الكوميشن المتدرج</label>
                                <div class="space-y-2" id="tiered-rules-list"></div>
                                <button type="button" id="add-tier-btn"
                                        class="mt-2 text-sm text-purple-600 hover:underline">
                                    + إضافة مستوى
                                </button>
                            </div>

                            <div class="p-3 bg-purple-50 rounded-lg text-sm text-purple-800">
                                <i class="fas fa-box-open mr-1"></i>
                                <span class="font-semibold">إجمالي العينات المتوقع:</span>
                                <strong id="total-samples-value">0</strong>
                                <span class="block text-xs text-gray-500 mt-1" id="sample-breakdown-text"></span>
                            </div>
                        </div>
                    </div>

                    <div id="create-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-4"></div>

                    <button type="submit" id="create-submit-btn"
                        class="w-full bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold py-3 rounded-xl transition-colors text-sm">
                        {{ __('partner.listings.create_listing_button') }}
                    </button>

                </form>
            </div>
        </div>

    </div>


@endsection
