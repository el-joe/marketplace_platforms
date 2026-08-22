<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->items->map(fn ($item) => [
            'id'           => $item->id,
            'sub_order_id' => $item->sub_order_id,
            'gross'        => $item->gross,
            'commission'   => $item->commission,
            'net'          => $item->net,
        ]);

        return [
            'id'              => $this->id,
            'payout_number'   => $this->payout_number,
            'period_start'    => $this->period_start?->toDateString(),
            'period_end'      => $this->period_end?->toDateString(),
            'currency'        => $this->currency,
            'status'          => $this->status?->value,
            'payout_method'   => $this->payout_method,
            'gateway_reference' => $this->gateway_reference,
            'processed_at'    => $this->processed_at?->toISOString(),
            'failed_reason'   => $this->failed_reason,

            // Itemized deductions — clear breakdown, not a single opaque number.
            'deductions' => [
                'commission'           => $this->commission,
                'refunds_deducted'     => $this->refunds_deducted,
                'chargebacks_deducted' => $this->chargebacks_deducted,
                'storage_fees'         => $this->storage_fees,
                'ad_fees'              => $this->ad_fees,
                'other_adjustments'    => $this->other_adjustments,
            ],

            'gross_sales' => $this->gross_sales,
            'net_amount'  => $this->net_amount,

            'bank_account' => $this->whenLoaded('bankAccount', fn () => [
                'id'       => $this->bankAccount->id,
                'bank_name' => $this->bankAccount->bank_name,
                'iban'     => $this->bankAccount->iban,
            ]),

            'items'      => $items,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
