@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/support-tickets.js'])
@endpush

@push('scripts')
<script type="module">
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    replySent: @json(__('admin.support_tickets.reply_sent')),
    failedSendReply: @json(__('admin.support_tickets.failed_send_reply')),
    statusUpdated: @json(__('admin.support_tickets.status_updated')),
    failedUpdateStatus: @json(__('admin.support_tickets.failed_update_status')),
    priorityUpdated: @json(__('admin.support_tickets.priority_updated')),
    failedUpdatePriority: @json(__('admin.support_tickets.failed_update_priority')),
    failedAssign: @json(__('admin.support_tickets.failed_assign')),
    reassigned: @json(__('admin.support_tickets.reassigned')),
    failedReassign: @json(__('admin.support_tickets.failed_reassign')),
    unassigned: @json(__('admin.support_tickets.unassigned')),
    supportAgent: @json(__('admin.support_tickets.support_agent')),
    internalNoteBadge: @json(__('admin.support_tickets.internal_note_badge')),
    statusLabels: {
        open: @json(__('admin.support_tickets.open')),
        in_progress: @json(__('admin.support_tickets.in_progress')),
        waiting_customer: @json(__('admin.support_tickets.waiting')),
        resolved: @json(__('admin.support_tickets.resolved')),
        closed: @json(__('admin.support_tickets.closed')),
    },
});
</script>
@endpush

@section('title', $ticket->ticket_number . ' — ' . __('admin.support_tickets.ticket_case_title'))

