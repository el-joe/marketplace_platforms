@extends('layouts.partner')

@section('title', __('partner.coupon_participation_invitations'))
@section('page-title', __('partner.coupon_participation_invitations'))

@php
    $statusLabels = ['pending' => __('partner.cp_status_pending'), 'approved' => __('partner.cp_status_approved'), 'rejected' => __('partner.cp_status_rejected'), 'paid' => __('partner.cp_status_paid')];
@endphp

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm p-3">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <h2 class="font-bold text-gray-900">{{ __('partner.cp_open_invitations') }}</h2>

    @forelse($invitations as $invitation)
        @php $myRequest = $invitation->requests->first(); @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start justify-between gap-4">
            <div class="flex-1">
                <h4 class="font-bold text-gray-900">{{ $invitation->title ?? __('partner.cp_default_title') }}</h4>
                <p class="text-sm text-gray-500 mt-1">{{ $invitation->description }}</p>
                <div class="text-sm text-gray-500 mt-2 space-y-0.5">
                    <div>{{ __('partner.cp_min_fee_label') }}: {{ number_format($invitation->min_fee_amount) }} {{ $invitation->currency }}</div>
                    <div>{{ __('partner.cp_participants') }}: {{ $invitation->approved_requests_count }} / {{ $invitation->max_participants }}</div>
                    <div>{{ __('partner.cp_deadline_label') }}: {{ $invitation->registration_deadline->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            <div class="w-56">
                @if($myRequest)
                    <div class="text-xs text-center px-2 py-2 rounded bg-gray-100 text-gray-600">
                        {{ __('partner.cp_your_request') }}: {{ number_format($myRequest->offered_fee_amount) }} {{ $invitation->currency }} — {{ $statusLabels[$myRequest->status] ?? $myRequest->status }}
                    </div>
                @else
                    <form method="POST" action="{{ route('partner.coupon-participation.store', $invitation->id) }}" class="flex flex-col gap-2" x-data="{ method: 'wallet', balance: {{ (int) ($balances[$invitation->id] ?? 0) }}, minFee: {{ (int) $invitation->min_fee_amount }} }" enctype="multipart/form-data">
                        @csrf
<div class="text-xs text-gray-600 mb-1">{{ __('partner.wallet_balance') }}: {{ number_format($balances[$invitation->id] ?? 0) }} {{ $invitation->currency }}</div>
@if(($balances[$invitation->id] ?? 0) < $invitation->min_fee_amount)
<div class="text-xs text-amber-700 bg-amber-50 rounded p-1 mb-1">{{ __('partner.insufficient_balance_warning') }}</div>
@endif
<select name="payment_method" x-model="method" class="w-full rounded border-gray-300 text-sm"><option value="wallet">{{ __('partner.payment_method_wallet') }}</option><option value="bank_transfer">{{ __('partner.payment_method_bank_transfer') }}</option></select>
<input type="file" name="bank_transfer_proof" class="w-full text-xs" />
                        <input type="number" name="offered_fee_amount" min="{{ $invitation->min_fee_amount }}" value="{{ $invitation->min_fee_amount }}" class="w-full rounded border-gray-300 text-sm" required />
                        <button type="submit" :disabled="method === \'wallet\' && balance < minFee" :class="method === \'wallet\' && balance < minFee ? \'opacity-50 cursor-not-allowed\' : \'\'" class="px-3 py-2 rounded bg-blue-600 text-white text-sm whitespace-nowrap">{{ __('partner.cp_submit_request') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 text-sm">{{ __('partner.cp_no_open') }}</div>
    @endforelse
    {{ $invitations->links() }}

    <h2 class="font-bold text-gray-900 pt-4">{{ __('partner.cp_my_requests') }}</h2>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr><th class="px-4 py-2 text-start">{{ __('partner.cp_col_invitation') }}</th><th class="px-4 py-2 text-start">{{ __('partner.cp_col_fee') }}</th><th class="px-4 py-2 text-start">{{ __('partner.cp_col_status') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($myRequests as $r)
                <tr>
                    <td class="px-4 py-2">{{ $r->invitation?->title ?? '—' }}</td>
                    <td class="px-4 py-2">{{ number_format($r->offered_fee_amount) }} {{ $r->invitation?->currency }}</td>
                    <td class="px-4 py-2">{{ $statusLabels[$r->status] ?? $r->status }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">{{ __('partner.cp_no_requests') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $myRequests->links() }}
</div>
@endsection
