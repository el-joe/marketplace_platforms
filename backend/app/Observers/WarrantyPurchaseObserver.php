<?php

namespace App\Observers;

use App\Models\WarrantyPurchase;
use Illuminate\Support\Facades\Log;

class WarrantyPurchaseObserver
{
    public function created(WarrantyPurchase $purchase): void
    {
        Log::info('Warranty purchase created', [
            'warranty_purchase_id' => $purchase->id,
            'customer_id' => $purchase->customer_id,
            'plan_name' => $purchase->plan_snapshot['name_en'] ?? null,
        ]);
    }
}
