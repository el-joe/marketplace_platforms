@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/ad-campaigns.js'])
@endpush

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            vendor: @json(__('admin.ad_campaigns.vendor')),
            campaign: @json(__('admin.ad_campaigns.campaign')),
            type: @json(__('admin.ad_campaigns.type')),
            status: @json(__('admin.ad_campaigns.status')),
            budget: @json(__('admin.ad_campaigns.budget')),
            spend: @json(__('admin.ad_campaigns.spend')),
            utilization: @json(__('admin.ad_campaigns.utilization')),
            quality: @json(__('admin.ad_campaigns.quality')),
            dates: @json(__('admin.ad_campaigns.dates')),
            ipAddress: @json(__('admin.ad_campaigns.ip_address')),
            clicksHr: @json(__('admin.ad_campaigns.clicks_hr')),
            clicksPer24h: @json(__('admin.ad_campaigns.clicks_per_24h')),
            blockedAt: @json(__('admin.ad_campaigns.blocked_at')),
            reasonCol: @json(__('admin.ad_campaigns.reason_col')),
            referenceCol: @json(__('admin.ad_campaigns.reference_col')),
            slotCol: @json(__('admin.ad_campaigns.slot_col')),
            rateCol: @json(__('admin.ad_campaigns.rate_col')),
            paymentCol: @json(__('admin.ad_campaigns.payment_col')),
            approve: @json(__('admin.marketers.approve')),
            reject: @json(__('admin.marketers.reject')),
            pause: @json(__('admin.ad_campaigns.pause')),
            resume: @json(__('admin.ad_campaigns.resume')),
            approving: @json(__('admin.ad_campaigns.approving')),
            rejecting: @json(__('admin.ad_campaigns.rejecting')),
            pausing: @json(__('admin.ad_campaigns.pausing')),
            resuming: @json(__('admin.ad_campaigns.resuming')),
            blocking: @json(__('admin.ad_campaigns.blocking')),
            campaignApproved: @json(__('admin.ad_campaigns.campaign_approved')),
            campaignRejected: @json(__('admin.ad_campaigns.campaign_rejected')),
            campaignPaused: @json(__('admin.ad_campaigns.campaign_paused')),
            campaignResumed: @json(__('admin.ad_campaigns.campaign_resumed')),
            failedToApproveCampaign: @json(__('admin.ad_campaigns.failed_to_approve_campaign')),
            failedToRejectCampaign: @json(__('admin.ad_campaigns.failed_to_reject_campaign')),
            genericFailed: @json(__('admin.ad_campaigns.generic_failed')),
            ipBlocked: @json(__('admin.ad_campaigns.ip_blocked')),
            failedToBlockIp: @json(__('admin.ad_campaigns.failed_to_block_ip')),
            blockIpBtn: @json(__('admin.ad_campaigns.block_ip_btn')),
            bookingApproved: @json(__('admin.ad_campaigns.booking_approved')),
            bookingRejected: @json(__('admin.ad_campaigns.booking_rejected')),
            creativeApproved: @json(__('admin.ad_campaigns.creative_approved')),
            creativeRejected: @json(__('admin.ad_campaigns.creative_rejected')),
            rejectCreativeBtnDt: @json(__('admin.ad_campaigns.reject_creative_btn_dt')),
            impressions: @json(__('admin.ad_campaigns.impressions')),
            clicks: @json(__('admin.ad_campaigns.clicks')),
            slotsName: @json(__('admin.ad_campaigns.slots_name')),
            slotsPlacement: @json(__('admin.ad_campaigns.slots_placement')),
            slotsCountry: @json(__('admin.ad_campaigns.slots_country')),
            slotsPricing: @json(__('admin.ad_campaigns.slots_pricing')),
            slotsBaseRate: @json(__('admin.ad_campaigns.slots_base_rate')),
            slotsBookingDays: @json(__('admin.ad_campaigns.slots_booking_days')),
            slotsAvailable: @json(__('admin.ad_campaigns.slots_available')),
        });
    </script>
@endpush

