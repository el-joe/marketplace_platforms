@extends('layouts.marketer')
@section('title', __('marketer.dashboard.title'))
@section('page-title', __('marketer.dashboard.page_title'))

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="space-y-6">

    {{-- Welcome banner if pending --}}
    @if($marketer->global_status?->value === 'pending')
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 flex items-start gap-4">
        <div class="text-2xl">⏳</div>
        <div>
            <div class="font-bold text-yellow-900">{{ __('marketer.dashboard.account_pending_heading') }}</div>
            <p class="text-yellow-700 text-sm mt-1">{{ __('marketer.dashboard.account_pending_hint') }}</p>
        </div>
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">{{ __('marketer.dashboard.pending_invitations') }}</div>
            <div class="text-3xl font-black text-gray-900">{{ $stats['pendingInvitations'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">{{ __('marketer.dashboard.active_campaigns') }}</div>
            <div class="text-3xl font-black text-yellow-500">{{ $stats['activeCampaigns'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">{{ __('marketer.dashboard.total_conversions') }}</div>
            <div class="text-3xl font-black text-green-600">{{ number_format($stats['totalConversions']) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-gray-500 text-xs mb-1">{{ __('marketer.dashboard.total_earnings') }}</div>
            <div class="text-2xl font-black text-gray-900">{{ number_format($stats['totalEarnings']) }}</div>
            <div class="text-xs text-gray-400 mt-0.5">{{ __('marketer.dashboard.pending_earnings_label') }} {{ number_format($stats['pendingEarnings']) }}</div>
        </div>
    </div>

    {{-- Earnings Chart --}}
    @if(!empty($stats['monthlyEarnings']))
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-800 mb-4">{{ __('marketer.dashboard.monthly_earnings_chart') }}</h3>
        <canvas id="earningsChart" height="80"></canvas>
    </div>
    @endif

    {{-- Pending Invitations --}}
    @if($recentInvitations->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-800">{{ __('marketer.dashboard.pending_invitations_section') }}</h3>
            <a href="{{ route('marketer.invitations.index') }}" class="text-yellow-600 text-sm hover:underline">{{ __('marketer.dashboard.view_all') }}</a>
        </div>
        <div class="space-y-3">
            @foreach($recentInvitations as $invitation)
            @php
                $product = $invitation->campaign->vendorListing?->productVariant?->product
                         ?? $invitation->campaign->adminListing?->productVariant?->product;
            @endphp
            <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-100 rounded-lg">
                <div>
                    <div class="font-semibold text-gray-900 text-sm">{{ $product?->name_ar ?? $invitation->campaign->title ?? __('marketer.invitations.campaign_default_label') }}</div>
                    <div class="text-xs text-gray-500">
                        {{ $invitation->campaign->vendor->name ?? 'ناوي' }} •
                        {{ $invitation->campaign->country->name_ar ?? '' }} •
                        {{ __('marketer.dashboard.expires_in') }} {{ $invitation->expires_at?->diffForHumans() ?? __('marketer.dashboard.soon') }}
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('marketer.invitations.accept', $invitation) }}">
                        @csrf
                        <button class="px-3 py-1 bg-green-500 text-white text-xs rounded-lg hover:bg-green-600">{{ __('marketer.dashboard.accept') }}</button>
                    </form>
                    <form method="POST" action="{{ route('marketer.invitations.reject', $invitation) }}">
                        @csrf
                        <button class="px-3 py-1 bg-gray-200 text-gray-700 text-xs rounded-lg hover:bg-gray-300">{{ __('marketer.dashboard.reject') }}</button>
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
@if(!empty($stats['monthlyEarnings']))
const ctx = document.getElementById('earningsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: @json(array_column($stats['monthlyEarnings'], 'month')),
        datasets: [{
            label: '{{ __('marketer.dashboard.earnings_chart_label') }}',
            data: @json(array_column($stats['monthlyEarnings'], 'total')),
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
