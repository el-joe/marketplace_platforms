@extends('layouts.travel-agency')

@section('title', __('travel.finance.revenue_title'))
@section('page-title', __('travel.finance.revenue_title'))

@section('content')
<div class="space-y-5">
    <h1 class="text-2xl font-black text-gray-900">{{ __('travel.finance.revenue_title') }}</h1>

    <form method="GET" action="{{ route('travel-agency.finance.revenue') }}" class="flex items-end gap-3 flex-wrap">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.reports.date_from') }}</label>
            <input type="date" name="date_from" value="{{ $from->toDateString() }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.reports.date_to') }}</label>
            <input type="date" name="date_to" value="{{ $to->toDateString() }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-500">
            {{ __('travel.reports.filter') }}
        </button>
    </form>

    @forelse($summary as $row)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-1">{{ __('travel.finance.column_gross') }} ({{ $row['currency'] }})</p>
                <p class="text-xl font-bold text-gray-900">{{ number_format($row['gross']) }} {{ $row['currency'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-1">{{ __('travel.finance.column_commission') }} ({{ $row['currency'] }})</p>
                <p class="text-xl font-bold text-amber-600">{{ number_format($row['commission']) }} {{ $row['currency'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-1">{{ __('travel.finance.column_net') }} ({{ $row['currency'] }})</p>
                <p class="text-xl font-bold text-emerald-600">{{ number_format($row['net_revenue']) }} {{ $row['currency'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-1">{{ __('travel.finance.column_bookings') }}</p>
                <p class="text-xl font-bold text-gray-900">{{ number_format($row['bookings_count']) }}</p>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 text-sm">
            {{ __('travel.finance.no_data') }}
        </div>
    @endforelse
</div>
@endsection
