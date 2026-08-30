@extends('layouts.partner')

@section('title', __('partner.returns.return_title', ['number' => $return->return_number]))
@section('page-title', __('partner.returns.return_details'))

@push('scripts')
<script>
    window.RETURN_CONFIG = {
        csrf: '{{ csrf_token() }}',
        messagesUrl: '/api/vendor/v1/returns/{{ $return->return_number }}/messages',
        status: '{{ $return->status->value }}',
    };
</script>
@vite('resources/js/partner/returns.js')
@endpush

@section('content')

@php
    $statusMap = [
        'requested'        => ['bg-yellow-100 text-yellow-700',  __('partner.returns.status_requested')],
        'approved'         => ['bg-blue-100 text-blue-700',      __('partner.returns.status_approved')],
        'rejected'         => ['bg-red-100 text-red-700',        __('partner.returns.status_rejected')],
        'awaiting_pickup'  => ['bg-amber-100 text-amber-700',    __('partner.returns.status_awaiting_pickup')],
        'in_transit'       => ['bg-indigo-100 text-indigo-700',  __('partner.returns.status_in_transit')],
        'received'         => ['bg-cyan-100 text-cyan-700',      __('partner.returns.status_received')],
        'inspecting'       => ['bg-purple-100 text-purple-700',  __('partner.returns.status_inspecting')],
        'completed'        => ['bg-green-100 text-green-700',    __('partner.returns.status_completed')],
        'cancelled'        => ['bg-gray-100 text-gray-500',      __('partner.returns.status_cancelled')],
    ];
    $reasonMap = [
        'changed_mind'     => __('partner.returns.reason_changed_mind'),
        'wrong_item'       => __('partner.returns.reason_wrong_item'),
        'defective'        => __('partner.returns.reason_defective'),
        'damaged'          => __('partner.returns.reason_damaged'),
        'not_as_described' => __('partner.returns.reason_not_as_described'),
        'size_issue'       => __('partner.returns.reason_size_issue'),
        'quality_issue'    => __('partner.returns.reason_quality_issue'),
        'arrived_late'     => __('partner.returns.reason_arrived_late'),
        'other'            => __('partner.returns.reason_other'),
    ];
    $typeMap = [
        'refund'       => __('partner.returns.type_refund_amount'),
        'exchange'     => __('partner.returns.type_exchange'),
        'store_credit' => __('partner.returns.type_store_credit'),
    ];
    $inspectionMap = [
        'good'         => ['bg-green-100 text-green-700',   __('partner.returns.inspection_good')],
        'damaged'      => ['bg-red-100 text-red-700',       __('partner.returns.inspection_damaged')],
        'missing'      => ['bg-orange-100 text-orange-700', __('partner.returns.inspection_missing')],
        'counterfeit'  => ['bg-red-100 text-red-800',       __('partner.returns.inspection_counterfeit')],
    ];
    $liabilityMap = [
        'customer'  => __('partner.returns.liability_customer'),
        'seller'    => __('partner.returns.liability_seller'),
        'platform'  => __('partner.returns.liability_platform'),
        'carrier'   => __('partner.returns.liability_carrier'),
    ];
    $isFinal       = in_array($return->status, [\App\Enums\ReturnRequestStatus::Completed, \App\Enums\ReturnRequestStatus::Cancelled], true);
    $hasInspection = in_array($return->status, [\App\Enums\ReturnRequestStatus::Inspecting, \App\Enums\ReturnRequestStatus::Completed, \App\Enums\ReturnRequestStatus::Cancelled], true);
    $orderMasked   = $return->order ? '****' . substr($return->order->order_number, -4) : null;
    $customerName  = trim(($return->customer->first_name ?? '') . ' ' . (isset($return->customer->last_name) ? strtoupper(substr($return->customer->last_name, 0, 1)) . '.' : ''));
    [$statusCls, $statusLabel] = $statusMap[$return->status->value] ?? ['bg-gray-100 text-gray-500', $return->status->value];
@endphp

