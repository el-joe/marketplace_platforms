@extends('layouts.travel-agency')

@section('title', __('travel.reports.revenue_title'))
@section('page-title', __('travel.reports.revenue_title'))

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.reports.revenue_title') }}</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('travel-agency.reports.revenue.export', request()->query()) }}"
               class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
                {{ __('travel.reports.export_csv') }}
            </a>
            <a href="{{ route('travel-agency.reports.revenue.export', array_merge(request()->query(), ['format' => 'excel'])) }}"
               class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-200">
                {{ __('common.export_excel') }}
            </a>
            <a href="{{ route('travel-agency.reports.revenue.export', array_merge(request()->query(), ['format' => 'word'])) }}"
               class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-200">
                {{ __('common.export_word') }}
            </a>
        </div>
    </div>

    {{-- Date range filter --}}
    <form method="GET" action="{{ route('travel-agency.reports.revenue') }}" class="flex items-end gap-3 flex-wrap">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.reports.date_from') }} – {{ __('travel.reports.date_to') }}</label>
            <input type="text" id="report-date-range" readonly
                   data-from="{{ $from->toDateString() }}" data-to="{{ $to->toDateString() }}"
                   value="{{ $from->toDateString() }} to {{ $to->toDateString() }}"
                   class="rounded-lg border-gray-300 text-sm w-64">
            <input type="hidden" id="date_from" name="date_from" value="{{ $from->toDateString() }}">
            <input type="hidden" id="date_to" name="date_to" value="{{ $to->toDateString() }}">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-500">
            {{ __('travel.reports.filter') }}
        </button>
    </form>

    {{-- Summary cards — grouped by currency, never summed across currencies --}}
    @forelse($revenueByCurrency as $currency => $row)
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.total_bookings_revenue') }} ({{ $currency }})</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($row['revenue']) }} {{ $currency }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.platform_commission') }} ({{ $currency }})</p>
            <p class="text-xl font-bold text-amber-600">{{ number_format($row['commission']) }} {{ $currency }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.net_revenue') }} ({{ $currency }})</p>
            <p class="text-xl font-bold text-emerald-600">{{ number_format($row['net']) }} {{ $currency }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.cancelled_bookings_value') }} ({{ $currency }})</p>
            <p class="text-xl font-bold text-red-600">{{ number_format($cancelledByCurrency[$currency] ?? 0) }} {{ $currency }}</p>
        </div>
    </div>
    @empty
    <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 text-sm">
        {{ __('travel.reports.no_data') }}
    </div>
    @endforelse

    {{-- Daily revenue chart --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('travel.reports.daily_revenue_chart') }}</h2>
        <canvas id="revenue-chart" height="90"></canvas>
    </div>

    {{-- Monthly breakdown table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <h2 class="text-sm font-semibold text-gray-700 px-4 py-3 border-b border-gray-100">{{ __('travel.reports.monthly_breakdown') }}</h2>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_month') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_bookings_count') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_revenue') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_commission') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_net') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_currency') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($monthlyBreakdown as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-800">{{ $row['month'] }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $row['bookings_count'] }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ number_format($row['revenue']) }}</td>
                    <td class="px-4 py-3 text-amber-600">{{ number_format($row['commission']) }}</td>
                    <td class="px-4 py-3 text-emerald-600">{{ number_format($row['net']) }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $row['currency'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">{{ __('travel.reports.no_data') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    window.REVENUE_CHART_DATA = @json($dailyRevenue);
</script>

@push('scripts')
    @vite('resources/js/travel_agency/reports.js')
@endpush
@endsection
