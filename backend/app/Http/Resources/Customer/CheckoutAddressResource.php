<?php

namespace App\Http\Resources\Customer;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trimmed-down address block used in the checkout preview response.
 * Wrap with: new CheckoutAddressResource($address, $country)
 */
class CheckoutAddressResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly Country $country)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient_name' => $this->recipient_name,
            'street_address' => $this->street_address,
            'city' => $this->city?->name_en,
            'country' => $this->country->name_en,
        ];
    }
}
