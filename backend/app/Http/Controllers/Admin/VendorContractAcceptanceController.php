<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorCategoryEnrollment;
use App\Models\VendorContract;
use Illuminate\View\View;

/**
 * One vendor's contract signatures and enrollment status, for both category scopes.
 */
class VendorContractAcceptanceController extends Controller
{
    public function index(Vendor $vendor): View
    {
        $contracts = VendorContract::where('vendor_id', $vendor->id)
            ->with(['contractTemplate', 'classifiedCategory', 'productCategory', 'signedByVendorAdmin:id,name'])
            ->orderByDesc('signed_at')
            ->paginate(30);

        $enrollments = VendorCategoryEnrollment::where('vendor_id', $vendor->id)
            ->with(['classifiedCategory:id,name_en', 'productCategory:id,name_en'])
            ->orderBy('status')
            ->get();

        return view('admin.vendors.contracts.index', compact('vendor', 'contracts', 'enrollments'));
    }
}
