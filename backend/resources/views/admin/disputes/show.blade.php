@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/disputes.js'])
@endpush

@section('title', $dispute->dispute_number . ' — ' . __('admin.disputes_section.title'))

@section('content')

    @php
        $statusBadge = match ($dispute->status->value) {
            'open' => 'bg-yellow-100 text-yellow-700',
            'seller_responded' => 'bg-blue-100 text-blue-700',
            'under_review' => 'bg-indigo-100 text-indigo-700',
            'escalated' => 'bg-red-100 text-red-700 border border-red-200',
            'resolved' => 'bg-green-100 text-green-700',
            'closed' => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
        $reasonLabel = __('admin.disputes_section.reason_' . $dispute->reason->value);
        $statusLabel = __('admin.disputes_section.' . $dispute->status->value);
        $isClosed = in_array($dispute->status->value, ['resolved', 'closed'], true);
        $currency = $dispute->order->currency ?? 'USD';
        $compensation = $dispute->compensation
            ? number_format($dispute->compensation, 2)
            : null;
    @endphp

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.disputes.index') }}" class="hover:text-primary-600">{{ __('admin.disputes_section.title') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium font-mono">{{ $dispute->dispute_number }}</span>
    </nav>

    <div class="flex flex-col lg:flex-row gap-5 items-start">

        {{-- ═════════════════════════════════════════════════════════════════ --}}
        {{-- LEFT: Conversation thread --}}
        {{-- ═════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 flex flex-col gap-4">

            {{-- Dispute header --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex flex-wrap items-start gap-3 mb-2">
                    <span class="font-mono text-sm text-gray-500">{{ $dispute->dispute_number }}</span>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }} js-status-badge">
                        {{ $statusLabel }}
                    </span>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                        {{ $reasonLabel }}
                    </span>
                </div>
                <h1 class="text-lg font-semibold text-gray-900">{{ __('admin.disputes_section.dispute_case') }}</h1>
                @if($dispute->description)
                    <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap">{{ $dispute->description }}</p>
                @endif
            </div>

            {{-- Message thread --}}
            <div id="message-thread" class="space-y-3">
                @foreach($dispute->messages as $msg)
                    @php
                        $role = $msg->sender_role?->value;
                        $isAdmin = $role === 'admin';
                        $isInternal = (bool) $msg->is_internal_note;

                        $bubbleClass = $isInternal
                            ? 'bg-yellow-50 border border-yellow-200'
                            : ($isAdmin
                                ? 'bg-blue-50 border border-blue-200'
                                : ($role === 'seller'
                                    ? 'bg-purple-50 border border-purple-200'
                                    : 'bg-gray-50 border border-gray-200'));

                        $alignClass = $isAdmin ? 'ml-8' : 'mr-8';

                        $authorLabel = match ($role) {
                            'admin' => __('admin.disputes_section.support_agent'),
                            'seller' => $dispute->vendor->store_name ?? __('admin.disputes_section.vendor'),
                            default => $dispute->customer->name ?? __('admin.disputes_section.customer'),
                        };
                    @endphp
                    <div class="message-bubble {{ $alignClass }}" data-message-id="{{ $msg->id }}">
                        <div class="rounded-xl p-4 {{ $bubbleClass }}">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-semibold text-gray-700">{{ $authorLabel }}</span>
                                @if($isInternal)
                                    <span
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-200 text-yellow-800">{{ __('admin.disputes_section.internal_note_badge') }}</span>
                                @endif
                                <span class="text-xs text-gray-400 ml-auto">
                                    {{ $msg->created_at ? \Carbon\Carbon::parse($msg->created_at)->format('M d, Y H:i') : '' }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $msg->message }}</div>
                        </div>
                    </div>
                @endforeach

                @if($dispute->messages->isEmpty())
                    <div class="text-center py-8 text-sm text-gray-400">{{ __('admin.disputes_section.no_messages') }}</div>
                @endif
            </div>

            {{-- Reply form --}}
            @if(!$isClosed)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" id="reply-area">
                    <form id="reply-form" data-url="{{ route('admin.disputes.reply', $dispute->id) }}" novalidate>
                        @csrf
                        <div id="reply-form-bg" class="mb-3 rounded-lg border border-gray-200 p-0.5 transition-colors">
                            <textarea id="reply-message" name="message" rows="4" placeholder="{{ __('admin.disputes_section.write_message_placeholder') }}"
                                class="w-full text-sm p-3 rounded-md resize-none focus:outline-none bg-transparent"
                                required></textarea>
                        </div>

                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-gray-600">
                                <input type="checkbox" id="is-internal-note" name="is_internal_note" value="1"
                                    class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400">
                                {{ __('admin.disputes_section.internal_note_checkbox') }}
                            </label>
                            <button type="submit" id="btn-reply" class="btn btn-primary btn-sm">
                                {{ __('admin.disputes_section.send_reply') }}
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="text-center text-sm text-gray-400 py-4">
                    {{ __('admin.disputes_section.dispute_status_reopen', ['status' => $statusLabel]) }}
                </div>
            @endif

            {{-- Resolve panel --}}
            @if(!$isClosed)
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">{{ __('admin.disputes_section.resolve') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('admin.disputes_section.resolve_dispute_desc') }}</p>

                    <form id="resolve-form" data-url="{{ route('admin.disputes.resolve', $dispute->id) }}"
                        class="grid grid-cols-1 md:grid-cols-2 gap-3" novalidate>
                        @csrf

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.disputes_section.outcome') }}</label>
                            <select name="resolution" required class="form-input w-full text-sm">
                                <option value="">{{ __('admin.disputes_section.select_placeholder') }}</option>
                                <option value="favor_customer">{{ __('admin.disputes_section.favor_customer') }}</option>
                                <option value="favor_seller">{{ __('admin.disputes_section.favor_seller') }}</option>
                                <option value="split">{{ __('admin.disputes_section.split') }}</option>
                                <option value="no_action">{{ __('admin.disputes_section.no_action') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                {{ __('admin.disputes_section.compensation', ['currency' => $currency]) }}
                            </label>
                            <input type="number" name="compensation" step="1" min="0" class="form-input w-full text-sm"
                                placeholder="0">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.disputes_section.resolution_notes') }}</label>
                            <textarea name="resolution_notes" rows="3" class="form-input w-full text-sm"
                                placeholder="{{ __('admin.disputes_section.resolution_notes_placeholder') }}"></textarea>
                        </div>

                        <div class="md:col-span-2 flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-gray-600">
                                <input type="checkbox" name="close" value="1"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-400">
                                {{ __('admin.disputes_section.close_thread_checkbox') }}
                            </label>
                            <button type="submit" id="btn-resolve" class="btn btn-primary btn-sm">
                                {{ __('admin.disputes_section.resolve') }}
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

            {{-- Dispute Info --}}
            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.disputes_section.dispute_info') }}</h3>
                <dl class="space-y-3 text-sm">

                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-xs text-gray-500 shrink-0">{{ __('admin.status') }}</dt>
                        <dd>
                            <select id="status-select" data-url="{{ route('admin.disputes.update-status', $dispute->id) }}"
                                class="form-input text-xs py-1 pr-7">
                                @foreach(['open', 'seller_responded', 'under_review', 'escalated', 'resolved', 'closed'] as $s)
                                    <option value="{{ $s }}" {{ $dispute->status->value === $s ? 'selected' : '' }}>
                                        {{ __('admin.disputes_section.' . $s) }}
                                    </option>
                                @endforeach
                            </select>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.reason') }}</dt>
                        <dd>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                {{ $reasonLabel }}
                            </span>
                        </dd>
                    </div>

                    @if($dispute->resolution)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.resolution') }}</dt>
                            <dd>
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                    {{ __('admin.disputes_section.' . $dispute->resolution->value) }}
                                </span>
                            </dd>
                        </div>
                    @endif

                    @if($compensation)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.compensation_col') }}</dt>
                            <dd class="text-sm font-medium text-gray-800">
                                {{ $compensation }} {{ $currency }}
                            </dd>
                        </div>
                    @endif

                    @if($dispute->resolution_notes)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.resolution_notes') }}</dt>
                            <dd class="text-sm text-gray-700 whitespace-pre-wrap">{{ $dispute->resolution_notes }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.customer') }}</dt>
                        <dd>
                            <a href="{{ route('admin.customers.show', $dispute->customer_id) }}"
                                class="text-sm text-primary-600 hover:underline">
                                {{ $dispute->customer->name ?? '—' }}
                            </a>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.vendor') }}</dt>
                        <dd>
                            <a href="{{ route('admin.vendors.show', $dispute->vendor_id) }}"
                                class="text-sm text-primary-600 hover:underline">
                                {{ $dispute->vendor->store_name ?? '—' }}
                            </a>
                        </dd>
                    </div>

                    @if($dispute->order)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.order') }}</dt>
                            <dd>
                                <a href="{{ route('admin.orders.show', $dispute->order_id) }}"
                                    class="text-sm text-primary-600 hover:underline font-mono">
                                    {{ $dispute->order->order_number }}
                                </a>
                            </dd>
                        </div>
                    @endif

                    @if($dispute->subOrder)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.sub_order') }}</dt>
                            <dd class="text-sm font-mono text-gray-700">
                                {{ $dispute->subOrder->sub_order_number }}
                            </dd>
                        </div>
                    @endif

                    @if($dispute->returnRequest)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.related_return') }}</dt>
                            <dd class="text-sm font-mono text-gray-700">
                                {{ $dispute->returnRequest->return_number }}
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.opened') }}</dt>
                        <dd class="text-sm text-gray-700">{{ $dispute->created_at->format('M d, Y H:i') }}</dd>
                    </div>

                    @if($dispute->resolved_at)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.disputes_section.resolved_at') }}</dt>
                            <dd class="text-sm text-green-700">
                                {{ \Carbon\Carbon::parse($dispute->resolved_at)->format('M d, Y H:i') }}
                            </dd>
                        </div>
                    @endif

                </dl>
            </x-card>

            {{-- Assigned to --}}
            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('admin.disputes_section.assigned_to_title') }}</h3>

                <p id="assignee-name" class="text-sm font-medium text-gray-800 mb-3">
                    {{ $dispute->assignedToAdmin?->name ?? __('admin.disputes_section.unassigned') }}
                </p>

                <div class="flex flex-col gap-2">
                    <button id="btn-assign-me" data-url="{{ route('admin.disputes.assign-me', $dispute->id) }}"
                        data-my-name="{{ $admin->name }}" class="btn btn-secondary btn-sm w-full">
                        {{ __('admin.disputes_section.assign_to_me') }}
                    </button>

                    <div class="flex gap-2">
                        <select id="reassign-select" class="form-input flex-1 text-xs py-1">
                            <option value="">{{ __('admin.disputes_section.reassign_to') }}</option>
                            @foreach($admins as $a)
                                <option value="{{ $a->id }}" {{ $dispute->assigned_to_admin_id === $a->id ? 'selected' : '' }}>
                                    {{ $a->name }}
                                </option>
                            @endforeach
                        </select>
                        <button id="btn-reassign" data-url="{{ route('admin.disputes.assign', $dispute->id) }}"
                            class="btn btn-primary btn-sm shrink-0">
                            {{ __('admin.disputes_section.save') }}
                        </button>
                    </div>
                </div>
            </x-card>

        </div>

    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            replySent: @json(__('admin.disputes_section.reply_sent')),
            replyFailed: @json(__('admin.disputes_section.reply_failed')),
            supportAgent: @json(__('admin.disputes_section.support_agent')),
            statusUpdated: @json(__('admin.disputes_section.status_updated')),
            statusUpdateFailed: @json(__('admin.disputes_section.status_update_failed')),
            assignFailed: @json(__('admin.disputes_section.assign_failed')),
            reassignFailed: @json(__('admin.disputes_section.reassign_failed')),
            unassigned: @json(__('admin.disputes_section.unassigned')),
            resolveConfirmTitle: @json(__('admin.disputes_section.resolve_confirm_title')),
            resolveConfirmText: @json(__('admin.disputes_section.resolve_confirm_text')),
            resolveConfirmButton: @json(__('admin.disputes_section.resolve_confirm_button')),
            resolvedMessage: @json(__('admin.disputes_section.resolved_message')),
            resolveFailed: @json(__('admin.disputes_section.resolve_failed')),
            internalNoteBadge: @json(__('admin.disputes_section.internal_note_badge')),
            statusLabels: {
                open: @json(__('admin.disputes_section.open')),
                seller_responded: @json(__('admin.disputes_section.seller_responded')),
                under_review: @json(__('admin.disputes_section.under_review')),
                escalated: @json(__('admin.disputes_section.escalated')),
                resolved: @json(__('admin.disputes_section.resolved')),
                closed: @json(__('admin.disputes_section.closed')),
            },
        });
    </script>
@endpush
