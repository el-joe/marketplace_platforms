<?php

namespace App\Services\Vendor;

use App\Services\Shared\ClassifiedListingService as SharedService;

/**
 * Vendor-scoped alias of the shared ClassifiedListingService.
 * Exists so existing vendor controller imports resolve without change.
 */
class ClassifiedListingService extends SharedService
{
}
