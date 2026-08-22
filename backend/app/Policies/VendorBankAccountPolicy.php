<?php

namespace App\Policies;

use App\Models\Vendor;
use App\Models\VendorBankAccount;

class VendorBankAccountPolicy
{
    public function view(Vendor $vendor, VendorBankAccount $account): bool
    {
        return $account->vendor_id === $vendor->id;
    }

    public function update(Vendor $vendor, VendorBankAccount $account): bool
    {
        return $account->vendor_id === $vendor->id;
    }

    public function delete(Vendor $vendor, VendorBankAccount $account): bool
    {
        return $account->vendor_id === $vendor->id;
    }
}
