@extends('layouts.partner')
@section('title', $booking->booking_reference)
@section('page-title', __('partner.ad_bookings.ad_details'))

@push('scripts')
<script>
    window.AD_BOOKING_CONFIG = {
        id: "{{ $booking->id }}",
        payUrl: "{{ route('partner.ad-bookings.pay', $booking->id) }}",
        cancelUrl: "{{ route('partner.ad-bookings.cancel', $booking->id) }}",
        creativeUrl: "{{ route('partner.ad-bookings.creative', $booking->id) }}",
        stats: @json($stats),
    };
</script>
@endpush

@section('content')
@php
    $statusColors = [
        'draft' => 'bg-gray-100 text-gray-600',
        'pending_review' => 'bg-yellow-100 text-yellow-700',
        'approved' => 'bg-blue-100 text-blue-700',
        'scheduled' => 'bg-indigo-100 text-indigo-700',
        'active' => 'bg-green-100 text-green-700',
        'paused' => 'bg-orange-100 text-orange-700',
        'completed' => 'bg-gray-100 text-gray-600',
        'rejected' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'expired' => 'bg-gray-100 text-gray-500',
    ];
    $paymentColors = [
        'unpaid' => 'bg-red-100 text-red-700',
        'paid' => 'bg-green-100 text-green-700',
        'reserved' => 'bg-blue-100 text-blue-700',
        'refunded' => 'bg-gray-100 text-gray-600',
        'partially_refunded' => 'bg-orange-100 text-orange-700',
    ];
    $creativeColors = [
        'draft' => 'bg-gray-100 text-gray-600',
        'pending_review' => 'bg-yellow-100 text-yellow-700',
        'approved' => 'bg-green-100 text-green-700',
        'rejected' => 'bg-red-100 text-red-700',
    ];
    $statusColor = $statusColors[$booking->status->value] ?? 'bg-gray-100 text-gray-600';
    $paymentColor = $paymentColors[$booking->payment_status->value] ?? 'bg-gray-100 text-gray-600';
@endphp

