@extends('layouts.marketer')
@section('title', 'تفاصيل الطلب')
@section('page-title', 'تفاصيل الطلب')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
    <div>
        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-700">
            {{ $specialRequest->category->name_ar ?? $specialRequest->category->name_en }}
        </span>
        <span class="text-xs text-gray-400 ms-2">{{ $specialRequest->created_at->format('Y-m-d H:i') }}</span>
    </div>

    <h3 class="text-xl font-bold text-gray-900">{{ $specialRequest->title_ar ?? $specialRequest->title_en }}</h3>
    <p class="text-gray-600">{{ $specialRequest->description_ar ?? $specialRequest->description_en }}</p>

    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <div class="text-gray-400">المدينة</div>
            <div class="font-semibold">{{ $specialRequest->city->name_ar ?? $specialRequest->city->name_en ?? 'كل المدن' }}</div>
        </div>
        @if($specialRequest->budget)
        <div>
            <div class="text-gray-400">الميزانية</div>
            <div class="font-semibold">{{ number_format($specialRequest->budget) }} {{ $specialRequest->budget_currency }}</div>
        </div>
        @endif
        <div>
            <div class="text-gray-400">العميل</div>
            <div class="font-semibold">{{ $specialRequest->customer->name ?? '—' }}</div>
        </div>
        <div>
            <div class="text-gray-400">الحالة</div>
            <div class="font-semibold">{{ $specialRequest->status }}</div>
        </div>
    </div>

    <a href="{{ route('marketer.special-requests.index') }}" class="inline-block mt-2 text-sm text-blue-600">
        ← العودة للقائمة
    </a>
</div>
@endsection
