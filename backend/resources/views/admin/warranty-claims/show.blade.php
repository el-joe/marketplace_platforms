@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/warranty-claims.js'])
@endpush

@section('title', $claim->claim_number . ' — ' . __('admin.warranty_claims_section.title'))

@section('content')

    @php
        $statusBadge = match ($claim->status) {
            'submitted' => 'bg-blue-100 text-blue-700',
            'under_review' => 'bg-yellow-100 text-yellow-700',
            'approved' => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700',
            'resolved' => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
        $statusLabel = __('admin.warranty_claims_section.' . $claim->status);
        $isResolved = $claim->status === 'resolved';
        $canResolve = $claim->status === 'approved';
        $sellerLabel = $claim->listing_type === \App\Models\WarrantyClaim::LISTING_TYPE_ADMIN
            ? __('admin.warranty_claims_section.platform_listing')
            : ($claim->vendor->store_name ?? '—');
    @endphp

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.warranty-claims.index') }}" class="hover:text-primary-600">{{ __('admin.warranty_claims_section.title') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium font-mono">{{ $claim->claim_number }}</span>
    </nav>

    <div class="flex flex-col lg:flex-row gap-5 items-start">

        {{-- ═════════════════════════════════════════════════════════════════ --}}
        {{-- LEFT: Claim details + messages --}}
        {{-- ═════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 flex flex-col gap-4">

            {{-- Claim header --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex flex-wrap items-start gap-3 mb-2">
                    <span class="font-mono text-sm text-gray-500">{{ $claim->claim_number }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }} js-status-badge">
                        {{ $statusLabel }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                        {{ \Illuminate\Support\Facades\Lang::has('admin.warranty_claims_section.issue_types.' . $claim->issue_type) ? __('admin.warranty_claims_section.issue_types.' . $claim->issue_type) : str_replace('_', ' ', ucfirst($claim->issue_type)) }}
                    </span>
                </div>
                <h1 class="text-lg font-semibold text-gray-900">{{ __('admin.warranty_claims_section.claim_case') }}</h1>
                @if($claim->issue_description)
                    <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap">{{ $claim->issue_description }}</p>
                @endif

                @if(!empty($claim->evidence_files))
                    <div class="mt-4">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('admin.warranty_claims_section.evidence_files') }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($claim->evidence_files as $path)
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($path) }}" target="_blank" rel="noopener"
                                    class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs text-primary-600 hover:bg-gray-50">
                                    {{ basename($path) }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="mt-3 text-xs text-gray-400 italic">{{ __('admin.warranty_claims_section.no_evidence') }}</p>
                @endif
            </div>

            {{-- Message thread --}}
            <div id="message-thread" class="space-y-3">
                @foreach($claim->messages as $msg)
                    @php
                        $role = $msg->sender_role;
                        $isAdmin = $role === 'admin';
                        $isInternal = (bool) $msg->is_internal_note;

                        $bubbleClass = $isInternal
                            ? 'bg-yellow-50 border border-yellow-200'
                            : ($isAdmin
                                ? 'bg-blue-50 border border-blue-200'
                                : ($role === 'vendor'
                                    ? 'bg-purple-50 border border-purple-200'
                                    : 'bg-gray-50 border border-gray-200'));

                        $alignClass = $isAdmin ? 'ml-8' : 'mr-8';

                        $authorLabel = match ($role) {
                            'admin' => __('admin.warranty_claims_section.support_agent'),
                            'vendor' => $claim->vendor->store_name ?? __('admin.warranty_claims_section.seller'),
                            default => $claim->customer->name ?? __('admin.warranty_claims_section.customer'),
                        };
                    @endphp
                    <div class="message-bubble {{ $alignClass }}" data-message-id="{{ $msg->id }}">
                        <div class="rounded-xl p-4 {{ $bubbleClass }}">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-semibold text-gray-700">{{ $authorLabel }}</span>
                                @if($isInternal)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-200 text-yellow-800">{{ __('admin.warranty_claims_section.internal_note_badge') }}</span>
                                @endif
                                <span class="text-xs text-gray-400 ml-auto">
                                    {{ $msg->created_at ? \Carbon\Carbon::parse($msg->created_at)->format('M d, Y H:i') : '' }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $msg->message }}</div>
                        </div>
                    </div>
                @endforeach

                @if($claim->messages->isEmpty())
                    <div class="text-center py-8 text-sm text-gray-400">{{ __('admin.warranty_claims_section.no_messages') }}</div>
                @endif
            </div>

            {{-- Reply form --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" id="reply-area">
                <form id="reply-form" data-url="{{ route('admin.warranty-claims.messages', $claim->id) }}" novalidate>
                    @csrf
                    <div id="reply-form-bg" class="mb-3 rounded-lg border border-gray-200 p-0.5 transition-colors">
                        <textarea id="reply-message" name="message" rows="4" placeholder="{{ __('admin.warranty_claims_section.write_message_placeholder') }}"
                            class="w-full text-sm p-3 rounded-md resize-none focus:outline-none bg-transparent"
                            required></textarea>
                    </div>

                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-gray-600">
                            <input type="checkbox" id="is-internal-note" name="is_internal_note" value="1"
                                class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400">
                            {{ __('admin.warranty_claims_section.internal_note_checkbox') }}
                        </label>
                        <button type="submit" id="btn-reply" class="btn btn-primary btn-sm">
                            {{ __('admin.warranty_claims_section.send_reply') }}
                        </button>
                    </div>
                </form>
            </div>

            {{-- Resolve panel --}}
            @if(!$isResolved)
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">{{ __('admin.warranty_claims_section.resolve') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('admin.warranty_claims_section.resolve_claim_desc') }}</p>

                    @unless($canResolve)
                        <p class="text-xs text-amber-600 mb-3">{{ __('admin.warranty_claims_section.must_be_approved_first') }}</p>
                    @endunless

                    <form id="resolve-form" data-url="{{ route('admin.warranty-claims.resolve', $claim->id) }}"
                        class="grid grid-cols-1 md:grid-cols-2 gap-3" novalidate>
                        @csrf

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warranty_claims_section.outcome') }}</label>
                            <select name="resolution" required class="form-input w-full text-sm" {{ $canResolve ? '' : 'disabled' }}>
                                <option value="">{{ __('admin.warranty_claims_section.select_placeholder') }}</option>
                                <option value="repair">{{ __('admin.warranty_claims_section.repair') }}</option>
                                <option value="replace">{{ __('admin.warranty_claims_section.replace') }}</option>
                                <option value="refund">{{ __('admin.warranty_claims_section.refund') }}</option>
                                <option value="no_action">{{ __('admin.warranty_claims_section.no_action') }}</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.warranty_claims_section.resolution_notes') }}</label>
                            <textarea name="resolution_notes" rows="3" class="form-input w-full text-sm" required
                                placeholder="{{ __('admin.warranty_claims_section.resolution_notes_placeholder') }}" {{ $canResolve ? '' : 'disabled' }}></textarea>
                        </div>

                        <div class="md:col-span-2 flex items-center justify-end gap-3">
                            <button type="submit" id="btn-resolve" class="btn btn-primary btn-sm" {{ $canResolve ? '' : 'disabled' }}>
                                {{ __('admin.warranty_claims_section.resolve') }}
                            </button>
                        </div>
                    </form>
                </x-card>
            @endif

        </div>

        {{-- ═════════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT: Sidebar --}}
        {{-- ═════════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-80 shrink-0 flex flex-col gap-4">

            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.warranty_claims_section.claim_info') }}</h3>
                <dl class="space-y-3 text-sm">

                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-xs text-gray-500 shrink-0">{{ __('admin.status') }}</dt>
                        <dd>
                            <select id="status-select" data-url="{{ route('admin.warranty-claims.status', $claim->id) }}"
                                class="form-input text-xs py-1 pr-7">
                                @foreach(['submitted', 'under_review', 'approved', 'rejected', 'resolved'] as $s)
                                    <option value="{{ $s }}" {{ $claim->status === $s ? 'selected' : '' }}>
                                        {{ __('admin.warranty_claims_section.' . $s) }}
                                    </option>
                                @endforeach
                            </select>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.issue_type') }}</dt>
                        <dd class="text-sm text-gray-700">{{ \Illuminate\Support\Facades\Lang::has('admin.warranty_claims_section.issue_types.' . $claim->issue_type) ? __('admin.warranty_claims_section.issue_types.' . $claim->issue_type) : str_replace('_', ' ', ucfirst($claim->issue_type)) }}</dd>
                    </div>

                    @if($claim->resolution)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.resolution') }}</dt>
                            <dd>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                    {{ __('admin.warranty_claims_section.' . $claim->resolution) }}
                                </span>
                            </dd>
                        </div>
                    @endif

                    @if($claim->resolution_notes)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.resolution_notes') }}</dt>
                            <dd class="text-sm text-gray-700 whitespace-pre-wrap">{{ $claim->resolution_notes }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.customer') }}</dt>
                        <dd>
                            <a href="{{ route('admin.customers.show', $claim->customer_id) }}"
                                class="text-sm text-primary-600 hover:underline">
                                {{ $claim->customer->name ?? '—' }}
                            </a>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.product') }}</dt>
                        <dd class="text-sm text-gray-700">{{ $claim->product->name ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.seller') }}</dt>
                        <dd class="text-sm text-gray-700">
                            @if($claim->listing_type === \App\Models\WarrantyClaim::LISTING_TYPE_VENDOR && $claim->vendor)
                                <a href="{{ route('admin.vendors.show', $claim->vendor_id) }}" class="text-primary-600 hover:underline">
                                    {{ $sellerLabel }}
                                </a>
                            @elseif($claim->listing_type === \App\Models\WarrantyClaim::LISTING_TYPE_ADMIN && $claim->adminListing)
                                <a href="{{ route('admin.admin-listings.edit', $claim->adminListing->id) }}" class="text-primary-600 hover:underline">
                                    {{ $sellerLabel }}
                                </a>
                            @else
                                {{ $sellerLabel }}
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.purchase_date') }}</dt>
                        <dd class="text-sm text-gray-700">{{ $claim->purchase_date?->format('M d, Y') }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.warranty_expires_at') }}</dt>
                        <dd class="text-sm text-gray-700">{{ $claim->warranty_expires_at?->format('M d, Y') }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.opened') ?? 'Opened' }}</dt>
                        <dd class="text-sm text-gray-700">{{ $claim->created_at->format('M d, Y H:i') }}</dd>
                    </div>

                    @if($claim->resolved_at)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.resolved_at') }}</dt>
                            <dd class="text-sm text-green-700">
                                {{ \Carbon\Carbon::parse($claim->resolved_at)->format('M d, Y H:i') }}
                            </dd>
                        </div>
                    @endif

                    @if($claim->resolvedByAdmin)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.warranty_claims_section.resolved_by') }}</dt>
                            <dd class="text-sm text-gray-700">{{ $claim->resolvedByAdmin->name }}</dd>
                        </div>
                    @endif

                </dl>
            </x-card>

        </div>

    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            replySent: @json(__('admin.warranty_claims_section.reply_sent')),
            replyFailed: @json(__('admin.warranty_claims_section.reply_failed')),
            supportAgent: @json(__('admin.warranty_claims_section.support_agent')),
            statusUpdated: @json(__('admin.warranty_claims_section.status_updated')),
            statusUpdateFailed: @json(__('admin.warranty_claims_section.status_update_failed')),
            resolveConfirmTitle: @json(__('admin.warranty_claims_section.resolve_confirm_title')),
            resolveConfirmText: @json(__('admin.warranty_claims_section.resolve_confirm_text')),
            resolveConfirmButton: @json(__('admin.warranty_claims_section.resolve_confirm_button')),
            resolvedMessage: @json(__('admin.warranty_claims_section.resolved_message')),
            resolveFailed: @json(__('admin.warranty_claims_section.resolve_failed')),
            internalNoteBadge: @json(__('admin.warranty_claims_section.internal_note_badge')),
            statusLabels: {
                submitted: @json(__('admin.warranty_claims_section.submitted')),
                under_review: @json(__('admin.warranty_claims_section.under_review')),
                approved: @json(__('admin.warranty_claims_section.approved')),
                rejected: @json(__('admin.warranty_claims_section.rejected')),
                resolved: @json(__('admin.warranty_claims_section.resolved')),
            },
        });
    </script>
@endpush
