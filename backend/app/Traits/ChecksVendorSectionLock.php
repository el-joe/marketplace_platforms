<?php

namespace App\Traits;

use App\Models\VendorAdmin;
use App\Services\VendorSectionLockService;
use Illuminate\Support\Facades\Auth;

trait ChecksVendorSectionLock
{
    private function assertSectionEditable(string $section): void
    {
        /** @var VendorAdmin $vendorAdmin */
        $vendorAdmin = Auth::guard('vendor')->user();

        if (app(VendorSectionLockService::class)->isLocked($vendorAdmin->vendor_id, $section)) {
            abort(403, 'This section is currently locked by the platform.');
        }
    }
}
