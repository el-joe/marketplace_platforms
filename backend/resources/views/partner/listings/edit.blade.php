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
                <div class="bg-white rounded-2xl border border-purple-200 p-6 space-y-4"
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
    </script>
@endpush

@endsection
