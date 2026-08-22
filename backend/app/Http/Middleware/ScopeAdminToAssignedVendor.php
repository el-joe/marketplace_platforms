<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use App\Models\VendorAcquisitionCommission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ScopeAdminToAssignedVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        $acquisitionVendorIds = $admin
            ? VendorAcquisitionCommission::where('admin_id', $admin->id)
                ->where('status', 'active')
                ->pluck('vendor_id')
                ->all()
            : [];

        if ($admin && ($admin->hasRole('vendor_relations_admin') || $admin->hasPermissionTo('vendors.assigned_only', 'admin'))) {
            $vendorIds = Vendor::where('account_manager_admin_id', $admin->id)->pluck('id')->all();
            $vendorIds = array_values(array_unique(array_merge($vendorIds, $acquisitionVendorIds)));

            $request->attributes->set('is_scoped_admin', true);
            $request->attributes->set('scoped_vendor_ids', $vendorIds);
        } elseif ($admin && !empty($acquisitionVendorIds)) {
            // Not a scoped account-manager admin, but has acquisition commissions:
            // grant view access to just those vendors.
            $request->attributes->set('is_scoped_admin', true);
            $request->attributes->set('scoped_vendor_ids', $acquisitionVendorIds);
        } else {
            $request->attributes->set('is_scoped_admin', false);
            $request->attributes->set('scoped_vendor_ids', null);
        }

        return $next($request);
    }
}
