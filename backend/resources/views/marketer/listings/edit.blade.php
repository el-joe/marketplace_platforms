@extends('layouts.marketer')

@php
    $product = $listing->productVariant->product;
    $variant = $listing->productVariant;
    $primaryImg = $product->images->where('is_primary', true)->first() ?? $product->images->first();
    $statusVal = $listing->status instanceof \App\Enums\MarketerListingStatus ? $listing->status->value : $listing->status;
    $isLocked = in_array($statusVal, [
        \App\Enums\MarketerListingStatus::PendingReview->value,
        \App\Enums\MarketerListingStatus::Active->value,
    ]);
@endphp

@section('title', 'تعديل القائمة')
@section('page-title', 'تعديل القائمة')

@section('content')

    <div class="mb-4">
        <a href="{{ route('marketer.listings.show', $listing) }}"
            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            العودة إلى التفاصيل
        </a>
    </div>

    @if($listing->rejection_reason)
        <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded mb-4 text-sm">
            <strong>سبب الرفض:</strong> {{ $listing->rejection_reason }}
            <p class="mt-1">عدّل القائمة وأعد تقديمها للمراجعة.</p>
        </div>
    @endif

    @if($isLocked)
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3 rounded mb-4 text-sm">
            @if($statusVal === \App\Enums\MarketerListingStatus::Active->value)
                القائمة نشطة حالياً. يرجى إيقافها مؤقتاً قبل التعديل.
            @else
                القائمة قيد المراجعة حالياً ولا يمكن تعديلها.
            @endif
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

                <div class="mt-4 pt-4 border-t border-gray-100 space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between">
                        <span class="text-gray-400">الدولة</span>
                        <span>{{ $listing->country?->name_ar ?: $listing->country?->name_en }} ({{ $listing->currency }})</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">المستودع</span>
                        <span class="font-medium">{{ $listing->warehouse?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">نموذج التنفيذ</span>
                        <span class="font-medium">FBN</span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400">المستودع ونموذج التنفيذ لا يمكن تغييرهما بعد الإنشاء.</p>
                </div>
            </div>
        </div>

        {{-- RIGHT: Editable form --}}
        <div class="lg:col-span-7">
            <form method="POST" action="{{ route('marketer.listings.update', $listing) }}" class="space-y-5">
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

                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-800">تفاصيل قابلة للتعديل</h3>

                    {{-- Price --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">السعر <span class="text-red-500">*</span></label>
                            <input type="number" name="price" value="{{ old('price', $listing->price) }}" min="1" required
                                   @disabled($isLocked)
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 disabled:bg-gray-50 disabled:text-gray-400 @error('price') border-red-500 @enderror">
                            @error('price') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">سعر المقارنة</label>
                            <input type="number" name="compare_at_price" value="{{ old('compare_at_price', $listing->compare_at_price) }}" min="1"
                                   @disabled($isLocked)
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                    </div>

                    {{-- Condition --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">حالة المنتج <span class="text-red-500">*</span></label>
                        <select name="condition" required @disabled($isLocked)
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 disabled:bg-gray-50 disabled:text-gray-400">
                            @foreach($conditions as $value => $label)
                                <option value="{{ $value }}" {{ old('condition', $listing->condition) === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Condition Notes --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">ملاحظات الحالة</label>
                        <textarea name="condition_notes" rows="3" @disabled($isLocked)
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 disabled:bg-gray-50 disabled:text-gray-400">{{ old('condition_notes', $listing->condition_notes) }}</textarea>
                        @error('condition_notes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Vendor SKU --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">SKU الخاص بك</label>
                        <input type="text" name="vendor_sku" value="{{ old('vendor_sku', $listing->vendor_sku) }}" maxlength="100"
                               @disabled($isLocked)
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 disabled:bg-gray-50 disabled:text-gray-400">
                    </div>

                    {{-- Low Stock Threshold --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">حد التنبيه للمخزون</label>
                        <input type="number" name="low_stock_threshold" value="{{ old('low_stock_threshold', $listing->low_stock_threshold ?? 5) }}"
                               min="0" max="9999" @disabled($isLocked)
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 disabled:bg-gray-50 disabled:text-gray-400">
                        <p class="text-xs text-gray-400 mt-1">ستصلك تنبيهات عندما ينخفض المخزون عن هذا الرقم.</p>
                    </div>
                </div>

                @unless($isLocked)
                    <button type="submit"
                            class="w-full bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold py-3 rounded-xl text-sm transition-colors">
                        حفظ التغييرات
                    </button>
                @endunless
            </form>
        </div>

    </div>

@endsection
