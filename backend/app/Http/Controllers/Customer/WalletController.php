<?php

namespace App\Http\Controllers\Customer;

use App\Enums\WalletOwnerType;
use App\Enums\WalletWithdrawalRequestStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\GiftCardBalanceRequest;
use App\Http\Requests\Api\Customer\RedeemGiftCardRequest;
use App\Http\Requests\Api\Customer\RedeemVoucherRequest;
use App\Http\Resources\Customer\WalletResource;
use App\Http\Resources\Customer\WalletTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Admin;
use App\Models\GiftCard;
use App\Models\Wallet;
use App\Models\WalletTransaction as WalletTransactionModel;
use App\Models\WalletWithdrawalRequest;
use App\Notifications\Admin\WithdrawalRequested;
use App\Services\GiftCardService;
use App\Services\VoucherService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Canonical customer-facing wallet controller (backed by the polymorphic
 * Wallet model, owner_type=customer). This is the single controller for
 * all wallet-related behavior: balance, transactions, bank withdrawal,
 * gift-card redemption, voucher redemption, and gift-card-balance lookup.
 *
 * It is deliberately wired to THREE route prefixes for backward
 * compatibility (`wallet/*`, `api-wallet/*`, `gift-card-wallet/*` in
 * routes/api_customer_v1.php) via differently-named methods that preserve
 * each family's historic request/response shape. See the wallet systems
 * merge plan (WALLET_MERGE_PLAN.md) — a later frontend wave will
 * consolidate the URLs and let the extra method names be dropped.
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly GiftCardService $giftCardService,
        private readonly VoucherService $voucherService,
    ) {}

    /*
    |--------------------------------------------------------------------
    | "wallet/*" family (customer.wallet.*) — country-scoped
    |--------------------------------------------------------------------
    */

    public function show(string $country): JsonResponse
    {
        $customer = auth('customer')->user();
        $currency = $customer->resolveCurrency();

        if (! $currency) {
            return ApiResponse::error('Unable to determine account currency. Please update your address.', [], 422);
        }

        $wallet = $this->walletService->getOrCreateWallet(
            WalletOwnerType::Customer->value,
            $customer->id,
            $currency,
        );

        return ApiResponse::success(new WalletResource($wallet));
    }

    public function transactions(string $country): JsonResponse
    {
        $customer = auth('customer')->user();
        $currency = $customer->resolveCurrency();

        if (! $currency) {
            return ApiResponse::error('Unable to determine account currency. Please update your address.', [], 422);
        }

        $wallet = $this->walletService->getOrCreateWallet(
            WalletOwnerType::Customer->value,
            $customer->id,
            $currency,
        );

        $transactions = $wallet->transactions()->paginate(15);

        return ApiResponse::paginated($transactions, WalletTransactionResource::class);
    }

    public function requestWithdrawal(Request $request, string $country): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:100'],
            'bank_name'    => ['required', 'string', 'max:150'],
            'bank_iban'    => ['required', 'string', 'max:50'],
        ]);

        $customer = auth('customer')->user();
        $currency = $customer->resolveCurrency();

        if (! $currency) {
            return ApiResponse::error('Unable to determine account currency. Please update your address.', [], 422);
        }

        $wallet = $this->walletService->getOrCreateWallet(
            WalletOwnerType::Customer->value,
            $customer->id,
            $currency,
        );

        if ($wallet->is_frozen) {
            return ApiResponse::error(__('common.exceptions.wallet.frozen_withdrawal'), [], 422);
        }

        if ($wallet->balance < $data['amount']) {
            return ApiResponse::error(__('common.exceptions.wallet.insufficient_balance'), [], 422);
        }

        try {
            $withdrawalRequest = $this->walletService->requestWithdrawal($wallet, $data['amount'], [
                'bank_name' => $data['bank_name'],
                'bank_iban' => $data['bank_iban'],
            ]);
        } catch (InsufficientBalanceException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        try {
            Notification::send(
                Admin::where('status', 'active')->get(),
                new WithdrawalRequested($withdrawalRequest),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return ApiResponse::success(null, __('common.exceptions.wallet.withdrawal_submitted'), 201);
    }

    /*
    |--------------------------------------------------------------------
    | "api-wallet/*" family (customer.api.wallet.*) — currency-list style
    |--------------------------------------------------------------------
    */

    public function apiIndex(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $wallets = Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $customer->id)
            ->get();

        return ApiResponse::success(\App\Http\Resources\Api\Customer\CustomerWalletResource::collection($wallets));
    }

    public function apiTransactions(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $walletsQuery = Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $customer->id);

        if ($currency = $request->query('currency')) {
            $walletsQuery->where('currency', $currency);
        }

        $walletIds = $walletsQuery->pluck('id');

        $paginator = WalletTransactionModel::whereIn('wallet_id', $walletIds)
            ->orderByDesc('created_at')
            ->paginate(15);

        $items = \App\Http\Resources\Api\Customer\WalletTransactionResource::collection(collect($paginator->items()));

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function apiWithdrawalRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_iban' => ['required', 'string', 'max:50'],
        ]);

        $customer = auth('customer')->user();

        $wallet = Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $customer->id)
            ->where('currency', $data['currency'])
            ->first();

        if (! $wallet) {
            return ApiResponse::error(__('customer_api.wallet.not_found_for_currency'), [], 404);
        }

        if ($wallet->is_frozen) {
            return ApiResponse::error(__('customer_api.wallet.frozen'), [], 422);
        }

        if ($wallet->getRawOriginal('balance') < $data['amount']) {
            return ApiResponse::error(__('customer_api.wallet.insufficient_balance'), [], 422);
        }

        $hasPending = $wallet->withdrawalRequests()
            ->where('status', WalletWithdrawalRequestStatus::Pending)
            ->exists();

        if ($hasPending) {
            return ApiResponse::error(__('customer_api.wallet.pending_withdrawal_exists'), [], 422);
        }

        $withdrawalRequest = WalletWithdrawalRequest::create([
            'wallet_id' => $wallet->id,
            'amount' => $data['amount'],
            'currency' => $wallet->currency,
            'bank_name' => $data['bank_name'],
            'bank_iban' => $data['bank_iban'],
            'status' => WalletWithdrawalRequestStatus::Pending,
        ]);

        return ApiResponse::success(
            new \App\Http\Resources\Api\Customer\WalletWithdrawalRequestResource($withdrawalRequest),
            __('customer_api.wallet.withdrawal_submitted'),
            201,
        );
    }

    /*
    |--------------------------------------------------------------------
    | "gift-card-wallet/*" family (customer.api.gift-card-wallet.*)
    |--------------------------------------------------------------------
    */

    public function giftCardWalletIndex(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $wallets = Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $customer->id)
            ->get();

        $country = $request->attributes->get('country')?->site_code;

        return ApiResponse::success([
            'wallets' => $wallets->map(fn (Wallet $wallet) => [
                'currency_code' => $wallet->currency,
                'balance' => $wallet->balance,
                'balance_display' => $wallet->currency.' '.$wallet->balance,
                'updated_at' => $wallet->updated_at?->toIso8601String(),
            ]),
            'actions' => [
                'redeem_gift_card' => route('customer.api.gift-card-wallet.redeem-gift-card', [$country]),
                'redeem_voucher' => route('customer.api.gift-card-wallet.redeem.voucher', [$country]),
                'transactions' => route('customer.api.gift-card-wallet.transactions', [$country]),
                'gift_card_balance' => route('customer.api.gift-card-wallet.gift_card.balance', [$country]),
            ],
        ]);
    }

    public function giftCardWalletTransactions(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $query = WalletTransactionModel::where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        if ($request->filled('currency')) {
            $query->where('currency_code', $request->string('currency')->value());
        }

        $paginator = $query->paginate(20);

        $items = collect($paginator->items())->map(fn ($transaction) => [
            'type' => $transaction->type,
            'direction' => $transaction->direction,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'currency_code' => $transaction->currency_code,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at,
        ]);

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function redeemGiftCard(RedeemGiftCardRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();

        try {
            $result = $this->giftCardService->redeemToWallet(
                $request->string('code')->value(),
                $request->string('pin')->value(),
                $customer,
            );
        } catch (ValidationException $e) {
            return ApiResponse::error($e->validator->errors()->first());
        }

        return ApiResponse::success([
            'gift_card' => [
                'code_masked' => $result['card_code_masked'],
                'amount_credited' => $result['amount_credited'],
                'currency_code' => $result['currency_code'],
            ],
            'wallet' => [
                'currency_code' => $result['currency_code'],
                'new_balance' => $result['new_balance'],
                'new_balance_display' => $result['currency_code'].' '.$result['new_balance'],
            ],
        ], __('customer_api.customer_wallet.gift_card_redeemed', [
            'currency' => $result['currency_code'],
            'amount' => $result['amount_credited'],
        ]));
    }

    public function redeemVoucher(RedeemVoucherRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();

        try {
            $voucher = $this->voucherService->validate($request->string('code')->value(), $customer);
            $result = $this->voucherService->redeem($voucher, $customer);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->validator->errors()->first());
        }

        return ApiResponse::success([
            'voucher' => [
                'code' => $voucher->code,
                'title' => $result['title'],
                'amount_credited' => $result['amount_credited'],
                'currency_code' => $result['currency_code'],
            ],
            'wallet' => [
                'currency_code' => $result['currency_code'],
                'new_balance' => $result['new_balance'],
                'new_balance_display' => $result['currency_code'].' '.$result['new_balance'],
            ],
        ], __('customer_api.customer_wallet.voucher_redeemed', [
            'currency' => $result['currency_code'],
            'amount' => $result['amount_credited'],
        ]));
    }

    public function giftCardBalance(GiftCardBalanceRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $card = GiftCard::whereRaw('UPPER(code) = ?', [strtoupper($request->string('code')->value())])
            ->where('status', 'active')
            ->first();

        $accessible = $card
            && (! $card->expires_at || $card->expires_at->isFuture())
            && (! $card->issued_to_customer_id || $card->issued_to_customer_id === $customer->id)
            && $card->remaining_balance > 0;

        if ($accessible) {
            return ApiResponse::success([
                'found' => true,
                'code_masked' => $card->masked_code,
                'currency_code' => $card->currency_code,
                'remaining_balance' => $card->remaining_balance,
                'remaining_balance_display' => $card->currency_code.' '.$card->remaining_balance,
                'expires_at' => $card->expires_at?->toIso8601String(),
            ]);
        }

        return ApiResponse::success(['found' => false]);
    }
}
