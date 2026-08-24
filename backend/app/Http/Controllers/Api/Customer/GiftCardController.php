<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\CustomerGiftCardResource;
use App\Http\Resources\Api\Customer\GiftCardValidationResource;
use App\Http\Responses\ApiResponse;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'currency' => ['required', 'string'],
        ]);

        $giftCard = GiftCard::where('code', $data['code'])
            ->where('status', 'active')
            ->where('currency_code', $data['currency'])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $giftCard) {
            return ApiResponse::error(__('customer_api.gift_card.invalid'), [], 422);
        }

        return ApiResponse::success((new GiftCardValidationResource($giftCard))->toArray($request));
    }

    public function mine(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $giftCards = GiftCard::where('purchased_by_customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(CustomerGiftCardResource::collection($giftCards));
    }
}
