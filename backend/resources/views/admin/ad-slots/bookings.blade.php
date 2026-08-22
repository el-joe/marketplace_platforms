@extends('layouts.admin')

@section('title', __('admin.ad_slots.bookings_for', ['name' => $adSlot->name]))

@section('content')

    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.ad-slots.index') }}" class="hover:text-primary-600">{{ __('admin.ad_slots.title') }}</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">{{ $adSlot->name }}</span>
                <span>/</span>
                <span class="text-gray-800 font-medium">{{ __('admin.ad_slots.bookings') }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.ad_slots.bookings') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __('admin.ad_slots.slot') }}: <strong>{{ $adSlot->name }}</strong>
                <span class="font-mono ml-2 text-xs text-gray-400">{{ $adSlot->slot_code }}</span>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.ad-slots.edit', $adSlot->id) }}" class="btn btn-secondary btn-sm">{{ __('admin.ad_slots.edit_slot') }}</a>
            <a href="{{ route('admin.ad-slots.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.ad_slots.all_slots') }}</a>
        </div>
    </div>

    {{-- Slot summary card --}}
    <x-card class="mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.ad_slots.pricing_model') }}</dt>
                <dd class="font-semibold uppercase">{{ $adSlot->pricing_model?->label() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.ad_slots.base_rate') }}</dt>
                <dd class="font-semibold">${{ number_format($adSlot->base_rate ?? 0, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.ad_slots.status') }}</dt>
                <dd>
                    @if($adSlot->is_available)
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">{{ __('admin.ad_slots.available') }}</span>
                    @else
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('admin.ad_slots.unavailable') }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.ad_slots.country') }}</dt>
                <dd>{{ $adSlot->country?->flag_emoji ?? '' }} {{ $adSlot->country?->name_en ?? '—' }}</dd>
            </div>
        </div>
    </x-card>

    {{-- Bookings table --}}
    <x-card title="{{ __('admin.ad_slots.bookings') }}">
        @if($bookings->isEmpty())
            <p class="text-sm text-gray-400 py-6 text-center">{{ __('admin.ad_slots.no_bookings_for_slot') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-start">
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_slots.reference') }}</th>
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_slots.vendor') }}</th>
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_slots.dates') }}</th>
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_slots.rate') }}</th>
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_slots.status') }}</th>
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_slots.payment') }}</th>
                            <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_slots.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($bookings as $booking)
                            @php
                                $statusColors = ['pending' => 'warning', 'active' => 'success', 'rejected' => 'danger', 'cancelled' => 'gray', 'ended' => 'gray'];
                                $sc = $statusColors[$booking->status->value] ?? 'gray';
                                $payColors = ['unpaid' => 'danger', 'paid' => 'success', 'invoiced' => 'warning', 'refunded' => 'gray'];
                                $pc = $payColors[$booking->payment_status ?? 'unpaid'] ?? 'gray';
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 pr-4 font-mono text-xs">{{ $booking->booking_reference }}</td>
                                <td class="py-2 pr-4 font-medium">{{ $booking->vendor?->store_name ?? '—' }}</td>
                                <td class="py-2 pr-4 text-gray-600">
                                    {{ $booking->booked_from ? \Carbon\Carbon::parse($booking->booked_from)->format('d M') : '—' }}
                                    –
                                    {{ $booking->booked_until ? \Carbon\Carbon::parse($booking->booked_until)->format('d M Y') : '—' }}
                                </td>
                                <td class="py-2 pr-4">${{ number_format($booking->agreed_rate, 2) }}</td>
                                <td class="py-2 pr-4">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">
                                        {{ $booking->status->label() }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $pc }}-100 text-{{ $pc }}-700">
                                        {{ ucfirst($booking->payment_status ?? 'unpaid') }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <a href="{{ route('admin.paid-ad-bookings.show', $booking->id) }}"
                                        class="btn btn-xs btn-secondary">{{ __('admin.ad_slots.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        @endif
    </x-card>

@endsection
