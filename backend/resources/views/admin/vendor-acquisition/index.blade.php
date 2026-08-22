@extends('layouts.admin')

@section('title', __('admin.nav.acquisition_commissions'))

@section('content')

<div class="mb-6">
    <h1 class="text-xl font-semibold text-gray-900">{{ __('admin.nav.acquisition_commissions') }}</h1>
</div>

<x-card>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
                    <th class="py-2 pr-4">{{ __('admin.vendors.title') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.vendors.agent') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.vendors.commission_rate') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.vendors.total_earned') }}</th>
                    <th class="py-2 pr-4">{{ __('common.status') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.vendors.expires_on') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($commissions as $commission)
                    <tr class="border-b border-gray-50">
                        <td class="py-2 pr-4 font-medium text-gray-900">
                            <a href="{{ route('admin.vendors.acquisition-agent.show', $commission->vendor) }}" class="text-primary-600 hover:underline">
                                {{ $commission->vendor?->store_name }}
                            </a>
                        </td>
                        <td class="py-2 pr-4">{{ $commission->admin?->name }}</td>
                        <td class="py-2 pr-4">{{ number_format($commission->commission_rate / 100, 2) }}%</td>
                        <td class="py-2 pr-4">{{ number_format($commission->total_earned) }} {{ $commission->currency }}</td>
                        <td class="py-2 pr-4"><x-badge :color="$commission->status === 'active' ? 'success' : ($commission->status === 'expired' ? 'gray' : 'danger')">{{ ucfirst($commission->status) }}</x-badge></td>
                        <td class="py-2 pr-4">{{ $commission->valid_until->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-gray-400">{{ __('common.no_results') ?? 'No records' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $commissions->links() }}</div>
</x-card>

@endsection
