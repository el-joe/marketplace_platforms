@extends('layouts.admin')

@section('title', __('admin.flash_sales.title'))

@push('scripts')
    @vite(['resources/js/components/datatable.js', 'resources/js/admin/flash-sales.js'])
@endpush

@section('content')

    {{-- Stats row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat-card label="{{ __('admin.flash_sales.live_now') }}"            :value="$stats['live_count']"                  color="success" icon="bolt" />
        <x-stat-card label="{{ __('admin.flash_sales.upcoming') }}"            :value="$stats['upcoming_count']"               color="primary" icon="calendar" />
        <x-stat-card label="{{ __('admin.flash_sales.units_this_month') }}"    :value="number_format($stats['total_this_month_units'])" color="info" icon="shopping-bag" />
        <x-stat-card label="{{ __('admin.flash_sales.revenue_this_month') }}"  :value="number_format($stats['total_this_month_revenue'], 2)" color="warning" icon="currency-dollar" />
    </div>

    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div class="flex flex-wrap gap-1" x-data="{ tab: 'all' }">
                @foreach ([
                    'all'               => __('common.all'),
                    'draft'             => __('admin.flash_sales.status_draft'),
                    'submission_open'   => __('admin.flash_sales.status_submission_open'),
                    'under_review'      => __('admin.flash_sales.status_under_review'),
                    'live'              => __('admin.flash_sales.status_live'),
                    'ended'             => __('admin.flash_sales.status_ended'),
                    'cancelled'         => __('admin.flash_sales.status_cancelled'),
                ] as $val => $label)
                    <button
                        @click="tab = '{{ $val }}'; window.filterTable('{{ $val }}')"
                        :class="tab === '{{ $val }}' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm'"
                    >{{ $label }}</button>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <x-export-dropdown />
                @if(auth('admin')->user()->can('flash_sales.create', \App\Models\FlashSale::class))
                    <a href="{{ route('admin.flash-sales.create') }}" class="btn btn-primary btn-sm">
                        + {{ __('admin.flash_sales.new_flash_sale') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-3 mb-4" id="table-filters">
            <input type="text" id="filter-search" class="form-input form-input-sm w-48" placeholder="{{ __('admin.flash_sales.name') }}">
            <select id="filter-country" class="form-select form-select-sm w-44">
                <option value="">{{ __('admin.flash_sales.all_countries') }}</option>
                @foreach($countries as $c)
                    <option value="{{ $c->id }}">{{ $c->name_en }}</option>
                @endforeach
            </select>
            <input type="date" id="filter-date-from" class="form-input form-input-sm w-36" placeholder="{{ __('common.from') }}">
            <input type="date" id="filter-date-to"   class="form-input form-input-sm w-36" placeholder="{{ __('common.to') }}">
        </div>

        <div class="overflow-x-auto">
            <table id="flash-sales-table" class="w-full text-sm"></table>
        </div>
    </x-card>

@endsection

@push('scripts')
<script>
    window.TRANSLATIONS = window.TRANSLATIONS || {};
    Object.assign(window.TRANSLATIONS, {
        name: @json(__('admin.flash_sales.name')),
        country: @json(__('admin.flash_sales.country')),
        status: @json(__('admin.status')),
        submissions: @json(__('admin.flash_sales.submissions')),
        salePeriod: @json(__('admin.flash_sales.sale_period')),
        slots: @json(__('admin.flash_sales.slots')),
        minDisc: @json(__('admin.flash_sales.min_disc')),
        unitsSold: @json(__('admin.flash_sales.units_sold')),
        statusDraft: @json(__('admin.flash_sales.status_draft')),
        statusSubmissionOpen: @json(__('admin.flash_sales.status_submission_open')),
        statusSubmissionClosed: @json(__('admin.flash_sales.status_submission_closed')),
        statusUnderReview: @json(__('admin.flash_sales.status_under_review')),
        statusApproved: @json(__('admin.flash_sales.status_approved')),
        statusLive: @json(__('admin.flash_sales.status_live')),
        statusEnded: @json(__('admin.flash_sales.status_ended')),
        statusCancelled: @json(__('admin.flash_sales.status_cancelled')),
        timelineSubOpens: @json(__('admin.flash_sales.timeline_sub_opens')),
        timelineSubCloses: @json(__('admin.flash_sales.timeline_sub_closes')),
        timelineReview: @json(__('admin.flash_sales.timeline_review')),
        timelineSaleStart: @json(__('admin.flash_sales.timeline_sale_start')),
        timelineSaleEnd: @json(__('admin.flash_sales.timeline_sale_end')),
        creating: @json(__('admin.flash_sales.creating')),
        createFlashSale: @json(__('admin.flash_sales.create_flash_sale')),
        createdMessage: @json(__('admin.flash_sales.created_message')),
        confirmOpenSubmissions: @json(__('admin.flash_sales.confirm_open_submissions')),
        createFailed: @json(__('admin.flash_sales.create_failed')),
        saving: @json(__('admin.flash_sales.saving')),
        saved: @json(__('admin.flash_sales.saved')),
        saveChanges: @json(__('admin.flash_sales.save_changes')),
        saveFailed: @json(__('admin.flash_sales.save_failed')),
        confirmCloseSubmissions: @json(__('admin.flash_sales.confirm_close_submissions')),
        confirmEndSale: @json(__('admin.flash_sales.confirm_end_sale')),
        confirmMarkApproved: @json(__('admin.flash_sales.confirm_mark_approved')),
        confirmStartSale: @json(__('admin.flash_sales.confirm_start_sale')),
        confirmGenericAction: @json(__('admin.flash_sales.confirm_generic_action')),
        statusUpdated: @json(__('admin.flash_sales.status_updated')),
        transitionFailed: @json(__('admin.flash_sales.transition_failed')),
        cancellationReasonRequired: @json(__('admin.flash_sales.cancellation_reason_required')),
        cancelledMessage: @json(__('admin.flash_sales.cancelled_message')),
        cancelFailed: @json(__('admin.flash_sales.cancel_failed')),
        loadingCount: @json(__('admin.flash_sales.loading_count')),
        inviteEligibleVendorsBtn: @json(__('admin.flash_sales.invite_eligible_vendors_btn')),
        noEligibleVendorsFound: @json(__('admin.flash_sales.no_eligible_vendors_found')),
        confirmInviteEligible: @json(__('admin.flash_sales.confirm_invite_eligible')),
        sendingInvitations: @json(__('admin.flash_sales.sending_invitations')),
        vendorsInvitedResult: @json(__('admin.flash_sales.vendors_invited_result')),
        inviteFailed: @json(__('admin.flash_sales.invite_failed')),
        failedLoadEligibleCount: @json(__('admin.flash_sales.failed_load_eligible_count')),
        vendor: @json(__('admin.flash_sales.vendor')),
        notifiedLabel: @json(__('admin.flash_sales.notified_label')),
        respondedLabel: @json(__('admin.flash_sales.responded_label')),
        product: @json(__('admin.flash_sales.product')),
        flashPrice: @json(__('admin.flash_sales.flash_price')),
        originalShort: @json(__('admin.flash_sales.original_short')),
        discount: @json(__('admin.flash_sales.discount')),
        qty: @json(__('admin.flash_sales.qty')),
        reviewBtn: @json(__('admin.flash_sales.review_btn')),
        possibleFakeDiscount: @json(__('admin.flash_sales.possible_fake_discount')),
        loading: @json(__('admin.flash_sales.loading')),
        meetsMinimum: @json(__('admin.flash_sales.meets_minimum')),
        belowMinimum: @json(__('admin.flash_sales.below_minimum')),
        potentialFakeDiscountDetected: @json(__('admin.flash_sales.potential_fake_discount_detected')),
        vsAvg: @json(__('admin.flash_sales.vs_avg')),
        selectDecision: @json(__('admin.flash_sales.select_decision')),
        selectRejectionReason: @json(__('admin.flash_sales.select_rejection_reason')),
        saveDecision: @json(__('admin.flash_sales.save_decision')),
        decisionSaved: @json(__('admin.flash_sales.decision_saved')),
        saveDecisionFailed: @json(__('admin.flash_sales.save_decision_failed')),
        selectAtLeastOneSubmission: @json(__('admin.flash_sales.select_at_least_one_submission')),
        confirmApproveSelected: @json(__('admin.flash_sales.confirm_approve_selected')),
        bulkReviewResult: @json(__('admin.flash_sales.bulk_review_result')),
        bulkApproveFailed: @json(__('admin.flash_sales.bulk_approve_failed')),
        bulkRejectFailed: @json(__('admin.flash_sales.bulk_reject_failed')),
        historicalPrice: @json(__('admin.flash_sales.historical_price')),
        originalPrice: @json(__('admin.flash_sales.original_price')),
        grossRevenue: @json(__('admin.flash_sales.gross_revenue')),
        unitsSoldChart: @json(__('admin.flash_sales.units_sold')),
    });
</script>
<script type="module">
const FLASH_SALES_DATATABLE_URL = '{{ route('admin.flash-sales.datatable') }}';

const STATUS_BADGES = {
    draft:              { label: window.TRANSLATIONS.statusDraft,              color: 'gray'    },
    submission_open:    { label: window.TRANSLATIONS.statusSubmissionOpen,      color: 'primary' },
    submission_closed:  { label: window.TRANSLATIONS.statusSubmissionClosed,    color: 'warning' },
    under_review:       { label: window.TRANSLATIONS.statusUnderReview,        color: 'info'    },
    approved:           { label: window.TRANSLATIONS.statusApproved,           color: 'success' },
    live:               { label: window.TRANSLATIONS.statusLive,               color: 'success' },
    ended:              { label: window.TRANSLATIONS.statusEnded,              color: 'gray'    },
    cancelled:          { label: window.TRANSLATIONS.statusCancelled,          color: 'danger'  },
};

window.filterTable = function filterTable(status) {
    if ($.fn.DataTable.isDataTable('#flash-sales-table')) {
        const dt = $('#flash-sales-table').DataTable();
        dt.ajax.url(
            FLASH_SALES_DATATABLE_URL + (status !== 'all' ? '?status=' + status : '')
        ).load();
    }
};

document.addEventListener('DOMContentLoaded', function () {
    initDataTable('flash-sales-table', {
        url: FLASH_SALES_DATATABLE_URL,
        columns: [
            { data: 'name_en',           title: window.TRANSLATIONS.name,
              render: (d, t, r) => `<a href="${r.show_url}" class="font-medium text-primary-600 hover:underline">${d}</a>` },
            { data: 'country_name',      title: window.TRANSLATIONS.country },
            { data: 'status',            title: window.TRANSLATIONS.status,
              render: (d) => {
                const b = STATUS_BADGES[d] || { label: d, color: 'gray' };
                return `<span class="badge badge-${b.color}">${b.label}</span>`;
              }},
            { data: 'submission_period', title: window.TRANSLATIONS.submissions, orderable: false },
            { data: 'sale_period',       title: window.TRANSLATIONS.salePeriod },
            { data: 'slots',             title: window.TRANSLATIONS.slots, orderable: false },
            { data: 'min_discount_pct',  title: window.TRANSLATIONS.minDisc },
            { data: 'units_sold',        title: window.TRANSLATIONS.unitsSold },
        ],
        serverSideFilters: {
            search:     () => $('#filter-search').val(),
            country_id: () => $('#filter-country').val(),
            date_from:  () => $('#filter-date-from').val(),
            date_to:    () => $('#filter-date-to').val(),
        },
    });

    $('#filter-country, #filter-date-from, #filter-date-to').on('change', function () {
        if ($.fn.DataTable.isDataTable('#flash-sales-table')) {
            $('#flash-sales-table').DataTable().ajax.reload();
        }
    });

    let flashSaleSearchTimer;
    $('#filter-search').on('input', function () {
        clearTimeout(flashSaleSearchTimer);
        flashSaleSearchTimer = setTimeout(function () {
            if ($.fn.DataTable.isDataTable('#flash-sales-table')) {
                $('#flash-sales-table').DataTable().ajax.reload();
            }
        }, 350);
    });
}, { once: true });
</script>
@endpush
