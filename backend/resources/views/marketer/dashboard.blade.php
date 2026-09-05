@extends('layouts.marketer')
@section('title', 'الإحصائيات')
@section('page-title', 'الإحصائيات والأداء')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="space-y-6">

    {{-- Welcome banner if pending --}}
    @if((string)$marketer->global_status === 'pending')
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 flex items-start gap-4">
        <div class="text-2xl">⏳</div>
        <div>
            <div class="font-bold text-yellow-900">حسابك قيد المراجعة</div>
            <p class="text-yellow-700 text-sm mt-1">سيتم تفعيل حسابك خلال 24-48 ساعة بعد مراجعة الفريق. ستصلك رسالة على بريدك الإلكتروني عند الموافقة.</p>
        </div>
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">دعوات معلّقة</div>
            <div class="text-3xl font-black text-gray-900">{{ $stats['pendingInvitations'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">حملات نشطة</div>
            <div class="text-3xl font-black text-yellow-500">{{ $stats['activeCampaigns'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">إجمالي التحويلات</div>
            <div class="text-3xl font-black text-green-600">{{ number_format($stats['totalConversions']) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">أرباح محصّلة</div>
            <div class="text-2xl font-black text-gray-900">{{ number_format($stats['totalEarnings']) }}</div>
            <div class="text-xs text-gray-400 mt-0.5">معلّق: {{ number_format($stats['pendingEarnings']) }}</div>
        </div>
    </div>

    {{-- Earnings Chart --}}
    @if($stats['monthlyEarnings']->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-800 mb-4">الأرباح الشهرية (آخر 12 شهراً)</h3>
        <canvas id="earningsChart" height="80"></canvas>
    </div>
    @endif

    {{-- Pending Invitations --}}
    @if($recentInvitations->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-800">دعوات معلّقة تحتاج ردّك</h3>
            <a href="{{ route('marketer.invitations.index') }}" class="text-yellow-600 text-sm hover:underline">عرض الكل</a>
        </div>
        <div class="space-y-3">
            @foreach($recentInvitations as $invitation)
            @php
                $product = $invitation->campaign->vendorListing?->productVariant?->product
                         ?? $invitation->campaign->adminListing?->productVariant?->product;
            @endphp
            <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-100 rounded-lg">
                <div>
                    <div class="font-semibold text-gray-900 text-sm">{{ $product?->name_ar ?? $invitation->campaign->title ?? 'حملة' }}</div>
                    <div class="text-xs text-gray-500">
                        {{ $invitation->campaign->vendor->name ?? 'نون' }} •
                        {{ $invitation->campaign->country->name_ar ?? '' }} •
                        تنتهي خلال {{ $invitation->expires_at?->diffForHumans() ?? 'قريباً' }}
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('marketer.invitations.accept', $invitation) }}">
                        @csrf
                        <button class="px-3 py-1 bg-green-500 text-white text-xs rounded-lg hover:bg-green-600">قبول</button>
                    </form>
                    <form method="POST" action="{{ route('marketer.invitations.reject', $invitation) }}">
                        @csrf
                        <button class="px-3 py-1 bg-gray-200 text-gray-700 text-xs rounded-lg hover:bg-gray-300">رفض</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
@if($stats['monthlyEarnings']->isNotEmpty())
const ctx = document.getElementById('earningsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: @json($stats['monthlyEarnings']->pluck('month')),
        datasets: [{
            label: 'الأرباح',
            data: @json($stats['monthlyEarnings']->pluck('total')),
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
