@extends('layouts.delivery')

@section('title', __('delivery.profile.title'))

@section('content')

@php
    /** @var \App\Models\DeliveryAgent $agent */
    $docStatusColors = [
        \App\Enums\DeliveryAgentDocumentStatus::Verified->value => 'chip-delivered',
        \App\Enums\DeliveryAgentDocumentStatus::Pending->value  => 'chip-assigned',
        \App\Enums\DeliveryAgentDocumentStatus::Rejected->value => 'chip-failed',
        \App\Enums\DeliveryAgentDocumentStatus::Expired->value  => 'chip-failed',
    ];
@endphp

{{-- ── Profile Card ─────────────────────────────────────────────────────────── --}}
<div class="d-card mb-4 text-center py-8">
    <div class="w-20 h-20 rounded-full bg-yellow-500/20 text-yellow-400 flex items-center justify-center text-3xl font-bold mx-auto mb-3">
        {{ strtoupper(substr($agent->name, 0, 1)) }}
    </div>
    <h1 class="text-xl font-bold">{{ $agent->name }}</h1>
    <p class="text-slate-400 text-sm mt-0.5">{{ $agent->phone }}</p>
    <div class="flex items-center justify-center gap-2 mt-2">
        @php
            $statusColor = match($agent->status) {
                \App\Enums\DeliveryAgentStatus::Active    => 'chip-accepted',
                \App\Enums\DeliveryAgentStatus::OnShift   => 'chip-picked_up',
                \App\Enums\DeliveryAgentStatus::Suspended => 'chip-failed',
                default     => 'chip-assigned',
            };
        @endphp
        <span class="chip {{ $statusColor }}">{{ ucfirst(str_replace('_', ' ', $agent->status->value)) }}</span>
        <span class="chip chip-assigned">{{ $agent->vehicle_type->label() }}</span>
    </div>
    <div class="flex items-center justify-center gap-4 mt-4 pt-4 border-t border-slate-700">
        <div class="text-center">
            <p class="text-xl font-bold text-yellow-400">{{ number_format($agent->rating_avg, 1) }}</p>
            <p class="text-xs text-slate-400">{{ __('delivery.profile.rating') }}</p>
        </div>
        <div class="w-px h-10 bg-slate-700"></div>
        <div class="text-center">
            <p class="text-xl font-bold text-white">{{ number_format($agent->total_deliveries) }}</p>
            <p class="text-xs text-slate-400">{{ __('delivery.profile.deliveries') }}</p>
        </div>
        @if($agent->zone)
            <div class="w-px h-10 bg-slate-700"></div>
            <div class="text-center">
                <p class="text-base font-bold text-white truncate max-w-[80px]">{{ $agent->zone->name }}</p>
                <p class="text-xs text-slate-400">{{ __('delivery.profile.zone') }}</p>
            </div>
        @endif
    </div>
</div>

{{-- ── Vehicle Info ─────────────────────────────────────────────────────────── --}}
<div class="d-card mb-4">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.profile.vehicle_info') }}</h3>
    <div class="space-y-3">
        @foreach([
            __('delivery.profile.type')        => $agent->vehicle_type->label(),
            __('delivery.profile.plate')       => $agent->vehicle_plate ?? '—',
            __('delivery.profile.national_id') => $agent->national_id ? '••••' . substr($agent->national_id, -4) : '—',
        ] as $label => $value)
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">{{ $label }}</span>
                <span class="font-medium text-slate-200">{{ $value }}</span>
            </div>
        @endforeach
    </div>
</div>

{{-- ── Emergency Contact ────────────────────────────────────────────────────── --}}
@if($agent->emergency_contact_name || $agent->emergency_contact_phone)
    <div class="d-card mb-4">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.profile.emergency_contact') }}</h3>
        <div class="flex items-center justify-between">
            <div>
                <p class="font-medium text-slate-200">{{ $agent->emergency_contact_name ?? '—' }}</p>
                <p class="text-sm text-slate-400">{{ $agent->emergency_contact_phone ?? '—' }}</p>
            </div>
            @if($agent->emergency_contact_phone)
                <a href="tel:{{ $agent->emergency_contact_phone }}"
                   class="bg-green-600 text-white rounded-xl px-4 py-2 text-sm font-semibold">{{ __('delivery.profile.call') }}</a>
            @endif
        </div>
    </div>
@endif

{{-- ── Documents ────────────────────────────────────────────────────────────── --}}
<div class="d-card mb-4">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.profile.documents') }}</h3>
    @forelse($agent->documents as $doc)
        <div class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
            <p class="text-sm font-medium text-slate-200">{{ $doc->label }}</p>
            <span class="chip {{ $docStatusColors[$doc->status->value] ?? 'chip-assigned' }}">
                {{ $doc->status->label() }}
            </span>
        </div>
    @empty
        <p class="text-sm text-slate-500">{{ __('delivery.profile.no_documents_on_file') }}</p>
    @endforelse
</div>

{{-- ── Change Password ──────────────────────────────────────────────────────── --}}
<div class="d-card mb-8" x-data="{ open: false }">
    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between text-sm font-semibold text-slate-200">
        <span>{{ __('delivery.profile.change_password') }}</span>
        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': open }"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-collapse class="mt-4 border-t border-slate-700 pt-4">
        <form method="POST" action="{{ route('delivery.profile.password') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs text-slate-400 mb-1">{{ __('delivery.profile.current_password') }}</label>
                <input type="password" name="current_password"
                    class="w-full bg-slate-700 text-slate-200 rounded-xl p-3 text-sm border border-slate-600 focus:border-yellow-400 focus:outline-none"
                    required>
                @error('current_password')
                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">{{ __('delivery.profile.new_password') }}</label>
                <input type="password" name="password"
                    class="w-full bg-slate-700 text-slate-200 rounded-xl p-3 text-sm border border-slate-600 focus:border-yellow-400 focus:outline-none"
                    required minlength="8">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">{{ __('delivery.profile.confirm_new_password') }}</label>
                <input type="password" name="password_confirmation"
                    class="w-full bg-slate-700 text-slate-200 rounded-xl p-3 text-sm border border-slate-600 focus:border-yellow-400 focus:outline-none"
                    required minlength="8">
            </div>
            <button type="submit" class="btn-action btn-yellow text-sm" style="min-height:48px;">
                {{ __('delivery.profile.update_password') }}
            </button>
        </form>
    </div>
</div>

{{-- ── Logout ───────────────────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('delivery.logout') }}" class="mb-8">
    @csrf
    <button type="submit" class="btn-action btn-outline text-sm text-red-400" style="min-height:48px; border-color: #7f1d1d;">
        {{ __('delivery.profile.sign_out') }}
    </button>
</form>

@endsection
