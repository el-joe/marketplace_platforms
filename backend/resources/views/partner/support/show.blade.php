@extends('layouts.partner')

@section('title', __('partner.support.ticket_title', ['number' => $ticket->ticket_number]))
@section('page-title', __('partner.support.ticket_page_title'))

@push('scripts')
    @vite('resources/js/partner/support.js')
    <script>
        window.SUPPORT_TICKET = {
            csrf: '{{ csrf_token() }}',
            replyUrl: '{{ route('partner.support.tickets.reply', $ticket->ticket_number) }}',
            status: '{{ $ticket->status->value }}',
        };
    </script>
@endpush

@section('content')
    @php
        $statusLabels = [
            'open' => ['label' => __('partner.support.status_open'), 'color' => 'bg-blue-100 text-blue-700'],
            'in_progress' => ['label' => __('partner.support.status_in_progress'), 'color' => 'bg-yellow-100 text-yellow-700'],
            'waiting_customer' => ['label' => __('partner.support.status_waiting_customer'), 'color' => 'bg-amber-100 text-amber-700'],
            'resolved' => ['label' => __('partner.support.status_resolved'), 'color' => 'bg-green-100 text-green-700'],
            'closed' => ['label' => __('partner.support.status_closed'), 'color' => 'bg-gray-100 text-gray-500'],
        ];
        $statusCfg = $statusLabels[$ticket->status->value] ?? ['label' => $ticket->status->value, 'color' => 'bg-gray-100 text-gray-600'];
        $isClosed = in_array($ticket->status, [\App\Enums\SupportTicketStatus::Resolved, \App\Enums\SupportTicketStatus::Closed], true);
    @endphp
    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Breadcrumb --}}
        <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('partner.support.tickets.index') }}" class="hover:text-gray-700">{{ __('partner.support.title') }}</a>
            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-gray-800 font-medium font-mono">{{ $ticket->ticket_number }}</span>
        </div>

        {{-- Ticket header --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-lg font-bold text-gray-900 leading-tight">{{ $ticket->subject }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                        <span class="font-mono">{{ $ticket->ticket_number }}</span>
                        <span>·</span>
                        <span>{{ str_replace('_', ' ', $ticket->category) }}</span>
                        <span>·</span>
                        <span>{{ $ticket->created_at->format('d/m/Y H:i') }}</span>
                        @if ($ticket->resolved_at)
                            <span>·</span>
                            <span>{{ __('partner.support.resolved_on', ['date' => $ticket->resolved_at->format('d/m/Y')]) }}</span>
                        @endif
                    </div>
                </div>
                <span
                    class="flex-shrink-0 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusCfg['color'] }}">
                    {{ $statusCfg['label'] }}
                </span>
            </div>
        </div>

        {{-- Conversation thread --}}
        <div class="space-y-3 mb-4" id="thread">
            @foreach ($messages as $msg)
                @php $isMine = $msg->sender_type === \App\Models\VendorAdmin::class; @endphp
                <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} msg-row">
                    <div class="max-w-[80%] sm:max-w-[70%]">
                        {{-- Sender label --}}
                        <p class="mb-1 text-xs {{ $isMine ? 'text-end' : 'text-start' }} text-gray-400">
                            {{ $isMine ? __('partner.support.you') : __('partner.support.support_team') }}
                            · {{ $msg->created_at->format('d/m H:i') }}
                        </p>
                        {{-- Bubble --}}
                        <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm
                                {{ $isMine
                ? 'bg-primary-600 text-white rounded-tl-md'
                : 'bg-white border border-gray-200 text-gray-800 rounded-tr-md' }}">
                            {!! nl2br(e($msg->message)) !!}
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($messages->isEmpty())
                <div class="text-center py-10 text-sm text-gray-400">{{ __('partner.support.no_messages_yet') }}</div>
            @endif
        </div>

        {{-- Reply form --}}
        @if (!$isClosed)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
                <form id="form-reply" novalidate>
                    <textarea id="reply-message" name="message" rows="3" placeholder="{{ __('partner.support.reply_placeholder') }}"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 resize-y mb-3"></textarea>
                    <div class="flex justify-end">
                        <button type="submit" id="btn-reply"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            {{ __('partner.support.send') }}
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-500 text-center">
                {{ __('partner.support.ticket_resolved_notice', ['status' => $ticket->status === \App\Enums\SupportTicketStatus::Resolved ? __('partner.support.status_resolved') : __('partner.support.status_closed')]) }}
            </div>
        @endif

    </div>
@endsection