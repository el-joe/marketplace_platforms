@extends('layouts.partner')

@section('title', __('partner.warranty_claims.title'))
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
    $tabStatuses = array_keys($statusMap);
    $currentStatus = request('status');
@endphp

{{-- Status tabs --}}
<div class="bg-white rounded-2xl border border-gray-200 mb-4">
    <div class="flex items-center overflow-x-auto">
        <a href="{{ route('partner.warranty-claims.index') }}" @class([
            'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
            'border-primary-500 text-primary-600' => !$currentStatus,
            'border-transparent text-gray-500 hover:text-gray-700' => $currentStatus,
        ])>{{ __('partner.warranty_claims.all') }} <span class="mr-1 text-xs text-gray-400">({{ $claims->total() }})</span></a>

        @foreach($tabStatuses as $st)
            <a href="{{ route('partner.warranty-claims.index', ['status' => $st]) }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                'border-primary-500 text-primary-600' => $currentStatus === $st,
                'border-transparent text-gray-500 hover:text-gray-700' => $currentStatus !== $st,
            ])>{{ $statusMap[$st][1] }}</a>
        @endforeach
    </div>
</div>

<div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold text-gray-800">{{ __('partner.warranty_claims.claims_list') }}</h2>
    <span class="text-xs text-gray-400">{{ __('partner.warranty_claims.claim_count', ['count' => $claims->total()]) }}</span>
</div>

@if($claims->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
        <div class="text-4xl mb-3">🛡️</div>
        <h3 class="font-semibold text-gray-800 mb-1">{{ __('partner.warranty_claims.no_claims_found') }}</h3>
        <p class="text-sm text-gray-400">{{ __('partner.warranty_claims.no_claims_hint') }}</p>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($claims as $claim)
            @php
                [$statusCls, $statusLabel] = $statusMap[$claim->status] ?? ['bg-gray-100 text-gray-500', $claim->status];
                $productImage = $claim->product?->images->where('is_primary', true)->first() ?? $claim->product?->images->first();
                $nameParts = array_filter(explode(' ', trim($claim->customer->name ?? '')));
                $initials = $nameParts ? collect($nameParts)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('.') . '.' : '—';
            @endphp
            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-col gap-3">
                <div class="flex items-start gap-3">
                    <div class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center shrink-0">
                        @if($productImage)
                            <img src="{{ $productImage->url }}" alt="{{ $claim->product->name }}" class="w-full h-full object-cover">
                        @else
                            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $claim->product->name ?? '—' }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.warranty_claims.customer') }}: {{ $initials }}</p>
                        <p class="text-xs font-mono text-gray-400 mt-0.5">{{ $claim->claim_number }}</p>
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusCls }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                    <span class="text-xs text-gray-400">{{ $claim->created_at->format('Y/m/d') }}</span>
                    <a href="{{ route('partner.warranty-claims.show', $claim->id) }}"
                        class="text-xs font-medium text-primary-600 hover:text-primary-800">
                        {{ __('partner.warranty_claims.view') }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    @if($claims->hasPages())
        <div class="mt-4">
            {{ $claims->links() }}
        </div>
    @endif
@endif

@endsection
