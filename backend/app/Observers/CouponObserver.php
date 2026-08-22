<?php

namespace App\Observers;

use App\Models\Coupon;
use Illuminate\Support\Facades\Cache;

class CouponObserver
{
    public function created(Coupon $coupon): void
    {
        $this->bustProductCouponsCache();
    }

    public function updated(Coupon $coupon): void
    {
        $this->bustProductCouponsCache();
    }

    public function deleted(Coupon $coupon): void
    {
        $this->bustProductCouponsCache();
    }

    /**
     * Invalidate the "product_coupons:*" cache used by ProductDetailEnrichmentService
     * by bumping its version segment, since those keys are per product/country/customer
     * and can't be targeted individually without cache tag support.
     */
    private function bustProductCouponsCache(): void
    {
        Cache::increment('product_coupons:version');
    }
}
