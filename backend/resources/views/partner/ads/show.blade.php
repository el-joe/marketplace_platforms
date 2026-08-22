@extends('layouts.partner')

@section('title', $campaign->name)
@section('page-title', __('partner.ads.title'))

@push('scripts')
    @vite('resources/js/partner/ads.js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        window.PARTNER_TRANSLATIONS = window.PARTNER_TRANSLATIONS || {};
        Object.assign(window.PARTNER_TRANSLATIONS, {
            confirmPauseCampaign: @json(__('partner.ads.confirm_pause_campaign')),
            confirmResumeCampaign: @json(__('partner.ads.confirm_resume_campaign')),
        });
    </script>
@endpush

@section('content')
@php
    $statusMap = [
        'draft'            => ['label' => __('partner.ads.statuses.draft'),            'cls' => 'bg-gray-100 text-gray-700'],
        'pending_review'   => ['label' => __('partner.ads.statuses.pending_review'), 'cls' => 'bg-amber-100 text-amber-700'],
        'active'           => ['label' => __('partner.ads.statuses.active'),             'cls' => 'bg-emerald-100 text-emerald-700'],
        'paused'           => ['label' => __('partner.ads.statuses.paused'),           'cls' => 'bg-blue-100 text-blue-700'],
        'budget_exhausted' => ['label' => __('partner.ads.statuses.budget_exhausted'),    'cls' => 'bg-red-100 text-red-700'],
        'ended'            => ['label' => __('partner.ads.statuses.ended'),           'cls' => 'bg-gray-100 text-gray-500'],
        'rejected'         => ['label' => __('partner.ads.statuses.rejected'),           'cls' => 'bg-red-100 text-red-700'],
    ];
    $st = $statusMap[$campaign->status->value] ?? ['label' => $campaign->status->value, 'cls' => 'bg-gray-100 text-gray-600'];
    $adCurrency = $campaign->country?->currency_code ?? '';
@endphp

<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    <script>
        window.ADS_CONFIG = {
            performanceUrl:  "{{ route('partner.ads.performance', $campaign->id) }}",
            qualityScoreUrl: "{{ route('partner.ads.quality-score', $campaign->id) }}",
            pauseUrl:        "{{ route('partner.ads.pause', $campaign->id) }}",
            resumeUrl:       "{{ route('partner.ads.resume', $campaign->id) }}",
        };
    </script>

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('partner.ads.index') }}" class="hover:text-primary-600">{{ __('partner.ads.title') }}</a>
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
        <span class="text-gray-800 font-medium truncate max-w-xs">{{ $campaign->name }}</span>
    </nav>

    {{-- Campaign Header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ $campaign->name }}</h1>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $st['cls'] }}">
                {{ $st['label'] }}
            </span>
            @if ($campaign->type === \App\Enums\AdCampaignType::Cpc)
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-indigo-100 text-indigo-700">CPC</span>
            @else
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-purple-100 text-purple-700">CPM</span>
            @endif
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-2">
            @if ($campaign->status === \App\Enums\AdCampaignStatus::Active)
                <button
                    onclick="pauseCampaign('{{ route('partner.ads.pause', $campaign->id) }}')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/>
                    </svg>
                    {{ __('partner.ads.pause') }}
                </button>
            @elseif ($campaign->status === \App\Enums\AdCampaignStatus::Paused && ($campaign->ends_at === null || $campaign->ends_at->isFuture()))
                <button
                    onclick="resumeCampaign('{{ route('partner.ads.resume', $campaign->id) }}')"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/>
                    </svg>
                    {{ __('partner.ads.resume') }}
                </button>
            @endif
            <a href="{{ route('partner.ads.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                {{ __('partner.ads.back') }}
            </a>
        </div>
    </div>

    {{-- Rejection notice --}}
    @if ($campaign->status === \App\Enums\AdCampaignStatus::Rejected && $campaign->rejection_reason)
        <div class="rounded-xl bg-red-50 border border-red-200 px-5 py-4">
            <p class="text-sm font-semibold text-red-800 mb-1">{{ __('partner.ads.campaign_rejected') }}</p>
            <p class="text-sm text-red-700">{{ $campaign->rejection_reason }}</p>
        </div>
    @endif

    {{-- Key stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @php
            $budgetPct = $campaign->budget_total > 0
                ? min(100, ($campaign->budget_spent_total / $campaign->budget_total) * 100)
                : 0;
        @endphp
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.ads.total_spend') }}</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($campaign->budget_spent_total, 2) }} <span class="text-sm font-normal text-gray-400">{{ $adCurrency }}</span></p>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full bg-primary-500" style="width: {{ $budgetPct }}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ __('partner.ads.of_amount') }} {{ number_format($campaign->budget_total, 2) }} {{ $adCurrency }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.ads.spend_today') }}</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($campaign->budget_spent_today, 2) }} <span class="text-sm font-normal text-gray-400">{{ $adCurrency }}</span></p>
            @if ($campaign->budget_daily)
                <p class="text-xs text-gray-400 mt-2">{{ __('partner.ads.daily_limit') }}: {{ number_format($campaign->budget_daily, 2) }} {{ $adCurrency }}</p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.ads.bidding') }}</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($campaign->bid, 2) }} <span class="text-sm font-normal text-gray-400">{{ $adCurrency }}</span></p>
            <p class="text-xs text-gray-400 mt-2">{{ strtoupper($campaign->type->value) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.ads.ad_quality') }}</p>
            @php
                $qs = $campaign->quality_score;
                $qsColor = $qs >= 7 ? 'text-emerald-600' : ($qs >= 4 ? 'text-amber-600' : 'text-red-500');
            @endphp
            <p class="text-xl font-bold {{ $qs !== null ? $qsColor : 'text-gray-400' }}">
                {{ $qs !== null ? number_format($qs, 1) : '—' }}
                <span class="text-sm font-normal text-gray-400">/ 10</span>
            </p>
        </div>
    </div>

    {{-- ── Performance Chart ── --}}
    <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
            <h2 class="text-base font-semibold text-gray-800">{{ __('partner.ads.campaign_performance') }}</h2>
            <div class="flex items-center gap-2 flex-wrap">
                <input type="date" id="perf-from"
                    value="{{ now()->subDays(29)->toDateString() }}"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                <span class="text-gray-400 text-sm">{{ __('partner.ads.to') }}</span>
                <input type="date" id="perf-to"
                    value="{{ now()->toDateString() }}"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                <button id="apply-dates"
                    class="rounded-lg bg-primary-600 px-4 py-1.5 text-sm text-white hover:bg-primary-700">
                    {{ __('partner.ads.apply') }}
                </button>
            </div>
        </div>

        {{-- Totals strip --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 divide-x divide-x-reverse divide-gray-100 border-b border-gray-100 bg-gray-50 text-center">
            @foreach ([
                ['id' => 'total-impressions', 'label' => __('partner.ads.stat_impressions')],
                ['id' => 'total-clicks',      'label' => __('partner.ads.stat_clicks')],
                ['id' => 'total-conversions', 'label' => __('partner.ads.stat_conversions')],
                ['id' => 'total-spend',       'label' => __('partner.ads.stat_spend')],
                ['id' => 'total-revenue',     'label' => __('partner.ads.stat_attributed_revenue')],
            ] as $stat)
                <div class="px-3 py-3">
                    <p class="text-xs text-gray-500 mb-0.5">{{ $stat['label'] }}</p>
                    <p class="text-sm font-semibold text-gray-800" id="{{ $stat['id'] }}">—</p>
                </div>
            @endforeach
        </div>

        <div class="p-5">
            <canvas id="perf-chart" height="100"></canvas>
        </div>
    </div>

    {{-- ── Quality Score Breakdown ── --}}
    <div id="quality-score-section" class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">{{ __('partner.ads.ad_quality') }}</h2>
                <div class="text-right">
                    <span id="qs-overall" class="text-3xl font-bold text-gray-800">—</span>
                    <span class="text-sm text-gray-400"> / 10</span>
                </div>
            </div>
        </div>
        <div class="p-5 space-y-4">
            @foreach ([
                ['id' => 'qs-ctr',       'label' => __('partner.ads.quality_breakdown.ctr'),        'desc' => __('partner.ads.quality_breakdown.ctr_desc')],
                ['id' => 'qs-relevance', 'label' => __('partner.ads.quality_breakdown.relevance'),   'desc' => __('partner.ads.quality_breakdown.relevance_desc')],
                ['id' => 'qs-landing',   'label' => __('partner.ads.quality_breakdown.landing'), 'desc' => __('partner.ads.quality_breakdown.landing_desc')],
                ['id' => 'qs-vendor',    'label' => __('partner.ads.quality_breakdown.vendor'),      'desc' => __('partner.ads.quality_breakdown.vendor_desc')],
            ] as $qs)
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">{{ $qs['label'] }}</span>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $qs['desc'] }}</p>
                        </div>
                        <span id="{{ $qs['id'] }}-val" class="text-sm font-bold text-gray-800 ml-4">—</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div id="{{ $qs['id'] }}-bar" class="h-full rounded-full bg-primary-500 transition-all duration-700" style="width: 0%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- ── Targeting ── --}}
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-800">{{ __('partner.ads.targeting') }}</h2>
            </div>
            <div class="p-5">
                @if ($campaign->targeting_type === \App\Enums\AdCampaignTargetingType::Auto)
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-500 shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                            </svg>
                        </span>
                        <span>{{ __('partner.ads.auto_targeting_desc') }}</span>
                    </div>
                @endif

                @if (in_array($campaign->targeting_type, [\App\Enums\AdCampaignTargetingType::Keyword, \App\Enums\AdCampaignTargetingType::Mixed]))
                    @php $keywords = $campaign->keywords()->where('is_active', true)->where('is_negative', false)->get(); @endphp
                    <div class="mb-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ __('partner.ads.keywords_count', ['count' => $keywords->count()]) }}</p>
                        <div class="space-y-1.5 max-h-48 overflow-y-auto">
                            @forelse ($keywords as $kw)
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="font-medium text-gray-800">{{ $kw->keyword }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                        @if ($kw->match_type === \App\Enums\AdCampaignKeywordMatchType::Exact) bg-indigo-100 text-indigo-700
                                        @elseif ($kw->match_type === \App\Enums\AdCampaignKeywordMatchType::Phrase) bg-blue-100 text-blue-700
                                        @else bg-gray-100 text-gray-600 @endif">
                                        {{ __('partner.ads.match_types.' . $kw->match_type->value) }}
                                    </span>
                                    @if ($kw->bid_override)
                                        <span class="text-xs text-amber-600">{{ number_format($kw->bid_override / 100, 2) }} {{ $adCurrency }}</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-400">{{ __('partner.ads.no_keywords') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif

                @if (in_array($campaign->targeting_type, [\App\Enums\AdCampaignTargetingType::Category, \App\Enums\AdCampaignTargetingType::Mixed]))
                    @php $cats = $campaign->categoryTargets()->where('is_active', true)->with('category')->get(); @endphp
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ __('partner.ads.target_categories_count', ['count' => $cats->count()]) }}</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($cats as $ct)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                                    {{ $ct->category?->name_ar ?? $ct->category?->name_en ?? '—' }}
                                    @if ($ct->bid_override)
                                        <span class="ms-1 text-xs text-amber-600">{{ number_format($ct->bid_override / 100, 2) }}</span>
                                    @endif
                                </span>
                            @empty
                                <p class="text-sm text-gray-400">{{ __('partner.ads.no_categories') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Products in Campaign ── --}}
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-800">{{ __('partner.ads.products_in_campaign') }}</h2>
            </div>
            <div class="p-5">
                @php $products = $campaign->products()->where('is_active', true)->with('vendorListing')->get(); @endphp
                @forelse ($products as $p)
                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
                        <div class="h-10 w-10 rounded-lg bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                            @php $img = $p->vendorListing?->primaryImage?->url ?? null; @endphp
                            @if ($img)
                                <img src="{{ $img }}" class="h-full w-full object-cover" alt="">
                            @else
                                <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">
                                {{ $p->vendorListing?->product?->name_ar
                                    ?? $p->vendorListing?->product?->name_en
                                    ?? __('partner.ads.product_fallback') }}
                            </p>
                            <p class="text-xs text-gray-400">{{ $p->vendorListing?->sku ?? '—' }}</p>
                        </div>
                        @if ($p->vendorListing?->price)
                            <span class="mr-auto text-sm font-semibold text-gray-700">
                                {{ number_format($p->vendorListing->price, 2) }} {{ $adCurrency }}
                            </span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-6">{{ __('partner.ads.no_products') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Campaign dates --}}
    <div class="rounded-xl border border-gray-100 bg-white shadow-sm px-5 py-4 flex gap-6 flex-wrap text-sm">
        <div>
            <span class="text-gray-500">{{ __('partner.ads.starts_at') }}:</span>
            <span class="font-medium text-gray-800 ms-1">{{ $campaign->starts_at?->translatedFormat('j M Y') ?? '—' }}</span>
        </div>
        <div>
            <span class="text-gray-500">{{ __('partner.ads.ends_at') }}:</span>
            <span class="font-medium text-gray-800 ms-1">{{ $campaign->ends_at?->translatedFormat('j M Y') ?? __('partner.ads.open_ended') }}</span>
        </div>
        @if ($campaign->approved_at)
            <div>
                <span class="text-gray-500">{{ __('partner.ads.approved_at') }}:</span>
                <span class="font-medium text-gray-800 ms-1">{{ $campaign->approved_at->translatedFormat('j M Y') }}</span>
            </div>
        @endif
        <div>
            <span class="text-gray-500">{{ __('partner.ads.targeting_type') }}:</span>
            <span class="font-medium text-gray-800 ms-1">
                {{ __('partner.ads.targeting_types.' . $campaign->targeting_type->value) }}
            </span>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => window.initShowPage());
</script>
@endsection
