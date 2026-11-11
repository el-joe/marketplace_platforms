<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Concerns\HandlesPanelProductRequests;
use App\Http\Controllers\Controller;
use App\Models\ProductRequest;
use App\Models\VendorAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Product vendors request catalog products that are missing when adding a listing.
 */
class ProductRequestController extends Controller
{
    use HandlesPanelProductRequests;

    protected function panelUser(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    protected function teamRequests(): Builder
    {
        return ProductRequest::query()->forVendor($this->panelUser()->vendor_id);
    }

    protected function panel(): string
    {
        return 'partner';
    }

    protected function defaultCountryId(): ?string
    {
        return $this->panelUser()->vendor?->country_id;
    }
}
