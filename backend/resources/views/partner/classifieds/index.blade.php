@extends('layouts.partner')
@section('title', __('partner.classifieds_extra.index_title'))
@section('page-title', __('partner.classifieds_extra.index_title'))

@push('scripts')
    @vite('resources/js/partner/classifieds.js')
    @php $json = [
                'title'        => __('partner.classifieds_extra.wizard.attachments_title'),
                'optional'     => __('partner.classifieds_extra.wizard.attachments_optional'),
                'genericDesc'  => __('partner.classifieds_extra.wizard.attachments_generic_desc'),
                'typedDesc'    => __('partner.classifieds_extra.wizard.attachments_typed_desc'),
                'uploadHint'   => __('partner.classifieds_extra.wizard.attachments_upload_hint'),
                'noFileChosen' => __('partner.classifieds_extra.wizard.no_file_chosen'),
                'chooseFile'   => __('partner.classifieds_extra.wizard.choose_file'),
                'replaceFile'  => __('partner.classifieds_extra.wizard.replace_file'),
            ]; @endphp
    <script>
        window.CLASSIFIEDS_CFG = {
            datatableUrl:       "{{ route('partner.classifieds.datatable') }}",
            categoriesUrl:      "{{ route('partner.classifieds.categories') }}",
            storeUrl:           "{{ route('partner.classifieds.store') }}",
            stepLabelsFull:     @json(__('partner.classifieds_extra.wizard.step_labels_full')),
            stepLabelsNoContract: @json(__('partner.classifieds_extra.wizard.step_labels_no_contract')),
            countryCurrencyMap: @json($countries->pluck('currency_code', 'id')),
            selectCountryFirstLabel: '{{ __('partner.classifieds_extra.wizard.select_country_first') }}',
            attachmentsI18n: @json($json),
        };
    </script>
@endpush

