@extends('layouts.partner')

@section('title', __('partner.flash_sales_extra.index_title'))
@section('page-title', __('partner.flash_sales_extra.index_title'))

@push('scripts')
    @vite('resources/js/partner/flash-sales.js')
@endpush

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.flash_sales_extra.index_title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.flash_sales_extra.index_subtitle') }}</p>
    </div>

    {{-- Tabs --}}
    <div x-data="{ tab: '{{ count($grouped['open']) ? 'open' : (count($grouped['live']) ? 'live' : 'upcoming') }}' }">

        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex gap-x-6 overflow-x-auto">
                @foreach ([
                    'open'     => ['label' => __('partner.flash_sales_extra.tabs.open'),  'color' => 'blue',   'count' => count($grouped['open'])],
                    'upcoming' => ['label' => __('partner.flash_sales_extra.tabs.upcoming'),   'color' => 'gray',   'count' => count($grouped['upcoming'])],
                    'live'     => ['label' => __('partner.flash_sales_extra.tabs.live'),   'color' => 'green',  'count' => count($grouped['live'])],
                    'ended'    => ['label' => __('partner.flash_sales_extra.tabs.ended'),  'color' => 'gray',   'count' => count($grouped['ended'])],
                ] as $key => $cfg)
                <button
                    type="button"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'border-primary-600 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    {{ $cfg['label'] }}
                    @if ($cfg['count'] > 0)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            @if ($key === 'live') bg-green-100 text-green-700
                            @elseif ($key === 'open') bg-blue-100 text-blue-700
                            @else bg-gray-100 text-gray-600 @endif">
                            {{ $cfg['count'] }}
                        </span>
                    @endif
                </button>
                @endforeach
            </nav>
        </div>

        {{-- Open (قادم) --}}
        <div x-show="tab === 'open'" x-cloak>
            @forelse ($grouped['open'] as $inv)
                @php $sale = $inv->flashSale; @endphp
                <div class="mb-4 rounded-xl border border-blue-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex items-start justify-between p-5 gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                    {{ __('partner.flash_sales_extra.open_for_submission') }}
                                </span>
                                @if ($sale->is_exclusive)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ __('partner.flash_sales_extra.exclusive') }}</span>
                                @endif
                            </div>
                            <h3 class="text-base font-semibold text-gray-900 truncate">{{ $sale->name_ar }}</h3>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($sale->country)
                                    <span>{{ $sale->country->name_ar }}</span> &bull;
                                @endif
                                {{ $sale->sale_starts_at?->translatedFormat('j M Y') }} — {{ $sale->sale_ends_at?->translatedFormat('j M Y') }}
                            </p>
                        </div>
                        <a href="{{ route('partner.flash-sales.show', $sale->id) }}"
                           class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors">
                            {{ __('partner.flash_sales_extra.submit_product') }}
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                    <div class="grid grid-cols-3 divide-x divide-x-reverse divide-gray-100 border-t border-gray-100 bg-gray-50 text-center">
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.min_discount') }}</p>
                            <p class="text-sm font-semibold text-gray-800">{{ number_format($sale->min_discount_pct, 0) }}%</p>
                        </div>
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.my_submissions') }}</p>
                            <p class="text-sm font-semibold text-gray-800">{{ $inv->submissions_count }}</p>
                        </div>
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.submission_deadline') }}</p>
                            <p class="text-sm font-semibold text-blue-700" data-countdown="{{ $sale->submission_closes_at?->toIso8601String() }}">
                                {{ $sale->submission_closes_at?->translatedFormat('j M H:i') ?? '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm text-gray-500">{{ __('partner.flash_sales_extra.no_open_offers') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Upcoming (قادم) --}}
        <div x-show="tab === 'upcoming'" x-cloak>
            @forelse ($grouped['upcoming'] as $inv)
                @php $sale = $inv->flashSale; @endphp
                <div class="mb-4 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex items-start justify-between p-5 gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ __('partner.flash_sales_extra.tabs.upcoming') }}</span>
                                @if ($sale->is_featured)
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-700">{{ __('partner.flash_sales_extra.featured') }}</span>
                                @endif
                            </div>
                            <h3 class="text-base font-semibold text-gray-900 truncate">{{ $sale->name_ar }}</h3>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($sale->country)
                                    <span>{{ $sale->country->name_ar }}</span> &bull;
                                @endif
                                {{ __('partner.flash_sales_extra.starts') }} {{ $sale->sale_starts_at?->translatedFormat('j M Y') ?? '—' }}
                            </p>
                        </div>
                        <a href="{{ route('partner.flash-sales.show', $sale->id) }}"
                           class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            {{ __('partner.flash_sales_extra.details') }}
                        </a>
                    </div>
                    <div class="grid grid-cols-3 divide-x divide-x-reverse divide-gray-100 border-t border-gray-100 bg-gray-50 text-center">
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.min_discount') }}</p>
                            <p class="text-sm font-semibold text-gray-800">{{ number_format($sale->min_discount_pct, 0) }}%</p>
                        </div>
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.submission_opens') }}</p>
                            <p class="text-sm font-semibold text-gray-800">{{ $sale->submission_opens_at?->translatedFormat('j M H:i') ?? '—' }}</p>
                        </div>
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.status_label') }}</p>
                            <p class="text-sm font-semibold text-gray-800">
                                @php
                                    $statusLabel = match($sale->status) {
                                        \App\Enums\FlashSaleStatus::Approved => __('partner.flash_sales_extra.sale_status.approved'),
                                        \App\Enums\FlashSaleStatus::UnderReview => __('partner.flash_sales_extra.sale_status.under_review'),
                                        \App\Enums\FlashSaleStatus::SubmissionClosed => __('partner.flash_sales_extra.sale_status.submission_closed'),
                                        default => $sale->status->value,
                                    };
                                @endphp
                                {{ $statusLabel }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center">
                    <p class="text-sm text-gray-500">{{ __('partner.flash_sales_extra.no_upcoming_offers') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Live (جارٍ) --}}
        <div x-show="tab === 'live'" x-cloak>
            @forelse ($grouped['live'] as $inv)
                @php $sale = $inv->flashSale; @endphp
                <div class="mb-4 rounded-xl border border-emerald-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex items-start justify-between p-5 gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    {{ __('partner.flash_sales_extra.live_now') }}
                                </span>
                            </div>
                            <h3 class="text-base font-semibold text-gray-900 truncate">{{ $sale->name_ar }}</h3>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($sale->country)
                                    <span>{{ $sale->country->name_ar }}</span> &bull;
                                @endif
                                {{ __('partner.flash_sales_extra.ends') }} {{ $sale->sale_ends_at?->translatedFormat('j M H:i') ?? '—' }}
                            </p>
                        </div>
                        <a href="{{ route('partner.flash-sales.show', $sale->id) }}"
                           class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition-colors">
                            {{ __('partner.flash_sales_extra.follow_stats') }}
                        </a>
                    </div>
                    <div class="grid grid-cols-3 divide-x divide-x-reverse divide-gray-100 border-t border-gray-100 bg-emerald-50 text-center">
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.my_submissions') }}</p>
                            <p class="text-sm font-semibold text-gray-800">{{ $inv->submissions_count }}</p>
                        </div>
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.time_remaining') }}</p>
                            <p class="text-sm font-semibold text-emerald-700" data-countdown="{{ $sale->sale_ends_at?->toIso8601String() }}">
                                {{ $sale->sale_ends_at?->translatedFormat('H:i') ?? '—' }}
                            </p>
                        </div>
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('partner.flash_sales_extra.sale_duration') }}</p>
                            <p class="text-sm font-semibold text-gray-800">{{ $sale->sale_duration_hours }} {{ __('partner.flash_sales_extra.hours') }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center">
                    <p class="text-sm text-gray-500">{{ __('partner.flash_sales_extra.no_live_offers') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Ended (منتهي) --}}
        <div x-show="tab === 'ended'" x-cloak>
            @forelse ($grouped['ended'] as $inv)
                @php $sale = $inv->flashSale; @endphp
                <div class="mb-4 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden opacity-75">
                    <div class="flex items-start justify-between p-5 gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">
                                    {{ $sale->status === \App\Enums\FlashSaleStatus::Cancelled ? __('partner.flash_sales_extra.cancelled') : __('partner.flash_sales_extra.ended') }}
                                </span>
                            </div>
                            <h3 class="text-base font-semibold text-gray-700 truncate">{{ $sale->name_ar }}</h3>
                            <p class="mt-1 text-xs text-gray-400">
                                @if ($sale->country)
                                    <span>{{ $sale->country->name_ar }}</span> &bull;
                                @endif
                                {{ $sale->sale_ends_at?->translatedFormat('j M Y') ?? '—' }}
                            </p>
                        </div>
                        <a href="{{ route('partner.flash-sales.show', $sale->id) }}"
                           class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors">
                            {{ __('partner.flash_sales_extra.details') }}
                        </a>
                    </div>
                    <div class="grid grid-cols-2 divide-x divide-x-reverse divide-gray-100 border-t border-gray-100 bg-gray-50 text-center">
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-400 mb-0.5">{{ __('partner.flash_sales_extra.my_submissions') }}</p>
                            <p class="text-sm font-semibold text-gray-600">{{ $inv->submissions_count }}</p>
                        </div>
                        <div class="px-3 py-2">
                            <p class="text-xs text-gray-400 mb-0.5">{{ __('common.date') }}</p>
                            <p class="text-sm font-semibold text-gray-600">{{ $sale->sale_ends_at?->translatedFormat('j M Y') ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center">
                    <p class="text-sm text-gray-500">{{ __('partner.flash_sales_extra.no_ended_offers') }}</p>
                </div>
            @endforelse
        </div>

    </div>{{-- end x-data tabs --}}
</div>
@endsection
