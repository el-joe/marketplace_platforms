<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\AdPackage;
use App\Models\Vendor;
use App\Models\VendorAdSubscription;
use App\Models\VendorListing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdSubscriptionController extends Controller
{
    private function vendor(): Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    public function index(): View
    {
        $subscriptions = VendorAdSubscription::with(['adPackage', 'vendorListing.productVariant.product'])
            ->forVendor($this->vendor()->id)
            ->latest()
            ->paginate(20);

        return view('partner.ad-subscriptions.index', compact('subscriptions'));
    }

    public function packages(): View
    {
        $vendor = $this->vendor();

        $packages = AdPackage::active()->ordered()->get();
        $listings = VendorListing::with('productVariant.product')
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->get();

        return view('partner.ad-subscriptions.packages', compact('packages', 'listings'));
    }

    /**
     * Deprecated: legacy packages were activated without payment (NA-02).
     * Vendors must use Ad Slots, which handle payment and approval.
     */
    public function subscribe(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ad packages are no longer available. Please use Ad Slots (promotions) to boost your listings.',
            'redirect' => route('partner.ad-slots.index'),
        ], 410);
    }

    public function cancel(VendorAdSubscription $subscription): JsonResponse
    {
        abort_if($subscription->vendor_id !== $this->vendor()->id, 403);

        $subscription->update(['status' => 'cancelled']);

        $subscription->vendorListing()->update([
            'is_ad_boosted' => false,
            'ad_boost_expires_at' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Subscription cancelled.']);
    }
}
