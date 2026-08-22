@extends('layouts.admin')

@section('title', __('admin.carriers_section.claim_id') . ' ' . $claim->claim_number)

@section('content')

    @php
        $statusLabels = [
            'submitted'    => __('admin.carriers_section.submitted'),
            'under_review' => __('admin.carriers_section.under_review'),
            'approved'     => __('admin.carriers_section.approved'),
            'compensated'  => __('admin.carriers_section.compensated'),
            'rejected'     => __('admin.carriers_section.rejected'),
        ];
        $claimTypeLabels = [
            'lost'       => __('admin.carriers_section.claim_type_lost'),
            'damaged'    => __('admin.carriers_section.claim_type_damaged'),
            'delayed'    => __('admin.carriers_section.claim_type_delayed'),
            'wrong_item' => __('admin.carriers_section.claim_type_wrong_item'),
            'other'      => __('admin.carriers_section.claim_type_other'),
        ];
    @endphp

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('admin.carrier-claims.index') }}" class="text-gray-400 hover:text-gray-600">
            {{ __('admin.carriers_section.back_to_claims') }}
        </a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-900">{{ $claim->claim_number }}</h1>
        <span class="badge {{ $claim->statusBadgeClass() }}">
            {{ $statusLabels[$claim->status->value] ?? Str::title(str_replace('_',' ',$claim->status->value)) }}
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ─── Main detail ──────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-5">

            <div class="card p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('admin.carriers_section.claim_details') }}</h2>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('admin.carriers_section.type_label') }}</dt>
                        <dd class="font-medium">{{ $claimTypeLabels[$claim->claim_type->value] ?? Str::title(str_replace('_',' ',$claim->claim_type->value)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('admin.carriers_section.claimed_amount_label') }}</dt>
                        <dd class="font-medium">{{ $claim->claimed_amount_formatted }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('admin.carriers_section.carrier_label') }}</dt>
                        <dd class="font-medium">{{ $claim->shippingCompany?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('admin.carriers_section.agent_label') }}</dt>
                        <dd class="font-medium">{{ $claim->deliveryAgent?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('admin.carriers_section.vendor_label') }}</dt>
                        <dd>{{ $claim->shipment?->subOrder?->vendor?->store_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('admin.carriers_section.submitted_label') }}</dt>
                        <dd>{{ $claim->created_at->format('d M Y H:i') }}</dd>
                    </div>
                </dl>
                <div>
                    <p class="text-gray-500 text-xs mb-1">{{ __('admin.carriers_section.description_label') }}</p>
                    <p class="text-gray-800 text-sm leading-relaxed">{{ $claim->description }}</p>
                </div>
            </div>

            {{-- Evidence Files --}}
            @if($claim->evidence_files)
                <div class="card p-5">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">{{ __('admin.carriers_section.evidence') }}</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($claim->evidence_files as $file)
                            <a href="{{ Storage::url($file) }}" target="_blank"
                               class="block rounded-lg overflow-hidden border border-gray-200 hover:border-primary-400 transition">
                                @if(Str::endsWith($file, ['.jpg','.jpeg','.png','.webp']))
                                    <img src="{{ Storage::url($file) }}" class="w-full h-28 object-cover" alt="{{ __('admin.carriers_section.evidence_alt') }}">
                                @else
                                    <div class="h-28 flex items-center justify-center bg-gray-50 text-gray-400 text-xs">
                                        {{ basename($file) }}
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Resolution --}}
            @if($claim->isResolved())
                <div class="card p-5 border-l-4 {{ $claim->status === \App\Enums\CarrierClaimStatus::Rejected ? 'border-red-400' : 'border-green-400' }}">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">{{ __('admin.carriers_section.resolution') }}</h2>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('admin.carriers_section.decision_label') }}</dt>
                            <dd class="font-medium capitalize">{{ $statusLabels[$claim->status->value] ?? Str::title(str_replace('_',' ',$claim->status->value)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('admin.carriers_section.compensated_amount_label') }}</dt>
                            <dd class="font-medium">{{ $claim->compensated_amount_formatted }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('admin.carriers_section.resolved_by_label') }}</dt>
                            <dd>{{ $claim->resolvedBy?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('admin.carriers_section.resolved_at_label') }}</dt>
                            <dd>{{ $claim->resolved_at?->format('d M Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if($claim->resolution_notes)
                        <p class="mt-3 text-sm text-gray-700 leading-relaxed">{{ $claim->resolution_notes }}</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- ─── Actions ──────────────────────────────────────────────────── --}}
        <div class="space-y-4">

            @if(! $claim->isResolved())

                @if($claim->status === \App\Enums\CarrierClaimStatus::Submitted)
                    <form method="POST" action="{{ route('admin.carrier-claims.under-review', $claim) }}">
                        @csrf @method('PATCH')
                        <button class="w-full btn btn-secondary">{{ __('admin.carriers_section.mark_under_review') }}</button>
                    </form>
                @endif

                <div class="card p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.carriers_section.resolve_claim') }}</h3>
                    <form method="POST" action="{{ route('admin.carrier-claims.resolve', $claim) }}">
                        @csrf @method('PATCH')

                        <div class="mb-3">
                            <label class="label">{{ __('admin.carriers_section.decision_required') }}</label>
                            <select name="decision" required class="input w-full">
                                <option value="approved">{{ __('admin.carriers_section.approve') }}</option>
                                <option value="rejected">{{ __('admin.carriers_section.reject') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="label">{{ __('admin.carriers_section.compensation_amount') }}</label>
                            <input type="number" name="compensated_amount" step="0.01" min="0"
                                   placeholder="0.00" class="input w-full"
                                   value="{{ old('compensated_amount') }}">
                            <p class="text-xs text-gray-500 mt-1">{{ __('admin.carriers_section.compensation_hint') }}</p>
                        </div>

                        <div class="mb-4">
                            <label class="label">{{ __('admin.carriers_section.resolution_notes') }}</label>
                            <textarea name="resolution_notes" rows="3" class="input w-full"
                                      placeholder="{{ __('admin.carriers_section.resolution_notes_placeholder') }}">{{ old('resolution_notes') }}</textarea>
                        </div>

                        <button type="submit" class="w-full btn btn-primary">{{ __('admin.carriers_section.submit_decision') }}</button>
                    </form>
                </div>

            @endif

        </div>

    </div>

@endsection
