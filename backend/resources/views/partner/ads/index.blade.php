@extends('layouts.partner')
@section('title', __('partner.ads.page_title'))
@section('page-title', __('partner.ads.page_title'))

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@push('scripts')
    @vite('resources/js/partner/ads.js')
    <script>
        window.ADS_CONFIG = {
            datatableUrl:  "{{ route('partner.ads.datatable') }}",
            storeUrl:      "{{ route('partner.ads.store') }}",
            listingsUrl:   "{{ route('partner.listings.datatable') }}",
            categoriesUrl: "{{ route('partner.ads.categories') }}",
        };
        window.PARTNER_TRANSLATIONS = window.PARTNER_TRANSLATIONS || {};
        Object.assign(window.PARTNER_TRANSLATIONS, {
            confirmPauseCampaign: @json(__('partner.ads.confirm_pause_campaign')),
            confirmResumeCampaign: @json(__('partner.ads.confirm_resume_campaign')),
        });
    </script>
@endpush

@php $vendorCurrency = auth()->guard('vendor')->user()->vendor?->country?->currency_code ?? ''; @endphp

@section('content')

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500">{{ __('partner.ads.manage_desc') }}</p>
        </div>
        <div>
            <button id="btn-open-wizard"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                {{ __('partner.ads.new_campaign') }}
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
            </svg>
            <input id="ads-search" type="text" placeholder="{{ __('partner.ads.search_placeholder') }}"
                class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-400/40 placeholder-gray-400">
        </div>
        <select id="ads-filter-status" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-400/40">
            <option value="">{{ __('partner.ads.all_statuses') }}</option>
            <option value="draft">{{ __('partner.ads.statuses.draft') }}</option>
            <option value="pending_review">{{ __('partner.ads.statuses.pending_review') }}</option>
            <option value="active">{{ __('partner.ads.statuses.active') }}</option>
            <option value="paused">{{ __('partner.ads.statuses.paused') }}</option>
            <option value="budget_exhausted">{{ __('partner.ads.statuses.budget_exhausted') }}</option>
            <option value="ended">{{ __('partner.ads.statuses.ended') }}</option>
            <option value="rejected">{{ __('partner.ads.statuses.rejected') }}</option>
        </select>
    </div>

    {{-- DataTable --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table id="ads-table" class="w-full text-sm" style="width:100%">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('partner.ads.campaign_name') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('common.type') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('common.status') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('partner.ads.budget_total_daily') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('partner.ads.bid') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide">{{ __('common.created_at') }}</th>
                    <th class="px-4 py-3 text-left font-semibold tracking-wide"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"></tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
            <span id="ads-table-info" class="text-xs text-gray-400"></span>
            <div id="ads-table-pagination" class="flex items-center gap-1"></div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         CREATE CAMPAIGN WIZARD MODAL
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div id="wizard-overlay" class="hidden fixed inset-0 z-50 items-center justify-center p-4" role="dialog" aria-modal="true" style="display:none">
        <div id="wizard-backdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-2xl max-h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ __('partner.ads.wizard_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.ads.wizard_step') }} <span id="wiz-step-label">1</span> {{ __('partner.ads.wizard_step_of') }} 5</p>
                </div>
                <button id="wiz-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 pt-4 pb-2">
                <div id="wiz-progress" class="flex items-center"></div>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-5">
                <div id="wiz-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
                <div id="wiz-step-1" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.ads.campaign_name') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="wiz-name" maxlength="200" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500" placeholder="{{ __('partner.ads.campaign_name_placeholder') }}">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('partner.ads.pricing_model') }} <span class="text-red-500">*</span></label>
                            <div class="flex gap-3">
                                <label id="type-cpc-label" class="flex-1 flex items-center gap-2 rounded-lg border-2 border-primary-500 bg-primary-50 p-3 cursor-pointer transition-colors">
                                    <input type="radio" name="wiz-type" value="cpc" class="sr-only" checked>
                                    <div><div class="text-sm font-semibold text-primary-700">CPC</div><div class="text-xs text-gray-500">{{ __('partner.ads.pay_per_click') }}</div></div>
                                </label>
                                <label id="type-cpm-label" class="flex-1 flex items-center gap-2 rounded-lg border-2 border-gray-200 p-3 cursor-pointer transition-colors">
                                    <input type="radio" name="wiz-type" value="cpm" class="sr-only">
                                    <div><div class="text-sm font-semibold text-gray-700">CPM</div><div class="text-xs text-gray-500">{{ __('partner.ads.pay_per_1000_views') }}</div></div>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('partner.ads.targeting_type') }} <span class="text-red-500">*</span></label>
                            <select id="wiz-targeting" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                <option value="auto">{{ __('partner.ads.targeting_auto_option') }}</option>
                                <option value="keyword">{{ __('partner.ads.targeting_keyword') }}</option>
                                <option value="category">{{ __('partner.ads.targeting_category') }}</option>
                                <option value="mixed">{{ __('partner.ads.targeting_mixed_option') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="wiz-step-2" class="hidden space-y-4">
                    <div id="wiz-auto-msg" class="rounded-xl bg-blue-50 border border-blue-200 p-5 text-center">
                        <svg class="mx-auto h-8 w-8 text-blue-400 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                        <p class="text-sm font-semibold text-blue-800 mb-1">{{ __('partner.ads.auto_targeting') }}</p>
                        <p class="text-sm text-blue-700">{{ __('partner.ads.auto_targeting_desc') }}</p>
                    </div>
                    <div id="wiz-keywords-section" class="hidden space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700">{{ __('partner.ads.keywords') }} <span class="text-red-500">*</span></label>
                            <button type="button" id="btn-add-keyword" class="text-xs text-primary-600 hover:text-primary-700 font-medium">+ {{ __('partner.ads.add_keyword') }}</button>
                        </div>
                        <div id="wiz-keywords-list"></div>
                    </div>
                    <div id="wiz-categories-section" class="hidden space-y-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('partner.ads.targeted_categories') }} <span class="text-red-500">*</span></label>
                        <div id="wiz-categories-loading" class="text-xs text-gray-400">{{ __('partner.ads.loading_categories') }}</div>
                        <div id="wiz-categories-grid" class="hidden grid-cols-2 gap-2"></div>
                    </div>
                </div>
                <div id="wiz-step-3" class="hidden space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">{{ __('partner.ads.choose_products') }} <span class="text-red-500">*</span></label>
                    <p class="text-xs text-gray-500">{{ __('partner.ads.choose_products_desc') }}</p>
                    <div id="wiz-products-loading" class="py-8 text-center text-sm text-gray-400">{{ __('partner.ads.loading_products') }}</div>
                    <div id="wiz-products-empty" class="hidden py-8 text-center text-sm text-gray-400">{{ __('partner.ads.no_active_products') }}</div>
                    <div id="wiz-products-grid" class="hidden grid-cols-1 sm:grid-cols-2 gap-3"></div>
                </div>
                <div id="wiz-step-4" class="hidden space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.ads.total_budget') }} ({{ $vendorCurrency }}) <span class="text-red-500">*</span></label>
                            <input type="number" id="wiz-budget-total" min="1" step="1" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500" placeholder="{{ __('partner.ads.example_500') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.ads.daily_budget') }} ({{ $vendorCurrency }}) <span class="text-gray-400 text-xs font-normal">{{ __('common.optional') }}</span></label>
                            <input type="number" id="wiz-budget-daily" min="1" step="1" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500" placeholder="{{ __('partner.ads.example_50') }}">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><span id="wiz-bid-label">{{ __('partner.ads.click_bid') }} ({{ $vendorCurrency }})</span> <span class="text-red-500">*</span></label>
                        <input type="number" id="wiz-bid" min="1" step="1" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500" placeholder="{{ __('partner.ads.example_050') }}">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.ads.start_date') }} <span class="text-red-500">*</span></label>
                            <input type="date" id="wiz-starts-at" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.ads.end_date') }} <span class="text-gray-400 text-xs font-normal">{{ __('common.optional') }}</span></label>
                            <input type="date" id="wiz-ends-at" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        </div>
                    </div>
                </div>
                <div id="wiz-step-5" class="hidden space-y-4">
                    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        {{ __('partner.ads.review_notice') }}
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 divide-y divide-gray-100 text-sm" id="wiz-review-table"></div>
                </div>
            </div>
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                <button id="wiz-prev" class="hidden items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    {{ __('common.previous') }}
                </button>
                <span id="wiz-prev-spacer"></span>
                <div class="flex items-center gap-3">
                    <button id="wiz-cancel" class="text-sm text-gray-500 hover:text-gray-700">{{ __('common.cancel') }}</button>
                    <button id="wiz-next" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">
                        {{ __('common.next') }}
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </button>
                    <button id="wiz-submit" class="hidden items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                        {{ __('partner.ads.submit_for_review') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection
