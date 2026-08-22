@extends('layouts.admin')

@section('title', $card->masked_code)

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $card->masked_code }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __('admin.gift_cards_section.status') }}: {{ __('admin.gift_cards_section.' . $card->status) }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                <div x-data="{ copied: false }" class="flex items-center gap-3">
                    <span class="text-gray-400 text-sm">{{ __('admin.gift_cards_section.full_code') }}:</span>
                    <code class="font-mono text-sm bg-gray-100 px-2 py-1 rounded" id="full-code">{{ $card->code }}</code>
                    <button type="button"
                            @click="navigator.clipboard.writeText(document.getElementById('full-code').textContent); copied = true; setTimeout(() => copied = false, 2000)"
                            class="btn btn-secondary btn-sm">
                        <span x-show="!copied">{{ __('admin.gift_cards_section.copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('admin.gift_cards_section.copied') }}</span>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 sm:grid-cols-3">
                    <div><span class="text-gray-400">{{ __('admin.gift_cards_section.currency_code') }}:</span> {{ $card->currency_code }}</div>
                    <div><span class="text-gray-400">{{ __('admin.gift_cards_section.amount') }}:</span> {{ (int) $card->amount }} {{ $card->currency_code }}</div>
                    <div><span class="text-gray-400">{{ __('admin.gift_cards_section.remaining_balance') }}:</span> {{ (int) $card->remaining_balance }} {{ $card->currency_code }}</div>
                    <div><span class="text-gray-400">{{ __('admin.gift_cards_section.source') }}:</span> {{ $card->source }}</div>
                    <div><span class="text-gray-400">{{ __('admin.gift_cards_section.expiry') }}:</span> {{ $card->expires_at?->format('Y-m-d H:i') ?? '—' }}</div>
                    <div><span class="text-gray-400">{{ __('admin.gift_cards_section.issued_to') }}:</span> {{ $card->issuedTo?->name ?? '—' }}</div>
                    <div>
                        <span class="text-gray-400">{{ __('admin.gift_cards_section.redeemed_by') }}:</span>
                        {{ $card->redeemedBy?->name ?? '—' }}
                        @if($card->redeemedBy && $card->redeemed_at)
                            ({{ $card->redeemed_at->format('Y-m-d H:i') }})
                        @endif
                    </div>
                </div>

                @php
                    $pct = $card->amount > 0 ? min(100, max(0, round(($card->remaining_balance / $card->amount) * 100))) : 0;
                @endphp
                <div>
                    <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                        <span>{{ __('admin.gift_cards_section.remaining_balance') }}</span>
                        <span>{{ (int) $card->remaining_balance }} / {{ (int) $card->amount }} {{ $card->currency_code }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('admin.gift_cards_section.transactions') }}</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 text-xs">
                            <th class="py-1">{{ __('admin.gift_cards_section.type') }}</th>
                            <th class="py-1">{{ __('admin.gift_cards_section.amount') }}</th>
                            <th class="py-1">{{ __('admin.gift_cards_section.balance_after') }}</th>
                            <th class="py-1">{{ __('admin.gift_cards_section.order_number') }}</th>
                            <th class="py-1">{{ __('admin.gift_cards_section.performed_by') }}</th>
                            <th class="py-1">{{ __('admin.gift_cards_section.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $typeColors = [
                                'issuance' => 'bg-blue-100 text-blue-700',
                                'redemption' => 'bg-emerald-100 text-emerald-700',
                                'refund' => 'bg-amber-100 text-amber-700',
                                'admin_adjustment' => 'bg-purple-100 text-purple-700',
                                'expiry' => 'bg-gray-100 text-gray-600',
                            ];
                        @endphp
                        @forelse($card->transactions as $tx)
                        <tr class="border-t border-gray-100">
                            <td class="py-1.5">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$tx->type] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ __('admin.gift_cards_section.' . $tx->type) ?: $tx->type }}
                                </span>
                            </td>
                            <td class="py-1.5">{{ $tx->amount }}</td>
                            <td class="py-1.5">{{ $tx->balance_after }}</td>
                            <td class="py-1.5">{{ $tx->order?->order_number ?? '—' }}</td>
                            <td class="py-1.5">{{ $tx->performedByAdmin?->name ?? $tx->performedByCustomer?->name ?? '—' }}</td>
                            <td class="py-1.5">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="py-3 text-center text-gray-400">{{ __('admin.gift_cards_section.no_transactions') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(auth('admin')->user()->can('gift_cards.edit'))
        <div class="space-y-4" x-data="{ adjustOpen: false }">
            <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
                @if($card->status === 'inactive')
                <form method="POST" action="{{ route('admin.gift-cards.activate', $card->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm w-full">{{ __('admin.gift_cards_section.activate_batch') }}</button>
                </form>
                @endif
                @if($card->status === 'active')
                <form method="POST" action="{{ route('admin.gift-cards.block', $card->id) }}" onsubmit="return confirm(@json(__('admin.gift_cards_section.activate_confirm')));">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm w-full">{{ __('admin.gift_cards_section.block_card') }}</button>
                </form>
                @endif
                <button type="button" @click="adjustOpen = true" class="btn btn-secondary btn-sm w-full">
                    {{ __('admin.gift_cards_section.adjust_balance') }}
                </button>
            </div>

            <div x-show="adjustOpen" x-cloak
                 class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
                 @keydown.escape.window="adjustOpen = false">
                <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6" @click.outside="adjustOpen = false">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('admin.gift_cards_section.adjust_balance') }}</h3>
                    <form method="POST" action="{{ route('admin.gift-cards.adjust', $card->id) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('admin.gift_cards_section.delta') }}</label>
                            <input type="number" name="delta" required class="form-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('admin.gift_cards_section.notes') }}</label>
                            <textarea name="notes" required maxlength="255" rows="3" class="form-textarea w-full"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" @click="adjustOpen = false" class="btn btn-secondary btn-sm">{{ __('admin.cancel') }}</button>
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.gift_cards_section.apply_adjustment') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection
