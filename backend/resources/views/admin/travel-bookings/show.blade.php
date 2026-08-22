@extends('layouts.admin')

@section('title', __('admin.travel.booking_title', ['number' => $travelBooking->booking_number]))

@section('content')
<div class="p-6 space-y-6 max-w-4xl">

    {{-- ─── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.travel.bookings.index') }}" class="text-xs text-gray-400 hover:text-gray-600 mb-1 inline-block">{{ __('admin.travel.back_to_bookings') }}</a>
            <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $travelBooking->booking_number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.travel.booked_at', ['date' => $travelBooking->created_at->format('d M Y H:i')]) }}</p>
        </div>
        @php
        $statusColors = [
            'pending_documents' => 'bg-amber-100 text-amber-700',
            'confirmed'         => 'bg-emerald-100 text-emerald-700',
            'cancelled'         => 'bg-red-100 text-red-700',
            'completed'         => 'bg-gray-100 text-gray-600',
        ];
        @endphp
        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$travelBooking->status->value] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $travelBooking->status->label() }}
        </span>
    </div>

    {{-- ─── Main grid ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Customer --}}
        <x-card>
            <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.customer') }}</h3>
            @php $customer = $travelBooking->customer; @endphp
            @if($customer)
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('common.name') }}</dt><dd class="text-gray-900 font-medium">{{ $customer->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('common.email') }}</dt><dd class="text-gray-900">{{ $customer->email }}</dd></div>
                @if($customer->phone)
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('common.phone') }}</dt><dd class="text-gray-900">{{ $customer->phone }}</dd></div>
                @endif
            </dl>
            @else
                <p class="text-sm text-gray-400">{{ __('admin.travel.customer_not_found') }}</p>
            @endif
        </x-card>

        {{-- Booking Details --}}
        <x-card>
            <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.booking_details') }}</h3>
            @php $package = $travelBooking->package; @endphp
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.travelers') }}</dt><dd class="text-gray-900 font-semibold">{{ $travelBooking->travelers_count }}</dd></div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.travel.total_amount') }}</dt>
                    <dd class="text-gray-900 font-semibold">
                        {{ $package?->currency ?? '' }} {{ number_format($travelBooking->total_price, 2) }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.travel.contract_signed') }}</dt>
                    <dd class="text-gray-900">
                        {{ $travelBooking->contract_signed_at ? $travelBooking->contract_signed_at->format('d M Y H:i') : '—' }}
                    </dd>
                </div>
                @if($travelBooking->passport_file_path)
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.travel.passport') }}</dt>
                    <dd><a href="/storage/{{ $travelBooking->passport_file_path }}" target="_blank" class="text-primary-600 text-xs hover:underline">{{ __('admin.travel.view_file') }}</a></dd>
                </div>
                @endif
            </dl>
        </x-card>

    </div>

    {{-- ─── Package ──────────────────────────────────────────────────────────────── --}}
    @if($package)
    <x-card>
        <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.package') }}</h3>
        <div class="flex items-start gap-4">
            @php $cover = $package->media->where('media_type', 'image')->first(); @endphp
            @if($cover)
            <img src="/storage/{{ $cover->file_path }}" class="w-24 h-24 rounded-lg object-cover border border-gray-200 shrink-0" alt="">
            @endif
            <div class="flex-1">
                <p class="font-semibold text-gray-900">{{ $package->title_en }}</p>
                <p class="text-sm text-gray-500 mt-0.5">{{ $package->agency?->name }}</p>
                <dl class="mt-2 grid grid-cols-2 gap-x-8 gap-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.departure') }}</dt><dd class="text-gray-900">{{ $package->departure_date->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.return') }}</dt><dd class="text-gray-900">{{ $package->return_date->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.duration') }}</dt><dd class="text-gray-900">{{ $package->duration_days }}d / {{ $package->duration_nights }}n</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.price_per_seat') }}</dt><dd class="text-gray-900">{{ $package->priceFormatted() }}</dd></div>
                </dl>
                <div class="mt-3">
                    <a href="{{ route('admin.travel.packages.show', $package->id) }}" class="btn btn-secondary btn-sm">{{ __('admin.travel.view_package') }}</a>
                </div>
            </div>
        </div>
    </x-card>
    @endif

    {{-- ─── Read-only note ──────────────────────────────────────────────────────── --}}
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-700">
        {{ __('admin.travel.readonly_note') }}
    </div>

</div>
@endsection
