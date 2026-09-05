@extends('layouts.marketer')
@section('title', 'قوائم المنتجات')
@section('page-title', 'قوائم المنتجات')

@section('content')
<div class="space-y-4">

    {{-- Header + Add button --}}
    <div class="flex items-center justify-between">
        <div class="flex gap-2">
            @foreach(['', 'active', 'paused'] as $s)
                <a href="{{ route('marketer.listings.index', $s ? ['status' => $s] : []) }}"
                   class="px-3 py-1 rounded-full text-xs font-semibold border {{ request('status') === $s || (!request('status') && !$s) ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 text-gray-600' }}">
                    {{ $s ?: 'الكل' }}
                </a>
            @endforeach
        </div>
        <a href="{{ route('marketer.listings.create') }}"
           class="px-4 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500">
            + إضافة قائمة
        </a>
    </div>

    @if($listings->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">📦</div>
            <h3 class="font-bold text-gray-700">لا توجد قوائم بعد</h3>
            <p class="text-gray-400 text-sm mt-1">أضف منتجات لتروّجها وتكسب عمولات من مبيعاتها</p>
            <a href="{{ route('marketer.listings.create') }}"
               class="inline-block mt-4 px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm">
                إضافة منتج
            </a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="px-4 py-3 text-start">المنتج</th>
                        <th class="px-4 py-3 text-center">الدولة</th>
                        <th class="px-4 py-3 text-center">السعر</th>
                        <th class="px-4 py-3 text-center">المبيعات</th>
                        <th class="px-4 py-3 text-center">المخزون</th>
                        <th class="px-4 py-3 text-center">الحالة</th>
                        <th class="px-4 py-3 text-center">المصدر</th>
                        <th class="px-4 py-3 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($listings as $listing)
                    @php
                        $product = $listing->productVariant->product;
                        $img = $listing->productVariant->images->first()?->url
                            ?? $product->images->first()?->url;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($img)
                                    <img src="{{ $img }}" alt="" class="w-10 h-10 object-cover rounded-lg border">
                                @endif
                                <div>
                                    <div class="font-semibold text-gray-900 text-xs">{{ Str::limit($product->name_ar, 40) }}</div>
                                    <div class="text-gray-400 text-xs">{{ $listing->productVariant->sku }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $listing->country->name_ar }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="font-bold text-gray-900">{{ number_format($listing->price) }}</div>
                            <div class="text-xs text-gray-400">{{ $listing->currency }}</div>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ number_format($listing->total_sold) }}</td>
                        <td class="px-4 py-3 text-center text-xs text-gray-400">
                            @if($listing->invitation_id)
                                <span class="text-blue-500">من الحملة</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $listing->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $listing->status === 'active' ? 'نشط' : 'موقوف' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($listing->invitation_id)
                                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded">حملة</span>
                            @else
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded">مستقل</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                {{-- Toggle status --}}
                                <form method="POST" action="{{ route('marketer.listings.toggle-status', $listing) }}">
                                    @csrf
                                    <button class="text-xs px-2 py-1 rounded {{ $listing->status === 'active' ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700' }} hover:opacity-80">
                                        {{ $listing->status === 'active' ? 'إيقاف' : 'تفعيل' }}
                                    </button>
                                </form>

                                {{-- Update price inline --}}
                                <form method="POST" action="{{ route('marketer.listings.update-price', $listing) }}"
                                      x-data="{ open: false }" class="relative">
                                    @csrf @method('PATCH')
                                    <button type="button" @click="open = !open"
                                            class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 hover:opacity-80">
                                        سعر
                                    </button>
                                    <div x-show="open" x-cloak
                                         class="absolute left-0 top-8 z-20 bg-white border rounded-lg shadow p-3 w-44 space-y-2">
                                        <input type="number" name="price" value="{{ $listing->price }}" min="1"
                                               placeholder="السعر" class="w-full border rounded px-2 py-1 text-xs">
                                        <button type="submit" class="w-full bg-yellow-400 text-gray-900 text-xs font-bold py-1 rounded">حفظ</button>
                                    </div>
                                </form>

                                {{-- Delete --}}
                                @if(!$listing->invitation_id)
                                <form method="POST" action="{{ route('marketer.listings.destroy', $listing) }}">
                                    @csrf @method('DELETE')
                                    <button onclick="return confirm('حذف هذه القائمة؟')"
                                            class="text-xs px-2 py-1 rounded bg-red-100 text-red-600 hover:opacity-80">
                                        حذف
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="px-4 py-3 border-t">{{ $listings->withQueryString()->links() }}</div>
        </div>
    @endif
</div>
@endsection
