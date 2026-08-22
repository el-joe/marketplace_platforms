<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Receiver\StoreReceiverRequest;
use App\Http\Requests\Customer\Receiver\UpdateReceiverRequest;
use App\Http\Resources\Customer\ReceiverResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerReceiver;
use App\Services\Customer\ReceiverService;
use Illuminate\Http\JsonResponse;

class ReceiverController extends Controller
{
    public function __construct(private readonly ReceiverService $receiverService) {}

    public function index(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $receivers = $customer->receivers()->orderByDesc('is_default')->get();

        return ApiResponse::success(
            ReceiverResource::collection($receivers),
            __('common.exceptions.receiver.retrieved')
        );
    }

    public function store(StoreReceiverRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $data = $request->validated();

        $isFirst = ! $customer->receivers()->exists();

        if ($isFirst || !empty($data['is_default'])) {
            $customer->receivers()->where('is_default', true)->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        $receiver = $customer->receivers()->create($data);

        return ApiResponse::success(new ReceiverResource($receiver), __('common.exceptions.receiver.created'), 201);
    }

    public function update(UpdateReceiverRequest $request, $country, CustomerReceiver $receiver): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['is_default'])) {
            $this->receiverService->setDefault(auth('customer')->user(), $receiver);
            unset($data['is_default']);
        }

        $receiver->update($data);

        return ApiResponse::success(new ReceiverResource($receiver->fresh()), __('common.exceptions.receiver.updated'));
    }

    public function destroy($country, CustomerReceiver $receiver): JsonResponse
    {
        if ($receiver->is_default) {
            return ApiResponse::error(
                __('common.exceptions.receiver.cannot_delete_default'),
                [],
                422
            );
        }

        $receiver->delete();

        return ApiResponse::success(null, __('common.exceptions.receiver.deleted'));
    }

    public function setDefault($country, CustomerReceiver $receiver): JsonResponse
    {
        $this->receiverService->setDefault(auth('customer')->user(), $receiver);

        return ApiResponse::success(new ReceiverResource($receiver->fresh()), __('common.exceptions.receiver.default_updated'));
    }
}
