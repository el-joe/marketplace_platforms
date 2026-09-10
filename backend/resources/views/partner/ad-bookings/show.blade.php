@extends('layouts.partner')
@section('title', $booking->booking_reference)
@section('page-title', $booking->booking_reference)

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
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="text-lg font-bold">{{ $booking->booking_reference }}</div>
                    <div class="text-sm text-gray-500">{{ $booking->slot?->name }}</div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">{{ __('ads.booking_status.'.$booking->status->value) }}</span>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-400">{{ __('partner.ad_bookings.dates') }}:</span> {{ $booking->booked_from?->format('d M Y') }} - {{ $booking->booked_until?->format('d M Y') }}</div>
                <div><span class="text-gray-400">{{ __('partner.ad_bookings.amount') }}:</span> {{ number_format($booking->total_charged ?: ($booking->quoted_amount + $booking->tax_amount)) }} {{ $booking->currency }}</div>
                <div><span class="text-gray-400">{{ __('partner.ad_bookings.payment') }}:</span> {{ __('ads.payment_status.'.$booking->payment_status->value) }} ({{ $booking->payment_method?->value }})</div>
                @if ($booking->payment_due_at)
                    <div><span class="text-gray-400">{{ __('partner.ad_bookings.payment_due') }}:</span> {{ $booking->payment_due_at->format('d M Y H:i') }}</div>
                @endif
            </div>
            @if ($booking->rejection_reason)
                <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ $booking->rejection_reason }}</div>
            @endif
            @if ($booking->cancellation_reason)
                <div class="mt-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">{{ $booking->cancellation_reason }}</div>
            @endif

            <div class="flex items-center gap-3 mt-5">
                @if ($booking->status->value === 'approved' && $booking->payment_status->value === 'unpaid')
                    <button id="btn-pay" class="rounded-lg bg-primary-600 text-white px-4 py-2 text-sm font-medium">{{ __('partner.ad_bookings.pay_now') }}</button>
                @endif
                @if (in_array($booking->status->value, ['draft', 'pending_review', 'approved', 'scheduled']))
                    <button id="btn-cancel" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-medium">{{ __('common.cancel') }}</button>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('partner.ad_bookings.current_creative') }}</h3>
            @php $creative = $booking->creatives->firstWhere('is_current', true); @endphp
            @if ($creative)
                <div class="grid grid-cols-2 gap-3">
                    @foreach (['desktop', 'mobile'] as $device)
                        @php $img = $creative->imagePair($device); @endphp
                        @if ($img['en'])
                            <img src="{{ $img['en'] }}" class="rounded-lg border border-gray-100 w-full">
                        @endif
                    @endforeach
                </div>
                <div class="text-xs text-gray-400 mt-2">v{{ $creative->version }} — {{ __('ads.creative_status.'.$creative->status->value) }}</div>
            @else
                <p class="text-sm text-gray-400">{{ __('partner.ad_bookings.no_creative') }}</p>
            @endif

            <h4 class="text-xs font-semibold text-gray-500 mt-5 mb-2">{{ __('partner.ad_bookings.replace_creative') }}</h4>
            <form id="creative-form" enctype="multipart/form-data" class="space-y-2 text-sm">
                <input type="file" name="desktop_en" accept="image/*" required class="block text-xs">
                <input type="file" name="mobile_en" accept="image/*" required class="block text-xs">
                <input type="hidden" name="destination_type" value="{{ $creative->destination_type ?? 'store' }}">
                <button type="submit" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs">{{ __('partner.ad_bookings.upload') }}</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('partner.ad_bookings.charges') }}</h3>
            <table class="w-full text-xs">
                <thead><tr class="text-gray-400"><th class="text-left py-1">{{ __('common.type') }}</th><th class="text-left py-1">{{ __('partner.ad_bookings.amount') }}</th><th class="text-left py-1">{{ __('partner.ad_bookings.settlement') }}</th><th class="text-left py-1">{{ __('partner.payouts.payout') }}</th></tr></thead>
                <tbody>
                    @forelse ($booking->charges as $charge)
                        <tr class="border-t border-gray-50">
                            <td class="py-1">{{ $charge->type->value }}</td>
                            <td class="py-1">{{ number_format($charge->amount) }} {{ $charge->currency }}</td>
                            <td class="py-1">{{ $charge->settlement }}</td>
                            <td class="py-1">
                                @if ($charge->payout_id)
                                    <a href="{{ route('partner.payouts.show', $charge->payout_id) }}" class="text-primary-600 hover:underline">{{ __('partner.ad_bookings.view_payout') }}</a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-gray-400">{{ __('partner.ad_bookings.no_charges') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('partner.ad_bookings.performance') }}</h3>
            <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                <div><div class="text-gray-400 text-xs">{{ __('partner.ad_bookings.impressions') }}</div><div class="font-semibold">{{ number_format($stats['totals']['impressions']) }}</div></div>
                <div><div class="text-gray-400 text-xs">{{ __('partner.ad_bookings.clicks') }}</div><div class="font-semibold">{{ number_format($stats['totals']['clicks']) }}</div></div>
                <div><div class="text-gray-400 text-xs">CTR</div><div class="font-semibold">{{ $stats['totals']['ctr'] }}%</div></div>
                <div><div class="text-gray-400 text-xs">{{ __('partner.ad_bookings.spend') }}</div><div class="font-semibold">{{ number_format($stats['totals']['spend']) }} {{ $stats['totals']['currency'] }}</div></div>
            </div>
            <canvas id="stats-chart" height="150"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@vite('resources/js/partner/ad-booking-show.js')
@endsection
