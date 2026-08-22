@extends('layouts.partner')

@section('title', __('partner.disputes.title', ['number' => $dispute->dispute_number]))
@section('page-title', __('partner.disputes.details_title'))

@push('scripts')
    @vite('resources/js/partner/disputes.js')
    <script>
        window.DISPUTE = {
            csrf: '{{ csrf_token() }}',
            replyUrl: '{{ route('partner.disputes.reply', $dispute->dispute_number) }}',
            evidenceUrl: '{{ route('partner.disputes.evidence', $dispute->dispute_number) }}',
            status: '{{ $dispute->status->value }}',
        };
    </script>
@endpush

@section('content')
    @php
        $statusLabels = [
            'open' => ['label' => __('partner.disputes.status.open'), 'color' => 'bg-blue-100 text-blue-700'],
            'seller_responded' => ['label' => __('partner.disputes.status.seller_responded'), 'color' => 'bg-indigo-100 text-indigo-700'],
            'under_review' => ['label' => __('partner.disputes.status.under_review'), 'color' => 'bg-yellow-100 text-yellow-700'],
            'escalated' => ['label' => __('partner.disputes.status.escalated'), 'color' => 'bg-orange-100 text-orange-700'],
            'resolved' => ['label' => __('partner.disputes.status.resolved'), 'color' => 'bg-green-100 text-green-700'],
            'closed' => ['label' => __('partner.disputes.status.closed'), 'color' => 'bg-gray-100 text-gray-500'],
        ];
        $reasonLabels = [
            'item_not_received' => __('partner.disputes.reasons.item_not_received'),
            'item_damaged' => __('partner.disputes.reasons.item_damaged'),
            'item_not_as_described' => __('partner.disputes.reasons.item_not_as_described'),
            'counterfeit' => __('partner.disputes.reasons.counterfeit'),
            'wrong_item' => __('partner.disputes.reasons.wrong_item'),
            'quality_issue' => __('partner.disputes.reasons.quality_issue'),
            'seller_unresponsive' => __('partner.disputes.reasons.seller_unresponsive'),
            'refund_not_received' => __('partner.disputes.reasons.refund_not_received'),
            'other' => __('partner.disputes.reasons.other'),
        ];
        $statusCfg = $statusLabels[$dispute->status->value] ?? ['label' => $dispute->status->value, 'color' => 'bg-gray-100 text-gray-600'];
        $isClosed = in_array($dispute->status->value, ['resolved', 'closed']);
    @endphp
    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Breadcrumb --}}
        <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('partner.support.tickets.index', ['tab' => 'disputes']) }}"
                class="hover:text-gray-700">{{ __('partner.disputes.breadcrumb') }}</a>
            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-gray-800 font-medium font-mono">{{ $dispute->dispute_number }}</span>
        </div>

        {{-- Dispute header card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-lg font-bold text-gray-900">
                        {{ __('partner.disputes.dispute_heading', ['reason' => $reasonLabels[$dispute->reason->value] ?? $dispute->reason->value]) }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                        <span class="font-mono">{{ $dispute->dispute_number }}</span>
                        @if ($dispute->order)
                            <span>·</span>
                            <span>{{ __('partner.disputes.order_label', ['number' => $dispute->order->order_number]) }}</span>
                        @endif
                        <span>·</span>
                        <span>{{ $dispute->created_at->format('d/m/Y H:i') }}</span>
                        @if ($dispute->resolved_at)
                            <span>·</span>
                            <span>{{ __('partner.disputes.resolved_on', ['date' => $dispute->resolved_at->format('d/m/Y')]) }}</span>
                        @endif
                    </div>

                    {{-- Description --}}
                    @if ($dispute->description)
                        <p class="mt-3 text-sm text-gray-600 leading-relaxed">{{ $dispute->description }}</p>
                    @endif
                </div>
                <span
                    class="flex-shrink-0 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusCfg['color'] }}">
                    {{ $statusCfg['label'] }}
                </span>
            </div>

            {{-- Resolution decision --}}
            @if ($dispute->resolution_decision)
                <div
                    class="mt-4 rounded-xl border {{ $dispute->resolution_decision === 'favor_seller' ? 'border-green-200 bg-green-50' : 'border-yellow-200 bg-yellow-50' }} px-4 py-3">
                    <p
                        class="text-xs font-semibold {{ $dispute->resolution_decision === 'favor_seller' ? 'text-green-700' : 'text-yellow-700' }} mb-1">
                        {{ __('partner.disputes.final_decision') }}
                    </p>
                    <p class="text-sm text-gray-700">
                        @if ($dispute->resolution_decision === 'favor_seller')
                            {{ __('partner.disputes.decision_favor_seller') }}
                        @elseif ($dispute->resolution_decision === 'favor_buyer')
                            {{ __('partner.disputes.decision_favor_buyer') }}
                        @else
                            {{ __('partner.disputes.decision_shared') }}
                        @endif
                    </p>
                    @if ($dispute->resolution_notes)
                        <p class="mt-1 text-xs text-gray-500">{{ $dispute->resolution_notes }}</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="lg:grid lg:grid-cols-3 lg:gap-4">
            {{-- ══ Left column: conversation ══════════════════════════════════════ --}}
            <div class="lg:col-span-2">

                {{-- Thread --}}
                <div class="space-y-3 mb-4" id="dispute-thread">
                    @foreach ($messages as $msg)
                                @php $isMine = $msg->sender_role === \App\Enums\DisputeMessageSenderRole::Seller; @endphp
                                <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} msg-row">
                                    <div class="max-w-[80%] sm:max-w-[75%]">
                                        <p class="mb-1 text-xs {{ $isMine ? 'text-end' : 'text-start' }} text-gray-400">
                                            {{ $isMine ? __('partner.disputes.you') : ($msg->sender_role === \App\Enums\DisputeMessageSenderRole::Admin ? __('partner.disputes.platform') : __('partner.disputes.buyer')) }}
                                            · {{ $msg->created_at->format('d/m H:i') }}
                                        </p>
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
                        <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center text-sm text-gray-400">
                            {{ __('partner.disputes.no_messages') }}
                        </div>
                    @endif
                </div>

                {{-- Reply form --}}
                @if (!$isClosed)
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
                        <p class="text-xs font-semibold text-gray-600 mb-2">{{ __('partner.disputes.reply_title') }}</p>
                        <form id="form-dispute-reply" novalidate>
                            <textarea id="dispute-reply-message" name="message" rows="3" placeholder="{{ __('partner.disputes.reply_placeholder') }}"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 resize-y mb-3"></textarea>
                            <div class="flex justify-end">
                                <button type="submit" id="btn-dispute-reply"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    {{ __('partner.disputes.send_reply') }}
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-500 text-center">
                        {{ __('partner.disputes.dispute_closed_notice', ['status' => $dispute->status->value === 'resolved' ? __('partner.disputes.status.resolved') : __('partner.disputes.status.closed')]) }}
                    </div>
                @endif
            </div>

            {{-- ══ Right column: evidence ══════════════════════════════════════════ --}}
            <div class="mt-4 lg:mt-0">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('partner.disputes.evidence_title') }}</h2>

                    {{-- Existing evidence --}}
                    <div id="evidence-list" class="space-y-2 mb-4">
                        @forelse ($evidence as $ev)
                            @php
                                $evFile = $ev->files->first();
                                $fileUrl = $evFile ? asset("storage/{$evFile->path}") : null;
                                $isImage = $evFile && in_array($evFile->extension, ['jpg', 'jpeg', 'png']);
                            @endphp
                            <div class="flex items-start gap-2 rounded-lg border border-gray-100 bg-gray-50 p-2">
                                {{-- Thumbnail --}}
                                <div
                                    class="flex-shrink-0 w-10 h-10 rounded overflow-hidden bg-gray-200 flex items-center justify-center">
                                    @if ($isImage && $fileUrl)
                                        <img src="{{ $fileUrl }}" alt="{{ __('partner.disputes.evidence_title') }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    @if ($ev->description)
                                        <p class="text-xs text-gray-700 truncate">{{ $ev->description }}</p>
                                    @endif
                                    @if ($fileUrl)
                                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener"
                                            class="text-xs text-primary-600 hover:underline">
                                            {{ $isImage ? __('partner.disputes.view_image') : __('partner.disputes.download_file') }}
                                        </a>
                                    @endif
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ \Carbon\Carbon::parse($ev->created_at)->format('d/m/Y') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 text-center py-4">{{ __('partner.disputes.no_evidence') }}</p>
                        @endforelse
                    </div>

                    {{-- Upload form (only when not closed) --}}
                    @if (!$isClosed)
                        <form id="form-evidence-upload" novalidate>
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('partner.disputes.upload_evidence_label') }}</label>
                                    <input type="file" id="evidence-file" name="file" accept=".pdf,.jpg,.jpeg,.png"
                                        class="block w-full text-xs text-gray-600 file:me-2 file:rounded file:border-0 file:bg-primary-50 file:px-2 file:py-1 file:text-xs file:font-medium file:text-primary-700 hover:file:bg-primary-100 cursor-pointer">
                                    <p class="mt-0.5 text-xs text-gray-400">{{ __('partner.disputes.evidence_hint') }}</p>
                                </div>
                                <div>
                                    <input type="text" id="evidence-description" name="description" maxlength="500"
                                        placeholder="{{ __('partner.disputes.evidence_description_placeholder') }}"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </div>
                                <button type="submit" id="btn-upload-evidence"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gray-700 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-800 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    {{ __('partner.disputes.upload_evidence') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection