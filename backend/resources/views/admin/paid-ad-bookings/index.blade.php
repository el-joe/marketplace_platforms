@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/ad-campaigns.js'])
@endpush

@section('title', __('admin.paid_ad_bookings.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.ad-campaigns.index') }}" class="hover:text-primary-600">{{ __('admin.ad_campaigns.title') }}</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">{{ __('admin.paid_ad_bookings.title') }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.paid_ad_bookings.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.paid_ad_bookings.manage_desc') }}</p>
        </div>
        <div>
            <a href="{{ route('admin.ad-slots.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.paid_ad_bookings.manage_slots') }}</a>
        </div>
    </div>

    {{-- ─── Stats ────────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-card title="{{ __('admin.paid_ad_bookings.pending_approval') }}" :value="number_format($stats['pending'])"
            iconBg="bg-warning-100 text-warning-600" />
        <x-stat-card title="{{ __('admin.paid_ad_bookings.active_bookings') }}" :value="number_format($stats['active'])"
            iconBg="bg-success-100 text-success-600" />
        <x-stat-card title="{{ __('admin.paid_ad_bookings.rejected') }}" :value="number_format($stats['rejected'])" iconBg="bg-red-100 text-red-600" />
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="bookings-filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.paid_ad_bookings.search') }}</label>
                <input type="text" id="bookings-search" class="form-input w-full text-sm" placeholder="{{ __('admin.paid_ad_bookings.search_placeholder') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.paid_ad_bookings.status') }}</label>
                <select id="bookings-filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.paid_ad_bookings.all_statuses') }}</option>
                    <option value="pending">{{ __('admin.paid_ad_bookings.pending') }}</option>
                    <option value="active">{{ __('admin.paid_ad_bookings.active') }}</option>
                    <option value="rejected">{{ __('admin.paid_ad_bookings.rejected') }}</option>
                    <option value="cancelled">{{ __('admin.paid_ad_bookings.cancelled') }}</option>
                    <option value="ended">{{ __('admin.paid_ad_bookings.ended') }}</option>
                </select>
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.paid_ad_bookings.payment') }}</label>
                <select id="bookings-filter-payment" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.paid_ad_bookings.all_payments') }}</option>
                    <option value="unpaid">{{ __('admin.paid_ad_bookings.unpaid') }}</option>
                    <option value="paid">{{ __('admin.paid_ad_bookings.paid') }}</option>
                    <option value="invoiced">{{ __('admin.paid_ad_bookings.invoiced') }}</option>
                    <option value="refunded">{{ __('admin.paid_ad_bookings.refunded') }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.paid_ad_bookings.booked_from') }}</label>
                <input type="date" id="bookings-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.paid_ad_bookings.ends_by') }}</label>
                <input type="date" id="bookings-date-to" class="form-input w-full text-sm">
            </div>
            <button type="button" id="clear-bookings-filters" class="btn btn-ghost btn-sm self-end">{{ __('admin.paid_ad_bookings.reset') }}</button>
        </form>
    </x-card>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="bookings-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.paid_ad_bookings.reference') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.paid_ad_bookings.vendor') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.paid_ad_bookings.slot') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.paid_ad_bookings.dates') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.paid_ad_bookings.rate') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.paid_ad_bookings.status') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.paid_ad_bookings.payment') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.paid_ad_bookings.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Approve Confirm Modal ───────────────────────────────────────────────── --}}
    <x-modal id="approve-booking-modal" title="{{ __('admin.paid_ad_bookings.approve_booking_title') }}" size="sm">
        <p class="text-sm text-gray-600">
            {{ __('admin.paid_ad_bookings.approve') }} <strong id="approve-booking-ref" class="font-mono text-gray-800"></strong>?
            {{ __('admin.paid_ad_bookings.booking_will_become_active') }}
        </p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary"
                onclick="$('#approve-booking-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-approve-booking-btn" class="btn btn-success">{{ __('admin.paid_ad_bookings.approve') }}</button>
        </div>
    </x-modal>

    {{-- ─── Reject Modal ────────────────────────────────────────────────────────── --}}
    <x-modal id="reject-booking-modal" title="{{ __('admin.paid_ad_bookings.reject_booking_title') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">
            {{ __('admin.paid_ad_bookings.reject') }} <strong id="reject-booking-ref" class="font-mono text-gray-800"></strong>.
        </p>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.paid_ad_bookings.rejection_reason') }} <span
                class="text-red-500">*</span></label>
        <textarea id="reject-booking-reason" rows="3" class="form-input w-full text-sm"
            placeholder="{{ __('admin.paid_ad_bookings.reject_booking_placeholder') }}"></textarea>
        <p class="text-xs text-red-500 hidden mt-1" id="reject-booking-reason-error">{{ __('admin.paid_ad_bookings.reason_required') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary"
                onclick="$('#reject-booking-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-reject-booking-btn" class="btn btn-danger">{{ __('admin.paid_ad_bookings.reject') }}</button>
        </div>
    </x-modal>

@endsection
