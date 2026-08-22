@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/ad-campaigns.js'])
@endpush

@section('title', __('admin.paid_ad_bookings.booking_title', ['ref' => $paidAdBooking->booking_reference]))

@section('content')

    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.paid-ad-bookings.index') }}" class="hover:text-primary-600">{{ __('admin.ad_campaigns.paid_bookings') }}</a>
                <span>/</span>
                <span class="font-mono text-gray-800">{{ $paidAdBooking->booking_reference }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $paidAdBooking->booking_reference }}</h1>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($paidAdBooking->status->value === 'pending')
                <button type="button"
                    class="btn btn-success js-approve-booking-btn"
                    data-url="{{ route('admin.paid-ad-bookings.approve', $paidAdBooking->id) }}"
                    data-ref="{{ e($paidAdBooking->booking_reference) }}">
                    {{ __('admin.paid_ad_bookings.approve_booking_btn') }}
                </button>
                <button type="button"
                    class="btn btn-danger js-reject-booking-btn"
                    data-url="{{ route('admin.paid-ad-bookings.reject', $paidAdBooking->id) }}"
                    data-ref="{{ e($paidAdBooking->booking_reference) }}">
                    {{ __('admin.paid_ad_bookings.reject_booking_btn') }}
                </button>
            @endif
            <a href="{{ route('admin.paid-ad-bookings.index') }}" class="btn btn-secondary">{{ __('admin.paid_ad_bookings.back') }}</a>
        </div>
    </div>

    @if($paidAdBooking->status->value === 'rejected' && $paidAdBooking->rejection_reason)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <strong>{{ __('admin.paid_ad_bookings.rejection_reason_shown') }}</strong> {{ $paidAdBooking->rejection_reason }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- ─── Booking Info ─────────────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-5">
            <x-card title="{{ __('admin.paid_ad_bookings.booking_details') }}">
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-3 text-sm">
                    @php
                        $statusColors = ['pending'=>'warning','active'=>'success','rejected'=>'danger','cancelled'=>'gray','ended'=>'gray'];
                        $sc = $statusColors[$paidAdBooking->status->value] ?? 'gray';
                        $payColors = ['unpaid'=>'danger','paid'=>'success','invoiced'=>'warning','refunded'=>'gray'];
                        $pc = $payColors[$paidAdBooking->payment_status ?? 'unpaid'] ?? 'gray';
                    @endphp
                    <div>
                        <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.status') }}</dt>
                        <dd>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">
                                {{ $paidAdBooking->status->label() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.payment') }}</dt>
                        <dd>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $pc }}-100 text-{{ $pc }}-700">
                                {{ ucfirst($paidAdBooking->payment_status ?? 'unpaid') }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.agreed_rate') }}</dt>
                        <dd class="font-semibold">${{ number_format($paidAdBooking->agreed_rate, 2) }} <span class="text-xs text-gray-400">{{ strtoupper($paidAdBooking->currency ?? 'USD') }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.total_charged') }}</dt>
                        <dd class="font-semibold">
                            {{ $paidAdBooking->total_charged ? '$' . number_format($paidAdBooking->total_charged, 2) : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.booked_from_label') }}</dt>
                        <dd>{{ $paidAdBooking->booked_from ? \Carbon\Carbon::parse($paidAdBooking->booked_from)->format('d M Y') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.booked_until_label') }}</dt>
                        <dd>{{ $paidAdBooking->booked_until ? \Carbon\Carbon::parse($paidAdBooking->booked_until)->format('d M Y') : '—' }}</dd>
                    </div>
                    @if($paidAdBooking->approvedByAdmin)
                        <div>
                            <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.approved_by') }}</dt>
                            <dd>{{ $paidAdBooking->approvedByAdmin->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.approved_at') }}</dt>
                            <dd>{{ $paidAdBooking->approved_at ? \Carbon\Carbon::parse($paidAdBooking->approved_at)->format('d M Y H:i') : '—' }}</dd>
                        </div>
                    @endif
                    @if($paidAdBooking->vendor_notes)
                        <div class="col-span-full">
                            <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.paid_ad_bookings.vendor_notes') }}</dt>
                            <dd class="text-gray-700">{{ $paidAdBooking->vendor_notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            {{-- ─── Creatives ──────────────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.paid_ad_bookings.ad_creatives') }}">
                @if($paidAdBooking->creatives->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">{{ __('admin.paid_ad_bookings.no_creatives') }}</p>
                @else
                    <div class="space-y-4">
                        @foreach($paidAdBooking->creatives as $creative)
                            @php
                                $crColors = ['pending_review'=>'warning','approved'=>'success','rejected'=>'danger','draft'=>'gray'];
                                $cc = $crColors[$creative->status->value] ?? 'gray';
                            @endphp
                            <div class="border border-gray-100 rounded-lg p-4">
                                <div class="flex items-start justify-between gap-4 flex-wrap">
                                    <div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $cc }}-100 text-{{ $cc }}-700">
                                                {{ $creative->status->label() }}
                                            </span>
                                            @if($creative->is_current)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700">{{ __('admin.paid_ad_bookings.current') }}</span>
                                            @endif
                                        </div>
                                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                            <div>
                                                <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.creative_title') }}</dt>
                                                <dd class="font-medium">{{ $creative->title ?? '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.creative_type') }}</dt>
                                                <dd>{{ ucfirst($creative->creative_type ?? '—') }}</dd>
                                            </div>
                                            @if($creative->headline)
                                                <div class="col-span-2">
                                                    <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.headline') }}</dt>
                                                    <dd>{{ $creative->headline }}</dd>
                                                </div>
                                            @endif
                                            @if($creative->description)
                                                <div class="col-span-2">
                                                    <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.description') }}</dt>
                                                    <dd class="text-gray-600">{{ $creative->description }}</dd>
                                                </div>
                                            @endif
                                            @if($creative->cta_text)
                                                <div>
                                                    <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.cta_text') }}</dt>
                                                    <dd>{{ $creative->cta_text }}</dd>
                                                </div>
                                            @endif
                                            @if($creative->destination_url)
                                                <div class="col-span-2">
                                                    <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.destination_url') }}</dt>
                                                    <dd class="break-all text-primary-600 text-xs">{{ $creative->destination_url }}</dd>
                                                </div>
                                            @endif
                                            @if($creative->rejection_reason)
                                                <div class="col-span-2">
                                                    <dt class="text-xs text-red-400">{{ __('admin.paid_ad_bookings.rejection_reason') }}</dt>
                                                    <dd class="text-red-700 text-sm">{{ $creative->rejection_reason }}</dd>
                                                </div>
                                            @endif
                                            @if($creative->reviewedByAdmin)
                                                <div class="col-span-2 text-xs text-gray-400 mt-1">
                                                    {{ __('admin.paid_ad_bookings.reviewed_by', ['name' => $creative->reviewedByAdmin->name, 'date' => \Carbon\Carbon::parse($creative->reviewed_at)->format('d M Y H:i')]) }}
                                                </div>
                                            @endif
                                        </dl>
                                    </div>

                                    {{-- Review actions --}}
                                    @if($creative->status->value === 'pending_review')
                                        <div class="flex gap-2 flex-shrink-0">
                                            <button type="button"
                                                class="btn btn-success btn-sm js-approve-creative-btn"
                                                data-url="{{ route('admin.paid-ad-bookings.creatives.review', $creative->id) }}"
                                                data-id="{{ $creative->id }}">
                                                {{ __('admin.paid_ad_bookings.approve_creative') }}
                                            </button>
                                            <button type="button"
                                                class="btn btn-danger btn-sm js-reject-creative-btn"
                                                data-url="{{ route('admin.paid-ad-bookings.creatives.review', $creative->id) }}"
                                                data-id="{{ $creative->id }}">
                                                {{ __('admin.paid_ad_bookings.reject_creative') }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        {{-- ─── Sidebar: Slot & Vendor ───────────────────────────────────────────── --}}
        <div class="space-y-5">
            <x-card title="{{ __('admin.paid_ad_bookings.ad_slot') }}">
                @if($paidAdBooking->slot)
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.slot_name') }}</dt>
                            <dd class="font-medium">{{ $paidAdBooking->slot->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.slot_code') }}</dt>
                            <dd class="font-mono text-xs">{{ $paidAdBooking->slot->slot_code }}</dd>
                        </div>
                        @if($paidAdBooking->slot->placementDefinition)
                            <div>
                                <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.placement') }}</dt>
                                <dd>{{ $paidAdBooking->slot->placementDefinition->name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.pricing_model') }}</dt>
                            <dd class="uppercase">{{ $paidAdBooking->slot->pricing_model?->value ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.base_rate') }}</dt>
                            <dd>${{ number_format($paidAdBooking->slot->base_rate ?? 0, 2) }}</dd>
                        </div>
                        <div class="pt-2">
                            <a href="{{ route('admin.ad-slots.index') }}" class="text-xs text-primary-600 hover:underline">{{ __('admin.paid_ad_bookings.view_all_slots') }}</a>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-gray-400">{{ __('admin.paid_ad_bookings.slot_not_found') }}</p>
                @endif
            </x-card>

            <x-card title="{{ __('admin.paid_ad_bookings.vendor_card') }}">
                @if($paidAdBooking->vendor)
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.store_name') }}</dt>
                            <dd class="font-medium">{{ $paidAdBooking->vendor->store_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">{{ __('admin.paid_ad_bookings.country') }}</dt>
                            <dd>{{ $paidAdBooking->country?->name_en ?? '—' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-gray-400">{{ __('admin.paid_ad_bookings.vendor_not_found') }}</p>
                @endif
            </x-card>
        </div>
    </div>

    {{-- ─── Modals ──────────────────────────────────────────────────────────────── --}}
    <x-modal id="approve-booking-modal" title="{{ __('admin.paid_ad_bookings.approve_booking_title') }}" size="sm">
        <p class="text-sm text-gray-600">{{ __('admin.paid_ad_bookings.approve') }} <strong id="approve-booking-ref" class="font-mono"></strong>? {{ __('admin.paid_ad_bookings.booking_will_become_active') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#approve-booking-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-approve-booking-btn" class="btn btn-success">{{ __('admin.paid_ad_bookings.approve') }}</button>
        </div>
    </x-modal>

    <x-modal id="reject-booking-modal" title="{{ __('admin.paid_ad_bookings.reject_booking_title') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">{{ __('admin.paid_ad_bookings.reject') }} <strong id="reject-booking-ref" class="font-mono"></strong>.</p>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.paid_ad_bookings.rejection_reason') }} <span class="text-red-500">*</span></label>
        <textarea id="reject-booking-reason" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.paid_ad_bookings.reject_booking_placeholder') }}"></textarea>
        <p class="text-xs text-red-500 hidden mt-1" id="reject-booking-reason-error">{{ __('admin.paid_ad_bookings.reason_required') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#reject-booking-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-reject-booking-btn" class="btn btn-danger">{{ __('admin.paid_ad_bookings.reject') }}</button>
        </div>
    </x-modal>

    <x-modal id="reject-creative-modal" title="{{ __('admin.paid_ad_bookings.reject_creative_title') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">{{ __('admin.paid_ad_bookings.reject_creative_desc') }}</p>
        <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.paid_ad_bookings.rejection_code') }}</label>
            <select id="creative-rejection-code" class="form-input w-full text-sm">
                <option value="">{{ __('admin.paid_ad_bookings.select_code_optional') }}</option>
                <option value="prohibited_content">{{ __('admin.paid_ad_bookings.code_prohibited_content') }}</option>
                <option value="misleading">{{ __('admin.paid_ad_bookings.code_misleading') }}</option>
                <option value="low_quality">{{ __('admin.paid_ad_bookings.code_low_quality') }}</option>
                <option value="wrong_dimensions">{{ __('admin.paid_ad_bookings.code_wrong_dimensions') }}</option>
                <option value="trademark">{{ __('admin.paid_ad_bookings.code_trademark') }}</option>
                <option value="other">{{ __('admin.paid_ad_bookings.code_other') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.paid_ad_bookings.rejection_reason') }} <span class="text-red-500">*</span></label>
            <textarea id="creative-rejection-reason" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.marketers.reject_campaign_reason_placeholder') }}"></textarea>
            <p class="text-xs text-red-500 hidden mt-1" id="creative-rejection-reason-error">{{ __('admin.paid_ad_bookings.reason_required') }}</p>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#reject-creative-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-reject-creative-btn" class="btn btn-danger">{{ __('admin.paid_ad_bookings.reject_creative_btn') }}</button>
        </div>
    </x-modal>

@endsection
