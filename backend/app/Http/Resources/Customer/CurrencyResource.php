<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'symbol_type' => $this->symbol_type?->value,
            'symbol' => $this->symbol,
            'symbol_image_url' => $this->symbol_image_url,
            'decimal_places' => $this->decimal_places,
        ];
    }
}
