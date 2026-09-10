@extends('layouts.marketer')
@section('title', $booking->booking_reference)
@section('page-title', $booking->booking_reference)

@push('scripts')
<script>
    window.AD_BOOKING_CONFIG = {
        id: "{{ $booking->id }}",
        payUrl: "{{ route('marketer.promote.bookings.pay', $booking->id) }}",
        cancelUrl: "{{ route('marketer.promote.bookings.cancel', $booking->id) }}",
        creativeUrl: "{{ route('marketer.promote.bookings.creative', $booking->id) }}",
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
                <div><span class="text-gray-400">التواريخ:</span> {{ $booking->booked_from?->format('d M Y') }} - {{ $booking->booked_until?->format('d M Y') }}</div>
                <div><span class="text-gray-400">المبلغ:</span> {{ number_format($booking->total_charged ?: ($booking->quoted_amount + $booking->tax_amount)) }} {{ $booking->currency }}</div>
                <div><span class="text-gray-400">الدفع:</span> {{ __('ads.payment_status.'.$booking->payment_status->value) }} (محفظة)</div>
                @if ($booking->payment_due_at)
                    <div><span class="text-gray-400">استحقاق الدفع:</span> {{ $booking->payment_due_at->format('d M Y H:i') }}</div>
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
                    <button id="btn-pay" class="rounded-lg bg-primary-600 text-white px-4 py-2 text-sm font-medium">ادفع الآن</button>
                @endif
                @if (in_array($booking->status->value, ['draft', 'pending_review', 'approved', 'scheduled']))
                    <button id="btn-cancel" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-medium">إلغاء</button>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">التصميم الحالي</h3>
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
                <div class="text-xs text-gray-400 mt-2">إصدار {{ $creative->version }} — {{ __('ads.creative_status.'.$creative->status->value) }}</div>
            @else
                <p class="text-sm text-gray-400">لم يتم رفع تصميم بعد.</p>
            @endif

            <h4 class="text-xs font-semibold text-gray-500 mt-5 mb-2">استبدال التصميم</h4>
            <form id="creative-form" enctype="multipart/form-data" class="space-y-2 text-sm">
                <input type="file" name="desktop_en" accept="image/*" required class="block text-xs">
                <input type="file" name="mobile_en" accept="image/*" required class="block text-xs">
                <input type="hidden" name="destination_type" value="{{ $creative->destination_type ?? 'marketer_profile' }}">
                <button type="submit" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs">رفع</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">الرسوم</h3>
            <table class="w-full text-xs">
                <thead><tr class="text-gray-400"><th class="text-left py-1">النوع</th><th class="text-left py-1">المبلغ</th><th class="text-left py-1">التسوية</th></tr></thead>
                <tbody>
                    @forelse ($booking->charges as $charge)
                        <tr class="border-t border-gray-50">
                            <td class="py-1">{{ $charge->type->value }}</td>
                            <td class="py-1">{{ number_format($charge->amount) }} {{ $charge->currency }}</td>
                            <td class="py-1">{{ $charge->settlement }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-gray-400">لا توجد رسوم بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">الأداء</h3>
            <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                <div><div class="text-gray-400 text-xs">مرات الظهور</div><div class="font-semibold">{{ number_format($stats['totals']['impressions']) }}</div></div>
                <div><div class="text-gray-400 text-xs">النقرات</div><div class="font-semibold">{{ number_format($stats['totals']['clicks']) }}</div></div>
                <div><div class="text-gray-400 text-xs">CTR</div><div class="font-semibold">{{ $stats['totals']['ctr'] }}%</div></div>
                <div><div class="text-gray-400 text-xs">الإنفاق</div><div class="font-semibold">{{ number_format($stats['totals']['spend']) }} {{ $stats['totals']['currency'] }}</div></div>
            </div>
            <canvas id="stats-chart" height="150"></canvas>
        </div>

        @if ($attribution)
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">الإحالة والعمولة</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><div class="text-gray-400 text-xs">الطلبات المحالة</div><div class="font-semibold">{{ $attribution['conversions_count'] }}</div></div>
                    <div><div class="text-gray-400 text-xs">العمولة المكتسبة</div><div class="font-semibold">{{ number_format($attribution['commission_earned']) }} {{ $attribution['currency'] }}</div></div>
                    <div><div class="text-gray-400 text-xs">تكلفة الإعلان</div><div class="font-semibold">{{ number_format($attribution['ad_spend']) }} {{ $attribution['currency'] }}</div></div>
                    <div>
                        <div class="text-gray-400 text-xs">العائد على الإعلان (ROI)</div>
                        <div class="font-semibold {{ ($attribution['roi_percent'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $attribution['roi_percent'] !== null ? $attribution['roi_percent'].'%' : '-' }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@vite('resources/js/marketer/promote-booking-show.js')
@endsection
