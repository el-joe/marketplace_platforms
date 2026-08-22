@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/components/select2.js'])
@endpush

@push('scripts')
    @vite('resources/js/admin/flash-sale-detail.js')
@endpush

@section('title', __('admin.flash_sales.title') . ': ' . $flashSale->name_en)

@section('content')
    @php
        $statusColors = [
            'draft'             => 'gray',
            'submission_open'   => 'primary',
            'submission_closed' => 'warning',
            'under_review'      => 'info',
            'approved'          => 'success',
            'live'              => 'success',
            'ended'             => 'gray',
            'cancelled'         => 'danger',
        ];
        $statusLabels = [
            'draft'             => __('admin.flash_sales.status_draft'),
            'submission_open'   => __('admin.flash_sales.status_submission_open'),
            'submission_closed' => __('admin.flash_sales.status_submission_closed'),
            'under_review'      => __('admin.flash_sales.status_under_review'),
            'approved'          => __('admin.flash_sales.status_approved'),
            'live'              => __('admin.flash_sales.status_live'),
            'ended'             => __('admin.flash_sales.status_ended'),
            'cancelled'         => __('admin.flash_sales.status_cancelled'),
        ];
    @endphp

    {{-- Pass data to JS --}}
    <script>
        window.FLASH_SALE_ID     = '{{ $flashSale->id }}';
        window.FLASH_SALE_STATUS = '{{ $flashSale->status->value }}';
        window.URLS = {
            show:              '{{ route('admin.flash-sales.show', $flashSale->id) }}',
            edit:              '{{ route('admin.flash-sales.edit', $flashSale->id) }}',
            transition:        '{{ route('admin.flash-sales.transition', $flashSale->id) }}',
            inviteVendors:     '{{ route('admin.flash-sales.invite-vendors', $flashSale->id) }}',
            eligibleCount:     '{{ route('admin.flash-sales.eligible-vendor-count', $flashSale->id) }}',
            submissionStats:   '{{ route('admin.flash-sales.submission-stats', $flashSale->id) }}',
            submissionsDt:     '{{ route('admin.flash-sales.submissions.datatable', $flashSale->id) }}',
            submissionsStore:  '{{ route('admin.flash-sales.submissions.store', $flashSale->id) }}',
            searchVendorListings: '{{ route('admin.flash-sales.search.vendor-listings') }}',
            searchVendors:        '{{ route('admin.flash-sales.search.vendors') }}',
            searchAdminListings:  '{{ route('admin.flash-sales.search.admin-listings') }}',
            invitationsDt:     '{{ route('admin.flash-sales.invitations.datatable', $flashSale->id) }}',
            resendInvitation:  '{{ url('/flash-sales/' . $flashSale->id . '/invitations') }}',
            bulkReview:        '{{ route('admin.flash-sales.submissions.bulk-review', $flashSale->id) }}',
            liveData:          '{{ route('admin.flash-sales.live-data', $flashSale->id) }}',
            analyticsData:     '{{ route('admin.flash-sales.analytics-data', $flashSale->id) }}',
            submissionDetail:  '{{ url('/flash-sales/submissions') }}',
        };
        window.MIN_DISCOUNT_PCT = {{ (float) $flashSale->min_discount_pct }};
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            statusSubmitted: @json(__('admin.flash_sales.submitted')),
            statusUnderReview: @json(__('admin.flash_sales.status_under_review')),
            statusApproved: @json(__('admin.flash_sales.status_approved')),
            statusLive: @json(__('admin.flash_sales.status_live')),
            statusSoldOut: @json(__('admin.flash_sales.sold_out')),
            statusRejected: @json(__('admin.flash_sales.status_rejected')),
            statusWithdrawn: @json(__('admin.flash_sales.status_withdrawn')),
            statusEnded: @json(__('admin.flash_sales.status_ended')),
            product: @json(__('admin.flash_sales.product')),
            flashPrice: @json(__('admin.flash_sales.flash_price')),
            originalShort: @json(__('admin.flash_sales.original_short')),
            discShort: @json(__('admin.flash_sales.disc_short')),
            minDiscountShort: @json(__('admin.flash_sales.min_discount_short')),
            qty: @json(__('admin.flash_sales.qty')),
            status: @json(__('admin.status')),
            submitted: @json(__('admin.flash_sales.submitted')),
            reviewBtn: @json(__('admin.flash_sales.review_btn')),
            loading: @json(__('admin.flash_sales.loading')),
            possibleFakeDiscount: @json(__('admin.flash_sales.possible_fake_discount')),
            bulkRejectCount: @json(__('admin.flash_sales.bulk_reject_count')),
            vendor: @json(__('admin.flash_sales.vendor')),
            typeLabel: @json(__('admin.flash_sales.type_label')),
            manualInviteType: @json(__('admin.flash_sales.manual_invite_type')),
            autoInviteType: @json(__('admin.flash_sales.auto_invite_type')),
            slots: @json(__('admin.flash_sales.slots')),
            invitedLabel: @json(__('admin.flash_sales.invited_label')),
            notifiedLabel: @json(__('admin.flash_sales.notified_label')),
            respondedLabel: @json(__('admin.flash_sales.responded_label')),
            viewReason: @json(__('admin.flash_sales.view_reason')),
            resend: @json(__('admin.flash_sales.resend')),
            resendNotificationTooltip: @json(__('admin.flash_sales.resend_notification_tooltip')),
            notificationQueued: @json(__('admin.flash_sales.notification_queued')),
            resendFailed: @json(__('admin.flash_sales.resend_failed')),
            statusPending: @json(__('admin.flash_sales.pending')),
            statusAccepted: @json(__('admin.flash_sales.accepted')),
            statusDeclined: @json(__('admin.flash_sales.declined')),
            failedLoadPricing: @json(__('admin.flash_sales.failed_load_pricing')),
            noPriceHistoryData: @json(__('admin.flash_sales.no_price_history_data')),
            acknowledgeWarningRequired: @json(__('admin.flash_sales.acknowledge_warning_required')),
            saving: @json(__('admin.flash_sales.saving')),
            confirmDecision: @json(__('admin.flash_sales.confirm_decision')),
            reviewFailed: @json(__('admin.flash_sales.review_failed')),
            decisionSaved: @json(__('admin.flash_sales.decision_saved')),
            bulkRejectResult: @json(__('admin.flash_sales.bulk_reject_result')),
            bulkRejectFailed: @json(__('admin.flash_sales.bulk_reject_failed')),
            confirmGenericAction: @json(__('admin.flash_sales.confirm_generic_action')),
            statusUpdated: @json(__('admin.flash_sales.status_updated')),
            transitionFailed: @json(__('admin.flash_sales.transition_failed')),
            failedLoadEligibleCount: @json(__('admin.flash_sales.failed_load_eligible_count')),
            inviting: @json(__('admin.flash_sales.inviting')),
            sendInvitations: @json(__('admin.flash_sales.send_invitations')),
            vendorsInvitedResult: @json(__('admin.flash_sales.vendors_invited_result')),
            inviteFailed: @json(__('admin.flash_sales.invite_failed')),
            activeCriteriaHint: @json(__('admin.flash_sales.active_criteria_hint')),
            noCriteriaHint: @json(__('admin.flash_sales.no_criteria_hint')),
            minDiscountCriteria: @json(__('admin.flash_sales.min_discount_criteria')),
            vendorsInvitedGeneric: @json(__('admin.flash_sales.vendors_invited_generic')),
            inviteVendorsFailed: @json(__('admin.flash_sales.invite_vendors_failed')),
            noDataYet: @json(__('admin.flash_sales.no_data_yet')),
            ended: @json(__('admin.flash_sales.ended')),
            analyticsUnavailable: @json(__('admin.flash_sales.analytics_unavailable')),
            noDailyData: @json(__('admin.flash_sales.no_daily_data')),
            addProducts: @json(__('admin.flash_sales.add_products')),
            selectVendorFirst: @json(__('admin.flash_sales.select_vendor_first')),
            addProduct: @json(__('admin.flash_sales.add_product')),
            adding: @json(__('admin.flash_sales.adding')),
            submissionCreatedMessage: @json(__('admin.flash_sales.submission_created_message')),
            submissionCreateFailed: @json(__('admin.flash_sales.submission_create_failed')),
            typeAdmin: @json(__('admin.flash_sales.type_admin')),
            typeVendorPrefix: @json(__('admin.flash_sales.type_vendor_prefix')),
        });
    </script>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- MAIN COLUMN --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 space-y-4">

            {{-- Tab nav --}}
            <div x-data="{ tab: 'submissions' }">
                <nav class="flex gap-1 border-b border-gray-200 mb-5 overflow-x-auto">
                    @foreach([
                        'submissions'  => ['label' => __('admin.flash_sales.submissions_tab'),  'icon' => 'inbox-stack'],
                        'invitations'  => ['label' => __('admin.flash_sales.invitations_tab'),  'icon' => 'envelope'],
                        'live'         => ['label' => __('admin.flash_sales.live_monitor'), 'icon' => 'signal'],
                        'analytics'    => ['label' => __('admin.flash_sales.analytics'),    'icon' => 'chart-bar'],
                        'settings'     => ['label' => __('admin.flash_sales.settings'),     'icon' => 'cog-6-tooth'],
                    ] as $key => $cfg)
                        <button type="button"
                            @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}'
                                ? 'border-b-2 border-primary-600 text-primary-700 font-semibold'
                                : 'text-gray-500 hover:text-gray-700'"
                            class="flex items-center gap-1.5 px-4 py-2.5 text-sm transition-colors whitespace-nowrap">
                            <x-heroicon name="{{ $cfg['icon'] }}" class="w-4 h-4" />
                            {{ $cfg['label'] }}
                            @if($key === 'submissions')
                                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                    {{ $submissionStats->sum() }}
                                </span>
                            @elseif($key === 'invitations')
                                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                    {{ $invitationCount }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </nav>

                {{-- ─── Submissions tab ──────────────────────────────────────── --}}
                <div x-show="tab === 'submissions'" x-cloak>

                    {{-- Status strip --}}
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        @php
                            $pillColors = [
                                'submitted'    => 'bg-gray-100 text-gray-700',
                                'under_review' => 'bg-amber-100 text-amber-800',
                                'approved'     => 'bg-primary-100 text-primary-800',
                                'live'         => 'bg-emerald-100 text-emerald-800',
                                'sold_out'     => 'bg-orange-100 text-orange-800',
                                'rejected'     => 'bg-red-100 text-red-800',
                                'withdrawn'    => 'bg-gray-100 text-gray-500',
                                'ended'        => 'bg-gray-100 text-gray-500',
                            ];
                            $submissionStatusLabels = [
                                'submitted'    => __('admin.flash_sales.submitted'),
                                'under_review' => __('admin.flash_sales.status_under_review'),
                                'approved'     => __('admin.flash_sales.status_approved'),
                                'live'         => __('admin.flash_sales.status_live'),
                                'sold_out'     => __('admin.flash_sales.sold_out'),
                                'rejected'     => __('admin.flash_sales.status_rejected'),
                                'withdrawn'    => __('admin.flash_sales.status_withdrawn'),
                                'ended'        => __('admin.flash_sales.status_ended'),
                            ];
                        @endphp
                        @foreach($submissionStats as $status => $count)
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pillColors[$status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $submissionStatusLabels[$status] ?? ucwords(str_replace('_', ' ', $status)) }}
                                <span class="font-bold">{{ $count }}</span>
                            </span>
                        @endforeach

                        <div class="{{ app()->getLocale() == 'ar' ? 'mr' : 'ml' }}-auto flex items-center gap-2">
                            <select id="sub-filter-status" class="form-select form-select-sm">
                                <option value="">{{ __('admin.flash_sales.all_statuses') }}</option>
                                @foreach([
                                    'submitted'    => $submissionStatusLabels['submitted'],
                                    'under_review' => $submissionStatusLabels['under_review'],
                                    'approved'     => $submissionStatusLabels['approved'],
                                    'rejected'     => $submissionStatusLabels['rejected'],
                                    'live'         => $submissionStatusLabels['live'],
                                    'sold_out'     => $submissionStatusLabels['sold_out'],
                                    'ended'        => $submissionStatusLabels['ended'],
                                ] as $v => $l)
                                    <option value="{{ $v }}">{{ $l }}</option>
                                @endforeach
                            </select>
                            @if(auth('admin')->user()->can('flash_sales.review_submissions'))
                                <button type="button" id="btn-bulk-reject" class="btn btn-danger btn-sm hidden">
                                    {{ __('admin.flash_sales.bulk_reject') }}
                                </button>
                            @endif
                            @if(auth('admin')->user()->can('flash_sales.edit'))
                                <button type="button" data-modal-open="add-product-modal" class="btn btn-primary btn-sm">
                                    {{ __('admin.flash_sales.add_products') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                    <table id="submissions-table" class="w-full text-sm"></table>
                    </div>
                </div>

                {{-- ─── Invitations tab ──────────────────────────────────────── --}}
                <div x-show="tab === 'invitations'" x-cloak>

                    {{-- Stat cards --}}
                    @php
                        $invStatConfig = [
                            'pending'   => ['label' => __('admin.flash_sales.pending'),   'color' => 'bg-amber-50 border-amber-200',   'dot' => 'bg-amber-400',   'text' => 'text-amber-800'],
                            'accepted'  => ['label' => __('admin.flash_sales.accepted'),  'color' => 'bg-emerald-50 border-emerald-200','dot' => 'bg-emerald-500', 'text' => 'text-emerald-800'],
                            'declined'  => ['label' => __('admin.flash_sales.declined'),  'color' => 'bg-red-50 border-red-200',       'dot' => 'bg-red-400',     'text' => 'text-red-800'],
                            'submitted' => ['label' => __('admin.flash_sales.submitted'), 'color' => 'bg-blue-50 border-blue-200',     'dot' => 'bg-blue-500',    'text' => 'text-blue-800'],
                        ];
                    @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                        @foreach($invStatConfig as $status => $cfg)
                            <div class="rounded-xl border {{ $cfg['color'] }} px-4 py-3 flex items-center gap-3">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $cfg['dot'] }}"></span>
                                <div>
                                    <p class="text-xs font-medium {{ $cfg['text'] }}">{{ $cfg['label'] }}</p>
                                    <p class="text-2xl font-bold {{ $cfg['text'] }} leading-tight">{{ $invitationStats[$status] ?? 0 }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <x-card>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <p class="text-sm text-gray-500">{{ __('admin.flash_sales.vendors_invited_total', ['count' => $invitationCount]) }}</p>
                                <select id="inv-filter-status" class="form-select form-select-sm">
                                    <option value="">{{ __('admin.flash_sales.all_statuses') }}</option>
                                    @foreach($invStatConfig as $status => $cfg)
                                        <option value="{{ $status }}">{{ $cfg['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if(!in_array($flashSale->status->value, ['ended', 'cancelled']))
                                <div class="flex gap-2">
                                    <button type="button" id="btn-auto-invite" class="btn btn-secondary btn-sm">
                                        <x-heroicon name="user-group" class="w-4 h-4 mr-1.5" />
                                        {{ __('admin.flash_sales.auto_invite_eligible') }}
                                    </button>
                                    <button type="button" data-modal-open="manual-invite-modal" class="btn btn-ghost btn-sm">
                                        <x-heroicon name="user-plus" class="w-4 h-4 mr-1.5" />
                                        {{ __('admin.flash_sales.invite_manually') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                        <div class="overflow-x-auto">
                        <table id="invitations-table" class="w-full text-sm"></table>
                        </div>
                    </x-card>
                </div>

                {{-- ─── Live Monitor tab ─────────────────────────────────────── --}}
                <div x-show="tab === 'live'" x-cloak>
                    @if($flashSale->status->value === 'live')
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <x-stat-card label="{{ __('admin.flash_sales.units_sold') }}"     id="live-units"     value="—" color="success" icon="shopping-bag" />
                            <x-stat-card label="{{ __('admin.flash_sales.revenue') }}"        id="live-revenue"   value="—" color="primary" icon="currency-dollar" />
                            <x-stat-card label="{{ __('admin.flash_sales.sold_out') }}"       id="live-sold-out"  value="—" color="warning" icon="x-circle" />
                            <x-stat-card label="{{ __('admin.flash_sales.time_remaining') }}" id="live-countdown" value="—" color="info"    icon="clock" />
                        </div>

                        <x-card title="{{ __('admin.flash_sales.top_products') }}">
                            <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-start">{{ __('admin.flash_sales.product') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.flash_sales.sold') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.flash_sales.remaining') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.flash_sales.revenue') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="live-top-tbody" class="divide-y divide-gray-100">
                                    <tr><td colspan="4" class="text-center py-8 text-gray-400 text-sm italic">{{ __('admin.flash_sales.loading') }}</td></tr>
                                </tbody>
                            </table>
                            </div>
                        </x-card>
                    @else
                        <x-card>
                            <div class="text-center py-12 text-gray-400">
                                <x-heroicon name="signal" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                                <p class="text-sm">{{ __('admin.flash_sales.live_monitor_unavailable') }}</p>
                                <p class="text-xs mt-1">{{ __('admin.flash_sales.current_status', ['status' => $statusLabels[$flashSale->status->value] ?? $flashSale->status->value]) }}</p>
                            </div>
                        </x-card>
                    @endif
                </div>

                {{-- ─── Analytics tab ────────────────────────────────────────── --}}
                <div x-show="tab === 'analytics'" x-cloak>
                    @if(in_array($flashSale->status->value, ['live', 'ended']))
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                            <x-stat-card label="{{ __('admin.flash_sales.total_units_sold') }}"    id="an-units"      value="—" color="primary" icon="shopping-bag" />
                            <x-stat-card label="{{ __('admin.flash_sales.gross_revenue') }}"       id="an-revenue"    value="—" color="success" icon="currency-dollar" />
                            <x-stat-card label="{{ __('admin.flash_sales.discount_given') }}"      id="an-discount"   value="—" color="warning" icon="tag" />
                            <x-stat-card label="{{ __('admin.flash_sales.platform_commission') }}" id="an-commission" value="—" color="info"    icon="building-library" />
                            <x-stat-card label="{{ __('admin.flash_sales.vendor_payout') }}"       id="an-payout"     value="—" color="success" icon="banknotes" />
                            <x-stat-card label="{{ __('admin.flash_sales.avg_conversion') }}"      id="an-conversion" value="—" color="gray"    icon="arrow-trending-up" />
                        </div>

                        <x-card title="{{ __('admin.flash_sales.daily_breakdown') }}">
                            <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-start">{{ __('admin.flash_sales.date') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.flash_sales.units') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.flash_sales.revenue') }}</th>
                                        <th class="px-4 py-2 text-end">{{ __('admin.flash_sales.discount') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="analytics-tbody">
                                    <tr><td colspan="4" class="text-center py-8 text-gray-400 text-sm italic">{{ __('admin.flash_sales.loading') }}</td></tr>
                                </tbody>
                            </table>
                            </div>
                        </x-card>
                    @else
                        <x-card>
                            <div class="text-center py-12 text-gray-400">
                                <x-heroicon name="chart-bar" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                                <p class="text-sm">{{ __('admin.flash_sales.analytics_unavailable') }}</p>
                            </div>
                        </x-card>
                    @endif
                </div>

                {{-- ─── Settings tab ─────────────────────────────────────────── --}}
                <div x-show="tab === 'settings'" x-cloak>
                    <x-card title="{{ __('admin.flash_sales.flash_sale_details') }}">
                        <div class="grid grid-cols-1 gap-y-4 sm:grid-cols-2 text-sm">
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.name_en') }}</p>
                                <p class="font-medium text-gray-900">{{ $flashSale->name_en }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.name_ar') }}</p>
                                <p class="font-medium text-gray-900 text-end" dir="rtl">{{ $flashSale->name_ar }}</p>
                            </div>
                            @if($flashSale->description_en)
                                <div class="sm:col-span-2">
                                    <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.description_en') }}</p>
                                    <p class="text-gray-700">{{ $flashSale->description_en }}</p>
                                </div>
                            @endif
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.submission_opens') }}</p>
                                <p class="text-gray-700">{{ $flashSale->submission_opens_at?->format('M j, Y H:i') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.submission_closes') }}</p>
                                <p class="text-gray-700">{{ $flashSale->submission_closes_at?->format('M j, Y H:i') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.review_deadline') }}</p>
                                <p class="text-gray-700">{{ $flashSale->review_deadline_at?->format('M j, Y H:i') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.sale_starts') }}</p>
                                <p class="text-gray-700">{{ $flashSale->sale_starts_at?->format('M j, Y H:i') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.sale_ends') }}</p>
                                <p class="text-gray-700">{{ $flashSale->sale_ends_at?->format('M j, Y H:i') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.min_discount') }}</p>
                                <p class="font-medium text-gray-900">{{ $flashSale->min_discount_pct }}%</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.max_products_vendor') }}</p>
                                <p class="font-medium text-gray-900">{{ $flashSale->max_products_per_seller ?? '∞' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.max_total_slots') }}</p>
                                <p class="font-medium text-gray-900">{{ $flashSale->max_total_slots ?? '∞' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.commission_override') }}</p>
                                <p class="font-medium text-gray-900">{{ $flashSale->commission_override_pct ? $flashSale->commission_override_pct . '%' : __('admin.default') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.min_seller_rating') }}</p>
                                <p class="font-medium text-gray-900">{{ $flashSale->min_seller_rating ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.eligible_tiers_label') }}</p>
                                <p class="font-medium text-gray-900">
                                    {{ !empty($flashSale->eligible_seller_tiers) ? implode(', ', $flashSale->eligible_seller_tiers) : __('common.all') }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.eligible_categories') }}</p>
                                <p class="font-medium text-gray-900">
                                    {{ !empty($flashSale->eligible_categories) ? __('admin.flash_sales.categories_selected', ['count' => count($flashSale->eligible_categories)]) : __('common.all') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.featured') }}</p>
                                <p class="font-medium">{{ $flashSale->is_featured ? __('admin.flash_sales.yes') : __('admin.flash_sales.no') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.exclusive') }}</p>
                                <p class="font-medium">{{ $flashSale->is_exclusive ? __('admin.flash_sales.yes_invite_only') : __('admin.flash_sales.no') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs mb-0.5">{{ __('admin.flash_sales.price_drop_required') }}</p>
                                <p class="font-medium">{{ $flashSale->price_drop_required ? __('admin.flash_sales.yes') : __('admin.flash_sales.no') }}</p>
                            </div>
                        </div>

                        @if(!in_array($flashSale->status->value, ['live', 'ended', 'cancelled']))
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <a href="{{ route('admin.flash-sales.edit', $flashSale->id) }}" class="btn btn-secondary btn-sm">
                                    <x-heroicon name="pencil-square" class="w-4 h-4 mr-1.5" />
                                    {{ __('admin.flash_sales.edit_settings') }}
                                </a>
                            </div>
                        @endif
                    </x-card>
                </div>

            </div>{{-- /tabs --}}
        </div>{{-- /main column --}}

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SIDEBAR --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-80 flex-shrink-0 space-y-4 lg:sticky lg:top-20">

            {{-- Summary card --}}
            <x-card title="{{ $flashSale->name_en }}">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">{{ __('admin.status') }}</span>
                        <x-badge :color="$statusColors[$flashSale->status->value] ?? 'gray'">
                            {{ $statusLabels[$flashSale->status->value] ?? $flashSale->status->value }}
                        </x-badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('admin.country') }}</span>
                        <span class="text-gray-700">{{ $flashSale->country?->name_en ?? __('common.all') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('admin.flash_sales.approved_slots') }}</span>
                        <span class="font-mono font-medium">{{ $flashSale->approved_slots_count }} / {{ $flashSale->max_total_slots ?? '∞' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('admin.flash_sales.submissions') }}</span>
                        <span class="font-mono font-medium">{{ $submissionStats->sum() }}</span>
                    </div>
                    @if($flashSale->is_featured)
                        <div class="flex items-center gap-1.5 text-amber-700 text-xs font-medium">
                            <x-heroicon name="star" class="w-3.5 h-3.5" />
                            {{ __('admin.flash_sales.featured') }}
                        </div>
                    @endif
                    @if($flashSale->is_exclusive)
                        <div class="flex items-center gap-1.5 text-purple-700 text-xs font-medium">
                            <x-heroicon name="lock-closed" class="w-3.5 h-3.5" />
                            {{ __('admin.flash_sales.exclusive_invite_only_badge') }}
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Timeline --}}
            <x-card title="{{ __('admin.flash_sales.timeline') }}">
                <div class="space-y-2 text-xs text-gray-600">
                    @foreach([
                        __('admin.flash_sales.submissions_open')  => $flashSale->submission_opens_at,
                        __('admin.flash_sales.submissions_close') => $flashSale->submission_closes_at,
                        __('admin.flash_sales.review_deadline')   => $flashSale->review_deadline_at,
                        __('admin.flash_sales.sale_starts')       => $flashSale->sale_starts_at,
                        __('admin.flash_sales.sale_ends')         => $flashSale->sale_ends_at,
                    ] as $label => $date)
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span>{{ $date?->format('M j, Y H:i') ?? '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Actions --}}
            @if(!in_array($flashSale->status->value, ['ended', 'cancelled']))
                <x-card title="{{ __('common.actions') }}">
                    <div class="space-y-2">
                        @php
                            $actionMap = [
                                'submission_open'   => ['action' => 'open_submissions',  'label' => __('admin.flash_sales.open_submissions'),  'color' => 'primary'],
                                'submission_closed' => ['action' => 'close_submissions', 'label' => __('admin.flash_sales.close_submissions'), 'color' => 'secondary'],
                                'under_review'      => ['action' => 'move_to_review',    'label' => __('admin.flash_sales.move_to_review'),    'color' => 'info'],
                                'approved'          => ['action' => 'mark_approved',     'label' => __('admin.flash_sales.mark_approved'),     'color' => 'success'],
                                'live'              => ['action' => 'start_sale',        'label' => __('admin.flash_sales.launch_sale'),       'color' => 'success'],
                                'ended'             => ['action' => 'end_sale',          'label' => __('admin.flash_sales.end_sale'),          'color' => 'secondary'],
                                'cancelled'         => ['action' => 'cancel',            'label' => __('common.cancel'),            'color' => 'danger'],
                            ];
                        @endphp
                        @foreach($nextStatuses as $next)
                            @php $act = $actionMap[$next['value']] ?? null; @endphp
                            @if($act)
                                <button type="button"
                                    class="btn btn-{{ $act['color'] }} w-full justify-center flash-sale-transition"
                                    data-action="{{ $act['action'] }}"
                                    data-needs-confirm="{{ $act['action'] === 'cancel' ? '1' : '0' }}">
                                    {{ $act['label'] }}
                                </button>
                            @endif
                        @endforeach
                        @if(!in_array($flashSale->status->value, ['cancelled']) && !in_array('cancelled', collect($nextStatuses)->pluck('value')->toArray()))
                            <button type="button"
                                class="btn btn-danger w-full justify-center flash-sale-transition"
                                data-action="cancel"
                                data-needs-confirm="1">
                                {{ __('admin.flash_sales.cancel_sale') }}
                            </button>
                        @endif
                    </div>
                </x-card>
            @endif

            {{-- Meta --}}
            @if($flashSale->createdBy)
                <div class="text-xs text-gray-400 text-center">
                    {{ __('admin.flash_sales.created_by', ['name' => $flashSale->createdBy->name, 'date' => $flashSale->created_at->format('M j, Y')]) }}
                </div>
            @endif

        </div>{{-- /sidebar --}}
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MODALS --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    {{-- Review submission modal --}}
    <x-modal id="review-modal" title="{{ __('admin.flash_sales.review_submission') }}" size="lg">
        <div class="space-y-4">
            <div id="review-product-info" class="flex items-center gap-3 pb-3 border-b border-gray-100 hidden">
                <img id="review-product-img" src="" alt="" class="w-14 h-14 object-cover rounded-lg border border-gray-200">
                <div>
                    <p id="review-product-name" class="font-medium text-gray-900 text-sm"></p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <span id="review-flash-price" class="font-semibold text-primary-600"></span>
                        <span class="mx-1 text-gray-300">{{ __('admin.flash_sales.vs') }}</span>
                        <span id="review-original-price" class="line-through text-gray-400"></span>
                        <span id="review-discount-pct" class="ml-1.5 font-medium text-emerald-600"></span>
                    </p>
                </div>
            </div>

            <div id="review-price-history-wrap" class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('admin.flash_sales.30d_price_history') }}</p>
                <div id="review-price-chart" class="h-16 flex items-end gap-px overflow-hidden">
                    <span class="text-xs text-gray-400">{{ __('admin.flash_sales.loading') }}</span>
                </div>
            </div>

            <div id="fraud-warning" class="hidden rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                <strong>{{ __('admin.flash_sales.pricing_warning') }}</strong>
                <ul id="fraud-reasons" class="mt-1 list-disc list-inside text-xs"></ul>
                <label class="flex items-center gap-2 mt-2 cursor-pointer">
                    <input type="checkbox" id="override-fraud-check" class="form-checkbox">
                    <span>{{ __('admin.flash_sales.acknowledge_risk') }}</span>
                </label>
            </div>

            <div id="review-stock-info" class="text-xs text-gray-500 flex gap-4 hidden">
                <span>{{ __('admin.flash_sales.max_qty') }}: <strong id="review-max-qty">—</strong></span>
                <span>{{ __('admin.flash_sales.qty_sold') }}: <strong id="review-qty-sold">—</strong></span>
                <span>{{ __('admin.flash_sales.qty_remaining') }}: <strong id="review-qty-remaining">—</strong></span>
            </div>

            <div x-data="{ decision: 'approved' }">
                <label class="form-label">{{ __('admin.flash_sales.decision') }}</label>
                <div class="flex gap-3 mt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="decision" value="approved" class="form-radio text-primary-600">
                        <span class="text-sm font-medium text-emerald-700">{{ __('admin.flash_sales.approve') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="decision" value="rejected" class="form-radio text-danger-600">
                        <span class="text-sm font-medium text-red-700">{{ __('admin.flash_sales.reject') }}</span>
                    </label>
                </div>

                <div x-show="decision === 'rejected'" class="mt-3 space-y-3">
                    <div>
                        <label class="form-label">{{ __('admin.flash_sales.rejection_code_required') }} <span class="text-danger-500">*</span></label>
                        <select id="review-rejection-code" class="form-select w-full">
                            <option value="manual_rejection">{{ __('admin.flash_sales.rejection_manual_review') }}</option>
                            <option value="price_too_low">{{ __('admin.flash_sales.rejection_price_too_low') }}</option>
                            <option value="insufficient_discount">{{ __('admin.flash_sales.rejection_insufficient_discount') }}</option>
                            <option value="fake_discount_detected">{{ __('admin.flash_sales.rejection_fake_discount') }}</option>
                            <option value="ineligible_category">{{ __('admin.flash_sales.rejection_ineligible_category') }}</option>
                            <option value="ineligible_vendor">{{ __('admin.flash_sales.rejection_ineligible_vendor') }}</option>
                            <option value="duplicate_submission">{{ __('admin.flash_sales.rejection_duplicate_submission') }}</option>
                            <option value="other">{{ __('admin.flash_sales.rejection_other') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.flash_sales.reason') }}</label>
                        <textarea id="review-rejection-reason" rows="2" class="form-textarea w-full" placeholder="{{ __('admin.flash_sales.reason_for_rejection_placeholder') }}"></textarea>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">{{ __('admin.flash_sales.admin_notes_optional') }}</label>
                    <textarea id="review-admin-notes" rows="2" class="form-textarea w-full" placeholder="{{ __('admin.flash_sales.internal_notes_placeholder') }}"></textarea>
                </div>

                <input type="hidden" id="review-submission-id">
                <input type="hidden" id="review-decision" :value="decision">
            </div>
        </div>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
            <button type="button" id="btn-confirm-review" class="btn btn-primary">{{ __('admin.flash_sales.confirm_decision') }}</button>
        </x-slot:footer>
    </x-modal>

    {{-- Bulk reject modal --}}
    <x-modal id="bulk-reject-modal" title="{{ __('admin.flash_sales.bulk_reject_submissions') }}" size="sm">
        <div class="space-y-3">
            <p class="text-sm text-gray-600">{!! __('admin.flash_sales.reject_count_selected', ['count' => '<strong id="bulk-reject-count">0</strong>']) !!}</p>
            <div>
                <label class="form-label">{{ __('admin.flash_sales.rejection_code_required') }} <span class="text-danger-500">*</span></label>
                <select id="bulk-rejection-code" class="form-select w-full">
                    <option value="manual_rejection">{{ __('admin.flash_sales.rejection_manual_review') }}</option>
                    <option value="insufficient_discount">{{ __('admin.flash_sales.rejection_insufficient_discount') }}</option>
                    <option value="fake_discount_detected">{{ __('admin.flash_sales.rejection_fake_discount') }}</option>
                    <option value="ineligible_category">{{ __('admin.flash_sales.rejection_ineligible_category') }}</option>
                    <option value="other">{{ __('admin.flash_sales.rejection_other') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('admin.flash_sales.reason') }}</label>
                <textarea id="bulk-rejection-reason" rows="2" class="form-textarea w-full"></textarea>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
            <button type="button" id="btn-confirm-bulk-reject" class="btn btn-danger">{{ __('admin.flash_sales.reject_all_selected') }}</button>
        </x-slot:footer>
    </x-modal>

    {{-- Cancel modal --}}
    <x-modal id="cancel-modal" title="{{ __('admin.flash_sales.cancel_flash_sale') }}" size="sm">
        <div class="space-y-3">
            <p class="text-sm text-gray-600">
                {{ __('admin.flash_sales.cancel_confirm', ['name' => $flashSale->name_en]) }}
            </p>
            <div>
                <label class="form-label">{{ __('admin.flash_sales.cancellation_reason') }}</label>
                <textarea id="cancel-reason" rows="3" class="form-textarea w-full" placeholder="{{ __('admin.flash_sales.cancellation_reason_hint') }}"></textarea>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn btn-ghost">{{ __('admin.flash_sales.go_back') }}</button>
            <button type="button" id="btn-confirm-cancel" class="btn btn-danger">{{ __('admin.flash_sales.cancel_sale') }}</button>
        </x-slot:footer>
    </x-modal>

    {{-- Auto-invite confirmation modal --}}
    <x-modal id="auto-invite-modal" title="{{ __('admin.flash_sales.auto_invite_title') }}" size="sm">
        <div class="space-y-3">
            <div id="auto-invite-loading" class="py-6 text-center text-sm text-gray-400">
                {{ __('admin.flash_sales.checking_eligible_vendors') }}
            </div>
            <div id="auto-invite-content" class="hidden space-y-3">
                <p class="text-sm text-gray-700">
                    <span id="auto-invite-count" class="font-bold text-gray-900 text-lg">0</span>
                    {{ __('admin.flash_sales.auto_invite_match_msg') }}
                </p>
                <div id="auto-invite-zero-msg" class="hidden rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                    <strong>{{ __('admin.flash_sales.no_new_vendors') }}</strong>
                    <p class="mt-1 text-xs" id="auto-invite-criteria-hint"></p>
                </div>
                <div id="auto-invite-confirm-area">
                    <p class="text-xs text-gray-500">{{ __('admin.flash_sales.auto_invite_note') }}</p>
                </div>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
            <button type="button" id="btn-confirm-auto-invite" class="btn btn-secondary hidden">
                {{ __('admin.flash_sales.send_invitations') }}
            </button>
        </x-slot:footer>
    </x-modal>

    {{-- Decline reason modal --}}
    <x-modal id="decline-reason-modal" title="{{ __('admin.flash_sales.decline_reason') }}" size="sm">
        <div class="space-y-2">
            <p class="text-sm text-gray-600" id="decline-reason-vendor"></p>
            <blockquote class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800 italic" id="decline-reason-text"></blockquote>
        </div>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.close') }}</button>
        </x-slot:footer>
    </x-modal>

    {{-- Manual invite modal --}}
    <x-modal id="manual-invite-modal" title="{{ __('admin.flash_sales.invite_vendor_manually') }}" size="sm">
        <div class="space-y-3">
            <p class="text-sm text-gray-500">{{ __('admin.flash_sales.enter_vendor_ids') }}</p>
            <div>
                <label class="form-label">{{ __('admin.flash_sales.vendor_ids') }}</label>
                <textarea id="manual-invite-ids" rows="5" class="form-textarea w-full font-mono text-sm" placeholder="{{ __('admin.flash_sales.vendor_ids_placeholder') }}"></textarea>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
            <button type="button" id="btn-confirm-manual-invite" class="btn btn-primary">{{ __('admin.flash_sales.send_invitations') }}</button>
        </x-slot:footer>
    </x-modal>

    {{-- Add Product (manual submission) modal --}}
    <x-modal id="add-product-modal" title="{{ __('admin.flash_sales.add_products') }}" size="md">
        <div class="space-y-4">
            <div>
                <label class="form-label">{{ __('admin.flash_sales.submission_type') }}</label>
                <div class="flex gap-4">
                    <label class="inline-flex items-center gap-1.5 text-sm">
                        <input type="radio" name="submission-type" value="vendor" id="submission-type-vendor" checked>
                        {{ __('admin.flash_sales.submission_type_vendor') }}
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-sm">
                        <input type="radio" name="submission-type" value="admin" id="submission-type-admin">
                        {{ __('admin.flash_sales.submission_type_admin') }}
                    </label>
                </div>
            </div>

            {{-- Vendor listing fields --}}
            <div id="vendor-listing-fields" class="space-y-3">
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.vendor_label') }}</label>
                    <select id="add-product-vendor" data-async-select
                        data-config='{{ json_encode(["url" => route("admin.flash-sales.search.vendors"), "param" => "q", "minLength" => 0, "delay" => 300]) }}'
                        class="form-select w-full">
                        <option value=""></option>
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.vendor_listing_label') }}</label>
                    <select id="add-product-vendor-listing" data-async-select disabled
                        data-config='{{ json_encode(["url" => route("admin.flash-sales.search.vendor-listings"), "param" => "q", "minLength" => 0, "delay" => 300]) }}'
                        class="form-select w-full">
                        <option value=""></option>
                    </select>
                </div>
            </div>

            {{-- Admin listing fields --}}
            <div id="admin-listing-fields" class="space-y-3 hidden">
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.admin_listing_label') }}</label>
                    <select id="add-product-admin-listing" data-async-select
                        data-config='{{ json_encode(["url" => route("admin.flash-sales.search.admin-listings"), "param" => "q", "minLength" => 0, "delay" => 300]) }}'
                        class="form-select w-full">
                        <option value=""></option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.original_price_label') }}</label>
                    <input type="number" id="add-product-original-price" class="form-input w-full" min="0">
                </div>
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.flash_price_label') }}</label>
                    <input type="number" id="add-product-flash-price" class="form-input w-full" min="0">
                </div>
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.max_quantity_total_label') }}</label>
                    <input type="number" id="add-product-max-qty" class="form-input w-full" min="1">
                </div>
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.max_quantity_per_customer_label') }}</label>
                    <input type="number" id="add-product-max-qty-customer" class="form-input w-full" min="1">
                </div>
            </div>

            <div>
                <label class="form-label">{{ __('admin.flash_sales.admin_notes_label') }}</label>
                <textarea id="add-product-admin-notes" rows="2" class="form-textarea w-full"></textarea>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
            <button type="button" id="btn-confirm-add-product" class="btn btn-primary">{{ __('admin.flash_sales.add_product') }}</button>
        </x-slot:footer>
    </x-modal>

@endsection
