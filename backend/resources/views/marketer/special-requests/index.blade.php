@extends('layouts.marketer')
@php $ar = session('locale', 'ar') === 'ar'; $nm = fn($m) => $m ? ($ar ? ($m->name_ar ?? $m->name_en) : ($m->name_en ?? $m->name_ar)) : null; @endphp
@section('title', $ar ? 'طلبات العملاء' : 'Special Requests')
@section('page-title', $ar ? 'طلبات العملاء' : 'Special Requests')

@section('content')
<div class="space-y-4">

    @if(!$hasSpecialization)
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4 text-sm">
            {{ $ar ? 'لم تحدد تخصصك بعد، لذلك لن تصلك طلبات.' : 'You have not set your specialization yet, so no requests will match.' }}
            <a href="{{ route('marketer.profile') }}#broker-specialization" class="font-semibold underline">{{ $ar ? 'حدد القسم والمدينة من الملف الشخصي' : 'Set it in your profile' }}</a>
        </div>
    @endif

    @if($requests->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <h3 class="font-bold text-gray-700">{{ $ar ? 'لا توجد طلبات مطابقة لتخصصك حالياً' : 'No matching requests right now' }}</h3>
            <p class="text-gray-400 text-sm mt-1">{{ $ar ? 'ستظهر هنا طلبات العملاء التي تطابق القسم والمدينة المحددين في ملفك' : 'Customer requests matching your category and city will appear here' }}</p>
        </div>
    @else
        @foreach($requests as $request)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-700">{{ $nm($request->category) }}</span>
                        <span class="text-xs text-gray-400">{{ $request->created_at->format('Y-m-d') }}</span>
                    </div>
                    <h4 class="font-bold text-gray-900">{{ $ar ? ($request->title_ar ?? $request->title_en) : ($request->title_en ?? $request->title_ar) }}</h4>
                    <p class="text-sm text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($ar ? ($request->description_ar ?? $request->description_en) : ($request->description_en ?? $request->description_ar), 200) }}</p>
                    <div class="text-sm text-gray-500 mt-2">
                        {{ $nm($request->city) ?? ($ar ? 'كل المدن' : 'All cities') }}
                        @if($request->budget)
                            &bull; {{ $ar ? 'الميزانية' : 'Budget' }}: {{ number_format($request->budget) }} {{ $request->budget_currency }}
                        @endif
                    </div>
                </div>
                <a href="{{ route('marketer.special-requests.show', $request->id) }}"
                   class="px-4 py-2 bg-gray-900 text-white text-sm font-semibold rounded-lg hover:bg-gray-800">{{ $ar ? 'عرض' : 'View' }}</a>
            </div>
        </div>
        @endforeach

        <div class="mt-4">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
