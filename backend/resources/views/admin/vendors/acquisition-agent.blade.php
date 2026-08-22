@extends('layouts.admin')

@section('title', $vendor->store_name . ' — ' . __('admin.vendors.acquisition_agent'))

@section('content')

<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-gray-900">{{ __('admin.vendors.acquisition_agent') }}</h1>
        <p class="text-sm text-gray-500">{{ $vendor->store_name }}</p>
    </div>
    <a href="{{ route('admin.vendors.show', $vendor) }}" class="btn btn-ghost btn-sm">{{ __('common.back') }}</a>
</div>

<x-card>
    @if($commission)
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-6">
            <div>
                <dt class="text-gray-500">{{ __('admin.vendors.agent') }}</dt>
                <dd class="font-medium text-gray-900">{{ $commission->admin?->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('admin.vendors.commission_rate') }}</dt>
                <dd class="font-medium text-gray-900">{{ number_format($commission->commission_rate / 100, 2) }}%</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('admin.vendors.monthly_min_sales') }}</dt>
                <dd class="font-medium text-gray-900">{{ $commission->monthly_min_sales }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('admin.vendors.monthly_max_sales') }}</dt>
                <dd class="font-medium text-gray-900">{{ $commission->monthly_max_sales }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('common.status') }}</dt>
                <dd><x-badge :color="$commission->status === 'active' ? 'success' : ($commission->status === 'expired' ? 'gray' : 'danger')">{{ ucfirst($commission->status) }}</x-badge></dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('admin.vendors.total_earned') }}</dt>
                <dd class="font-medium text-gray-900">{{ number_format($commission->total_earned) }} {{ $commission->currency }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('admin.vendors.expires_on') }}</dt>
                <dd class="font-medium text-gray-900">{{ $commission->valid_until->format('d M Y') }}</dd>
            </div>
        </dl>

        <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.vendors.view_earnings') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="py-2 pr-4">{{ __('common.month') ?? 'Month' }}</th>
                        <th class="py-2 pr-4">Sales in Month</th>
                        <th class="py-2 pr-4">{{ __('admin.vendors.total_earned') }}</th>
                        <th class="py-2 pr-4">{{ __('common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthlyEarnings as $row)
                        <tr class="border-b border-gray-50">
                            <td class="py-2 pr-4">{{ \Illuminate\Support\Carbon::parse($row->month)->format('M Y') }}</td>
                            <td class="py-2 pr-4">{{ $row->sales_count }}</td>
                            <td class="py-2 pr-4">{{ number_format($row->commission_earned) }} {{ $commission->currency }}</td>
                            <td class="py-2 pr-4"><x-badge color="gray">{{ ucfirst($row->status) }}</x-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">{{ __('common.no_results') ?? 'No earnings yet' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">{{ __('admin.vendors.assign_acquisition_agent') }}</p>
    @endif
</x-card>

@endsection
