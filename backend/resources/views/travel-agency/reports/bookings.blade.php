@extends('layouts.travel-agency')

@section('title', __('travel.reports.bookings_title'))
@section('page-title', __('travel.reports.bookings_title'))

@section('content')
<div class="space-y-5">
    <h1 class="text-2xl font-black text-gray-900">{{ __('travel.reports.bookings_title') }}</h1>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.total_bookings') }}</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($summary['total']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.confirmed') }}</p>
            <p class="text-xl font-bold text-emerald-600">{{ number_format($summary['confirmed']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.completed') }}</p>
            <p class="text-xl font-bold text-blue-600">{{ number_format($summary['completed']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.cancelled') }}</p>
            <p class="text-xl font-bold text-red-600">{{ number_format($summary['cancelled']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">{{ __('travel.reports.pending_documents') }}</p>
            <p class="text-xl font-bold text-amber-600">{{ number_format($summary['pending']) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('travel-agency.reports.bookings') }}" class="flex items-end gap-3 flex-wrap">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.filter') }}</label>
            <select name="status" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('travel.reports.all_statuses') }}</option>
                @foreach(['pending_documents','confirmed','cancelled','completed'] as $st)
                    <option value="{{ $st }}" @selected(request('status') === $st)>{{ __('travel.bookings.status_' . $st) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.packages.title') }}</label>
            <select name="package_id" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('travel.reports.all_packages') }}</option>
                @foreach($packages as $pkg)
                    <option value="{{ $pkg->id }}" @selected(request('package_id') === $pkg->id)>{{ $pkg->title_en }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.packages.destination_country') }}</label>
            <select name="destination_country" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('travel.reports.all_countries') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country }}" @selected(request('destination_country') === $country)>{{ $country }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.date_from') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.date_to') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
            {{ __('travel.bookings.filter') }}
        </button>
        <a href="{{ route('travel-agency.reports.bookings.export', request()->query()) }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-500">
            {{ __('travel.reports.export_csv') }}
        </a>
        <a href="{{ route('travel-agency.reports.bookings.export', array_merge(request()->query(), ['format' => 'excel'])) }}"
           class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-200">
            {{ __('common.export_excel') }}
        </a>
        <a href="{{ route('travel-agency.reports.bookings.export', array_merge(request()->query(), ['format' => 'word'])) }}"
           class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-200">
            {{ __('common.export_word') }}
        </a>
    </form>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('travel.reports.bookings_by_status') }}</h2>
            <canvas id="bookings-status-chart" height="180"></canvas>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('travel.reports.bookings_over_time') }}</h2>
            <canvas id="bookings-over-time-chart" height="180"></canvas>
        </div>
    </div>

    {{-- Bookings table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_booking_ref') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_customer_name') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_package') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_travel_date') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_price') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_currency') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.reports.column_status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @php
                $colors = ['pending_documents'=>'bg-amber-100 text-amber-700','confirmed'=>'bg-emerald-100 text-emerald-700','cancelled'=>'bg-red-100 text-red-700','completed'=>'bg-blue-100 text-blue-700'];
                @endphp
                @forelse($bookings as $bk)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $bk->booking_number }}</td>
                    <td class="px-4 py-3 text-gray-800">{{ $bk->customer->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $bk->package->title_ar ?: $bk->package->title_en }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $bk->package->departure_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ number_format($bk->total_price) }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $bk->package->currency }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$bk->status->value] ?? '' }}">
                            {{ __('travel.bookings.status_' . $bk->status->value) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('travel-agency.bookings.show', $bk) }}" class="text-blue-600 text-xs hover:underline">{{ __('travel.reports.details') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">{{ __('travel.bookings.no_bookings') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $bookings->links() }}
        </div>
    </div>
</div>

<script>
    window.BOOKINGS_STATUS_DATA = @json($byStatus);
    window.BOOKINGS_OVER_TIME_DATA = @json($overTime);
</script>

@push('scripts')
    @vite('resources/js/travel_agency/reports.js')
@endpush
@endsection
