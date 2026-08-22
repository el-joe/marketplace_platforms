@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/reviews.js'])
@endpush

@section('title', __('admin.reviews_section.management_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.reviews_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.reviews_section.moderate_desc') }}</p>
        </div>
        <x-export-dropdown />
    </div>

    {{-- ─── Stats ──────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.reviews_section.pending') }}" :value="number_format($stats['pending'])" iconBg="bg-warning-100 text-warning-600" />
        <x-stat-card title="{{ __('admin.reviews_section.auto_flagged') }}" :value="number_format($stats['auto_flagged'])" iconBg="{{ $stats['auto_flagged'] > 0 ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-400' }}" />
        <x-stat-card title="{{ __('admin.reviews_section.published_today') }}" :value="number_format($stats['published_today'])" iconBg="bg-success-100 text-success-600" />
        <x-stat-card title="{{ __('admin.reviews_section.avg_rating') }}" :value="$stats['avg_rating'] ? number_format($stats['avg_rating'], 1) . ' ★' : '—'" iconBg="bg-yellow-100 text-yellow-600" />
    </div>

    {{-- ─── AI Flag Alert ────────────────────────────────────────────────────────── --}}
    @if($aiFlaggedCount > 0)
        <div class="mb-5 flex items-center gap-3 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
            <svg class="w-5 h-5 flex-shrink-0 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><strong>{{ number_format($aiFlaggedCount) }}</strong> review{{ $aiFlaggedCount !== 1 ? 's' : '' }} {{ __('admin.reviews_section.flagged_by_ai') }} — <button type="button" class="underline font-medium" id="tab-ai-flagged-trigger">{{ __('admin.reviews_section.review_now') }}</button></span>
        </div>
    @endif

    {{-- ─── Quick Action Tabs ────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-1 mb-4 border-b border-gray-200 overflow-x-auto whitespace-nowrap -mx-4 px-4 sm:mx-0 sm:px-0">
        @foreach([
            ['id' => 'all',         'label' => __('admin.reviews_section.tab_all'),        'status' => ''],
            ['id' => 'pending',     'label' => __('admin.reviews_section.pending'),    'status' => 'pending'],
            ['id' => 'ai_flagged',  'label' => __('admin.reviews_section.tab_ai_flagged'), 'status' => 'auto_flagged'],
            ['id' => 'rejected',    'label' => __('admin.reviews_section.tab_rejected'),   'status' => 'rejected'],
        ] as $tab)
            <button
                type="button"
                class="tab-btn flex-shrink-0 px-4 py-2 text-sm font-medium border-b-2 transition-colors
                    {{ $loop->first ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                data-tab="{{ $tab['id'] }}"
                data-status="{{ $tab['status'] }}">
                {{ $tab['label'] }}
                @if($tab['status'] === 'pending' && $stats['pending'] > 0)
                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-700">{{ $stats['pending'] }}</span>
                @endif
                @if($tab['id'] === 'ai_flagged' && $aiFlaggedCount > 0)
                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">{{ $aiFlaggedCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        {{-- Row 1: primary filters --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">

            {{-- Search --}}
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">{{ __('admin.reviews_section.search') }}</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-2.5 -translate-y-1/2 w-4 h-4 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="search-input"
                           class="form-input w-full text-sm pl-9"
                           placeholder="{{ __('admin.reviews_section.search_placeholder') }}">
                </div>
            </div>

            {{-- Status --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">{{ __('admin.reviews_section.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.reviews_section.all_statuses') }}</option>
                    <option value="pending">{{ __('admin.reviews_section.pending') }}</option>
                    <option value="published">{{ __('admin.reviews_section.published') }}</option>
                    <option value="rejected">{{ __('admin.reviews_section.tab_rejected') }}</option>
                    <option value="flagged">{{ __('admin.reviews_section.flagged') }}</option>
                    <option value="auto_flagged">{{ __('admin.reviews_section.auto_flagged') }}</option>
                </select>
            </div>

            {{-- Country --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">{{ __('admin.reviews_section.country') }}</label>
                <select id="filter-country" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.reviews_section.all_countries') }}</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Listing Type --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">{{ __('admin.reviews_section.listing_type') }}</label>
                <select id="filter-listing-type" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.reviews_section.all_listing_types') }}</option>
                    <option value="vendor">{{ __('admin.reviews_section.vendor_listing') }}</option>
                    <option value="admin">{{ __('admin.reviews_section.admin_listing') }}</option>
                </select>
            </div>

            {{-- Rating --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">{{ __('admin.reviews_section.rating') }}</label>
                <div class="flex gap-1">
                    @foreach([1,2,3,4,5] as $r)
                        <label class="flex-1 flex items-center justify-center cursor-pointer"
                               title="{{ $r }} star{{ $r > 1 ? 's' : '' }}">
                            <input type="checkbox" class="rating-checkbox sr-only" value="{{ $r }}">
                            <span class="w-full h-9 flex items-center justify-center rounded-lg border text-sm font-semibold
                                         rating-star-btn border-gray-200 text-gray-400
                                         hover:border-yellow-400 hover:text-yellow-500 hover:bg-yellow-50
                                         transition-all select-none">
                                {{ $r }}★
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Divider --}}
        <div class="border-t border-gray-100 mb-4"></div>

        {{-- Row 2: secondary filters --}}
        <div class="flex flex-wrap items-center gap-3">

            {{-- Date range --}}
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-gray-500 whitespace-nowrap">{{ __('admin.reviews_section.date_range') }}</span>
                <input type="date" id="filter-date-from" class="form-input text-sm w-full xs:w-auto sm:w-36">
                <span class="text-gray-300 font-bold hidden sm:inline">→</span>
                <input type="date" id="filter-date-to" class="form-input text-sm w-full xs:w-auto sm:w-36">
            </div>

            <div class="h-5 w-px bg-gray-200 hidden lg:block"></div>

            {{-- Toggle pills --}}
            <div class="flex flex-wrap items-center gap-2 lg:ml-auto">

                {{-- Verified purchase toggle --}}
                <label class="cursor-pointer select-none">
                    <input type="checkbox" id="filter-verified" class="sr-only peer">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-medium
                                 transition-all duration-150 border-gray-200 text-gray-500
                                 hover:border-success-400 hover:text-success-600
                                 peer-checked:border-success-500 peer-checked:text-success-700 peer-checked:bg-success-50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ __('admin.reviews_section.verified') }}
                    </span>
                </label>

                {{-- AI Flagged toggle --}}
                <label class="cursor-pointer select-none">
                    <input type="checkbox" id="filter-ai-flagged" class="sr-only peer">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-medium
                                 transition-all duration-150 border-gray-200 text-gray-500
                                 hover:border-orange-400 hover:text-orange-600
                                 peer-checked:border-orange-500 peer-checked:text-orange-700 peer-checked:bg-orange-50">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ __('admin.reviews_section.ai_flagged') }}
                    </span>
                </label>

                <div class="h-5 w-px bg-gray-200"></div>
                <button type="button" id="clear-filters" class="btn btn-ghost btn-sm">{{ __('admin.reviews_section.reset') }}</button>
            </div>
        </div>
    </x-card>

    {{-- ─── Bulk action bar ─────────────────────────────────────────────────────── --}}
    <div id="bulk-action-bar" class="hidden mb-4 flex items-center gap-3 rounded-lg border border-primary-200 bg-primary-50 px-4 py-2.5">
        <span class="text-sm text-primary-700 font-medium"><span id="selected-count">0</span> {{ __('admin.reviews_section.selected') }}</span>
        <button type="button" class="btn btn-success btn-sm" id="bulk-approve-btn">{{ __('admin.reviews_section.approve_all') }}</button>
        <button type="button" class="btn btn-danger btn-sm" id="bulk-reject-btn">{{ __('admin.reviews_section.reject_all') }}</button>
        <button type="button" class="btn btn-secondary btn-sm" id="bulk-delete-btn">{{ __('admin.reviews_section.delete_all') }}</button>
        <button type="button" class="btn btn-ghost btn-xs ml-auto" id="deselect-all-btn">✕ {{ __('admin.reviews_section.clear') }}</button>
    </div>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="reviews-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-3 w-8">
                            <input type="checkbox" id="select-all-checkbox" class="form-checkbox">
                        </th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.reviews_section.product') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.reviews_section.customer') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.reviews_section.rating') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.reviews_section.status') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.reviews_section.ai_col') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.reviews_section.verified') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">👍</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">👎</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.reviews_section.date') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('admin.reviews_section.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Reject Modal ─────────────────────────────────────────────────────────── --}}
    <x-modal id="reject-modal" title="{{ __('admin.reviews_section.reject_review_title') }}" size="sm">
        <p class="text-sm text-gray-600 mb-3">{{ __('admin.reviews_section.reject_note_prompt') }}</p>
        <textarea id="reject-reason-input" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.reviews_section.optional_rejection_reason') }}"></textarea>
        <div class="flex justify-end gap-3 mt-4">
            <button type="button" class="btn btn-secondary" onclick="$('#reject-modal').modal('close')">{{ __('admin.reviews_section.cancel') }}</button>
            <button type="button" id="confirm-reject-btn" class="btn btn-danger">{{ __('admin.reviews_section.reject_review') }}</button>
        </div>
    </x-modal>

    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            bulkActionConfirm: @json(__('admin.reviews_section.bulk_action_confirm')),
            actionApprove: @json(__('admin.reviews_section.action_approve')),
            actionReject: @json(__('admin.reviews_section.action_reject')),
            actionDelete: @json(__('admin.reviews_section.action_delete')),
            bulkActionDone: @json(__('admin.reviews_section.bulk_action_done')),
            bulkActionFailed: @json(__('admin.reviews_section.bulk_action_failed')),
            confirmApprovePublish: @json(__('admin.reviews_section.confirm_approve_publish')),
            reviewApproved: @json(__('admin.reviews_section.review_approved')),
            approvalFailed: @json(__('admin.reviews_section.approval_failed')),
            confirmDeleteReview: @json(__('admin.reviews_section.confirm_delete_review')),
            reviewDeleted: @json(__('admin.reviews_section.review_deleted')),
            deleteFailed: @json(__('admin.reviews_section.delete_failed')),
            reviewRejected: @json(__('admin.reviews_section.review_rejected')),
            rejectionFailed: @json(__('admin.reviews_section.rejection_failed')),
            rejecting: @json(__('admin.reviews_section.rejecting')),
            confirmRejectBtnLabel: @json(__('admin.reviews_section.reject_review')),
        });
    </script>

@endsection
