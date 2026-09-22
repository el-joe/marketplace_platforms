@extends('layouts.admin')

@section('title', $invitation->title ?? 'دعوة مشاركة')

@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h1 class="text-lg font-bold text-gray-900">{{ $invitation->title ?? 'دعوة مشاركة' }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $invitation->description }}</p>
        <div class="grid grid-cols-4 gap-4 mt-4 text-sm">
            <div><span class="text-gray-400">الحالة</span><div class="font-medium">{{ $invitation->status }}</div></div>
            <div><span class="text-gray-400">الحد الأقصى</span><div class="font-medium">{{ $invitation->max_participants }}</div></div>
            <div><span class="text-gray-400">الحد الأدنى للرسوم</span><div class="font-medium">{{ number_format($invitation->min_fee_amount) }} {{ $invitation->currency }}</div></div>
            <div><span class="text-gray-400">آخر موعد</span><div class="font-medium">{{ $invitation->registration_deadline->format('Y-m-d H:i') }}</div></div>
        </div>
        @if($invitation->status === \App\Models\CouponParticipationInvitation::STATUS_OPEN)
        <form method="POST" action="{{ route('admin.coupon-participation-invitations.cancel', $invitation->id) }}" class="mt-4">
            @csrf
            <button type="submit" class="btn btn-outline text-red-600" onclick="return confirm('إلغاء هذه الدعوة؟')">إلغاء الدعوة</button>
        </form>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-sm">طلبات المشاركة</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-2 text-start">النوع</th>
                    <th class="px-4 py-2 text-start">المشارك</th>
                    <th class="px-4 py-2 text-start">الرسوم المعروضة</th>
                    <th class="px-4 py-2 text-start">الحالة</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $row)
                @php $r = $row['model']; @endphp
                <tr>
                    <td class="px-4 py-2">{{ $r->participant_type === 'vendor' ? 'بائع' : 'مشهور' }}</td>
                    <td class="px-4 py-2">{{ $row['participant_name'] }}</td>
                    <td class="px-4 py-2">{{ number_format($r->offered_fee_amount) }} {{ $invitation->currency }}</td>
                    <td class="px-4 py-2">{{ $r->status }}</td>
                    <td class="px-4 py-2 text-end space-s-2">
                        @if($r->status === 'pending')
                            <button class="text-green-600 text-xs" data-approve="{{ route('admin.coupon-participation-invitations.requests.approve', [$invitation->id, $r->id]) }}">قبول</button>
                            <button class="text-red-600 text-xs" data-reject="{{ route('admin.coupon-participation-invitations.requests.reject', [$invitation->id, $r->id]) }}">رفض</button>
                        @elseif($r->status === 'approved')
                            <button class="text-blue-600 text-xs" data-mark-paid="{{ route('admin.coupon-participation-invitations.requests.mark-paid', [$invitation->id, $r->id]) }}">تحديد كمدفوع</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">لا توجد طلبات مشاركة بعد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-approve], [data-reject], [data-mark-paid]');
        if (!btn) return;
        const url = btn.getAttribute('data-approve') || btn.getAttribute('data-reject') || btn.getAttribute('data-mark-paid');
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        }).then(r => r.json()).then(res => {
            if (res.success) { location.reload(); } else { alert(res.message || 'حدث خطأ'); }
        });
    });
</script>
@endpush
@endsection
