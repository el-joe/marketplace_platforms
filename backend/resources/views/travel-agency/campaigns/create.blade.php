@extends('layouts.travel-agency')

@section('title', __('travel.campaigns.new_offer_title'))

@push('scripts')
    @vite('resources/js/travel_agency/campaigns.js')
    <script>
        window.CAMPAIGN_OFFER_CONFIG = {
            storeUrl:     "{{ route('travel-agency.campaigns.store') }}",
            packagesUrl:  "{{ route('travel-agency.campaigns.packages.search') }}",
            mode:         'create',
        };
        window.TRAVEL_AGENCY_TRANSLATIONS = window.TRAVEL_AGENCY_TRANSLATIONS || {};
        Object.assign(window.TRAVEL_AGENCY_TRANSLATIONS, {
            enterCampaignName: @json(__('travel.campaigns.enter_campaign_name')),
            selectCampaignType: @json(__('travel.campaigns.select_campaign_type')),
            selectCampaignDates: @json(__('travel.campaigns.select_campaign_dates')),
            selectAttributionModel: @json(__('travel.campaigns.select_attribution_model')),
            enterValidCommissionRate: @json(__('travel.campaigns.enter_valid_commission_rate')),
            selectCommissionType: @json(__('travel.campaigns.select_commission_type')),
            confirmWithdrawInvitation: @json(__('travel.campaigns.confirm_withdraw_invitation')),
            packagesWord: @json(__('travel.campaigns.packages_word')),
        });
    </script>
