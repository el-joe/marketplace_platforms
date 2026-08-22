@extends('layouts.partner')

@section('title', __('partner.support.page_title'))
@section('page-title', __('partner.support.title'))

@section('content')
    @php
        $statusLabels = [
            'open' => ['label' => __('partner.support.status_open'), 'color' => 'bg-blue-100 text-blue-700'],
            'in_progress' => ['label' => __('partner.support.status_in_progress'), 'color' => 'bg-yellow-100 text-yellow-700'],
            'waiting_customer' => ['label' => __('partner.support.status_waiting_customer'), 'color' => 'bg-amber-100 text-amber-700'],
            'resolved' => ['label' => __('partner.support.status_resolved'), 'color' => 'bg-green-100 text-green-700'],
            'closed' => ['label' => __('partner.support.status_closed'), 'color' => 'bg-gray-100 text-gray-500'],
        ];
        $priorityLabels = [
            'low' => ['label' => __('partner.support.priority_low'), 'color' => 'bg-gray-100 text-gray-600'],
            'normal' => ['label' => __('partner.support.priority_normal'), 'color' => 'bg-blue-100 text-blue-600'],
            'high' => ['label' => __('partner.support.priority_high'), 'color' => 'bg-orange-100 text-orange-700'],
            'urgent' => ['label' => __('partner.support.priority_urgent'), 'color' => 'bg-red-100 text-red-700'],
        ];
        $disputeStatusLabels = [
            'open' => ['label' => __('partner.support.dispute_status_open'), 'color' => 'bg-blue-100 text-blue-700'],
            'seller_responded' => ['label' => __('partner.support.dispute_status_seller_responded'), 'color' => 'bg-indigo-100 text-indigo-700'],
            'under_review' => ['label' => __('partner.support.dispute_status_under_review'), 'color' => 'bg-yellow-100 text-yellow-700'],
            'escalated' => ['label' => __('partner.support.dispute_status_escalated'), 'color' => 'bg-orange-100 text-orange-700'],
            'resolved' => ['label' => __('partner.support.dispute_status_resolved'), 'color' => 'bg-green-100 text-green-700'],
            'closed' => ['label' => __('partner.support.dispute_status_closed'), 'color' => 'bg-gray-100 text-gray-500'],
        ];
        $disputeReasonLabels = [
            'item_not_received' => __('partner.support.reason_item_not_received'),
            'item_damaged' => __('partner.support.reason_item_damaged'),
            'item_not_as_described' => __('partner.support.reason_item_not_as_described'),
            'counterfeit' => __('partner.support.reason_counterfeit'),
            'wrong_item' => __('partner.support.reason_wrong_item'),
            'quality_issue' => __('partner.support.reason_quality_issue'),
            'seller_unresponsive' => __('partner.support.reason_seller_unresponsive'),
            'refund_not_received' => __('partner.support.reason_refund_not_received'),
            'other' => __('partner.support.reason_other'),
        ];
    @endphp
    <div class="px-4 py-6 sm:px-6 lg:px-8"
        x-data="{ tab: '{{ request('tab', 'tickets') }}', ticketFilter: 'all', disputeFilter: 'all' }">

        {{-- Page header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.support.page_title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('partner.support.page_subtitle') }}</p>
            </div>
            <a href="{{ route('partner.support.tickets.create') }}"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('partner.support.new_ticket') }}
            </a>
        </div>

        {{-- Tabs --}}
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex gap-x-6">
                <button type="button" @click="tab = 'tickets'" :class="tab === 'tickets'
                        ? 'border-primary-600 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    {{ __('partner.support.support_tickets_tab') }}
                    @if ($tickets->where('status', \App\Enums\SupportTicketStatus::WaitingCustomer)->count() > 0)
                        <span
                            class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                            {{ $tickets->where('status', \App\Enums\SupportTicketStatus::WaitingCustomer)->count() }}
                        </span>
                    @endif
                </button>
                <button type="button" @click="tab = 'disputes'" :class="tab === 'disputes'
                        ? 'border-primary-600 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    {{ __('partner.support.disputes_tab') }}
                    @if ($disputes->whereIn('status', ['open', 'under_review', 'escalated'])->count() > 0)
                        <span
                            class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                            {{ $disputes->whereIn('status', ['open', 'under_review', 'escalated'])->count() }}
                        </span>
                    @endif
                </button>
            </nav>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- Tab: Support Tickets --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'tickets'" x-cloak>

            {{-- Status filter pills --}}
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach (['all' => __('partner.support.all'), 'open' => __('partner.support.status_open'), 'waiting_customer' => __('partner.support.status_waiting_customer'), 'in_progress' => __('partner.support.status_in_progress'), 'resolved' => __('partner.support.status_resolved'), 'closed' => __('partner.support.status_closed')] as $val => $lbl)
                    <button type="button" @click="ticketFilter = '{{ $val }}'" :class="ticketFilter === '{{ $val }}'
                                ? 'bg-primary-600 text-white'
                                : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-full border border-gray-200 px-3 py-1 text-xs font-medium transition-colors">
                        {{ $lbl }}
                    </button>
                @endforeach
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('partner.support.ticket_number') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('partner.support.subject') }}</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                                {{ __('partner.support.category') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('common.status') }}</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                                {{ __('partner.support.priority') }}</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                                {{ __('common.date') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tickets as $ticket)
                            @php
                                $statusCfg = $statusLabels[$ticket->status->value] ?? ['label' => $ticket->status->value, 'color' => 'bg-gray-100 text-gray-600'];
                                $priorityCfg = $priorityLabels[$ticket->priority] ?? ['label' => $ticket->priority, 'color' => 'bg-gray-100 text-gray-600'];
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors"
                                x-show="ticketFilter === 'all' || ticketFilter === '{{ $ticket->status->value }}'" x-cloak>
                                <td class="px-5 py-4">
                                    <span
                                        class="font-mono text-xs font-semibold text-gray-700">{{ $ticket->ticket_number }}</span>
                                </td>
                                <td class="px-5 py-4 max-w-xs">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $ticket->subject }}</p>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell">
                                    <span class="text-xs text-gray-500">{{ str_replace('_', ' ', $ticket->category) }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusCfg['color'] }}">
                                        {{ $statusCfg['label'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 hidden sm:table-cell">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $priorityCfg['color'] }}">
                                        {{ $priorityCfg['label'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell">
                                    <span class="text-xs text-gray-400">{{ $ticket->created_at->format('d/m/Y') }}</span>
                                </td>
                                <td class="px-5 py-4 text-left">
                                    <a href="{{ route('partner.support.tickets.show', $ticket->ticket_number) }}"
                                        class="text-xs font-medium text-primary-600 hover:text-primary-800">
                                        {{ __('partner.support.view') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">
                                    {{ __('partner.support.no_tickets_yet') }}
                                    <a href="{{ route('partner.support.tickets.create') }}"
                                        class="text-primary-600 hover:underline">{{ __('partner.support.open_new_ticket_link') }}</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- Tab: Disputes --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'disputes'" x-cloak>

            {{-- Status filter pills --}}
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach (['all' => __('partner.support.all'), 'open' => __('partner.support.dispute_status_open'), 'seller_responded' => __('partner.support.dispute_status_seller_responded'), 'under_review' => __('partner.support.dispute_status_under_review'), 'escalated' => __('partner.support.dispute_status_escalated'), 'resolved' => __('partner.support.dispute_status_resolved'), 'closed' => __('partner.support.dispute_status_closed')] as $val => $lbl)
                    <button type="button" @click="disputeFilter = '{{ $val }}'" :class="disputeFilter === '{{ $val }}'
                                ? 'bg-primary-600 text-white'
                                : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-full border border-gray-200 px-3 py-1 text-xs font-medium transition-colors">
                        {{ $lbl }}
                    </button>
                @endforeach
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('partner.support.dispute_number') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('partner.support.reason') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('common.status') }}</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                                {{ __('common.date') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($disputes as $dispute)
                            @php $dStatusCfg = $disputeStatusLabels[$dispute->status->value] ?? ['label' => $dispute->status->value, 'color' => 'bg-gray-100 text-gray-600']; @endphp
                            <tr class="hover:bg-gray-50 transition-colors"
                                x-show="disputeFilter === 'all' || disputeFilter === '{{ $dispute->status->value }}'" x-cloak>
                                <td class="px-5 py-4">
                                    <span
                                        class="font-mono text-xs font-semibold text-gray-700">{{ $dispute->dispute_number }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="text-sm text-gray-700">{{ $disputeReasonLabels[$dispute->reason->value] ?? $dispute->reason->value }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $dStatusCfg['color'] }}">
                                        {{ $dStatusCfg['label'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 hidden sm:table-cell">
                                    <span class="text-xs text-gray-400">{{ $dispute->created_at->format('d/m/Y') }}</span>
                                </td>
                                <td class="px-5 py-4 text-left">
                                    <a href="{{ route('partner.disputes.show', $dispute->dispute_number) }}"
                                        class="text-xs font-medium text-primary-600 hover:text-primary-800">
                                        {{ __('partner.support.view') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">
                                    {{ __('partner.support.no_disputes') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection