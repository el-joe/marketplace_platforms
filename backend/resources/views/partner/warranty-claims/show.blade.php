@extends('layouts.partner')

@section('title', __('partner.warranty_claims.title') . ' · ' . $claim->claim_number)
@section('page-title', __('partner.warranty_claims.title'))

@section('content')
@php
    $statusMap = [
        \App\Models\WarrantyClaim::STATUS_SUBMITTED => ['bg-blue-100 text-blue-700', __('partner.warranty_claims.status_submitted')],
        \App\Models\WarrantyClaim::STATUS_UNDER_REVIEW => ['bg-yellow-100 text-yellow-700', __('partner.warranty_claims.status_under_review')],
        \App\Models\WarrantyClaim::STATUS_APPROVED => ['bg-green-100 text-green-700', __('partner.warranty_claims.status_approved')],
        \App\Models\WarrantyClaim::STATUS_REJECTED => ['bg-red-100 text-red-700', __('partner.warranty_claims.status_rejected')],
        \App\Models\WarrantyClaim::STATUS_RESOLVED => ['bg-gray-100 text-gray-500', __('partner.warranty_claims.status_resolved')],
    ];
    [$statusCls, $statusLabel] = $statusMap[$claim->status] ?? ['bg-gray-100 text-gray-500', $claim->status];
    $canRespond = in_array($claim->status, [\App\Models\WarrantyClaim::STATUS_SUBMITTED, \App\Models\WarrantyClaim::STATUS_UNDER_REVIEW], true);
    $nameParts = array_filter(explode(' ', trim($claim->customer->name ?? '')));
    $initials = $nameParts ? collect($nameParts)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('.') . '.' : '—';
@endphp

<div class="px-4 py-6 sm:px-6 lg:px-8">

    {{-- Breadcrumb --}}
    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('partner.warranty-claims.index') }}" class="hover:text-gray-700">{{ __('partner.warranty_claims.breadcrumb') }}</a>
        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-gray-800 font-medium font-mono">{{ $claim->claim_number }}</span>
    </div>

    {{-- Claim header card --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-lg font-bold text-gray-900">{{ $claim->product->name ?? '—' }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                    <span class="font-mono">{{ $claim->claim_number }}</span>
                    <span>·</span>
                    <span>{{ __('partner.warranty_claims.customer') }}: {{ $initials }}</span>
                    <span>·</span>
                    <span>{{ $claim->created_at->format('d/m/Y H:i') }}</span>
                </div>

                <dl class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-xs">
                    <div>
                        <dt class="text-gray-400">{{ __('partner.warranty_claims.issue_type') }}</dt>
                        <dd class="text-gray-700 font-medium">{{ __('partner.warranty_claims.issue_types.' . $claim->issue_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">{{ __('partner.warranty_claims.purchase_date') }}</dt>
                        <dd class="text-gray-700 font-medium">{{ $claim->purchase_date?->format('Y/m/d') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">{{ __('partner.warranty_claims.warranty_expires_at') }}</dt>
                        <dd class="text-gray-700 font-medium">{{ $claim->warranty_expires_at?->format('Y/m/d') ?? '—' }}</dd>
                    </div>
                </dl>

                @if($claim->issue_description)
                    <p class="mt-3 text-sm text-gray-600 leading-relaxed">{{ $claim->issue_description }}</p>
                @endif
            </div>
            <span class="flex-shrink-0 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusCls }}">
                {{ $statusLabel }}
            </span>
        </div>
    </div>

    {{-- Message thread --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 mb-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('partner.warranty_claims.claims_list') }}</h2>
        <div class="space-y-3">
            @foreach($claim->messages as $msg)
                @php $isMine = $msg->sender_role === \App\Models\WarrantyClaimMessage::SENDER_ROLE_VENDOR; @endphp
                <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] sm:max-w-[75%]">
                        <p class="mb-1 text-xs {{ $isMine ? 'text-end' : 'text-start' }} text-gray-400">
                            {{ $isMine
                                ? __('partner.warranty_claims.you')
                                : ($msg->sender_role === \App\Models\WarrantyClaimMessage::SENDER_ROLE_ADMIN
                                    ? __('partner.warranty_claims.admin_label')
                                    : __('partner.warranty_claims.customer_label')) }}
                            · {{ $msg->created_at->format('d/m H:i') }}
                        </p>
                        <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm
                            {{ $isMine
                                ? 'bg-primary-600 text-white rounded-tl-md'
                                : 'bg-white border border-gray-200 text-gray-800 rounded-tr-md' }}">
                            {!! nl2br(e($msg->message)) !!}
                        </div>
                    </div>
                </div>
            @endforeach

            @if($claim->messages->isEmpty())
                <div class="text-center text-sm text-gray-400 py-6">{{ __('partner.warranty_claims.no_messages') }}</div>
            @endif
        </div>
    </div>

    {{-- Response form --}}
    @if($canRespond)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-600 mb-2">{{ __('partner.warranty_claims.reply_title') }}</p>
            <form method="POST" action="{{ route('partner.warranty-claims.respond', $claim->id) }}">
                @csrf
                <textarea name="vendor_response" rows="4" minlength="10" maxlength="2000" required
                    placeholder="{{ __('partner.warranty_claims.reply_placeholder') }}"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 resize-y mb-3">{{ old('vendor_response') }}</textarea>
                @error('vendor_response')
                    <p class="text-xs text-red-600 mb-2">{{ $message }}</p>
                @enderror
                <div class="flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                        {{ __('partner.warranty_claims.send_reply') }}
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-500 text-center">
            {{ __('partner.warranty_claims.claim_closed_notice', ['status' => $statusLabel]) }}
        </div>
    @endif

</div>
@endsection
