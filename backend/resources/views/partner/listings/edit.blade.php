@extends('layouts.partner')

@php
    $product = $listing->productVariant->product;
    $variant = $listing->productVariant;
    $primaryImg = $product->images->where('is_primary', true)->first() ?? $product->images->first();
@endphp

@section('title', 'تعديل القائمة')
@section('page-title', 'تعديل القائمة')

@section('content')

    <div class="mb-4">
        <a href="{{ route('partner.listings.show', $listing) }}"
            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            العودة إلى القائمة
        </a>
    </div>

    @if($listing->status->value === 'rejected' && $listing->rejection_reason)
        <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded mb-4">
            <strong>سبب الرفض / Rejection Reason:</strong> {{ $listing->rejection_reason }}
            <p class="mt-1 text-sm">{{ __('partner.listings.fix_and_resubmit') }}</p>
        </div>
    @endif

    @if($listing->status->value === 'active')
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3 rounded mb-4">
            هذه القائمة نشطة حالياً. يجب إيقافها مؤقتاً قبل التعديل.
        </div>
    @endif

    @if($missingCertification)
        <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded mb-4">
            ⚠ هذا المنتج يتطلب شهادة اعتماد محلية في {{ $listing->country->name_ar ?? $listing->country->name_en }}.
            لا يمكن تفعيل هذه القائمة حتى تتم الموافقة على شهادتك.
            <a href="{{ route('partner.product-certifications.index') }}" class="underline font-semibold">رفع الشهادة &rarr;</a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- LEFT: Product info (read-only) --}}
        <div class="lg:col-span-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 sticky top-6">
                <h3 class="font-semibold text-gray-800 mb-4">المنتج</h3>
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-xl border border-gray-100 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                        @if($primaryImg)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($primaryImg->disk ?? 'public')->url($primaryImg->path) }}" class="w-full h-full object-cover">
                        @else
                            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10" />
                            </svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900 text-sm">{{ $product->name_ar ?: $product->name_en }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $variant->variant_name ?: 'النسخة الافتراضية' }}</p>
                        <p class="text-xs font-mono text-gray-400 mt-0.5">{{ $variant->sku }}</p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-4">البلد: {{ $listing->country?->name_ar ?: $listing->country?->name_en }} ({{ $listing->currency }})</p>

                @php $customerUrl = "/products/{$variant->id}/{$listing->id}"; @endphp
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('partner.listings.customer_url') ?? 'Customer URL' }}</label>
                    <p class="text-xs text-gray-400 mb-1">{{ __('partner.listings.customer_url_hint') ?? 'This is the URL customers will see for your listing.' }}</p>
                    <p class="text-xs text-gray-500 mb-1">{{ $variant->attributeSummary() }}</p>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="{{ $customerUrl }}"
                            class="flex-1 border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-xs font-mono text-gray-500 focus:outline-none">
                        <button type="button" class="js-copy px-3 py-2 text-xs font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50"
                                data-value="{{ $customerUrl }}">
                            {{ __('partner.listings.copy_url') ?? 'Copy URL' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Editable form --}}
        <div class="lg:col-span-7">
            <form method="POST" action="{{ route('partner.listings.update', $listing) }}" class="space-y-5">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="text-sm text-red-600 bg-red-50 rounded-lg p-4">
                        <ul class="list-disc pr-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h4 class="font-semibold text-gray-800 text-sm mb-2">بيانات القائمة</h4>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('common.price') }} <span class="text-red-500">*</span></label>
                            <input type="number" name="price" step="1" min="1" required
                                value="{{ old('price', $listing->price) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            <p class="text-xs text-gray-400 mt-1">{{ __('partner.listings.price_whole_number_hint') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">الحالة <span class="text-red-500">*</span></label>
                            <select name="condition" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                                @foreach($conditions as $key => $label)
                                    <option value="{{ $key }}" {{ old('condition', $listing->condition) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">نموذج التنفيذ <span class="text-red-500">*</span></label>
                            <select name="fulfillment_model" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                                @foreach($fulfillmentModels as $key => $label)
                                    <option value="{{ $key }}" {{ old('fulfillment_model', $listing->fulfillment_model) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">SKU الخاص بالبائع</label>
                            <input type="text" name="vendor_sku" maxlength="100" value="{{ old('vendor_sku', $listing->vendor_sku) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">الحد الأقصى للطلب</label>
                            <input type="number" name="max_order_quantity" min="1" max="9999" value="{{ old('max_order_quantity', $listing->max_order_quantity) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">حد المخزون المنخفض</label>
                            <input type="number" name="low_stock_threshold" min="0" value="{{ old('low_stock_threshold', $listing->low_stock_threshold) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('common.notes') }}</label>
                        <textarea name="vendor_notes" rows="2" maxlength="1000"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40 resize-none">{{ old('vendor_notes', $listing->vendor_notes) }}</textarea>
                    </div>

                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="vendor_covers_delivery" value="1" {{ old('vendor_covers_delivery', $listing->vendor_covers_delivery) ? 'checked' : '' }}
                            class="mt-1 rounded border-gray-300 text-yellow-500 focus:ring-yellow-400/40">
                        <span class="text-sm text-gray-700">أتحمل تكاليف التوصيل المتبقية / I cover remaining delivery costs</span>
                    </label>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h4 class="font-semibold text-gray-800 text-sm mb-1">الشحن والأبعاد</h4>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">وزن المنتج (جرام) <span class="text-red-500">*</span></label>
                        <input type="number" name="declared_weight_grams" min="1" step="1" required
                            value="{{ old('declared_weight_grams', $listing->declared_weight_grams) }}"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">أبعاد التغليف (سم)</label>
                        <div class="grid grid-cols-3 gap-3">
                            <input type="number" name="declared_length_cm" min="0.1" step="0.1" placeholder="L"
                                value="{{ old('declared_length_cm', $listing->declared_length_cm) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            <input type="number" name="declared_width_cm" min="0.1" step="0.1" placeholder="W"
                                value="{{ old('declared_width_cm', $listing->declared_width_cm) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            <input type="number" name="declared_height_cm" min="0.1" step="0.1" placeholder="H"
                                value="{{ old('declared_height_cm', $listing->declared_height_cm) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">فئة المناولة <span class="text-red-500">*</span></label>
                        <select name="handling_class" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            <option value="standard" {{ old('handling_class', $listing->handling_class) === 'standard' ? 'selected' : '' }}>عادي / Standard</option>
                            <option value="refrigerated" {{ old('handling_class', $listing->handling_class) === 'refrigerated' ? 'selected' : '' }}>يحتاج تبريد / Requires Refrigeration</option>
                            <option value="fragile" {{ old('handling_class', $listing->handling_class) === 'fragile' ? 'selected' : '' }}>هش - يحتاج حرص / Fragile</option>
                            <option value="special_tech" {{ old('handling_class', $listing->handling_class) === 'special_tech' ? 'selected' : '' }}>يحتاج تقنية خاصة / Special Handling</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-3">
                    <h4 class="font-semibold text-gray-800 text-sm mb-1">{{ __('partner.listings.preferred_shipping_method') ?? 'Primary Shipping Method (optional — overrides category default)' }}</h4>
                    <select name="primary_shipping_method_id"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        <option value="">{{ __('partner.listings.shipping_method_default_option') ?? 'Use category default' }}</option>
                        @foreach($availableShippingMethods as $method)
                            <option value="{{ $method->id }}" {{ old('primary_shipping_method_id', $listing->primary_shipping_method_id) === $method->id ? 'selected' : '' }}>
                                {{ $method->name }}{{ $method->pivot->is_default ? ' (default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400">{{ __('partner.listings.preferred_shipping_method_hint') ?? "If not set, the category default method will be used automatically" }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h4 class="font-semibold text-gray-800 text-sm mb-1">عمولات التسويق</h4>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">نسبة عمولة المؤثرين</label>
                            <input type="number" name="influencer_commission_percentage" step="0.01" min="0" max="100"
                                value="{{ old('influencer_commission_percentage', $listing->influencer_commission_percentage) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">حصة عينات المؤثرين</label>
                            <input type="number" name="influencer_sample_quota" min="0" max="9999"
                                value="{{ old('influencer_sample_quota', $listing->influencer_sample_quota) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">نسبة عمولة الشركاء</label>
                            <input type="number" name="affiliate_commission_percentage" step="0.01" min="0" max="100"
                                value="{{ old('affiliate_commission_percentage', $listing->affiliate_commission_percentage) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">حصة عينات الشركاء</label>
                            <input type="number" name="affiliate_sample_quota" min="0" max="9999"
                                value="{{ old('affiliate_sample_quota', $listing->affiliate_sample_quota) }}"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        </div>
                    </div>
                </div>

                {{-- Marketer Campaign --}}
                <div id="campaign-section" class="bg-white rounded-2xl border border-purple-200 p-6 space-y-4"
                    x-data="campaignSection()">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="campaign_enabled" value="1" x-model="enabled"
                            class="mt-1 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span class="text-sm text-gray-700">
                            <i class="fas fa-bullhorn text-purple-500 mr-1"></i>
                            تفعيل حملة ماركتر لهذا المنتج
                            <span class="block text-xs text-gray-400 mt-0.5">
                                متاح فقط لقوائم FBN — يتيح لك دعوة ماركترز للترويج مقابل عمولة.
                            </span>
                        </span>
                    </label>

                    <template x-if="!isFbn">
                        <p class="text-xs text-amber-600 bg-amber-50 rounded-lg p-3">
                            يجب اختيار نموذج التنفيذ FBN لتفعيل حملة الماركتر.
                        </p>
                    </template>

                    <div x-show="enabled && isFbn" x-cloak class="space-y-4"
                        x-effect="enabled && $nextTick(() => window.initSelect2 && window.initSelect2())">
                        @if($marketerVendors->isEmpty())
                            <p class="text-xs text-amber-600 bg-amber-50 rounded-lg p-3">
                                لا يوجد ماركترز متاحين في بلدك حالياً. يمكن للأدمن تفعيل ماركترز من لوحة التحكم.
                            </p>
                        @else
                        <x-form.select
                            name="marketer_ids"
                            label="اختر الماركترز"
                            :multiple="true"
                            :select2="true"
                            placeholder="ابحث واختر الماركترز..."
                            x-on:change="updateSelectedMarketers($event)"
                        >
                            @foreach($marketerVendors as $m)
                                <option value="{{ $m->id }}" data-type="{{ $m->marketer_type }}"
                                        data-name="{{ $m->name }}">
                                    {{ $m->name }} — {{ $m->marketer_type === 'influencer' ? 'مؤثر' : 'أفلييت' }}
                                </option>
                            @endforeach
                        </x-form.select>
                        @endif

                        {{-- Per-marketer fee breakdown table --}}
                        <div x-show="selectedMarketers.length > 0" x-cloak class="mt-4">
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
                                    <tbody>
                                        <template x-for="marketer in selectedMarketers" :key="marketer.id">
                                            <tr class="border-t border-gray-100">
                                                <td class="px-4 py-2 text-gray-800" x-text="marketer.name"></td>
                                                <td class="px-4 py-2 text-center">
                                                    <span class="px-2 py-0.5 rounded-full text-xs"
                                                          :class="marketer.type === 'influencer'
                                                              ? 'bg-purple-100 text-purple-700'
                                                              : 'bg-blue-100 text-blue-700'"
                                                          x-text="marketer.type === 'influencer' ? 'إنفلوينسر' : 'أفيلييت'">
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-center font-medium"
                                                    :class="marketer.type === 'influencer' && feePerInfluencer > 0
                                                        ? 'text-orange-700' : 'text-green-600'">
                                                    <span x-show="marketer.type === 'influencer' && feePerInfluencer > 0"
                                                          x-text="feePerInfluencer + ' ' + currency">
                                                    </span>
                                                    <span x-show="!(marketer.type === 'influencer' && feePerInfluencer > 0)"
                                                          class="text-green-600">
                                                        مجاني
                                                    </span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                        <tr>
                                            <td colspan="2" class="px-4 py-2 font-semibold text-gray-700 text-right">
                                                إجمالي رسوم المنصة
                                            </td>
                                            <td class="px-4 py-2 text-center font-bold"
                                                :class="totalInfluencerFee > 0 ? 'text-orange-700' : 'text-green-600'">
                                                <span x-show="totalInfluencerFee > 0"
                                                      x-text="totalInfluencerFee + ' ' + currency"></span>
                                                <span x-show="totalInfluencerFee === 0" class="text-green-600">
                                                    مجاني
                                                </span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                رسوم المنصة تُحسب لكل إنفلوينسر مختار — الأفيلييت مجاني دائماً
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع الكوميشن</label>
                            <select name="commission_type" x-model="commissionType"
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

                        <div x-show="commissionType === 'tiered'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 mb-2">قواعد الكوميشن المتدرج</label>
                            <div class="space-y-2">
                                <template x-for="(rule, i) in tieredRules" :key="i">
                                    <div class="flex gap-2 items-center">
                                        <input type="number" :name="`tiered_rules[${i}][from_sale_number]`"
                                               x-model="rule.from_sale_number"
                                               placeholder="رقم البيعة (مثال: 10)"
                                               class="w-1/2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
                                        <input type="number" :name="`tiered_rules[${i}][commission_amount]`"
                                               x-model="rule.commission_amount"
                                               placeholder="مبلغ الكوميشن"
                                               class="w-1/2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
                                        <button type="button" @click="tieredRules.splice(i, 1)"
                                                class="text-red-500 hover:text-red-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="tieredRules.push({from_sale_number: '', commission_amount: ''})"
                                    class="mt-2 text-sm text-purple-600 hover:underline">
                                + إضافة مستوى
                            </button>
                        </div>

                        <div class="p-3 bg-purple-50 rounded-lg text-sm text-purple-800">
                            <i class="fas fa-box-open mr-1"></i>
                            إجمالي العينات المتوقع: <strong x-text="selectedMarketers.length"></strong> ماركتر مختار
                            <span class="block text-xs text-gray-500 mt-1">
                                سيتم تحديد كمية العينات النهائية تلقائياً حسب فئة المنتج بعد إنشاء الحملة.
                            </span>
                        </div>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold py-3 rounded-xl transition-colors text-sm">
                    حفظ التعديلات
                </button>
            </form>

            @if($listing->status->value === 'rejected')
                <form method="POST" action="{{ route('partner.listings.resubmit', $listing) }}" class="mt-3">
                    @csrf
                    <button type="submit"
                        class="w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold py-3 rounded-xl transition-colors text-sm">
                        Save &amp; Resubmit for Review
                    </button>
                </form>
            @endif

            {{-- Custom Fields, Add-ons, Order Notes & Size Guide --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-6 mt-6"
                x-data="customizationSection({
                    listingId: '{{ $listing->id }}',
                    hasOrderNotes: {{ $listing->has_order_notes ? 'true' : 'false' }},
                    sizeGuideUrl: @js($listing->size_guide_image_url),
                    customFields: @js($listing->customFields),
                    addonGroups: @js($listing->addonGroups),
                    fieldTemplates: @js(config('listing_field_templates')),
                    defaultSizeGuides: @js(array_filter(config('size_guides', []))),
                })">
                <h3 class="font-semibold text-gray-800">تخصيص المنتج (قياسات، إضافات، ملاحظات)</h3>

                {{-- Order notes toggle --}}
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="hasOrderNotes" @change="saveSettings()"
                        class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400">
                    <span class="text-sm text-gray-700">السماح للعميل بإضافة ملاحظة نصية عند الشراء</span>
                </label>

                {{-- Size guide image --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">صورة دليل المقاسات</label>
                    <div class="flex items-center gap-3">
                        <template x-if="sizeGuideUrl">
                            <img :src="sizeGuideUrl" class="w-16 h-16 rounded-lg border border-gray-200 object-cover">
                        </template>
                        <input type="file" accept="image/png,image/jpeg,image/webp" @change="uploadSizeGuide($event)"
                            class="text-sm">
                        <button type="button" x-show="sizeGuideUrl" @click="deleteSizeGuide()"
                            class="text-xs text-red-500 hover:underline">إزالة</button>
                    </div>
                    <template x-if="!sizeGuideUrl && Object.keys(defaultSizeGuides).length">
                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-gray-500">أو استخدم دليل مقاسات جاهز:</span>
                            <template x-for="(path, type) in defaultSizeGuides" :key="type">
                                <button type="button" @click="useDefaultSizeGuide(type)"
                                    class="text-xs border border-gray-200 rounded-lg px-2 py-1 hover:bg-gray-50"
                                    x-text="'استخدام دليل ' + type"></button>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Custom fields --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">الحقول المخصصة (مثل: القياسات)</label>
                        <div class="flex items-center gap-2">
                            <select x-model="selectedTemplate" class="text-xs border border-gray-200 rounded-lg px-2 py-1">
                                <template x-for="(tpl, key) in fieldTemplates" :key="key">
                                    <option :value="key" x-text="tpl.label_ar"></option>
                                </template>
                            </select>
                            <button type="button" @click="applyFieldTemplate()" class="text-xs text-blue-600 hover:underline">تطبيق القالب</button>
                            <button type="button" @click="addCustomField()" class="text-xs text-yellow-600 hover:underline">+ إضافة حقل</button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(field, idx) in customFields" :key="field.id || idx">
                            <div class="border border-gray-200 rounded-xl p-3 grid grid-cols-1 sm:grid-cols-5 gap-2 items-center">
                                <input type="text" x-model="field.label_en" placeholder="Label (EN)" class="sm:col-span-2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                                <select x-model="field.field_type" class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="date">Date</option>
                                </select>
                                <input type="text" x-model="field.unit" placeholder="Unit (cm, kg…)" class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-1 text-xs text-gray-600">
                                        <input type="checkbox" x-model="field.is_required"> مطلوب
                                    </label>
                                    <button type="button" @click="saveCustomField(field, idx)" class="text-xs text-green-600 hover:underline">حفظ</button>
                                    <button type="button" @click="removeCustomField(field, idx)" class="text-xs text-red-500 hover:underline">حذف</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Add-on groups --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">مجموعات الإضافات</label>
                        <button type="button" @click="addAddonGroup()" class="text-xs text-yellow-600 hover:underline">+ إضافة مجموعة</button>
                    </div>
                    <div class="space-y-3">
                        <template x-for="(group, gIdx) in addonGroups" :key="group.id || gIdx">
                            <div class="border border-gray-200 rounded-xl p-3 space-y-2">
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
                                    <input type="text" x-model="group.name_en" placeholder="Group name (EN)" class="sm:col-span-2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                                    <select x-model="group.selection_type" class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                                        <option value="single">اختيار واحد</option>
                                        <option value="multiple">اختيار متعدد</option>
                                    </select>
                                    <div class="flex items-center gap-2">
                                        <label class="flex items-center gap-1 text-xs text-gray-600">
                                            <input type="checkbox" x-model="group.is_required"> مطلوب
                                        </label>
                                        <button type="button" @click="saveAddonGroup(group, gIdx)" class="text-xs text-green-600 hover:underline">حفظ</button>
                                        <button type="button" @click="removeAddonGroup(group, gIdx)" class="text-xs text-red-500 hover:underline">حذف</button>
                                    </div>
                                </div>

                                <div class="ps-4 space-y-1.5" x-show="group.id">
                                    <template x-for="(option, oIdx) in (group.options || [])" :key="option.id || oIdx">
                                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
                                            <input type="text" x-model="option.name_en" placeholder="Option (EN)" class="sm:col-span-2 border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                            <input type="number" x-model.number="option.extra_price" min="0" placeholder="Extra price (fils/cents)" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="saveAddonOption(group, option, oIdx)" class="text-xs text-green-600 hover:underline">حفظ</button>
                                                <button type="button" @click="removeAddonOption(group, option, oIdx)" class="text-xs text-red-500 hover:underline">حذف</button>
                                            </div>
                                        </div>
                                    </template>
                                    <button type="button" @click="addAddonOption(group)" class="text-xs text-yellow-600 hover:underline">+ إضافة خيار</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
    <script>
        function campaignSection() {
            return {
                enabled: false,
                commissionType: 'fixed',
                tieredRules: [],
                selectedMarketers: [], // [{id, name, type}]
                feePerInfluencer: 0,
                currency: '',
                get isFbn() {
                    const fmSelect = document.querySelector('select[name="fulfillment_model"]');
                    return fmSelect ? fmSelect.value === 'fbn' : false;
                },
                get totalInfluencerFee() {
                    const count = this.selectedMarketers.filter(m => m.type === 'influencer').length;
                    return count * this.feePerInfluencer;
                },
                updateSelectedMarketers(event) {
                    const select = event.target;
                    this.selectedMarketers = Array.from(select.selectedOptions || []).map(opt => ({
                        id: opt.value,
                        name: opt.dataset.name || opt.text,
                        type: opt.dataset.type || 'affiliate',
                    }));
                },
                async fetchInfluencerFee() {
                    try {
                        const res = await fetch('{{ route("partner.listings.marketer-fee") }}', {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        this.feePerInfluencer = data.fee_per_influencer ?? 0;
                        this.currency = data.currency ?? '';
                    } catch (e) {
                        console.error('Failed to fetch influencer fee', e);
                    }
                },
                init() {
                    this.fetchInfluencerFee();
                },
            };
        }

        function customizationSection(config) {
            return {
                listingId: config.listingId,
                hasOrderNotes: config.hasOrderNotes,
                sizeGuideUrl: config.sizeGuideUrl,
                customFields: config.customFields || [],
                addonGroups: config.addonGroups || [],
                fieldTemplates: config.fieldTemplates || {},
                defaultSizeGuides: config.defaultSizeGuides || {},
                selectedTemplate: Object.keys(config.fieldTemplates || {})[0] || '',
                csrfToken: document.querySelector('meta[name="csrf-token"]').content,

                baseUrl(path) {
                    return '{{ url("listings") }}/' + this.listingId + path;
                },
                async request(method, path, body, isForm = false) {
                    const options = {
                        method,
                        headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    };
                    if (body && !isForm) {
                        options.headers['Content-Type'] = 'application/json';
                        options.body = JSON.stringify(body);
                    } else if (body) {
                        options.body = body;
                    }
                    const res = await fetch(this.baseUrl(path), options);
                    if (!res.ok) {
                        const err = await res.json().catch(() => ({}));
                        alert(err.message || 'حدث خطأ ما.');
                        throw new Error('request_failed');
                    }
                    return res.json();
                },
                async saveSettings() {
                    await this.request('POST', '/settings', { has_order_notes: this.hasOrderNotes });
                },
                async uploadSizeGuide(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    const form = new FormData();
                    form.append('file', file);
                    const data = await this.request('POST', '/size-guide', form, true);
                    this.sizeGuideUrl = data.data.size_guide_image_url;
                },
                async deleteSizeGuide() {
                    await this.request('DELETE', '/size-guide');
                    this.sizeGuideUrl = null;
                },
                async useDefaultSizeGuide(type) {
                    const data = await this.request('POST', '/size-guide/default', { type });
                    this.sizeGuideUrl = data.data.size_guide_image_url;
                },
                async applyFieldTemplate() {
                    if (!this.selectedTemplate) return;
                    const data = await this.request('POST', '/custom-fields/apply-template', { template: this.selectedTemplate });
                    this.customFields.push(...data.data);
                },
                addCustomField() {
                    this.customFields.push({ label_en: '', field_type: 'text', unit: '', is_required: true });
                },
                async saveCustomField(field, idx) {
                    const payload = {
                        label_en: field.label_en, label_ar: field.label_ar, field_type: field.field_type,
                        unit: field.unit, is_required: !!field.is_required,
                    };
                    const data = field.id
                        ? await this.request('PUT', '/custom-fields/' + field.id, payload)
                        : await this.request('POST', '/custom-fields', payload);
                    this.customFields[idx] = data.data;
                },
                async removeCustomField(field, idx) {
                    if (field.id) await this.request('DELETE', '/custom-fields/' + field.id);
                    this.customFields.splice(idx, 1);
                },
                addAddonGroup() {
                    this.addonGroups.push({ name_en: '', selection_type: 'single', is_required: false, options: [] });
                },
                async saveAddonGroup(group, idx) {
                    const payload = {
                        name_en: group.name_en, name_ar: group.name_ar,
                        selection_type: group.selection_type, is_required: !!group.is_required,
                    };
                    const data = group.id
                        ? await this.request('PUT', '/addon-groups/' + group.id, payload)
                        : await this.request('POST', '/addon-groups', payload);
                    this.addonGroups[idx] = { ...data.data, options: group.options || [] };
                },
                async removeAddonGroup(group, idx) {
                    if (group.id) await this.request('DELETE', '/addon-groups/' + group.id);
                    this.addonGroups.splice(idx, 1);
                },
                addAddonOption(group) {
                    group.options = group.options || [];
                    group.options.push({ name_en: '', extra_price: 0 });
                },
                async saveAddonOption(group, option, idx) {
                    const payload = { name_en: option.name_en, name_ar: option.name_ar, extra_price: option.extra_price || 0 };
                    const data = option.id
                        ? await this.request('PUT', '/addon-groups/' + group.id + '/options/' + option.id, payload)
                        : await this.request('POST', '/addon-groups/' + group.id + '/options', payload);
                    group.options[idx] = data.data;
                },
                async removeAddonOption(group, option, idx) {
                    if (option.id) await this.request('DELETE', '/addon-groups/' + group.id + '/options/' + option.id);
                    group.options.splice(idx, 1);
                },
            };
        }
    </script>
@endpush

@endsection
