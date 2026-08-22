@extends('layouts.carrier')
@section('title', __('carrier.reports.performance_title'))
@section('content')

<div class="mb-4 flex items-center gap-1 border-b border-gray-200">
    <a href="{{ route('carrier.reports.orders') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.orders*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_orders') }}
    </a>
    <a href="{{ route('carrier.reports.earnings') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.earnings*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_earnings') }}
    </a>
    <a href="{{ route('carrier.reports.payouts') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.payouts*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_payouts') }}
    </a>
    <a href="{{ route('carrier.reports.cod-settlements') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.cod-settlements*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_cod_settlements') }}
    </a>
    <a href="{{ route('carrier.reports.performance') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.performance*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_performance') }}
    </a>
    <a href="{{ route('carrier.reports.claims') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.claims*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_claims') }}
    </a>
</div>

<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.reports.performance_title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ $company->name }}</p>
    </div>
    {{-- Period tabs --}}
    <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
        @foreach(['week' => __('carrier.reports.period_week'), 'month' => __('carrier.reports.period_month'), 'quarter' => __('carrier.reports.period_quarter'), 'year' => __('carrier.reports.period_year')] as $val => $label)
        <a href="{{ route('carrier.reports.performance', ['period' => $val]) }}"
           class="px-3 py-1.5 text-xs font-semibold rounded-md transition
                  {{ $period === $val ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>
</div>

{{-- Top-level scorecard --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
        <div class="text-3xl font-black {{ $scorecard['avg_rating'] >= 4 ? 'text-emerald-600' : ($scorecard['avg_rating'] >= 3 ? 'text-amber-500' : 'text-red-600') }}">
            {{ $scorecard['avg_rating'] !== null ? number_format($scorecard['avg_rating'], 1) : '—' }}
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.perf_avg_rating') }}</div>
        <div class="text-xs text-gray-400">{{ number_format($scorecard['total_ratings']) }} {{ __('carrier.reports.perf_ratings') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
        <div class="text-3xl font-black text-indigo-600">
            {{ $scorecard['on_time_pct'] !== null ? $scorecard['on_time_pct'] . '%' : '—' }}
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.perf_on_time') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
        <div class="text-3xl font-black text-gray-700">{{ number_format($scorecard['total_claims']) }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.perf_claims') }}</div>
        @if($scorecard['total_claims'] > 0)
        <div class="text-xs text-gray-400">{{ $scorecard['claims_approved_pct'] }}% {{ __('carrier.reports.perf_approved') }}</div>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
        <div class="text-3xl font-black text-red-600">{{ number_format($scorecard['total_compensated']) }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.perf_compensated') }}</div>
    </div>
</div>

{{-- Rating trend chart --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
    <h2 class="font-bold text-gray-900 mb-4">{{ __('carrier.reports.perf_trend_title') }}</h2>
    <canvas id="trendChart" height="80"></canvas>
</div>

{{-- Per-agent breakdown --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-900">{{ __('carrier.reports.perf_per_agent') }}</h2>
    </div>
    @if($agentRatings->isEmpty())
    <div class="py-10 text-center text-gray-400 text-sm">{{ __('carrier.reports.no_data') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_agent') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.perf_avg_rating') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.perf_total_ratings') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.perf_on_time') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($agentRatings as $row)
                @php
                    $onTimePct = $row->on_time_eligible > 0
                        ? round($row->on_time_count / $row->on_time_eligible * 100, 1)
                        : null;
                    $rColor = $row->avg_rating >= 4 ? 'emerald' : ($row->avg_rating >= 3 ? 'amber' : 'red');
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row->agent_name }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-{{ $rColor }}-100 text-{{ $rColor }}-700">
                            ⭐ {{ number_format($row->avg_rating, 1) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $row->total_ratings }}</td>
                    <td class="px-4 py-3 text-center text-gray-600">
                        {{ $onTimePct !== null ? $onTimePct . '%' : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Recent ratings --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-900">{{ __('carrier.reports.perf_recent_ratings') }}</h2>
    </div>
    @if($recentRatings->isEmpty())
    <div class="py-10 text-center text-gray-400 text-sm">{{ __('carrier.reports.no_data') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_suborder') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_agent') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.col_rating') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.col_on_time') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_comment') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_rated_by') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recentRatings as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $r->sub_order_number }}</td>
                    <td class="px-4 py-3 text-gray-800">{{ $r->agent_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center font-bold text-gray-800">
                        @for($i = 1; $i <= 5; $i++)
                            <span class="{{ $i <= $r->rating ? 'text-amber-400' : 'text-gray-200' }}">★</span>
                        @endfor
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($r->on_time === null) <span class="text-gray-300">—</span>
                        @elseif($r->on_time) <span class="text-emerald-600 font-bold">✓</span>
                        @else <span class="text-red-500 font-bold">✗</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs max-w-xs truncate">{{ $r->comment ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs capitalize">{{ str_replace('_', ' ', $r->rated_by_type) }}</td>
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ \Carbon\Carbon::parse($r->created_at)->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
fetch(@json(route('carrier.reports.performance.trend')))
    .then(r => r.json())
    .then(data => {
        if (!data.length) return;
        new Chart(document.getElementById('trendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: data.map(d => d.month),
                datasets: [{
                    label: @json(__('carrier.reports.perf_avg_rating')),
                    data: data.map(d => d.avg_rating),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    borderWidth: 2,
                    pointRadius: 4,
                    tension: 0.3,
                    fill: true,
                }],
            },
            options: {
                scales: {
                    y: { min: 0, max: 5, ticks: { stepSize: 1 } },
                },
                plugins: { legend: { display: false } },
            },
        });
    });
</script>
@endpush
