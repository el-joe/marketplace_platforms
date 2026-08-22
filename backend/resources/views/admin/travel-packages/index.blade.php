@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/travel-packages.js'])
@endpush

@section('title', __('admin.travel.title_packages'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.travel.title_packages') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.travel.packages_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-export-dropdown />
            <a href="{{ route('admin.travel.agencies.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.travel.agencies') }}</a>
            <a href="{{ route('admin.travel.bookings.index') }}" class="btn btn-primary btn-sm">{{ __('admin.travel.bookings') }}</a>
        </div>
    </div>

    {{-- ─── Stats ────────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-card
            title="{{ __('admin.travel.pending_review') }}"
            :value="number_format($stats['pending'])"
            icon="clock"
            iconBg="bg-warning-100 text-warning-600" />
        <x-stat-card
            title="{{ __('admin.travel.active_packages_stat') }}"
            :value="number_format($stats['active'])"
            icon="check-circle"
            iconBg="bg-success-100 text-success-600" />
        <x-stat-card
            title="{{ __('admin.travel.sold_out_stat') }}"
            :value="number_format($stats['sold_out'])"
            icon="ticket"
            iconBg="bg-purple-100 text-purple-600" />
        <x-stat-card
            title="{{ __('admin.travel.urgent_departs_7d') }}"
            :value="number_format($stats['urgent'])"
            icon="exclamation-triangle"
            iconBg="{{ $stats['urgent'] > 0 ? 'bg-danger-100 text-danger-600' : 'bg-gray-100 text-gray-400' }}" />
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.travel.search_title_ar') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.travel.all_statuses') }}</option>
                    <option value="pending_review">{{ __('admin.travel.pending_review') }}</option>
                    <option value="active">{{ __('common.active') }}</option>
                    <option value="sold_out">{{ __('admin.travel.sold_out') }}</option>
                    <option value="expired">{{ __('admin.travel.status_expired') }}</option>
                    <option value="draft">{{ __('common.draft') }}</option>
                </select>
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.travel.destination') }}</label>
                <select id="filter-country" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.travel.all_destinations') }}</option>
                    @foreach($travelCountries as $c)
                        <option value="{{ $c->id }}">{{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.travel.departs_from') }}</label>
                <input type="date" id="filter-departure-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.travel.departs_by') }}</label>
                <input type="date" id="filter-departure-to" class="form-input w-full text-sm">
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('common.reset') }}</button>
        </form>
    </x-card>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="packages-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.package') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.agency') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.destination') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.price') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.departure') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.travel.seats') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.status') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Approve Confirm Modal ───────────────────────────────────────────────── --}}
    <x-modal id="approve-modal" title="{{ __('admin.travel.approve_package') }}" size="sm">
        <p class="text-sm text-gray-600">
            {{ __('admin.travel.approve_prefix') }} <strong id="approve-package-name"></strong>{{ __('admin.travel.approve_suffix') }}
        </p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#approve-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-approve-btn" class="btn btn-success">{{ __('admin.travel.approve_publish') }}</button>
        </div>
    </x-modal>

    {{-- ─── Reject Modal ────────────────────────────────────────────────────────── --}}
    <x-modal id="reject-modal" title="{{ __('admin.travel.return_to_agency') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">
            {{ __('admin.travel.return_prefix') }} <strong id="reject-package-name"></strong> {{ __('admin.travel.return_suffix') }}
        </p>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.reason') }} <span class="text-red-500">*</span></label>
        <textarea id="reject-reason-input" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.travel.reason_required_hint') }}"></textarea>
        <p class="text-xs text-red-500 hidden mt-1" id="reject-reason-error">{{ __('admin.travel.reason_is_required') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#reject-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-reject-btn" class="btn btn-danger">{{ __('admin.travel.return_to_draft') }}</button>
        </div>
    </x-modal>

@endsection

@push('scripts')
<script>
window.routes = {
    packagesDatatable: '{{ route('admin.travel.packages.datatable') }}',
};
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    approved: @json(__('admin.travel.approved')),
    failedToApprove: @json(__('admin.travel.failed_to_approve')),
    rejected: @json(__('admin.travel.rejected')),
    failedToReject: @json(__('admin.travel.failed_to_reject')),
    markExpiredConfirm: @json(__('admin.travel.mark_expired_confirm')),
    expired: @json(__('admin.travel.expired')),
    failedGeneric: @json(__('admin.travel.failed_generic')),
});
</script>
@endpush
