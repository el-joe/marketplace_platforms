@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@section('title', $customer->name . ' — ' . __('admin.customers_section.customer_name'))

@section('content')

@php
    $statusColors = [
        'active'    => 'success',
        'suspended' => 'warning',
        'banned'    => 'danger',
    ];
    $statusColor = $statusColors[$customer->status->value] ?? 'gray';

    $reviewStatusColors = [
        'pending'  => 'gray',
        'approved' => 'success',
        'rejected' => 'danger',
    ];
@endphp

{{-- ─── Page header ─────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex items-start justify-between gap-4">
    <div class="flex items-center gap-4 min-w-0">
        <div class="w-14 h-14 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center text-xl font-bold flex-shrink-0 select-none">
            {{ strtoupper(substr($customer->name, 0, 2)) }}
        </div>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900 truncate">{{ $customer->name }}</h1>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
                <x-badge :color="$statusColor" size="md">
                    {{ $customer->status->label() }}
                </x-badge>
                @if($customer->email_verified_at)
                    <x-badge color="success">{{ __('admin.customers_section.email_verified') }}</x-badge>
                @endif
            </div>
        </div>
    </div>
    <a href="{{ route('admin.customers.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex-shrink-0 mt-1">← {{ __('admin.customers_section.back') }}</a>
</div>

{{-- ─── Two-column layout ───────────────────────────────────────────────────── --}}
<div class="grid grid-cols-12 gap-6">

    {{-- ══ MAIN (8/12) ══════════════════════════════════════════════════════ --}}
    <div class="col-span-12 lg:col-span-8 space-y-6">

        <div x-data="{ tab: 'orders' }">
            {{-- Tab bar --}}
            <div class="flex gap-1 border-b border-gray-200 overflow-x-auto pb-px mb-6">
                @foreach([
                    'orders'          => __('admin.customers_section.tab_orders'),
                    'addresses'       => __('admin.customers_section.tab_addresses'),
                    'reviews'         => __('admin.customers_section.tab_reviews'),
                    'returns'         => __('admin.customers_section.tab_returns'),
                    'disputes'        => __('admin.customers_section.tab_disputes'),
                    'tickets'         => __('admin.customers_section.tab_tickets'),
                    'payment_methods' => __('admin.customers_section.tab_payment_methods'),
                    'wallet'          => __('admin.customers_section.tab_wallet'),
                    'wallet_credits'  => __('admin.customers_section.tab_wallet_credits'),
                    'referrals'       => 'Referrals (' . $referralStats['total_referred'] . ')',
                    'warranty'        => __('admin.customers_section.tab_warranty_claims'),
                    'gift_cards'      => __('admin.customers_section.tab_gift_cards'),
                    'notifications'   => __('admin.customers_section.tab_notifications'),
                    'security'        => __('admin.customers_section.tab_security'),
                    'activity'        => __('admin.customers_section.tab_activity'),
                ] as $key => $label)
                    <button type="button"
                            @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-b-2 border-primary-600 text-primary-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 text-sm whitespace-nowrap transition-colors focus:outline-none">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- ── Orders ─────────────────────────────────────────────────── --}}
            <div x-show="tab === 'orders'">
                <x-card title="{{ __('admin.customers_section.recent_orders') }}" subtitle="{{ __('admin.customers_section.latest_20_orders') }}">
                    <x-slot name="actions">
                        <button type="button" id="show-orders-datatable-btn"
                            data-url="{{ route('admin.customers.orders.datatable', $customer->id) }}"
                            class="text-xs text-primary-600 hover:underline">{{ __('admin.customers_section.full_list_datatable') }}</button>
                    </x-slot>
                    @if($orders->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_orders_yet') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.order_number_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.total_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.placed_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($orders as $order)
                                    @php
                                        $orderStatusColors = [
                                            'pending'          => 'gray',
                                            'confirmed'        => 'primary',
                                            'processing'       => 'primary',
                                            'shipped'          => 'primary',
                                            'out_for_delivery' => 'warning',
                                            'delivered'        => 'success',
                                            'completed'        => 'success',
                                            'cancelled'        => 'danger',
                                            'returned'         => 'danger',
                                            'refunded'         => 'danger',
                                        ];
                                        $oColor = $orderStatusColors[$order->status->value] ?? 'gray';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 font-mono text-xs">{{ $order->order_number }}</td>
                                        <td class="py-2 pr-4">{{ number_format((float) $order->total, 2) }} {{ strtoupper($order->currency ?? '') }}</td>
                                        <td class="py-2 pr-4">
                                            <x-badge :color="$oColor">{{ ucwords(str_replace('_', ' ', $order->status->value)) }}</x-badge>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">
                                            {{ $order->placed_at ? \Carbon\Carbon::parse($order->placed_at)->format('d M Y') : '—' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>

                <div id="orders-datatable-section" class="mt-4 hidden">
                    <x-card title="{{ __('admin.customers_section.all_orders') }}">
                        <div class="overflow-x-auto">
                            <table id="orders-datatable" class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.order_number_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.total_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.items_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.placed_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </div>

            {{-- ── Addresses ───────────────────────────────────────────────── --}}
            <div x-show="tab === 'addresses'">
                <x-card title="{{ __('admin.customers_section.saved_addresses') }}">
                    @if($customer->addresses->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_addresses_saved') }}</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($customer->addresses as $address)
                            <div class="rounded-lg border border-gray-200 p-4 text-sm space-y-1">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="font-semibold text-gray-800">{{ $address->label ?: ($address->address_type?->label() ?? __('admin.customers_section.address_fallback')) }}</span>
                                    @if($address->is_default)
                                        <x-badge color="primary">{{ __('admin.customers_section.default_badge') }}</x-badge>
                                    @endif
                                </div>
                                <p class="text-gray-700">{{ $address->recipient_name }}</p>
                                @if($address->recipient_phone)
                                    <p class="text-gray-500">{{ $address->recipient_phone }}</p>
                                @endif
                                <p class="text-gray-600">
                                    {{ implode(', ', array_filter([
                                        $address->street_address,
                                        $address->building,
                                        $address->floor ? __('admin.customers_section.floor_label') . ' ' . $address->floor : null,
                                        $address->apartment ? __('admin.customers_section.apartment_label') . ' ' . $address->apartment : null,
                                        $address->area,
                                        $address->city?->name_en ?? null,
                                        $address->country?->name_en ?? null,
                                        $address->postal_code,
                                    ])) }}
                                </p>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Reviews ─────────────────────────────────────────────────── --}}
            <div x-show="tab === 'reviews'">
                <x-card title="{{ __('admin.customers_section.tab_reviews') }}" subtitle="{{ __('admin.customers_section.latest_20') }}">
                    @if($reviews->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_reviews_yet') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.product_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.rating_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.verified_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.date_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($reviews as $review)
                                    @php $rColor = $reviewStatusColors[$review->status->value] ?? 'gray'; @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 max-w-[180px] truncate">
                                            {{ $review->product?->name ?? '—' }}
                                        </td>
                                        <td class="py-2 pr-4">
                                            <span class="inline-flex items-center gap-0.5 text-warning-500">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <span>{{ $i <= $review->rating ? '★' : '☆' }}</span>
                                                @endfor
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <x-badge :color="$rColor">{{ $review->status->label() }}</x-badge>
                                        </td>
                                        <td class="py-2 pr-4">
                                            @if($review->is_verified_purchase)
                                                <x-badge color="success">{{ __('admin.customers_section.verified_column') }}</x-badge>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $review->created_at->format('d M Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Returns ─────────────────────────────────────────────────── --}}
            <div x-show="tab === 'returns'">
                <x-card title="{{ __('admin.customers_section.return_requests') }}" subtitle="{{ __('admin.customers_section.latest_20') }}">
                    @if($returnRequests->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_return_requests') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.return_number_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.type_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.created_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($returnRequests as $ret)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 font-mono text-xs">{{ $ret->return_number }}</td>
                                        <td class="py-2 pr-4">{{ $ret->return_type?->label() ?? '—' }}</td>
                                        <td class="py-2 pr-4">
                                            <x-badge color="gray">{{ $ret->status->label() }}</x-badge>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $ret->created_at->format('d M Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Disputes ────────────────────────────────────────────────── --}}
            <div x-show="tab === 'disputes'">
                <x-card title="{{ __('admin.customers_section.tab_disputes') }}" subtitle="{{ __('admin.customers_section.latest_20') }}">
                    @if($disputes->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_disputes') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.dispute_number_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.reason_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.created_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($disputes as $dispute)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 font-mono text-xs">{{ $dispute->dispute_number }}</td>
                                        <td class="py-2 pr-4 max-w-[200px] truncate">{{ $dispute->reason->value }}</td>
                                        <td class="py-2 pr-4">
                                            <x-badge color="gray">{{ ucfirst(str_replace('_', ' ', $dispute->status->value)) }}</x-badge>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $dispute->created_at->format('d M Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Tickets ─────────────────────────────────────────────────── --}}
            <div x-show="tab === 'tickets'">
                <x-card title="{{ __('admin.customers_section.support_tickets') }}" subtitle="{{ __('admin.customers_section.latest_20') }}">
                    @if($tickets->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_support_tickets') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.ticket_number_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.subject_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.category_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.priority_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.created_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($tickets as $ticket)
                                    @php
                                        $ticketPriorityColors = ['low' => 'gray', 'medium' => 'primary', 'high' => 'warning', 'urgent' => 'danger'];
                                        $ticketStatusColors = ['open' => 'primary', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'gray'];
                                        $pColor = $ticketPriorityColors[$ticket->priority ?? 'low'] ?? 'gray';
                                        $sColor = $ticketStatusColors[$ticket->status?->value ?? 'open'] ?? 'gray';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 font-mono text-xs">{{ $ticket->ticket_number }}</td>
                                        <td class="py-2 pr-4 max-w-[200px] truncate">{{ $ticket->subject }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ ucfirst(str_replace('_', ' ', $ticket->category ?? '—')) }}</td>
                                        <td class="py-2 pr-4">
                                            <x-badge :color="$pColor">{{ ucfirst($ticket->priority ?? '—') }}</x-badge>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <x-badge :color="$sColor">{{ $ticket->status?->label() ?? '—' }}</x-badge>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $ticket->created_at->format('d M Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Payment Methods ──────────────────────────────────────────── --}}
            <div x-show="tab === 'payment_methods'">
                <x-card title="{{ __('admin.customers_section.payment_methods_title') }}">
                    {{-- Saved card tokens (gateway_token) are no longer stored. --}}
                    {{-- Payment history is shown in the Orders tab per sub-order. --}}
                    <p class="text-sm text-gray-500 py-4 text-center">
                        Payment history is available in the Orders tab. Saved cards are not stored — customers pay per order via the selected gateway.
                    </p>
                </x-card>
            </div>

            {{-- ── Wallet ──────────────────────────────────────────────────── --}}
            <div x-show="tab === 'wallet'">
                <x-card title="{{ __('admin.customers_section.wallet_balances') }}">
                    @if($wallets->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_wallets') }}</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($wallets as $wallet)
                            <div class="rounded-lg border border-gray-200 p-4 text-sm space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-800">{{ strtoupper($wallet->currency) }}</span>
                                    @if($wallet->is_frozen)
                                        <x-badge color="danger">{{ __('admin.customers_section.frozen_badge') }}</x-badge>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500">{{ __('admin.customers_section.available_balance') }}</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($wallet->balance, 2) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500">{{ __('admin.customers_section.pending_balance') }}</span>
                                    <span class="text-gray-700">{{ number_format($wallet->pending_balance, 2) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Wallet & Credits ────────────────────────────────────────── --}}
            <div x-show="tab === 'wallet_credits'">
                <x-card title="{{ __('admin.customers_section.wallet_summary') }}">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div class="rounded-lg border border-gray-200 p-3">
                            <p class="text-gray-500 text-xs mb-1">{{ __('admin.customers_section.current_balance_label') }}</p>
                            <p class="font-bold text-gray-900" id="wallet-current-balance">
                                {{ number_format($walletStats['balance']) }} {{ strtoupper($walletStats['currency_code'] ?? '') }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3">
                            <p class="text-gray-500 text-xs mb-1">{{ __('admin.customers_section.total_credited') }}</p>
                            <p class="font-bold text-success-700">{{ number_format($walletStats['total_credited']) }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3">
                            <p class="text-gray-500 text-xs mb-1">{{ __('admin.customers_section.total_debited') }}</p>
                            <p class="font-bold text-danger-700">{{ number_format($walletStats['total_debited']) }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3">
                            <p class="text-gray-500 text-xs mb-1">{{ __('admin.customers_section.transaction_count') }}</p>
                            <p class="font-bold text-gray-900">{{ number_format($walletStats['transaction_count']) }}</p>
                        </div>
                    </div>
                </x-card>

                @if(auth('admin')->user()->can('wallet.manual_adjust'))
                <x-card title="{{ __('admin.customers_section.manual_wallet_adjustment') }}" class="mt-4">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.direction_label') }} <span class="text-danger-500">*</span></label>
                            <select id="wallet-adjust-direction" class="form-input w-full">
                                <option value="credit">{{ __('admin.customers_section.credit_option') }}</option>
                                <option value="debit">{{ __('admin.customers_section.debit_option') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.amount_label') }} <span class="text-danger-500">*</span></label>
                            <input type="number" id="wallet-adjust-amount" class="form-input w-full" min="1" step="1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.note_label') }} <span class="text-danger-500">*</span></label>
                            <textarea id="wallet-adjust-note" class="form-input w-full resize-none" rows="3" placeholder="{{ __('admin.customers_section.note_required_hint') }}"></textarea>
                        </div>
                        <button type="button" id="wallet-adjust-submit-btn"
                            class="btn btn-primary btn-sm"
                            data-url="{{ route('admin.customers.wallet.adjust', $customer->id) }}">
                            {{ __('admin.customers_section.submit_adjustment') }}
                        </button>
                    </div>
                </x-card>
                @endif

                <x-card title="{{ __('admin.customers_section.wallet_transactions') }}" class="mt-4">
                    <div class="overflow-x-auto">
                        <table id="wallet-transactions-datatable" class="w-full text-sm"
                            data-url="{{ route('admin.customers.wallet.transactions.datatable', $customer->id) }}">
                            <thead>
                                <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                    <th class="py-2 pr-4">{{ __('admin.customers_section.date_column') }}</th>
                                    <th class="py-2 pr-4">{{ __('admin.customers_section.type_col') }}</th>
                                    <th class="py-2 pr-4">{{ __('admin.customers_section.direction_column') }}</th>
                                    <th class="py-2 pr-4">{{ __('admin.customers_section.amount_column') }}</th>
                                    <th class="py-2 pr-4">{{ __('admin.customers_section.balance_after_column') }}</th>
                                    <th class="py-2 pr-4">{{ __('admin.customers_section.reference_column') }}</th>
                                    <th class="py-2 pr-4">{{ __('admin.customers_section.note_column') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </x-card>
            </div>

            {{-- ─── Referrals tab ─────────────────────────────────────────────────────── --}}
            <div x-show="tab === 'referrals'" x-cloak>
                <x-card title="Referred Customers">
                    {{-- Summary stats --}}
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ number_format($referralStats['total_referred']) }}</div>
                            <div class="text-xs text-gray-500 mt-1">Customers Referred</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ number_format($referralStats['total_referred_orders']) }}</div>
                            <div class="text-xs text-gray-500 mt-1">Orders by Referrals</div>
                        </div>
                    </div>

                    @if($referralStats['total_referred'] > 0)
                        <div id="referrals-datatable-wrapper">
                            <table id="referrals-datatable" class="w-full text-sm"
                                   data-url="{{ route('admin.customers.referrals.datatable', $customer->id) }}">
                                <thead>
                                    <tr class="border-b border-gray-100 text-left text-xs text-gray-500 uppercase tracking-wide">
                                        <th class="pb-2 pr-3 font-medium">Name</th>
                                        <th class="pb-2 pr-3 font-medium">Email</th>
                                        <th class="pb-2 pr-3 font-medium">Orders</th>
                                        <th class="pb-2 pr-3 font-medium">Loyalty Pts</th>
                                        <th class="pb-2 font-medium">Joined</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const el = document.getElementById('referrals-datatable');
                                if (el && typeof initDataTable === 'function') {
                                    initDataTable(el, el.dataset.url);
                                }
                            });
                        </script>
                    @else
                        <p class="text-sm text-gray-400 text-center py-6">This customer has not referred anyone yet.</p>
                    @endif
                </x-card>
            </div>

            {{-- ── Warranty Claims ─────────────────────────────────────────── --}}
            <div x-show="tab === 'warranty'">
                <x-card title="{{ __('admin.customers_section.warranty_claims_title') }}" subtitle="{{ __('admin.customers_section.latest_20') }}">
                    @if($warrantyClaims->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_warranty_claims') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.claim_number_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.product_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.issue_type_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.created_column') }}</th>
                                        <th class="py-2 pr-4"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @php
                                        $claimStatusColors = [
                                            'submitted'    => 'gray',
                                            'under_review' => 'warning',
                                            'approved'     => 'primary',
                                            'rejected'     => 'danger',
                                            'resolved'     => 'success',
                                        ];
                                    @endphp
                                    @foreach($warrantyClaims as $claim)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 font-mono text-xs">{{ $claim->claim_number }}</td>
                                        <td class="py-2 pr-4 max-w-[180px] truncate">{{ $claim->product?->name ?? '—' }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ ucfirst(str_replace('_', ' ', $claim->issue_type)) }}</td>
                                        <td class="py-2 pr-4">
                                            <x-badge :color="$claimStatusColors[$claim->status] ?? 'gray'">{{ ucfirst(str_replace('_', ' ', $claim->status)) }}</x-badge>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $claim->created_at->format('d M Y') }}</td>
                                        <td class="py-2 pr-4">
                                            <a href="{{ route('admin.warranty-claims.show', $claim->id) }}" class="text-primary-600 hover:underline text-xs">
                                                {{ __('admin.customers_section.view_claim') }}
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Gift Cards ──────────────────────────────────────────────── --}}
            <div x-show="tab === 'gift_cards'">
                <x-card title="{{ __('admin.customers_section.gift_cards_title') }}" subtitle="{{ __('admin.customers_section.latest_20') }}">
                    @if($giftCards->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_gift_cards') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.gift_card_code_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.denomination_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.balance_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.expires_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @php
                                        $giftCardStatusColors = [
                                            'active'             => 'success',
                                            'redeemed'           => 'gray',
                                            'expired'            => 'danger',
                                            'cancelled'          => 'danger',
                                            'pending_activation' => 'warning',
                                        ];
                                    @endphp
                                    @foreach($giftCards as $giftCard)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 font-mono text-xs">{{ $giftCard->code }}</td>
                                        <td class="py-2 pr-4">{{ number_format($giftCard->denomination, 2) }} {{ strtoupper($giftCard->currency) }}</td>
                                        <td class="py-2 pr-4">{{ number_format($giftCard->balance, 2) }} {{ strtoupper($giftCard->currency) }}</td>
                                        <td class="py-2 pr-4">
                                            <x-badge :color="$giftCardStatusColors[$giftCard->status] ?? 'gray'">{{ ucfirst(str_replace('_', ' ', $giftCard->status)) }}</x-badge>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $giftCard->expires_at?->format('d M Y') ?? '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Notifications ───────────────────────────────────────────── --}}
            <div x-show="tab === 'notifications'">
                <x-card title="{{ __('admin.customers_section.notifications_title') }}" subtitle="{{ __('admin.customers_section.latest_20_notifications') }}">
                    @if($notifications->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_notifications_yet') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.title_label') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.channel_label') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.status_col') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.created_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($notifications as $notif)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-4 max-w-[220px] truncate">{{ $notif->data['title'] ?? $notif->type }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ ucfirst($notif->channel?->value ?? '—') }}</td>
                                        <td class="py-2 pr-4">
                                            @if($notif->read_at)
                                                <x-badge color="gray">{{ __('admin.customers_section.read_badge') }}</x-badge>
                                            @else
                                                <x-badge color="primary">{{ __('admin.customers_section.unread_badge') }}</x-badge>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ ($notif->sent_at ?? $notif->created_at)->format('d M Y, H:i') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Security ─────────────────────────────────────────────────── --}}
            <div x-show="tab === 'security'">
                <x-card title="{{ __('admin.customers_section.security_title') }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-5">
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-3">
                            <span class="text-gray-500">{{ __('admin.customers_section.email_verified_at_label') }}</span>
                            <span class="text-gray-900">{{ $customer->email_verified_at?->format('d M Y, H:i') ?? __('admin.customers_section.not_verified') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-3">
                            <span class="text-gray-500">{{ __('admin.customers_section.phone_verified_at_label') }}</span>
                            <span class="text-gray-900">{{ $customer->phone_verified_at?->format('d M Y, H:i') ?? __('admin.customers_section.not_verified') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-3">
                            <span class="text-gray-500">{{ __('admin.customers_section.last_login') }}</span>
                            <span class="text-gray-900">{{ $customer->last_login_at?->format('d M Y, H:i') ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-3">
                            <span class="text-gray-500">{{ __('admin.customers_section.active_devices') }}</span>
                            <span class="text-gray-900 font-semibold">{{ $deviceTokens->count() }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h4 class="text-sm font-semibold text-gray-700">{{ __('admin.customers_section.active_devices') }}</h4>
                        @if(auth('admin')->user()->can('customers.edit') && $deviceTokens->isNotEmpty())
                        <button type="button" id="revoke-devices-btn"
                            class="btn btn-danger btn-xs"
                            data-url="{{ route('admin.customers.devices.revoke-all', $customer->id) }}">
                            {{ __('admin.customers_section.log_out_all_devices') }}
                        </button>
                        @endif
                    </div>

                    <div id="devices-empty-state" class="{{ $deviceTokens->isNotEmpty() ? 'hidden' : '' }}">
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_active_devices') }}</p>
                    </div>

                    @if($deviceTokens->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-start text-xs text-gray-500 uppercase">
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.device_platform_column') }}</th>
                                        <th class="py-2 pr-4">{{ __('admin.customers_section.last_used_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($deviceTokens as $token)
                                    <tr class="hover:bg-gray-50 js-device-row">
                                        <td class="py-2 pr-4">{{ ucfirst($token->platform?->value ?? '—') }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $token->last_used_at?->format('d M Y, H:i') ?? '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ── Activity ─────────────────────────────────────────────────── --}}
            <div x-show="tab === 'activity'">
                <x-card title="{{ __('admin.customers_section.activity_log') }}" subtitle="{{ __('admin.customers_section.showing_last_50') }}">
                    @if($activityLog->isEmpty())
                        <p class="text-sm text-gray-500 py-4 text-center">{{ __('admin.customers_section.no_activity_recorded') }}</p>
                    @else
                        <ol class="relative border-l border-gray-200 ml-3 space-y-4 py-2">
                            @foreach($activityLog as $entry)
                            <li class="ml-6">
                                <span class="absolute -left-2 flex items-center justify-center w-4 h-4 rounded-full bg-primary-100 ring-4 ring-white">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary-600"></span>
                                </span>
                                <div class="text-sm">
                                    <p class="font-medium text-gray-800">{{ $entry->description }}</p>
                                    @if($entry->event)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600 mr-2">{{ $entry->event }}</span>
                                    @endif
                                    <span class="text-xs text-gray-400">{{ $entry->created_at->format('d M Y, H:i') }}</span>
                                    @if($entry->ip_address)
                                        <span class="text-xs text-gray-400 ml-2">· {{ $entry->ip_address }}</span>
                                    @endif
                                    @if($entry->properties && $entry->properties !== '[]' && $entry->properties !== '{}')
                                        @php $props = is_array($entry->properties) ? $entry->properties : json_decode($entry->properties, true); @endphp
                                        @if(!empty($props))
                                        <div class="mt-1 text-xs text-gray-500 bg-gray-50 rounded px-2 py-1 max-w-sm">
                                            @foreach($props as $k => $v)
                                                <span class="font-medium">{{ $k }}:</span> {{ is_array($v) ? json_encode($v) : $v }}{{ !$loop->last ? ' · ' : '' }}
                                            @endforeach
                                        </div>
                                        @endif
                                    @endif
                                </div>
                            </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>
            </div>

        </div>{{-- end x-data tabs --}}
    </div>

    {{-- ══ SIDEBAR (4/12) ════════════════════════════════════════════════════ --}}
    <div class="col-span-12 lg:col-span-4 space-y-5">

        {{-- Customer Info card --}}
        <x-card title="{{ __('admin.customers_section.customer_info') }}">
            <div class="space-y-3 text-sm">
                <div class="flex flex-col items-center py-2">
                    <div class="w-16 h-16 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-2xl font-bold select-none mb-2">
                        {{ strtoupper(substr($customer->name, 0, 2)) }}
                    </div>
                    <p class="font-semibold text-gray-900 text-base">{{ $customer->name }}</p>
                    <p class="text-xs text-gray-400">#{{ $customer->id }}</p>
                </div>

                <div class="border-t border-gray-100 pt-3 space-y-2.5">
                    {{-- Email --}}
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-gray-500 shrink-0">{{ __('admin.customers_section.email_column') }}</span>
                        <div class="text-end min-w-0">
                            <button type="button"
                                class="text-gray-900 hover:text-primary-600 font-mono text-xs truncate js-copy"
                                data-value="{{ $customer->email }}"
                                title="{{ __('admin.customers_section.click_to_copy') }}">
                                {{ $customer->email }}
                            </button>
                            @if($customer->email_verified_at)
                                <x-badge color="success" class="ml-1">✓</x-badge>
                            @else
                                <x-badge color="warning" class="ml-1">{{ __('admin.customers_section.unverified') }}</x-badge>
                            @endif
                        </div>
                    </div>

                    {{-- Phone --}}
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-gray-500 shrink-0">{{ __('admin.customers_section.phone_column') }}</span>
                        <div class="text-end min-w-0">
                            @if($customer->phone)
                                <button type="button"
                                    class="text-gray-900 hover:text-primary-600 font-mono text-xs js-copy"
                                    data-value="{{ $customer->phone }}"
                                    title="{{ __('admin.customers_section.click_to_copy') }}">
                                    {{ $customer->phone }}
                                </button>
                                @if($customer->phone_verified_at)
                                    <x-badge color="success" class="ml-1">✓</x-badge>
                                @else
                                    <x-badge color="warning" class="ml-1">{{ __('admin.customers_section.unverified') }}</x-badge>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </div>
                    </div>

                    {{-- Country --}}
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500">{{ __('admin.customers_section.country_column') }}</span>
                        <span class="text-gray-900">
                            @if($customer->country)
                                {{ $customer->country->flag_emoji ?? '' }} {{ $customer->country->name_en }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </span>
                    </div>

                    {{-- Joined --}}
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500">{{ __('admin.customers_section.joined_column') }}</span>
                        <span class="text-gray-900">{{ $customer->created_at->format('d M Y') }}</span>
                    </div>

                    {{-- Last login --}}
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-gray-500 shrink-0">{{ __('admin.customers_section.last_login') }}</span>
                        <div class="text-end">
                            <span class="text-gray-900 block">{{ $customer->last_login_at?->format('d M Y, H:i') ?? '—' }}</span>
                            @if($customer->last_login_ip)
                                <span class="text-xs text-gray-400 font-mono">{{ $customer->last_login_ip }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- DOB --}}
                    @if($customer->date_of_birth)
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500">{{ __('admin.customers_section.date_of_birth') }}</span>
                        <span class="text-gray-900">{{ $customer->date_of_birth }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- QR Code card --}}
        <x-card title="{{ __('admin.customers_section.qr_code') }}">
            <div class="flex flex-col items-center gap-3 text-sm">
                @if($customer->qr_code_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($customer->qr_code_path) }}" class="w-40 h-40 rounded-lg border border-gray-200" alt="QR code">
                @else
                    <div class="w-40 h-40 rounded-lg border border-dashed border-gray-300 flex items-center justify-center text-xs text-gray-400 text-center px-2">
                        {{ __('admin.customers_section.qr_code_not_generated') }}
                    </div>
                @endif
                <button type="button" id="regenerate-qr-btn"
                    data-url="{{ route('admin.customers.regenerate-qr', $customer->id) }}"
                    class="text-xs font-medium text-primary-600 hover:text-primary-700 border border-primary-200 rounded-lg px-3 py-1.5 hover:bg-primary-50 transition-colors">
                    {{ __('admin.customers_section.regenerate_qr_code') }}
                </button>
            </div>
        </x-card>

        {{-- Stats card --}}
        <x-card title="{{ __('admin.customers_section.stats') }}">
            <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-gray-500">{{ __('admin.customers_section.total_orders') }}</span>
                    <span class="font-semibold text-gray-900">{{ number_format($customer->total_orders) }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-gray-500">{{ __('admin.customers_section.total_spent') }}</span>
                    <span class="font-semibold text-gray-900">{{ number_format((float) $customer->total_spent, 2) }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-gray-500">{{ __('admin.customers_section.loyalty_points') }}</span>
                    <span class="font-semibold text-primary-700" id="loyalty-balance-display">{{ number_format((float) $customer->loyalty_points, 2) }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-gray-500">{{ __('admin.customers_section.referral_code') }}</span>
                    @if($customer->referral_code)
                        <div class="flex flex-col items-end gap-1">
                            <button type="button"
                                class="text-gray-900 hover:text-primary-600 font-mono text-xs js-copy"
                                data-value="{{ $customer->referral_code }}"
                                title="{{ __('admin.customers_section.click_to_copy') }}">
                                {{ $customer->referral_code }}
                            </button>
                            <button type="button"
                                class="text-xs text-primary-500 hover:text-primary-700 js-copy"
                                data-value="{{ rtrim(config('app.url'), '/') }}/r/{{ $customer->referral_code }}"
                                title="Copy referral link">
                                Copy link
                            </button>
                        </div>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </div>
                @if($customer->referredBy)
                <div class="flex items-center justify-between gap-2">
                    <span class="text-gray-500">{{ __('admin.customers_section.referred_by') }}</span>
                    <a href="{{ route('admin.customers.show', $customer->referredBy->id) }}"
                       class="text-primary-600 hover:underline text-xs">
                        {{ $customer->referredBy->name }}
                    </a>
                </div>
                @endif
            </div>
        </x-card>

        {{-- Status & Actions card --}}
        <x-card title="{{ __('admin.customers_section.status_actions') }}">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <x-badge :color="$statusColor" size="md">{{ ucfirst($customer->status->value) }}</x-badge>
                </div>

                {{-- Action buttons --}}
                @if(auth('admin')->user()->can('customers.suspend'))
                <div class="flex flex-col gap-2">
                    @if($customer->status === \App\Enums\CustomerStatus::Active)
                        <button type="button"
                            class="btn btn-warning btn-sm w-full js-suspend-btn"
                            data-url="{{ route('admin.customers.suspend', $customer->id) }}"
                            data-name="{{ $customer->name }}"
                            data-modal="suspend-modal">
                            {{ __('admin.customers_section.suspend_customer') }}
                        </button>
                        <button type="button"
                            class="btn btn-danger btn-sm w-full js-ban-btn"
                            data-url="{{ route('admin.customers.ban', $customer->id) }}"
                            data-name="{{ $customer->name }}"
                            data-modal="ban-modal">
                            {{ __('admin.customers_section.ban_customer_btn') }}
                        </button>
                    @elseif($customer->status === \App\Enums\CustomerStatus::Suspended)
                        <button type="button"
                            class="btn btn-success btn-sm w-full js-reactivate-btn"
                            data-url="{{ route('admin.customers.reactivate', $customer->id) }}"
                            data-name="{{ $customer->name }}">
                            {{ __('admin.customers_section.reactivate') }}
                        </button>
                        <button type="button"
                            class="btn btn-danger btn-sm w-full js-ban-btn"
                            data-url="{{ route('admin.customers.ban', $customer->id) }}"
                            data-name="{{ $customer->name }}"
                            data-modal="ban-modal">
                            {{ __('admin.customers_section.escalate_to_ban') }}
                        </button>
                    @elseif($customer->status === \App\Enums\CustomerStatus::Banned)
                        <button type="button"
                            class="btn btn-success btn-sm w-full js-reactivate-btn"
                            data-url="{{ route('admin.customers.reactivate', $customer->id) }}"
                            data-name="{{ $customer->name }}">
                            {{ __('admin.customers_section.reactivate') }}
                        </button>
                    @endif
                </div>
                @endif

                @if(auth('admin')->user()->can('customers.edit'))
                <div class="border-t border-gray-100 pt-3 flex flex-col gap-2">
                    <button type="button"
                        id="open-loyalty-modal"
                        class="btn btn-secondary btn-sm w-full">
                        {{ __('admin.customers_section.adjust_loyalty_points') }}
                    </button>
                    <button type="button"
                        id="open-notification-modal"
                        class="btn btn-ghost btn-sm w-full">
                        {{ __('admin.customers_section.send_notification') }}
                    </button>
                </div>
                @endif

                @if(auth('admin')->user()->can('customers.view'))
                <div class="border-t border-gray-100 pt-3">
                    <a href="{{ route('admin.customers.export', $customer->id) }}"
                       class="btn btn-ghost btn-sm w-full text-center"
                       target="_blank">
                        {{ __('admin.customers_section.export_data_gdpr') }}
                    </a>
                </div>
                @endif
            </div>
        </x-card>

    </div>{{-- end sidebar --}}
</div>{{-- end grid --}}

{{-- ═══════════════════════════════════════════════════════════════════════════
  ─── MODALS ───────────────────────────────────────────────────────────────
══════════════════════════════════════════════════════════════════════════════ --}}

{{-- ─── Suspend modal ──────────────────────────────────────────────────────── --}}
<x-modal id="suspend-modal" title="{{ __('admin.customers_section.suspend_customer') }}" size="sm">
    <p class="text-sm text-gray-600 mb-3">{{ \Illuminate\Support\Str::before(__('admin.customers_section.suspend_customer_confirm'), ':name') }}<strong>{{ $customer->name }}</strong>{{ \Illuminate\Support\Str::after(__('admin.customers_section.suspend_customer_confirm'), ':name') }}</p>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.reason_label') }} <span class="text-danger-500">*</span></label>
        <textarea id="suspend-reason" class="form-input w-full resize-none" rows="3" placeholder="{{ __('admin.customers_section.enter_reason_placeholder') }}"></textarea>
    </div>
    <x-slot name="footer">
        <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.customers_section.cancel') }}</button>
        <button type="button" id="suspend-confirm-btn"
            class="btn btn-warning btn-sm"
            data-url="{{ route('admin.customers.suspend', $customer->id) }}">
            {{ __('admin.customers_section.suspend') }}
        </button>
    </x-slot>
</x-modal>

{{-- ─── Ban modal ───────────────────────────────────────────────────────────── --}}
<x-modal id="ban-modal" title="{{ __('admin.customers_section.ban_customer') }}" size="sm" :persistent="true">
    <p class="text-sm text-gray-600 mb-3">{{ \Illuminate\Support\Str::before(__('admin.customers_section.ban_customer_confirm'), ':name') }}<strong>{{ $customer->name }}</strong>{{ \Illuminate\Support\Str::after(__('admin.customers_section.ban_customer_confirm'), ':name') }}</p>
    <div class="mb-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.reason_label') }} <span class="text-danger-500">*</span></label>
        <textarea id="ban-reason" class="form-input w-full resize-none" rows="3" placeholder="{{ __('admin.customers_section.enter_reason_placeholder') }}"></textarea>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.type_confirm_hint') }} <code class="bg-gray-100 px-1 rounded text-xs">CONFIRM</code></label>
        <input type="text" id="ban-confirm-input" class="form-input w-full" placeholder="CONFIRM" autocomplete="off">
    </div>
    <x-slot name="footer">
        <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.customers_section.cancel') }}</button>
        <button type="button" id="ban-confirm-btn"
            class="btn btn-danger btn-sm"
            disabled
            data-url="{{ route('admin.customers.ban', $customer->id) }}">
            {{ __('admin.customers_section.ban_customer_btn') }}
        </button>
    </x-slot>
</x-modal>

{{-- ─── Adjust loyalty points modal ────────────────────────────────────────── --}}
<x-modal id="adjust-loyalty-modal" title="{{ __('admin.customers_section.adjust_loyalty_points') }}" size="sm">
    <div class="space-y-4">
        <div class="rounded-lg bg-primary-50 border border-primary-100 px-4 py-3 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-primary-700">{{ __('admin.customers_section.current_balance') }}</span>
                <span class="font-bold text-primary-800" id="current-loyalty">{{ number_format((float) $customer->loyalty_points, 2) }}</span>
            </div>
            <div class="flex items-center justify-between mt-1">
                <span class="text-primary-700">{{ __('admin.customers_section.new_balance') }}</span>
                <span class="font-bold text-primary-900" id="preview-loyalty">{{ number_format((float) $customer->loyalty_points, 2) }}</span>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('admin.customers_section.adjustment_label') }} <span class="text-danger-500">*</span>
                <span class="font-normal text-gray-400">{{ __('admin.customers_section.adjustment_hint') }}</span>
            </label>
            <input type="number" id="adjustment-input"
                class="form-input w-full"
                placeholder="{{ __('admin.customers_section.adjustment_placeholder') }}"
                data-current="{{ (float) $customer->loyalty_points }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.reason_label') }} <span class="text-danger-500">*</span></label>
            <input type="text" id="loyalty-reason" class="form-input w-full" placeholder="{{ __('admin.customers_section.reason_placeholder_compensation') }}">
        </div>
    </div>
    <x-slot name="footer">
        <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.customers_section.cancel') }}</button>
        <button type="button" id="loyalty-confirm-btn"
            class="btn btn-primary btn-sm"
            data-url="{{ route('admin.customers.adjust-loyalty', $customer->id) }}">
            {{ __('admin.customers_section.apply') }}
        </button>
    </x-slot>
</x-modal>

{{-- ─── Send notification modal ─────────────────────────────────────────────── --}}
<x-modal id="send-notification-modal" title="{{ __('admin.customers_section.send_notification') }}" size="sm">
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.channel_label') }} <span class="text-danger-500">*</span></label>
            <select id="notif-channel" class="form-input w-full">
                <option value="database">{{ __('admin.customers_section.in_app_database') }}</option>
                <option value="email">{{ __('admin.customers_section.email_channel') }}</option>
                <option value="sms">{{ __('admin.customers_section.sms_channel') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.title_label') }} <span class="text-danger-500">*</span></label>
            <input type="text" id="notif-title" class="form-input w-full" placeholder="{{ __('admin.customers_section.notification_title_placeholder') }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.customers_section.message_label') }} <span class="text-danger-500">*</span></label>
            <textarea id="notif-message" class="form-input w-full resize-none" rows="4" placeholder="{{ __('admin.customers_section.message_body_placeholder') }}"></textarea>
        </div>
    </div>
    <x-slot name="footer">
        <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.customers_section.cancel') }}</button>
        <button type="button" id="notif-send-btn"
            class="btn btn-primary btn-sm"
            data-url="{{ route('admin.customers.send-notification', $customer->id) }}">
            {{ __('admin.customers_section.send') }}
        </button>
    </x-slot>
</x-modal>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            pleaseWait: @json(__('admin.customers_section.please_wait')),
            searchPlaceholder: @json(__('admin.customers_section.search_ellipsis')),
            suspendCountPrompt: @json(__('admin.customers_section.suspend_count_prompt')),
            customersSuspended: @json(__('admin.customers_section.customers_suspended')),
            someActionsFailed: @json(__('admin.customers_section.some_actions_failed')),
            useFiltersExportHint: @json(__('admin.customers_section.use_filters_export_hint')),
            enterReason: @json(__('admin.customers_section.enter_reason')),
            suspend: @json(__('admin.customers_section.suspend')),
            customerSuspended: @json(__('admin.customers_section.customer_suspended')),
            actionFailed: @json(__('admin.customers_section.action_failed')),
            typeConfirmToProceed: @json(__('admin.customers_section.type_confirm_to_proceed')),
            banCustomer: @json(__('admin.customers_section.ban_customer')),
            customerBanned: @json(__('admin.customers_section.customer_banned')),
            reactivateConfirm: @json(__('admin.customers_section.reactivate_confirm')),
            thisCustomer: @json(__('admin.customers_section.this_customer')),
            customerReactivated: @json(__('admin.customers_section.customer_reactivated')),
            enterNonZeroAdjustment: @json(__('admin.customers_section.enter_non_zero_adjustment')),
            apply: @json(__('admin.customers_section.apply')),
            loyaltyPointsAdjusted: @json(__('admin.customers_section.loyalty_points_adjusted')),
            fillAllFields: @json(__('admin.customers_section.fill_all_fields')),
            send: @json(__('admin.customers_section.send')),
            notificationSent: @json(__('admin.customers_section.notification_sent')),
            failedToSend: @json(__('admin.customers_section.failed_to_send')),
            fullListDatatable: @json(__('admin.customers_section.full_list_datatable')),
            hideDatatable: @json(__('admin.customers_section.hide_datatable')),
            copied: @json(__('admin.customers_section.copied')),
            copyFailed: @json(__('admin.customers_section.copy_failed')),
            revokeAllDevices: @json(__('admin.customers_section.log_out_all_devices')),
            revokeDevicesConfirm: @json(__('admin.customers_section.log_out_all_devices_confirm')),
            devicesRevoked: @json(__('admin.customers_section.devices_revoked_success')),
            walletAdjusted: @json(__('admin.customers_section.wallet_adjusted')),
            enterValidAmount: @json(__('admin.customers_section.enter_valid_amount')),
            enterNote: @json(__('admin.customers_section.enter_note')),
            submitAdjustment: @json(__('admin.customers_section.submit_adjustment')),
        });
    </script>
    @vite(['resources/js/admin/customers.js'])
@endpush
