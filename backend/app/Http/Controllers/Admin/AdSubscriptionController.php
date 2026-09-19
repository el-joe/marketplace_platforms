<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorAdSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdSubscriptionController extends Controller
{
    public function index(): View
    {
        $subscriptions = VendorAdSubscription::with(['adPackage', 'vendor', 'vendorListing.productVariant.product'])
            ->latest()
            ->paginate(20);

        return view('admin.ad-subscriptions.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Nawi Ads', 'url' => route('admin.ad-slots.index', ['target_type' => 'listing_promotion'])],
                ['label' => 'Ad Subscriptions'],
            ],
            'subscriptions' => $subscriptions,
        ]);
    }

    public function cancel(VendorAdSubscription $subscription): JsonResponse
    {
        $subscription->update(['status' => 'cancelled']);

        if ($subscription->vendorListing) {
            app(\App\Services\Ads\ListingBoostService::class)->refresh($subscription->vendorListing);
        }

        return response()->json(['success' => true, 'message' => 'Subscription cancelled.']);
    }
}
