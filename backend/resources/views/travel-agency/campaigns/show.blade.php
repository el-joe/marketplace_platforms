@extends('layouts.travel-agency')

@section('title', $offer->name)

@push('scripts')
    @vite('resources/js/travel_agency/campaigns.js')
    <script>
        window.CAMPAIGN_OFFER_CONFIG = {
            mode:               'show',
            offerId:            "{{ $offer->id }}",
            submitUrl:          "{{ route('travel-agency.campaigns.submit', $offer->id) }}",
            pauseUrl:           "{{ route('travel-agency.campaigns.pause', $offer->id) }}",
            resumeUrl:          "{{ route('travel-agency.campaigns.resume', $offer->id) }}",
            deleteUrl:          "{{ route('travel-agency.campaigns.destroy', $offer->id) }}",
            inviteUrl:          "{{ route('travel-agency.campaigns.invite', $offer->id) }}",
            revokeBaseUrl:      "{{ url('travel-agency/campaigns/invitations') }}",
            marketerSearchUrl:  "{{ route('travel-agency.campaigns.marketers.search') }}",
            offerStatus:        "{{ $offer->status->value }}",
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
            confirmDeleteOffer: @json(__('travel.campaigns.confirm_delete_offer')),
            confirmSubmitReview: @json(__('travel.campaigns.confirm_submit_review')),
            confirmPauseOffer: @json(__('travel.campaigns.confirm_pause_offer')),
            confirmResumeOffer: @json(__('travel.campaigns.confirm_resume_offer')),
            packagesWord: @json(__('travel.campaigns.packages_word')),
        });
    </script>
@endpush

@section('content')
@php
    $statusMap = [
        'draft'         => ['label' => __('travel.campaigns.status.draft'),         'cls' => 'bg-gray-100 text-gray-600'],
        'pending_admin' => ['label' => __('travel.campaigns.status.pending_admin'), 'cls' => 'bg-amber-100 text-amber-700'],
        'active'        => ['label' => __('travel.campaigns.status.active'),        'cls' => 'bg-emerald-100 text-emerald-700'],
        'paused'        => ['label' => __('travel.campaigns.status.paused'),        'cls' => 'bg-blue-100 text-blue-700'],
        'rejected'      => ['label' => __('travel.campaigns.status.rejected'),      'cls' => 'bg-red-100 text-red-700'],
        'ended'         => ['label' => __('travel.campaigns.status.ended'),         'cls' => 'bg-gray-100 text-gray-400'],
    ];
    $st = $statusMap[$offer->status->value] ?? ['label' => $offer->status->value, 'cls' => 'bg-gray-100 text-gray-600'];

    $typeMap = [
        'product_promotion' => __('travel.campaigns.types.product_promotion'),
        'store_promotion'   => __('travel.campaigns.types.store_promotion'),
        'brand_deal'        => __('travel.campaigns.types.brand_deal'),
        'product_specific'  => __('travel.campaigns.types.product_specific'),
        'flash_sale'        => __('travel.campaigns.types.flash_sale'),
    ];
    $commissionTypeMap = [
        'percentage'     => __('travel.campaigns.commission_types.percentage'),
        'flat_per_order' => __('travel.campaigns.commission_types.flat_per_order'),
        'flat_per_click' => __('travel.campaigns.commission_types.flat_per_click'),
    ];
    $invStatusMap = [
        'pending'  => ['label' => __('travel.campaigns.invited_stat'), 'cls' => 'bg-amber-100 text-amber-700'],
        'accepted' => ['label' => __('travel.campaigns.accepted_stat'), 'cls' => 'bg-emerald-100 text-emerald-700'],
        'declined' => ['label' => __('travel.campaigns.declined_stat'), 'cls' => 'bg-red-100 text-red-700'],
        'expired'  => ['label' => __('travel.campaigns.declined_stat'), 'cls' => 'bg-gray-100 text-gray-500'],
        'revoked'  => ['label' => __('travel.campaigns.declined_stat'), 'cls' => 'bg-gray-100 text-gray-500'],
    ];
@endphp

