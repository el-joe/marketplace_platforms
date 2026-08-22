<?php

namespace App\Services\Customer;

use App\Models\VendorCityShippingSurcharge;

class CityShippingSurchargeService
{
    public function resolveSurcharge(string $vendorId, ?string $warehouseId): int
    {
        if (!$warehouseId) {
            return 0;
        }

        return (int) (VendorCityShippingSurcharge::where('vendor_id', $vendorId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->value('extra_amount_cents') ?? 0);
    }
}
