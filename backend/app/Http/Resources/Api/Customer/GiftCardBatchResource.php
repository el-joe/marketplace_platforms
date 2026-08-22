<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftCardBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'image_url' => $this->image_url,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'available_count' => $this->available_count,
        ];
    }
}
