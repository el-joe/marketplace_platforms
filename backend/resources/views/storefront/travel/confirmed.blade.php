@extends('layouts.storefront')

@section('title', 'تم الحجز بنجاح')

@section('content')
<div class="max-w-lg mx-auto px-4 py-16 text-center space-y-6">

    <div class="inline-flex items-center justify-center w-20 h-20 bg-emerald-100 rounded-full text-5xl">
        ✅
    </div>

    <div>
        <h1 class="text-3xl font-black text-gray-900">تم الحجز بنجاح!</h1>
        <p class="text-gray-500 mt-2">سيتواصل معك فريق الوكالة لتأكيد التفاصيل</p>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 text-start space-y-3">
        <div class="flex justify-between text-sm">
            <span class="text-gray-500">رقم الحجز</span>
            <span class="font-mono font-bold text-gray-900">{{ $booking->booking_number }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-500">الباقة</span>
            <span class="font-medium text-gray-900">{{ $booking->package->title_ar ?: $booking->package->title_en }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-500">الوكالة</span>
            <span class="font-medium text-gray-900">{{ $booking->package->agency->name }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-500">تاريخ السفر</span>
            <span class="font-medium text-gray-900">{{ $booking->package->departure_date->format('d M Y') }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-500">عدد المسافرين</span>
            <span class="font-medium text-gray-900">{{ $booking->travelers_count }}</span>
        </div>
        <div class="flex justify-between text-sm border-t border-blue-200 pt-3">
            <span class="text-gray-700 font-semibold">الإجمالي</span>
            <span class="font-black text-blue-700">{{ $booking->totalFormatted() }}</span>
        </div>
    </div>

    <div class="flex gap-3 justify-center">
        <a href="{{ route('travel.index', request()->route('country', 'ae')) }}"
           class="px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-500">
            استعراض باقات أخرى
        </a>
    </div>
</div>
@endsection
