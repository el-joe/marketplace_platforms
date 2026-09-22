@extends('layouts.marketer')
@section('title', 'دعوات المشاركة في القسائم')
@section('page-title', 'دعوات المشاركة في القسائم')

@section('content')
<div class="space-y-4">

    @if($invitations->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">🎟️</div>
            <h3 class="font-bold text-gray-700">لا توجد دعوات مفتوحة حاليًا</h3>
            <p class="text-gray-400 text-sm mt-1">ستظهر هنا دعوات المشاركة في القسائم برسوم اشتراك</p>
        </div>
    @else
        @foreach($invitations as $invitation)
        @php
            $myRequest = $invitation->requests->first();
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4">
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
                            طلبك: {{ number_format($myRequest->offered_fee_amount) }} {{ $invitation->currency }} —
                            {{ ['pending' => 'قيد المراجعة', 'approved' => 'مقبول', 'rejected' => 'مرفوض', 'paid' => 'مدفوع'][$myRequest->status] ?? $myRequest->status }}
                        </div>
                    @else
                        <form method="POST" action="{{ route('marketer.coupon-participation.store', $invitation->id) }}" class="flex gap-2">
                            @csrf
                            <input type="number" name="offered_fee_amount" min="{{ $invitation->min_fee_amount }}" value="{{ $invitation->min_fee_amount }}" class="input w-full" required />
                            <button type="submit" class="btn btn-primary whitespace-nowrap">إرسال طلب</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        <div>{{ $invitations->links() }}</div>
    @endif

</div>
@endsection
