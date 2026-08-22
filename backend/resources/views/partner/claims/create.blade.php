@extends('layouts.partner')

@section('title', __('partner.claims.submit_claim'))

@section('content')

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('partner.claims.index') }}" class="text-gray-400 hover:text-gray-600">&larr; {{ __('partner.claims.claims') }}</a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-900">{{ __('partner.claims.submit_claim') }}</h1>
    </div>

    <div class="max-w-2xl">
        <div class="card p-6">
            <form method="POST" action="{{ route('partner.claims.store') }}">
                @csrf

                <div class="space-y-5">

                    <div>
                        <label class="label" for="shipment_id">{{ __('partner.claims.shipment_label') }}</label>
                        <select name="shipment_id" id="shipment_id" required class="input w-full @error('shipment_id') input-error @enderror">
                            <option value="">{{ __('partner.claims.select_shipment') }}</option>
                            @foreach($shipments as $s)
                                <option value="{{ $s->id }}" @selected(old('shipment_id') === $s->id)>
                                    {{ $s->subOrder?->order?->order_number ?? $s->id }}
                                    @if($s->tracking_number) · {{ $s->tracking_number }} @endif
                                    · {{ Str::title(str_replace('_',' ',$s->status?->value)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('shipment_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="label" for="claim_type">{{ __('partner.claims.claim_type_label') }}</label>
                        <select name="claim_type" id="claim_type" required class="input w-full @error('claim_type') input-error @enderror">
                            <option value="">{{ __('partner.claims.select_type') }}</option>
                            @foreach(['lost' => __('partner.claims.types.lost'), 'damaged' => __('partner.claims.types.damaged'), 'delayed' => __('partner.claims.types.delayed'), 'wrong_item' => __('partner.claims.types.wrong_item'), 'other' => __('partner.claims.types.other')] as $val => $label)
                                <option value="{{ $val }}" @selected(old('claim_type') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('claim_type')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="label" for="description">{{ __('partner.claims.description_label') }}</label>
                        <textarea name="description" id="description" rows="4" required
                                  class="input w-full @error('description') input-error @enderror"
                                  placeholder="{{ __('partner.claims.description_placeholder') }}">{{ old('description') }}</textarea>
                        @error('description')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="label" for="claimed_amount">{{ __('partner.claims.claimed_amount_label') }}</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">{{ auth()->guard('vendor')->user()->vendor?->country?->currency_code ?? '' }}</span>
                            <input type="number" name="claimed_amount" id="claimed_amount" step="0.01" min="0.01" required
                                   class="input w-full pl-12 @error('claimed_amount') input-error @enderror"
                                   value="{{ old('claimed_amount') }}" placeholder="0.00">
                        </div>
                        @error('claimed_amount')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    {{-- Evidence upload note (actual upload handled by existing file upload flow) --}}
                    <div class="bg-blue-50 rounded-lg p-4 text-sm text-blue-700">
                        <strong>{{ __('partner.claims.evidence_note_title') }}</strong> {{ __('partner.claims.evidence_note') }}
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="btn btn-primary">{{ __('partner.claims.submit') }}</button>
                        <a href="{{ route('partner.claims.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
                    </div>

                </div>
            </form>
        </div>
    </div>

@endsection
