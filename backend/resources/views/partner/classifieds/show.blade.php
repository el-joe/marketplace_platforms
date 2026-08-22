@extends('layouts.partner')
@section('title', __('partner.classifieds_extra.ad_details_title'))
@section('page-title', __('partner.classifieds_extra.ad_details_title'))

@push('scripts')
    @vite('resources/js/partner/classifieds.js')
    <script>
        window.CLASSIFIEDS_CFG = {
            showId: "{{ $listingId }}",
            showDataUrl: "{{ route('partner.classifieds.show-data', $listingId) }}",
            pauseUrl: "{{ route('partner.classifieds.pause', $listingId) }}",
            resumeUrl: "{{ route('partner.classifieds.resume', $listingId) }}",
            markSoldUrl: "{{ route('partner.classifieds.mark-sold', $listingId) }}",
            inquiriesUrl: "{{ route('partner.classifieds.inquiries.index', $listingId) }}",
            inquiryStatusBaseUrl: "{{ rtrim(route('partner.classifieds.inquiries.status', ['id' => $listingId, 'inquiryId' => '__ID__']), '') }}",
            contractUrl: "{{ route('partner.classifieds.contract.show', $listingId) }}",
            contractAcceptUrl: "{{ route('partner.classifieds.contract.accept', $listingId) }}",
            indexUrl: "{{ route('partner.classifieds.index') }}",
        };
    </script>
@endpush

