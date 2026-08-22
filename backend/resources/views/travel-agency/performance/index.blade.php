@extends('layouts.travel-agency')

@section('title', __('travel.performance.title'))

@section('content')
@php
$from = request('date_from', now()->subDays(29)->format('Y-m-d'));
$to = request('date_to', now()->format('Y-m-d'));
@endphp

<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.performance.title') }}</h1>
        <div class="flex items-center gap-2">
            <input id="performance-date-range" type="text" readonly
                   data-from="{{ $from }}" data-to="{{ $to }}"
                   value="{{ $from }} to {{ $to }}"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 bg-white cursor-pointer">
            <input type="hidden" id="date_from" value="{{ $from }}">
            <input type="hidden" id="date_to" value="{{ $to }}">
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.performance.total_bookings') }}</p>
            <p id="kpi-total-bookings" class="text-2xl font-bold text-gray-900">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.performance.completed_bookings') }}</p>
            <p id="kpi-completed-bookings" class="text-2xl font-bold text-emerald-600">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.performance.cancelled_bookings') }}</p>
            <p id="kpi-cancelled-bookings" class="text-2xl font-bold text-rose-600">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.performance.cancellation_rate') }}</p>
            <p id="kpi-cancellation-rate" class="text-2xl font-bold text-amber-600">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.performance.avg_booking_value') }}</p>
            <p id="kpi-avg-booking-value" class="text-lg font-bold text-gray-900">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.performance.total_revenue') }}</p>
            <p id="kpi-total-revenue" class="text-lg font-bold text-gray-900">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 col-span-2">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.performance.avg_response_time') }}</p>
            <p id="kpi-avg-response-time" class="text-2xl font-bold text-gray-900">—</p>
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('travel.performance.bookings_over_time') }}</h2>
            <canvas id="bookings-chart" height="120"></canvas>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('travel.performance.revenue_over_time') }}</h2>
            <canvas id="revenue-chart" height="120"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('travel.performance.top_packages') }}</h2>
        <canvas id="top-packages-chart" height="90"></canvas>
    </div>

    {{-- Per-package table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <h2 class="text-sm font-semibold text-gray-700 px-4 py-3 border-b border-gray-100">{{ __('travel.performance.package_performance') }}</h2>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.performance.column_package') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.performance.column_inquiries') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.performance.column_bookings') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.performance.column_conversion') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.performance.column_revenue') }}</th>
                </tr>
            </thead>
            <tbody id="package-performance-body" class="divide-y divide-gray-100">
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">{{ __('travel.performance.no_data') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    window.PERFORMANCE = {
        statsUrl: @json(route('travel-agency.performance.stats')),
        i18n: {
            bookings: @json(__('travel.performance.total_bookings')),
            revenue: @json(__('travel.performance.total_revenue')),
            minutes: @json(__('travel.performance.minutes')),
            noData: @json(__('travel.performance.no_data')),
        },
    };
</script>

@push('scripts')
    @vite('resources/js/travel_agency/performance.js')
@endpush
@endsection
