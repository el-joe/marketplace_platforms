@extends('layouts.partner')

@section('title', __('partner.bank_accounts.title'))
@section('page-title', __('partner.bank_accounts.title'))

@push('scripts')
    @vite('resources/js/partner/bank-accounts.js')
    <script>
        window.BANK_ACCOUNTS = {
            storeUrl:      '{{ route('partner.bank-accounts.store') }}',
            setPrimaryUrl: '{{ url('bank-accounts') }}',
            deleteUrl:     '{{ url('bank-accounts') }}',
            csrf:          '{{ csrf_token() }}',
        };
        window.PARTNER_TRANSLATIONS = window.PARTNER_TRANSLATIONS || {};
        Object.assign(window.PARTNER_TRANSLATIONS, {
            confirmDeleteBankAccount: @json(__('partner.bank_accounts.confirm_delete')),
        });
    </script>
@endpush

@section('content')

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-500">{{ __('partner.bank_accounts.verified_only_note') }}</p>
        <button id="btn-add-account"
                class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('partner.bank_accounts.add_account') }}
        </button>
    </div>

    {{-- Verification notice --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-blue-700">
            {!! __('partner.bank_accounts.verification_notice', ['days' => '<strong>2</strong>']) !!}
        </p>
    </div>

    {{-- Section always locked notice --}}
    <div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6 flex items-start gap-3">
        <span>🔒</span>
        <p class="text-sm text-yellow-800">{{ __('partner.profile.section_locked_generic') }}</p>
    </div>

    @if ($pendingChangeRequests->isNotEmpty())
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6">
            <p class="text-sm text-gray-600 mb-1">{{ __('partner.profile.change_request_pending_notice') }}</p>
            <a href="{{ route('partner.change-requests.index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-800">
                {{ __('partner.profile.view_change_requests') }}
            </a>
        </div>
    @endif

    {{-- Accounts grid --}}
    <div id="accounts-container">
        @if($accounts->isEmpty())
            <div id="empty-state" class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
                <div class="text-4xl mb-3">🏦</div>
                <h3 class="font-semibold text-gray-800 mb-1">{{ __('partner.bank_accounts.no_accounts') }}</h3>
                <p class="text-sm text-gray-400 mb-4">{{ __('partner.bank_accounts.no_accounts_desc') }}</p>
                <button id="btn-add-account-empty"
                        class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors">
                    {{ __('partner.bank_accounts.add_account') }}
                </button>
            </div>
        @else
            <div id="empty-state" class="hidden"></div>
            <div id="accounts-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($accounts as $account)
                    @php
                        $verificationMap = [
                            'pending'  => ['bg-yellow-100 text-yellow-700', __('partner.bank_accounts.verification.pending')],
                            'verified' => ['bg-green-100 text-green-700',   __('partner.bank_accounts.verification.verified')],
                            'rejected' => ['bg-red-100 text-red-700',       __('partner.bank_accounts.verification.rejected')],
                        ];
                        [$verifyCls, $verifyLabel] = $verificationMap[$account->verification_status->value]
                            ?? ['bg-gray-100 text-gray-500', $account->verification_status->value];

                        $clean  = preg_replace('/\s+/', '', $account->iban);
                        $len    = strlen($clean);
                        $masked = $len > 4 ? str_repeat('*', $len - 4) . substr($clean, -4) : $clean;
                    @endphp
                    <div class="bank-account-card bg-white rounded-2xl border {{ $account->is_primary ? 'border-yellow-400' : 'border-gray-200' }} p-5"
                         data-id="{{ $account->id }}"
                         data-status="{{ $account->verification_status->value }}"
                         data-primary="{{ $account->is_primary ? '1' : '0' }}">

                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M3 6l9-3 9 3M3 6v14l9 3 9-3V6M3 6l9 3 9-3"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm">{{ $account->bank_name }}</p>
                                    @if($account->branch)
                                        <p class="text-xs text-gray-400">{{ $account->branch }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                @if($account->is_primary)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                        {{ __('partner.bank_accounts.primary_badge') }} ★
                                    </span>
                                @endif
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $verifyCls }}">
                                    {{ $verifyLabel }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-1.5 text-sm mb-4">
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('partner.bank_accounts.account_holder') }}</span>
                                <span class="font-medium text-gray-800">{{ $account->account_holder_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('partner.bank_accounts.iban') }}</span>
                                <span class="font-mono text-xs text-gray-700 tracking-widest">{{ $masked }}</span>
                            </div>
                            @if($account->swift_code)
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ __('partner.bank_accounts.swift_label') }}</span>
                                    <span class="font-mono text-xs text-gray-700">{{ $account->swift_code }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('partner.bank_accounts.currency') }}</span>
                                <span class="font-medium text-gray-800">{{ $account->currency }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-3 border-t border-gray-50">
                            @if(!$account->is_primary && $account->verification_status === \App\Enums\VendorBankAccountVerificationStatus::Verified)
                                <button type="button"
                                        class="btn-set-primary flex-1 text-xs bg-yellow-50 hover:bg-yellow-100 text-yellow-700 font-medium py-2 rounded-lg transition-colors"
                                        data-id="{{ $account->id }}">
                                    {{ __('partner.bank_accounts.set_primary') }}
                                </button>
                            @elseif($account->is_primary)
                                <span class="flex-1 text-xs text-center text-gray-400 py-2">{{ __('partner.bank_accounts.current_primary') }}</span>
                            @else
                                <span class="flex-1 text-xs text-center text-gray-300 py-2">—</span>
                            @endif
                            <button type="button"
                                    class="btn-delete-account text-xs bg-red-50 hover:bg-red-100 text-red-600 font-medium px-3 py-2 rounded-lg transition-colors"
                                    data-id="{{ $account->id }}">
                                {{ __('partner.bank_accounts.delete') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Add Bank Account Modal --}}
    <div id="add-account-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">{{ __('partner.bank_accounts.add_new_account') }}</h3>
                <button id="modal-close-btn" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="add-account-form" class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            {{ __('partner.bank_accounts.holder_name_label') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="account_holder_name" required
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                               placeholder="{{ __('partner.bank_accounts.holder_placeholder') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            {{ __('partner.bank_accounts.bank_name_label') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="bank_name" required
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                               placeholder="{{ __('partner.bank_accounts.bank_name_placeholder') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.bank_accounts.branch_label') }}</label>
                        <input type="text" name="branch"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                               placeholder="{{ __('partner.bank_accounts.branch_placeholder') }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            IBAN <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="iban" required maxlength="34" dir="ltr"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                               placeholder="{{ __('partner.bank_accounts.iban_placeholder') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.bank_accounts.swift_label') }}</label>
                        <input type="text" name="swift_code" maxlength="11" dir="ltr"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                               placeholder="RJHISARI">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            {{ __('partner.bank_accounts.currency') }} <span class="text-red-500">*</span>
                        </label>
                        <select name="currency" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                            <option value="">{{ __('partner.bank_accounts.currency_select') }}</option>
                            @foreach(\App\Models\Currency::where('is_active', true)->orderBy('code')->get() as $cur)
                                <option value="{{ $cur->code }}">{{ $cur->code }}{{ $cur->name ? ' — ' . $cur->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.profile.change_request_reason') }}</label>
                    <textarea name="note" rows="2"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"></textarea>
                </div>

                <div id="account-form-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>

                <div class="flex gap-2 pt-1">
                    <button type="submit" id="account-submit-btn"
                            class="flex-1 bg-gray-900 hover:bg-gray-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                        {{ __('partner.bank_accounts.submit_account') }}
                    </button>
                    <button type="button" id="modal-cancel-btn"
                            class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
                        {{ __('common.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection
