@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/ad-campaigns.js', 'resources/js/components/select2.js'])
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
            addProduct: @json(__('admin.ad_campaigns.add_product')),
            removeProductConfirm: @json(__('admin.ad_campaigns.remove_product_confirm')),
            productAdded: @json(__('admin.ad_campaigns.product_added')),
            productRemoved: @json(__('admin.ad_campaigns.product_removed')),
            failedToAddProduct: @json(__('admin.ad_campaigns.failed_to_add_product')),
            failedToRemoveProduct: @json(__('admin.ad_campaigns.failed_to_remove_product')),
            remove: @json(__('common.remove')),
        });
    </script>
@endpush

@section('title', __('admin.ad_campaigns.campaign_title', ['name' => $campaign->name]))

@section('content')

    {{-- ─── Breadcrumb / header ─────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.ad-campaigns.index') }}" class="hover:text-primary-600">{{ __('admin.ad_campaigns.title') }}</a>
                <span>/</span>
                <span class="text-gray-800 font-medium truncate max-w-xs">{{ $campaign->name }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $campaign->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __('admin.ad_campaigns.by') }} <strong>{{ $campaign->vendor?->store_name ?? '—' }}</strong>
                @if($campaign->country)
                    · {{ $campaign->country->flag_emoji ?? '' }} {{ $campaign->country->name_en }}
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if($campaign->status->value === 'pending_review')
                <button type="button"
                    class="btn btn-success js-approve-btn"
                    data-url="{{ route('admin.ad-campaigns.approve', $campaign->id) }}"
                    data-name="{{ e($campaign->name) }}">
                    {{ __('admin.ad_campaigns.approve_campaign_btn') }}
                </button>
                <button type="button"
                    class="btn btn-danger js-reject-btn"
                    data-url="{{ route('admin.ad-campaigns.reject', $campaign->id) }}"
                    data-name="{{ e($campaign->name) }}">
                    {{ __('admin.ad_campaigns.reject_campaign_btn') }}
                </button>
            @elseif($campaign->status->value === 'active')
                <button type="button"
                    class="btn btn-warning js-pause-btn"
                    data-url="{{ route('admin.ad-campaigns.pause', $campaign->id) }}"
                    data-name="{{ e($campaign->name) }}">
                    {{ __('admin.ad_campaigns.pause_campaign_btn') }}
                </button>
            @elseif($campaign->status->value === 'paused')
                <button type="button"
                    class="btn btn-success js-resume-btn"
                    data-url="{{ route('admin.ad-campaigns.resume', $campaign->id) }}"
                    data-name="{{ e($campaign->name) }}">
                    {{ __('admin.ad_campaigns.resume_campaign_btn') }}
                </button>
            @endif
            <a href="{{ route('admin.ad-campaigns.index') }}" class="btn btn-secondary">{{ __('admin.ad_campaigns.back') }}</a>
        </div>
    </div>

    {{-- ─── Status banner for rejected campaigns ──────────────────────────────── --}}
    @if($campaign->status->value === 'rejected' && $campaign->rejection_reason)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <strong>{{ __('admin.ad_campaigns.rejection_reason_shown') }}</strong> {{ $campaign->rejection_reason }}
        </div>
    @endif

    {{-- ─── Tabs ────────────────────────────────────────────────────────────────── --}}
    <div
        x-data="{ activeTab: 'overview' }"
        class="space-y-5">

        {{-- Tab bar --}}
        <div class="flex border-b border-gray-200 gap-1 overflow-x-auto">
            @foreach([
                'overview'    => __('admin.ad_campaigns.tab_overview'),
                'products'    => __('admin.ad_campaigns.tab_products', ['n' => $campaign->products->count()]),
                'keywords'    => __('admin.ad_campaigns.tab_keywords', ['n' => $campaign->keywords->count()]),
                'categories'  => __('admin.ad_campaigns.tab_categories', ['n' => $campaign->categoryTargets->count()]),
                'performance' => __('admin.ad_campaigns.tab_performance'),
                'fraud'       => __('admin.ad_campaigns.tab_fraud', ['n' => $campaign->fraudPatterns->count()]),
            ] as $tab => $label)
                <button
                    type="button"
                    @click="activeTab = '{{ $tab }}'"
                    :class="activeTab === '{{ $tab }}' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="whitespace-nowrap px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ═══════════ TAB: OVERVIEW ═══════════ --}}
        <div x-show="activeTab === 'overview'" x-transition>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Campaign details --}}
                <div class="lg:col-span-2 space-y-5">

                    <x-card title="{{ __('admin.ad_campaigns.campaign_details') }}">
                        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.type') }}</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-700">
                                        {{ strtoupper($campaign->type->value) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.status') }}</dt>
                                <dd>
                                    @php
                                        $statusColors = ['active'=>'success','pending_review'=>'warning','paused'=>'gray','rejected'=>'danger','ended'=>'gray','draft'=>'gray'];
                                        $sc = $statusColors[$campaign->status->value] ?? 'gray';
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">
                                        {{ $campaign->status->label() }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.targeting_type') }}</dt>
                                <dd class="font-medium">{{ $campaign->targeting_type->label() }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.total_budget') }}</dt>
                                <dd class="font-semibold">{{ number_format($campaign->budget_total, 2) }} {{ $currency }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.daily_budget') }}</dt>
                                <dd class="font-semibold">
                                    {{ $campaign->budget_daily ? number_format($campaign->budget_daily, 2) . ' ' . $currency : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.bid') }}</dt>
                                <dd class="font-semibold">{{ number_format($campaign->bid, 4) }} {{ $currency }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.spent_total') }}</dt>
                                <dd class="font-semibold text-orange-600">{{ number_format($campaign->budget_spent_total, 2) }} {{ $currency }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.spent_today') }}</dt>
                                <dd class="font-semibold">{{ number_format($campaign->budget_spent_today, 2) }} {{ $currency }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.utilization') }}</dt>
                                @php
                                    $util = $campaign->budget_total > 0 ? min(100, round($campaign->budget_spent_total / $campaign->budget_total * 100, 1)) : 0;
                                    $barClass = $util >= 90 ? 'bg-red-500' : ($util >= 70 ? 'bg-yellow-500' : 'bg-green-500');
                                @endphp
                                <dd>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                                            <div class="{{ $barClass }} h-1.5 rounded-full" style="width:{{ $util }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium">{{ $util }}%</span>
                                    </div>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.starts_at') }}</dt>
                                <dd>{{ $campaign->starts_at ? $campaign->starts_at->format('d M Y H:i') : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.ends_at') }}</dt>
                                <dd>{{ $campaign->ends_at ? $campaign->ends_at->format('d M Y H:i') : '—' }}</dd>
                            </div>
                            @if($campaign->approvedByAdmin)
                                <div>
                                    <dt class="text-gray-500 text-xs uppercase font-medium mb-0.5">{{ __('admin.ad_campaigns.approved_by') }}</dt>
                                    <dd>{{ $campaign->approvedByAdmin->name ?? '—' }}</dd>
                                </div>
                            @endif
                        </dl>
                    </x-card>

                    {{-- 7-day performance summary --}}
                    <x-card title="{{ __('admin.ad_campaigns.performance_7d') }}">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @php
                                $ctr7 = $perfSummary['ctr'] ?? 0;
                                $acos7 = $perfSummary['acos'] ?? 0;
                            @endphp
                            <div class="text-center p-3 rounded-lg bg-gray-50">
                                <div class="text-2xl font-bold text-gray-800">{{ number_format($perfSummary['impressions']) }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('admin.ad_campaigns.impressions') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50">
                                <div class="text-2xl font-bold text-gray-800">{{ number_format($perfSummary['clicks']) }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('admin.ad_campaigns.clicks') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50">
                                <div class="text-2xl font-bold text-gray-800">{{ number_format($perfSummary['conversions']) }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('admin.ad_campaigns.conversions') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50">
                                <div class="text-2xl font-bold text-primary-600">{{ number_format($perfSummary['spend'], 2) }} {{ $currency }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('admin.ad_campaigns.spend') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50">
                                <div class="text-2xl font-bold text-gray-800">{{ number_format((float)$ctr7 * 100, 2) }}%</div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('admin.ad_campaigns.ctr') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50">
                                <div class="text-2xl font-bold text-gray-800">{{ number_format((float)$acos7 * 100, 2) }}%</div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('admin.ad_campaigns.acos') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50 sm:col-span-2">
                                <div class="text-2xl font-bold text-success-600">{{ number_format($perfSummary['revenue_attributed'], 2) }} {{ $currency }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('admin.ad_campaigns.revenue_attributed') }}</div>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- Quality score sidebar --}}
                <div class="space-y-5">
                    <x-card title="{{ __('admin.ad_campaigns.quality_score_title') }}">
                        @if($qualityScore)
                            @php
                                $scores = [
                                    __('admin.ad_campaigns.quality_overall')   => $qualityScore->score,
                                    __('admin.ad_campaigns.quality_ctr')       => $qualityScore->ctr_score,
                                    __('admin.ad_campaigns.quality_relevance') => $qualityScore->relevance_score,
                                    __('admin.ad_campaigns.quality_landing')   => $qualityScore->landing_score,
                                    __('admin.ad_campaigns.quality_vendor')    => $qualityScore->vendor_score,
                                ];
                            @endphp
                            <div class="space-y-3">
                                @foreach($scores as $label => $score)
                                    @php
                                        $pct = min(100, (float)$score * 10);
                                        $color = $score >= 7 ? 'success' : ($score >= 4 ? 'warning' : 'danger');
                                    @endphp
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-gray-600">{{ $label }}</span>
                                            <span class="font-semibold text-{{ $color }}-600">{{ $score }}</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                                            <div class="bg-{{ $color }}-500 h-1.5 rounded-full" style="width:{{ $pct }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                                <p class="text-xs text-gray-400 mt-2">{{ __('admin.ad_campaigns.last_calculated') }}: {{ $qualityScore->calculated_at ? \Carbon\Carbon::parse($qualityScore->calculated_at)->diffForHumans() : '—' }}</p>
                            </div>
                        @else
                            <p class="text-sm text-gray-400 py-4 text-center">{{ __('admin.ad_campaigns.no_quality_score') }}</p>
                        @endif
                    </x-card>
                </div>
            </div>
        </div>

        {{-- ═══════════ TAB: PRODUCTS ═══════════ --}}
        <div x-show="activeTab === 'products'" x-transition>
            <x-card title="{{ __('admin.ad_campaigns.promoted_products') }}">
                <div class="flex items-center justify-end mb-4">
                    <button type="button" id="add-campaign-product-btn" class="btn btn-primary btn-sm">
                        {{ __('admin.ad_campaigns.add_product') }}
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table id="campaign-products-table" class="w-full text-sm" style="width:100%"
                        data-datatable-url="{{ route('admin.ad-campaigns.products.datatable', $campaign) }}"
                        data-store-url="{{ route('admin.ad-campaigns.products.store', $campaign) }}"
                        data-destroy-url-template="{{ route('admin.ad-campaigns.products.destroy', [$campaign, '__ID__']) }}">
                        <thead>
                            <tr class="border-b border-gray-100 text-start">
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.listing_type') }}</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.product_listing') }}</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.variant') }}</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.product_price') }}</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.active') }}</th>
                                <th class="py-2 text-xs font-medium text-gray-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- ═══════════ TAB: KEYWORDS ═══════════ --}}
        <div x-show="activeTab === 'keywords'" x-transition>
            <x-card title="{{ __('admin.ad_campaigns.keyword_targeting') }}">
                @if($campaign->keywords->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">{{ __('admin.ad_campaigns.no_keywords') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-start">
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.keyword') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.match_type') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.bid_override') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.negative') }}</th>
                                    <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.active') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($campaign->keywords as $kw)
                                    @php
                                        $matchColors = ['broad' => 'gray', 'phrase' => 'primary', 'exact' => 'success'];
                                        $mc = $matchColors[$kw->match_type->value] ?? 'gray';
                                    @endphp
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">{{ $kw->keyword }}</td>
                                        <td class="py-2 pr-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $mc }}-100 text-{{ $mc }}-700">
                                                {{ $kw->match_type->label() }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4">
                                            {{ $kw->bid_override ? number_format($kw->bid_override, 4) . ' ' . $currency : '—' }}
                                        </td>
                                        <td class="py-2 pr-4">
                                            @if($kw->is_negative)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ __('admin.ad_campaigns.negative') }}</span>
                                            @else
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            @if($kw->is_active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">{{ __('admin.ad_campaigns.active') }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('admin.ad_campaigns.off') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- ═══════════ TAB: CATEGORIES ═══════════ --}}
        <div x-show="activeTab === 'categories'" x-transition>
            <x-card title="{{ __('admin.ad_campaigns.category_targeting') }}">
                @if($campaign->categoryTargets->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">{{ __('admin.ad_campaigns.no_category_targets') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-start">
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.category') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.bid_override') }}</th>
                                    <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.active') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($campaign->categoryTargets as $ct)
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            {{ $ct->category?->name_en ?? $ct->category_id }}
                                        </td>
                                        <td class="py-2 pr-4">
                                            {{ $ct->bid_override ? number_format($ct->bid_override, 4) . ' ' . $currency : '—' }}
                                        </td>
                                        <td class="py-2">
                                            @if($ct->is_active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">{{ __('admin.ad_campaigns.active') }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('admin.ad_campaigns.off') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- ═══════════ TAB: PERFORMANCE ═══════════ --}}
        <div x-show="activeTab === 'performance'" x-transition>

            {{-- Chart --}}
            <x-card title="{{ __('admin.ad_campaigns.impressions_clicks_30d') }}" class="mb-5">
                <div style="position:relative; height:280px;">
                    <canvas id="performance-chart"
                        data-labels="{{ json_encode($chartLabels) }}"
                        data-impressions="{{ json_encode($chartImpressions) }}"
                        data-clicks="{{ json_encode($chartClicks) }}">
                    </canvas>
                </div>
            </x-card>

            {{-- Daily stats table --}}
            <x-card title="{{ __('admin.ad_campaigns.daily_breakdown') }}">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-start">
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.marketers.date') }}</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.impressions') }}</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.clicks') }}</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.ctr') }}%</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.conversions') }}</th>
                                <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.spend') }}</th>
                                <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.acos') }}%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($dailyStats as $stat)
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 pr-4 font-medium text-gray-700">{{ \Carbon\Carbon::parse($stat->date)->format('d M Y') }}</td>
                                    <td class="py-2 pr-4">{{ number_format($stat->impressions) }}</td>
                                    <td class="py-2 pr-4">{{ number_format($stat->clicks) }}</td>
                                    <td class="py-2 pr-4">{{ number_format((float)$stat->ctr * 100, 2) }}%</td>
                                    <td class="py-2 pr-4">{{ number_format($stat->conversions) }}</td>
                                    <td class="py-2 pr-4 font-medium">{{ number_format($stat->spend, 2) }} {{ $currency }}</td>
                                    <td class="py-2">{{ number_format((float)$stat->acos * 100, 2) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-gray-400 text-sm">{{ __('admin.ad_campaigns.no_performance_data') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- ═══════════ TAB: FRAUD ALERTS ═══════════ --}}
        <div x-show="activeTab === 'fraud'" x-transition>
            <x-card title="{{ __('admin.ad_campaigns.fraud_patterns') }}">
                @if($campaign->fraudPatterns->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">{{ __('admin.ad_campaigns.no_fraud_patterns') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-start">
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.ip_address') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.clicks_per_hour') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.clicks_per_24h') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.status') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.blocked_at') }}</th>
                                    <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.reason_col') }}</th>
                                    <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.ad_campaigns.action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($campaign->fraudPatterns as $fp)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 font-mono text-xs">{{ $fp->ip_address }}</td>
                                        <td class="py-2 pr-4 {{ $fp->clicks_last_hour >= 20 ? 'text-red-600 font-semibold' : '' }}">{{ $fp->clicks_last_hour }}</td>
                                        <td class="py-2 pr-4 {{ $fp->clicks_last_24h >= 100 ? 'text-red-600 font-semibold' : '' }}">{{ $fp->clicks_last_24h }}</td>
                                        <td class="py-2 pr-4">
                                            @if($fp->is_blocked)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ __('admin.ad_campaigns.blocked') }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">{{ __('admin.ad_campaigns.suspicious') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $fp->blocked_at ? \Carbon\Carbon::parse($fp->blocked_at)->format('d M Y H:i') : '—' }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $fp->block_reason ?? '—' }}</td>
                                        <td class="py-2">
                                            @if(!$fp->is_blocked)
                                                <button type="button"
                                                    class="btn btn-xs btn-danger js-block-ip-btn"
                                                    data-url="{{ route('admin.ad-campaigns.fraud.block', $fp->id) }}"
                                                    data-ip="{{ $fp->ip_address }}">
                                                    {{ __('admin.ad_campaigns.block_ip_btn') }}
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

    </div>{{-- end x-data tabs --}}

    {{-- ─── Shared Modals ──────────────────────────────────────────────────────── --}}
    <x-modal id="approve-modal" title="{{ __('admin.ad_campaigns.approve_campaign_title') }}" size="sm">
        <p class="text-sm text-gray-600">{{ __('admin.marketers.approve') }} <strong id="approve-campaign-name"></strong>? {{ __('admin.ad_campaigns.will_become_active_immediately') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#approve-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-approve-btn" class="btn btn-success">{{ __('admin.marketers.approve') }}</button>
        </div>
    </x-modal>

    <x-modal id="reject-modal" title="{{ __('admin.ad_campaigns.reject_campaign_title') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">{{ __('admin.marketers.reject') }} <strong id="reject-campaign-name"></strong>.</p>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ad_campaigns.rejection_reason') }} <span class="text-red-500">*</span></label>
        <textarea id="reject-reason-input" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.marketers.reject_campaign_reason_placeholder') }}"></textarea>
        <p class="text-xs text-red-500 hidden mt-1" id="reject-reason-error">{{ __('admin.ad_campaigns.reason_required') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#reject-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-reject-btn" class="btn btn-danger">{{ __('admin.marketers.reject') }}</button>
        </div>
    </x-modal>

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

    <x-modal id="add-campaign-product-modal" title="{{ __('admin.ad_campaigns.add_product') }}" size="md">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ad_campaigns.listing_source') }}</label>
            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                <button type="button" id="cp-type-vendor-btn" data-type="vendor"
                    class="cp-type-btn px-4 py-1.5 text-sm font-medium bg-primary-600 text-white">{{ __('admin.ad_campaigns.vendor_listing_option') }}</button>
                <button type="button" id="cp-type-admin-btn" data-type="admin"
                    class="cp-type-btn px-4 py-1.5 text-sm font-medium bg-white text-gray-600">{{ __('admin.ad_campaigns.admin_listing_option') }}</button>
            </div>
            <input type="hidden" id="cp-type-input" value="vendor">
        </div>

        <div id="cp-vendor-field" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ad_campaigns.vendor_listing_option') }}</label>
            <select id="cp-vendor-listing-select" class="form-input w-full"
                data-async-select
                data-config='{"url": "{{ route('admin.ad-campaigns.listings.vendor-search', $campaign) }}", "param": "q", "minLength": 1, "placeholder": "{{ __('admin.ad_campaigns.search_vendor_listing_placeholder') }}"}'>
            </select>
        </div>

        <div id="cp-admin-field" class="mb-4 hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ad_campaigns.admin_listing_option') }}</label>
            <select id="cp-admin-listing-select" class="form-input w-full"
                data-async-select
                data-config='{"url": "{{ route('admin.ad-campaigns.listings.admin-search') }}", "param": "q", "minLength": 1, "placeholder": "{{ __('admin.ad_campaigns.search_admin_listing_placeholder') }}"}'>
            </select>
        </div>

        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#add-campaign-product-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-add-campaign-product-btn" class="btn btn-primary">{{ __('admin.save') }}</button>
        </div>
    </x-modal>

    <x-modal id="block-ip-modal" title="{{ __('admin.ad_campaigns.block_ip_title') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">{{ __('admin.ad_campaigns.block_ip') }} <strong id="block-ip-address" class="font-mono"></strong>?</p>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ad_campaigns.block_reason') }}</label>
        <input type="text" id="block-reason-input" class="form-input w-full text-sm" placeholder="{{ __('admin.ad_campaigns.block_reason_placeholder') }}">
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#block-ip-modal').modal('close')">{{ __('common.cancel') }}</button>
            <button type="button" id="confirm-block-btn" class="btn btn-danger">{{ __('admin.ad_campaigns.block_ip_btn') }}</button>
        </div>
    </x-modal>

@endsection
