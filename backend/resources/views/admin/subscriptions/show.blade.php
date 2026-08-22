@extends('layouts.admin')

@section('title', __('admin.subscriptions.subscription_details_title'))

@section('content')


    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ─── Left: Subscription Info ─────────────────────────────────────────── --}}
        <div class="lg:col-span-1 space-y-4">

            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-gray-900 text-lg">{{ __('admin.subscriptions.subscription_details') }}</h2>
                    <span class="badge badge-{{ $subscription->statusColor() }}">
                        {{ __('admin.subscriptions.' . $subscription->status->value) }}
                    </span>
                </div>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.subscriptions.vendor') }}</dt>
                        <dd class="font-medium text-gray-800">
                            <a href="{{ route('admin.vendors.show', $subscription->vendor_id) }}" class="hover:underline">
                                {{ $subscription->vendor->business_name ?? $subscription->vendor->name }}
                            </a>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.subscriptions.plan') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $subscription->plan->name_en ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.subscriptions.started') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $subscription->started_at?->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.subscriptions.period') }}</dt>
                        <dd class="font-medium text-gray-800">
                            {{ $subscription->period_start?->format('d M Y') }}
                            →
                            {{ $subscription->period_end?->format('d M Y') }}
                        </dd>
                    </div>
                    @if($subscription->isActive())
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.subscriptions.days_remaining') }}</dt>
                            <dd class="font-bold text-green-600">{{ $subscription->daysRemaining() }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.subscriptions.auto_renew') }}</dt>
                        <dd>
                            <span class="badge badge-{{ $subscription->auto_renew ? 'success' : 'secondary' }} text-xs">
                                {{ $subscription->auto_renew ? __('admin.subscriptions.on') : __('admin.subscriptions.off') }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.subscriptions.listings_used') }}</dt>
                        <dd class="font-medium text-gray-800">
                            {{ $subscription->listings_used }}
                            /
                            {{ $subscription->plan?->hasUnlimitedListings() ? '∞' : ($subscription->plan?->max_listings ?? '—') }}
                        </dd>
                    </div>
                    @if($subscription->approved_by_admin_id)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.subscriptions.approved_by') }}</dt>
                            <dd class="font-medium text-gray-800">
                                {{ $subscription->approvedByAdmin?->name ?? $subscription->approved_by_admin_id }}
                            </dd>
                        </div>
                    @endif
                    @if($subscription->cancelled_at)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.subscriptions.cancelled_at') }}</dt>
                            <dd class="text-red-500">{{ $subscription->cancelled_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if($subscription->cancellation_reason)
                        <div class="pt-1">
                            <dt class="text-gray-500 text-xs mb-0.5">{{ __('admin.subscriptions.cancellation_reason') }}</dt>
                            <dd class="text-xs bg-gray-50 rounded p-2 text-gray-700">{{ $subscription->cancellation_reason }}
                            </dd>
                        </div>
                    @endif
                </dl>

                @if($subscription->isActive())
                    <div class="mt-5 pt-4 border-t border-gray-100">
                        <button type="button" id="btn-cancel-this-sub" class="btn btn-danger btn-sm w-full"
                            data-id="{{ $subscription->id }}">
                            {{ __('admin.subscriptions.cancel_subscription') }}
                        </button>
                    </div>
                @endif
            </div>

            {{-- Plan info card --}}
            @if($subscription->plan)
                <div class="bg-white rounded-2xl border border-gray-100 p-5">
                    <h3 class="font-semibold text-gray-800 mb-3 text-sm">{{ __('admin.subscriptions.plan_details') }}</h3>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.subscriptions.price') }}</dt>
                            <dd class="font-medium">{{ number_format($subscription->plan->price) }}
                                {{ $subscription->plan->currency }}/mo</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.subscriptions.commission_discount') }}</dt>
                            <dd class="font-medium">{{ $subscription->plan->commission_discount_pct }}%</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.subscriptions.free_shipping') }}</dt>
                            <dd>
                                <span
                                    class="badge badge-{{ $subscription->plan->free_shipping_included ? 'success' : 'secondary' }} text-xs">
                                    {{ $subscription->plan->free_shipping_included ? __('admin.subscriptions.yes') : __('admin.subscriptions.no') }}
                                </span>
                            </dd>
                        </div>
                        @foreach(($subscription->plan->features ?? []) as $feature)
                            <div class="flex items-center gap-1.5 text-xs text-gray-600">
                                <span class="text-green-500">✓</span>
                                {{ ucwords(str_replace('_', ' ', $feature)) }}
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

        </div>

        {{-- ─── Right: Invoices ────────────────────────────────────────────────── --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">{{ __('admin.subscriptions.invoices') }}</h3>
                    <span class="text-xs text-gray-400">{{ __('admin.subscriptions.invoice_count', ['count' => $invoices->count()]) }}</span>
                </div>

                @if($invoices->isEmpty())
                    <div class="p-10 text-center text-gray-400 text-sm">{{ __('admin.subscriptions.no_invoices_yet') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.invoice_number') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.amount') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.status') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.period') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.paid_at') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($invoices as $invoice)
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $invoice->invoice_number }}</td>
                                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $invoice->amountFormatted() }}</td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="badge badge-{{ $invoice->statusColor() }} text-xs">{{ __('admin.subscriptions.' . $invoice->status->value) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500">
                                            {{ $invoice->period_start?->format('d M') }} → {{ $invoice->period_end?->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500">
                                            {{ $invoice->paid_at?->format('d M Y H:i') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($invoice->status !== \App\Enums\VendorSubscriptionInvoiceStatus::Paid)
                                                <button type="button" class="btn btn-xs btn-success btn-mark-paid"
                                                    data-id="{{ $invoice->id }}">
                                                    {{ __('admin.subscriptions.mark_paid') }}
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- ─── Cancel Subscription Modal ───────────────────────────────────────────── --}}
    <div id="cancel-sub-modal" class="modal" style="display:none;">
        <div class="modal-box max-w-sm">
            <h3 class="font-bold text-lg mb-4">{{ __('admin.subscriptions.cancel_subscription') }}</h3>
            <div>
                <label class="label-sm">{{ __('admin.subscriptions.reason_optional') }}</label>
                <textarea id="cancel-sub-reason" rows="3" class="form-input w-full text-sm"
                    placeholder="{{ __('admin.subscriptions.cancellation_reason_placeholder') }}"></textarea>
            </div>
            <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-gray-100">
                <button type="button" id="cancel-sub-close" class="btn btn-ghost btn-sm">{{ __('admin.subscriptions.close') }}</button>
                <button type="button" id="cancel-sub-confirm" class="btn btn-danger btn-sm">{{ __('admin.subscriptions.cancel_subscription') }}</button>
            </div>
        </div>
    </div>

    {{-- ─── Mark Paid Modal ─────────────────────────────────────────────────────── --}}
    <div id="mark-paid-modal" class="modal" style="display:none;">
        <div class="modal-box max-w-sm">
            <h3 class="font-bold text-lg mb-4">{{ __('admin.subscriptions.mark_invoice_paid') }}</h3>
            <input type="hidden" id="mp-invoice-id">
            <div>
                <label class="label-sm">{{ __('admin.subscriptions.payment_transaction_id_optional') }}</label>
                <input type="text" id="mp-txid" class="form-input w-full text-sm" placeholder="{{ __('admin.subscriptions.payment_transaction_id_placeholder') }}">
            </div>
            <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-gray-100">
                <button type="button" id="mp-close" class="btn btn-ghost btn-sm">{{ __('admin.subscriptions.close') }}</button>
                <button type="button" id="mp-confirm" class="btn btn-success btn-sm">{{ __('admin.subscriptions.mark_paid') }}</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tok = '{{ csrf_token() }}';

            // ── Cancel Subscription ────────────────────────────────────────────────────
            $('#btn-cancel-this-sub').on('click', () => {
                $('#cancel-sub-reason').val('');
                $('#cancel-sub-modal').show();
            });
            $('#cancel-sub-close').on('click', () => $('#cancel-sub-modal').hide());
            $('#cancel-sub-confirm').on('click', function () {
                fetch('/admin/subscriptions/{{ $subscription->id }}/cancel', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reason: $('#cancel-sub-reason').val() }),
                }).then(r => r.json()).then(data => {
                    if (data.success) { window.Toast.success(data.message); location.reload(); }
                    else { window.Toast.error(data.message); }
                });
            });

            // ── Mark Invoice Paid ──────────────────────────────────────────────────────
            $(document).on('click', '.btn-mark-paid', function () {
                $('#mp-invoice-id').val($(this).data('id'));
                $('#mp-txid').val('');
                $('#mark-paid-modal').show();
            });
            $('#mp-close').on('click', () => $('#mark-paid-modal').hide());
            $('#mp-confirm').on('click', function () {
                const id = $('#mp-invoice-id').val();
                fetch('/admin/subscriptions/invoices/' + id + '/mark-paid', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payment_transaction_id: $('#mp-txid').val() }),
                }).then(r => r.json()).then(data => {
                    if (data.success) { window.Toast.success(data.message); location.reload(); }
                    else { window.Toast.error(data.message); }
                });
            });
        }, { once: true });
    </script>
@endpush