@endpush

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8">

    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('travel-agency.campaigns.index') }}" class="hover:text-primary-600">{{ __('travel.campaigns.title') }}</a>
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
        <span class="text-gray-800 font-medium">{{ __('travel.campaigns.new_offer') }}</span>
    </nav>

    <div class="max-w-3xl mx-auto">

        {{-- Step indicators --}}
        <div class="mb-8 flex items-center justify-between" id="step-indicators">
            @foreach ([
                ['num' => 1, 'label' => __('travel.campaigns.steps.campaign_details')],
                ['num' => 2, 'label' => __('travel.campaigns.steps.commission_budget')],
                ['num' => 3, 'label' => __('travel.campaigns.steps.packages')],
            ] as $step)
                <div class="flex items-center {{ !$loop->last ? 'flex-1' : '' }}">
                    <div class="flex-shrink-0 flex items-center gap-3">
                        <div class="step-circle h-9 w-9 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-colors
                                    {{ $loop->first ? 'border-primary-600 bg-primary-600 text-white' : 'border-gray-300 bg-white text-gray-400' }}"
                             data-step="{{ $step['num'] }}">
                            {{ $step['num'] }}
                        </div>
                        <span class="text-sm font-medium step-label
                                     {{ $loop->first ? 'text-primary-600' : 'text-gray-400' }}"
                              data-step="{{ $step['num'] }}">{{ $step['label'] }}</span>
                    </div>
                    @if (!$loop->last)
                        <div class="step-connector flex-1 mx-4 h-0.5 bg-gray-200 transition-colors" data-after="{{ $step['num'] }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="campaign-offer-form" method="POST" action="{{ route('travel-agency.campaigns.store') }}" class="space-y-6" novalidate>
            @csrf

            {{-- ═══ STEP 1: Campaign Details ═══ --}}
            <div class="step-panel" data-panel="1">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                    <div class="px-6 py-4">
                        <h2 class="text-base font-semibold text-gray-900">{{ __('travel.campaigns.steps.campaign_details') }}</h2>
                    </div>
                    <div class="px-6 py-5 space-y-5">

                        {{-- Name --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5" for="name">
                                {{ __('travel.campaigns.name_label') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="255" required
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                   placeholder="{{ __('travel.campaigns.name_placeholder') }}">
                        </div>

                        {{-- Campaign Type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5" for="campaign_type">
                                {{ __('travel.campaigns.type_label') }} <span class="text-red-500">*</span>
                            </label>
                            <select id="campaign_type" name="campaign_type" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                <option value="">{{ __('travel.campaigns.select_type') }}</option>
                                <option value="product_promotion" {{ old('campaign_type') === 'product_promotion' ? 'selected' : '' }}>{{ __('travel.campaigns.types.product_promotion') }}</option>
                                <option value="store_promotion"   {{ old('campaign_type') === 'store_promotion'   ? 'selected' : '' }}>{{ __('travel.campaigns.types.store_promotion') }}</option>
                                <option value="brand_deal"        {{ old('campaign_type') === 'brand_deal'        ? 'selected' : '' }}>{{ __('travel.campaigns.types.brand_deal') }}</option>
                                <option value="product_specific"  {{ old('campaign_type') === 'product_specific'  ? 'selected' : '' }}>{{ __('travel.campaigns.types.product_specific') }}</option>
                                <option value="flash_sale"        {{ old('campaign_type') === 'flash_sale'        ? 'selected' : '' }}>{{ __('travel.campaigns.types.flash_sale') }}</option>
                            </select>
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5" for="description">
                                {{ __('travel.campaigns.description_label') }}
                            </label>
                            <textarea id="description" name="description" rows="3" maxlength="2000"
                                      class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                      placeholder="{{ __('travel.campaigns.description_placeholder') }}">{{ old('description') }}</textarea>
                        </div>

                        {{-- Requirements --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5" for="requirements">
                                {{ __('travel.campaigns.requirements_label') }}
                            </label>
                            <textarea id="requirements" name="requirements" rows="3" maxlength="2000"
                                      class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                      placeholder="{{ __('travel.campaigns.requirements_placeholder') }}">{{ old('requirements') }}</textarea>
                        </div>

                        {{-- Dates row --}}
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="starts_at">
                                    {{ __('travel.campaigns.start_date') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="starts_at" name="starts_at" value="{{ old('starts_at') }}" required
                                       min="{{ date('Y-m-d') }}"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="ends_at">
                                    {{ __('travel.campaigns.end_date') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="ends_at" name="ends_at" value="{{ old('ends_at') }}" required
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="invitation_deadline">
                                    {{ __('travel.campaigns.invite_deadline') }}
                                </label>
                                <input type="date" id="invitation_deadline" name="invitation_deadline" value="{{ old('invitation_deadline') }}"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                <p class="mt-1 text-xs text-gray-400">{{ __('travel.campaigns.invite_deadline_hint') }}</p>
                            </div>
                        </div>

                        {{-- Attribution + WhatsApp row --}}
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="attribution_model">
                                    {{ __('travel.campaigns.attribution_model') }} <span class="text-red-500">*</span>
                                </label>
                                <select id="attribution_model" name="attribution_model" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                    <option value="last_click"  {{ old('attribution_model', 'last_click') === 'last_click'  ? 'selected' : '' }}>{{ __('travel.campaigns.attribution.last_click') }}</option>
                                    <option value="first_click" {{ old('attribution_model') === 'first_click' ? 'selected' : '' }}>{{ __('travel.campaigns.attribution.first_click') }}</option>
                                    <option value="linear"      {{ old('attribution_model') === 'linear'      ? 'selected' : '' }}>{{ __('travel.campaigns.attribution.linear') }}</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-3 pt-6">
                                <button type="button" id="whatsapp-toggle" role="switch"
                                        aria-checked="{{ old('whatsapp_sharing_enabled') ? 'true' : 'false' }}"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2
                                               {{ old('whatsapp_sharing_enabled') ? 'bg-primary-600' : 'bg-gray-200' }}">
                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform
                                                 {{ old('whatsapp_sharing_enabled') ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                </button>
                                <input type="hidden" name="whatsapp_sharing_enabled" id="whatsapp_sharing_enabled_input" value="{{ old('whatsapp_sharing_enabled') ? '1' : '0' }}">
                                <label class="text-sm font-medium text-gray-700 cursor-pointer" for="whatsapp-toggle">
                                    {{ __('travel.campaigns.enable_whatsapp') }}
                                </label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- ═══ STEP 2: Commission & Budget ═══ --}}
            <div class="step-panel hidden" data-panel="2">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                    <div class="px-6 py-4">
                        <h2 class="text-base font-semibold text-gray-900">{{ __('travel.campaigns.steps.commission_budget') }}</h2>
                    </div>
                    <div class="px-6 py-5 space-y-5">

                        {{-- Commission rate --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5" for="offered_commission_rate">
                                {{ __('travel.campaigns.commission_rate_label') }} <span class="text-red-500">*</span>
                            </label>
                            <div class="relative max-w-xs">
                                <input type="number" id="offered_commission_rate" name="offered_commission_rate"
                                       value="{{ old('offered_commission_rate') }}"
                                       min="0" max="100" step="0.01" required
                                       class="w-full rounded-lg border border-gray-300 ps-3 pe-10 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                <span class="absolute inset-y-0 end-3 flex items-center text-gray-400 text-sm">%</span>
                            </div>
                            <p class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                {!! __('travel.campaigns.commission_preview', ['rate' => '<strong id="commission-preview">0%</strong>']) !!}
                            </p>
                        </div>

                        {{-- Commission type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('travel.campaigns.commission_type_label') }} <span class="text-red-500">*</span>
                            </label>
                            <div class="flex flex-wrap gap-3">
                                @foreach ([
                                    ['value' => 'percentage',      'label' => __('travel.campaigns.commission_types.percentage')],
                                    ['value' => 'flat_per_order',  'label' => __('travel.campaigns.commission_types.flat_per_order')],
                                    ['value' => 'flat_per_click',  'label' => __('travel.campaigns.commission_types.flat_per_click')],
                                ] as $ct)
                                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border px-4 py-2.5 text-sm transition-colors has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                                        <input type="radio" name="commission_type" value="{{ $ct['value'] }}"
                                               {{ old('commission_type', 'percentage') === $ct['value'] ? 'checked' : '' }}
                                               class="text-primary-600 focus:ring-primary-500">
                                        {{ $ct['label'] }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Budgets --}}
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="budget_per_marketer_display">
                                    {{ __('travel.campaigns.budget_per_marketer') }}
                                </label>
                                <div class="relative">
                                    <input type="number" id="budget_per_marketer_display" name="budget_per_marketer_display"
                                           value="{{ old('budget_per_marketer_display') }}"
                                           min="0" step="1"
                                           class="w-full rounded-lg border border-gray-300 ps-3 pe-14 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                           placeholder="0">
                                    <span class="absolute inset-y-0 end-3 flex items-center text-gray-400 text-sm">{{ __('common.sar') }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="total_budget_display">
                                    {{ __('travel.campaigns.total_budget') }}
                                </label>
                                <div class="relative">
                                    <input type="number" id="total_budget_display" name="total_budget_display"
                                           value="{{ old('total_budget_display') }}"
                                           min="0" step="1"
                                           class="w-full rounded-lg border border-gray-300 ps-3 pe-14 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                           placeholder="0">
                                    <span class="absolute inset-y-0 end-3 flex items-center text-gray-400 text-sm">{{ __('common.sar') }}</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- ═══ STEP 3: Packages ═══ --}}
            <div class="step-panel hidden" data-panel="3">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                    <div class="px-6 py-4">
                        <h2 class="text-base font-semibold text-gray-900">{{ __('travel.campaigns.select_packages_title') }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('travel.campaigns.select_packages_desc') }}</p>
                    </div>
                    <div class="px-6 py-5 space-y-4">

                        {{-- Package search --}}
                        <div class="relative">
                            <input type="text" id="package-search-input" placeholder="{{ __('travel.campaigns.package_search_placeholder') }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2.5 ps-10 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <svg class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                            </svg>
                        </div>

                        {{-- Search results --}}
                        <div id="package-results" class="hidden rounded-lg border border-gray-200 divide-y divide-gray-100 max-h-60 overflow-y-auto">
                            {{-- Populated by JS --}}
                        </div>

                        {{-- Selected packages pills --}}
                        <div id="selected-packages" class="space-y-2">
                            {{-- Populated by JS --}}
                        </div>

                        <p id="no-packages-msg" class="hidden text-xs text-red-600">{{ __('travel.campaigns.no_packages_error') }}</p>

                        {{-- Hidden inputs populated by JS --}}
                        <div id="package-inputs"></div>
                        <div id="commission-override-inputs"></div>

                    </div>
                </div>

                {{-- Review summary --}}
                <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 px-6 py-5 space-y-2 text-sm">
                    <h3 class="font-semibold text-gray-800 mb-3">{{ __('travel.campaigns.summary_title') }}</h3>
                    <div class="grid grid-cols-2 gap-2 text-gray-600">
                        <span>{{ __('travel.campaigns.summary.name') }}</span>        <span id="summary-name" class="font-medium text-gray-900">—</span>
                        <span>{{ __('travel.campaigns.summary.type') }}</span>         <span id="summary-type" class="font-medium text-gray-900">—</span>
                        <span>{{ __('travel.campaigns.summary.commission') }}</span>   <span id="summary-commission" class="font-medium text-gray-900">—</span>
                        <span>{{ __('travel.campaigns.summary.period') }}</span>       <span id="summary-dates" class="font-medium text-gray-900">—</span>
                        <span>{{ __('travel.campaigns.summary.packages') }}</span>     <span id="summary-packages" class="font-medium text-gray-900">—</span>
                    </div>
                </div>
            </div>

            {{-- Navigation buttons --}}
            <div class="flex items-center justify-between pt-2">
                <button type="button" id="btn-prev"
                        class="hidden inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                    </svg>
                    {{ __('travel.campaigns.prev') }}
                </button>
                <div class="flex items-center gap-3 ms-auto">
                    <button type="button" id="btn-next"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                        {{ __('travel.campaigns.next') }}
                        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                        </svg>
                    </button>
                    <button type="submit" id="btn-save-draft"
                            class="hidden inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        {{ __('travel.campaigns.save_draft') }}
                    </button>
                    <button type="submit" id="btn-save-invite"
                            class="hidden inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                        {{ __('travel.campaigns.save_invite') }}
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>
@endsection
