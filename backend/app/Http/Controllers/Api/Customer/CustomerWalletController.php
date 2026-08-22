<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\GiftCardBalanceRequest;
use App\Http\Requests\Api\Customer\RedeemGiftCardRequest;
use App\Http\Requests\Api\Customer\RedeemVoucherRequest;
use App\Http\Responses\ApiResponse;
use App\Models\CustomerWallet;
use App\Models\GiftCard;
use App\Services\GiftCardService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerWalletController extends Controller
{
    public function __construct(
        private readonly GiftCardService $giftCardService,
        private readonly VoucherService $voucherService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $wallets = CustomerWallet::where('customer_id', $customer->id)->get();

        return ApiResponse::success([
            'wallets' => $wallets->map(fn (CustomerWallet $wallet) => [
                'currency_code' => $wallet->currency_code,
                'balance' => $wallet->balance,
                'balance_display' => $wallet->currency_code.' '.$wallet->balance,
                'updated_at' => $wallet->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $query = DB::table('wallet_transactions')
            ->where('customer_id', $customer->id)
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
