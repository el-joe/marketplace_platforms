<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftCardPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'amount_paid' => $this->amount_paid,
            'currency_code' => $this->currency_code,
            'is_gift' => $this->is_gift,
            'recipient_email' => $this->recipient_email,
            'recipient_name' => $this->recipient_name,
            'gift_message' => $this->gift_message,
            'delivery_status' => $this->delivery_status,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'gift_card_code' => $this->whenLoaded('giftCard', fn () => $this->giftCard?->code),
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'title_en' => $this->batch->title_en,
                'title_ar' => $this->batch->title_ar,
                'amount' => $this->batch->amount,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
