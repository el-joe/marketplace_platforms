@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/vendor-change-requests-show.js'])
@endpush

@section('title', __('admin.vendor_change_requests.request_details'))

@section('content')

@php
    $statusColors = [
        'pending'   => 'gray',
        'approved'  => 'success',
        'rejected'  => 'danger',
        'cancelled' => 'gray',
    ];
    $requestedData = $changeRequest->requested_data ?? [];
    $allKeys = array_unique(array_merge(array_keys($currentData), array_keys($requestedData)));
@endphp

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <a href="{{ route('admin.vendor-change-requests.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('admin.vendor_change_requests.back') }}</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ __('admin.vendor_change_requests.request_details') }}</h1>
        <div class="flex items-center gap-2 mt-2">
            <x-badge :color="$statusColors[$changeRequest->status] ?? 'gray'">{{ __('admin.vendor_change_requests.' . $changeRequest->status) }}</x-badge>
            <span class="text-sm text-gray-500">{{ __('admin.vendors.section_' . $changeRequest->section) }} — {{ ucfirst($changeRequest->request_type) }}</span>
        </div>
    </div>
    <a href="{{ route('admin.vendors.show', $changeRequest->vendor_id) }}" class="text-sm text-primary-600 hover:underline">{{ $changeRequest->vendor?->store_name }}</a>
</div>

<div class="grid grid-cols-12 gap-6">
    <div class="col-span-12 lg:col-span-8 space-y-6">
        <x-card title="{{ __('admin.vendor_change_requests.request_details') }}">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-start">
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_change_requests.section') }}</th>
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_change_requests.current_data') }}</th>
                            <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_change_requests.requested_data') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($allKeys as $key)
                            @php
                                $before = $currentData[$key] ?? null;
                                $after = $requestedData[$key] ?? null;
                                $changed = $before !== $after;
                            @endphp
                            <tr class="{{ $changed ? 'bg-yellow-50/50' : '' }}">
                                <td class="py-2 pr-4 font-mono text-xs text-gray-500 align-top">{{ $key }}</td>
                                <td class="py-2 pr-4 align-top {{ $changed ? 'text-danger-600 line-through' : 'text-gray-700' }}">{{ is_scalar($before) ? $before : json_encode($before) }}</td>
                                <td class="py-2 pr-4 align-top {{ $changed ? 'text-success-700 font-medium' : 'text-gray-700' }}">{{ is_scalar($after) ? $after : json_encode($after) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($changeRequest->vendor_note)
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_change_requests.vendor_note') }}</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ $changeRequest->vendor_note }}</dd>
                </div>
            @endif

            @if($changeRequest->admin_note)
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_change_requests.admin_note') }}</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ $changeRequest->admin_note }}</dd>
                </div>
            @endif
        </x-card>
    </div>

    <div class="col-span-12 lg:col-span-4 space-y-4">
        <x-card title="{{ __('admin.vendor_change_requests.request_details') }}">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendor_change_requests.requested_by_column') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $changeRequest->requestedBy?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.vendor_change_requests.requested_at_column') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $changeRequest->created_at->format('d M Y H:i') }}</dd>
                </div>
                @if($changeRequest->reviewed_at)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.vendor_change_requests.reviewed_by_column') }}</dt>
                        <dd class="font-medium text-gray-900">{{ $changeRequest->reviewedByAdmin?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.vendor_change_requests.reviewed_at_column') }}</dt>
                        <dd class="font-medium text-gray-900">{{ $changeRequest->reviewed_at->format('d M Y H:i') }}</dd>
                    </div>
                @endif
            </dl>

            @if($changeRequest->status === 'pending')
                <form id="change-request-review-form" data-request-id="{{ $changeRequest->id }}" class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_change_requests.admin_note_label') }}</label>
                        <textarea name="admin_note" class="form-input w-full resize-none" rows="3" placeholder="{{ __('admin.vendor_change_requests.admin_note_placeholder') }}"></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" id="btn-approve-request" class="btn btn-success btn-sm flex-1">{{ __('admin.vendor_change_requests.approve') }}</button>
                        <button type="button" id="btn-reject-request" class="btn btn-danger btn-sm flex-1">{{ __('admin.vendor_change_requests.reject') }}</button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</div>

@endsection
