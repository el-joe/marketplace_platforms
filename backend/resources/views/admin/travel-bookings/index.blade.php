@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/travel-bookings.js'])
@endpush

@section('title', __('admin.travel.title_bookings'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.travel.title_bookings') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.travel.bookings_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-export-dropdown />
            <a href="{{ route('admin.travel.packages.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.travel.back_to_packages') }}</a>
        </div>
    </div>

    {{-- ─── Stats ────────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-card
            title="{{ __('admin.travel.total_bookings') }}"
            :value="number_format($stats['total'])"
            icon="ticket"
            iconBg="bg-primary-100 text-primary-600" />
        <x-stat-card
            title="{{ __('admin.travel.this_month') }}"
            :value="number_format($stats['this_month'])"
            icon="calendar"
            iconBg="bg-info-100 text-info-600" />
        <x-stat-card
            title="{{ __('admin.travel.confirmed') }}"
            :value="number_format($stats['confirmed'])"
            icon="check-circle"
            iconBg="bg-success-100 text-success-600" />
        <x-stat-card
            title="{{ __('admin.travel.cancelled') }}"
            :value="number_format($stats['cancelled'])"
            icon="x-circle"
            iconBg="bg-danger-100 text-danger-600" />
    </div>

    {{-- ─── Revenue by currency ─────────────────────────────────────────────────── --}}
    @if($revenueByCurrency->count())
    <x-card class="mb-5">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('admin.travel.confirmed_revenue_by_currency') }}</h3>
        <div class="flex flex-wrap gap-4">
            @foreach($revenueByCurrency as $rev)
            <div class="text-center">
                <p class="text-lg font-bold text-gray-900">{{ $rev['formatted'] }}</p>
            </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 mt-2">{{ __('admin.travel.revenue_currency_note') }}</p>
    </x-card>
    @endif

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.travel.search_booking_customer') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.travel.all_statuses') }}</option>
                    <option value="pending_documents">{{ __('admin.travel.pending_documents') }}</option>
                    <option value="confirmed">{{ __('admin.travel.confirmed') }}</option>
                    <option value="cancelled">{{ __('admin.travel.cancelled') }}</option>
                    <option value="completed">{{ __('common.completed') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.travel.booked_from') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.travel.booked_by') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('common.reset') }}</button>
        </form>
    </x-card>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="bookings-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.reference') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.package') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.customer') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.travel_dates') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.amount') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.status') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

@endsection

@push('scripts')
<script>
window.routes = {
    bookingsDatatable: '{{ route('admin.travel.bookings.datatable') }}',
};
</script>
@endpush
