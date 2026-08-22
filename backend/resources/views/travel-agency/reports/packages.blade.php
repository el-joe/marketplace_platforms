@extends('layouts.travel-agency')

@section('title', __('travel.reports.packages_title'))
@section('page-title', __('travel.reports.packages_title'))

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.reports.packages_title') }}</h1>

        <form method="GET" action="{{ route('travel-agency.reports.packages') }}" class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500">{{ __('travel.reports.sort_by') }}</label>
            <select name="sort" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                <option value="bookings" @selected($sort === 'bookings')>{{ __('travel.reports.sort_bookings') }}</option>
                <option value="revenue" @selected($sort === 'revenue')>{{ __('travel.reports.sort_revenue') }}</option>
                <option value="conversion" @selected($sort === 'conversion')>{{ __('travel.reports.sort_conversion') }}</option>
            </select>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_package_name') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_destination') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_inquiries') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_bookings_count') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_conversion_rate') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_revenue') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_avg_booking_value') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($packages as $pkg)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-800 font-medium">{{ $pkg['title'] }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $pkg['destination'] }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $pkg['inquiries'] }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $pkg['bookings'] }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $pkg['conversion_rate'] }}%</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ number_format($pkg['revenue']) }} {{ $pkg['currency'] }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ number_format($pkg['avg_booking_value']) }} {{ $pkg['currency'] }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $pkg['status'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">{{ __('travel.reports.no_data') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
