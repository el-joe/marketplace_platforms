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

@section('title', __('admin.ad_campaigns.fraud_alerts_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.ad-campaigns.index') }}" class="hover:text-primary-600">{{ __('admin.ad_campaigns.title') }}</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">{{ __('admin.ad_campaigns.fraud_alerts') }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.ad_campaigns.fraud_alerts_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.ad_campaigns.fraud_alerts_desc') }}</p>
        </div>
    </div>

    {{-- ─── Stats ────────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-card title="{{ __('admin.ad_campaigns.suspicious_ips') }}" :value="number_format($stats['unblocked'])"
            iconBg="bg-warning-100 text-warning-600" />
        <x-stat-card title="{{ __('admin.ad_campaigns.blocked_ips') }}" :value="number_format($stats['blocked'])" iconBg="bg-red-100 text-red-600" />
        <x-stat-card title="{{ __('admin.ad_campaigns.total_patterns') }}" :value="number_format($stats['total'])" iconBg="bg-gray-100 text-gray-600" />
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ad_campaigns.search_ip_campaign') }}</label>
                <input type="text" id="fraud-search" class="form-input w-full text-sm" placeholder="{{ __('admin.ad_campaigns.search_ip_placeholder') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ad_campaigns.status') }}</label>
                <select id="fraud-filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.ad_campaigns.all_statuses') }}</option>
                    <option value="0">{{ __('admin.ad_campaigns.suspicious_only') }}</option>
                    <option value="1">{{ __('admin.ad_campaigns.blocked_only') }}</option>
                </select>
            </div>
        </div>
    </x-card>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="fraud-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.ip_address') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.campaign_vendor') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.clicks_hr') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.clicks_per_24h') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.status') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.blocked_at') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.reason_col') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.action') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Block IP Modal ──────────────────────────────────────────────────────── --}}
    <x-modal id="block-ip-modal" title="{{ __('admin.ad_campaigns.block_ip_title') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">
            {{ __('admin.ad_campaigns.block_ip') }} <strong id="block-ip-address" class="font-mono text-gray-800"></strong>?
            {{ __('admin.ad_campaigns.block_ip_desc') }}
        </p>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ad_campaigns.block_reason') }}</label>
        <input type="text" id="block-reason-input" class="form-input w-full text-sm"
            placeholder="{{ __('admin.ad_campaigns.block_reason_placeholder') }}">
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#block-ip-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-block-btn" class="btn btn-danger">{{ __('admin.ad_campaigns.block_ip_btn') }}</button>
        </div>
    </x-modal>

@endsection