@section('content')

    {{-- Back breadcrumb --}}
    <div class="mb-5">
        <a href="{{ route('partner.classifieds.index') }}"
            class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
            </svg>
            {{ __('partner.classifieds_extra.index_title') }}
        </a>
    </div>

    {{-- Loading state --}}
    <div id="cl-show-loading" class="py-20 text-center text-sm text-gray-400">{{ __('partner.classifieds_extra.loading_ad') }}</div>

    {{-- Error state --}}
    <div id="cl-show-error" class="hidden py-20 text-center text-sm text-red-500">{{ __('partner.classifieds_extra.error_loading_ad') }}</div>

    {{-- Main content (hidden until loaded) --}}
    <div id="cl-show-content" class="hidden space-y-6">

        {{-- Header card --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span id="sh-listing-number" class="text-xs text-gray-400 font-mono"></span>
                        <span id="sh-status-badge"></span>
                    </div>
                    <h1 id="sh-title" class="text-xl font-bold text-gray-900 leading-snug"></h1>
                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">
                        <span id="sh-category" class="inline-flex items-center gap-1">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                            </svg>
                            <span id="sh-category-name">—</span>
                        </span>
                        <span id="sh-views" class="inline-flex items-center gap-1">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <span id="sh-views-count">0</span> {{ __('partner.classifieds_extra.views_suffix') }}
                        </span>
                        <span id="sh-created-at" class="text-xs"></span>
                    </div>
                </div>

                {{-- Price --}}
                <div class="text-end shrink-0">
                    <div id="sh-price" class="text-2xl font-bold text-gray-900"></div>
                    <div id="sh-negotiable" class="hidden text-xs text-emerald-600 font-medium mt-0.5">{{ __('partner.classifieds_extra.negotiable_label') }}</div>
                </div>
            </div>

            {{-- Rejection reason --}}
            <div id="sh-rejection-block"
                class="hidden mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold mb-0.5">{{ __('partner.classifieds_extra.rejection_reason_label') }}</p>
                <p id="sh-rejection-reason"></p>
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-3 mt-5 pt-5 border-t border-gray-100 flex-wrap">
                <button id="sh-btn-pause"
                    class="hidden items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                    </svg>
                    {{ __('partner.classifieds_extra.pause') }}
                </button>
                <button id="sh-btn-resume"
                    class="hidden items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                    </svg>
                    {{ __('partner.classifieds_extra.resume') }}
                </button>
                <button id="sh-btn-sold"
                    class="hidden items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ __('partner.classifieds_extra.mark_sold') }}
                </button>
                {{-- Contract accept button (pending_contract status) --}}
                <button id="sh-btn-contract"
                    class="hidden items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 text-sm font-semibold text-white hover:bg-amber-600 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    {{ __('partner.classifieds_extra.read_accept_contract') }}
                </button>
            </div>
        </div>

        {{-- Two-column: details + images --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left col: description & attributes --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-800 mb-3">{{ __('partner.classifieds_extra.details_title') }}</h2>
                    <div id="sh-description" class="text-sm text-gray-700 leading-relaxed whitespace-pre-line"></div>
                    <div id="sh-attributes-section" class="hidden mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('partner.classifieds_extra.wizard.additional_attributes') }}</p>
                        <dl id="sh-attributes" class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm"></dl>
                    </div>
                </div>

                {{-- Inquiries tab --}}
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">{{ __('partner.classifieds_extra.inquiries_title') }} <span id="sh-inquiries-count"
                                class="ms-1 inline-flex items-center justify-center rounded-full bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5">0</span>
                        </h2>
                    </div>
                    <div id="sh-inquiries-loading" class="py-8 text-center text-sm text-gray-400">{{ __('partner.classifieds_extra.loading_inquiries') }}
                    </div>
                    <div id="sh-inquiries-empty" class="hidden py-8 text-center text-sm text-gray-400">{{ __('partner.classifieds_extra.no_inquiries') }}</div>
                    <div id="sh-inquiries-list" class="hidden divide-y divide-gray-50"></div>
                </div>
            </div>

            {{-- Right col: images + meta --}}
            <div class="space-y-4">
                {{-- Images --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-4">
                    <h2 class="text-sm font-semibold text-gray-800 mb-3">{{ __('partner.classifieds_extra.images_title') }}</h2>
                    <div id="sh-images-loading" class="py-6 text-center text-sm text-gray-400">{{ __('partner.classifieds_extra.loading_images') }}</div>
                    <div id="sh-images-grid" class="hidden grid-cols-2 gap-2"></div>
                    <div id="sh-images-empty" class="hidden py-4 text-center text-xs text-gray-400">{{ __('partner.classifieds_extra.no_images') }}</div>
                </div>

                {{-- Meta card --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-4 text-sm space-y-2.5">
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('partner.classifieds_extra.purpose_label') }}</span>
                        <span id="sh-purpose" class="font-medium text-gray-800"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('partner.classifieds_extra.currency_label') }}</span>
                        <span id="sh-currency" class="font-medium text-gray-800"></span>
                    </div>
                    <div class="flex justify-between" id="sh-expires-row">
                        <span class="text-gray-500">{{ __('partner.classifieds_extra.expires_label') }}</span>
                        <span id="sh-expires" class="font-medium text-gray-800"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('partner.classifieds_extra.category_label') }}</span>
                        <span id="sh-meta-category" class="font-medium text-gray-800 text-end"></span>
                    </div>
                    <div id="sh-sketch-row" style="display:none" class="flex justify-between items-center">
                        <span class="text-gray-500">{{ __('partner.classifieds_extra.sketch_label') }}</span>
                        <a id="sh-sketch-link" href="#" target="_blank" class="text-primary-600 text-xs hover:underline">{{ __('partner.classifieds_extra.view_file') }}</a>
                    </div>
                </div>

                {{-- Attachments --}}
                <div id="sh-attachments-card" class="hidden bg-white rounded-2xl border border-gray-200 p-4">
                    <h2 class="text-sm font-semibold text-gray-800 mb-3">{{ __('partner.classifieds_extra.attachments_title') }}</h2>
                    <div id="sh-attachments-list" class="space-y-2"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Contract accept modal --}}
    <div id="sh-contract-modal" class="hidden fixed inset-0 z-50 items-center justify-center p-4" style="display:none">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" id="sh-contract-backdrop"></div>
        <div
            class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                <h3 class="text-base font-bold text-gray-900">{{ __('partner.classifieds_extra.accept_contract_title') }}</h3>
                <button id="sh-contract-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                <div id="sh-contract-modal-loading" class="py-6 text-center text-sm text-gray-400">{{ __('partner.classifieds_extra.loading_contract') }}</div>
                <div id="sh-contract-modal-body" class="hidden space-y-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 max-h-56 overflow-y-auto text-sm text-gray-700 leading-relaxed"
                        id="sh-contract-modal-text"></div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.signature_name') }} <span
                                class="text-red-500">*</span></label>
                        <input type="text" id="sh-contract-sig-name"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            placeholder="{{ __('partner.classifieds_extra.signature_name_placeholder') }}">
                    </div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" id="sh-contract-modal-agree"
                            class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span class="text-sm text-gray-700">{{ __('partner.classifieds_extra.contract_agree_short') }}</span>
                    </label>
                    <div id="sh-contract-modal-error"
                        class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex justify-end gap-3 shrink-0">
                <button id="sh-contract-cancel-btn" class="text-sm text-gray-500 hover:text-gray-700">{{ __('partner.classifieds_extra.cancel') }}</button>
                <button id="sh-contract-accept-btn"
                    class="hidden inline-flex items-center gap-2 rounded-lg bg-amber-500 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-60">
                    {{ __('partner.classifieds_extra.accept_contract_submit') }}
                </button>
            </div>
        </div>
    </div>

@endsection