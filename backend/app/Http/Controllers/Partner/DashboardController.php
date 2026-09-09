<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedInquiry;
use App\Models\Dispute;
use App\Models\Payout;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Models\VendorStrike;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $vendorAdmin = Auth::guard('vendor')->user();
        $vendorId = $vendorAdmin->vendor_id;

        if ($vendorAdmin->vendor?->isClassifiedVendor()) {
            return $this->classifiedDashboard($vendorAdmin, $vendorId);
        }

        $stats = Cache::remember(
            "vendor.dashboard.{$vendorId}",
            300,
            function () use ($vendorId, $vendorAdmin) {
                return [
                    // Revenue this month
                    'revenue_month' => SubOrder::where('vendor_id', $vendorId)
                        ->whereIn('status', ['completed', 'delivered', 'shipped'])
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->sum('vendor_payout'),

                    // Orders today
                    'orders_today' => SubOrder::where('vendor_id', $vendorId)
                        ->whereDate('created_at', today())
                        ->count(),

                    // Pending orders (need action)
                    'pending_orders' => SubOrder::where('vendor_id', $vendorId)
                        ->where('status', 'placed')
                        ->count(),

                    // SLA urgent (< 2 hours to deadline)
                    'sla_urgent' => SubOrder::where('vendor_id', $vendorId)
                        ->where('sla_ship_deadline', '<=', now()->addHours(2))
                        ->whereNotIn('status', ['shipped', 'delivered', 'completed', 'cancelled'])
                        ->count(),

                    // Low stock
                    'low_stock' => VendorListing::where('vendor_id', $vendorId)
                        ->where('status', 'active')
                        ->whereHas('warehouseInventories', fn($q) => $q->whereRaw(
                            '(quantity_on_hand - quantity_reserved) <= vendor_listings.low_stock_threshold'
                        ))
                        ->count(),

                    // Pending payout
                    'pending_payout' => Payout::where('vendor_id', $vendorId)
                        ->where('status', 'pending')
                        ->sum('net_amount'),

                    // Open disputes
                    'open_disputes' => Dispute::where('vendor_id', $vendorId)
                        ->where('status', 'open')
                        ->count(),

                    // Active strikes
                    'active_strikes' => VendorStrike::where('vendor_id', $vendorId)
                        ->where('is_active', 1)
                        ->count(),

                    // Store rating
                    'rating_avg' => $vendorAdmin->vendor->store_rating_avg ?? 0,
                    'rating_count' => $vendorAdmin->vendor->store_rating_count ?? 0,
                ];
            }
        );

        // Eloquent collections are excluded from the cache to avoid
        // unserialize() failures when class definitions are not yet loaded.
        $stats['recent_orders'] = SubOrder::where('vendor_id', $vendorId)
            ->with('order')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $stats['revenue_chart'] = SubOrder::where('vendor_id', $vendorId)
            ->whereIn('status', ['completed', 'delivered', 'shipped'])
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(vendor_payout) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('partner.dashboard', compact('stats'));
    }

    private function classifiedDashboard($vendorAdmin, string $vendorId): View
    {
        $vendor = $vendorAdmin->vendor;

        $stats = Cache::remember(
            "vendor.classified-dashboard.{$vendorId}",
            300,
            function () use ($vendor) {
                $listingsQuery = $vendor->classifiedListings();

                return [
                    'active_listings' => (clone $listingsQuery)->where('status', \App\Enums\ClassifiedListingStatus::Active->value)->count(),
                    'pending_listings' => (clone $listingsQuery)->whereIn('status', [
                        \App\Enums\ClassifiedListingStatus::PendingContract->value,
                        \App\Enums\ClassifiedListingStatus::PendingReview->value,
                    ])->count(),
                    'paused_listings' => (clone $listingsQuery)->where('status', \App\Enums\ClassifiedListingStatus::Paused->value)->count(),
                    'sold_listings' => (clone $listingsQuery)->where('status', \App\Enums\ClassifiedListingStatus::Sold->value)->count(),
                    'total_listings' => (clone $listingsQuery)->count(),
                    'new_inquiries' => ClassifiedInquiry::whereIn('classified_listing_id', (clone $listingsQuery)->pluck('id'))
                        ->where('status', \App\Enums\ClassifiedInquiryStatus::New->value)
                        ->count(),
                    'rating_avg' => $vendor->store_rating_avg ?? 0,
                    'rating_count' => $vendor->store_rating_count ?? 0,
                ];
            }
        );

        $stats['recent_listings'] = $vendor->classifiedListings()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('partner.dashboard-classified', compact('stats'));
    }
}
