@extends('layouts.marketer')
@section('title', 'التقارير')
@section('page-title', 'التقارير والعمولات')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="space-y-6">

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">إجمالي التحويلات</div>
            <div class="text-3xl font-black text-gray-900">{{ number_format($stats['total_conversions']) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">حملات مشارك بها</div>
            <div class="text-3xl font-black text-yellow-500">{{ number_format($stats['total_campaigns']) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">أرباح محصّلة</div>
            <div class="text-2xl font-black text-green-600">{{ number_format($stats['total_earnings']) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">أرباح معلّقة</div>
            <div class="text-2xl font-black text-yellow-600">{{ number_format($stats['pending_earnings']) }}</div>
        </div>
    </div>

    {{-- Monthly Earnings Chart --}}
    @if($monthlyEarnings->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-800 mb-4">الأرباح الشهرية (آخر 12 شهراً)</h3>
        <canvas id="reportsEarningsChart" height="80"></canvas>
    </div>
    @endif

    {{-- Per-campaign breakdown --}}
    @if($campaignBreakdown->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-800 mb-4">الأداء حسب الحملة</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100">
                        <th class="text-start py-2">الحملة</th>
                        <th class="text-start py-2">الدولة</th>
                        <th class="text-start py-2">التحويلات</th>
                        <th class="text-start py-2">العمولة المكتسبة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($campaignBreakdown as $inv)
                    @php
                        $product = $inv->campaign->vendorListing?->productVariant?->product
                                 ?? $inv->campaign->adminListing?->productVariant?->product;
                    @endphp
                    <tr class="border-b border-gray-50">
                        <td class="py-2 font-semibold text-gray-800">{{ $product?->name_ar ?? $inv->campaign->title ?? 'حملة' }}</td>
                        <td class="py-2 text-gray-500">{{ $inv->campaign->country->name_ar ?? '' }}</td>
                        <td class="py-2">{{ number_format($inv->conversions_count ?? 0) }}</td>
                        <td class="py-2 text-green-600 font-semibold">
                            {{ number_format($inv->conversions_sum_commission_amount ?? 0) }} {{ $inv->campaign->currency }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Conversion history --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-800 mb-4">سجل التحويلات</h3>

        @if($conversions->isEmpty())
            <div class="text-center py-10">
                <div class="text-4xl mb-2">📊</div>
                <p class="text-gray-400 text-sm">لا توجد تحويلات بعد</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100">
                            <th class="text-start py-2">التاريخ</th>
                            <th class="text-start py-2">الحملة</th>
                            <th class="text-start py-2">الطلب</th>
                            <th class="text-start py-2">العمولة</th>
                            <th class="text-start py-2">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($conversions as $conversion)
                        @php
                            $product = $conversion->campaign->vendorListing?->productVariant?->product
                                     ?? $conversion->campaign->adminListing?->productVariant?->product;
                        @endphp
                        <tr class="border-b border-gray-50">
                            <td class="py-2 text-gray-500">{{ $conversion->created_at->format('Y-m-d') }}</td>
                            <td class="py-2 font-semibold text-gray-800">{{ $product?->name_ar ?? $conversion->campaign->title ?? 'حملة' }}</td>
                            <td class="py-2 text-gray-500">#{{ $conversion->order->id ?? '-' }}</td>
                            <td class="py-2 text-green-600 font-semibold">
                                {{ number_format($conversion->commission_amount) }} {{ $conversion->currency }}
                            </td>
                            <td class="py-2">
                                @if($conversion->commissioned)
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded">مدفوعة</span>
                                @else
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded">معلّقة</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $conversions->links() }}</div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
@if($monthlyEarnings->isNotEmpty())
const ctx = document.getElementById('reportsEarningsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: @json($monthlyEarnings->pluck('month')),
        datasets: [{
            label: 'الأرباح',
            data: @json($monthlyEarnings->pluck('total')),
            backgroundColor: 'rgba(234, 179, 8, 0.7)',
            borderColor: 'rgb(234, 179, 8)',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
@endif
</script>
@endpush
