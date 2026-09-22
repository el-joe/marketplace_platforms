@extends('layouts.admin')
@section('title', 'تعديل وظيفة: '.$marketerJob->name_ar)
@section('page-title', 'تعديل وظيفة')

@section('content')
@vite(['resources/js/components/select2.js'])
<div class="max-w-2xl space-y-6">

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
            <ul class="list-disc ps-4">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border p-6 space-y-4">
        <h3 class="font-bold text-gray-800">بيانات الوظيفة</h3>
        <form method="POST" action="{{ route('admin.marketer-jobs.update', $marketerJob) }}" class="space-y-3">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs text-gray-500 mb-1">المفتاح (key)</label>
                <input type="text" name="key" value="{{ old('key', $marketerJob->key) }}" required dir="ltr"
                       class="border rounded-lg px-3 py-2 text-sm w-full">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">الاسم بالعربية</label>
                <input type="text" name="name_ar" value="{{ old('name_ar', $marketerJob->name_ar) }}" required
                       class="border rounded-lg px-3 py-2 text-sm w-full">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">الاسم بالإنجليزية</label>
                <input type="text" name="name_en" value="{{ old('name_en', $marketerJob->name_en) }}" required dir="ltr"
                       class="border rounded-lg px-3 py-2 text-sm w-full">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">الترتيب</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $marketerJob->sort_order) }}" min="0"
                       class="border rounded-lg px-3 py-2 text-sm w-full">
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $marketerJob->is_active) ? 'checked' : '' }}>
                    نشطة
                </label>
            </div>
            <button class="px-5 py-2 bg-gray-900 text-white font-semibold rounded-lg text-sm hover:bg-gray-800">حفظ</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border p-6 space-y-4">
        <h3 class="font-bold text-gray-800">الأقسام المتاحة لهذه الوظيفة</h3>
        <p class="text-xs text-gray-400">
            حدد مصادر الأقسام التي تعمل بها هذه الوظيفة، ويمكنك اختياريًا تحديد أقسام معينة من كل مصدر.
            اترك التحديد فارغًا ليشمل كل الأقسام. الوظائف المرتبطة بـ "الرحلات" فقط لا تحتاج لتحديد أقسام.
        </p>
        <form method="POST" action="{{ route('admin.marketer-jobs.category-types.sync', $marketerJob) }}" class="space-y-4">
            @csrf
            @php
                $activeTypes = $marketerJob->categories->pluck('category_type')->unique();
                $selectedProductIds = $marketerJob->categories->where('category_type', 'product')->pluck('category_id')->filter();
                $selectedClassifiedIds = $marketerJob->categories->where('category_type', 'classified')->pluck('category_id')->filter();
            @endphp

            <div class="space-y-2" x-data="{ checked: {{ $activeTypes->contains('product') ? 'true' : 'false' }} }">
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="category_types[]" value="product" x-model="checked">
                    منتجات (Marketplace Products)
                </label>
                <div x-show="checked" class="ps-6">
                    <select name="categories[product][]" multiple data-select2-init class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($productCategories as $category)
                            <option value="{{ $category->id }}" {{ $selectedProductIds->contains($category->id) ? 'selected' : '' }}>
                                {{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">اترك التحديد فارغًا للسماح بكل أقسام المنتجات.</p>
                </div>
            </div>

            <div class="space-y-2" x-data="{ checked: {{ $activeTypes->contains('classified') ? 'true' : 'false' }} }">
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="category_types[]" value="classified" x-model="checked">
                    مصنفات (opensouq)
                </label>
                <div x-show="checked" class="ps-6">
                    <select name="categories[classified][]" multiple data-select2-init class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($classifiedCategories as $category)
                            <option value="{{ $category->id }}" {{ $selectedClassifiedIds->contains($category->id) ? 'selected' : '' }}>
                                {{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">اترك التحديد فارغًا للسماح بكل أقسام المصنفات.</p>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="category_types[]" value="travel" {{ $activeTypes->contains('travel') ? 'checked' : '' }}>
                رحلات (Travel) — بدون تحديد أقسام
            </label>

            <button class="px-5 py-2 bg-gray-900 text-white font-semibold rounded-lg text-sm hover:bg-gray-800">حفظ</button>
        </form>
    </div>

    <a href="{{ route('admin.marketer-jobs.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; رجوع للقائمة</a>
</div>
@endsection
