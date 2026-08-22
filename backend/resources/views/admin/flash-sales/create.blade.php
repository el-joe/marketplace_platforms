@extends('layouts.admin')

@section('title', __('admin.flash_sales.new_flash_sale'))

@push('styles')
    @vite(['resources/js/components/flatpickr.js', 'resources/js/components/select2.js'])
@endpush

@push('scripts')
    @vite('resources/js/admin/flash-sale-form.js')
@endpush

@section('content')

    <form id="flash-sale-form" class="flex flex-col lg:flex-row gap-6 items-start" novalidate>
        @csrf

        {{-- ─── Left column ───────────────────────────────────────────────────── --}}
        <div class="flex-1 min-w-0">

            {{-- Tab nav --}}
            <div x-data="{ tab: 'details' }" class="space-y-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex" aria-label="{{ __('admin.flash_sales.form_tabs_aria_label') }}">
                        <button type="button" @click="tab='details'"
                            :class="tab==='details' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                            class="border-b-2 py-3 px-6 text-sm font-medium transition-colors duration-150 focus:outline-none whitespace-nowrap">
                            {{ __('admin.flash_sales.details') }}
                        </button>
                        <button type="button" @click="tab='rules'"
                            :class="tab==='rules' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                            class="border-b-2 py-3 px-6 text-sm font-medium transition-colors duration-150 focus:outline-none whitespace-nowrap">
                            {{ __('admin.flash_sales.rules_eligibility') }}
                        </button>
                    </nav>
                </div>

                {{-- ── Tab: details ──────────────────────────────────────────── --}}
                <div x-show="tab==='details'" class="space-y-6">

                    <x-card title="{{ __('admin.flash_sales.event_identity') }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-form-input name="name_en" label="{{ __('admin.flash_sales.name_en') }}" required />
                            <x-form-input name="name_ar" label="{{ __('admin.flash_sales.name_ar') }}" dir="rtl" required />
                            <div class="sm:col-span-2">
                                <x-form-textarea name="description_en" label="{{ __('admin.flash_sales.description_en') }}" rows="3" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-form-textarea name="description_ar" label="{{ __('admin.flash_sales.description_ar') }}" dir="rtl" rows="3" />
                            </div>
                            <x-form-select name="country_id" label="{{ __('admin.flash_sales.country') }}" :options="$countries->mapWithKeys(fn($c) => [$c->id => $c->name_en])->toArray()" :nullable="true" placeholder="{{ __('admin.flash_sales.all_countries') }}" />
                            <div class="flex items-end gap-6 pb-1">
                                <x-form-toggle name="is_featured" label="{{ __('admin.flash_sales.featured_on_homepage') }}" />
                                <x-form-toggle name="is_exclusive" label="{{ __('admin.flash_sales.exclusive_invite_only') }}" />
                            </div>
                        </div>
                    </x-card>

                    <x-card title="{{ __('admin.flash_sales.timeline') }}" id="timeline-card">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-form-date-picker name="submission_opens_at" label="{{ __('admin.flash_sales.submissions_open') }}" :enableTime="true"
                                required />
                            <x-form-date-picker name="submission_closes_at" label="{{ __('admin.flash_sales.submissions_close') }}" :enableTime="true"
                                required />
                            <x-form-date-picker name="review_deadline_at" label="{{ __('admin.flash_sales.review_deadline') }}" :enableTime="true"
                                required />
                            <div></div>
                            <x-form-date-picker name="sale_starts_at" label="{{ __('admin.flash_sales.sale_starts') }}" :enableTime="true" required />
                            <x-form-date-picker name="sale_ends_at" label="{{ __('admin.flash_sales.sale_ends') }}" :enableTime="true" required />
                        </div>
                        <div id="timeline-visual" class="mt-4"></div>
                    </x-card>

                </div>

                {{-- ── Tab: rules ────────────────────────────────────────────── --}}
                <div x-show="tab==='rules'" class="space-y-6">

                    <x-card title="{{ __('admin.flash_sales.discount_requirements') }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-form-input name="min_discount_pct" label="{{ __('admin.flash_sales.minimum_discount_pct') }}" type="number" step="0.01"
                                min="0" max="100" required suffix="%" hint="{{ __('admin.flash_sales.minimum_discount_hint') }}" />
                            <x-form-input name="max_products_per_seller" label="{{ __('admin.flash_sales.max_products_per_vendor') }}" type="number"
                                min="1" hint="{{ __('admin.flash_sales.max_products_per_vendor_hint') }}" />
                            <div class="sm:col-span-2">
                                <x-form-toggle name="price_drop_required" label="{{ __('admin.flash_sales.price_drop_required') }}"
                                    hint="{{ __('admin.flash_sales.price_drop_required_hint') }}" :value="true" />
                            </div>
                        </div>
                    </x-card>

                    <x-card title="{{ __('admin.flash_sales.vendor_eligibility') }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-form-checkbox-group name="eligible_seller_tiers" label="{{ __('admin.flash_sales.eligible_tiers') }}"
                                :options="['bronze' => __('admin.flash_sales.tier_bronze'), 'silver' => __('admin.flash_sales.tier_silver'), 'gold' => __('admin.flash_sales.tier_gold'), 'platinum' => __('admin.flash_sales.tier_platinum')]" />
                            <x-form-input name="min_seller_rating" label="{{ __('admin.flash_sales.minimum_seller_rating') }}" type="number" step="0.1"
                                min="0" max="5" />
                        </div>
                    </x-card>

                    <x-card title="{{ __('admin.flash_sales.capacity_commission') }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-form-input name="max_total_slots" label="{{ __('admin.flash_sales.maximum_approved_slots') }}" type="number" min="1"
                                hint="{{ __('admin.flash_sales.maximum_approved_slots_hint') }}" />
                            <x-form-input name="commission_override_pct" label="{{ __('admin.flash_sales.commission_override_pct') }}" type="number"
                                step="0.01" min="0" max="100" hint="{{ __('admin.flash_sales.commission_override_pct_hint') }}" />
                        </div>
                    </x-card>

                    <x-card title="{{ __('admin.flash_sales.eligible_categories') }}">
                        <x-form-checkbox-group name="eligible_categories" label="{{ __('admin.flash_sales.limit_to_categories') }}"
                            :options="$categories->mapWithKeys(fn($c) => [$c->id => $c->name_en])->toArray()" />
                    </x-card>

                </div>
            </div>
        </div>

        {{-- ─── Right sidebar ──────────────────────────────────────────────────── --}}
        <div class="w-full lg:w-72 flex-shrink-0 space-y-4 lg:sticky lg:top-20">
            <x-card>
                <div class="space-y-2">
                    <button type="submit" id="flash-sale-submit-btn" class="btn btn-primary w-full justify-center">
                        {{ __('admin.flash_sales.create_flash_sale') }}
                    </button>
                    <a href="{{ route('admin.flash-sales.index') }}" class="btn btn-ghost w-full justify-center">
                        {{ __('common.cancel') }}
                    </a>
                </div>
            </x-card>
        </div>

    </form>

    <script>
        window.FLASH_SALE_STORE_URL = '{{ route('admin.flash-sales.store') }}';
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            timelineSubOpens: @json(__('admin.flash_sales.timeline_sub_opens')),
            timelineSubCloses: @json(__('admin.flash_sales.timeline_sub_closes')),
            timelineReview: @json(__('admin.flash_sales.timeline_review')),
            timelineSaleStart: @json(__('admin.flash_sales.timeline_sale_start')),
            timelineSaleEnd: @json(__('admin.flash_sales.timeline_sale_end')),
            timelineFillDatesHint: @json(__('admin.flash_sales.timeline_fill_dates_hint')),
            previewNamePlaceholder: @json(__('admin.flash_sales.preview_name_placeholder')),
            saving: @json(__('admin.flash_sales.saving')),
            saveChanges: @json(__('admin.flash_sales.save_changes')),
            save: @json(__('common.save')),
            saved: @json(__('admin.flash_sales.saved')),
            genericError: @json(__('admin.flash_sales.generic_error')),
            confirmCloseSubmissions: @json(__('admin.flash_sales.confirm_close_submissions')),
            confirmEndSale: @json(__('admin.flash_sales.confirm_end_sale')),
            confirmMarkApproved: @json(__('admin.flash_sales.confirm_mark_approved')),
            confirmStartSale: @json(__('admin.flash_sales.confirm_start_sale')),
            statusUpdated: @json(__('admin.flash_sales.status_updated')),
            actionFailed: @json(__('admin.flash_sales.action_failed')),
            cancelling: @json(__('admin.flash_sales.cancelling')),
        });
    </script>

@endsection