@if($return->status === \App\Enums\ReturnRequestStatus::Requested && auth('vendor')->user()->can('returns.process'))
    @if($errors->has('status'))
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ $errors->first('status') }}
        </div>
    @endif
    <div class="mb-4 bg-white rounded-2xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('partner.returns.review_this_request') }}</h2>
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('partner.returns.approve', $return->return_number) }}">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition-colors">
                    ✓ {{ __('partner.returns.approve_return') }}
                </button>
            </form>
            <button type="button" onclick="document.getElementById('reject-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-2 rounded-lg bg-white border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 transition-colors">
                ✗ {{ __('partner.returns.reject_return') }}
            </button>
        </div>
    </div>

    {{-- Reject modal --}}
    <div id="reject-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div class="bg-white rounded-2xl w-full max-w-md p-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('partner.returns.reject_return') }}</h3>
            <form method="POST" action="{{ route('partner.returns.reject', $return->return_number) }}">
                @csrf
                <textarea name="rejection_reason" rows="4" required maxlength="500"
                    placeholder="{{ __('partner.returns.rejection_reason') }}"
                    class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 resize-y mb-3">{{ old('rejection_reason') }}</textarea>
                @error('rejection_reason')
                    <p class="text-xs text-red-600 mb-3">{{ $message }}</p>
                @enderror
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                        {{ __('partner.returns.cancel') }}
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        {{ __('partner.returns.reject_return') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Breadcrumb --}}
<div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
    <a href="{{ route('partner.returns.index') }}" class="hover:text-gray-700">{{ __('partner.returns.title') }}</a>
    <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="font-mono text-gray-800 font-medium">{{ $return->return_number }}</span>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    {{-- LEFT COLUMN: detail cards --}}
    <div class="xl:col-span-2 space-y-4">

        {{-- Header --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <p class="text-xs text-gray-400 mb-1">{{ __('partner.returns.return_number_label') }}</p>
                    <h1 class="text-lg font-bold font-mono text-gray-900">{{ $return->return_number }}</h1>
                    <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500">
                        @if($orderMasked)
                            <span>{{ __('partner.returns.order_label') }} <span class="font-mono">{{ $orderMasked }}</span></span>
                            <span>·</span>
                        @endif
                        @if($return->subOrder)
                            <span>{{ __('partner.returns.sub_order_label') }} <span class="font-mono">{{ $return->subOrder->sub_order_number }}</span></span>
                            <span>·</span>
                        @endif
                        <span>{{ $return->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
                <span class="flex-shrink-0 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusCls }}">
                    {{ $statusLabel }}
                </span>
            </div>
        </div>

        {{-- Return details --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('partner.returns.return_details_title') }}</h2>
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.customer') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $customerName ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.return_reason') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $reasonMap[$return->reason->value] ?? $return->reason->value }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.type_header') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $typeMap[$return->return_type->value] ?? $return->return_type->value }}</dd>
                </div>
                @if($return->pickup_scheduled_at)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.pickup_date') }}</dt>
                        <dd class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($return->pickup_scheduled_at)->format('d/m/Y') }}</dd>
                    </div>
                @endif
                @if($return->received_at_warehouse_at)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.received_at_warehouse_date') }}</dt>
                        <dd class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($return->received_at_warehouse_at)->format('d/m/Y') }}</dd>
                    </div>
                @endif
                @if($return->rejection_reason)
                    <div class="col-span-2 sm:col-span-3">
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.rejection_reason') }}</dt>
                        <dd class="font-medium text-red-700">{{ $return->rejection_reason }}</dd>
                    </div>
                @endif
                @if($return->reason_description)
                    <div class="col-span-2 sm:col-span-3">
                        <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.reason_description') }}</dt>
                        <dd class="text-gray-700">{{ $return->reason_description }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Inspection result (shown only once status >= inspecting) --}}
        @if($hasInspection && $return->inspection_result)
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('partner.returns.inspection_result') }}</h2>
                @php [$insCls, $insLabel] = $inspectionMap[$return->inspection_result->value] ?? ['bg-gray-100 text-gray-600', $return->inspection_result->value]; @endphp
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $insCls }}">
                        {{ $insLabel }}
                    </span>
                </div>
                @if($return->inspection_notes)
                    <p class="text-sm text-gray-700">{{ $return->inspection_notes }}</p>
                @endif
            </div>
        @endif

        {{-- Liability + refund (shown only when final) --}}
        @if($isFinal && ($return->liability || $return->refund_amount))
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('partner.returns.final_decision') }}</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    @if($return->liability)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.liability') }}</dt>
                            <dd class="font-medium text-gray-800">{{ $liabilityMap[$return->liability->value] ?? $return->liability->value }}</dd>
                        </div>
                    @endif
                    @if($return->refund_amount)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('partner.returns.refund_amount') }}</dt>
                            <dd class="font-bold text-green-700">{{ number_format($return->refund_amount, 2) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        {{-- Items --}}
        @if($return->items->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">{{ __('partner.returns.returned_products') }}</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($return->items as $item)
                        @php
                            $snapshot = $item->orderItem?->product_snapshot ?? [];
                            $name  = $snapshot['name'] ?? $snapshot['title'] ?? __('partner.returns.product_fallback');
                            $image = $snapshot['image'] ?? null;
                            $condMap = ['new' => __('partner.returns.condition_new'), 'opened' => __('partner.returns.condition_opened'), 'used' => __('partner.returns.condition_used'), 'damaged' => __('partner.returns.condition_damaged')];
                        @endphp
                        <div class="flex items-center gap-4 px-5 py-3">
                            @if($image)
                                <img src="{{ $image }}" alt="{{ $name }}" class="w-12 h-12 rounded-lg object-cover border border-gray-100 flex-shrink-0">
                            @else
                                <div class="w-12 h-12 rounded-lg bg-gray-100 flex-shrink-0 flex items-center justify-center text-gray-300 text-xl">📦</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $name }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ __('partner.returns.quantity') }}: {{ $item->quantity }}</p>
                            </div>
                            @if($item->condition_received)
                                <span class="flex-shrink-0 text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                    {{ $condMap[$item->condition_received->value] ?? $item->condition_received->value }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    {{-- RIGHT COLUMN: message channel --}}
    <div class="xl:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-200 flex flex-col" style="min-height: 520px;">

            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('partner.returns.admin_correspondence') }}</h2>
                <p class="text-xs text-gray-400 mt-0.5">{{ __('partner.returns.admin_correspondence_hint') }}</p>
            </div>

            {{-- Thread --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3" id="thread" style="max-height: 400px;">
                @forelse($return->messages as $msg)
                    @php $isMine = $msg->sender_role === \App\Enums\DisputeMessageSenderRole::Seller; @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%]">
                            <p class="text-xs mb-1 {{ $isMine ? 'text-end' : 'text-start' }} text-gray-400">
                                {{ $isMine ? __('partner.returns.you') : ($msg->sender_role === \App\Enums\DisputeMessageSenderRole::Customer ? __('partner.returns.customer_role') : __('partner.returns.admin_role')) }}
                                · {{ \Carbon\Carbon::parse($msg->created_at)->format('d/m H:i') }}
                            </p>
                            <div @class([
                                'rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm',
                                'bg-primary-600 text-white rounded-tl-md' => $isMine,
                                'bg-gray-100 text-gray-800 rounded-tr-md' => !$isMine,
                            ])>
                                {!! nl2br(e($msg->message)) !!}
                                @if($msg->attachments->isNotEmpty())
                                    <div class="mt-2 space-y-1">
                                        @foreach($msg->attachments as $att)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::temporaryUrl($att->file_path, now()->addMinutes(30)) }}"
                                                target="_blank"
                                                class="flex items-center gap-1.5 text-xs underline {{ $isMine ? 'text-blue-100' : 'text-primary-600' }}">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                </svg>
                                                {{ __('partner.returns.attachment') }} {{ $loop->iteration }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-xs text-gray-400" id="empty-thread">
                        {{ __('partner.returns.no_messages_yet') }}
                    </div>
                @endforelse
            </div>

            {{-- Reply form --}}
            <div class="border-t border-gray-100 p-4">
                <form id="form-message" novalidate>
                    @csrf
                    <textarea id="msg-text" name="message" rows="3"
                        placeholder="{{ __('partner.returns.message_placeholder') }}"
                        class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 resize-y mb-2"></textarea>

                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">{{ __('partner.returns.attachments_optional') }}</label>
                        <input type="file" id="msg-attachments" name="attachments[]" multiple
                            accept="image/*,.pdf,video/mp4,video/quicktime"
                            class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer">
                    </div>

                    <div class="flex items-center justify-between">
                        <span id="msg-status" class="text-xs text-gray-400"></span>
                        <button type="submit" id="btn-send"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            {{ __('partner.returns.send') }}
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</div>

@endsection
