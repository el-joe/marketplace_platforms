@extends('layouts.delivery')

@section('title', __('delivery.wallet.title'))

@section('content')

    @if(session('success'))
        <div class="mx-4 mb-4 rounded-xl bg-green-900/30 border border-green-500/30 px-4 py-3 text-green-300 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Balance Card --}}
    <div class="mx-4 mb-5 bg-gradient-to-br from-slate-700 to-slate-900 rounded-2xl p-5 border border-slate-600">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">{{ __('delivery.wallet.available_balance') }}</p>
        <p class="text-4xl font-extrabold text-white tracking-tight">{{ number_format($wallet->balance, 2) }}</p>
        <p class="text-sm text-slate-400 mt-0.5">{{ $wallet->currency }}</p>
        @if($wallet->pending_balance > 0)
            <p class="text-xs text-yellow-400 mt-2">{{ number_format($wallet->pending_balance, 2) }} {{ $wallet->currency }} {{ __('delivery.wallet.pending_withdrawal') }}</p>
        @endif
        @if($wallet->is_frozen)
            <div class="mt-3 inline-flex items-center gap-1.5 bg-red-500/20 text-red-400 text-xs font-medium px-3 py-1 rounded-full border border-red-500/30">{{ __('delivery.wallet.wallet_frozen') }}</div>
        @endif
    </div>

    {{-- Withdraw --}}
    @unless($wallet->is_frozen)
    <div class="mx-4 mb-5 d-card">
        <h2 class="text-sm font-bold text-slate-300 mb-4">{{ __('delivery.wallet.request_withdrawal') }}</h2>
        <form method="POST" action="{{ route('delivery.wallet.withdraw') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs text-slate-400 mb-1">{{ __('delivery.wallet.amount') }} ({{ $wallet->currency }})</label>
                <input type="number" name="amount" min="1" step="0.01" required max="{{ $wallet->balance }}"
                       class="w-full bg-slate-800 border border-slate-600 rounded-xl px-3 py-2.5 text-white text-sm focus:outline-none focus:border-blue-500" placeholder="0.00">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">{{ __('delivery.wallet.bank_name') }}</label>
                <input type="text" name="bank_name" required maxlength="150"
                       class="w-full bg-slate-800 border border-slate-600 rounded-xl px-3 py-2.5 text-white text-sm focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">{{ __('delivery.wallet.bank_iban') }}</label>
                <input type="text" name="bank_iban" required maxlength="50"
                       class="w-full bg-slate-800 border border-slate-600 rounded-xl px-3 py-2.5 text-white text-sm font-mono focus:outline-none focus:border-blue-500">
            </div>
            <button type="submit" class="w-full py-3 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition">
                {{ __('delivery.wallet.request_withdrawal') }}
            </button>
        </form>
    </div>
    @endunless

    {{-- Withdrawal History --}}
    @if($withdrawalRequests->isNotEmpty())
    <h2 class="mx-4 text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.wallet.withdrawal_requests') }}</h2>
    <div class="mx-4 mb-5 space-y-2">
        @foreach($withdrawalRequests as $wr)
            @php $colors = ['pending'=>'text-yellow-400','approved'=>'text-blue-400','processed'=>'text-green-400','rejected'=>'text-red-400']; @endphp
            <div class="d-card flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-white">{{ number_format($wr->amount, 2) }} {{ $wr->currency }}</p>
                    <p class="text-xs text-slate-400">{{ $wr->bank_name }} · {{ $wr->created_at->format('d M Y') }}</p>
                </div>
                <span class="text-xs font-bold {{ $colors[$wr->status->value] ?? 'text-slate-400' }}">{{ ucfirst($wr->status->value) }}</span>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Transaction History --}}
    <h2 class="mx-4 text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ __('delivery.wallet.transactions') }}</h2>
    <div class="mx-4 mb-5 space-y-2">
        @forelse($transactions as $tx)
            <div class="d-card flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white">{{ $tx->description }}</p>
                    <p class="text-xs text-slate-400">{{ str_replace('_',' ', $tx->source_type) }} · {{ $tx->created_at->format('d M H:i') }}</p>
                </div>
                <p class="text-sm font-bold {{ $tx->type === \App\Enums\WalletTransactionType::Credit ? 'text-green-400' : 'text-red-400' }}">
                    {{ $tx->type === \App\Enums\WalletTransactionType::Credit ? '+' : '−' }}{{ number_format($tx->amount, 2) }}
                </p>
            </div>
        @empty
            <div class="d-card text-center py-8 text-sm text-slate-500">{{ __('delivery.wallet.no_transactions_yet') }}</div>
        @endforelse
        @if($transactions->hasPages())
            <div class="py-3">{{ $transactions->links() }}</div>
        @endif
    </div>

@endsection
