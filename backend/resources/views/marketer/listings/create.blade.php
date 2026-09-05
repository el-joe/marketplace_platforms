@extends('layouts.marketer')
@section('title', 'إضافة قائمة منتج')
@section('page-title', 'إضافة قائمة منتج')

@section('content')
<div class="max-w-xl" x-data="{
    query: '',
    results: [],
    selected: null,
    loading: false,
    async search() {
        if (this.query.length < 2) { this.results = []; return; }
        this.loading = true;
        const res = await fetch(`{{ route('marketer.listings.search-products') }}?q=${encodeURIComponent(this.query)}`);
        this.results = await res.json();
        this.loading = false;
    },
    selectVariant(v) {
        this.selected = v;
        this.query = v.name_ar + ' — ' + v.sku;
        this.results = [];
        document.getElementById('product_variant_id').value = v.id;
    }
}">

    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        <h2 class="text-lg font-bold text-gray-900">إضافة منتج للترويج</h2>
        <p class="text-sm text-gray-500">ابحث عن منتج وحدد سعرك وستظهر قائمتك في صفحة بروفايلك العام.</p>

        <form method="POST" action="{{ route('marketer.listings.store') }}" class="space-y-4">
            @csrf

            {{-- Product search --}}
            <div class="relative">
                <label class="block text-sm font-semibold text-gray-700 mb-1">المنتج <span class="text-red-500">*</span></label>
                <input type="text" x-model="query" @input.debounce.300ms="search()"
                       placeholder="اكتب اسم المنتج أو SKU..."
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                <input type="hidden" id="product_variant_id" name="product_variant_id" value="{{ old('product_variant_id') }}">

                {{-- Dropdown --}}
                <div x-show="results.length > 0" x-cloak
                     class="absolute z-20 top-full left-0 right-0 bg-white border rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto">
                    <template x-for="v in results" :key="v.id">
                        <div @click="selectVariant(v)"
                             class="px-4 py-2 hover:bg-yellow-50 cursor-pointer border-b last:border-0 text-sm">
                            <div class="font-semibold text-gray-900" x-text="v.name_ar"></div>
                            <div class="text-xs text-gray-400" x-text="'SKU: ' + v.sku + (v.variant_name ? ' | ' + v.variant_name : '')"></div>
                        </div>
                    </template>
                </div>
                @error('product_variant_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Country --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">الدولة <span class="text-red-500">*</span></label>
                <select name="country_id" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                    <option value="">اختر الدولة</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}" {{ old('country_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->name_ar }} ({{ $c->currency_code }})
                        </option>
                    @endforeach
                </select>
                @error('country_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Price --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">سعر الترويج <span class="text-red-500">*</span></label>
                    <input type="number" name="price" value="{{ old('price') }}" min="1" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 @error('price') border-red-500 @enderror">
                    @error('price') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">سعر المقارنة (اختياري)</label>
                    <input type="number" name="compare_at_price" value="{{ old('compare_at_price') }}" min="1"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                </div>
            </div>

            {{-- Condition --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">الحالة</label>
                <select name="condition" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm">
                    <option value="new">جديد</option>
                    <option value="like_new">مثل الجديد</option>
                    <option value="good">جيد</option>
                    <option value="acceptable">مقبول</option>
                    <option value="refurbished">مُجدَّد</option>
                </select>
            </div>

            <button type="submit"
                    class="w-full bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold py-3 rounded-lg text-sm">
                إضافة القائمة
            </button>
        </form>
    </div>
</div>
@endsection