<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('travel-agency.campaigns.index') }}" class="hover:text-primary-600">{{ __('travel.campaigns.title') }}</a>
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
        <span class="text-gray-800 font-medium truncate max-w-xs">{{ $offer->name }}</span>
    </nav>

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ $offer->name }}</h1>
            <span id="status-badge" class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $st['cls'] }}">
                {{ $st['label'] }}
            </span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if ($offer->status === \App\Enums\VendorCampaignOfferStatus::Draft)
                <button id="btn-delete-offer"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors">
                    {{ __('travel.campaigns.delete_offer') }}
                </button>
                <button id="btn-submit-review"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                    {{ __('travel.campaigns.submit_review') }}
                </button>
            @endif
            @if ($offer->status === \App\Enums\VendorCampaignOfferStatus::Active)
                <button id="btn-pause"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                    {{ __('travel.campaigns.pause') }}
                </button>
            @endif
            @if ($offer->status === \App\Enums\VendorCampaignOfferStatus::Paused)
                <button id="btn-resume"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                    {{ __('travel.campaigns.resume') }}
                </button>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Toast for AJAX actions --}}
    <div id="action-toast" class="hidden fixed top-5 end-5 z-50 max-w-sm rounded-xl border px-4 py-3 text-sm shadow-lg transition-all"></div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- ═══ LEFT: Offer details ═══ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Details card --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                <div class="px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('travel.campaigns.details_title') }}</h2>
                </div>
                <div class="px-6 py-5 grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                    <div>
                        <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">{{ __('travel.campaigns.campaign_type') }}</p>
                        <p class="font-medium text-gray-900">{{ $typeMap[$offer->campaign_type->value] ?? $offer->campaign_type->value }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">{{ __('travel.campaigns.offered_commission') }}</p>
                        <p class="font-medium text-gray-900">
                            {{ $offer->offered_commission_rate }}%
                            <span class="text-xs text-gray-500 font-normal">{{ $commissionTypeMap[$offer->commission_type->value] ?? '' }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">{{ __('travel.campaigns.campaign_period') }}</p>
                        <p class="font-medium text-gray-900">
                            {{ $offer->starts_at->format('d M Y') }} — {{ $offer->ends_at->format('d M Y') }}
                        </p>
                    </div>
                    @if ($offer->invitation_deadline)
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">{{ __('travel.campaigns.invite_deadline_label') }}</p>
                            <p class="font-medium text-gray-900">{{ $offer->invitation_deadline->format('d M Y') }}</p>
                        </div>
                    @endif
                    @if ($offer->budget_per_marketer)
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">{{ __('travel.campaigns.budget_per_marketer_label') }}</p>
                            <p class="font-medium text-gray-900">{{ number_format($offer->budget_per_marketer, 2) }} {{ $offer->currency ?? '' }}</p>
                        </div>
                    @endif
                    @if ($offer->total_budget)
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">{{ __('travel.campaigns.total_budget_label') }}</p>
                            <p class="font-medium text-gray-900">{{ number_format($offer->total_budget, 2) }} {{ $offer->currency ?? '' }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">{{ __('travel.campaigns.attribution_model_label') }}</p>
                        <p class="font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $offer->attribution_model?->value ?? '')) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">{{ __('travel.campaigns.whatsapp_sharing') }}</p>
                        <p class="font-medium text-gray-900">{{ $offer->whatsapp_sharing_enabled ? __('travel.campaigns.enabled') : __('travel.campaigns.disabled') }}</p>
                    </div>
                </div>
                @if ($offer->description)
                    <div class="px-6 py-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ __('travel.campaigns.description') }}</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $offer->description }}</p>
                    </div>
                @endif
                @if ($offer->requirements)
                    <div class="px-6 py-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ __('travel.campaigns.requirements') }}</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $offer->requirements }}</p>
                    </div>
                @endif
            </div>

            {{-- Status timeline --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm px-6 py-5">
                <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('travel.campaigns.status_path_title') }}</h2>
                <div class="flex items-center gap-0">
                    @foreach ([
                        ['status' => 'draft',         'label' => __('travel.campaigns.status_path.draft')],
                        ['status' => 'pending_admin', 'label' => __('travel.campaigns.status_path.pending_admin')],
                        ['status' => 'active',        'label' => __('travel.campaigns.status_path.active')],
                    ] as $i => $step)
                        @php
                            $statuses = ['draft', 'pending_admin', 'active', 'paused', 'ended'];
                            $currentIndex = array_search($offer->status?->value, $statuses);
                            $stepIndex = array_search($step['status'], $statuses);
                            $isDone = $currentIndex !== false && $stepIndex <= $currentIndex;
                        @endphp
                        <div class="flex items-center {{ !$loop->last ? 'flex-1' : '' }}">
                            <div class="flex-shrink-0 flex flex-col items-center">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold
                                            {{ $isDone ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-400' }}">
                                    @if ($isDone)
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                        </svg>
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </div>
                                <span class="mt-1 text-xs {{ $isDone ? 'text-primary-600 font-medium' : 'text-gray-400' }}">{{ $step['label'] }}</span>
                            </div>
                            @if (!$loop->last)
                                <div class="flex-1 mx-2 h-0.5 {{ $isDone ? 'bg-primary-500' : 'bg-gray-200' }}"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($offer->status === \App\Enums\VendorCampaignOfferStatus::Cancelled && $offer->rejection_reason)
                    <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        <strong>{{ __('travel.campaigns.rejection_reason') }}</strong> {{ $offer->rejection_reason }}
                    </div>
                @endif
            </div>

            {{-- Packages grid --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                <div class="px-6 py-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('travel.campaigns.campaign_packages') }}</h2>
                    <span class="text-xs text-gray-500">{{ __('travel.campaigns.packages_count', ['count' => $offer->packages->count()]) }}</span>
                </div>
                <div class="px-6 py-4">
                    @if ($offer->packages->isEmpty())
                        <p class="text-sm text-gray-400">{{ __('travel.campaigns.no_packages') }}</p>
                    @else
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($offer->packages->sortBy('position') as $item)
                                @php
                                    $package = $item->package;
                                @endphp
                                <div class="flex items-center gap-3 rounded-lg border border-gray-100 p-3">
                                    <div class="h-10 w-10 flex-shrink-0 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
                                        {{ $item->position }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $package?->title_ar ?? $package?->title_en ?? '—' }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ trim(($package?->destination_city ? $package->destination_city . '، ' : '') . ($package?->destination_country ?? '')) }}</p>
                                        @if ($item->commission_override !== null)
                                            <p class="text-xs text-indigo-600 mt-0.5">{{ __('travel.campaigns.custom_commission', ['rate' => $item->commission_override]) }}</p>
                                        @else
                                            <p class="text-xs text-gray-400 mt-0.5">{{ __('travel.campaigns.campaign_commission', ['rate' => $offer->offered_commission_rate]) }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ═══ RIGHT: Invite Marketers ═══ --}}
        <div class="space-y-5">

            {{-- Invitation stats --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm px-6 py-4">
                <p class="text-sm text-gray-600 font-medium mb-3">{{ __('travel.campaigns.invitation_stats_title') }}</p>
                <div class="flex items-center gap-4 text-sm">
                    <span class="font-bold text-gray-900">{{ $invitationStats['invited'] }}</span><span class="text-gray-500">{{ __('travel.campaigns.invited_stat') }}</span>
                    <span class="text-gray-300">·</span>
                    <span class="font-bold text-emerald-600">{{ $invitationStats['accepted'] }}</span><span class="text-gray-500">{{ __('travel.campaigns.accepted_stat') }}</span>
                    <span class="text-gray-300">·</span>
                    <span class="font-bold text-red-500">{{ $invitationStats['declined'] }}</span><span class="text-gray-500">{{ __('travel.campaigns.declined_stat') }}</span>
                </div>
            </div>

            {{-- Search marketer panel --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                <div class="px-5 py-4">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('travel.campaigns.invite_marketers_title') }}</h2>
                </div>
                <div class="px-5 py-4 space-y-3">

                    {{-- Filters --}}
                    <div class="flex gap-2">
                        <select id="marketer-type-filter"
                                class="flex-1 rounded-lg border border-gray-300 px-2 py-2 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <option value="">{{ __('travel.campaigns.all_types') }}</option>
                            <option value="influencer">{{ __('travel.campaigns.marketer_types.influencer') }}</option>
                            <option value="celebrity">{{ __('travel.campaigns.marketer_types.celebrity') }}</option>
                            <option value="affiliate">{{ __('travel.campaigns.marketer_types.affiliate') }}</option>
                            <option value="brand_ambassador">{{ __('travel.campaigns.marketer_types.brand_ambassador') }}</option>
                        </select>
                        <input type="text" id="marketer-niche-filter" placeholder="{{ __('travel.campaigns.niche_placeholder') }}"
                               class="flex-1 rounded-lg border border-gray-300 px-2 py-2 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    </div>

                    {{-- Search input --}}
                    <div class="relative">
                        <input type="text" id="marketer-search"
                               placeholder="{{ __('travel.campaigns.search_marketer_placeholder') }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2.5 ps-9 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <svg class="absolute start-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </div>

                    {{-- Search results --}}
                    <div id="marketer-results" class="hidden space-y-2 max-h-72 overflow-y-auto rounded-lg border border-gray-100 p-1">
                        {{-- Populated by JS --}}
                    </div>

                    {{-- Pending invite queue --}}
                    <div id="invite-queue" class="hidden">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-medium text-gray-600">{{ __('travel.campaigns.invite_list_label') }} (<span id="invite-queue-count">0</span>)</p>
                            <button id="btn-clear-queue" type="button" class="text-xs text-red-500 hover:underline">{{ __('travel.campaigns.clear_all') }}</button>
                        </div>
                        <div id="invite-queue-list" class="space-y-1 max-h-40 overflow-y-auto"></div>
                        <div class="mt-3">
                            <textarea id="vendor-note" rows="2" placeholder="{{ __('travel.campaigns.note_placeholder') }}"
                                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 resize-none"></textarea>
                        </div>
                        <button id="btn-send-invites" type="button"
                                class="mt-2 w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                            {{ __('travel.campaigns.send_invites') }}
                        </button>
                    </div>

                </div>
            </div>

            {{-- Invited marketers list --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                <div class="px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('travel.campaigns.invited_marketers') }}</h2>
                </div>
                <div class="divide-y divide-gray-50" id="invitations-list">
                    @forelse ($offer->invitations as $inv)
                        @php
                            $is = $invStatusMap[$inv->status->value] ?? ['label' => $inv->status->value, 'cls' => 'bg-gray-100 text-gray-500'];
                        @endphp
                        <div class="px-5 py-3 flex items-start gap-3" data-invitation-id="{{ $inv->id }}" data-status="{{ $inv->status->value }}">
                            {{-- Avatar --}}
                            <div class="h-9 w-9 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600">
                                {{ mb_substr($inv->marketer->name ?? '?', 0, 1) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-medium text-gray-900">{{ $inv->marketer->name ?? '—' }}</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $is['cls'] }}">
                                        {{ $is['label'] }}
                                    </span>
                                </div>
                                @if ($inv->marketer_note)
                                    <p class="text-xs text-gray-500 mt-0.5 italic">{{ $inv->marketer_note }}</p>
                                @endif
                                <div class="mt-1 flex items-center gap-3 flex-wrap">
                                    @if ($inv->status === \App\Enums\VendorCampaignInvitationStatus::Accepted && $inv->resulting_campaign_id)
                                        <a href="{{ route('travel-agency.campaigns.show', $offer->id) }}"
                                           class="text-xs text-primary-600 hover:underline">{{ __('travel.campaigns.view') }} ←</a>
                                    @endif
                                    @if ($inv->status === \App\Enums\VendorCampaignInvitationStatus::Pending)
                                        <button type="button"
                                                class="btn-revoke text-xs text-red-500 hover:underline"
                                                data-invitation-id="{{ $inv->id }}">
                                            {{ __('travel.campaigns.confirm_withdraw_invitation') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center text-sm text-gray-400">
                            {{ __('travel.campaigns.no_marketer_invited') }}
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
