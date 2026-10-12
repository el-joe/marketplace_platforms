@extends('layouts.marketer')
@section('title', __('marketer.finance.wallet_title'))
@section('page-title', __('marketer.finance.wallet_title'))

@section('content')
<div class="max-w-3xl space-y-5">

    @if(!empty($error))
        <div class="p-4 bg-red-100 text-red-800 rounded-lg text-sm">{{ $error }}</div>
    @else
        {{-- Balance card --}}
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 text-white">
            <p class="text-sm font-medium text-gray-300 mb-1">{{ __('marketer.finance.available_balance') }}</p>
            <p class="text-4xl font-black">{{ \App\Support\Money::format($wallet->balance) }} <span class="text-lg font-semibold text-yellow-400">{{ $wallet->currency }}</span></p>
            @if($wallet->pending_balance > 0)
                <p class="text-sm text-gray-300 mt-2">+ {{ \App\Support\Money::format($wallet->pending_balance) }} {{ $wallet->currency }} {{ __('marketer.finance.pending_withdrawal_hint') }}</p>
            @endif
            @if($wallet->is_frozen)
                <div class="mt-3 inline-flex items-center gap-1.5 bg-red-500/30 text-red-100 text-xs font-medium px-3 py-1 rounded-full">
                    {{ __('marketer.finance.wallet_frozen_badge') }}
                </div>
            @endif
        </div>

        {{-- Net statement (P11) --}}
        <div class="bg-white rounded-xl border p-5" data-wallet-statement>
            <h2 class="font-semibold text-gray-800 mb-3">{{ __('marketer.finance.statement_title') }}</h2>
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.gross_header') }}</dt><dd class="font-bold">{{ \App\Support\Money::format($statementTotals['gross']) }}</dd></div>
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.cut_header') }}</dt><dd class="font-bold text-red-600">−{{ \App\Support\Money::format($statementTotals['cut']) }}</dd></div>
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.vat_header') }}</dt><dd class="font-bold text-red-600">−{{ \App\Support\Money::format($statementTotals['vat']) }}</dd></div>
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.net_header') }}</dt><dd class="font-bold text-green-600">{{ \App\Support\Money::format($statementTotals['net']) }}</dd></div>
            </dl>
            <p class="text-xs text-gray-400 mt-2">{{ __('marketer.finance.withdraw_net_hint') }}</p>
        </div>

        {{-- Sales of own listings (seller = marketer) --}}
        @if(($salesTotals['count'] ?? 0) > 0)
        <div class="bg-white rounded-xl border p-5" data-wallet-sales>
            <h2 class="font-semibold text-gray-800 mb-3">{{ __('marketer.finance.sales_title') }}</h2>
            <dl class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.gross_header') }}</dt><dd class="font-bold">{{ \App\Support\Money::format($salesTotals['gross']) }}</dd></div>
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.sales_commission_header') }}</dt><dd class="font-bold text-red-600">−{{ \App\Support\Money::format($salesTotals['commission']) }}</dd></div>
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.vat_header') }}</dt><dd class="font-bold text-red-600">−{{ \App\Support\Money::format($salesTotals['vat']) }}</dd></div>
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.sales_peer_header') }}</dt><dd class="font-bold text-red-600">−{{ \App\Support\Money::format($salesTotals['peer']) }}</dd></div>
                <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.net_header') }}</dt><dd class="font-bold text-green-600">{{ \App\Support\Money::format($salesTotals['net']) }}</dd></div>
            </dl>
            <p class="text-xs text-gray-400 mt-2">{{ __('marketer.finance.sales_hint') }}</p>
        </div>
        @endif

        {{-- Withdraw --}}
        @unless($wallet->is_frozen)
        <div class="bg-white rounded-xl border p-5">
            <h2 class="font-semibold text-gray-800 mb-4">{{ __('marketer.finance.withdrawal_request_heading') }}</h2>
            <form method="POST" action="{{ route('marketer.finance.wallet.withdraw') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('marketer.finance.amount_label') }} ({{ $wallet->currency }})</label>
                        <input type="number" name="amount" min="1" step="1" required max="{{ $wallet->balance }}"
                               class="w-full form-input rounded-lg border-gray-300 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('marketer.finance.bank_name_label') }}</label>
                        <input type="text" name="bank_name" required maxlength="150"
                               class="w-full form-input rounded-lg border-gray-300 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('marketer.finance.iban_label') }}</label>
                        <input type="text" name="bank_iban" required maxlength="50"
                               class="w-full form-input rounded-lg border-gray-300 text-sm font-mono">
                    </div>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-yellow-500 text-gray-900 text-sm font-semibold rounded-xl hover:bg-yellow-400 transition">
                    {{ __('marketer.finance.submit_withdrawal') }}
                </button>
            </form>
        </div>
        @endunless

        {{-- Withdrawal history --}}
        @if($withdrawalRequests->isNotEmpty())
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-4 border-b"><h2 class="font-semibold text-gray-800">{{ __('marketer.finance.recent_withdrawals_heading') }}</h2></div>
            <div class="divide-y">
                @foreach($withdrawalRequests as $wr)
                    @php
                        $colors = ['pending'=>'bg-yellow-100 text-yellow-700','approved'=>'bg-blue-100 text-blue-700','processed'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700'];
                        $labels = [
                            'pending'   => __('marketer.finance.withdrawal_status_pending'),
                            'approved'  => __('marketer.finance.withdrawal_status_approved'),
                            'processed' => __('marketer.finance.withdrawal_status_processed'),
                            'rejected'  => __('marketer.finance.withdrawal_status_rejected'),
                        ];
                    @endphp
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ \App\Support\Money::format($wr->amount) }} {{ $wr->currency }}</p>
                            <p class="text-xs text-gray-500">{{ $wr->bank_name }} · {{ $wr->created_at->format('d M Y') }}</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $colors[$wr->status->value] ?? '' }}">
                            {{ $labels[$wr->status->value] ?? $wr->status->value }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Transaction history --}}
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-4 border-b"><h2 class="font-semibold text-gray-800">{{ __('marketer.finance.transaction_history_heading') }}</h2></div>
            @forelse($transactions as $tx)
                <div class="px-5 py-3 flex items-center justify-between border-b last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $tx->description }}</p>
                        <p class="text-xs text-gray-400">{{ $tx->created_at->format('d M Y H:i') }}</p>
                    </div>
                    <p class="text-sm font-bold {{ (($tx->type instanceof \BackedEnum ? $tx->type->value : $tx->type) === 'credit') ? 'text-green-600' : 'text-red-500' }}">
                        {{ (($tx->type instanceof \BackedEnum ? $tx->type->value : $tx->type) === 'credit') ? '+' : '−' }}{{ \App\Support\Money::format($tx->amount) }}
                    </p>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-gray-400">{{ __('marketer.finance.no_transactions') }}</div>
            @endforelse
            @if($transactions->hasPages())
                <div class="px-5 py-3 border-t">{{ $transactions->links() }}</div>
            @endif
        </div>
    @endif
</div>
@endsection
