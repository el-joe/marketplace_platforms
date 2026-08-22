<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Address\StoreAddressRequest;
use App\Http\Requests\Customer\Address\UpdateAddressRequest;
use App\Http\Resources\Customer\AddressResource;
use App\Http\Responses\ApiResponse;
use App\Models\Address;
use App\Models\Customer;
use App\Services\Customer\AddressService;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addressService) {}

    public function index(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $addresses = $customer->addresses()->orderByDesc('is_default')->get();

        return ApiResponse::success(
            AddressResource::collection($addresses),
            __('common.exceptions.address.retrieved')
        );
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $data = $request->validated();

        if (!empty($data['is_default'])) {
            $customer->addresses()->where('is_default', true)->update(['is_default' => false]);
        }

        $address = $customer->addresses()->create(array_merge($data, [
            'country_id' => $request->attributes->get('country')?->id ?? $customer->country_id,
        ]));

        return ApiResponse::success(new AddressResource($address), __('common.exceptions.address.created'), 201);
    }

    public function update(UpdateAddressRequest $request,$country, Address $address): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['is_default'])) {
            $this->addressService->setDefault(auth('customer')->user(), $address);
            unset($data['is_default']);
        }

        $address->update($data);

        return ApiResponse::success(new AddressResource($address->fresh()), __('common.exceptions.address.updated'));
    }

    public function destroy($country,Address $address): JsonResponse
    {
        if (!$this->addressService->canDelete($address)) {
            return ApiResponse::error(
                __('common.exceptions.address.in_use_cannot_delete'),
                [],
                409
            );
        }

        $address->delete();

        return ApiResponse::success(null, __('common.exceptions.address.deleted'));
    }

    public function setDefault($country, Address $address): JsonResponse
    {
        $this->addressService->setDefault(auth('customer')->user(), $address);

        return ApiResponse::success(new AddressResource($address->fresh()), __('common.exceptions.address.default_updated'));
    }
}
