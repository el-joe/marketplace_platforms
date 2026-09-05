@extends('layouts.marketer')
@section('title', 'تفاصيل الطلب')
@section('page-title', 'تفاصيل الطلب')

@section('content')
<div class="max-w-2xl space-y-5">

    @php
        $order = $conversion->order;
        $campaign = $conversion->invitation->campaign;
    @endphp

    {{-- Order summary card --}}
    <div class="bg-white rounded-xl border p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <div class="font-black text-gray-900 text-lg">{{ $order->order_number }}</div>
                <div class="text-xs text-gray-400 mt-0.5">{{ $order->placed_at?->format('Y-m-d H:i') }}</div>
            </div>
            <div class="flex flex-col items-end gap-1">
                <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded font-semibold">{{ $order->status }}</span>
                @if($conversion->commissioned)
                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded">عمولة محصّلة</span>
                @else
                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded">عمولة معلّقة</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm border-t border-gray-100 pt-4">
            <div>
                <span class="text-gray-400">العمولة المكتسبة: </span>
                <strong class="text-green-600">{{ number_format($conversion->commission_amount) }} {{ $conversion->currency }}</strong>
            </div>
            <div>
                <span class="text-gray-400">الحملة: </span>
                <strong>{{ $campaign->getPromotedTitle() }}</strong>
            </div>
            <div>
                <span class="text-gray-400">نوع الحملة: </span>
                <strong>{{ ['product' => 'منتج', 'travel' => 'سياحة', 'classified' => 'إعلان'][$campaign->campaign_category ?? 'product'] }}</strong>
            </div>
        </div>
    </div>

    {{-- Order items --}}
    @if($order->items->isNotEmpty())
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b font-bold text-gray-800">المنتجات في الطلب</div>
        <div class="divide-y divide-gray-100">
            @foreach($order->items as $item)
            @php $snap = $item->product_snapshot ?? []; @endphp
            <div class="px-5 py-4 flex items-center gap-4">
                <div class="flex-1">
                    <div class="font-semibold text-sm text-gray-900">{{ $snap['name_ar'] ?? $snap['name_en'] ?? '—' }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">الكمية: {{ $item->quantity }} × {{ number_format($item->unit_price) }}</div>
                </div>
                <div class="text-sm font-bold text-gray-900">{{ number_format($item->line_total) }}</div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 border-t bg-gray-50 flex justify-between text-sm font-bold">
            <span>الإجمالي</span>
            <span>{{ number_format($order->total) }} {{ $order->currency }}</span>
        </div>
    </div>
    @endif

    {{-- Read-only notice --}}
    <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl text-sm text-blue-700">
        هذه صفحة عرض فقط. إدارة الطلب تتم من قِبَل البائع أو المنصة.
    </div>

    <a href="{{ route('marketer.orders.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← العودة لقائمة الطلبات
    </a>
</div>
@endsection
