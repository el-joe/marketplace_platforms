@extends('layouts.admin')

@section('title', $invitation->title ?? __('admin.coupon_participation_section.cps_default'))

@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h1 class="text-lg font-bold text-gray-900">{{ $invitation->title ?? __('admin.coupon_participation_section.cps_default') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $invitation->description }}</p>
        <div class="grid grid-cols-4 gap-4 mt-4 text-sm">
            <div><span class="text-gray-400">{{ __('admin.coupon_participation_section.cps_status') }}</span><div class="font-medium">{{ $invitation->status }}</div></div>
            <div><span class="text-gray-400">{{ __('admin.coupon_participation_section.cps_max') }}</span><div class="font-medium">{{ $invitation->max_participants }}</div></div>
            <div><span class="text-gray-400">{{ __('admin.coupon_participation_section.cps_min_fee') }}</span><div class="font-medium">{{ number_format($invitation->min_fee_amount) }} {{ $invitation->currency }}</div></div>
            <div><span class="text-gray-400">{{ __('admin.coupon_participation_section.cps_deadline') }}</span><div class="font-medium">{{ $invitation->registration_deadline->format('Y-m-d H:i') }}</div></div>
        </div>
        @if($invitation->status === \App\Models\CouponParticipationInvitation::STATUS_OPEN)
        <form method="POST" action="{{ route('admin.coupon-participation-invitations.cancel', $invitation->id) }}" class="mt-4">
            @csrf
            <button type="submit" class="btn btn-outline text-red-600" onclick="return confirm(__('admin.coupon_participation_section.cps_cancel_confirm'))">{{ __('admin.coupon_participation_section.cps_cancel') }}</button>
        </form>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-sm">{{ __('admin.coupon_participation_section.cps_requests') }}</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_type') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_participant') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_offered_fee') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_status') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $row)
                @php $r = $row['model']; @endphp
                <tr>
                    <td class="px-4 py-2">{{ $r->participant_type === 'vendor' ? __('admin.coupon_participation_section.cps_vendor') : __('admin.coupon_participation_section.cps_marketer') }}</td>
                    <td class="px-4 py-2">{{ $row['participant_name'] }}</td>
                    <td class="px-4 py-2">{{ number_format($r->offered_fee_amount) }} {{ $invitation->currency }}</td>
                    <td class="px-4 py-2">{{ $r->status }} ({{ $r->payment_method === 'bank_transfer' ? __('partner.payment_method_bank_transfer') : __('partner.payment_method_wallet') }})
                        @if($r->bank_transfer_proof_path)<div class="text-xs text-gray-500">{{ $r->bank_transfer_proof_path }}</div>@endif</td>
                    <td class="px-4 py-2 text-end space-s-2">
                        @if($r->status === 'pending')
                            <button class="text-green-600 text-xs" data-approve="{{ route('admin.coupon-participation-invitations.requests.approve', [$invitation->id, $r->id]) }}">{{ __('admin.coupon_participation_section.cps_approve') }}</button>
                            <button class="text-red-600 text-xs" data-refund="1" data-reject="{{ route('admin.coupon-participation-invitations.requests.reject', [$invitation->id, $r->id]) }}">{{ __('admin.coupon_participation_section.cps_reject') }}</button>
                        @elseif($r->status === 'approved')
                            <button class="text-blue-600 text-xs" data-mark-paid="{{ route('admin.coupon-participation-invitations.requests.mark-paid', [$invitation->id, $r->id]) }}">{{ __('admin.coupon_participation_section.cps_mark_paid') }}</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('admin.coupon_participation_section.cps_no_requests') }}</td></tr>
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
        const refund = btn.hasAttribute('data-reject') ? (confirm(__('admin.coupon_participation_section.cps_refund_confirm')) ? 1 : 0) : 1;
        fetch(url, {
            method: 'POST',
            body: new URLSearchParams({refund_on_reject: refund}),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        }).then(r => r.json()).then(res => {
            if (res.success) { location.reload(); } else { alert(res.message || __('admin.coupon_participation_section.cps_error')); }
        });
    });
</script>
@endpush
@endsection
