<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\PurchaseGiftCardRequest;
use App\Http\Resources\Api\Customer\GiftCardBatchResource;
use App\Http\Resources\Api\Customer\GiftCardPurchaseResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\SendGiftCardNotificationJob;
use App\Models\GiftCardBatch;
use App\Models\GiftCardPurchase;
use App\Services\GiftCardPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerGiftCardStoreController extends Controller
{
    public function __construct(
        private readonly GiftCardPurchaseService $giftCardPurchaseService,
    ) {
    }

    public function available(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currency_code' => ['required', 'string'],
        ]);

        $batches = $this->giftCardPurchaseService->getAvailableBatches($data['currency_code']);

        return ApiResponse::success(GiftCardBatchResource::collection($batches));
    }

    /**
     * Single-batch lookup backing the storefront detail/purchase page
     * (e.g. `/gift-cards/{id}` on the frontend).
     */
    public function show(Request $request): JsonResponse
    {
        // Note: deliberately reads the {batchId} segment via $request->route()
        // rather than a second method parameter. This route sits under
        // .../v1/{country}/gift-card-store/{batchId}, and Laravel's controller
        // dependency resolver (ResolvesRouteDependencies::spliceIntoParameters)
        // splices type-hinted dependencies into the route-parameters array by
        // *position*, discarding string keys in the process — with two scalar
        // route segments ({country}, {batchId}) ahead of a single scalar method
        // parameter, that shifts $country's value into what would have been
        // $batchId. Reading it off the request avoids the misbinding entirely.
        $batchId = (string) $request->route('batchId');

        $data = $request->validate([
            'currency_code' => ['required', 'string'],
        ]);

        $batch = GiftCardBatch::find($batchId);

        if (! $batch || $batch->currency_code !== $data['currency_code'] || ! $batch->is_purchasable) {
            return ApiResponse::error(__('customer_api.gift_card_store.purchase_not_found'), [], 404);
        }

        return ApiResponse::success(new GiftCardBatchResource($batch));
    }

    public function purchase(PurchaseGiftCardRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();

        try {
            $result = $this->giftCardPurchaseService->purchase($request->validated(), $customer);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors());
        }

        foreach ($result['cards'] as $card) {
            SendGiftCardNotificationJob::dispatch($card->id);
        }

        return ApiResponse::success([
            'order_id' => $result['order']->id,
            'purchases' => GiftCardPurchaseResource::collection(collect($result['purchases'])),
        ], __('customer_api.gift_card_store.purchased'), 201);
    }

    public function myPurchases(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $purchases = $this->giftCardPurchaseService->getPurchaseHistory($customer);

        return ApiResponse::paginated($purchases, GiftCardPurchaseResource::class);
    }

    public function resend(Request $request, GiftCardPurchase $purchase): JsonResponse
    {
        $customer = auth('customer')->user();

        if ($purchase->buyer_customer_id !== $customer->id) {
            return ApiResponse::error(__('customer_api.gift_card_store.purchase_not_found'), [], 404);
        }

        if (! $purchase->gift_card_id) {
            return ApiResponse::error(__('customer_api.gift_card_store.not_yet_available'), [], 422);
        }

        if ($purchase->delivery_attempts >= 3) {
            return ApiResponse::error(__('customer_api.gift_card_store.max_resend_reached'), [], 422);
        }

        SendGiftCardNotificationJob::dispatch($purchase->gift_card_id);

        return ApiResponse::success(null, __('customer_api.gift_card_store.delivery_email_resent'));
    }
}
