<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Coupon;
use App\Services\Admin\CouponService;

class CouponPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->hasPermissionTo('coupons.view');
    }

    public function view(Admin $admin, Coupon $coupon): bool
    {
        return $admin->hasPermissionTo('coupons.view');
    }

    public function create(Admin $admin): bool
    {
        return $admin->hasPermissionTo('coupons.create');
    }

    /**
     * Vendor/product-scoped coupons are owned by the vendor panel and are
     * read-only for admins; only platform/category scopes are mutable here.
     */
    public function update(Admin $admin, Coupon $coupon): bool
    {
        return $admin->hasPermissionTo('coupons.edit')
            && in_array($coupon->scope->value, CouponService::ADMIN_MANAGEABLE_SCOPES, true);
    }

    public function delete(Admin $admin, Coupon $coupon): bool
    {
        return $admin->hasPermissionTo('coupons.delete')
            && in_array($coupon->scope->value, CouponService::ADMIN_MANAGEABLE_SCOPES, true);
    }

    public function toggleActive(Admin $admin, Coupon $coupon): bool
    {
        return $this->update($admin, $coupon);
    }
}
