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
use Illuminate\Support\Facades\Storage;

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

    public function subscribe(Request $request): JsonResponse
    {
        $vendor = $this->vendor();

        $data = $request->validate([
            'vendor_listing_id' => 'required|uuid|exists:vendor_listings,id',
            'ad_package_id' => 'required|uuid|exists:ad_packages,id',
            'popup_title_en' => 'nullable|string|max:255',
            'popup_title_ar' => 'nullable|string|max:255',
            'popup_body_en' => 'nullable|string',
            'popup_body_ar' => 'nullable|string',
            'popup_image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'popup_cta_url' => 'nullable|url',
        ]);

        $listing = VendorListing::where('id', $data['vendor_listing_id'])
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $package = AdPackage::where('is_active', true)->findOrFail($data['ad_package_id']);

        $popupImageUrl = null;
        if ($request->hasFile('popup_image')) {
            $path = $request->file('popup_image')->store('ad-popups', 'public');
            $popupImageUrl = Storage::disk('public')->url($path);
        }

        $startsAt = now();
        $endsAt = $startsAt->copy()->addDays(30);

        $subscription = VendorAdSubscription::create([
            'vendor_listing_id' => $listing->id,
            'vendor_id' => $vendor->id,
            'ad_package_id' => $package->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'amount_paid' => $package->price_monthly,
            'currency' => $package->currency,
            'popup_title_en' => $data['popup_title_en'] ?? null,
            'popup_title_ar' => $data['popup_title_ar'] ?? null,
            'popup_body_en' => $data['popup_body_en'] ?? null,
            'popup_body_ar' => $data['popup_body_ar'] ?? null,
            'popup_image_url' => $popupImageUrl,
            'popup_cta_url' => $data['popup_cta_url'] ?? null,
        ]);

        $listing->update([
            'is_ad_boosted' => true,
            'ad_boost_expires_at' => $endsAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad subscription activated for 30 days.',
            'subscription' => $subscription,
        ]);
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
