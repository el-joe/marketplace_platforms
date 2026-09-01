@extends('layouts.partner')

@section('title', $flashSale->name_ar)
@section('page-title', __('partner.flash_sales_extra.details'))

@push('scripts')
    @vite('resources/js/partner/flash-sales.js')
    <script>
        window.FLASH_SALES = {
            eligibleListingsUrl: '{{ route('partner.flash-sales.eligible-listings', $flashSale->id) }}',
            submitUrl:           '{{ route('partner.flash-sales.submit', $flashSale->id) }}',
            @if ($flashSale->isLive())
            liveStatsUrl:        '{{ route('partner.flash-sales.live-stats', $flashSale->id) }}',
            @endif
            isLive:                  {{ $flashSale->isLive() ? 'true' : 'false' }},
            isAcceptingSubmissions:  {{ $flashSale->status === \App\Enums\FlashSaleStatus::SubmissionOpen ? 'true' : 'false' }},
            minDiscountPct:          {{ (float) $flashSale->min_discount_pct }},
            csrf:                    '{{ csrf_token() }}',
        };
    </script>
@endpush

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('partner.flash-sales.index') }}" class="hover:text-primary-600">{{ __('partner.flash_sales_extra.index_title') }}</a>
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
        <span class="text-gray-900 font-medium truncate max-w-xs">{{ $flashSale->name_ar }}</span>
    </nav>

    {{-- Sale header card --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    {{-- Status badge --}}
                    @php
                        $statusConfig = match($flashSale->status) {
                            \App\Enums\FlashSaleStatus::SubmissionOpen   => ['label' => __('partner.flash_sales_extra.open_for_submission'), 'class' => 'bg-blue-100 text-blue-700'],
                            \App\Enums\FlashSaleStatus::Live              => ['label' => __('partner.flash_sales_extra.live_now'),     'class' => 'bg-emerald-100 text-emerald-700'],
                            \App\Enums\FlashSaleStatus::Ended             => ['label' => __('partner.flash_sales_extra.ended'),          'class' => 'bg-gray-100 text-gray-500'],
                            \App\Enums\FlashSaleStatus::Cancelled         => ['label' => __('partner.flash_sales_extra.cancelled'),           'class' => 'bg-red-100 text-red-600'],
                            \App\Enums\FlashSaleStatus::UnderReview      => ['label' => __('partner.flash_sales_extra.sale_status.under_review'),  'class' => 'bg-amber-100 text-amber-700'],
                            \App\Enums\FlashSaleStatus::Approved          => ['label' => __('partner.flash_sales_extra.sale_status.approved'),          'class' => 'bg-purple-100 text-purple-700'],
                            \App\Enums\FlashSaleStatus::SubmissionClosed => ['label' => __('partner.flash_sales_extra.sale_status.submission_closed'),  'class' => 'bg-gray-100 text-gray-600'],
                            default             => ['label' => $flashSale->status->value, 'class' => 'bg-gray-100 text-gray-600'],
                        };
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusConfig['class'] }} mb-2">
                        @if ($flashSale->isLive())
                            <span class="me-1 h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        @endif
                        {{ $statusConfig['label'] }}
                    </span>
                    <h2 class="text-xl font-bold text-gray-900">{{ $flashSale->name_ar }}</h2>
                    @if ($flashSale->description_ar)
                        <p class="mt-1 text-sm text-gray-500">{{ $flashSale->description_ar }}</p>
                    @endif
                </div>
                @if ($flashSale->isLive() && $flashSale->sale_ends_at)
                    <div class="text-center">
                        <p class="text-xs text-gray-500 mb-1">{{ __('partner.flash_sales_extra.time_remaining') }}</p>
                        <p id="live-sale-countdown" class="text-2xl font-bold text-emerald-600 tabular-nums" data-countdown="{{ $flashSale->sale_ends_at->toIso8601String() }}">—</p>
                    </div>
                @endif
            </div>

            {{-- Info grid --}}
            <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-4 pt-5 border-t border-gray-100">
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">{{ __('partner.flash_sales_extra.show_starts') }}</p>
                    <p class="text-sm font-medium text-gray-800">{{ $flashSale->sale_starts_at?->translatedFormat('j M Y H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">{{ __('partner.flash_sales_extra.show_ends') }}</p>
                    <p class="text-sm font-medium text-gray-800">{{ $flashSale->sale_ends_at?->translatedFormat('j M Y H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">{{ __('partner.flash_sales_extra.min_discount_pct') }}</p>
                    <p class="text-sm font-semibold text-primary-700">{{ number_format($flashSale->min_discount_pct, 0) }}%</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">{{ __('partner.flash_sales_extra.sale_duration_label') }}</p>
                    <p class="text-sm font-medium text-gray-800">{{ $flashSale->sale_duration_hours }} {{ __('partner.flash_sales_extra.hours') }}</p>
                </div>
                @if ($flashSale->max_products_per_seller)
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">{{ __('partner.flash_sales_extra.max_products_per_seller') }}</p>
                    <p class="text-sm font-medium text-gray-800">{{ $flashSale->max_products_per_seller }}</p>
                </div>
                @endif
                @if ($flashSale->country)
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">{{ __('partner.flash_sales_extra.country') }}</p>
                    <p class="text-sm font-medium text-gray-800">{{ $flashSale->country->name_ar }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Flags row --}}
        @if ($flashSale->is_featured || $flashSale->is_exclusive || $flashSale->price_drop_required)
        <div class="flex items-center gap-3 flex-wrap px-6 py-3 bg-amber-50 border-t border-amber-100 text-xs font-medium">
            @if ($flashSale->is_featured)
                <span class="inline-flex items-center gap-1 text-amber-700">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd"/></svg>
                    {{ __('partner.flash_sales_extra.featured') }}
                </span>
            @endif
            @if ($flashSale->is_exclusive)
                <span class="inline-flex items-center gap-1 text-amber-700">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    {{ __('partner.flash_sales_extra.exclusive_notice') }}
                </span>
            @endif
            @if ($flashSale->price_drop_required)
                <span class="inline-flex items-center gap-1 text-amber-700">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181"/></svg>
                    {{ __('partner.flash_sales_extra.price_drop_notice') }}
                </span>
            @endif
        </div>
        @endif
    </div>

    {{-- Invitation status --}}
    @php
        $invBadge = match($invitation->status) {
            \App\Enums\FlashSaleVendorInvitationStatus::Pending   => ['label' => __('partner.flash_sales_extra.invitation_status.pending'), 'class' => 'bg-amber-100 text-amber-700'],
            \App\Enums\FlashSaleVendorInvitationStatus::Accepted  => ['label' => __('partner.flash_sales_extra.invitation_status.accepted'),  'class' => 'bg-blue-100 text-blue-700'],
            \App\Enums\FlashSaleVendorInvitationStatus::Declined  => ['label' => __('partner.flash_sales_extra.invitation_status.declined'),  'class' => 'bg-red-100 text-red-600'],
            \App\Enums\FlashSaleVendorInvitationStatus::Submitted => ['label' => __('partner.flash_sales_extra.invitation_status.submitted'), 'class' => 'bg-green-100 text-green-700'],
            default     => ['label' => $invitation->status->value, 'class' => 'bg-gray-100 text-gray-600'],
        };
    @endphp
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $invBadge['class'] }}">
                {{ $invBadge['label'] }}
            </span>
            <p class="text-sm text-gray-600">{{ __('partner.flash_sales_extra.invitation_status_label') }}</p>
        </div>
        @if ($invitation->slots_allocated)
            <div class="text-sm text-gray-500">
                {{ __('partner.flash_sales_extra.slots_allocated') }}: <strong class="text-gray-800">{{ $invitation->slots_allocated }}</strong>
            </div>
        @endif
    </div>

    {{-- Submit section (only when submission_open and not declined) --}}
    @if ($flashSale->status === \App\Enums\FlashSaleStatus::SubmissionOpen && $invitation->status !== \App\Enums\FlashSaleVendorInvitationStatus::Declined)
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h3 class="text-sm font-semibold text-blue-900">{{ __('partner.flash_sales_extra.submit_product_title') }}</h3>
                <p class="text-xs text-blue-700 mt-0.5">
                    {{ __('partner.flash_sales_extra.submission_ends', ['date' => $flashSale->submission_closes_at ? $flashSale->submission_closes_at->translatedFormat('j M Y') . ' ' . __('common.at_time') . ' ' . $flashSale->submission_closes_at->format('H:i') : '—']) }}
                </p>
            </div>
            <button id="btn-add-product" type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('partner.flash_sales_extra.submit_product') }}
            </button>
        </div>
    </div>
    @endif

    {{-- My submissions table --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-900">{{ __('partner.flash_sales_extra.my_submissions') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.table.product') }}</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.table.flash_price') }}</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.table.original_price') }}</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.table.discount') }}</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.table.quantity') }}</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.table.status') }}</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.table.submitted_at') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($submissions as $sub)
                        @php
                            $product  = $sub->vendorListing?->productVariant?->product;
                            $currency = $sub->flash_price_currency ?? '';
                            $subBadge = match($sub->status) {
                                \App\Enums\FlashSaleSubmissionStatus::Draft        => ['label' => __('partner.flash_sales_extra.submission_status.draft'),         'class' => 'bg-gray-100 text-gray-500'],
                                \App\Enums\FlashSaleSubmissionStatus::Submitted    => ['label' => __('partner.flash_sales_extra.submission_status.submitted'),        'class' => 'bg-blue-100 text-blue-700'],
                                \App\Enums\FlashSaleSubmissionStatus::UnderReview => ['label' => __('partner.flash_sales_extra.submission_status.under_review'), 'class' => 'bg-amber-100 text-amber-700'],
                                \App\Enums\FlashSaleSubmissionStatus::Approved     => ['label' => __('partner.flash_sales_extra.submission_status.approved'),         'class' => 'bg-green-100 text-green-700'],
                                \App\Enums\FlashSaleSubmissionStatus::Live         => ['label' => __('partner.flash_sales_extra.submission_status.live'),          'class' => 'bg-emerald-100 text-emerald-700'],
                                \App\Enums\FlashSaleSubmissionStatus::SoldOut     => ['label' => __('partner.flash_sales_extra.submission_status.sold_out'),   'class' => 'bg-orange-100 text-orange-700'],
                                \App\Enums\FlashSaleSubmissionStatus::Rejected     => ['label' => __('partner.flash_sales_extra.submission_status.rejected'),         'class' => 'bg-red-100 text-red-600'],
                                \App\Enums\FlashSaleSubmissionStatus::Withdrawn    => ['label' => __('partner.flash_sales_extra.submission_status.withdrawn'),         'class' => 'bg-gray-100 text-gray-500'],
                                \App\Enums\FlashSaleSubmissionStatus::Ended        => ['label' => __('partner.flash_sales_extra.submission_status.ended'),         'class' => 'bg-gray-100 text-gray-500'],
                                default        => ['label' => $sub->status->value,    'class' => 'bg-gray-100 text-gray-500'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900 max-w-xs truncate">
                                {{ $product?->name_ar ?? $product?->name_en ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-primary-700 font-semibold">
                                {{ number_format($sub->flash_price, 2) }} {{ $currency }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 line-through">
                                {{ number_format($sub->original_price, 2) }} {{ $currency }}
                            </td>
                            <td class="px-4 py-3 text-green-700 font-medium">
                                {{ number_format($sub->calculated_discount_pct, 1) }}%
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ number_format($sub->max_quantity_total) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $subBadge['class'] }}">
                                    {{ $subBadge['label'] }}
                                </span>
                                @if ($sub->rejection_reason)
                                    <p class="mt-1 text-xs text-red-500">{{ $sub->rejection_reason }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                {{ $sub->submitted_at?->translatedFormat('j M Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400">
                                {{ __('partner.flash_sales_extra.no_submissions') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Live stats section (only when sale is live) --}}
    @if ($flashSale->isLive())
        @php $liveSubmissions = $submissions->whereIn('status', [\App\Enums\FlashSaleSubmissionStatus::Approved, \App\Enums\FlashSaleSubmissionStatus::Live, \App\Enums\FlashSaleSubmissionStatus::SoldOut]); @endphp
        @if ($liveSubmissions->isNotEmpty())
        <div id="live-stats-container" class="rounded-xl border border-emerald-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-emerald-100 bg-emerald-50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h3 class="text-base font-semibold text-emerald-900">{{ __('partner.flash_sales_extra.live_stats_title') }}</h3>
                </div>
                <span class="text-xs text-gray-500">{{ __('partner.flash_sales_extra.refreshes_every_10s') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.live_table.product') }}</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.live_table.sold') }}</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.live_table.remaining') }}</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.live_table.revenue') }}</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 text-right">{{ __('partner.flash_sales_extra.live_table.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($liveSubmissions as $sub)
                            @php $product = $sub->vendorListing?->productVariant?->product; @endphp
                            <tr data-submission-id="{{ $sub->id }}">
                                <td class="px-4 py-3 font-medium text-gray-900 max-w-xs truncate">
                                    {{ $product?->name_ar ?? $product?->name_en ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="stat-qty-sold font-semibold text-gray-800">{{ number_format($sub->quantity_sold) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="stat-qty-remaining text-gray-600">{{ number_format($sub->quantity_remaining) }}</span>
                                </td>
                                <td class="px-4 py-3 text-primary-700 font-medium">
                                    <span class="stat-revenue">
                                        {{ number_format(($sub->flash_price * $sub->quantity_sold), 2) }}
                                    </span>
                                    {{ $sub->flash_price_currency }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($sub->status === \App\Enums\FlashSaleSubmissionStatus::SoldOut)
                                        <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-orange-700">{{ __('partner.flash_sales_extra.submission_status.sold_out') }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            {{ __('partner.flash_sales_extra.submission_status.live') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endif

</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     Submit Product Modal
     ═══════════════════════════════════════════════════════════════════════════ --}}
@if ($flashSale->status === \App\Enums\FlashSaleStatus::SubmissionOpen && $invitation->status !== \App\Enums\FlashSaleVendorInvitationStatus::Declined)
<div id="submit-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative flex min-h-screen items-center justify-center p-4">
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">

            {{-- Modal header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900">{{ __('partner.flash_sales_extra.submit_product_title') }}</h3>
                <button id="btn-close-submit-modal" type="button"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal body --}}
            <form id="form-submit" class="px-6 py-5 space-y-4" novalidate>

                {{-- Listing select --}}
                <div>
                    <label for="input-listing" class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.flash_sales_extra.modal_product_label') }} <span class="text-red-500">*</span></label>
                    <select id="input-listing"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        <option value="">{{ __('partner.flash_sales_extra.modal_loading_products') }}</option>
                    </select>
                    <p id="avg-price-hint" class="mt-1 text-xs text-gray-500 hidden"></p>
                </div>

                {{-- Price row --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="input-original-price" class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.flash_sales_extra.modal_original_price') }} <span class="text-red-500">*</span></label>
                        <input type="number" id="input-original-price" step="1" min="1"
                            placeholder="0"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                    </div>
                    <div>
                        <label for="input-flash-price" class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.flash_sales_extra.modal_flash_price') }} <span class="text-red-500">*</span></label>
                        <input type="number" id="input-flash-price" step="1" min="1"
                            placeholder="0"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                    </div>
                </div>

                {{-- Discount indicator --}}
                <p id="discount-pct-display" class="text-sm font-medium text-gray-400">{{ __('partner.flash_sales_extra.discount_display') }}</p>

                {{-- Quantity row --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="input-max-qty" class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.flash_sales_extra.modal_max_qty') }} <span class="text-red-500">*</span></label>
                        <input type="number" id="input-max-qty" min="1" placeholder="{{ __('partner.flash_sales_extra.modal_max_qty_placeholder') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                    </div>
                    <div>
                        <label for="input-max-per-customer" class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.flash_sales_extra.modal_max_per_customer') }}</label>
                        <input type="number" id="input-max-per-customer" min="1" placeholder="{{ __('partner.flash_sales_extra.modal_max_per_customer_placeholder') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label for="input-vendor-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.flash_sales_extra.modal_notes') }}</label>
                    <textarea id="input-vendor-notes" rows="2" maxlength="500"
                        placeholder="{{ __('partner.flash_sales_extra.modal_notes_placeholder') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 resize-none"></textarea>
                </div>

                {{-- Min discount hint --}}
                <p id="min-discount-hint" class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700">
                    {!! __('partner.flash_sales_extra.modal_min_discount_hint', ['pct' => number_format($flashSale->min_discount_pct, 0)]) !!}
                    @if ($flashSale->price_drop_required)
                        {{ __('partner.flash_sales_extra.modal_price_drop_hint') }}
                    @endif
                </p>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                    <button type="button" id="btn-cancel-submit"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        {{ __('partner.flash_sales_extra.cancel') }}
                    </button>
                    <button type="submit" id="btn-submit-product"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors disabled:opacity-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                        {{ __('partner.flash_sales_extra.submit_product') }}
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
@endif

@endsection
