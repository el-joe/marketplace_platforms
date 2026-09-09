@extends('layouts.marketer')
@section('title', 'العمولات')
@section('page-title', 'العمولات')

@section('content')
<div class="space-y-5">

    <div class="grid grid-cols-2 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">إجمالي العمولات المحصّلة</div>
            <div class="text-3xl font-black text-green-600">{{ number_format($totalEarned) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">عمولات معلّقة</div>
            <div class="text-3xl font-black text-yellow-500">{{ number_format($pendingEarnings) }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        @if($conversions->isEmpty())
            <div class="p-12 text-center text-gray-400">لا توجد عمولات بعد</div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">التاريخ</th>
                        <th class="px-4 py-3 text-start">الحملة</th>
                        <th class="px-4 py-3 text-start">المنتج</th>
                        <th class="px-4 py-3 text-center">رقم الطلب</th>
                        <th class="px-4 py-3 text-center">العمولة</th>
                        <th class="px-4 py-3 text-center">مكافأة التخفيضات السريعة</th>
                        <th class="px-4 py-3 text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($conversions as $conv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-600">{{ $conv->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $conv->campaign?->title ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $conv->campaign?->vendorListing?->productVariant?->product?->title_en ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-mono text-xs">{{ $conv->order?->order_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-bold text-green-600">{{ number_format($conv->commission_amount) }} {{ $conv->currency }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($conv->flash_sale_bonus_amount)
                                <span class="text-purple-600 font-bold">+{{ number_format($conv->flash_sale_bonus_amount) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs {{ $conv->commissioned ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ $conv->commissioned ? 'مدفوعة' : 'معلّقة' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="p-4">{{ $conversions->links() }}</div>
        @endif
    </div>
</div>
@endsection
