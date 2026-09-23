@extends('layouts.partner')

@section('title', 'دعوات المشاركة في القسائم')
@section('page-title', 'دعوات المشاركة في القسائم')

@php
    $statusLabels = ['pending' => 'قيد المراجعة', 'approved' => 'مقبول', 'rejected' => 'مرفوض', 'paid' => 'مقبول ومدفوع'];
@endphp

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm p-3">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <h2 class="font-bold text-gray-900">الدعوات المفتوحة</h2>

    @forelse($invitations as $invitation)
        @php $myRequest = $invitation->requests->first(); @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start justify-between gap-4">
            <div class="flex-1">
                <h4 class="font-bold text-gray-900">{{ $invitation->title ?? 'دعوة مشاركة في قسيمة' }}</h4>
                <p class="text-sm text-gray-500 mt-1">{{ $invitation->description }}</p>
                <div class="text-sm text-gray-500 mt-2 space-y-0.5">
                    <div>الحد الأدنى للرسوم: {{ number_format($invitation->min_fee_amount) }} {{ $invitation->currency }}</div>
                    <div>المشاركون: {{ $invitation->approved_requests_count }} / {{ $invitation->max_participants }}</div>
                    <div>آخر موعد للتسجيل: {{ $invitation->registration_deadline->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            <div class="w-56">
                @if($myRequest)
                    <div class="text-xs text-center px-2 py-2 rounded bg-gray-100 text-gray-600">
                        طلبك: {{ number_format($myRequest->offered_fee_amount) }} {{ $invitation->currency }} — {{ $statusLabels[$myRequest->status] ?? $myRequest->status }}
                    </div>
                @else
                    <form method="POST" action="{{ route('partner.coupon-participation.store', $invitation->id) }}" class="flex gap-2">
                        @csrf
                        <input type="number" name="offered_fee_amount" min="{{ $invitation->min_fee_amount }}" value="{{ $invitation->min_fee_amount }}" class="w-full rounded border-gray-300 text-sm" required />
                        <button type="submit" class="px-3 py-2 rounded bg-blue-600 text-white text-sm whitespace-nowrap">إرسال طلب</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 text-sm">لا توجد دعوات مفتوحة حاليًا</div>
    @endforelse
    {{ $invitations->links() }}

    <h2 class="font-bold text-gray-900 pt-4">طلباتي</h2>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr><th class="px-4 py-2 text-start">الدعوة</th><th class="px-4 py-2 text-start">الرسوم</th><th class="px-4 py-2 text-start">الحالة</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($myRequests as $r)
                <tr>
                    <td class="px-4 py-2">{{ $r->invitation?->title ?? '—' }}</td>
                    <td class="px-4 py-2">{{ number_format($r->offered_fee_amount) }} {{ $r->invitation?->currency }}</td>
                    <td class="px-4 py-2">{{ $statusLabels[$r->status] ?? $r->status }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">لا توجد طلبات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $myRequests->links() }}
</div>
@endsection
