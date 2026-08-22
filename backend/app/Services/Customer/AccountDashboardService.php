<?php

namespace App\Services\Customer;

use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Setting;

class AccountDashboardService
{
    public function getDashboard(Customer $customer, Country $country): array
    {
        $customer->loadMissing(['orders', 'wishlists']);

        $recentOrders = $customer->orders()
            ->with(['subOrders'])
            ->latest()
            ->limit(3)
            ->get();

        $recentListings = $customer->classifiedListings()
            ->with(['images'])
            ->latest()
            ->limit(3)
            ->get();

        $recentInquiries = $customer->classifiedInquiries()
            ->with(['listing:id,listing_number,title_en,title_ar,status'])
            ->latest()
            ->limit(3)
            ->get();

        $recentTravelBookings = $customer->travelBookings()
            ->with(['package:id,title,price,currency'])
            ->latest()
            ->limit(3)
            ->get();

        $referralCount = $customer->referrals()->count();

        $referralLink = rtrim(config('app.url'), '/') . '/r/' . $customer->referral_code;

        return [
            'profile' => [
                'id'            => $customer->id,
                'name'          => $customer->name,
                'email'         => $customer->email,
                'phone'         => $customer->phone,
                'referral_code' => $customer->referral_code,
            ],
            'referral' => [
                'code'            => $customer->referral_code,
                'link'            => $referralLink,
                'qr_code_url'     => $customer->qr_code_path
                                        ? asset('storage/' . $customer->qr_code_path)
                                        : null,
                'total_referrals' => $referralCount,
                'bonus_points'    => (float) Setting::get('loyalty_referral_bonus_points', 50),
                'referee_bonus'   => (float) Setting::get('loyalty_new_customer_bonus_points', 50),
            ],
            'recent_orders' => $recentOrders->map(fn ($o) => [
                'order_number' => $o->order_number,
                'status'       => $o->status->value,
                'total'        => $o->total,
                'currency'     => $o->currency,
                'created_at'   => $o->created_at?->toIso8601String(),
            ]),
            'recent_classified_listings' => $recentListings->map(fn ($l) => [
                'listing_number' => $l->listing_number,
                'title'          => $l->title,
                'status'         => $l->status?->value,
                'primary_image'  => $l->primary_image_url,
                'created_at'     => $l->created_at?->toIso8601String(),
            ]),
            'recent_classified_inquiries' => $recentInquiries->map(fn ($i) => [
                'id'             => $i->id,
                'listing_number' => $i->listing?->listing_number,
                'listing_title'  => $i->listing?->title,
                'status'         => $i->status?->value,
                'created_at'     => $i->created_at?->toIso8601String(),
            ]),
            'recent_travel_bookings' => $recentTravelBookings->map(fn ($b) => [
                'booking_number' => $b->booking_number,
                'package_title'  => $b->package?->title,
                'status'         => $b->status?->value,
                'total'          => $b->total_price,
                'currency'       => $b->package?->currency,
                'created_at'     => $b->created_at?->toIso8601String(),
            ]),
            'loyalty_points'             => (float) $customer->loyalty_points,
            'loyalty_tier'               => $this->resolveTier((float) $customer->loyalty_points),
            'wishlist_count'             => $customer->wishlists()->count(),
            'unread_notifications_count' => $customer->unreadNotifications()->count(),
        ];
    }

    private function resolveTier(float $points): string
    {
        $platinum = (int) Setting::get('loyalty_tier_platinum_points', 5000);
        $gold     = (int) Setting::get('loyalty_tier_gold_points', 2000);
        $silver   = (int) Setting::get('loyalty_tier_silver_points', 500);

        return match (true) {
            $points >= $platinum => 'platinum',
            $points >= $gold     => 'gold',
            $points >= $silver   => 'silver',
            default              => 'standard',
        };
    }
}
