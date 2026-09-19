@extends('layouts.marketer')
@php $ar = session('locale', 'ar') === 'ar'; $nm = fn($m) => $m ? ($ar ? ($m->name_ar ?? $m->name_en) : ($m->name_en ?? $m->name_ar)) : null; @endphp
@section('title', $ar ? 'تفاصيل الطلب' : 'Request details')
@section('page-title', $ar ? 'تفاصيل الطلب' : 'Request details')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
    <div>
        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-700">{{ $nm($specialRequest->category) }}</span>
        <span class="text-xs text-gray-400 ms-2">{{ $specialRequest->created_at->format('Y-m-d H:i') }}</span>
    </div>

    <h3 class="text-xl font-bold text-gray-900">{{ $ar ? ($specialRequest->title_ar ?? $specialRequest->title_en) : ($specialRequest->title_en ?? $specialRequest->title_ar) }}</h3>
    <p class="text-gray-600 whitespace-pre-line">{{ $ar ? ($specialRequest->description_ar ?? $specialRequest->description_en) : ($specialRequest->description_en ?? $specialRequest->description_ar) }}</p>

    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <div class="text-gray-400">{{ $ar ? 'المدينة' : 'City' }}</div>
            <div class="font-semibold">{{ $nm($specialRequest->city) ?? ($ar ? 'كل المدن' : 'All cities') }}</div>
        </div>
        @if($specialRequest->budget)
        <div>
            <div class="text-gray-400">{{ $ar ? 'الميزانية' : 'Budget' }}</div>
            <div class="font-semibold">{{ number_format($specialRequest->budget) }} {{ $specialRequest->budget_currency }}</div>
        </div>
        @endif
        <div>
            <div class="text-gray-400">{{ $ar ? 'العميل' : 'Customer' }}</div>
            <div class="font-semibold">{{ \Illuminate\Support\Str::before(trim((string) ($specialRequest->customer->name ?? '')), ' ') ?: '—' }}</div>
        </div>
        <div>
            <div class="text-gray-400">{{ $ar ? 'الحالة' : 'Status' }}</div>
            <div class="font-semibold">{{ $specialRequest->status }}</div>
        </div>
    </div>

    <a href="{{ route('marketer.special-requests.index') }}" class="inline-block mt-2 text-sm text-blue-600">{{ $ar ? '← العودة للقائمة' : '← Back to list' }}</a>
</div>
@endsection
