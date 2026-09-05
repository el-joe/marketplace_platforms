@extends('layouts.marketer')
@section('title', 'الطلبات')
@section('page-title', 'طلباتي عبر الإحالات')

@section('content')
<div class="space-y-5">

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">إجمالي الطلبات</div>
            <div class="text-3xl font-black text-gray-900">{{ number_format($summary['total_orders']) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">إجمالي العمولة</div>
            <div class="text-2xl font-black text-gray-900">{{ number_format($summary['total_commission']) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">عمولة محصّلة</div>
            <div class="text-2xl font-black text-green-600">{{ number_format($summary['paid_commission']) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">عمولة معلّقة</div>
            <div class="text-2xl font-black text-yellow-500">{{ number_format($summary['pending_commission']) }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex gap-2 flex-wrap">
        @foreach(['', 'placed', 'confirmed', 'delivered', 'completed', 'cancelled'] as $s)
            <a href="{{ route('marketer.orders.index', $s ? ['status' => $s] : []) }}"
               class="px-3 py-1 rounded-full text-xs font-semibold border
                      {{ request('status', '') === $s ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 text-gray-600' }}">
                {{ $s ?: 'الكل' }}
            </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        @if($conversions->isEmpty())
            <div class="p-12 text-center text-gray-400">لا توجد طلبات بعد</div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">رقم الطلب</th>
                        <th class="px-4 py-3 text-center">الحالة</th>
                        <th class="px-4 py-3 text-center">العمولة</th>
                        <th class="px-4 py-3 text-center">حالة العمولة</th>
                        <th class="px-4 py-3 text-center">الحملة</th>
                        <th class="px-4 py-3 text-center">التاريخ</th>
                        <th class="px-4 py-3 text-center"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($conversions as $conv)
                    @php
                        $order = $conv->order;
                        $statusMap = [
                            'placed' => ['label' => 'مُقدَّم', 'cls' => 'bg-blue-100 text-blue-700'],
                            'confirmed' => ['label' => 'مؤكد', 'cls' => 'bg-indigo-100 text-indigo-700'],
                            'delivered' => ['label' => 'تم التوصيل', 'cls' => 'bg-green-100 text-green-700'],
                            'completed' => ['label' => 'مكتمل', 'cls' => 'bg-green-200 text-green-800'],
                            'cancelled' => ['label' => 'ملغى', 'cls' => 'bg-red-100 text-red-700'],
                        ];
                        $st = $statusMap[$order->status ?? ''] ?? ['label' => $order->status, 'cls' => 'bg-gray-100 text-gray-500'];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $order->order_number }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs {{ $st['cls'] }}">{{ $st['label'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-center font-bold">
                            {{ number_format($conv->commission_amount) }}
                            <span class="text-xs text-gray-400">{{ $conv->currency }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($conv->commissioned)
                                <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded">محصّلة</span>
                            @else
                                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded">معلّقة</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500">
                            {{ Str::limit($conv->invitation->campaign->getPromotedTitle(), 25) }}
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-400">
                            {{ $order->placed_at?->format('Y-m-d') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('marketer.orders.show', $order->id) }}"
                               class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">
                                عرض
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="px-4 py-3 border-t">{{ $conversions->links() }}</div>
        @endif
    </div>
</div>
@endsection
