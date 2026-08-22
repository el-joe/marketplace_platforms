<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\WalletOwnerType;
use App\Enums\WalletWithdrawalRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\CustomerWalletResource;
use App\Http\Resources\Api\Customer\WalletTransactionResource;
use App\Http\Resources\Api\Customer\WalletWithdrawalRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $wallets = Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $customer->id)
            ->get();

        return ApiResponse::success(CustomerWalletResource::collection($wallets));
    }

    public function transactions(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $walletsQuery = Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $customer->id);

        if ($currency = $request->query('currency')) {
            $walletsQuery->where('currency', $currency);
        }

        $walletIds = $walletsQuery->pluck('id');

        $paginator = \App\Models\WalletTransaction::whereIn('wallet_id', $walletIds)
            ->orderByDesc('created_at')
            ->paginate(15);

        $items = WalletTransactionResource::collection(collect($paginator->items()));

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

    public function withdrawalRequest(Request $request): JsonResponse
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

        $withdrawalRequest = \App\Models\WalletWithdrawalRequest::create([
            'wallet_id' => $wallet->id,
            'amount' => $data['amount'],
            'currency' => $wallet->currency,
            'bank_name' => $data['bank_name'],
            'bank_iban' => $data['bank_iban'],
            'status' => WalletWithdrawalRequestStatus::Pending,
        ]);

        return ApiResponse::success(
            new WalletWithdrawalRequestResource($withdrawalRequest),
            __('customer_api.wallet.withdrawal_submitted'),
            201,
        );
    }
}