@section('content')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('partner.classifieds_extra.index_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.classifieds_extra.index_subtitle') }}</p>
        </div>
        <button id="btn-open-wizard"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            {{ __('partner.classifieds_extra.create_ad') }}
        </button>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
            </svg>
            <input id="cl-search" type="text" placeholder="{{ __('partner.classifieds_extra.search_placeholder') }}"
                class="w-full ps-9 pe-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-400/40 placeholder-gray-400">
        </div>
        <select id="cl-filter-status" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-400/40">
            <option value="">{{ __('partner.classifieds_extra.all_statuses') }}</option>
            <option value="draft">{{ __('partner.classifieds_extra.status.draft') }}</option>
            <option value="pending_contract">{{ __('partner.classifieds_extra.status.pending_contract') }}</option>
            <option value="pending_review">{{ __('partner.classifieds_extra.status.pending_review') }}</option>
            <option value="active">{{ __('partner.classifieds_extra.status.active') }}</option>
            <option value="paused">{{ __('partner.classifieds_extra.status.paused') }}</option>
            <option value="sold">{{ __('partner.classifieds_extra.status.sold') }}</option>
            <option value="expired">{{ __('partner.classifieds_extra.status.expired') }}</option>
            <option value="rejected">{{ __('partner.classifieds_extra.status.rejected') }}</option>
        </select>
    </div>

    {{-- Listings table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table id="cl-table" class="w-full text-sm" style="width:100%">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <th class="px-4 py-3 text-start font-semibold tracking-wide">{{ __('partner.classifieds_extra.table.ad') }}</th>
                    <th class="px-4 py-3 text-start font-semibold tracking-wide">{{ __('partner.classifieds_extra.table.category') }}</th>
                    <th class="px-4 py-3 text-start font-semibold tracking-wide">{{ __('partner.classifieds_extra.table.status') }}</th>
                    <th class="px-4 py-3 text-start font-semibold tracking-wide">{{ __('partner.classifieds_extra.table.price') }}</th>
                    <th class="px-4 py-3 text-start font-semibold tracking-wide">{{ __('partner.classifieds_extra.table.views') }}</th>
                    <th class="px-4 py-3 text-start font-semibold tracking-wide">{{ __('partner.classifieds_extra.table.created_at') }}</th>
                    <th class="px-4 py-3 text-start font-semibold tracking-wide"></th>
                </tr>
            </thead>
            <tbody id="cl-tbody" class="divide-y divide-gray-100"></tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
            <span id="cl-info" class="text-xs text-gray-400"></span>
            <div id="cl-pagination" class="flex items-center gap-2 text-sm"></div>
        </div>
    </div>

    {{-- Empty state (hidden initially) --}}
    <div id="cl-empty" class="hidden mt-6 py-16 text-center bg-white rounded-2xl border border-gray-200">
        <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/>
        </svg>
        <p class="text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.no_ads') }}</p>
        <p class="text-xs text-gray-400 mb-4">{{ __('partner.classifieds_extra.no_ads_desc') }}</p>
        <button id="btn-open-wizard-empty"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors">
            {{ __('partner.classifieds_extra.create_ad') }}
        </button>
    </div>


    {{-- ═══════════════════════════════════════════════════════════════════════
         CREATE LISTING WIZARD MODAL
         Steps: 1-الفئة → 2-الأساسيات → 3-الموقع والخصائص → 4-الصور والمرفقات → 5-العقد → 6-مراجعة
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div id="cl-wizard-overlay" class="hidden fixed inset-0 z-50 items-center justify-center p-4" role="dialog" aria-modal="true" style="display:none">
        <div id="cl-wizard-backdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-2xl max-h-[92vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ __('partner.classifieds_extra.wizard_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{!! __('partner.classifieds_extra.wizard_step_of', ['current' => '<span id="cl-wiz-step-label">1</span>', 'total' => '<span id="cl-wiz-total-label">6</span>']) !!}</p>
                </div>
                <button id="cl-wiz-close" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Progress bar --}}
            <div class="px-6 pt-4 pb-2 shrink-0">
                <div id="cl-wiz-progress" class="flex items-center"></div>
            </div>

            {{-- Scrollable body --}}
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-5">
                <div id="cl-wiz-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>

                {{-- Step 1: Category selection --}}
                <div id="cl-wiz-step-1" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">{{ __('partner.classifieds_extra.wizard.choose_category') }} <span class="text-red-500">*</span></label>
                        <p class="text-xs text-gray-500 mb-3">{{ __('partner.classifieds_extra.wizard.choose_category_desc') }}</p>
                        <div id="cl-categories-loading" class="py-8 text-center text-sm text-gray-400">{{ __('partner.classifieds_extra.wizard.loading_categories') }}</div>
                        <div id="cl-categories-grid" class="hidden grid-cols-2 gap-3"></div>
                    </div>
                    {{-- Sub-category (shown after parent selection) --}}
                    <div id="cl-subcategory-section" class="hidden space-y-2">
                        <label class="block text-sm font-semibold text-gray-800">{{ __('partner.classifieds_extra.wizard.subcategory') }} <span class="text-red-500">*</span></label>
                        <div id="cl-subcategories-grid" class="grid grid-cols-2 gap-2"></div>
                    </div>
                </div>

                {{-- Step 2: Basics --}}
                <div id="cl-wiz-step-2" class="hidden space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.title_ar') }} <span class="text-red-500">*</span></label>
                            <input type="text" id="cl-title-ar" maxlength="255"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                placeholder="{{ __('partner.classifieds_extra.wizard.title_ar_placeholder') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.title_en') }} <span class="text-red-500">*</span></label>
                            <input type="text" id="cl-title-en" maxlength="255"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                placeholder="{{ __('partner.classifieds_extra.wizard.title_en_placeholder') }}">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.description_ar') }}</label>
                        <textarea id="cl-desc-ar" rows="3"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            placeholder="{{ __('partner.classifieds_extra.wizard.description_placeholder_ar') }}"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.description_en') }}</label>
                        <textarea id="cl-desc-en" rows="3"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            placeholder="{{ __('partner.classifieds_extra.wizard.description_placeholder_en') }}"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('partner.classifieds_extra.wizard.country') }}
                            <span class="text-red-500">*</span>
                        </label>
                        <select id="cl-country-id"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            <option value="">{{ __('partner.classifieds_extra.wizard.select_country') }}</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}" data-currency="{{ $country->currency_code }}">
                                    {{ $country->name_ar ?: $country->name_en }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.purpose') }} <span class="text-red-500">*</span></label>
                            <select id="cl-purpose" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                <option value="sale">{{ __('partner.classifieds_extra.wizard.purpose_sale') }}</option>
                                <option value="rent">{{ __('partner.classifieds_extra.wizard.purpose_rent') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.price') }} <span class="text-red-500">*</span></label>
                            <input type="number" id="cl-price" min="0" step="1"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                placeholder="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.currency') }}</label>
                            <div class="flex items-center gap-2 h-[42px] px-3 rounded-lg border border-gray-200 bg-gray-50">
                                <span id="cl-currency-display" class="text-sm font-mono font-semibold text-gray-400">
                                    {{ __('partner.classifieds_extra.wizard.select_country_first') }}
                                </span>
                            </div>
                            <input type="hidden" id="cl-currency" name="currency" value="">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="cl-negotiable" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span class="text-sm text-gray-700">{{ __('partner.classifieds_extra.wizard.negotiable') }}</span>
                    </label>
                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/60">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="cl-marketer-promo" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <div>
                                <span class="block text-sm font-medium text-gray-800">{{ __('partner.classifieds_extra.wizard.allow_marketer_promo') }}</span>
                                <span class="block text-xs text-gray-500 mt-0.5">{{ __('partner.classifieds_extra.wizard.allow_marketer_promo_desc') }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Step 3: Location & Attributes (conditional) --}}
                <div id="cl-wiz-step-3" class="hidden space-y-4">
                    {{-- City (country already chosen in Step 2) --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.city') }}</label>
                            <select id="cl-city-id" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500" disabled>
                                <option value="">{{ __('partner.classifieds_extra.wizard.select_city') }}</option>
                            </select>
                        </div>
                    </div>

                    {{-- Location map (shown if category requires_location_map) --}}
                    <div id="cl-location-section" class="space-y-3">
                        <label class="block text-sm font-semibold text-gray-800">{{ __('partner.classifieds_extra.wizard.location_title') }}</label>
                        <div class="rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-700">
                            <p class="font-medium mb-1">{{ __('partner.classifieds_extra.wizard.enter_coordinates') }}</p>
                            <p class="text-xs text-blue-600">{{ __('partner.classifieds_extra.wizard.coordinates_hint') }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('partner.classifieds_extra.wizard.latitude') }}</label>
                                <input type="number" id="cl-latitude" step="0.0000001" min="-90" max="90"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                    placeholder="24.7136">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('partner.classifieds_extra.wizard.longitude') }}</label>
                                <input type="number" id="cl-longitude" step="0.0000001" min="-180" max="180"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                    placeholder="46.6753">
                            </div>
                        </div>
                    </div>

                    {{-- Category attributes (dynamic) --}}
                    <div id="cl-attributes-section" class="space-y-3">
                        <label class="block text-sm font-semibold text-gray-800">{{ __('partner.classifieds_extra.wizard.additional_attributes') }}</label>
                        <div id="cl-attributes-fields" class="space-y-3"></div>
                        <p class="text-xs text-gray-400">{{ __('partner.classifieds_extra.wizard.attributes_hint') }}</p>
                    </div>
                </div>

                {{-- Step 4: Images, Sketch, Attachments (conditional) --}}
                <div id="cl-wiz-step-4" class="hidden space-y-5">
                    {{-- Images (required) --}}
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold text-gray-800">{{ __('partner.classifieds_extra.wizard.images_title') }} <span class="text-red-500">*</span></label>
                        <p class="text-xs text-gray-500">{{ __('partner.classifieds_extra.wizard.images_hint') }}</p>
                        <div id="cl-images-dropzone"
                            class="relative border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-primary-400 transition-colors">
                            <input type="file" id="cl-images-input" accept="image/*" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                            </svg>
                            <p class="text-sm text-gray-500">{{ __('partner.classifieds_extra.wizard.images_drop_hint') }} <span class="text-primary-600 font-medium">{{ __('partner.classifieds_extra.wizard.images_drop_action') }}</span></p>
                        </div>
                        <div id="cl-images-preview" class="grid grid-cols-4 gap-2"></div>
                    </div>

                    {{-- Sketch upload (conditional) --}}
                    <div id="cl-sketch-section" class="hidden space-y-3">
                        <label class="block text-sm font-semibold text-gray-800">{{ __('partner.classifieds_extra.wizard.sketch_title') }} <span class="text-gray-400 text-xs font-normal">{{ __('partner.classifieds_extra.wizard.sketch_optional') }}</span></label>
                        <div class="relative border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-primary-400 transition-colors">
                            <input type="file" id="cl-sketch-input" accept=".pdf,.jpg,.jpeg,.png,.dwg" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <p class="text-sm text-gray-500">{{ __('partner.classifieds_extra.wizard.sketch_upload_hint') }}</p>
                        </div>
                        <div id="cl-sketch-name" class="hidden text-xs text-gray-500 flex items-center gap-2"></div>
                    </div>

                    {{-- Additional Attachments (always shown; per-type or generic based on category) --}}
                    <div id="cl-attachments-section" class="space-y-3">
                        {{-- Inner content is (re)rendered by wizRenderConditionalFileFields() in classifieds.js --}}
                    </div>
                </div>

                {{-- Step 5: Contract (conditional — only if category has_contract) --}}
                <div id="cl-wiz-step-5" class="hidden space-y-4">
                    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        {{ __('partner.classifieds_extra.wizard.contract_required_notice') }}
                    </div>
                    <div id="cl-contract-loading" class="py-6 text-center text-sm text-gray-400">{{ __('partner.classifieds_extra.wizard.loading_contract') }}</div>
                    <div id="cl-contract-content" class="hidden">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 max-h-64 overflow-y-auto text-sm text-gray-700 leading-relaxed" id="cl-contract-text"></div>
                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.classifieds_extra.wizard.signature_name') }} <span class="text-red-500">*</span></label>
                                <input type="text" id="cl-signature-name"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                    placeholder="{{ __('partner.classifieds_extra.wizard.signature_name_placeholder') }}">
                            </div>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" id="cl-contract-agree" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700">{{ __('partner.classifieds_extra.wizard.contract_agree') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Step 6 (or 5 without contract): Review --}}
                <div id="cl-wiz-step-6" class="hidden space-y-4">
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                        {{ __('partner.classifieds_extra.wizard.review_notice') }}
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 divide-y divide-gray-100 text-sm" id="cl-review-table"></div>
                </div>
            </div>

            {{-- Footer buttons --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50 shrink-0">
                <button id="cl-wiz-prev" class="hidden items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    {{ __('partner.classifieds_extra.wizard.prev') }}
                </button>
                <span id="cl-wiz-prev-spacer"></span>
                <div class="flex items-center gap-3">
                    <button id="cl-wiz-cancel" class="text-sm text-gray-500 hover:text-gray-700">{{ __('partner.classifieds_extra.wizard.cancel') }}</button>
                    <button id="cl-wiz-next" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">
                        {{ __('partner.classifieds_extra.wizard.next') }}
                        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </button>
                    <button id="cl-wiz-submit" class="hidden items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                        {{ __('partner.classifieds_extra.wizard.submit_ad') }}
                    </button>
                </div>
            </div>

        </div>
    </div>

@endsection
