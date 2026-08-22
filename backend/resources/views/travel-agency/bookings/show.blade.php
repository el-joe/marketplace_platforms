@extends('layouts.travel-agency')

@section('title', __('travel.bookings.booking') . ' ' . $booking->booking_number)

@section('content')
@php
$statusColors = ['pending_documents'=>'bg-amber-100 text-amber-700','confirmed'=>'bg-emerald-100 text-emerald-700','cancelled'=>'bg-red-100 text-red-700','completed'=>'bg-blue-100 text-blue-700'];
$statusLabels = [
    'pending_documents' => __('travel.bookings.status_pending_documents'),
    'confirmed' => __('travel.bookings.status_confirmed'),
    'cancelled' => __('travel.bookings.status_cancelled'),
    'completed' => __('travel.bookings.status_completed')
];
@endphp

<div class="space-y-6 max-w-3xl">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('travel-agency.bookings.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-black text-gray-900">{{ $booking->booking_number }}</h1>
            <span class="px-2.5 py-0.5 rounded-full text-sm font-medium {{ $statusColors[$booking->status->value] ?? '' }}">
                {{ $statusLabels[$booking->status->value] ?? $booking->status->label() }}
            </span>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        {{-- Package details --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
            <h2 class="font-bold text-gray-800 border-b border-gray-100 pb-2">{{ __('travel.bookings.package_details') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.bookings.package') }}</dt>
                    <dd class="font-medium text-gray-900">
                        <a href="{{ route('travel-agency.packages.show', $booking->package) }}" class="text-blue-600 hover:underline">
                            {{ $booking->package->title_ar ?: $booking->package->title_en }}
                        </a>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.packages.destination') }}</dt>
                    <dd class="text-gray-700">{{ $booking->package->destination_country }}{{ $booking->package->destination_city ? '، '.$booking->package->destination_city : '' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.packages.departure_date') }}</dt>
                    <dd class="text-gray-700">{{ $booking->package->departure_date->format('d M Y') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.packages.return_date') }}</dt>
                    <dd class="text-gray-700">{{ $booking->package->return_date->format('d M Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Customer details --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
            <h2 class="font-bold text-gray-800 border-b border-gray-100 pb-2">{{ __('travel.bookings.customer_data') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.bookings.name') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $booking->customer->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.bookings.email') }}</dt>
                    <dd class="text-gray-700">{{ $booking->customer->email ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.bookings.travelers_count') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $booking->travelers_count }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.bookings.total_payment') }}</dt>
                    <dd class="font-bold text-gray-900">{{ $booking->totalFormatted() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('travel.bookings.phone') }}</dt>
                    <dd class="text-gray-700">
                        @if($booking->customer?->phone)
                            <a href="tel:{{ $booking->customer->phone }}" class="text-blue-600 hover:underline">{{ $booking->customer->phone }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    @if($booking->status === \App\Enums\TravelBookingStatus::Cancelled && $booking->cancellation_reason)
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
        <span class="font-semibold">{{ __('travel.bookings.cancellation_reason') }}:</span> {{ $booking->cancellation_reason }}
    </div>
    @endif

    {{-- Documents --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
        <h2 class="font-bold text-gray-800 border-b border-gray-100 pb-2">{{ __('travel.bookings.documents_contract') }}</h2>
        <div class="grid md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500 mb-1">{{ __('travel.bookings.passport') }}</p>
                @if($booking->passport_file_path)
                    <a href="{{ asset('storage/'.$booking->passport_file_path) }}" target="_blank"
                       class="inline-flex items-center gap-1 text-blue-600 hover:underline font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        {{ __('travel.bookings.download_passport') }}
                    </a>
                @else
                    <span class="text-gray-400">{{ __('travel.bookings.not_uploaded_yet') }}</span>
                @endif
            </div>
            <div>
                <p class="text-gray-500 mb-1">{{ __('travel.bookings.contract') }}</p>
                @if($booking->contract_signed_at)
                    <p class="text-emerald-600 font-medium">{{ __('travel.bookings.signed') }} — {{ $booking->contract_signed_at->format('d M Y') }}</p>
                @else
                    <span class="text-gray-400">{{ __('travel.bookings.not_signed_yet') }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Status actions --}}
    @if(in_array($booking->status, [\App\Enums\TravelBookingStatus::PendingDocuments, \App\Enums\TravelBookingStatus::Confirmed]))
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-bold text-gray-800 mb-4">{{ __('travel.bookings.booking_actions') }}</h2>
        <div class="flex gap-3 flex-wrap">
            @if($booking->status === \App\Enums\TravelBookingStatus::PendingDocuments)
            <form method="POST" action="{{ route('travel-agency.bookings.status', $booking) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="confirmed">
                <button type="submit"
                        class="px-5 py-2.5 bg-emerald-500 text-white rounded-xl font-bold hover:bg-emerald-400 transition-colors"
                        onclick="return confirm('{{ __('travel.bookings.confirm_booking_prompt') }}')">
                    ✓ {{ __('travel.bookings.confirm_booking') }}
                </button>
            </form>
            @endif
            <div x-data="{ open: false, reason: '' }">
                <button type="button" @click="open = true"
                        class="px-5 py-2.5 bg-red-500 text-white rounded-xl font-bold hover:bg-red-400 transition-colors">
                    ✗ {{ __('travel.bookings.cancel_booking') }}
                </button>
                <div x-show="open" x-cloak
                     class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="open = false">
                    <form method="POST" action="{{ route('travel-agency.bookings.status', $booking) }}"
                          class="bg-white rounded-xl p-6 w-full max-w-sm space-y-3">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <h3 class="font-bold text-gray-900">{{ __('travel.bookings.cancel_booking_prompt') }}</h3>
                        <textarea name="cancellation_reason" x-model="reason" required rows="3"
                                  class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                                  placeholder="{{ __('travel.bookings.cancellation_reason') }}"></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">{{ __('travel.bookings.close') }}</button>
                            <button type="submit" :disabled="reason.length === 0"
                                    class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-bold hover:bg-red-400 disabled:opacity-50">
                                {{ __('travel.bookings.cancel_booking') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @error('status')
        <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    @endif

    <div class="text-xs text-gray-400">
        {{ __('travel.bookings.booking_date') }}: {{ $booking->created_at->format('d M Y H:i') }}
    </div>
</div>
@endsection
