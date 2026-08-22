@extends('layouts.admin')

@section('title', __('admin.nav.my_acquisition_commissions'))

@section('content')

<div class="mb-6">
    <h1 class="text-xl font-semibold text-gray-900">{{ __('admin.nav.my_acquisition_commissions') }}</h1>
    <p class="text-sm text-gray-500">{{ __('admin.vendors.my_acquisition_commissions_hint') }}</p>
</div>

<x-card>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
                    <th class="py-2 pr-4">{{ __('admin.vendors.title') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.vendors.commission_rate') }}</th>
                    <th class="py-2 pr-4">Sales This Month</th>
                    <th class="py-2 pr-4">Earned This Month</th>
                    <th class="py-2 pr-4">{{ __('admin.vendors.total_earned') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.vendors.expires_on') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="border-b border-gray-50">
                        <td class="py-2 pr-4 font-medium text-gray-900">{{ $row['vendor_name'] }}</td>
                        <td class="py-2 pr-4">{{ number_format($row['commission_rate'] / 100, 2) }}%</td>
                        <td class="py-2 pr-4">{{ $row['sales_this_month'] }}</td>
                        <td class="py-2 pr-4">{{ number_format($row['earned_this_month']) }} {{ $row['currency'] }}</td>
                        <td class="py-2 pr-4">{{ number_format($row['total_earned']) }} {{ $row['currency'] }}</td>
                        <td class="py-2 pr-4">{{ \Illuminate\Support\Carbon::parse($row['expires_on'])->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-gray-400">{{ __('common.no_results') ?? 'No active commissions' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

@endsection
