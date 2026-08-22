@extends('layouts.travel-agency')

@section('title', __('travel.finance.sales_report_title'))
@section('page-title', __('travel.finance.sales_report_title'))

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.finance.sales_report_title') }}</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('travel-agency.finance.sales-report.export', request()->query()) }}"
               class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
                {{ __('travel.finance.export_button') }}
            </a>
            <a href="{{ route('travel-agency.finance.sales-report.export', array_merge(request()->query(), ['format' => 'excel'])) }}"
               class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-200">
                {{ __('common.export_excel') }}
            </a>
            <a href="{{ route('travel-agency.finance.sales-report.export', array_merge(request()->query(), ['format' => 'word'])) }}"
               class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-200">
                {{ __('common.export_word') }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('travel-agency.finance.sales-report') }}" class="flex items-end gap-3 flex-wrap">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.reports.date_from') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.reports.date_to') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.finance.column_package') }}</label>
            <select name="package_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('travel.reports.filter') }}</option>
                @foreach($packages as $package)
                    <option value="{{ $package->id }}" @selected(request('package_id') === $package->id)>{{ $package->title_en }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.finance.column_status') }}</label>
            <select name="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">—</option>
                <option value="pending_documents" @selected(request('status') === 'pending_documents')>{{ __('travel.bookings.status_pending_documents') }}</option>
                <option value="confirmed" @selected(request('status') === 'confirmed')>{{ __('travel.bookings.status_confirmed') }}</option>
                <option value="completed" @selected(request('status') === 'completed')>{{ __('travel.bookings.status_completed') }}</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('travel.bookings.status_cancelled') }}</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-500">
            {{ __('travel.reports.filter') }}
        </button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('travel.finance.column_booking_ref') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('travel.finance.column_package') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('travel.finance.column_customer') }}</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('travel.finance.column_travelers') }}</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('travel.finance.column_price') }}</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('travel.finance.column_commission') }}</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('travel.finance.column_net') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('travel.finance.column_date') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('travel.finance.column_status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($bookings as $row)
                        <tr>
                            <td class="px-4 py-2 text-gray-900 font-mono text-xs">{{ $row->booking_ref }}</td>
                            <td class="px-4 py-2 text-gray-900">{{ $row->package_name }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $row->customer_name }}</td>
                            <td class="px-4 py-2 text-right text-gray-900">{{ $row->travelers }}</td>
                            <td class="px-4 py-2 text-right text-gray-900">{{ number_format($row->price) }} {{ $row->currency }}</td>
                            <td class="px-4 py-2 text-right text-amber-600">{{ number_format($row->commission) }}</td>
                            <td class="px-4 py-2 text-right text-emerald-600">{{ number_format($row->net_revenue) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $row->date->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ ucfirst(str_replace('_', ' ', $row->status->value)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-400">{{ __('travel.finance.no_data') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($bookings->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $bookings->links() }}</div>
        @endif
    </div>
</div>
@endsection
