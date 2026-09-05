@extends('layouts.marketer')
@section('title', 'العينات')
@section('page-title', 'العينات المخصصة لك')

@section('content')
<div class="space-y-4">

    {{-- Status summary --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-yellow-700">{{ $statusCounts['pending'] }}</div>
            <div class="text-xs text-yellow-600 mt-0.5">معلّقة (تحتاج عنوان)</div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-blue-700">{{ $statusCounts['dispatched'] }}</div>
            <div class="text-xs text-blue-600 mt-0.5">جاري الشحن</div>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-green-700">{{ $statusCounts['delivered'] }}</div>
            <div class="text-xs text-green-600 mt-0.5">تم الاستلام</div>
        </div>
    </div>

    @if($samples->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">📦</div>
            <h3 class="font-bold text-gray-700">لا توجد عينات بعد</h3>
            <p class="text-gray-400 text-sm mt-1">ستظهر العينات المخصصة لك من الحملات التي تقبلها</p>
        </div>
    @else
        @foreach($samples as $sample)
        @php
            $product = $sample->campaign->vendorListing?->productVariant?->product
                     ?? $sample->campaign->adminListing?->productVariant?->product;
            $statusMap = [
                'pending'    => ['label' => 'معلّقة', 'cls' => 'bg-yellow-100 text-yellow-700'],
                'dispatched' => ['label' => 'جاري الشحن', 'cls' => 'bg-blue-100 text-blue-700'],
                'delivered'  => ['label' => 'تم الاستلام', 'cls' => 'bg-green-100 text-green-700'],
                'returned'   => ['label' => 'مرتجعة', 'cls' => 'bg-red-100 text-red-700'],
            ];
            $st = $statusMap[$sample->status] ?? ['label' => $sample->status, 'cls' => 'bg-gray-100 text-gray-500'];
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h4 class="font-bold text-gray-900">{{ $product?->name_ar ?? 'منتج' }}</h4>
                    <div class="text-sm text-gray-500 mt-0.5">الكمية: {{ $sample->quantity }} قطعة</div>
                </div>
                <span class="px-2 py-0.5 text-xs font-semibold rounded {{ $st['cls'] }}">{{ $st['label'] }}</span>
            </div>

            @if($sample->status === 'pending')
                @if($sample->delivery_address_snapshot)
                <div class="p-3 bg-green-50 rounded-lg text-xs text-green-700 mb-3">
                    ✓ تم تسجيل عنوانك. سيتم الشحن قريباً.
                </div>
                @else
                <form method="POST" action="{{ route('marketer.samples.address', $sample) }}" class="space-y-3">
                    @csrf
                    <p class="text-sm text-amber-700 font-semibold">⚠️ يُرجى تسجيل عنوان التوصيل لتتمكن من استلام العينة:</p>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="address_line_1" placeholder="العنوان (سطر 1)" required class="border rounded-lg px-3 py-2 text-sm col-span-2">
                        <input type="text" name="city" placeholder="المدينة" required class="border rounded-lg px-3 py-2 text-sm">
                        <input type="text" name="country" placeholder="الدولة" required class="border rounded-lg px-3 py-2 text-sm">
                        <input type="text" name="phone" placeholder="رقم الهاتف" required class="border rounded-lg px-3 py-2 text-sm col-span-2">
                        <textarea name="notes" placeholder="ملاحظات إضافية (اختياري)" rows="2" class="border rounded-lg px-3 py-2 text-sm col-span-2"></textarea>
                    </div>
                    <button type="submit" class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500">
                        حفظ العنوان
                    </button>
                </form>
                @endif
            @elseif($sample->delivery_address_snapshot)
            <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-600">
                <strong>عنوان التوصيل:</strong>
                {{ $sample->delivery_address_snapshot['address_line_1'] }},
                {{ $sample->delivery_address_snapshot['city'] }},
                {{ $sample->delivery_address_snapshot['country'] }}
            </div>
            @endif

            @if($sample->dispatched_at)
            <div class="text-xs text-gray-400 mt-2">تاريخ الشحن: {{ $sample->dispatched_at->format('Y-m-d') }}</div>
            @endif
        </div>
        @endforeach

        <div>{{ $samples->links() }}</div>
    @endif
</div>
@endsection
