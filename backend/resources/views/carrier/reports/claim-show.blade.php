@extends('layouts.carrier')
@section('title', $claim->claim_number)
@section('content')

<div class="mb-6">
    <a href="{{ route('carrier.reports.claims') }}" class="text-indigo-600 hover:underline text-sm">
        ← {{ __('carrier.reports.claims_title') }}
    </a>
    <h1 class="text-2xl font-black text-gray-900 mt-2">{{ $claim->claim_number }}</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-5">

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">{{ __('carrier.reports.claim_details') }}</h2>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500 text-xs uppercase">{{ __('carrier.reports.claims_type') }}</dt>
                    <dd class="font-medium capitalize mt-0.5">{{ str_replace('_',' ',$claim->claim_type->value) }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">{{ __('carrier.reports.col_status') }}</dt>
                    <dd class="font-medium capitalize mt-0.5">{{ str_replace('_',' ',$claim->status->value) }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">{{ __('carrier.reports.col_agent') }}</dt>
                    <dd class="font-medium mt-0.5">{{ $claim->deliveryAgent?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">{{ __('carrier.reports.col_tracking') }}</dt>
                    <dd class="font-mono text-xs mt-0.5">{{ $claim->shipment?->tracking_number ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">{{ __('carrier.reports.col_claimed') }} ({{ $currency }})</dt>
                    <dd class="font-bold mt-0.5">{{ number_format($claim->claimed_amount) }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">{{ __('carrier.reports.col_compensated') }} ({{ $currency }})</dt>
                    <dd class="font-bold {{ $claim->compensated_amount ? 'text-red-600' : 'text-gray-400' }} mt-0.5">
                        {{ $claim->compensated_amount ? number_format($claim->compensated_amount) : '—' }}
                    </dd></div>
            </dl>
            <div class="mt-4 border-t border-gray-100 pt-4">
                <dt class="text-gray-500 text-xs uppercase mb-1">{{ __('carrier.reports.col_description') }}</dt>
                <dd class="text-gray-700 text-sm">{{ $claim->description }}</dd>
            </div>
            @if($claim->resolution_notes)
            <div class="mt-4 border-t border-gray-100 pt-4">
                <dt class="text-gray-500 text-xs uppercase mb-1">{{ __('carrier.reports.col_resolution') }}</dt>
                <dd class="text-gray-700 text-sm">{{ $claim->resolution_notes }}</dd>
            </div>
            @endif
        </div>

    </div>

    <div class="space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
            <h2 class="font-bold text-gray-900 mb-3">{{ __('carrier.reports.claim_timeline') }}</h2>
            <div class="space-y-2 text-gray-500">
                <div>📅 {{ __('carrier.reports.col_date') }}: {{ $claim->created_at->format('d M Y H:i') }}</div>
                @if($claim->resolved_at)
                <div>✓ {{ __('carrier.reports.claim_resolved') }}: {{ $claim->resolved_at->format('d M Y H:i') }}</div>
                @endif
            </div>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-700">
            {{ __('carrier.reports.claim_readonly_note') }}
        </div>
    </div>
</div>
@endsection
