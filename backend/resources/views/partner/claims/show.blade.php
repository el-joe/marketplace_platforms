@extends('layouts.partner')

@section('title', __('partner.claims.claim_details') . ' ' . $claim->claim_number)

@section('content')

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('partner.claims.index') }}" class="text-gray-400 hover:text-gray-600">&larr; {{ __('partner.claims.claims') }}</a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-900">{{ $claim->claim_number }}</h1>
        <span class="badge {{ $claim->statusBadgeClass() }}">
            {{ Str::title(str_replace('_',' ',$claim->status->value)) }}
        </span>
    </div>

    <div class="max-w-2xl space-y-5">

        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('partner.claims.claim_details') }}</h2>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">{{ __('partner.claims.claim_type_label') }}</dt>
                    <dd class="font-medium">{{ Str::title(str_replace('_',' ',$claim->claim_type->value)) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('partner.claims.claimed_amount_label') }}</dt>
                    <dd class="font-medium">{{ $claim->claimed_amount_formatted }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('partner.claims.carrier') }}</dt>
                    {{-- Vendor CAN see carrier name --}}
                    <dd class="font-medium">{{ $claim->shippingCompany?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('partner.claims.submitted') }}</dt>
                    <dd>{{ $claim->created_at->format('d M Y H:i') }}</dd>
                </div>
            </dl>
            <div>
                <p class="text-gray-500 text-xs mb-1">{{ __('common.description') }}</p>
                <p class="text-gray-800 text-sm leading-relaxed">{{ $claim->description }}</p>
            </div>
        </div>

        {{-- Status timeline --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">{{ __('common.status') }}</h2>
            @php
                $steps = ['submitted','under_review','approved','compensated'];
                $current = array_search($claim->status->value, $steps);
                if ($claim->status === \App\Enums\CarrierClaimStatus::Rejected) $current = -1;
            @endphp
            @if($claim->status === \App\Enums\CarrierClaimStatus::Rejected)
                <div class="flex items-center gap-3 p-3 bg-red-50 rounded-lg">
                    <span class="text-red-500 text-xl">✗</span>
                    <div>
                        <p class="font-medium text-red-700">{{ __('partner.claims.claim_rejected') }}</p>
                        @if($claim->resolution_notes)
                            <p class="text-sm text-red-600 mt-0.5">{{ $claim->resolution_notes }}</p>
                        @endif
                    </div>
                </div>
            @else
                <ol class="flex gap-0">
                    @foreach($steps as $i => $step)
                        <li class="flex-1 flex flex-col items-center text-xs
                            {{ $i <= $current ? 'text-primary-600' : 'text-gray-400' }}">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center mb-1
                                {{ $i < $current ? 'bg-primary-600 text-white' : ($i === $current ? 'border-2 border-primary-600 text-primary-600' : 'border-2 border-gray-300 text-gray-300') }}">
                                @if($i < $current) ✓ @else {{ $i + 1 }} @endif
                            </div>
                            {{ __('partner.claims.status_steps.' . $step) }}
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        @if($claim->isResolved() && $claim->status !== \App\Enums\CarrierClaimStatus::Rejected)
            <div class="card p-5 border-l-4 border-green-400">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">{{ __('partner.claims.resolution_title') }}</h2>
                <p class="text-sm text-gray-700">
                    {!! __('partner.claims.resolution_text', ['amount' => '<strong>' . $claim->compensated_amount_formatted . '</strong>']) !!}
                </p>
                @if($claim->resolution_notes)
                    <p class="text-sm text-gray-500 mt-1">{{ $claim->resolution_notes }}</p>
                @endif
            </div>
        @endif

    </div>

@endsection
