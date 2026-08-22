@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/payouts.js'])
@endpush

@section('title', __('admin.payouts.payout_number') . ' ' . $payout->payout_number)

@section('content')
    @php
        $currency = strtoupper($payout->currency);
        $fmt = fn($cents) => $currency . ' ' . number_format($cents / 100, 2);

        $statusColors = [
            'pending'    => 'gray',
            'approved'   => 'primary',
            'processing' => 'primary',
            'completed'  => 'success',
            'failed'     => 'danger',
            'on_hold'    => 'warning',
        ];

        $methodLabels = [
            'bank_transfer' => __('admin.payouts.bank_transfer'),
            'wallet'        => __('admin.payouts.wallet'),
            'paypal'        => __('admin.payouts.paypal'),
        ];

        $accountTypeLabels = [
            'customer_payment'    => 'Customer Payment',
            'platform_revenue'    => 'Platform Revenue',
            'platform_commission' => __('admin.payouts.platform_commission'),
            'seller_payable'      => 'Seller Payable',
            'gateway_fee'         => __('admin.payouts.gateway_fee') ?? 'Gateway Fee',
            'tax_payable'         => 'Tax Payable',
            'refund_liability'    => 'Refund Liability',
            'shipping_revenue'    => 'Shipping Revenue',
            'cod_clearing'        => 'COD Clearing',
        ];
    @endphp

    <script>window.PAYOUT_ID = {{ $payout->id }};</script>
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            payoutsApproving: @json(__('admin.payouts.approving')),
            payoutsApprove: @json(__('admin.payouts.approve')),
            payoutsApproved: @json(__('admin.payouts.payout_approved')),
            payoutsFailedApprove: @json(__('admin.payouts.failed_to_approve_payout')),
            payoutsProcessing: @json(__('admin.payouts.processing_ellipsis')),
            payoutsMarkCompleted: @json(__('admin.payouts.mark_as_completed')),
            payoutsCompleted: @json(__('admin.payouts.payout_marked_completed')),
            payoutsFailedProcess: @json(__('admin.payouts.failed_to_process_payout')),
            payoutsPleaseProvideHoldReason: @json(__('admin.payouts.please_provide_hold_reason')),
            payoutsSaving: @json(__('admin.payouts.saving')),
            payoutsPutOnHold: @json(__('admin.payouts.put_on_hold')),
            payoutsOnHold: @json(__('admin.payouts.payout_placed_on_hold')),
            payoutsFailedHold: @json(__('admin.payouts.failed_to_hold_payout')),
            payoutsRecalculate: @json(__('admin.payouts.recalculate')),
            payoutsCalculating: @json(__('admin.payouts.calculating')),
            payoutsRecalculated: @json(__('admin.payouts.payout_recalculated')),
            payoutsRecalculationFailed: @json(__('admin.payouts.recalculation_failed')),
        });
    </script>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        {{-- MAIN COLUMN --}}
        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 space-y-6">

            {{-- ──────────────────────────────────── --}}
            {{-- Financial Breakdown --}}
            {{-- ──────────────────────────────────── --}}
            <x-card title="{{ __('admin.payouts.financial_breakdown') }}">
                <div class="space-y-0 divide-y divide-gray-100">
                    {{-- Income rows --}}
                    <div class="flex items-center justify-between py-3 text-sm">
                        <span class="text-gray-600">{{ __('admin.payouts.gross_sales') }}</span>
                        <span class="font-medium text-gray-900" dir="ltr">{{ $fmt($payout->gross_sales) }}</span>
                    </div>

                    {{-- Deduction rows --}}
                    <div class="flex items-center justify-between py-3 text-sm">
                        <span class="text-gray-500">{{ __('admin.payouts.platform_commission') }}</span>
                        <span class="text-danger-600" dir="ltr">−{{ $fmt($payout->commission) }}</span>
                    </div>
                    @if($payout->gateway_fee_deducted > 0)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <span class="text-gray-500">{{ __('admin.payouts.payment_gateway_fee_vendor_borne') }}</span>
                            <span class="text-danger-600" dir="ltr">−{{ $fmt($payout->gateway_fee_deducted) }}</span>
                        </div>
                    @endif
                    @if($payout->refunds_deducted > 0)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <span class="text-gray-500">{{ __('admin.payouts.refunds_deducted') }}</span>
                            <span class="text-danger-600" dir="ltr">−{{ $fmt($payout->refunds_deducted) }}</span>
                        </div>
                    @endif
                    @if($payout->chargebacks_deducted > 0)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <span class="text-gray-500">{{ __('admin.payouts.chargebacks_deducted') }}</span>
                            <span class="text-danger-600" dir="ltr">−{{ $fmt($payout->chargebacks_deducted) }}</span>
                        </div>
                    @endif
                    @if($payout->storage_fees > 0)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <span class="text-gray-500">{{ __('admin.payouts.storage_fees_fbn') }}</span>
                            <span class="text-danger-600" dir="ltr">−{{ $fmt($payout->storage_fees) }}</span>
                        </div>
                    @endif
                    @if($payout->ad_fees > 0)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <span class="text-gray-500">{{ __('admin.payouts.advertising_fees') }}</span>
                            <span class="text-danger-600" dir="ltr">−{{ $fmt($payout->ad_fees) }}</span>
                        </div>
                    @endif
                    @if($payout->other_adjustments != 0)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <span class="text-gray-500">{{ __('admin.payouts.other_adjustments') }}</span>
                            <span class="{{ $payout->other_adjustments < 0 ? 'text-danger-600' : 'text-success-600' }}" dir="ltr">
                                {{ $payout->other_adjustments < 0 ? '−' : '+' }}{{ $fmt(abs($payout->other_adjustments)) }}
                            </span>
                        </div>
                    @endif

                    {{-- Net total --}}
                    <div class="flex items-center justify-between py-4 text-base font-bold bg-gray-50 px-4 -mx-4 rounded-b-xl mt-2">
                        <span class="text-gray-900">{{ __('admin.payouts.net_payout_amount') }}</span>
                        <span class="text-primary-700 text-lg" dir="ltr">{{ $fmt($payout->net_amount) }}</span>
                    </div>
                </div>
            </x-card>

            {{-- ──────────────────────────────────── --}}
            {{-- Sub-Order Items --}}
            {{-- ──────────────────────────────────── --}}
            @if($payout->items->isNotEmpty())
                <x-card title="{{ __('admin.payouts.sub_orders_included') }}">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2.5 text-start text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.sub_order') }}</th>
                                    <th class="px-4 py-2.5 text-end text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.gross') }}</th>
                                    <th class="px-4 py-2.5 text-end text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.commission') }}</th>
                                    <th class="px-4 py-2.5 text-end text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.net') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($payout->items as $item)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                            {{ $item->subOrder->sub_order_number ?? $item->sub_order_id }}
                                        </td>
                                        <td class="px-4 py-3 text-end" dir="ltr">{{ $fmt($item->gross) }}</td>
                                        <td class="px-4 py-3 text-end text-danger-600" dir="ltr">−{{ $fmt($item->commission) }}</td>
                                        <td class="px-4 py-3 text-end font-medium" dir="ltr">{{ $fmt($item->net) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-700 text-sm">{{ __('admin.payouts.total') }}</td>
                                    <td class="px-4 py-3 text-end font-semibold" dir="ltr">{{ $fmt($payout->items->sum('gross')) }}</td>
                                    <td class="px-4 py-3 text-end font-semibold text-danger-600" dir="ltr">−{{ $fmt($payout->items->sum('commission')) }}</td>
                                    <td class="px-4 py-3 text-end font-semibold text-primary-700" dir="ltr">{{ $fmt($payout->items->sum('net')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </x-card>
            @endif

            {{-- ──────────────────────────────────── --}}
            {{-- Ledger Entries --}}
            {{-- ──────────────────────────────────── --}}
            @if($ledgerEntries->isNotEmpty())
                <x-card title="{{ __('admin.payouts.ledger_entries') }}">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2.5 text-start text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.account_type') }}</th>
                                    <th class="px-4 py-2.5 text-start text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.description') }}</th>
                                    <th class="px-4 py-2.5 text-end text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.debit') }}</th>
                                    <th class="px-4 py-2.5 text-end text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.credit') }}</th>
                                    <th class="px-4 py-2.5 text-start text-xs font-semibold text-gray-500 uppercase">{{ __('admin.payouts.created') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($ledgerEntries as $entry)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-200">
                                                {{ $accountTypeLabels[$entry->account_type] ?? $entry->account_type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $entry->description }}</td>
                                        <td class="px-4 py-3 text-end font-mono text-sm" dir="ltr">
                                            @if($entry->debit > 0)
                                                <span class="text-danger-600">{{ $fmt($entry->debit) }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-end font-mono text-sm" dir="ltr">
                                            @if($entry->credit > 0)
                                                <span class="text-success-600">{{ $fmt($entry->credit) }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-400">
                                            {{ \Carbon\Carbon::parse($entry->created_at)->format('M j, Y H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

        </div>{{-- /main column --}}

        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        {{-- SIDEBAR --}}
        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-80 flex-shrink-0 space-y-4 lg:sticky lg:top-20">

            {{-- Payout Summary --}}
            <x-card title="{{ __('admin.payouts.payout_summary') }}">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('admin.payouts.payout_number') }}</span>
                        <span class="font-mono font-semibold text-gray-900">{{ $payout->payout_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('admin.payouts.period') }}</span>
                        <span class="text-gray-700">
                            {{ \Carbon\Carbon::parse($payout->period_start)->format('M j') }}
                            →
                            {{ \Carbon\Carbon::parse($payout->period_end)->format('M j, Y') }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">{{ __('admin.payouts.status') }}</span>
                        <x-badge :color="$statusColors[$payout->status->value] ?? 'gray'">
                            {{ ucwords(str_replace('_', ' ', $payout->status->value)) }}
                        </x-badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('admin.payouts.method') }}</span>
                        <span class="text-gray-700">{{ $methodLabels[$payout->payout_method] ?? $payout->payout_method }}</span>
                    </div>
                    @if($payout->processed_at)
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('admin.payouts.processed') }}</span>
                            <span class="text-gray-700">{{ \Carbon\Carbon::parse($payout->processed_at)->format('M j, Y') }}</span>
                        </div>
                    @endif
                    @if($payout->gateway_reference)
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-gray-500 flex-shrink-0">{{ __('admin.payouts.ref_number') }}</span>
                            <span class="font-mono text-xs text-gray-700 break-all text-end">{{ $payout->gateway_reference }}</span>
                        </div>
                    @endif
                    @if($payout->receipt_url)
                        <div class="pt-1">
                            <a href="{{ $payout->receipt_url }}" target="_blank"
                                class="inline-flex items-center gap-1.5 text-xs text-primary-600 hover:text-primary-800 hover:underline">
                                <x-heroicon name="document-arrow-down" class="w-3.5 h-3.5" />
                                {{ __('admin.payouts.download_receipt') }}
                            </a>
                        </div>
                    @endif
                </div>

                <div class="border-t border-gray-100 mt-3 pt-3">
                    <div class="flex justify-between font-bold text-base text-gray-900">
                        <span>{{ __('admin.payouts.net_amount') }}</span>
                        <span class="text-primary-700" dir="ltr">{{ $fmt($payout->net_amount) }}</span>
                    </div>
                </div>
            </x-card>

            {{-- Vendor Info --}}
            <x-card title="{{ __('admin.payouts.vendor_card_title') }}">
                @if($payout->vendor)
                    <div class="space-y-2 text-sm">
                        <p class="font-semibold text-gray-900">{{ $payout->vendor->store_name }}</p>
                        @if($payout->vendor->business_name)
                            <p class="text-gray-500 text-xs">{{ $payout->vendor->business_name }}</p>
                        @endif
                        <a href="{{ route('admin.vendors.show', $payout->vendor->id) }}"
                            class="inline-flex items-center gap-1 text-xs text-primary-600 hover:underline mt-1">
                            <x-heroicon name="arrow-top-right-on-square" class="w-3.5 h-3.5" />
                            {{ __('admin.payouts.view_vendor_profile') }}
                        </a>
                    </div>
                @else
                    <p class="text-sm text-gray-400 italic">{{ __('admin.payouts.vendor_not_found') }}</p>
                @endif
            </x-card>

            {{-- Bank Account --}}
            @if($payout->bankAccount)
                <x-card title="{{ __('admin.payouts.bank_account') }}">
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('admin.payouts.bank') }}</span>
                            <span class="text-gray-800 font-medium">{{ $payout->bankAccount->bank_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('admin.payouts.account_holder') }}</span>
                            <span class="text-gray-700">{{ $payout->bankAccount->account_holder_name }}</span>
                        </div>
                        @if($payout->bankAccount->iban)
                            <div class="flex justify-between items-start gap-2">
                                <span class="text-gray-500 flex-shrink-0">{{ __('admin.payouts.iban') }}</span>
                                <span class="font-mono text-xs text-gray-700 text-end" dir="ltr">
                                    {{ '****' . substr($payout->bankAccount->iban, -4) }}
                                </span>
                            </div>
                        @endif
                        @if($payout->bankAccount->swift_code)
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('admin.payouts.swift') }}</span>
                                <span class="font-mono text-xs text-gray-700" dir="ltr">{{ $payout->bankAccount->swift_code }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">{{ __('admin.payouts.verification') }}</span>
                            <x-badge :color="$payout->bankAccount->verification_status === \App\Enums\VendorBankAccountVerificationStatus::Verified ? 'success' : 'warning'">
                                {{ ucfirst($payout->bankAccount->verification_status->value) }}
                            </x-badge>
                        </div>
                    </div>
                </x-card>
            @endif

            {{-- Approved By --}}
            @if($payout->approvedByAdmin)
                <x-card title="{{ __('admin.payouts.approved_by') }}">
                    <p class="text-sm font-medium text-gray-800">{{ $payout->approvedByAdmin->name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $payout->approvedByAdmin->email }}</p>
                </x-card>
            @endif

            {{-- On-hold reason --}}
            @if($payout->status === \App\Enums\PayoutStatus::OnHold && $payout->failed_reason)
                <x-card title="{{ __('admin.payouts.hold_reason') }}">
                    <p class="text-sm text-amber-700">{{ $payout->failed_reason }}</p>
                </x-card>
            @endif

            {{-- Failed reason --}}
            @if($payout->status === \App\Enums\PayoutStatus::Failed && $payout->failed_reason)
                <x-card title="{{ __('admin.payouts.failure_reason') }}">
                    <p class="text-sm text-danger-700">{{ $payout->failed_reason }}</p>
                </x-card>
            @endif

            {{-- Actions --}}
            <x-card title="{{ __('admin.payouts.actions') }}">
                <div class="space-y-2">
                    @if($payout->status === \App\Enums\PayoutStatus::Pending)
                        <button type="button" data-modal-open="approve-modal"
                            class="btn btn-primary w-full justify-center">
                            <x-heroicon name="check-circle" class="w-4 h-4 mr-1.5" />
                            {{ __('admin.payouts.approve_payout') }}
                        </button>
                        <button type="button" data-modal-open="recalculate-modal"
                            class="btn btn-secondary w-full justify-center">
                            <x-heroicon name="arrow-path" class="w-4 h-4 mr-1.5" />
                            {{ __('admin.payouts.recalculate') }}
                        </button>
                    @endif
                    @if($payout->status === \App\Enums\PayoutStatus::Approved)
                        <button type="button" data-modal-open="process-modal"
                            class="btn btn-primary w-full justify-center">
                            <x-heroicon name="banknotes" class="w-4 h-4 mr-1.5" />
                            {{ __('admin.payouts.mark_as_completed') }}
                        </button>
                    @endif
                    @if(!in_array($payout->status, [\App\Enums\PayoutStatus::Completed, \App\Enums\PayoutStatus::Failed]))
                        <button type="button" data-modal-open="hold-modal"
                            class="btn btn-ghost w-full justify-center text-warning-700 hover:bg-warning-50">
                            <x-heroicon name="pause-circle" class="w-4 h-4 mr-1.5" />
                            {{ __('admin.payouts.put_on_hold') }}
                        </button>
                    @endif
                    @if($payout->status === \App\Enums\PayoutStatus::OnHold)
                        <button type="button" data-modal-open="recalculate-modal"
                            class="btn btn-secondary w-full justify-center">
                            <x-heroicon name="arrow-path" class="w-4 h-4 mr-1.5" />
                            {{ __('admin.payouts.recalculate') }}
                        </button>
                    @endif
                </div>
            </x-card>

        </div>{{-- /sidebar --}}

    </div>{{-- /flex row --}}

    {{-- ═══════════════════════════════════════════════════════════════════════════ --}}
    {{-- MODALS --}}
    {{-- ═══════════════════════════════════════════════════════════════════════════ --}}

    {{-- 1. Approve Payout --}}
    <x-modal id="approve-modal" title="{{ __('admin.payouts.approve_payout_modal_title') }}" size="sm">
        <form id="approve-form">
            @csrf
            <div class="space-y-4">
                <div class="rounded-lg bg-primary-50 border border-primary-200 p-3 text-sm text-primary-800">
                    {{ __('admin.payouts.approve_payout_notice') }}
                    {{ __('admin.payouts.net_amount_label') }} <strong dir="ltr">{{ $fmt($payout->net_amount) }}</strong>
                </div>
                <div>
                    <label class="form-label">{{ __('admin.payouts.notes_optional') }}</label>
                    <textarea name="notes" rows="2" class="form-textarea w-full"
                        placeholder="{{ __('admin.payouts.approval_notes_placeholder') }}"></textarea>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('admin.payouts.cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('admin.payouts.approve') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 2. Process / Complete Payout --}}
    <x-modal id="process-modal" title="{{ __('admin.payouts.mark_payout_completed') }}" size="sm">
        <form id="process-form">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="form-label">{{ __('admin.payouts.gateway_reference_optional') }}</label>
                    <input type="text" name="gateway_reference" class="form-input w-full"
                        placeholder="{{ __('admin.payouts.bank_transaction_id_placeholder') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('admin.payouts.receipt_url_optional') }}</label>
                    <input type="url" name="receipt_url" class="form-input w-full"
                        placeholder="https://…">
                </div>
            </div>
            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('admin.payouts.cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('admin.payouts.mark_as_completed') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 3. Hold Payout --}}
    <x-modal id="hold-modal" title="{{ __('admin.payouts.put_payout_on_hold') }}" size="sm">
        <form id="hold-form">
            @csrf
            <div class="space-y-4">
                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800 flex items-start gap-2">
                    <x-heroicon name="exclamation-triangle" class="w-5 h-5 flex-shrink-0 mt-0.5" />
                    <span>{{ __('admin.payouts.hold_payout_notice') }}</span>
                </div>
                <div>
                    <label class="form-label">{{ __('admin.payouts.reason_required') }} <span class="text-danger-500">*</span></label>
                    <textarea name="reason" rows="3" class="form-textarea w-full"
                        placeholder="{{ __('admin.payouts.hold_reason_placeholder') }}"></textarea>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('admin.payouts.cancel') }}</button>
                <button type="submit" class="btn btn-warning">{{ __('admin.payouts.put_on_hold') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- 4. Recalculate Payout --}}
    <x-modal id="recalculate-modal" title="{{ __('admin.payouts.recalculate_payout') }}" size="sm">
        <form id="recalculate-form">
            @csrf
            <div class="space-y-4">
                <p class="text-sm text-gray-600">
                    {{ __('admin.payouts.recalculate_payout_notice') }}
                    <strong>{{ \Carbon\Carbon::parse($payout->period_start)->format('M j') }}
                    → {{ \Carbon\Carbon::parse($payout->period_end)->format('M j, Y') }}</strong>
                    {{ __('admin.payouts.and_update_net_amount') }}
                </p>
            </div>
            <x-slot:footer>
                <button type="button" data-modal-close class="btn btn-ghost">{{ __('admin.payouts.cancel') }}</button>
                <button type="submit" class="btn btn-secondary">{{ __('admin.payouts.recalculate') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

@endsection
