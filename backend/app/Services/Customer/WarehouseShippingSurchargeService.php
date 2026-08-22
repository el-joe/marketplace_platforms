<?php

namespace App\Services\Customer;

use App\Models\WarehouseShippingSurcharge;

class WarehouseShippingSurchargeService
{
    public function resolveSurcharge(?string $warehouseId): int
    {
        if (!$warehouseId) {
            return 0;
        }

        return (int) (WarehouseShippingSurcharge::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->value('extra_amount_cents') ?? 0);
    }
}
