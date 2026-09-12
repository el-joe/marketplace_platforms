@extends('layouts.marketer')
@section('title', 'طلبات العملاء')
@section('page-title', 'طلبات العملاء')

@section('content')
<div class="space-y-4">

    @if($requests->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">📭</div>
            <h3 class="font-bold text-gray-700">لا توجد طلبات مطابقة لتخصصك حالياً</h3>
            <p class="text-gray-400 text-sm mt-1">ستظهر هنا طلبات العملاء التي تطابق القسم والمدينة المحددين في ملفك</p>
        </div>
    @else
        @foreach($requests as $request)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-700">
                            {{ $request->category->name_ar ?? $request->category->name_en }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $request->created_at->format('Y-m-d') }}</span>
                    </div>
                    <h4 class="font-bold text-gray-900">{{ $request->title_ar ?? $request->title_en }}</h4>
                    <p class="text-sm text-gray-500 mt-1">{{ $request->description_ar ?? $request->description_en }}</p>
                    <div class="text-sm text-gray-500 mt-2">
                        📍 {{ $request->city->name_ar ?? $request->city->name_en ?? 'كل المدن' }}
                        @if($request->budget)
                            • الميزانية: {{ number_format($request->budget) }} {{ $request->budget_currency }}
                        @endif
                    </div>
                </div>
                <a href="{{ route('marketer.special-requests.show', $request->id) }}"
                   class="px-4 py-2 bg-gray-900 text-white text-sm font-semibold rounded-lg hover:bg-gray-800">
                    عرض
                </a>
            </div>
        </div>
        @endforeach

        <div class="mt-4">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