<div class="px-4 py-6 sm:px-6 lg:px-8">

    {{-- Breadcrumb --}}
    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('partner.ad-bookings.index') }}" class="hover:text-gray-700">{{ __('partner.ad_bookings.breadcrumb') }}</a>
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-gray-800 font-medium font-mono">{{ $booking->booking_reference }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            {{-- Summary card --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
                    <div class="min-w-0">
                        <h1 class="text-lg font-bold text-gray-900 font-mono">{{ $booking->booking_reference }}</h1>
                        <div class="text-sm text-gray-500 mt-0.5">{{ $booking->slot?->name }}</div>
                    </div>
                    <span class="shrink-0 px-3 py-1 rounded-full text-xs font-semibold {{ $statusColor }}">
                        {{ __('ads.booking_status.'.$booking->status->value) }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.ad_bookings.dates') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $booking->booked_from?->format('d M Y') }} – {{ $booking->booked_until?->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.ad_bookings.subscription_fee') }}</dt>
                        <dd class="font-semibold text-gray-900">{{ number_format($booking->subscription_charged) }} {{ $booking->currency }}</dd>
                    </div>
                    @if(! \App\Enums\PaidAdSlotPricingModel::from($booking->pricing_model)->isFixed())
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.ad_bookings.usage_spend') }}</dt>
                        <dd class="font-semibold text-gray-900">{{ number_format($booking->total_charged) }} {{ $booking->currency }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.ad_bookings.total_spend') }}</dt>
                        <dd class="font-semibold text-gray-900">{{ number_format($booking->total_spend) }} {{ $booking->currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.ad_bookings.payment') }}</dt>
                        <dd class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $paymentColor }}">{{ __('ads.payment_status.'.$booking->payment_status->value) }}</span>
                            @if ($booking->payment_method)<span class="text-gray-500 text-xs">({{ __('ads.payment_method.'.$booking->payment_method->value) }})</span>@endif
                        </dd>
                    </div>
                    @if ($booking->payment_due_at)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.ad_bookings.payment_due') }}</dt>
                            <dd class="font-medium text-gray-800">{{ $booking->payment_due_at->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($booking->rejection_reason)
                    <div class="mt-5 flex gap-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                        <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86l-8.18 14.14A1 1 0 003 19.5h18a1 1 0 00.89-1.5L13.71 3.86a1 1 0 00-1.72 0z" /></svg>
                        <div class="text-sm">
                            <div class="font-medium text-red-800">{{ __('partner.ad_bookings.rejection_reason') }}</div>
                            <div class="text-red-700">{{ $booking->rejection_reason }}</div>
                        </div>
                    </div>
                @endif
                @if ($booking->cancellation_reason)
                    <div class="mt-5 flex gap-3 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
                        <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 21a9 9 0 100-18 9 9 0 000 18z" /></svg>
                        <div class="text-sm">
                            <div class="font-medium text-gray-700">{{ __('partner.ad_bookings.cancellation_reason') }}</div>
                            <div class="text-gray-600">{{ $booking->cancellation_reason }}</div>
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-3 mt-6 pt-5 border-t border-gray-100">
                    @if ($booking->status->value === 'approved' && $booking->payment_status->value === 'unpaid')
                        <button id="btn-pay" class="rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 text-sm font-medium transition-colors">{{ __('partner.ad_bookings.pay_now') }}</button>
                    @endif
                    @if (in_array($booking->status->value, ['draft', 'pending_review', 'approved', 'scheduled']))
                        <button id="btn-cancel" class="rounded-lg border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2 text-sm font-medium transition-colors">{{ __('common.cancel') }}</button>
                    @endif
                </div>
            </div>

            {{-- Creative card --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('partner.ad_bookings.current_creative') }}</h3>
                    @php $creative = $booking->creatives->firstWhere('is_current', true); @endphp
                    @if ($creative)
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $creativeColors[$creative->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                            v{{ $creative->version }} — {{ __('ads.creative_status.'.$creative->status->value) }}
                        </span>
                    @endif
                </div>

                @if ($creative)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach (['desktop' => __('partner.ad_bookings.desktop'), 'mobile' => __('partner.ad_bookings.mobile')] as $device => $label)
                            @php $img = $creative->imagePair($device); @endphp
                            @if ($img['en'])
                                <div>
                                    <div class="text-xs text-gray-400 mb-1.5">{{ $label }}</div>
                                    <img src="{{ $img['en'] }}" class="rounded-lg border border-gray-200 w-full object-cover">
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center justify-center rounded-lg border border-dashed border-gray-200 py-8">
                        <p class="text-sm text-gray-400">{{ __('partner.ad_bookings.no_creative') }}</p>
                    </div>
                @endif

                <div class="mt-6 pt-5 border-t border-gray-100">
                    <h4 class="text-xs font-semibold text-gray-700 mb-1">{{ __('partner.ad_bookings.replace_creative') }}</h4>
                    <p class="text-xs text-gray-400 mb-3">{{ __('partner.ad_bookings.creative_hint') }}</p>
                    <form id="creative-form" enctype="multipart/form-data" class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 mb-1">{{ __('partner.ad_bookings.desktop') }}</label>
                            <input type="file" name="desktop_en" accept="image/*" required
                                class="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 mb-1">{{ __('partner.ad_bookings.mobile') }}</label>
                            <input type="file" name="mobile_en" accept="image/*" required
                                class="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        </div>
                        <input type="hidden" name="destination_type" value="{{ $creative->destination_type ?? 'store' }}">
                        <button type="submit" class="rounded-lg border border-gray-200 hover:bg-gray-50 px-4 py-2 text-xs font-medium text-gray-700 transition-colors shrink-0">{{ __('partner.ad_bookings.upload') }}</button>
                    </form>
                </div>
            </div>

            {{-- Charges card --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('partner.ad_bookings.charges') }}</h3>
                <div class="overflow-x-auto -mx-6 px-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide border-b border-gray-100">
                                <th class="text-start py-2 font-medium">{{ __('common.type') }}</th>
                                <th class="text-start py-2 font-medium">{{ __('partner.ad_bookings.amount') }}</th>
                                <th class="text-start py-2 font-medium">{{ __('partner.ad_bookings.settlement') }}</th>
                                <th class="text-start py-2 font-medium">{{ __('partner.ad_bookings.payout') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($booking->charges as $charge)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="py-3 text-gray-700 capitalize">{{ $charge->type->value }}</td>
                                    <td class="py-3 font-medium text-gray-900">{{ number_format($charge->amount) }} {{ $charge->currency }}</td>
                                    <td class="py-3 text-gray-600 capitalize">{{ str_replace('_', ' ', $charge->settlement) }}</td>
                                    <td class="py-3">
                                        @if ($charge->payout_id)
                                            <a href="{{ route('partner.payouts.show', $charge->payout_id) }}" class="text-primary-600 hover:underline font-medium">{{ __('partner.ad_bookings.view_payout') }}</a>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-gray-400">{{ __('partner.ad_bookings.no_charges') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Performance sidebar --}}
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('partner.ad_bookings.performance') }}</h3>
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div class="rounded-xl bg-gray-50 p-3">
                        <div class="text-xs text-gray-400 mb-1">{{ __('partner.ad_bookings.impressions') }}</div>
                        <div class="text-lg font-bold text-gray-900">{{ number_format($stats['totals']['impressions']) }}</div>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-3">
                        <div class="text-xs text-gray-400 mb-1">{{ __('partner.ad_bookings.clicks') }}</div>
                        <div class="text-lg font-bold text-gray-900">{{ number_format($stats['totals']['clicks']) }}</div>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-3">
                        <div class="text-xs text-gray-400 mb-1">{{ __('partner.ad_bookings.ctr') }}</div>
                        <div class="text-lg font-bold text-gray-900">{{ $stats['totals']['ctr'] }}%</div>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-3">
                        <div class="text-xs text-gray-400 mb-1">{{ __('partner.ad_bookings.spend') }}</div>
                        <div class="text-lg font-bold text-gray-900">{{ number_format($stats['totals']['spend']) }} {{ $stats['totals']['currency'] }}</div>
                    </div>
                </div>
                <canvas id="stats-chart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@vite('resources/js/partner/ad-booking-show.js')
@endsection
