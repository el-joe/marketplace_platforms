<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'transaction_group_id' => $this->transaction_group_id,
            'account_type'         => $this->account_type,
            'debit'                => $this->debit,
            'credit'               => $this->credit,
            'currency'             => $this->currency,
            'reference_type'       => $this->reference_type,
            'reference_id'         => $this->reference_id,
            'description'          => $this->description,
            'created_at'           => $this->created_at?->toISOString(),
        ];
        // NOTE: account_holder_id intentionally omitted — prevents id leakage
        // for any platform-side entries that share a transaction_group_id.
    }
}
