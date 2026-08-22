@extends('layouts.partner')

@section('title', __('partner.returns.title'))
@section('page-title', __('partner.returns.title'))

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('apply-date-filter')?.addEventListener('click', function () {
            const from = document.getElementById('filter-date-from').value;
            const to   = document.getElementById('filter-date-to').value;
            const url  = new URL(window.location.href);
            if (from) url.searchParams.set('date_from', from);
            else url.searchParams.delete('date_from');
            if (to) url.searchParams.set('date_to', to);
            else url.searchParams.delete('date_to');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
        document.getElementById('clear-date-filter')?.addEventListener('click', function () {
            const url = new URL(window.location.href);
            url.searchParams.delete('date_from');
            url.searchParams.delete('date_to');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    });
</script>
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
        'changed_mind'      => __('partner.returns.reason_changed_mind'),
        'wrong_item'        => __('partner.returns.reason_wrong_item'),
        'defective'         => __('partner.returns.reason_defective'),
        'damaged'           => __('partner.returns.reason_damaged'),
        'not_as_described'  => __('partner.returns.reason_not_as_described'),
        'size_issue'        => __('partner.returns.reason_size_issue'),
        'quality_issue'     => __('partner.returns.reason_quality_issue'),
        'arrived_late'      => __('partner.returns.reason_arrived_late'),
        'other'             => __('partner.returns.reason_other'),
    ];
    $typeMap = [
        'refund'       => __('partner.returns.type_refund'),
        'exchange'     => __('partner.returns.type_exchange'),
        'store_credit' => __('partner.returns.type_store_credit'),
    ];
    $tabStatuses = array_keys($statusMap);
    $currentStatus = request('status');
@endphp

{{-- Status tabs --}}
<div class="bg-white rounded-2xl border border-gray-200 mb-4">
    <div class="flex items-center overflow-x-auto">
        <a href="{{ route('partner.returns.index') }}" @class([
            'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
            'border-primary-500 text-primary-600' => !$currentStatus,
            'border-transparent text-gray-500 hover:text-gray-700' => $currentStatus,
        ])>{{ __('partner.returns.all') }} <span class="mr-1 text-xs text-gray-400">({{ $returns->total() }})</span></a>

        @foreach($tabStatuses as $st)
            <a href="{{ route('partner.returns.index', ['status' => $st, 'date_from' => request('date_from'), 'date_to' => request('date_to')]) }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                'border-primary-500 text-primary-600' => $currentStatus === $st,
                'border-transparent text-gray-500 hover:text-gray-700' => $currentStatus !== $st,
            ])>{{ $statusMap[$st][1] }}</a>
        @endforeach
    </div>
</div>

{{-- Date filter --}}
<div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('partner.returns.from_date') }}</label>
        <input type="date" id="filter-date-from" value="{{ request('date_from') }}"
            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400/40">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('partner.returns.to_date') }}</label>
        <input type="date" id="filter-date-to" value="{{ request('date_to') }}"
            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400/40">
    </div>
    <button id="apply-date-filter"
        class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">
        {{ __('partner.returns.apply') }}
    </button>
    <button id="clear-date-filter"
        class="border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">
        {{ __('partner.returns.reset') }}
    </button>
</div>

{{-- Table --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">{{ __('partner.returns.returns_list') }}</h2>
        <span class="text-xs text-gray-400">{{ __('partner.returns.return_count', ['count' => $returns->total()]) }}</span>
    </div>

    @if($returns->isEmpty())
        <div class="py-16 text-center">
            <div class="text-4xl mb-3">📦</div>
            <h3 class="font-semibold text-gray-800 mb-1">{{ __('partner.returns.no_returns_found') }}</h3>
            <p class="text-sm text-gray-400">{{ __('partner.returns.no_returns_hint') }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-xs text-gray-500 uppercase">
                        <th class="text-right py-3 px-5 font-medium">{{ __('partner.returns.return_number') }}</th>
                        <th class="text-right py-3 px-4 font-medium">{{ __('partner.returns.order_number') }}</th>
                        <th class="text-right py-3 px-4 font-medium">{{ __('partner.returns.customer') }}</th>
                        <th class="text-center py-3 px-4 font-medium">{{ __('partner.returns.reason_header') }}</th>
                        <th class="text-center py-3 px-4 font-medium">{{ __('partner.returns.type_header') }}</th>
                        <th class="text-center py-3 px-4 font-medium">{{ __('common.status') }}</th>
                        <th class="text-right py-3 px-4 font-medium">{{ __('common.date') }}</th>
                        <th class="py-3 px-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($returns as $ret)
                        @php
                            [$statusCls, $statusLabel] = $statusMap[$ret->status->value] ?? ['bg-gray-100 text-gray-500', $ret->status->value];
                            $orderMasked = $ret->order ? '****' . substr($ret->order->order_number, -4) : '—';
                            $customerName = trim(($ret->customer->first_name ?? '') . ' ' . (isset($ret->customer->last_name) ? strtoupper(substr($ret->customer->last_name, 0, 1)) . '.' : ''));
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-5">
                                <span class="font-mono text-xs font-medium text-gray-800">{{ $ret->return_number }}</span>
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-500 font-mono">{{ $orderMasked }}</td>
                            <td class="py-3 px-4 text-xs text-gray-700">{{ $customerName ?: '—' }}</td>
                            <td class="py-3 px-4 text-center text-xs text-gray-600">
                                {{ $reasonMap[$ret->reason->value] ?? $ret->reason->value }}
                            </td>
                            <td class="py-3 px-4 text-center text-xs text-gray-600">
                                {{ $typeMap[$ret->return_type->value] ?? $ret->return_type->value }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusCls }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-xs text-gray-400">
                                {{ $ret->created_at->format('Y/m/d') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('partner.returns.show', $ret->return_number) }}"
                                    class="text-xs text-primary-600 hover:text-primary-800 font-medium">
                                    {{ __('partner.returns.view') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($returns->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $returns->links() }}
            </div>
        @endif
    @endif
</div>

@endsection
