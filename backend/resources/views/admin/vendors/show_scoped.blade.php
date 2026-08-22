@extends('layouts.admin')

@section('title', $vendor->store_name . ' — ' . __('admin.vendors.title'))

@section('content')

@php
    $statusColors = [
        \App\Enums\VendorGlobalStatus::Pending->value      => 'gray',
        \App\Enums\VendorGlobalStatus::Active->value       => 'success',
        \App\Enums\VendorGlobalStatus::Suspended->value    => 'warning',
        \App\Enums\VendorGlobalStatus::Rejected->value     => 'danger',
        \App\Enums\VendorGlobalStatus::Blacklisted->value  => 'danger',
        \App\Enums\VendorGlobalStatus::UnderReview->value  => 'primary',
    ];
@endphp

<div class="mb-6 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
    {{ __('admin.vendors.restricted_profile_banner') }}
</div>

<div class="mb-6 flex items-center gap-4">
    @if($vendor->avatar)
        <img src="{{ $vendor->avatar }}" class="w-14 h-14 rounded-xl object-cover flex-shrink-0 border border-gray-200">
    @else
        <div class="w-14 h-14 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center text-xl font-bold flex-shrink-0">
            {{ strtoupper(substr($vendor->store_name, 0, 1)) }}
        </div>
    @endif
    <div class="min-w-0">
        <h1 class="text-lg font-semibold text-gray-900 truncate">{{ $vendor->store_name }}</h1>
        <x-badge :color="$statusColors[$vendor->global_status?->value] ?? 'gray'">
            {{ $vendor->global_status?->value }}
        </x-badge>
    </div>
</div>

<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-8">
        <x-card title="{{ __('admin.vendors.vendor_info') }}">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendors.store_name') }}</dt>
                    <dd class="font-medium text-gray-900 truncate ml-2">{{ $vendor->store_name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendors.slug') }}</dt>
                    <dd class="font-mono text-xs text-gray-700">{{ $vendor->store_slug }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendors.country') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $vendor->country?->name_en }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendors.status') }}</dt>
                    <dd><x-badge :color="$statusColors[$vendor->global_status?->value] ?? 'gray'">{{ $vendor->global_status?->value }}</x-badge></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendors.contact_email') }}</dt>
                    <dd class="font-medium text-gray-900 truncate ml-2">{{ $vendor->contact_email ?? $vendor->email }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendors.contact_phone') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $vendor->contact_phone ?? $vendor->phone }}</dd>
                </div>
            </dl>
        </x-card>
    </div>

    <div class="col-span-12 lg:col-span-4">
        <x-card title="{{ __('admin.vendors.performance') }}">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendors.completed_orders') }}</dt>
                    <dd class="font-medium text-gray-900">{{ number_format($completedOrdersCount) }}</dd>
                </div>
                @if($vendor->sla_compliance_pct !== null)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.vendors.sla_compliance_pct') }}</dt>
                        <dd class="font-medium text-gray-900">{{ number_format($vendor->sla_compliance_pct, 1) }}%</dd>
                    </div>
                @endif
            </dl>
        </x-card>
    </div>
</div>

@endsection
