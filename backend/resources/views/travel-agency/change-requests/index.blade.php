@extends('layouts.travel-agency')

@section('title', __('travel.change_requests.title'))
@section('page-title', __('travel.change_requests.title'))

@push('scripts')
    <script>
        window.CHANGE_REQUESTS = {
            csrf: '{{ csrf_token() }}',
            cancelUrlBase: '{{ url('change-requests') }}',
            cancelFailedMessage: @json(__('travel.change_requests.cancel_failed')),
        };
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.btn-cancel-request').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    if (!window.confirm(@json(__('travel.change_requests.cancel_confirm')))) return;
                    btn.disabled = true;
                    const id = btn.dataset.id;
                    const res = await fetch(`${window.CHANGE_REQUESTS.cancelUrlBase}/${id}/cancel`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': window.CHANGE_REQUESTS.csrf,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message ?? window.CHANGE_REQUESTS.cancelFailedMessage);
                        btn.disabled = false;
                    }
                });
            });
        });
    </script>
@endpush

@section('content')

@php
    $sectionLabels = [
        'bank_accounts' => __('travel.change_requests.section_bank_accounts'),
    ];
    $statusMap = [
        'pending'   => ['bg-yellow-100 text-yellow-700', __('travel.change_requests.status_pending')],
        'approved'  => ['bg-green-100 text-green-700',   __('travel.change_requests.status_approved')],
        'rejected'  => ['bg-red-100 text-red-700',       __('travel.change_requests.status_rejected')],
        'cancelled' => ['bg-gray-100 text-gray-500',     __('travel.change_requests.status_cancelled')],
    ];
@endphp

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">{{ __('travel.change_requests.title') }}</h2>
        <span class="text-xs text-gray-400">{{ $requests->total() }}</span>
    </div>

    @if($requests->isEmpty())
        <div class="py-16 text-center">
            <div class="text-4xl mb-3">📝</div>
            <h3 class="font-semibold text-gray-800 mb-1">{{ __('travel.change_requests.no_requests') }}</h3>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-xs text-gray-500 uppercase">
                        <th class="text-right py-3 px-5 font-medium">{{ __('travel.change_requests.column_section') }}</th>
                        <th class="text-right py-3 px-4 font-medium">{{ __('travel.change_requests.column_changes') }}</th>
                        <th class="text-center py-3 px-4 font-medium">{{ __('travel.change_requests.column_status') }}</th>
                        <th class="text-right py-3 px-4 font-medium">{{ __('travel.change_requests.column_submitted') }}</th>
                        <th class="text-right py-3 px-4 font-medium">{{ __('travel.change_requests.column_reviewed') }}</th>
                        <th class="text-right py-3 px-4 font-medium">{{ __('travel.change_requests.column_admin_note') }}</th>
                        <th class="py-3 px-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($requests as $cr)
                        @php
                            [$statusCls, $statusLabel] = $statusMap[$cr->status] ?? ['bg-gray-100 text-gray-500', $cr->status];
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors align-top">
                            <td class="py-3 px-5 text-xs font-medium text-gray-800">
                                {{ $sectionLabels[$cr->section] ?? $cr->section }}
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">
                                <ul class="space-y-0.5">
                                    @foreach($cr->requested_data as $field => $value)
                                        <li>
                                            <span class="text-gray-400">{{ $field }}:</span>
                                            <span class="text-gray-700">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusCls }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-xs text-gray-400">
                                {{ $cr->created_at->format('Y/m/d H:i') }}
                            </td>
                            <td class="py-3 px-4 text-right text-xs text-gray-400">
                                {{ $cr->reviewed_at?->format('Y/m/d H:i') ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">
                                {{ $cr->admin_note ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($cr->status === 'pending')
                                    <button type="button" class="btn-cancel-request text-xs text-red-600 hover:text-red-800 font-medium"
                                        data-id="{{ $cr->id }}">
                                        {{ __('travel.change_requests.cancel') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $requests->links() }}
            </div>
        @endif
    @endif
</div>

@endsection
