@extends('layouts.marketer')
@section('title', 'التخفيضات السريعة')
@section('page-title', 'التخفيضات السريعة')

@section('content')
<div class="space-y-8">

    {{-- Pending invitations --}}
    <div>
        <h2 class="font-bold text-gray-800 mb-3">دعوات معلّقة</h2>
        @if($pending->isEmpty())
            <div class="bg-white rounded-xl border p-8 text-center text-gray-400 text-sm">لا توجد دعوات جديدة</div>
        @else
            <div class="grid gap-3">
                @foreach($pending as $invitation)
                <div class="bg-white rounded-xl border p-5 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $invitation->flashSale->name_ar ?? $invitation->flashSale->name_en }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $invitation->flashSale->sale_starts_at?->format('Y-m-d H:i') }} → {{ $invitation->flashSale->sale_ends_at?->format('Y-m-d H:i') }}
                        </p>
                        @if($invitation->extra_commission_rate)
                            <p class="text-xs text-purple-600 font-semibold mt-1">عمولة إضافية: {{ $invitation->extra_commission_rate }}%</p>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('marketer.flash-sales.accept', $invitation) }}">
                            @csrf
                            <button class="px-4 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-lg">قبول</button>
                        </form>
                        <form method="POST" action="{{ route('marketer.flash-sales.decline', $invitation) }}">
                            @csrf
                            <button class="px-4 py-1.5 bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg">رفض</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Active flash sales --}}
    <div>
        <h2 class="font-bold text-gray-800 mb-3">تخفيضات سريعة نشطة</h2>
        @if($active->isEmpty())
            <div class="bg-white rounded-xl border p-8 text-center text-gray-400 text-sm">لا توجد تخفيضات نشطة حاليًا</div>
        @else
            <div class="grid gap-3">
                @foreach($active as $invitation)
                <div class="bg-white rounded-xl border p-5">
                    <p class="font-semibold text-gray-900">{{ $invitation->flashSale->name_ar ?? $invitation->flashSale->name_en }}</p>
                    <p class="text-xs text-gray-500 mt-1">ينتهي في {{ $invitation->flashSale->sale_ends_at?->format('Y-m-d H:i') }}</p>
                    @if($invitation->extra_commission_rate)
                        <p class="text-xs text-purple-600 font-semibold mt-1">عمولة إضافية أثناء الحملة: {{ $invitation->extra_commission_rate }}%</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-2">استخدم روابط الإحالة الخاصة بحملاتك النشطة للمنتجات المشاركة في هذا العرض لتحصيل المكافأة.</p>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Past flash sales --}}
    <div>
        <h2 class="font-bold text-gray-800 mb-3">سجل التخفيضات السريعة</h2>
        @if($past->isEmpty())
            <div class="bg-white rounded-xl border p-8 text-center text-gray-400 text-sm">لا يوجد سجل بعد</div>
        @else
            <div class="bg-white rounded-xl border overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start">الحملة</th>
                            <th class="px-4 py-3 text-center">التحويلات</th>
                            <th class="px-4 py-3 text-center">المكافأة المحصّلة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($past as $invitation)
                        <tr>
                            <td class="px-4 py-3">{{ $invitation->flashSale->name_ar ?? $invitation->flashSale->name_en }}</td>
                            <td class="px-4 py-3 text-center">{{ $invitation->conversions_earned }}</td>
                            <td class="px-4 py-3 text-center font-bold text-purple-600">{{ number_format($invitation->bonus_earned) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
