@extends('layouts.admin')

@section('title', $sale->name_en)

@push('styles')
    @vite(['resources/js/components/flatpickr.js', 'resources/js/components/select2.js', 'resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@push('scripts')
    @vite(['resources/js/admin/flash-sale-form.js', 'resources/js/admin/flash-sale-detail.js'])
@endpush

@section('content')

@php
    $isEditable = !in_array($sale->status->value, ['live', 'ended', 'cancelled']);
    $hasSubmissions = $sale->submissions()->exists();
@endphp

<div class="flex flex-col lg:flex-row gap-6 items-start">

    {{-- ─── Left column ───────────────────────────────────────────────────── --}}
    <div class="flex-1 min-w-0"
         x-data="{ tab: '{{ $sale->status->value === 'live' ? 'live-monitor' : ($sale->status->value === 'ended' ? 'analytics' : 'details') }}' }">

        {{-- Tab nav --}}
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex overflow-x-auto" aria-label="{{ __('admin.flash_sales.tabs_aria_label') }}">
                <button type="button" @click="tab='details'"
                    :class="tab==='details' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="border-b-2 py-3 px-5 text-sm font-medium transition-colors duration-150 focus:outline-none whitespace-nowrap">
                    {{ __('admin.flash_sales.details') }}
                </button>
                <button type="button" @click="tab='rules'"
                    :class="tab==='rules' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="border-b-2 py-3 px-5 text-sm font-medium transition-colors duration-150 focus:outline-none whitespace-nowrap">
                    {{ __('admin.flash_sales.rules_eligibility') }}
                </button>
                @if($sale->status->value !== 'draft')
                <button type="button" @click="tab='invitations'"
                    :class="tab==='invitations' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="border-b-2 py-3 px-5 text-sm font-medium transition-colors duration-150 focus:outline-none whitespace-nowrap">
                    {{ __('admin.flash_sales.invitations_tab') }}
                </button>
                @endif
                @if($hasSubmissions)
                <button type="button" @click="tab='submissions'"
                    :class="tab==='submissions' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="border-b-2 py-3 px-5 text-sm font-medium transition-colors duration-150 focus:outline-none whitespace-nowrap">
                    {{ __('admin.flash_sales.submissions_tab') }}
                </button>
                @endif
                @if($sale->status->value === 'live')
                <button type="button" @click="tab='live-monitor'"
                    :class="tab==='live-monitor' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="border-b-2 py-3 px-5 text-sm font-medium transition-colors duration-150 focus:outline-none whitespace-nowrap">
                    ⚡ {{ __('admin.flash_sales.live_monitor') }}
                </button>
                @endif
                @if($sale->status->value === 'ended')
                <button type="button" @click="tab='analytics'"
                    :class="tab==='analytics' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="border-b-2 py-3 px-5 text-sm font-medium transition-colors duration-150 focus:outline-none whitespace-nowrap">
                    {{ __('admin.flash_sales.analytics') }}
                </button>
                @endif
            </nav>
        </div>

        {{-- ── Tab: details ──────────────────────────────────────────────── --}}
        <div x-show="tab==='details'">
            <form id="flash-sale-form" class="space-y-6" novalidate>
                @csrf
                @method('PUT')

                <x-card title="{{ __('admin.flash_sales.event_identity') }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-form-input name="name_en" label="{{ __('admin.flash_sales.name_en') }}" :value="$sale->name_en" required :disabled="!$isEditable" />
                        <x-form-input name="name_ar" label="{{ __('admin.flash_sales.name_ar') }}"  :value="$sale->name_ar" dir="rtl" required :disabled="!$isEditable" />
                        <div class="sm:col-span-2">
                            <x-form-textarea name="description_en" label="{{ __('admin.flash_sales.description_en') }}" :value="$sale->description_en" rows="3" :disabled="!$isEditable" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-form-textarea name="description_ar" label="{{ __('admin.flash_sales.description_ar') }}" :value="$sale->description_ar" dir="rtl" rows="3" :disabled="!$isEditable" />
                        </div>
                        <x-form-select name="country_id" label="{{ __('admin.country') }}"
                            :options="$countries->mapWithKeys(fn($c) => [$c->id => $c->name_en])->toArray()"
                            :selected="$sale->country_id" :nullable="true" placeholder="{{ __('admin.flash_sales.all_countries') }}" :disabled="!$isEditable" />
                        <div class="flex items-end gap-6 pb-1">
                            <x-form-toggle name="is_featured"  label="{{ __('admin.flash_sales.featured') }}"  :value="$sale->is_featured"  :disabled="!$isEditable" />
                            <x-form-toggle name="is_exclusive" label="{{ __('admin.flash_sales.exclusive') }}" :value="$sale->is_exclusive" :disabled="!$isEditable" />
                        </div>
                    </div>
                </x-card>

                <x-card title="{{ __('admin.flash_sales.timeline') }}" id="timeline-card">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-form-date-picker name="submission_opens_at"  label="{{ __('admin.flash_sales.submissions_open') }}"  :value="$sale->submission_opens_at?->format('Y-m-d H:i')"  :enableTime="true" required :disabled="!$isEditable" />
                        <x-form-date-picker name="submission_closes_at" label="{{ __('admin.flash_sales.submissions_close') }}" :value="$sale->submission_closes_at?->format('Y-m-d H:i')" :enableTime="true" required :disabled="!$isEditable" />
                        <x-form-date-picker name="review_deadline_at"   label="{{ __('admin.flash_sales.review_deadline') }}"   :value="$sale->review_deadline_at?->format('Y-m-d H:i')"   :enableTime="true" required :disabled="!$isEditable" />
                        <div></div>
                        <x-form-date-picker name="sale_starts_at" label="{{ __('admin.flash_sales.sale_starts') }}" :value="$sale->sale_starts_at?->format('Y-m-d H:i')" :enableTime="true" required :disabled="!$isEditable" />
                        <x-form-date-picker name="sale_ends_at"   label="{{ __('admin.flash_sales.sale_ends') }}"   :value="$sale->sale_ends_at?->format('Y-m-d H:i')"   :enableTime="true" required :disabled="!$isEditable" />
                    </div>
                    <div id="timeline-visual" class="mt-4"></div>
                </x-card>

                @if($isEditable)
                <div class="flex justify-end">
                    <button type="submit" id="flash-sale-submit-btn" class="btn btn-primary">{{ __('admin.flash_sales.save_changes') }}</button>
                </div>
                @endif
            </form>
        </div>

        {{-- ── Tab: rules ─────────────────────────────────────────────────── --}}
        <div x-show="tab==='rules'">
            <form id="flash-sale-form-rules" class="space-y-6" novalidate>
                @csrf @method('PUT')

                <x-card title="{{ __('admin.flash_sales.discount_requirements') }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-form-input name="min_discount_pct" label="{{ __('admin.flash_sales.minimum_discount_pct') }}" type="number"
                            step="0.01" min="0" max="100" :value="$sale->min_discount_pct" required suffix="%" :disabled="!$isEditable" />
                        <x-form-input name="max_products_per_seller" label="{{ __('admin.flash_sales.max_products_per_vendor') }}" type="number"
                            min="1" :value="$sale->max_products_per_seller" :disabled="!$isEditable" />
                        <div class="sm:col-span-2">
                            <x-form-toggle name="price_drop_required" label="{{ __('admin.flash_sales.price_drop_required') }}" :value="$sale->price_drop_required" :disabled="!$isEditable" />
                        </div>
                    </div>
                </x-card>

                <x-card title="{{ __('admin.flash_sales.vendor_eligibility') }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-form-checkbox-group name="eligible_seller_tiers"
                            label="{{ __('admin.flash_sales.eligible_tiers') }}"
                            :options="['bronze' => __('admin.flash_sales.tier_bronze'), 'silver' => __('admin.flash_sales.tier_silver'), 'gold' => __('admin.flash_sales.tier_gold'), 'platinum' => __('admin.flash_sales.tier_platinum')]"
                            :values="$sale->eligible_seller_tiers ?? []" :disabled="!$isEditable" />
                        <x-form-input name="min_seller_rating" label="{{ __('admin.flash_sales.minimum_seller_rating') }}"
                            type="number" step="0.1" min="0" max="5" :value="$sale->min_seller_rating" :disabled="!$isEditable" />
                    </div>
                </x-card>

                <x-card title="{{ __('admin.flash_sales.capacity_commission') }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-form-input name="max_total_slots" label="{{ __('admin.flash_sales.maximum_approved_slots') }}"
                            type="number" min="1" :value="$sale->max_total_slots"
                            :hint="$sale->approved_slots_count ? __('admin.flash_sales.currently_approved', ['count' => $sale->approved_slots_count]) : __('admin.flash_sales.maximum_approved_slots_hint')"
                            :disabled="!$isEditable" />
                        <x-form-input name="commission_override_pct" label="{{ __('admin.flash_sales.commission_override_pct') }}"
                            type="number" step="0.01" min="0" max="100" :value="$sale->commission_override_pct" :disabled="!$isEditable" />
                    </div>
                </x-card>

                <x-card title="{{ __('admin.flash_sales.eligible_categories') }}">
                    <x-form-checkbox-group name="eligible_categories"
                        label="{{ __('admin.flash_sales.limit_to_categories') }}"
                        :options="$categories->mapWithKeys(fn($c) => [$c->id => $c->name_en])->toArray()"
                        :values="$sale->eligible_categories ?? []" :disabled="!$isEditable" />
                </x-card>

                @if($isEditable)
                <div class="flex justify-end">
                    <button type="submit" id="flash-sale-rules-submit-btn" class="btn btn-primary">{{ __('admin.flash_sales.save_changes') }}</button>
                </div>
                @endif
            </form>
        </div>

        {{-- ── Tab: invitations ───────────────────────────────────────────── --}}
        @if($sale->status->value !== 'draft')
        <div x-show="tab==='invitations'" class="space-y-4">

            @php
                $invStatConfig = [
                    'pending'   => ['label' => __('admin.flash_sales.pending'),   'color' => 'bg-amber-50 border-amber-200',    'dot' => 'bg-amber-400',   'text' => 'text-amber-800'],
                    'accepted'  => ['label' => __('admin.flash_sales.accepted'),  'color' => 'bg-emerald-50 border-emerald-200','dot' => 'bg-emerald-500', 'text' => 'text-emerald-800'],
                    'declined'  => ['label' => __('admin.flash_sales.declined'),  'color' => 'bg-red-50 border-red-200',        'dot' => 'bg-red-400',     'text' => 'text-red-800'],
                    'submitted' => ['label' => __('admin.flash_sales.submitted'), 'color' => 'bg-blue-50 border-blue-200',      'dot' => 'bg-blue-500',    'text' => 'text-blue-800'],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach($invStatConfig as $status => $cfg)
                    <div class="rounded-xl border {{ $cfg['color'] }} px-4 py-3 flex items-center gap-3">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $cfg['dot'] }}"></span>
                        <div>
                            <p class="text-xs font-medium {{ $cfg['text'] }}">{{ $cfg['label'] }}</p>
                            <p class="text-2xl font-bold {{ $cfg['text'] }} leading-tight">{{ $invitationStats[$status] ?? 0 }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <p class="text-sm text-gray-500">{{ __('admin.flash_sales.vendors_invited_total', ['count' => $invitationCount]) }}</p>
                        <select id="inv-filter-status" class="form-select form-select-sm">
                            <option value="">{{ __('admin.flash_sales.all_statuses') }}</option>
                            @foreach($invStatConfig as $status => $cfg)
                                <option value="{{ $status }}">{{ $cfg['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(!in_array($sale->status->value, ['ended', 'cancelled']))
                        <div class="flex gap-2">
                            <button type="button" id="btn-auto-invite" class="btn btn-secondary btn-sm">
                                <x-heroicon name="user-group" class="w-4 h-4 mr-1.5" />
                                {{ __('admin.flash_sales.auto_invite_eligible') }}
                            </button>
                            <button type="button" data-modal-open="manual-invite-modal" class="btn btn-ghost btn-sm">
                                <x-heroicon name="user-plus" class="w-4 h-4 mr-1.5" />
                                {{ __('admin.flash_sales.invite_manually') }}
                            </button>
                        </div>
                    @endif
                </div>
                <div class="overflow-x-auto">
                <table id="invitations-table" class="w-full text-sm"></table>
                </div>
            </x-card>
        </div>
        @endif

        {{-- ── Tab: submissions ───────────────────────────────────────────── --}}
        @if($hasSubmissions)
        <div x-show="tab==='submissions'" class="space-y-4">

            @php
                $pillColors = [
                    'submitted'    => 'bg-gray-100 text-gray-700',
                    'under_review' => 'bg-amber-100 text-amber-800',
                    'approved'     => 'bg-primary-100 text-primary-800',
                    'live'         => 'bg-emerald-100 text-emerald-800',
                    'sold_out'     => 'bg-orange-100 text-orange-800',
                    'rejected'     => 'bg-red-100 text-red-800',
                    'withdrawn'    => 'bg-gray-100 text-gray-500',
                    'ended'        => 'bg-gray-100 text-gray-500',
                ];
                $submissionStatusLabels = [
                    'submitted'    => __('admin.flash_sales.submitted'),
                    'under_review' => __('admin.flash_sales.status_under_review'),
                    'approved'     => __('admin.flash_sales.status_approved'),
                    'live'         => __('admin.flash_sales.status_live'),
                    'sold_out'     => __('admin.flash_sales.sold_out'),
                    'rejected'     => __('admin.flash_sales.status_rejected'),
                    'withdrawn'    => __('admin.flash_sales.status_withdrawn'),
                    'ended'        => __('admin.flash_sales.status_ended'),
                ];
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                @foreach($submissionStats as $status => $count)
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pillColors[$status] ?? 'bg-gray-100 text-gray-700' }}">
                        {{ $submissionStatusLabels[$status] ?? ucwords(str_replace('_', ' ', $status)) }}
                        <span class="font-bold">{{ $count }}</span>
                    </span>
                @endforeach

                <div class="{{ app()->getLocale() == 'ar' ? 'mr' : 'ml' }}-auto flex items-center gap-2">
                    <select id="sub-filter-status" class="form-select form-select-sm">
                        <option value="">{{ __('admin.flash_sales.all_statuses') }}</option>
                        @foreach([
                            'submitted'    => $submissionStatusLabels['submitted'],
                            'under_review' => $submissionStatusLabels['under_review'],
                            'approved'     => $submissionStatusLabels['approved'],
                            'rejected'     => $submissionStatusLabels['rejected'],
                            'live'         => $submissionStatusLabels['live'],
                            'sold_out'     => $submissionStatusLabels['sold_out'],
                            'ended'        => $submissionStatusLabels['ended'],
                        ] as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="btn-bulk-reject" class="btn btn-danger btn-sm hidden">
                        {{ __('admin.flash_sales.bulk_reject') }}
                    </button>
                </div>
            </div>

            <x-card>
                <div class="overflow-x-auto">
                <table id="submissions-table" class="w-full text-sm"></table>
                </div>
            </x-card>
        </div>
        @endif

        {{-- ── Tab: live-monitor ──────────────────────────────────────────── --}}
        @if($sale->status->value === 'live')
        <div x-show="tab==='live-monitor'" id="live-monitor-section" class="space-y-4">
            @php $remaining = max(0, now()->diffInSeconds($sale->sale_ends_at, false)); @endphp

            <div class="text-center py-6"
                 x-data="{ remaining: {{ $remaining }} }"
                 x-init="setInterval(() => { if(remaining > 0) remaining-- }, 1000)">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ __('admin.flash_sales.time_remaining') }}</p>
                <p class="font-mono text-4xl font-bold"
                   :class="remaining < 3600 ? 'text-danger-600' : 'text-gray-900'"
                   x-text="new Date(remaining * 1000).toISOString().slice(11,19)">
                </p>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <x-stat-card label="{{ __('admin.flash_sales.units_sold') }}"  value="—" id="live-units"   color="success" />
                <x-stat-card label="{{ __('admin.flash_sales.revenue') }}"     value="—" id="live-revenue" color="primary" />
                <x-stat-card label="{{ __('admin.flash_sales.sold_out') }}"    value="—" id="live-soldout" color="danger"  />
            </div>

            <x-card title="{{ __('admin.flash_sales.live_submissions') }}">
                <table id="live-submissions-table" class="w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 border-b">
                            <th class="py-2 px-3 text-start">{{ __('admin.flash_sales.product') }}</th>
                            <th class="py-2 px-3 text-end">{{ __('admin.flash_sales.sold') }}</th>
                            <th class="py-2 px-3 text-end">{{ __('admin.flash_sales.remaining') }}</th>
                            <th class="py-2 px-3 text-end">{{ __('admin.flash_sales.revenue') }}</th>
                            <th class="py-2 px-3">{{ __('admin.status') }}</th>
                        </tr>
                    </thead>
                    <tbody id="live-submissions-tbody"></tbody>
                </table>
            </x-card>
        </div>
        @endif

        {{-- ── Tab: analytics ─────────────────────────────────────────────── --}}
        @if($sale->status->value === 'ended')
        <div x-show="tab==='analytics'" class="space-y-4" id="analytics-section">
            <div class="grid grid-cols-3 gap-3">
                <x-stat-card label="{{ __('admin.flash_sales.total_units_sold') }}"    value="—" id="an-units"      color="primary" />
                <x-stat-card label="{{ __('admin.flash_sales.gross_revenue') }}"       value="—" id="an-revenue"    color="success" />
                <x-stat-card label="{{ __('admin.flash_sales.discount_given') }}"      value="—" id="an-discount"   color="warning" />
                <x-stat-card label="{{ __('admin.flash_sales.commission') }}"          value="—" id="an-commission" color="info" />
                <x-stat-card label="{{ __('admin.flash_sales.vendor_payouts') }}"      value="—" id="an-payout"     color="gray" />
                <x-stat-card label="{{ __('admin.flash_sales.avg_conversion_pct') }}"    value="—" id="an-conversion" color="info" />
            </div>
            <x-card title="{{ __('admin.flash_sales.revenue_vs_discount') }}">
                <div style="height:250px"><canvas id="analytics-chart"></canvas></div>
            </x-card>
        </div>
        @endif

    </div>{{-- /left column --}}

    {{-- ─── Right sidebar ──────────────────────────────────────────────────── --}}
    <div class="w-full lg:w-72 flex-shrink-0 space-y-4 lg:sticky lg:top-20">

        {{-- Status card --}}
        <x-card title="{{ __('admin.status') }}">
            @php
                $statusColors = [
                    'draft'             => 'gray',
                    'submission_open'   => 'primary',
                    'submission_closed' => 'warning',
                    'under_review'      => 'info',
                    'approved'          => 'success',
                    'live'              => 'success',
                    'ended'             => 'gray',
                    'cancelled'         => 'danger',
                ];
                $flashSaleStatusLabels = [
                    'draft'             => __('admin.flash_sales.status_draft'),
                    'submission_open'   => __('admin.flash_sales.status_submission_open'),
                    'submission_closed' => __('admin.flash_sales.status_submission_closed'),
                    'under_review'      => __('admin.flash_sales.status_under_review'),
                    'approved'          => __('admin.flash_sales.status_approved'),
                    'live'              => __('admin.flash_sales.status_live'),
                    'ended'             => __('admin.flash_sales.status_ended'),
                    'cancelled'         => __('admin.flash_sales.status_cancelled'),
                ];
                $color = $statusColors[$sale->status->value] ?? 'gray';
                $label = $flashSaleStatusLabels[$sale->status->value] ?? $sale->status->value;
            @endphp
            <div class="text-center mb-4">
                <span class="badge badge-{{ $color }} text-base px-4 py-1">{{ $label }}</span>
            </div>

            @if($sale->cancelled_at)
                <div class="text-xs text-danger-600 mb-3">
                    {{ __('admin.flash_sales.status_cancelled') }} {{ $sale->cancelled_at->diffForHumans() }}<br>
                    @if($sale->cancellation_reason)
                        <em>{{ $sale->cancellation_reason }}</em>
                    @endif
                </div>
            @endif

            {{-- Timeline steps --}}
            <ol class="space-y-2 mb-4">
                @foreach ($flashSaleStatusLabels as $sv => $sl)
                    @if($sv === 'cancelled') @continue @endif
                    @php
                        $steps = ['draft','submission_open','submission_closed','under_review','approved','live','ended'];
                        $currentIdx = array_search($sale->status->value, $steps);
                        $stepIdx    = array_search($sv, $steps);
                        $isDone     = $currentIdx !== false && $stepIdx < $currentIdx;
                        $isCurrent  = $sv === $sale->status->value;
                    @endphp
                    <li class="flex items-center gap-2 text-xs">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0
                            {{ $isDone ? 'bg-success-500 text-white' : ($isCurrent ? 'bg-primary-500 text-white' : 'border-2 border-gray-300 text-gray-300') }}">
                            @if($isDone) ✓ @elseif($isCurrent) ● @else ○ @endif
                        </span>
                        <span class="{{ $isCurrent ? 'font-semibold text-gray-900' : ($isDone ? 'text-success-700' : 'text-gray-400') }}">
                            {{ $sl }}
                        </span>
                    </li>
                @endforeach
            </ol>

            {{-- Action buttons --}}
            <div class="space-y-2" x-data="{}">
                @if($sale->status->value === 'draft')
                    <button data-transition="open_submissions" data-sale-id="{{ $sale->id }}"
                        class="btn btn-primary w-full justify-center btn-sm">{{ __('admin.flash_sales.open_submissions') }}</button>
                    <button data-transition="cancel" data-sale-id="{{ $sale->id }}"
                        class="btn btn-danger-outline w-full justify-center btn-sm">{{ __('common.cancel') }}</button>
                @elseif($sale->status->value === 'submission_open')
                    <button data-transition="close_submissions" data-sale-id="{{ $sale->id }}"
                        class="btn btn-warning w-full justify-center btn-sm">{{ __('admin.flash_sales.close_submissions_early') }}</button>
                    <button data-transition="cancel" data-sale-id="{{ $sale->id }}"
                        class="btn btn-danger-outline w-full justify-center btn-sm">{{ __('common.cancel') }}</button>
                @elseif($sale->status->value === 'submission_closed')
                    <button data-transition="move_to_review" data-sale-id="{{ $sale->id }}"
                        class="btn btn-primary w-full justify-center btn-sm">{{ __('admin.flash_sales.start_review') }}</button>
                    <button data-transition="cancel" data-sale-id="{{ $sale->id }}"
                        class="btn btn-danger-outline w-full justify-center btn-sm">{{ __('common.cancel') }}</button>
                @elseif($sale->status->value === 'under_review')
                    <button data-transition="mark_approved" data-sale-id="{{ $sale->id }}"
                        class="btn btn-primary w-full justify-center btn-sm">{{ __('admin.flash_sales.mark_approved') }}</button>
                    <button data-transition="cancel" data-sale-id="{{ $sale->id }}"
                        class="btn btn-danger-outline w-full justify-center btn-sm">{{ __('common.cancel') }}</button>
                @elseif($sale->status->value === 'approved')
                    <button data-transition="start_sale" data-sale-id="{{ $sale->id }}"
                        class="btn btn-primary w-full justify-center btn-sm">{{ __('admin.flash_sales.start_sale_now') }}</button>
                    <button data-transition="cancel" data-sale-id="{{ $sale->id }}"
                        class="btn btn-danger-outline w-full justify-center btn-sm">{{ __('common.cancel') }}</button>
                @elseif($sale->status->value === 'live')
                    <button data-transition="end_sale" data-sale-id="{{ $sale->id }}"
                        class="btn btn-warning w-full justify-center btn-sm">{{ __('admin.flash_sales.end_sale_early') }}</button>
                    <button data-transition="cancel" data-sale-id="{{ $sale->id }}"
                        class="btn btn-danger-outline w-full justify-center btn-sm">{{ __('common.cancel') }}</button>
                @endif

                @if($sale->status->value === 'draft')
                    <form method="POST" action="{{ route('admin.flash-sales.destroy', $sale->id) }}"
                        onsubmit="return confirm('{{ __('admin.flash_sales.delete_confirm') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger w-full justify-center btn-sm">{{ __('admin.flash_sales.delete') }}</button>
                    </form>
                @endif
            </div>
        </x-card>

        {{-- Event details --}}
        <x-card title="{{ __('admin.flash_sales.event_details') }}">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.country') }}</dt>
                    <dd class="font-medium">{{ $sale->country?->name_en ?? __('admin.flash_sales.all_countries') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.flash_sales.duration') }}</dt>
                    <dd class="font-medium">{{ __('admin.flash_sales.duration_hours', ['count' => $sale->getDurationHours()]) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.flash_sales.slots') }}</dt>
                    <dd class="font-medium">{{ $sale->approved_slots_count }} / {{ $sale->max_total_slots ?? '∞' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.flash_sales.min_discount') }}</dt>
                    <dd class="font-medium">{{ $sale->min_discount_pct }}%</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.flash_sales.commission') }}</dt>
                    <dd class="font-medium">{{ $sale->commission_override_pct ? $sale->commission_override_pct . '%' : __('admin.default') }}</dd>
                </div>
            </dl>
        </x-card>

    </div>{{-- /sidebar --}}

</div>

{{-- Cancel modal --}}
<x-modal id="cancel-modal" title="{{ __('admin.flash_sales.cancel_flash_sale') }}">
    <form id="cancel-form">
        <x-form-textarea name="cancellation_reason" label="{{ __('admin.flash_sales.cancellation_reason') }}" rows="3" required />
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" onclick="closeModal('cancel-modal')" class="btn btn-ghost">{{ __('admin.flash_sales.nevermind') }}</button>
            <button type="submit" id="cancel-submit-btn" class="btn btn-danger">{{ __('admin.flash_sales.cancel_sale') }}</button>
        </div>
    </form>
</x-modal>

{{-- Bulk reject modal --}}
<x-modal id="bulk-reject-modal" title="{{ __('admin.flash_sales.bulk_reject_submissions') }}">
    <form id="bulk-reject-form">
        <input type="hidden" name="bulk_ids">
        <x-form-select name="bulk_rejection_code" label="{{ __('admin.flash_sales.rejection_code') }}" required
            :options="[
                'discount_too_low'        => __('admin.flash_sales.rejection_discount_too_low'),
                'fake_discount_suspected' => __('admin.flash_sales.rejection_fake_discount_suspected'),
                'insufficient_stock'      => __('admin.flash_sales.rejection_insufficient_stock'),
                'not_eligible_category'   => __('admin.flash_sales.rejection_not_eligible_category'),
                'slot_limit_reached'      => __('admin.flash_sales.rejection_slot_limit_reached'),
                'policy_violation'        => __('admin.flash_sales.rejection_policy_violation'),
                'vendor_not_eligible'     => __('admin.flash_sales.rejection_vendor_not_eligible'),
            ]" />
        <x-form-textarea name="bulk_rejection_reason" label="{{ __('admin.flash_sales.rejection_reason_optional') }}" rows="2" />
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" onclick="closeModal('bulk-reject-modal')" class="btn btn-ghost">{{ __('common.cancel') }}</button>
            <button type="submit" id="bulk-reject-submit" class="btn btn-danger">{{ __('admin.flash_sales.reject_all') }}</button>
        </div>
    </form>
</x-modal>

{{-- Review modal --}}
<x-modal id="review-modal" title="{{ __('admin.flash_sales.review_submission') }}" size="lg">
    <div class="space-y-4">
        <div id="review-product-info" class="flex items-center gap-3 pb-3 border-b border-gray-100 hidden">
            <img id="review-product-img" src="" alt="" class="w-14 h-14 object-cover rounded-lg border border-gray-200">
            <div>
                <p id="review-product-name" class="font-medium text-gray-900 text-sm"></p>
                <p class="text-xs text-gray-500 mt-0.5">
                    <span id="review-flash-price" class="font-semibold text-primary-600"></span>
                    <span class="mx-1 text-gray-300">{{ __('admin.flash_sales.vs') }}</span>
                    <span id="review-original-price" class="line-through text-gray-400"></span>
                    <span id="review-discount-pct" class="ml-1.5 font-medium text-emerald-600"></span>
                </p>
            </div>
        </div>

        <div id="review-price-history-wrap" class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('admin.flash_sales.30d_price_history') }}</p>
            <div id="review-price-chart" class="h-16 flex items-end gap-px overflow-hidden">
                <span class="text-xs text-gray-400">{{ __('admin.flash_sales.loading') }}</span>
            </div>
        </div>

        <div id="fraud-warning" class="hidden rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
            <strong>{{ __('admin.flash_sales.pricing_warning') }}</strong>
            <ul id="fraud-reasons" class="mt-1 list-disc list-inside text-xs"></ul>
            <label class="flex items-center gap-2 mt-2 cursor-pointer">
                <input type="checkbox" id="override-fraud-check" class="form-checkbox">
                <span>{{ __('admin.flash_sales.acknowledge_risk') }}</span>
            </label>
        </div>

        <div id="review-stock-info" class="text-xs text-gray-500 flex gap-4 hidden">
            <span>{{ __('admin.flash_sales.max_qty') }}: <strong id="review-max-qty">—</strong></span>
            <span>{{ __('admin.flash_sales.qty_sold') }}: <strong id="review-qty-sold">—</strong></span>
            <span>{{ __('admin.flash_sales.qty_remaining') }}: <strong id="review-qty-remaining">—</strong></span>
        </div>

        <div x-data="{ decision: 'approved' }">
            <label class="form-label">{{ __('admin.flash_sales.decision') }}</label>
            <div class="flex gap-3 mt-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" x-model="decision" value="approved" class="form-radio text-primary-600">
                    <span class="text-sm font-medium text-emerald-700">{{ __('admin.flash_sales.approve') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" x-model="decision" value="rejected" class="form-radio text-danger-600">
                    <span class="text-sm font-medium text-red-700">{{ __('admin.flash_sales.reject') }}</span>
                </label>
            </div>

            <div x-show="decision === 'rejected'" class="mt-3 space-y-3">
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.rejection_code_required') }} <span class="text-danger-500">*</span></label>
                    <select id="review-rejection-code" class="form-select w-full">
                        <option value="manual_rejection">{{ __('admin.flash_sales.rejection_manual_review') }}</option>
                        <option value="price_too_low">{{ __('admin.flash_sales.rejection_price_too_low') }}</option>
                        <option value="insufficient_discount">{{ __('admin.flash_sales.rejection_insufficient_discount') }}</option>
                        <option value="fake_discount_detected">{{ __('admin.flash_sales.rejection_fake_discount') }}</option>
                        <option value="ineligible_category">{{ __('admin.flash_sales.rejection_ineligible_category') }}</option>
                        <option value="ineligible_vendor">{{ __('admin.flash_sales.rejection_ineligible_vendor') }}</option>
                        <option value="duplicate_submission">{{ __('admin.flash_sales.rejection_duplicate_submission') }}</option>
                        <option value="other">{{ __('admin.flash_sales.rejection_other') }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('admin.flash_sales.reason') }}</label>
                    <textarea id="review-rejection-reason" rows="2" class="form-textarea w-full" placeholder="{{ __('admin.flash_sales.reason_for_rejection_placeholder') }}"></textarea>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">{{ __('admin.flash_sales.admin_notes_optional') }}</label>
                <textarea id="review-admin-notes" rows="2" class="form-textarea w-full" placeholder="{{ __('admin.flash_sales.internal_notes_placeholder') }}"></textarea>
            </div>

            <input type="hidden" id="review-submission-id">
            <input type="hidden" id="review-decision" :value="decision">
        </div>
    </div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-review" class="btn btn-primary">{{ __('admin.flash_sales.confirm_decision') }}</button>
    </x-slot:footer>
</x-modal>

{{-- Auto-invite confirmation modal --}}
<x-modal id="auto-invite-modal" title="{{ __('admin.flash_sales.auto_invite_title') }}" size="sm">
    <div class="space-y-3">
        <div id="auto-invite-loading" class="py-6 text-center text-sm text-gray-400">
            {{ __('admin.flash_sales.checking_eligible_vendors') }}
        </div>
        <div id="auto-invite-content" class="hidden space-y-3">
            <p class="text-sm text-gray-700">
                <span id="auto-invite-count" class="font-bold text-gray-900 text-lg">0</span>
                {{ __('admin.flash_sales.auto_invite_match_msg') }}
            </p>
            <div id="auto-invite-zero-msg" class="hidden rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                <strong>{{ __('admin.flash_sales.no_new_vendors') }}</strong>
                <p class="mt-1 text-xs" id="auto-invite-criteria-hint"></p>
            </div>
            <div id="auto-invite-confirm-area">
                <p class="text-xs text-gray-500">{{ __('admin.flash_sales.auto_invite_note') }}</p>
            </div>
        </div>
    </div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-auto-invite" class="btn btn-secondary hidden">
            {{ __('admin.flash_sales.send_invitations') }}
        </button>
    </x-slot:footer>
</x-modal>

{{-- Manual invite modal --}}
<x-modal id="manual-invite-modal" title="{{ __('admin.flash_sales.invite_vendor_manually') }}" size="sm">
    <div class="space-y-3">
        <p class="text-sm text-gray-500">{{ __('admin.flash_sales.enter_vendor_ids') }}</p>
        <div>
            <label class="form-label">{{ __('admin.flash_sales.vendor_ids') }}</label>
            <textarea id="manual-invite-ids" rows="5" class="form-textarea w-full font-mono text-sm" placeholder="{{ __('admin.flash_sales.vendor_ids_placeholder') }}"></textarea>
        </div>
    </div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-manual-invite" class="btn btn-primary">{{ __('admin.flash_sales.send_invitations') }}</button>
    </x-slot:footer>
</x-modal>

{{-- Decline reason modal --}}
<x-modal id="decline-reason-modal" title="{{ __('admin.flash_sales.decline_reason') }}" size="sm">
    <div class="space-y-2">
        <p class="text-sm text-gray-600" id="decline-reason-vendor"></p>
        <blockquote class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800 italic" id="decline-reason-text"></blockquote>
    </div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-ghost">{{ __('common.close') }}</button>
    </x-slot:footer>
</x-modal>

<script>
window.FLASH_SALE_ID         = '{{ $sale->id }}';
window.FLASH_SALE_UPDATE_URL = '{{ route('admin.flash-sales.update', $sale->id) }}';
window.FLASH_SALE_STATUS     = '{{ $sale->status->value }}';
window.MIN_DISCOUNT_PCT      = {{ (float) $sale->min_discount_pct }};
window.URLS = {
    update:            '{{ route('admin.flash-sales.update', $sale->id) }}',
    submissionsDt:     '{{ route('admin.flash-sales.submissions.datatable', $sale->id) }}',
    invitationsDt:     '{{ route('admin.flash-sales.invitations.datatable', $sale->id) }}',
    transition:        '{{ route('admin.flash-sales.transition', $sale->id) }}',
    inviteVendors:     '{{ route('admin.flash-sales.invite-vendors', $sale->id) }}',
    eligibleCount:     '{{ route('admin.flash-sales.eligible-vendor-count', $sale->id) }}',
    bulkReview:        '{{ route('admin.flash-sales.submissions.bulk-review', $sale->id) }}',
    liveData:          '{{ route('admin.flash-sales.live-data', $sale->id) }}',
    priceHistory:      '{{ route('admin.flash-sales.price-history') }}',
    resendInvitation:  '{{ url('/flash-sales/' . $sale->id . '/invitations') }}',
    submissionDetail:  '{{ url('/flash-sales/submissions') }}',
    analyticsData:     '{{ route('admin.flash-sales.analytics-data', $sale->id) }}',
};
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
    statusSubmitted: @json(__('admin.flash_sales.submitted')),
    statusUnderReview: @json(__('admin.flash_sales.status_under_review')),
    statusApproved: @json(__('admin.flash_sales.status_approved')),
    statusLive: @json(__('admin.flash_sales.status_live')),
    statusSoldOut: @json(__('admin.flash_sales.sold_out')),
    statusRejected: @json(__('admin.flash_sales.status_rejected')),
    statusWithdrawn: @json(__('admin.flash_sales.status_withdrawn')),
    statusEnded: @json(__('admin.flash_sales.status_ended')),
    product: @json(__('admin.flash_sales.product')),
    flashPrice: @json(__('admin.flash_sales.flash_price')),
    originalShort: @json(__('admin.flash_sales.original_short')),
    discShort: @json(__('admin.flash_sales.disc_short')),
    minDiscountShort: @json(__('admin.flash_sales.min_discount_short')),
    qty: @json(__('admin.flash_sales.qty')),
    status: @json(__('admin.status')),
    submitted: @json(__('admin.flash_sales.submitted')),
    reviewBtn: @json(__('admin.flash_sales.review_btn')),
    loading: @json(__('admin.flash_sales.loading')),
    possibleFakeDiscount: @json(__('admin.flash_sales.possible_fake_discount')),
    bulkRejectCount: @json(__('admin.flash_sales.bulk_reject_count')),
    vendor: @json(__('admin.flash_sales.vendor')),
    typeLabel: @json(__('admin.flash_sales.type_label')),
    manualInviteType: @json(__('admin.flash_sales.manual_invite_type')),
    autoInviteType: @json(__('admin.flash_sales.auto_invite_type')),
    slots: @json(__('admin.flash_sales.slots')),
    invitedLabel: @json(__('admin.flash_sales.invited_label')),
    notifiedLabel: @json(__('admin.flash_sales.notified_label')),
    respondedLabel: @json(__('admin.flash_sales.responded_label')),
    viewReason: @json(__('admin.flash_sales.view_reason')),
    resend: @json(__('admin.flash_sales.resend')),
    resendNotificationTooltip: @json(__('admin.flash_sales.resend_notification_tooltip')),
    notificationQueued: @json(__('admin.flash_sales.notification_queued')),
    resendFailed: @json(__('admin.flash_sales.resend_failed')),
    statusPending: @json(__('admin.flash_sales.pending')),
    statusAccepted: @json(__('admin.flash_sales.accepted')),
    statusDeclined: @json(__('admin.flash_sales.declined')),
    failedLoadPricing: @json(__('admin.flash_sales.failed_load_pricing')),
    noPriceHistoryData: @json(__('admin.flash_sales.no_price_history_data')),
    acknowledgeWarningRequired: @json(__('admin.flash_sales.acknowledge_warning_required')),
    confirmDecision: @json(__('admin.flash_sales.confirm_decision')),
    reviewFailed: @json(__('admin.flash_sales.review_failed')),
    decisionSaved: @json(__('admin.flash_sales.decision_saved')),
    bulkRejectResult: @json(__('admin.flash_sales.bulk_reject_result')),
    bulkRejectFailed: @json(__('admin.flash_sales.bulk_reject_failed')),
    confirmGenericAction: @json(__('admin.flash_sales.confirm_generic_action')),
    transitionFailed: @json(__('admin.flash_sales.transition_failed')),
    failedLoadEligibleCount: @json(__('admin.flash_sales.failed_load_eligible_count')),
    inviting: @json(__('admin.flash_sales.inviting')),
    sendInvitations: @json(__('admin.flash_sales.send_invitations')),
    vendorsInvitedResult: @json(__('admin.flash_sales.vendors_invited_result')),
    inviteFailed: @json(__('admin.flash_sales.invite_failed')),
    activeCriteriaHint: @json(__('admin.flash_sales.active_criteria_hint')),
    noCriteriaHint: @json(__('admin.flash_sales.no_criteria_hint')),
    minDiscountCriteria: @json(__('admin.flash_sales.min_discount_criteria')),
    vendorsInvitedGeneric: @json(__('admin.flash_sales.vendors_invited_generic')),
    inviteVendorsFailed: @json(__('admin.flash_sales.invite_vendors_failed')),
    noDataYet: @json(__('admin.flash_sales.no_data_yet')),
    ended: @json(__('admin.flash_sales.ended')),
    analyticsUnavailable: @json(__('admin.flash_sales.analytics_unavailable')),
    noDailyData: @json(__('admin.flash_sales.no_daily_data')),
});
</script>

@endsection
