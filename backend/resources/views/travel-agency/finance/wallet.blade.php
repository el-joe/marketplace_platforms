@extends('layouts.travel-agency')

@section('title', __('travel.finance.wallet_title'))
@section('page-title', __('travel.finance.wallet_title'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Balance Card --}}
    <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 text-white shadow-lg">
        <p class="text-sm font-medium text-blue-200 mb-1">{{ __('travel.finance.wallet_title') }}</p>
        <p class="text-4xl font-extrabold tracking-tight">{{ number_format($wallet->balance, 2) }} <span class="text-xl font-semibold text-blue-300">{{ $wallet->currency }}</span></p>
        @if($wallet->pending_balance > 0)
            <p class="text-sm text-blue-300 mt-2">+ {{ number_format($wallet->pending_balance, 2) }} {{ $wallet->currency }}</p>
        @endif
        @if($wallet->is_frozen)
            <div class="mt-3 inline-flex items-center gap-1.5 bg-red-500/30 text-red-100 text-xs font-medium px-3 py-1 rounded-full">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                {{ $wallet->frozen_reason }}
            </div>
        @endif
    </div>

    {{-- Withdraw --}}
    @unless($wallet->is_frozen)
    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
        <h2 class="font-semibold text-gray-800 mb-4">{{ __('partner.wallet.request_withdrawal') }}</h2>
        <form method="POST" action="{{ route('travel-agency.finance.wallet.withdraw') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.wallet.amount') }} ({{ $wallet->currency }})</label>
                    <input type="number" name="amount" min="1" step="1" required
                           max="{{ $wallet->balance }}"
                           class="w-full form-input rounded-lg border-gray-300 text-sm" placeholder="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.wallet.bank_name') }}</label>
                    <input type="text" name="bank_name" required maxlength="150"
                           class="w-full form-input rounded-lg border-gray-300 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.wallet.bank_iban') }}</label>
                    <input type="text" name="bank_iban" required maxlength="50"
                           class="w-full form-input rounded-lg border-gray-300 text-sm font-mono">
                </div>
            </div>
            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition">
                {{ __('partner.wallet.request_withdrawal') }}
            </button>
        </form>
    </div>
    @endunless

    {{-- Withdrawal History --}}
    @if($withdrawalRequests->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">{{ __('partner.wallet.recent_withdrawal_requests') }}</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($withdrawalRequests as $wr)
                <div class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ number_format($wr->amount, 2) }} {{ $wr->currency }}</p>
                        <p class="text-xs text-gray-500">{{ $wr->bank_name }} · {{ $wr->created_at->format('d M Y') }}</p>
                    </div>
                    @php
                        $colors = ['pending'=>'bg-yellow-100 text-yellow-700','approved'=>'bg-blue-100 text-blue-700','processed'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700'];
                    @endphp
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $colors[$wr->status->value] ?? '' }}">{{ ucfirst($wr->status->value) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Transaction History --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">{{ __('partner.wallet.transaction_history') }}</h2>
        </div>
        @forelse($transactions as $tx)
            <div class="px-5 py-3 flex items-center justify-between border-b border-gray-50 last:border-0">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ $tx->description }}</p>
                    <p class="text-xs text-gray-400">{{ str_replace('_',' ', $tx->source_type) }} · {{ $tx->created_at->format('d M Y H:i') }}</p>
                </div>
                <p class="text-sm font-bold {{ $tx->type === \App\Enums\WalletTransactionType::Credit ? 'text-green-600' : 'text-red-500' }}">
                    {{ $tx->type === \App\Enums\WalletTransactionType::Credit ? '+' : '−' }}{{ number_format($tx->amount, 2) }}
                </p>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-gray-400">{{ __('partner.wallet.no_transactions_yet') }}</div>
        @endforelse
        @if($transactions->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">{{ $transactions->links() }}</div>
        @endif
    </div>

</div>
@endsection
