@extends('layouts.marketer')

@section('title', 'إدارة المخزون')
@section('page-title', 'إدارة المخزون')

@section('content')

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">إجمالي المنتجات</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats->total_skus) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">إجمالي الوحدات</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats->total_on_hand) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">متاح للبيع</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($stats->total_available) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">مخزون منخفض</p>
            <a href="{{ route('marketer.inventory.low-stock') }}"
               class="text-2xl font-bold {{ $lowStockCount > 0 ? 'text-orange-500' : 'text-gray-400' }} hover:underline">
                {{ number_format($lowStockCount) }}
            </a>
        </div>
    </div>

    {{-- Filter tabs --}}
    <div class="bg-white rounded-2xl border border-gray-200 mb-4">
        <div class="flex items-center overflow-x-auto">
            <a href="{{ route('marketer.inventory.index') }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                'border-yellow-400 text-yellow-600' => !request('filter'),
                'border-transparent text-gray-500 hover:text-gray-700' => request('filter'),
            ])>الكل</a>

            <a href="{{ route('marketer.inventory.index', ['filter' => 'low_stock']) }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-1',
                'border-orange-400 text-orange-600' => request('filter') === 'low_stock',
                'border-transparent text-gray-500 hover:text-gray-700' => request('filter') !== 'low_stock',
            ])>
                ⚠ مخزون منخفض
                @if($lowStockCount > 0)
                    <span class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full leading-none">{{ $lowStockCount }}</span>
                @endif
            </a>

            <a href="{{ route('marketer.inventory.index', ['filter' => 'out_of_stock']) }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-1',
                'border-red-400 text-red-600' => request('filter') === 'out_of_stock',
                'border-transparent text-gray-500 hover:text-gray-700' => request('filter') !== 'out_of_stock',
            ])>
                🚫 نفد المخزون
                @if($outOfStockCount > 0)
                    <span class="bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full leading-none">{{ $outOfStockCount }}</span>
                @endif
            </a>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

        @if($rows->isEmpty())
            <div class="py-16 text-center">
                <div class="text-4xl mb-3">📦</div>
                <h3 class="font-semibold text-gray-800 mb-1">لا يوجد مخزون بعد</h3>
                <p class="text-sm text-gray-400">أضف قوائم منتجات مع نموذج FBN لإدارة المخزون هنا.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr class="text-xs text-gray-500">
                            <th class="text-right py-3 px-5 font-semibold">المنتج</th>
                            <th class="text-right py-3 px-4 font-semibold">النسخة / SKU</th>
                            <th class="text-right py-3 px-4 font-semibold">المستودع</th>
                            <th class="py-3 px-4 text-center font-semibold">في المستودع</th>
                            <th class="py-3 px-4 text-center font-semibold">محجوز</th>
                            <th class="py-3 px-4 text-center font-semibold">متاح</th>
                            <th class="py-3 px-4 text-center font-semibold">في الطريق</th>
                            <th class="py-3 px-4 text-center font-semibold">تالف</th>
                            <th class="py-3 px-4 text-center font-semibold">حد التنبيه</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($rows as $row)
                            @php
                                $available = $row->quantity_on_hand - $row->quantity_reserved;
                                $threshold = (int) ($row->low_stock_threshold ?? 5);
                                $stockCls = $available <= 0
                                    ? 'text-red-600 font-bold'
                                    : ($available <= $threshold ? 'text-orange-500 font-semibold' : 'text-gray-800');
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-5">
                                    <p class="font-medium text-gray-800 leading-tight">{{ $row->name_ar ?: $row->name_en }}</p>
                                    @if($row->name_ar && $row->name_en)
                                        <p class="text-xs text-gray-400">{{ $row->name_en }}</p>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <p class="text-gray-700 text-xs">{{ $row->variant_name ?: 'النسخة الافتراضية' }}</p>
                                    <p class="font-mono text-gray-400 text-xs">{{ $row->sku }}</p>
                                </td>
                                <td class="py-3 px-4 text-gray-600 text-xs">{{ $row->warehouse_name }}</td>
                                <td class="py-3 px-4 text-center font-medium text-gray-800">{{ $row->quantity_on_hand }}</td>
                                <td class="py-3 px-4 text-center text-gray-500">{{ $row->quantity_reserved }}</td>
                                <td class="py-3 px-4 text-center">
                                    <span class="{{ $stockCls }} text-xs">{{ $available }}</span>
                                </td>
                                <td class="py-3 px-4 text-center text-blue-500 text-xs">{{ $row->quantity_inbound ?? 0 }}</td>
                                <td class="py-3 px-4 text-center text-red-400 text-xs">{{ $row->quantity_damaged ?? 0 }}</td>
                                <td class="py-3 px-4 text-center text-gray-400 text-xs">{{ $threshold }}</td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('marketer.listings.show', $row->listing_id) }}"
                                       class="text-xs text-blue-600 hover:underline whitespace-nowrap">عرض القائمة</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($rows->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $rows->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection
