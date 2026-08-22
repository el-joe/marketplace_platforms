@extends('layouts.admin')

@section('title', __('admin.marketer_campaigns.title'))

@section('content')

@php
    $statusColors = [
        'pending_admin'  => 'warning',
        'active'         => 'success',
        'rejected'       => 'danger',
        'auto_approved'  => 'primary',
        'done'           => 'gray',
    ];
@endphp

<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.marketer_campaigns.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.marketer_campaigns.manage_subtitle') }}</p>
    </div>
    @if($pendingCount > 0)
        <span class="inline-flex items-center gap-2 rounded-lg bg-warning-100 text-warning-800 px-3 py-1.5 text-sm font-medium">
            <span>{{ __('admin.marketer_campaigns.pending_review') }}</span>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-warning-600 text-white text-xs font-bold">{{ $pendingCount }}</span>
        </span>
    @endif
</div>

{{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
<x-card class="mb-5">
    <form method="GET" action="{{ route('admin.marketer-campaigns.index') }}" class="flex flex-wrap gap-3 items-end">
        <div class="w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.marketer_campaigns.status_column') }}</label>
            <select name="status" class="form-input w-full text-sm">
                <option value="">{{ __('admin.marketer_campaigns.all_statuses') }}</option>
                @foreach(['pending_admin', 'active', 'rejected', 'auto_approved', 'done'] as $statusOption)
                    <option value="{{ $statusOption }}" {{ request('status') === $statusOption ? 'selected' : '' }}>
                        {{ __('admin.marketer_campaigns.status_' . $statusOption) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.marketer_campaigns.country_column') }}</label>
            <select name="country_id" class="form-input w-full text-sm">
                <option value="">{{ __('admin.marketer_campaigns.all_countries') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" {{ (string) request('country_id') === (string) $country->id ? 'selected' : '' }}>
                        {{ $country->name_en }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.marketer_campaigns.filter') }}</button>
        <a href="{{ route('admin.marketer-campaigns.index') }}" class="btn btn-ghost btn-sm">{{ __('admin.marketer_campaigns.reset') }}</a>
    </form>
</x-card>

{{-- ─── Table ───────────────────────────────────────────────────────────────── --}}
<x-card>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
                    <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.campaign_id_column') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.vendor_column') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.product_column') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.country_column') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.commission_type_column') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.status_column') }}</th>
                    <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.created_column') }}</th>
                    <th class="py-2 pr-4 text-end">{{ __('admin.marketer_campaigns.actions_column') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaigns as $campaign)
                    @php
                        $product = $campaign->vendorListing?->productVariant?->product
                            ?? $campaign->adminListing?->productVariant?->product;
                    @endphp
                    <tr class="border-b border-gray-50">
                        <td class="py-2 pr-4 font-mono text-xs text-gray-500">{{ substr($campaign->id, 0, 8) }}</td>
                        <td class="py-2 pr-4 font-medium text-gray-900">{{ $campaign->vendor?->store_name ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $product?->name ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $campaign->country?->name_en ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ __('admin.marketer_campaigns.commission_type_' . $campaign->commission_type) }}</td>
                        <td class="py-2 pr-4">
                            <x-badge :color="$statusColors[$campaign->status] ?? 'gray'">
                                {{ __('admin.marketer_campaigns.status_' . $campaign->status) }}
                            </x-badge>
                        </td>
                        <td class="py-2 pr-4">{{ $campaign->created_at->format('d M Y') }}</td>
                        <td class="py-2 pr-4 text-end">
                            <a href="{{ route('admin.marketer-campaigns.show', $campaign) }}" class="text-primary-600 hover:underline">
                                {{ __('admin.marketer_campaigns.view') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-4 text-center text-gray-400">{{ __('admin.marketer_campaigns.no_campaigns') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $campaigns->appends(request()->query())->links() }}</div>
</x-card>

@endsection
