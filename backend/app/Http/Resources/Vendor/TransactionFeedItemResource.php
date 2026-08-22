<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionFeedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return match ($this->resource['type']) {
            'sale'   => $this->saleShape(),
            'refund' => $this->refundShape(),
            'payout' => $this->payoutShape(),
            default  => $this->resource,
        };
    }

    private function saleShape(): array
    {
        return [
            'type'             => 'sale',
            'date'             => $this->resource['date'],
            'reference'        => $this->resource['reference'],
            'description'      => $this->resource['description'],
            'gross'            => $this->resource['gross'],
            'commission'       => $this->resource['commission'],
            'net'              => $this->resource['net'],
            'payout_status'    => $this->resource['payout_status'],
        ];
    }

    private function refundShape(): array
    {
        return [
            'type'         => 'refund',
            'date'         => $this->resource['date'],
            'reference'    => $this->resource['reference'],
            'description'  => $this->resource['description'],
            'amount' => $this->resource['amount'],
        ];
    }

    private function payoutShape(): array
    {
        return [
            'type'         => 'payout',
            'date'         => $this->resource['date'],
            'reference'    => $this->resource['reference'],
            'description'  => $this->resource['description'],
            'amount' => $this->resource['amount'],
            'currency'     => $this->resource['currency'],
            'receipt_url'  => $this->resource['receipt_url'],
        ];
    }
}
