@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/select2.js', 'resources/js/admin/vendor-applications.js'])
@endpush

@section('title', __('admin.vendor_applications.review_prefix', ['name' => $vendor->store_name]))

@section('content')

    {{-- ─── Header ───────────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.vendor-applications.index') }}" class="hover:text-primary-600">{{ __('admin.vendor_applications.applications_breadcrumb') }}</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">{{ $vendor->store_name }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $vendor->store_name }}</h1>
            <div class="flex items-center gap-3 mt-1">
                @php
                    $statusColors = ['pending' => 'warning', 'under_review' => 'primary', 'active' => 'success', 'rejected' => 'danger'];
                    $sc = $statusColors[$vendor->global_status?->value] ?? 'gray';
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">
                    {{ __('admin.vendor_applications.global_status_' . $vendor->global_status?->value) }}
                </span>
                @php
                    $urgencyClass = $daysWaiting > 5 ? 'text-red-600 font-semibold' : ($daysWaiting >= 2 ? 'text-yellow-600' : 'text-green-600');
                @endphp
                <span class="text-sm {{ $urgencyClass }}">{{ __('admin.vendor_applications.waiting_days_text', ['days' => $daysWaiting]) }}</span>
                <button type="button"
                    id="start-review-btn"
                    class="btn btn-secondary btn-xs {{ $vendor->global_status === \App\Enums\VendorGlobalStatus::UnderReview ? 'hidden' : '' }}"
                    data-url="{{ route('admin.vendor-applications.start-review', $vendor->id) }}">
                    {{ __('admin.vendor_applications.start_review') }}
                </button>
                <button type="button"
                    id="assign-me-btn"
                    class="btn btn-ghost btn-xs"
                    data-url="{{ route('admin.vendor-applications.assign-me', $vendor->id) }}"
                    title="{{ __('admin.vendor_applications.assign_to_me_title') }}">
                    {{ __('admin.vendor_applications.assign_to_me') }}
                </button>
            </div>
        </div>
        <a href="{{ route('admin.vendor-applications.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.vendor_applications.back_to_queue') }}</a>
    </div>

    {{-- ─── Main 2-column layout ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-12 gap-6">

        {{-- ═══════ LEFT: Review Content (7 cols) ═══════ --}}
        <div class="col-span-12 lg:col-span-7 space-y-6">

            {{-- ─── Business Information ────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.vendors.business_information') }}">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.business_name_label') }}</dt>
                        <dd class="font-medium">{{ $vendor->business_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.business_type_label') }}</dt>
                        <dd>{{ $vendor->business_type ? __('admin.vendor_applications.business_type_' . $vendor->business_type->value) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.registration_no_label') }}</dt>
                        <dd class="font-mono text-xs">{{ $vendor->business_registration_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.tax_id_label') }}</dt>
                        <dd class="font-mono text-xs">{{ $vendor->tax_id ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.contact_email_label') }}</dt>
                        <dd class="break-all">{{ $vendor->contact_email ?? $vendor->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.contact_phone_label') }}</dt>
                        <dd>{{ $vendor->contact_phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.whatsapp_label') }}</dt>
                        <dd>{{ $vendor->whatsapp_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.country_label') }}</dt>
                        <dd>{{ $vendor->country?->flag_emoji ?? '' }} {{ $vendor->country?->name_en ?? '—' }}</dd>
                    </div>
                    @php $address = $vendor->addresses->first(); @endphp
                    @if($address)
                        <div class="col-span-2">
                            <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.business_address_label') }}</dt>
                            <dd>
                                {{ $address->street_address ?? '' }}
                                @if($address->building), {{ $address->building }}@endif
                                @if($address->area), {{ $address->area }}@endif
                                @if($address->city), {{ $address->city->name }}@endif
                                @if($address->postal_code), {{ $address->postal_code }}@endif
                            </dd>
                        </div>
                    @endif
                    @if($vendor->store_description)
                        <div class="col-span-2">
                            <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.store_description_label') }}</dt>
                            <dd class="text-gray-600 text-sm">{{ $vendor->store_description }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            {{-- ─── Documents ────────────────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.vendors.documents') }}" subtitle="{{ __('admin.vendor_applications.required_docs_subtitle') }}">

                {{-- Required docs checklist --}}
                <div class="flex flex-wrap gap-3 mb-4">
                    @foreach($requiredDocs as $type => $info)
                        <div class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium
                            {{ $info['verified'] ? 'bg-green-100 text-green-700' : ($info['uploaded'] ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                            @if($info['verified'])
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @elseif($info['uploaded'])
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                            @else
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            @endif
                            {{ $info['label'] }}
                        </div>
                    @endforeach
                </div>

                {{-- All documents table --}}
                @if($vendor->documents->isEmpty())
                    <p class="text-sm text-gray-400 py-4 text-center">{{ __('admin.vendors.no_documents_uploaded') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-start">
                                    <th class="py-2 pr-3 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.type_column') }}</th>
                                    <th class="py-2 pr-3 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.status_column') }}</th>
                                    <th class="py-2 pr-3 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.expires_column') }}</th>
                                    <th class="py-2 pr-3 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendor_applications.verified_by_column') }}</th>
                                    <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.vendors.actions_column') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($vendor->documents as $doc)
                                    @php
                                        $docStatColors = ['approved'=>'success','pending'=>'warning','rejected'=>'danger','expired'=>'danger'];
                                        $dc = $docStatColors[$doc->status->value] ?? 'gray';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-3 font-medium">
                                            {{ $doc->documentType?->name_en ?? '—' }}
                                        </td>
                                        <td class="py-2 pr-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $dc }}-100 text-{{ $dc }}-700">
                                                {{ __('admin.vendor_applications.doc_status_' . $doc->status->value) }}
                                            </span>
                                            @if($doc->rejection_reason)
                                                <div class="text-xs text-red-600 mt-0.5">{{ $doc->rejection_reason }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3 text-gray-500 text-xs">
                                            {{ $doc->expires_at ? $doc->expires_at->format('d M Y') : '—' }}
                                            @if($doc->isExpired())
                                                <span class="text-red-500 ml-1">{{ __('admin.vendor_applications.expired_suffix') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3 text-xs text-gray-500">
                                            @if($doc->verifiedByAdmin)
                                                {{ $doc->verifiedByAdmin->name }}
                                                @if($doc->verified_at)
                                                    <br><span class="text-gray-400">{{ $doc->verified_at->format('d M Y') }}</span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            <div class="flex items-center gap-1">
                                                @if($doc->file_path)
                                                    <button type="button"
                                                        class="btn btn-xs btn-secondary js-preview-doc-btn"
                                                        data-file="{{ Storage::url($doc->file_path) }}"
                                                        data-type="{{ $doc->documentType?->code }}"
                                                        data-id="{{ $doc->id }}">
                                                        {{ __('admin.vendors.view') }}
                                                    </button>
                                                @endif
                                                @if($doc->status?->value !== 'approved')
                                                    <button type="button"
                                                        class="btn btn-xs btn-success js-verify-doc-btn"
                                                        data-url="{{ route('admin.vendor-applications.documents.verify', $doc->id) }}"
                                                        data-id="{{ $doc->id }}">
                                                        {{ __('admin.vendors.verify') }}
                                                    </button>
                                                @endif
                                                @if($doc->status?->value !== 'rejected')
                                                    <button type="button"
                                                        class="btn btn-xs btn-danger js-reject-doc-btn"
                                                        data-url="{{ route('admin.vendor-applications.documents.reject', $doc->id) }}"
                                                        data-id="{{ $doc->id }}">
                                                        {{ __('admin.vendors.reject') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Show missing required doc rows --}}
                @foreach($requiredDocs as $type => $info)
                    @if(!$info['uploaded'])
                        <div class="mt-3 flex items-center gap-3 rounded border border-dashed border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <span>{!! str_replace($info['label'], '<strong>' . e($info['label']) . '</strong>', __('admin.vendor_applications.missing_doc_notice', ['label' => $info['label']])) !!}</span>
                        </div>
                    @endif
                @endforeach
            </x-card>

            {{-- ─── Bank Account ─────────────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.vendors.bank_account') }}">
                @php
                    $primaryBank = $vendor->bankAccounts->where('is_primary', true)->first()
                        ?? $vendor->bankAccounts->first();
                    $bankStatusColors = ['verified'=>'success','pending'=>'warning','rejected'=>'danger','unverified'=>'gray'];
                @endphp
                @if($primaryBank)
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.bank_name_label') }}</dt>
                            <dd class="font-medium">{{ $primaryBank->bank_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.account_holder_label') }}</dt>
                            <dd>{{ $primaryBank->account_holder_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.iban_label') }}</dt>
                            <dd class="font-mono text-xs">
                                @if($primaryBank->iban)
                                    ****{{ substr($primaryBank->iban, -4) }}
                                @else —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.status_label') }}</dt>
                            <dd>
                                @php $bsc = $bankStatusColors[$primaryBank->verification_status->value] ?? 'gray'; @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $bsc }}-100 text-{{ $bsc }}-700">
                                    {{ __('admin.vendor_applications.bank_status_' . ($primaryBank->verification_status?->value ?? 'unverified')) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.currency_label') }}</dt>
                            <dd class="uppercase">{{ $primaryBank->currency ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 uppercase font-medium mb-0.5">{{ __('admin.vendor_applications.swift_label') }}</dt>
                            <dd class="font-mono text-xs">{{ $primaryBank->swift_code ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if($primaryBank->verification_status !== \App\Enums\VendorBankAccountVerificationStatus::Verified)
                        <div class="mt-4">
                            <button type="button"
                                class="btn btn-success btn-sm js-verify-bank-btn"
                                data-url="{{ route('admin.vendors.bank-accounts.verify', [$vendor->id, $primaryBank->id]) }}"
                                data-name="{{ e($primaryBank->bank_name ?? 'bank account') }}">
                                {{ __('admin.vendor_applications.verify_bank_account_btn') }}
                            </button>
                        </div>
                    @endif
                    @if($vendor->bankAccounts->count() > 1)
                        <p class="text-xs text-gray-400 mt-3">{{ __('admin.vendor_applications.more_accounts_suffix', ['count' => $vendor->bankAccounts->count() - 1]) }}</p>
                    @endif
                @else
                    <div class="flex items-center gap-2 rounded border border-dashed border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        {{ __('admin.vendor_applications.no_bank_account_notice') }}
                    </div>
                @endif
            </x-card>

        </div>{{-- end left column --}}

        {{-- ═══════ RIGHT: Decision Panel (5 cols) ═══════ --}}
        <div class="col-span-12 lg:col-span-5 space-y-5">

            {{-- ─── Review Checklist ─────────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.vendors.review_checklist') }}">
                <ul class="space-y-2.5">
                    @foreach($checklist as $key => $item)
                        <li class="flex items-center gap-2.5">
                            @if($item['pass'])
                                <span class="flex-shrink-0 w-5 h-5 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                </span>
                                <span class="text-sm text-gray-700">{{ $item['label'] }}</span>
                            @else
                                <span class="flex-shrink-0 w-5 h-5 rounded-full bg-red-100 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </span>
                                <span class="text-sm text-red-600">{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if(!$canApprove)
                    <p class="mt-3 text-xs text-red-500 bg-red-50 rounded px-3 py-2">
                        {{ __('admin.vendor_applications.resolve_checklist_notice') }}
                    </p>
                @endif
            </x-card>

            {{-- ─── Assignment ───────────────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.vendors.assignment') }}">
                <div class="space-y-4">
                    <x-form-async-select
                        name="account_manager_admin_id"
                        label="{{ __('admin.vendor_applications.account_manager_label') }}"
                        :search-url="route('admin.admins.search')"
                        :value="$vendor->account_manager_admin_id"
                        :value-label="$vendor->accountManagerAdmin?->name"
                        placeholder="{{ __('admin.vendor_applications.search_admin_placeholder') }}"
                        search-param="q" />
                    <x-form-input
                        type="number"
                        name="commission_rate_override_display"
                        label="{{ __('admin.vendor_applications.commission_rate_override') }}"
                        step="0.01"
                        min="0"
                        max="100"
                        placeholder="{{ __('admin.vendor_applications.leave_empty_category_default') }}"
                        :value="$vendor->commission_rate" />
                </div>
            </x-card>

            {{-- ─── Decision ─────────────────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.vendors.decision') }}">
                <div class="space-y-2">
                    @if(in_array($vendor->global_status, [\App\Enums\VendorGlobalStatus::Pending, \App\Enums\VendorGlobalStatus::UnderReview]))
                        <button
                            type="button"
                            id="open-approve-modal-btn"
                            class="w-full btn btn-primary {{ !$canApprove ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ !$canApprove ? 'disabled' : '' }}
                            title="{{ !$canApprove ? __('admin.vendor_applications.complete_checklist_first') : '' }}"
                            data-can-approve="{{ $canApprove ? 'true' : 'false' }}"
                            data-vendor-id="{{ $vendor->id }}"
                            data-approve-url="{{ route('admin.vendor-applications.approve', $vendor->id) }}"
                            data-vendor-name="{{ e($vendor->store_name) }}">
                            {{ __('admin.vendor_applications.approve_application') }}
                        </button>
                        <button
                            type="button"
                            id="open-request-info-modal-btn"
                            class="w-full btn btn-secondary"
                            data-vendor-name="{{ e($vendor->store_name) }}">
                            {{ __('admin.vendor_applications.request_more_information') }}
                        </button>
                        <button
                            type="button"
                            id="open-reject-modal-btn"
                            class="w-full btn btn-danger"
                            data-vendor-name="{{ e($vendor->store_name) }}"
                            data-reject-url="{{ route('admin.vendor-applications.reject', $vendor->id) }}">
                            {{ __('admin.vendor_applications.reject_application') }}
                        </button>
                    @elseif($vendor->global_status === \App\Enums\VendorGlobalStatus::Active)
                        <div class="rounded-lg bg-green-50 border border-green-100 px-4 py-3 text-sm text-green-800">
                            {{ __('admin.vendor_applications.approved_notice') }}
                        </div>
                    @elseif($vendor->global_status === \App\Enums\VendorGlobalStatus::Rejected)
                        <div class="rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-800">
                            {{ __('admin.vendor_applications.rejected_notice') }}
                            @if($vendor->rejection_reason)
                                <p class="mt-1 text-xs">{{ $vendor->rejection_reason }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </x-card>

        </div>{{-- end right column --}}
    </div>{{-- end grid --}}

    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    {{-- ─── MODALS ───────────────────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════════ --}}

    {{-- ─── Document Preview Modal ──────────────────────────────────────────────── --}}
    <x-modal id="doc-preview-modal" title="{{ __('admin.vendor_applications.document_preview') }}" size="xl">
        <div id="doc-preview-content" class="min-h-[400px] flex items-center justify-center bg-gray-50 rounded">
            <p class="text-gray-400 text-sm">{{ __('admin.vendor_applications.loading') }}</p>
        </div>
        <div class="flex items-center justify-between mt-4">
            <div class="flex gap-2">
                <button type="button" id="modal-verify-doc-btn" class="btn btn-success btn-sm hidden">{{ __('admin.vendor_applications.verify_document_btn') }}</button>
                <button type="button" id="modal-reject-doc-open-btn" class="btn btn-danger btn-sm hidden">{{ __('admin.vendor_applications.reject_document_btn') }}</button>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="$('#doc-preview-modal').modal('close')">{{ __('admin.vendor_applications.close') }}</button>
        </div>
    </x-modal>

    {{-- ─── Reject Document Modal ────────────────────────────────────────────────── --}}
    <x-modal id="reject-doc-modal" title="{{ __('admin.vendor_applications.reject_document_btn') }}" size="sm">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_applications.rejection_reason_required') }} <span class="text-red-500">*</span></label>
        <textarea id="doc-rejection-reason" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.vendor_applications.rejection_reason_hint') }}"></textarea>
        <p class="text-xs text-red-500 hidden mt-1" id="doc-rejection-error">{{ __('admin.vendor_applications.reason_required') }}</p>
        <div class="flex justify-end gap-3 mt-4">
            <button type="button" class="btn btn-secondary" onclick="$('#reject-doc-modal').modal('close')">{{ __('admin.vendor_applications.cancel') }}</button>
            <button type="button" id="confirm-reject-doc-btn" class="btn btn-danger">{{ __('admin.vendor_applications.reject') }}</button>
        </div>
    </x-modal>

    {{-- ─── Approve Application Modal ────────────────────────────────────────────── --}}
    <x-modal id="approve-modal" title="{{ __('admin.vendor_applications.approve_application') }}" size="md">
        <p class="text-sm text-gray-600 mb-4">
            {{ \Illuminate\Support\Str::before(__('admin.vendor_applications.approve_application_notice'), ':name') }}<strong id="approve-vendor-name"></strong>{{ \Illuminate\Support\Str::after(__('admin.vendor_applications.approve_application_notice'), ':name') }}
        </p>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_applications.commission_rate_override') }}</label>
                <input type="number" id="approve-commission" class="form-input w-full text-sm" step="0.01" min="0" max="100"
                    placeholder="{{ __('admin.vendor_applications.leave_empty_category_default') }}"
                    value="{{ $vendor->commission_rate }}">
                <p class="text-xs text-gray-400 mt-1">{{ __('admin.vendor_applications.current_label', ['value' => $vendor->commission_rate ? $vendor->commission_rate . '%' : __('admin.vendor_applications.category_default')]) }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_applications.account_manager_label') }}</label>
                <select id="approve-account-manager" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.vendor_applications.account_manager_none') }}</option>
                    @foreach($admins as $a)
                        <option value="{{ $a->id }}" {{ $vendor->account_manager_admin_id === $a->id ? 'selected' : '' }}>
                            {{ $a->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_applications.internal_notes_label') }}</label>
                <textarea id="approve-notes" rows="2" class="form-input w-full text-sm" placeholder="{{ __('admin.vendor_applications.internal_notes_placeholder') }}"></textarea>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#approve-modal').modal('close')">{{ __('admin.vendor_applications.cancel') }}</button>
            <button type="button" id="confirm-approve-btn" class="btn btn-primary">{{ __('admin.vendor_applications.confirm_approval') }}</button>
        </div>
    </x-modal>

    {{-- ─── Reject Application Modal ─────────────────────────────────────────────── --}}
    <x-modal id="reject-modal" title="{{ __('admin.vendor_applications.reject_application') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">{{ __('admin.vendor_applications.reject') }} <strong id="reject-vendor-name"></strong>. {{ __('admin.vendor_applications.select_rejection_reasons') }}</p>
        <div class="space-y-2 mb-4">
            @foreach([
                'documents_incomplete'    => __('admin.vendor_applications.documents_incomplete'),
                'documents_invalid'       => __('admin.vendor_applications.documents_invalid'),
                'business_not_verifiable' => __('admin.vendor_applications.business_not_verifiable'),
                'policy_violation'        => __('admin.vendors.policy_violation'),
                'duplicate_account'       => __('admin.vendor_applications.duplicate_account'),
                'prohibited_category'     => __('admin.vendor_applications.prohibited_category'),
                'other'                   => __('admin.vendor_applications.other'),
            ] as $code => $label)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" class="form-checkbox rejection-code-checkbox" value="{{ $code }}">
                    <span class="text-sm text-gray-700">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_applications.detailed_reason') }} <span class="text-red-500">*</span></label>
        <textarea id="reject-reason-input" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.vendor_applications.detailed_reason_placeholder') }}"></textarea>
        <p class="text-xs text-red-500 hidden mt-1" id="reject-reason-error">{{ __('admin.vendor_applications.rejection_reason_required_hint') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#reject-modal').modal('close')">{{ __('admin.vendor_applications.cancel') }}</button>
            <button type="button" id="confirm-reject-btn" class="btn btn-danger">{{ __('admin.vendor_applications.reject_application') }}</button>
        </div>
    </x-modal>

    {{-- ─── Request More Info Modal ──────────────────────────────────────────────── --}}
    <x-modal id="request-info-modal" title="{{ __('admin.vendor_applications.request_more_information') }}" size="md">
        <p class="text-sm text-gray-600 mb-3">{{ \Illuminate\Support\Str::before(__('admin.vendor_applications.select_documents_needed'), ':name') }}<strong id="request-info-vendor-name"></strong>{{ \Illuminate\Support\Str::after(__('admin.vendor_applications.select_documents_needed'), ':name') }}</p>
        <div class="space-y-2 mb-4">
            @foreach([
                'business_license' => __('admin.vendors.business_license'),
                'tax_certificate'  => __('admin.vendors.tax_certificate'),
                'owner_id'         => __('admin.vendors.owner_id'),
                'bank_statement'   => __('admin.vendor_applications.bank_statement'),
                'trade_license'    => __('admin.vendor_applications.trade_license'),
                'other'            => __('admin.vendor_applications.other_custom'),
            ] as $docType => $docLabel)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" class="form-checkbox request-doc-checkbox" value="{{ $docType }}">
                    <span class="text-sm text-gray-700">{{ $docLabel }}</span>
                </label>
            @endforeach
        </div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_applications.message_to_vendor') }}</label>
        <textarea id="request-info-message" rows="3" class="form-input w-full text-sm" placeholder="{{ __('admin.vendor_applications.additional_instructions') }}"></textarea>
        <p class="text-xs text-red-500 hidden mt-1" id="request-doc-error">{{ __('admin.vendor_applications.select_at_least_one_doc') }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" class="btn btn-secondary" onclick="$('#request-info-modal').modal('close')">{{ __('admin.vendor_applications.cancel') }}</button>
            <button type="button" id="confirm-request-info-btn" class="btn btn-secondary"
                data-url="{{ route('admin.vendor-applications.request-info', $vendor->id) }}">
                {{ __('admin.vendor_applications.send_request') }}
            </button>
        </div>
    </x-modal>

    {{-- Data for JS --}}
    <script>
        window.vendorApp = {
            approveUrl:       @json(route('admin.vendor-applications.approve', $vendor->id)),
            rejectUrl:        @json(route('admin.vendor-applications.reject', $vendor->id)),
            requestInfoUrl:   @json(route('admin.vendor-applications.request-info', $vendor->id)),
            startReviewUrl:   @json(route('admin.vendor-applications.start-review', $vendor->id)),
            assignMeUrl:      @json(route('admin.vendor-applications.assign-me', $vendor->id)),
            canApprove:       {{ $canApprove ? 'true' : 'false' }},
        };
    </script>

@endsection
