@extends('layouts.partner')

@section('title', __('partner.profile.page_title'))
@section('page-title', __('partner.profile.title'))

@push('scripts')
    @vite('resources/js/partner/profile.js')
    <script>
        window.PROFILE = {
            csrf:               '{{ csrf_token() }}',
            updateStoreUrl:     '{{ route('partner.profile.update-store') }}',
            updatePasswordUrl:  '{{ route('partner.profile.update-password') }}',
            uploadDocumentUrl:  '{{ route('partner.documents.upload') }}',
            changeRequestUrl:   '{{ route('partner.change-requests.store') }}',
            isOwner:            {{ $admin->isOwner() ? 'true' : 'false' }},
        };
    </script>
@endpush

@section('content')
@php
$businessTypeLabels = [
    'individual' => __('partner.profile.business_type_individual'),
    'sole_prop'  => __('partner.profile.business_type_sole_prop'),
    'llc'        => __('partner.profile.business_type_llc'),
    'corp'       => __('partner.profile.business_type_corp'),
];
@endphp
<div class="px-4 py-6 sm:px-6 lg:px-8"
     x-data="{ tab: '{{ request('tab', 'store') }}' }">

    {{-- Page header --}}
    <div class="mb-6 flex items-center gap-4">
        {{-- Avatar --}}
        <div class="shrink-0 h-14 w-14 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold text-xl select-none">
            {{ mb_substr($vendor->store_name, 0, 1) }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $vendor->store_name }}</h1>
            <p class="text-sm text-gray-500">
                @if ($vendor->country)
                    {{ $vendor->country->flag_emoji ?? '' }} {{ session('locale', 'ar') === 'ar' ? ($vendor->country->name_ar ?? $vendor->country->name) : ($vendor->country->name_en ?? $vendor->country->name) }}
                    &nbsp;·&nbsp;
                @endif
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                    {{ $vendor->global_status === \App\Enums\VendorGlobalStatus::Active ? 'bg-green-100 text-green-700' :
                       ($vendor->global_status === \App\Enums\VendorGlobalStatus::Suspended ? 'bg-red-100 text-red-700' :
                       'bg-yellow-100 text-yellow-700') }}">
                    {{ $vendor->global_status === \App\Enums\VendorGlobalStatus::Active ? __('partner.nav.status_active') :
                       ($vendor->global_status === \App\Enums\VendorGlobalStatus::Suspended ? __('partner.nav.status_suspended') :
                       ($vendor->global_status === \App\Enums\VendorGlobalStatus::Rejected ? __('partner.nav.status_rejected') : __('partner.nav.status_under_review'))) }}
                </span>
            </p>
        </div>
    </div>

    <div class="mb-4">
        <a href="{{ route('partner.change-requests.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-800">
            {{ __('partner.profile.view_change_requests') }}
        </a>
    </div>

    {{-- Tabs nav --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-x-6 overflow-x-auto">
            @foreach ([
                'store'    => __('partner.profile.tab_store'),
                'documents'=> __('partner.profile.tab_documents'),
                'password' => __('partner.profile.tab_password'),
            ] as $key => $label)
                <button type="button"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'border-primary-600 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: Store info                                                        --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @php
        $storeProfileLocked = $lockStatus[\App\Models\VendorSectionLock::SECTION_STORE_PROFILE] ?? false;
        $contactInfoLocked  = $lockStatus[\App\Models\VendorSectionLock::SECTION_CONTACT_INFO] ?? false;
        $anySectionLocked   = $storeProfileLocked || $contactInfoLocked;
    @endphp
    <div x-show="tab === 'store'" x-cloak x-data="{ showChangeRequest: {{ $anySectionLocked ? 'true' : 'false' }} }">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Editable store fields --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-5">{{ __('partner.profile.store_data') }}</h2>

                @if (! $admin->isOwner())
                    <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700">
                        {{ __('partner.profile.owner_only_edit_notice') }}
                    </div>
                @endif

                @if ($anySectionLocked)
                    <div class="mb-4 rounded-lg bg-yellow-50 border border-yellow-300 px-4 py-3 text-sm text-yellow-800 flex items-start gap-2">
                        <span>🔒</span>
                        <div>
                            @if ($storeProfileLocked)
                                <p>{{ __('partner.profile.section_locked', ['reason' => $sectionLocks[\App\Models\VendorSectionLock::SECTION_STORE_PROFILE]->locked_reason ?? __('partner.profile.section_locked_generic')]) }}</p>
                            @endif
                            @if ($contactInfoLocked)
                                <p>{{ __('partner.profile.section_locked', ['reason' => $sectionLocks[\App\Models\VendorSectionLock::SECTION_CONTACT_INFO]->locked_reason ?? __('partner.profile.section_locked_generic')]) }}</p>
                            @endif
                            <button type="button" @click="showChangeRequest = !showChangeRequest"
                                class="mt-2 inline-flex items-center gap-1 rounded-lg bg-yellow-100 px-3 py-1.5 text-xs font-semibold text-yellow-800 hover:bg-yellow-200">
                                {{ __('partner.profile.request_change') }}
                            </button>
                        </div>
                    </div>
                @endif

                <form id="form-store" novalidate>
                    <div class="space-y-4">
                        {{-- Store name --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.store_name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="store_name"
                                value="{{ old('store_name', $vendor->store_name) }}"
                                {{ (! $admin->isOwner() || $storeProfileLocked) ? 'disabled' : '' }}
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>

                        {{-- Store description --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.store_description') }}</label>
                            <textarea name="store_description" rows="3"
                                {{ (! $admin->isOwner() || $storeProfileLocked) ? 'disabled' : '' }}
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-400">{{ old('store_description', $vendor->store_description) }}</textarea>
                        </div>

                        {{-- Contact email --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.contact_email') }} <span class="text-red-500">*</span></label>
                                <input type="email" name="contact_email"
                                    value="{{ old('contact_email', $vendor->contact_email) }}"
                                    {{ (! $admin->isOwner() || $contactInfoLocked) ? 'disabled' : '' }}
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.contact_phone') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="contact_phone"
                                    value="{{ old('contact_phone', $vendor->contact_phone) }}"
                                    {{ (! $admin->isOwner() || $contactInfoLocked) ? 'disabled' : '' }}
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-400">
                            </div>
                        </div>

                        {{-- WhatsApp --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.whatsapp_number') }}</label>
                            <input type="text" name="whatsapp_number"
                                value="{{ old('whatsapp_number', $vendor->whatsapp_number) }}"
                                {{ (! $admin->isOwner() || $contactInfoLocked) ? 'disabled' : '' }}
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                    </div>

                    @if ($admin->isOwner())
                        <div class="mt-6 flex justify-end">
                            <button type="submit" id="btn-save-store"
                                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('partner.profile.save_changes') }}
                            </button>
                        </div>
                    @endif
                </form>

                {{-- Change request form for locked fields --}}
                @if ($anySectionLocked && $admin->isOwner())
                    <div x-show="showChangeRequest" x-cloak class="mt-6 border-t border-gray-100 pt-6">
                        @if ($pendingChangeRequests->has(\App\Models\VendorSectionLock::SECTION_STORE_PROFILE) || $pendingChangeRequests->has(\App\Models\VendorSectionLock::SECTION_CONTACT_INFO))
                            <div class="mb-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-600">
                                {{ __('partner.profile.change_request_pending_notice') }}
                            </div>
                        @else
                            <form id="form-change-request-store" novalidate>
                                <input type="hidden" name="fields" value="{{ implode(',', array_merge(
                                    $storeProfileLocked ? ['store_name', 'store_description'] : [],
                                    $contactInfoLocked ? ['contact_email', 'contact_phone', 'whatsapp_number'] : []
                                )) }}">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @if ($storeProfileLocked)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.store_name') }}</label>
                                            <input type="text" name="store_name" value="{{ $vendor->store_name }}"
                                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.store_description') }}</label>
                                            <input type="text" name="store_description" value="{{ $vendor->store_description }}"
                                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                        </div>
                                    @endif
                                    @if ($contactInfoLocked)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.contact_email') }}</label>
                                            <input type="email" name="contact_email" value="{{ $vendor->contact_email }}"
                                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.contact_phone') }}</label>
                                            <input type="text" name="contact_phone" value="{{ $vendor->contact_phone }}"
                                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.whatsapp_number') }}</label>
                                            <input type="text" name="whatsapp_number" value="{{ $vendor->whatsapp_number }}"
                                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                        </div>
                                    @endif
                                </div>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.change_request_reason') }}</label>
                                    <textarea name="note" rows="2" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"></textarea>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <button type="submit" id="btn-submit-change-request-store"
                                        class="inline-flex items-center gap-2 rounded-lg bg-yellow-600 px-5 py-2 text-sm font-semibold text-white hover:bg-yellow-700">
                                        {{ __('partner.profile.submit_change_request') }}
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Read-only business info --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 h-fit">
                <h2 class="text-base font-semibold text-gray-800 mb-5">{{ __('partner.profile.business_info') }}</h2>
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500 mb-0.5">{{ __('partner.profile.country') }}</dt>
                        <dd class="font-medium text-gray-900">
                            @if ($vendor->country)
                                {{ $vendor->country->flag_emoji ?? '' }} {{ session('locale', 'ar') === 'ar' ? ($vendor->country->name_ar ?? $vendor->country->name) : ($vendor->country->name_en ?? $vendor->country->name) }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 mb-0.5">{{ __('partner.profile.business_type') }}</dt>
                        <dd class="font-medium text-gray-900">
                            {{ $businessTypeLabels[$vendor->business_type?->value] ?? ($vendor->business_type?->value ?? '—') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 mb-0.5">{{ __('partner.profile.registration_number') }}</dt>
                        <dd class="font-medium text-gray-900 font-mono text-xs">
                            {{ $vendor->business_registration_number ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 mb-0.5">{{ __('partner.profile.primary_email') }}</dt>
                        <dd class="font-medium text-gray-900">{{ $vendor->email }}</dd>
                    </div>
                    <div class="pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400">{{ __('partner.profile.contact_support_to_edit') }}</p>
                    </div>
                </dl>

                {{-- Bank accounts summary --}}
                @if ($bankAccounts->isNotEmpty())
                    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-3">{{ __('partner.profile.bank_accounts') }}</h3>
                    <ul class="space-y-2">
                        @foreach ($bankAccounts as $acct)
                            <li class="flex items-center justify-between text-xs rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                <span class="font-medium text-gray-800">{{ $acct->bank_name }}</span>
                                <span class="text-gray-500 font-mono">•••• {{ substr($acct->account_number ?? $acct->iban ?? '', -4) }}</span>
                                @if ($acct->is_primary)
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">{{ __('partner.profile.primary') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: Documents                                                         --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'documents'" x-cloak>

        @php $documentsLocked = $lockStatus[\App\Models\VendorSectionLock::SECTION_DOCUMENTS] ?? false; @endphp
        @if ($documentsLocked)
            <div class="mb-4 rounded-lg bg-yellow-50 border border-yellow-300 px-4 py-3 text-sm text-yellow-800 flex items-start gap-2">
                <span>🔒</span>
                <p>{{ __('partner.profile.section_locked', ['reason' => $sectionLocks[\App\Models\VendorSectionLock::SECTION_DOCUMENTS]->locked_reason ?? __('partner.profile.section_locked_generic')]) }}</p>
            </div>
        @endif

        @if ($requiredDocs->isEmpty())
            <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-6 text-center text-sm text-gray-500">
                {{ __('partner.profile.documents_country_missing') }}
            </div>
        @else
            @php
                $mandatoryNeedingAttention = $requiredDocs->filter(function ($entry) use ($latestDocByType) {
                    if ($entry['requirement_level'] !== 'mandatory') return false;
                    $doc = $latestDocByType->get($entry['type']->id);
                    if (! $doc) return true; // never uploaded
                    return in_array($doc->isExpired() ? 'expired' : $doc->status?->value, ['rejected', 'expired']);
                });
            @endphp

            {{-- Attention banner --}}
            @if ($mandatoryNeedingAttention->isNotEmpty())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-300 px-4 py-3 text-sm text-red-700 flex items-start gap-3">
                    <svg class="mt-0.5 w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <div>
                        <p class="font-semibold">
                            {{ $mandatoryNeedingAttention->count() }}
                            {{ $mandatoryNeedingAttention->count() === 1 ? __('partner.profile.mandatory_document_singular') : __('partner.profile.mandatory_document_plural') }}
                            {{ __('partner.profile.your_attention') }}
                        </p>
                        <p class="mt-0.5 text-xs text-red-600">
                            {{ $mandatoryNeedingAttention->pluck('type')->map(fn($t) => session('locale', 'ar') === 'ar' ? ($t->name_ar ?? $t->name_en) : ($t->name_en ?? $t->name_ar))->join(session('locale', 'ar') === 'ar' ? '، ' : ', ') }}
                        </p>
                    </div>
                </div>
            @endif

            <div class="mb-4 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-700">
                {{ __('partner.profile.document_upload_hint') }}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($requiredDocs as $entry)
                    @php
                        $docType        = $entry['type'];
                        $reqLevel       = $entry['requirement_level'];
                        $doc            = $latestDocByType->get($docType->id);
                        $effectiveStatus = $doc ? ($doc->isExpired() ? 'expired' : $doc->status?->value) : null;
                        $needsAttention = $reqLevel === 'mandatory'
                            && (! $doc || in_array($effectiveStatus, ['rejected', 'expired']));
                    @endphp
                    <div class="bg-white rounded-2xl border {{ $needsAttention ? 'border-red-300 border-dashed' : 'border-gray-200' }} shadow-sm p-5 flex flex-col gap-3"
                         id="doc-card-{{ $docType->id }}">

                        {{-- Header --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-5 h-5 shrink-0 {{ $doc ? 'text-primary-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-800 truncate">
                                        {{ session('locale', 'ar') === 'ar' ? ($docType->name_ar ?? $docType->name_en) : ($docType->name_en ?? $docType->name_ar) }}
                                    </span>
                                    @if ($reqLevel === 'optional')
                                        <span class="text-xs text-gray-400">{{ __('partner.profile.optional_tag') }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Status badge --}}
                            @if ($doc)
                                @php
                                    $badgeCfg = match($effectiveStatus) {
                                        'approved', 'verified' => ['bg-green-100 text-green-700', __('partner.profile.verified')],
                                        'pending'              => ['bg-yellow-100 text-yellow-700', __('partner.documents.document_pending')],
                                        'rejected'             => ['bg-red-100 text-red-700', __('partner.documents.document_rejected')],
                                        'expired'              => ['bg-red-100 text-red-600', __('partner.documents.document_expired')],
                                        default                => ['bg-gray-100 text-gray-600', $effectiveStatus],
                                    };
                                @endphp
                                <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeCfg[0] }}">
                                    {{ $badgeCfg[1] }}
                                </span>
                            @else
                                <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-500">
                                    {{ __('partner.profile.not_uploaded') }}
                                </span>
                            @endif
                        </div>

                        {{-- Dates & rejection reason --}}
                        @if ($doc)
                            <div class="text-xs text-gray-500 space-y-1">
                                <div class="flex justify-between">
                                    <span>{{ __('partner.profile.upload_date') }}</span>
                                    <span class="font-medium text-gray-700">{{ $doc->created_at->format('d/m/Y') }}</span>
                                </div>
                                @if ($doc->expires_at)
                                    <div class="flex justify-between">
                                        <span>{{ __('partner.profile.expiry_date') }}</span>
                                        <span class="font-medium {{ $doc->isExpired() ? 'text-red-600' : 'text-gray-700' }}">
                                            {{ $doc->expires_at->format('d/m/Y') }}
                                        </span>
                                    </div>
                                @endif
                                @if ($doc->rejection_reason)
                                    <p class="mt-1 text-red-600">{{ $doc->rejection_reason }}</p>
                                @endif
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="mt-auto flex items-center gap-2 pt-2">
                            @if ($doc && $doc->file_path)
                                <a href="{{ asset('storage/' . $doc->file_path) }}"
                                   target="_blank"
                                   class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    {{ __('partner.profile.preview') }}
                                </a>
                            @endif

                            <label class="{{ $documentsLocked ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }} inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                {{ $doc ? __('partner.profile.reupload') : __('partner.profile.upload_file') }}
                                <input type="file"
                                       class="hidden doc-upload-input"
                                       data-type-id="{{ $docType->id }}"
                                       {{ $documentsLocked ? 'disabled' : '' }}
                                       accept=".pdf,.jpg,.jpeg,.png">
                            </label>

                            <span class="doc-upload-status hidden text-xs text-gray-400">
                                {{ __('partner.profile.uploading') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: Password                                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'password'" x-cloak>
        <div class="max-w-lg bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-5">{{ __('partner.profile.change_password') }}</h2>
            <form id="form-password" novalidate>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.current_password') }} <span class="text-red-500">*</span></label>
                        <input type="password" name="current_password" autocomplete="current-password"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.new_password') }} <span class="text-red-500">*</span></label>
                        <input type="password" name="password" autocomplete="new-password"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        <p class="mt-1 text-xs text-gray-400">{{ __('partner.profile.min_chars_hint') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.profile.confirm_password') }} <span class="text-red-500">*</span></label>
                        <input type="password" name="password_confirmation" autocomplete="new-password"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" id="btn-save-password"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        {{ __('partner.profile.change_password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
