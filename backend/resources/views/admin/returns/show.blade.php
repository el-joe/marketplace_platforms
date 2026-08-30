@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/returns.js'])
@endpush

@section('title', $returnRequest->return_number . ' — ' . __('admin.returns_section.title'))

@section('content')

    @php
        $statusBadge = match ($returnRequest->status->value) {
            'requested' => 'bg-amber-100 text-amber-700',
            'approved' => 'bg-blue-100 text-blue-700',
            'awaiting_pickup' => 'bg-blue-100 text-blue-700',
            'in_transit' => 'bg-indigo-100 text-indigo-700',
            'received' => 'bg-purple-100 text-purple-700',
            'inspecting' => 'bg-purple-100 text-purple-700',
            'completed' => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700 border border-red-200',
            'cancelled' => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
        $reasonLabel = __('admin.returns_section.reason_' . $returnRequest->reason->value);
        $statusLabel = __('admin.returns_section.' . $returnRequest->status->value);
        $currency = $returnRequest->order->currency ?? 'USD';

        $timelineSteps = ['requested', 'approved', 'awaiting_pickup', 'in_transit', 'received', 'inspecting', 'completed'];
        $currentStepIndex = array_search($returnRequest->status->value, $timelineSteps, true);
        $isTerminalFail = in_array($returnRequest->status->value, ['rejected', 'cancelled'], true);
    @endphp

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.returns.index') }}" class="hover:text-primary-600">{{ __('admin.returns_section.title') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium font-mono">{{ $returnRequest->return_number }}</span>
    </nav>

    <div class="flex flex-col lg:flex-row gap-5 items-start">

        {{-- ═════════════════════════════════════════════════════════════════ --}}
        {{-- LEFT --}}
        {{-- ═════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 flex flex-col gap-4">

            {{-- Header --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex flex-wrap items-start gap-3 mb-2">
                    <span class="font-mono text-sm text-gray-500">{{ $returnRequest->return_number }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }} js-status-badge">
                        {{ $statusLabel }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                        {{ $reasonLabel }}
                    </span>
                </div>
                @if($returnRequest->reason_description)
                    <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap">{{ $returnRequest->reason_description }}</p>
                @endif
            </div>

            {{-- Status timeline --}}
            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.status') }}</h3>
                @if($isTerminalFail)
                    <div class="text-sm {{ $returnRequest->status->value === 'rejected' ? 'text-red-600' : 'text-gray-500' }}">
                        {{ $statusLabel }}
                    </div>
                @else
                    <div class="flex items-center overflow-x-auto">
                        @foreach($timelineSteps as $i => $step)
                            <div class="flex items-center shrink-0">
                                <div class="flex flex-col items-center gap-1">
                                    <div class="w-3 h-3 rounded-full {{ $i <= $currentStepIndex ? 'bg-primary-600' : 'bg-gray-200' }}"></div>
                                    <span class="text-[10px] whitespace-nowrap {{ $i <= $currentStepIndex ? 'text-gray-700 font-medium' : 'text-gray-400' }}">
                                        {{ __('admin.returns_section.' . $step) }}
                                    </span>
                                </div>
                                @if(!$loop->last)
                                    <div class="w-10 h-0.5 mx-1 {{ $i < $currentStepIndex ? 'bg-primary-600' : 'bg-gray-200' }}"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Items --}}
            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('admin.returns_section.items') }}</h3>
                <div class="divide-y divide-gray-100">
                    @foreach($returnRequest->items as $item)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-800">
                                    {{ $item->orderItem?->product_snapshot['title'] ?? $item->orderItem?->sku ?? '—' }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ __('common.quantity') ?? 'Qty' }}: {{ $item->quantity }}
                                    @if($item->condition_received)
                                        · {{ $item->condition_received->value }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                    @if($returnRequest->items->isEmpty())
                        <div class="text-center py-6 text-sm text-gray-400">—</div>
                    @endif
                </div>
            </x-card>

            {{-- Evidence --}}
            @if($returnRequest->files->isNotEmpty())
                <x-card>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('admin.returns_section.evidence') }}</h3>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                        @foreach($returnRequest->files as $file)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($file->path ?? $file->file_path ?? '') }}" target="_blank" class="block rounded-lg overflow-hidden border border-gray-200">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($file->path ?? $file->file_path ?? '') }}" class="w-full h-24 object-cover" alt="">
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- Action cards --}}
            @if($returnRequest->status->value === 'requested')
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">{{ __('admin.returns_section.action_approve') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('admin.returns_section.action_approve_desc') }}</p>
                    <div class="flex gap-2">
                        <button id="btn-approve" data-url="{{ route('admin.returns.approve', $returnRequest->id) }}"
                            class="btn btn-primary btn-sm">{{ __('admin.returns_section.action_approve') }}</button>
                    </div>
                </x-card>

                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">{{ __('admin.returns_section.action_reject') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('admin.returns_section.action_reject_desc') }}</p>
                    <form id="reject-form" data-url="{{ route('admin.returns.reject', $returnRequest->id) }}" class="flex flex-col gap-3" novalidate>
                        @csrf
                        <textarea name="rejection_reason" rows="2" required class="form-input w-full text-sm"
                            placeholder="{{ __('admin.returns_section.rejection_reason') }}"></textarea>
                        <button type="submit" id="btn-reject" class="btn btn-secondary btn-sm self-start">{{ __('admin.returns_section.action_reject') }}</button>
                    </form>
                </x-card>
            @elseif($returnRequest->status->value === 'approved')
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">{{ __('admin.returns_section.action_schedule_pickup') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('admin.returns_section.action_schedule_pickup_desc') }}</p>
                    <form id="schedule-pickup-form" data-url="{{ route('admin.returns.schedule-pickup', $returnRequest->id) }}" class="flex items-end gap-3" novalidate>
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.returns_section.scheduled_date') }}</label>
                            <input type="date" name="scheduled_date" class="form-input text-sm">
                        </div>
                        <button type="submit" id="btn-schedule-pickup" class="btn btn-primary btn-sm">{{ __('admin.returns_section.save') }}</button>
                    </form>
                </x-card>
            @elseif(in_array($returnRequest->status->value, ['awaiting_pickup', 'in_transit'], true))
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">{{ __('admin.returns_section.action_mark_received') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('admin.returns_section.action_mark_received_desc') }}</p>
                    <button id="btn-mark-received" data-url="{{ route('admin.returns.mark-received', $returnRequest->id) }}"
                        class="btn btn-primary btn-sm">{{ __('admin.returns_section.action_mark_received') }}</button>
                </x-card>
            @elseif($returnRequest->status->value === 'received')
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">{{ __('admin.returns_section.action_inspect') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('admin.returns_section.action_inspect_desc') }}</p>
                    <form id="inspect-form" data-url="{{ route('admin.returns.inspect', $returnRequest->id) }}"
                        class="grid grid-cols-1 md:grid-cols-2 gap-3" novalidate>
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.returns_section.inspection_outcome') }}</label>
                            <select name="inspection_outcome" required class="form-input w-full text-sm">
                                <option value="">{{ __('admin.returns_section.select_placeholder') }}</option>
                                <option value="good">{{ __('admin.returns_section.outcome_good') }}</option>
                                <option value="damaged_by_customer">{{ __('admin.returns_section.outcome_damaged_by_customer') }}</option>
                                <option value="damaged_in_transit">{{ __('admin.returns_section.outcome_damaged_in_transit') }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.returns_section.inspection_notes') }}</label>
                            <textarea name="inspection_notes" rows="3" class="form-input w-full text-sm"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" id="btn-inspect" class="btn btn-primary btn-sm">{{ __('admin.returns_section.save') }}</button>
                        </div>
                    </form>
                </x-card>
            @endif

        </div>

        {{-- ═════════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT --}}
        {{-- ═════════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-80 shrink-0 flex flex-col gap-4">

            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.returns_section.return_info') }}</h3>
                <dl class="space-y-3 text-sm">

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.customer') }}</dt>
                        <dd>
                            <a href="{{ route('admin.customers.show', $returnRequest->customer_id) }}" class="text-sm text-primary-600 hover:underline">
                                {{ $returnRequest->customer->name ?? '—' }}
                            </a>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.vendor') }}</dt>
                        <dd>
                            <a href="{{ route('admin.vendors.show', $returnRequest->vendor_id) }}" class="text-sm text-primary-600 hover:underline">
                                {{ $returnRequest->vendor->store_name ?? '—' }}
                            </a>
                        </dd>
                    </div>

                    @if($returnRequest->order)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.order') }}</dt>
                            <dd>
                                <a href="{{ route('admin.orders.show', $returnRequest->order_id) }}" class="text-sm text-primary-600 hover:underline font-mono">
                                    {{ $returnRequest->order->order_number }}
                                </a>
                            </dd>
                        </div>
                    @endif

                    @if($returnRequest->subOrder)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.sub_order') }}</dt>
                            <dd class="text-sm font-mono text-gray-700">{{ $returnRequest->subOrder->sub_order_number }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.return_type') }}</dt>
                        <dd class="text-sm text-gray-700">{{ $returnRequest->return_type->value }}</dd>
                    </div>

                    @if($returnRequest->pickup_scheduled_at)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.pickup_scheduled_at') }}</dt>
                            <dd class="text-sm text-gray-700">{{ \Carbon\Carbon::parse($returnRequest->pickup_scheduled_at)->format('M d, Y') }}</dd>
                        </div>
                    @endif

                    @if($returnRequest->received_at_warehouse_at)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.received_at') }}</dt>
                            <dd class="text-sm text-gray-700">{{ \Carbon\Carbon::parse($returnRequest->received_at_warehouse_at)->format('M d, Y H:i') }}</dd>
                        </div>
                    @endif

                    @if($returnRequest->inspection_result)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.inspection_result') }}</dt>
                            <dd class="text-sm text-gray-700">{{ $returnRequest->inspection_result->value }}</dd>
                        </div>
                    @endif

                    @if($returnRequest->inspection_notes)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.inspection_notes') }}</dt>
                            <dd class="text-sm text-gray-700 whitespace-pre-wrap">{{ $returnRequest->inspection_notes }}</dd>
                        </div>
                    @endif

                    @if($returnRequest->liability)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.liability') }}</dt>
                            <dd class="text-sm text-gray-700">{{ $returnRequest->liability->value }}</dd>
                        </div>
                    @endif

                    @if($returnRequest->rejection_reason)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.rejection_reason') }}</dt>
                            <dd class="text-sm text-gray-700 whitespace-pre-wrap">{{ $returnRequest->rejection_reason }}</dd>
                        </div>
                    @endif

                    @if($returnRequest->reviewedByAdmin)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.reviewed_by') }}</dt>
                            <dd class="text-sm text-gray-700">{{ $returnRequest->reviewedByAdmin->name }}</dd>
                        </div>
                    @endif

                    @if($returnRequest->refund)
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.refund') }}</dt>
                            <dd class="text-sm text-gray-700">{{ number_format($returnRequest->refund->amount, 2) }} {{ $currency }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-gray-500 mb-0.5">{{ __('admin.returns_section.created_at') }}</dt>
                        <dd class="text-sm text-gray-700">{{ $returnRequest->created_at->format('M d, Y H:i') }}</dd>
                    </div>

                </dl>
            </x-card>

        </div>

    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            actionFailed: @json(__('admin.returns_section.action_failed')),
            approvedMessage: @json(__('admin.returns_section.approved_message')),
            rejectedMessage: @json(__('admin.returns_section.rejected_message')),
            pickupScheduledMessage: @json(__('admin.returns_section.pickup_scheduled_message')),
            receivedMessage: @json(__('admin.returns_section.received_message')),
            inspectedMessage: @json(__('admin.returns_section.inspected_message')),
            statusLabels: {
                requested: @json(__('admin.returns_section.requested')),
                approved: @json(__('admin.returns_section.approved')),
                awaiting_pickup: @json(__('admin.returns_section.awaiting_pickup')),
                in_transit: @json(__('admin.returns_section.in_transit')),
                received: @json(__('admin.returns_section.received')),
                inspecting: @json(__('admin.returns_section.inspecting')),
                completed: @json(__('admin.returns_section.completed')),
                rejected: @json(__('admin.returns_section.rejected')),
                cancelled: @json(__('admin.returns_section.cancelled')),
            },
        });
    </script>
@endpush