@section('content')

    @php
        $priorityBadge = match ($ticket->priority) {
            'urgent' => 'bg-red-100 text-red-700 border border-red-200',
            'high' => 'bg-orange-100 text-orange-700',
            'normal' => 'bg-blue-100 text-blue-700',
            'low' => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
        $statusBadge = match ($ticket->status) {
            \App\Enums\SupportTicketStatus::Open => 'bg-yellow-100 text-yellow-700',
            \App\Enums\SupportTicketStatus::InProgress => 'bg-blue-100 text-blue-700',
            \App\Enums\SupportTicketStatus::WaitingCustomer => 'bg-indigo-100 text-indigo-700',
            \App\Enums\SupportTicketStatus::Resolved => 'bg-green-100 text-green-700',
            \App\Enums\SupportTicketStatus::Closed => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
        $requester = $ticket->requester_role === \App\Enums\SupportTicketRequesterRole::Seller
            ? $ticket->requesterVendor
            : $ticket->requesterCustomer;
        $requesterName = $requester->store_name ?? $requester->name ?? __('admin.support_tickets.unknown');
        $statusLabelKey = $ticket->status === \App\Enums\SupportTicketStatus::WaitingCustomer ? 'waiting' : $ticket->status->value;
        $requesterRoute = $ticket->requester_role === \App\Enums\SupportTicketRequesterRole::Seller
            ? route('admin.vendors.show', $ticket->requester_user_id)
            : route('admin.customers.show', $ticket->requester_user_id);
    @endphp

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.support-tickets.index') }}" class="hover:text-primary-600">{{ __('admin.support_tickets.title') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium font-mono">{{ $ticket->ticket_number }}</span>
    </nav>

    <div class="flex flex-col lg:flex-row gap-5 items-start">

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- LEFT: Conversation thread (7/12) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 flex flex-col gap-4">

            {{-- Ticket header --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex flex-wrap items-start gap-3 mb-2">
                    <span class="font-mono text-sm text-gray-500">{{ $ticket->ticket_number }}</span>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }} js-status-badge">
                        {{ __('admin.support_tickets.' . $statusLabelKey) }}
                    </span>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $priorityBadge }} js-priority-badge">
                        {{ __('admin.support_tickets.' . $ticket->priority) }}
                    </span>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                        {{ __('admin.support_tickets.category_' . $ticket->category) }}
                    </span>
                </div>
                <h1 class="text-lg font-semibold text-gray-900">{{ $ticket->subject }}</h1>
                @if($ticket->description)
                    <p class="mt-2 text-sm text-gray-600">{{ $ticket->description }}</p>
                @endif
            </div>

            {{-- Message thread --}}
            <div id="message-thread" class="space-y-3">
                @foreach($ticket->messages as $msg)
                    @php
                        $isAdmin = str_contains($msg->sender_type ?? '', 'Admin');
                        $isInternal = (bool) $msg->is_internal_note;
                        $isAi = (bool) $msg->is_ai_generated;

                        $bubbleClass = $isInternal
                            ? 'bg-yellow-50 border border-yellow-200'
                            : ($isAi
                                ? 'bg-purple-50 border border-purple-200'
                                : ($isAdmin
                                    ? 'bg-blue-50 border border-blue-200'
                                    : 'bg-gray-50 border border-gray-200'));

                        $alignClass = $isAdmin ? 'ml-8' : 'mr-8';
                    @endphp
                    <div class="message-bubble {{ $alignClass }}" data-message-id="{{ $msg->id }}">
                        <div class="rounded-xl p-4 {{ $bubbleClass }}">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-semibold text-gray-700">
                                    {{ $isAdmin ? __('admin.support_tickets.support_agent') : $requesterName }}
                                </span>
                                @if($isInternal)
                                    <span
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-200 text-yellow-800">{{ __('admin.support_tickets.internal_note_badge') }}</span>
                                @elseif($isAi)
                                    <span
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-200 text-purple-800">{{ __('admin.support_tickets.ai_badge') }}</span>
                                @endif
                                <span class="text-xs text-gray-400 ml-auto">
                                    {{ $msg->created_at ? \Carbon\Carbon::parse($msg->created_at)->format('M d, Y H:i') : '' }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $msg->message }}</div>
                        </div>
                    </div>
                @endforeach

                @if($ticket->messages->isEmpty())
                    <div class="text-center py-8 text-sm text-gray-400">{{ __('admin.support_tickets.no_messages') }}</div>
                @endif
            </div>

            {{-- Reply form --}}
            @if(!in_array($ticket->status, [\App\Enums\SupportTicketStatus::Resolved, \App\Enums\SupportTicketStatus::Closed], true))
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" id="reply-area">
                    <form id="reply-form" data-url="{{ route('admin.support-tickets.reply', $ticket->id) }}" novalidate>
                        @csrf

                        <div id="reply-form-bg" class="mb-3 rounded-lg border border-gray-200 p-0.5 transition-colors">
                            <textarea id="reply-message" name="message" rows="4" placeholder="{{ __('admin.support_tickets.write_reply_placeholder') }}"
                                class="w-full text-sm p-3 rounded-md resize-none focus:outline-none bg-transparent"
                                required></textarea>
                        </div>

                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div class="flex items-center gap-3">
                                {{-- Internal note toggle --}}
                                <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-gray-600">
                                    <input type="checkbox" id="is-internal-note" name="is_internal_note" value="1"
                                        class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400">
                                    {{ __('admin.support_tickets.internal_note_checkbox') }}
                                </label>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" name="close_after" value="0" id="btn-reply"
                                    class="btn btn-primary btn-sm">
                                    {{ __('admin.support_tickets.send_reply') }}
                                </button>
                                <button type="submit" name="close_after" value="1" id="btn-reply-close"
                                    class="btn btn-secondary btn-sm">
                                    {{ __('admin.support_tickets.send_and_close') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @else
                <div class="text-center text-sm text-gray-400 py-4">
                    {{ __('admin.support_tickets.ticket_status_reopen', ['status' => $ticket->status->value]) }}
                </div>
            @endif

        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT: Sidebar (5/12) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-80 shrink-0 flex flex-col gap-4">

            {{-- Ticket Info --}}
            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.support_tickets.ticket_info') }}</h3>
                <dl class="space-y-3 text-sm">

                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-xs text-gray-500 shrink-0">{{ __('admin.status') }}</dt>
                        <dd>
                            <select id="status-select"
                                data-url="{{ route('admin.support-tickets.update-status', $ticket->id) }}"
                                class="form-input text-xs py-1 pr-7">
                                @foreach(['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'] as $s)
                                    <option value="{{ $s }}" {{ $ticket->status->value === $s ? 'selected' : '' }}>
                                        {{ __('admin.support_tickets.' . (['waiting_customer' => 'waiting'][$s] ?? $s)) }}
                                    </option>
                                @endforeach
                            </select>
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-xs text-gray-500 shrink-0">{{ __('admin.support_tickets.priority') }}</dt>
                        <dd>
                            <select id="priority-select"
                                data-url="{{ route('admin.support-tickets.update-priority', $ticket->id) }}"
                                class="form-input text-xs py-1 pr-7">
                                @foreach(['low', 'normal', 'high', 'urgent'] as $p)
                                    <option value="{{ $p }}" {{ $ticket->priority === $p ? 'selected' : '' }}>
                                        {{ __('admin.support_tickets.' . $p) }}
                                    </option>
                                @endforeach
                            </select>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.support_tickets.category') }}</dt>
                        <dd>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                {{ __('admin.support_tickets.category_' . $ticket->category) }}
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.support_tickets.requester') }}</dt>
                        <dd class="flex items-center gap-1.5">
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                    {{ $ticket->requester_role === \App\Enums\SupportTicketRequesterRole::Seller ? 'bg-purple-100 text-purple-700' : 'bg-teal-100 text-teal-700' }}">
                                {{ $ticket->requester_role === \App\Enums\SupportTicketRequesterRole::Seller ? __('admin.support_tickets.vendor_seller') : __('admin.support_tickets.customer') }}
                            </span>
                            <a href="{{ $requesterRoute }}" class="text-sm text-primary-600 hover:underline truncate">
                                {{ $requesterName }}
                            </a>
                        </dd>
                    </div>

                    @if($ticket->relatedOrder)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.support_tickets.related_order') }}</dt>
                            <dd>
                                <a href="{{ route('admin.orders.show', $ticket->related_order_id) }}"
                                    class="text-sm text-primary-600 hover:underline font-mono">
                                    {{ $ticket->relatedOrder->order_number }}
                                </a>
                            </dd>
                        </div>
                    @endif

                    @if($ticket->relatedProduct)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.support_tickets.related_product') }}</dt>
                            <dd>
                                <a href="{{ route('admin.products.show', $ticket->related_product_id) }}"
                                    class="text-sm text-primary-600 hover:underline">
                                    {{ $ticket->relatedProduct->name_en }}
                                </a>
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.support_tickets.opened') }}</dt>
                        <dd class="text-sm text-gray-700">{{ $ticket->created_at->format('M d, Y H:i') }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.support_tickets.first_response') }}</dt>
                        <dd class="text-sm {{ $ticket->first_response_at ? 'text-gray-700' : 'text-orange-500' }}">
                            {{ $ticket->first_response_at
        ? \Carbon\Carbon::parse($ticket->first_response_at)->format('M d, Y H:i')
        : __('admin.support_tickets.not_yet_responded') }}
                        </dd>
                    </div>

                    @if($ticket->resolved_at)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.support_tickets.resolved') }}</dt>
                            <dd class="text-sm text-green-700">
                                {{ \Carbon\Carbon::parse($ticket->resolved_at)->format('M d, Y H:i') }}</dd>
                        </div>
                    @endif

                    @if($ticket->satisfaction_rating)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.support_tickets.satisfaction') }}</dt>
                            <dd class="flex items-center gap-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <span
                                        class="{{ $i <= $ticket->satisfaction_rating ? 'text-yellow-400' : 'text-gray-200' }}">★</span>
                                @endfor
                                @if($ticket->satisfaction_comment)
                                    <span
                                        class="text-xs text-gray-500 ml-1 italic">{{ Str::limit($ticket->satisfaction_comment, 40) }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif

                </dl>
            </x-card>

            {{-- Assigned to --}}
            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('admin.support_tickets.assigned_to_title') }}</h3>

                <p id="assignee-name" class="text-sm font-medium text-gray-800 mb-3">
                    {{ $ticket->assignedToAdmin?->name ?? __('admin.support_tickets.unassigned') }}
                </p>

                <div class="flex flex-col gap-2">
                    <button id="btn-assign-me" data-url="{{ route('admin.support-tickets.assign-me', $ticket->id) }}"
                        data-my-name="{{ $admin->name }}" class="btn btn-secondary btn-sm w-full">
                        {{ __('admin.support_tickets.assign_to_me') }}
                    </button>

                    <div class="flex gap-2">
                        <select id="reassign-select" class="form-input flex-1 text-xs py-1">
                            <option value="">{{ __('admin.support_tickets.reassign_to') }}</option>
                            @foreach($admins as $a)
                                <option value="{{ $a->id }}" {{ $ticket->assigned_to_admin_id === $a->id ? 'selected' : '' }}>
                                    {{ $a->name }}
                                </option>
                            @endforeach
                        </select>
                        <button id="btn-reassign" data-url="{{ route('admin.support-tickets.assign', $ticket->id) }}"
                            class="btn btn-primary btn-sm shrink-0">
                            {{ __('admin.support_tickets.save') }}
                        </button>
                    </div>
                </div>
            </x-card>

        </div>

    </div>

@endsection
