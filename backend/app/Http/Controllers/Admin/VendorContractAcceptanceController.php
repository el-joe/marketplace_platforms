<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketerSellerContract;
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
            ->with(['contractTemplate', 'classifiedCategory', 'productCategory', 'product:id,name_en', 'signedByVendorAdmin:id,name'])
            ->orderByDesc('signed_at')
            ->paginate(30);

        $enrollments = VendorCategoryEnrollment::where('vendor_id', $vendor->id)
            ->with(['classifiedCategory:id,name_en', 'productCategory:id,name_en'])
            ->orderBy('status')
            ->get();

        // Seller-marketer collaboration contracts whose Display On includes the seller profile (a separate concept).
        $marketerContracts = MarketerSellerContract::where('vendor_id', $vendor->id)
            ->whereHas('template.displayDestinations', fn ($query) => $query->where('key', 'seller_profile'))
            ->with(['marketer:id,name', 'template:id,name', 'campaign:id,title'])
            ->orderByDesc('signed_at')
            ->paginate(15, ['*'], 'marketer_contracts_page');

        return view('admin.vendors.contracts.index', compact('vendor', 'contracts', 'enrollments', 'marketerContracts'));
    }
}
