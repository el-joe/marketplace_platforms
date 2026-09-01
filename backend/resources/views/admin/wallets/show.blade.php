@extends('layouts.admin')

@section('title', __('admin.wallets.wallet_title_prefix', ['name' => $wallet->owner?->name ?? $wallet->owner_id]))

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.wallets.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $wallet->owner?->name ?? $wallet->owner_id }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.wallets.' . $wallet->owner_type->value) }} · {{ $wallet->currency }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Balance Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('admin.wallets.balance') }}</p>
            <p class="text-3xl font-extrabold text-gray-900">{{ number_format($wallet->balance, 2) }} <span class="text-base font-normal text-gray-500">{{ $wallet->currency }}</span></p>
        </div>
        <div class="bg-white rounded-xl border border-amber-200 p-5">
            <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-1">{{ __('admin.wallets.pending') }}</p>
            <p class="text-3xl font-extrabold text-amber-700">{{ number_format($wallet->pending_balance, 2) }} <span class="text-base font-normal text-amber-500">{{ $wallet->currency }}</span></p>
        </div>
        <div class="bg-white rounded-xl border {{ $wallet->is_frozen ? 'border-red-300' : 'border-green-200' }} p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('admin.wallets.status') }}</p>
                <p class="text-lg font-bold {{ $wallet->is_frozen ? 'text-red-600' : 'text-green-600' }}">{{ $wallet->is_frozen ? __('admin.wallets.frozen') : __('admin.wallets.active') }}</p>
                @if($wallet->frozen_reason)<p class="text-xs text-gray-500 mt-1">{{ $wallet->frozen_reason }}</p>@endif
            </div>
            @if($wallet->is_frozen)
                <form method="POST" action="{{ route('admin.wallets.unfreeze', $wallet) }}">
                    @csrf @method('PATCH')
                    <button class="text-xs px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700">{{ __('admin.wallets.unfreeze') }}</button>
                </form>
            @else
                <button onclick="document.getElementById('freeze-modal').classList.remove('hidden')" class="text-xs px-3 py-1.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">{{ __('admin.wallets.freeze') }}</button>
            @endif
        </div>
    </div>

    {{-- Adjust Balance --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-800 mb-4">{{ __('admin.wallets.manual_adjustment') }}</h2>
        <form method="POST" action="{{ route('admin.wallets.adjust', $wallet) }}" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.wallets.type') }}</label>
                <select name="type" class="form-select text-sm rounded-lg border-gray-300">
                    <option value="credit">{{ __('admin.wallets.credit') }}</option>
                    <option value="debit">{{ __('admin.wallets.debit') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.wallets.amount') }} ({{ $wallet->currency }})</label>
                <input type="number" name="amount" min="1" step="1" required class="form-input text-sm rounded-lg border-gray-300 w-36" placeholder="0">
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.wallets.description') }}</label>
                <input type="text" name="description" required maxlength="255" class="form-input text-sm rounded-lg border-gray-300 w-full" placeholder="{{ __('admin.wallets.reason_for_adjustment') }}">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">{{ __('admin.wallets.apply') }}</button>
        </form>
    </div>

    {{-- Transaction Ledger --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">{{ __('admin.wallets.transaction_history') }}</h2>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.date') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.type') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.source') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.description') }}</th>
                    <th class="px-4 py-3 text-end font-medium text-gray-600">{{ __('admin.wallets.amount') }}</th>
                    <th class="px-4 py-3 text-end font-medium text-gray-600">{{ __('admin.wallets.balance_after') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($transactions as $tx)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $tx->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if($tx->type->value === 'credit')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">+ {{ __('admin.wallets.credit') }}</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">− {{ __('admin.wallets.debit') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ str_replace('_',' ', $tx->source_type) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $tx->description }}</td>
                        <td class="px-4 py-3 text-end font-semibold {{ $tx->type->value === 'credit' ? 'text-green-700' : 'text-red-600' }}">
                            {{ $tx->type->value === 'credit' ? '+' : '−' }}{{ number_format($tx->amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-end text-gray-700">{{ number_format($tx->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('admin.wallets.no_transactions_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">{{ $transactions->links() }}</div>
    </div>

</div>

{{-- Freeze Modal --}}
<div id="freeze-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('admin.wallets.freeze_wallet') }}</h3>
        <form method="POST" action="{{ route('admin.wallets.freeze', $wallet) }}">
            @csrf @method('PATCH')
            <textarea name="reason" required rows="3" placeholder="{{ __('admin.wallets.freeze_reason_placeholder') }}" class="w-full form-textarea text-sm rounded-lg border-gray-300 mb-4"></textarea>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('freeze-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">{{ __('admin.wallets.cancel') }}</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700">{{ __('admin.wallets.freeze') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