@section('title', __('admin.ad_campaigns.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.ad_campaigns.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.ad_campaigns.manage_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-export-dropdown />
            <a href="{{ route('admin.ad-campaigns.fraud') }}" class="btn btn-secondary btn-sm">{{ __('admin.ad_campaigns.fraud_alerts') }}</a>
            <a href="{{ route('admin.ad-slots.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.ad_campaigns.ad_slots') }}</a>
            <a href="{{ route('admin.paid-ad-bookings.index') }}" class="btn btn-primary btn-sm">{{ __('admin.ad_campaigns.paid_bookings') }}</a>
        </div>
    </div>

    {{-- ─── Stats ────────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-card
            title="{{ __('admin.ad_campaigns.pending_review') }}"
            :value="number_format($stats['pending'])"
            icon="clock"
            iconBg="bg-warning-100 text-warning-600" />
        <x-stat-card
            title="{{ __('admin.ad_campaigns.active_campaigns') }}"
            :value="number_format($stats['active'])"
            icon="check-circle"
            iconBg="bg-success-100 text-success-600" />
        <x-stat-card
            title="{{ __('admin.ad_campaigns.paused') }}"
            :value="number_format($stats['paused'])"
            icon="pause-circle"
            iconBg="bg-gray-100 text-gray-600" />
        <x-stat-card
            title="{{ __('admin.ad_campaigns.spend_today') }}"
            :value="'$' . number_format($stats['spend_today'] / 100, 2)"
            icon="trending-up"
            iconBg="bg-primary-100 text-primary-600" />
    </div>

    {{-- ─── Tab navigation ──────────────────────────────────────────────────────── --}}
    <div class="flex border-b border-gray-200 mb-5 gap-1">
        @foreach([
            'all'      => __('admin.ad_campaigns.active_campaigns_tab'),
            'cpc'      => __('admin.ad_campaigns.cpc_campaigns'),
            'cpm'      => __('admin.ad_campaigns.cpm_campaigns'),
        ] as $typeVal => $typeLabel)
            <button
                type="button"
                class="campaign-type-tab px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                    {{ $typeVal === 'all' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                data-type="{{ $typeVal }}">
                {{ $typeLabel }}
            </button>
        @endforeach
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ad_campaigns.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.ad_campaigns.search_placeholder') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ad_campaigns.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.ad_campaigns.all_statuses') }}</option>
                    <option value="pending_review">{{ __('admin.ad_campaigns.pending_review') }}</option>
                    <option value="active">{{ __('admin.ad_campaigns.active') }}</option>
                    <option value="paused">{{ __('admin.ad_campaigns.paused') }}</option>
                    <option value="rejected">{{ __('admin.ad_campaigns.rejected') }}</option>
                    <option value="ended">{{ __('admin.ad_campaigns.ended') }}</option>
                    <option value="draft">{{ __('admin.ad_campaigns.draft') }}</option>
                </select>
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.country') }}</label>
                <select id="filter-country" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.ad_campaigns.all_countries') }}</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ad_campaigns.start_from') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ad_campaigns.ends_by') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('admin.ad_campaigns.reset') }}</button>
        </form>
    </x-card>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="campaigns-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.vendor') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.marketers.campaign') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.type') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.status') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.budget') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.spend') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.utilization') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.quality') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.dates') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.ad_campaigns.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Approve Confirm Modal ───────────────────────────────────────────────── --}}
    <x-modal id="approve-modal" title="{{ __('admin.ad_campaigns.approve_campaign_title') }}" size="sm">
        <p class="text-sm text-gray-600">
            {{ __('admin.marketers.approve') }} {{ __('admin.marketers.campaign') }} <strong id="approve-campaign-name"></strong>?
            {{ __('admin.ad_campaigns.will_become_active_immediately') }}
        </p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#approve-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-approve-btn" class="btn btn-success">{{ __('admin.marketers.approve') }}</button>
        </div>
    </x-modal>

    {{-- ─── Reject Modal ────────────────────────────────────────────────────────── --}}
    <x-modal id="reject-modal" title="{{ __('admin.ad_campaigns.reject_campaign_title') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">
            {{ __('admin.ad_campaigns.reject_campaign_title') }} <strong id="reject-campaign-name"></strong>.
        </p>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ad_campaigns.rejection_reason') }} <span class="text-red-500">*</span></label>
        <textarea id="reject-reason-input" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.marketers.reject_campaign_reason_placeholder') }}"></textarea>
        <p class="text-xs text-red-500 hidden mt-1" id="reject-reason-error">{{ __('admin.ad_campaigns.reason_required') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#reject-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-reject-btn" class="btn btn-danger">{{ __('admin.marketers.reject') }}</button>
        </div>
    </x-modal>

    {{-- ─── Pause / Resume Confirm Modals ──────────────────────────────────────── --}}
    <x-modal id="pause-modal" title="{{ __('admin.ad_campaigns.pause_campaign_title') }}" size="sm">
        <p class="text-sm text-gray-600">{{ __('admin.ad_campaigns.pause') }} <strong id="pause-campaign-name"></strong>? {{ __('admin.ad_campaigns.will_stop_serving_ads') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#pause-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-pause-btn" class="btn btn-warning">{{ __('admin.ad_campaigns.pause') }}</button>
        </div>
    </x-modal>

    <x-modal id="resume-modal" title="{{ __('admin.ad_campaigns.resume_campaign_title') }}" size="sm">
        <p class="text-sm text-gray-600">{{ __('admin.ad_campaigns.resume') }} <strong id="resume-campaign-name"></strong>? {{ __('admin.ad_campaigns.will_start_serving_ads_again') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#resume-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-resume-btn" class="btn btn-success">{{ __('admin.ad_campaigns.resume') }}</button>
        </div>
    </x-modal>

@endsection
