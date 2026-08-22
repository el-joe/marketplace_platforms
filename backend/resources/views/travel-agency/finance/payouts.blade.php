@extends('layouts.travel-agency')

@section('title', __('travel.finance.payouts_title'))
@section('page-title', __('travel.finance.payouts_title'))

@section('content')
<div class="space-y-5">
    <h1 class="text-2xl font-black text-gray-900">{{ __('travel.finance.payouts_title') }}</h1>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('travel.finance.column_period') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('travel.finance.column_currency') }}</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('travel.finance.column_bookings') }}</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('travel.finance.column_gross') }}</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('travel.finance.column_commission') }}</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('travel.finance.column_net') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($payouts as $row)
                        <tr>
                            <td class="px-4 py-2 text-gray-900">{{ $row['period'] }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $row['currency'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-900">{{ number_format($row['bookings_count']) }}</td>
                            <td class="px-4 py-2 text-right text-gray-900">{{ number_format($row['gross']) }}</td>
                            <td class="px-4 py-2 text-right text-amber-600">{{ number_format($row['commission']) }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-emerald-600">{{ number_format($row['net_payout']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('travel.finance.no_data') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
