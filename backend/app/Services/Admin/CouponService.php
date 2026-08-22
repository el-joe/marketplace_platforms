<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Coupon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Admin panel may only create platform/category-scoped coupons.
     * Vendor/product-scoped coupons are owned and managed by the vendor panel.
     */
    public const ADMIN_MANAGEABLE_SCOPES = ['platform', 'category'];

    public function create(array $data, Admin $admin): Coupon
    {
        $data['code'] = strtoupper(trim($data['code']));
        $this->validateCodeUnique($data['code']);

        if (!in_array($data['scope'], self::ADMIN_MANAGEABLE_SCOPES, true)) {
            throw ValidationException::withMessages([
                'scope' => __('admin.coupons_section.scope_not_admin_manageable'),
            ]);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['is_stackable'] = (bool) ($data['is_stackable'] ?? false);
        $data['created_by_user_id'] = $admin->id;
        $data['vendor_id'] = null;
        $data['category_id'] = $data['scope'] === 'category' ? ($data['category_id'] ?? null) : null;
        $data['country_ids'] = !empty($data['country_ids']) ? $data['country_ids'] : null;
        $data['eligible_customer_ids'] = $data['customer_eligibility'] === 'specific_users' ? ($data['eligible_customer_ids'] ?? []) : null;
        $data['vendor_share_pct'] = $data['funded_by'] === 'shared' ? $data['vendor_share_pct'] : null;

        $coupon = DB::transaction(fn () => Coupon::query()->create(array_merge(
            ['id' => Str::uuid()->toString()],
            $data
        )));

        return $coupon;
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        $data['code'] = strtoupper(trim($data['code']));
        $this->validateCodeUnique($data['code'], $coupon->id);

        if (!in_array($data['scope'], self::ADMIN_MANAGEABLE_SCOPES, true)) {
            throw ValidationException::withMessages([
                'scope' => __('admin.coupons_section.scope_not_admin_manageable'),
            ]);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['is_stackable'] = (bool) ($data['is_stackable'] ?? false);
        $data['vendor_id'] = null;
        $data['category_id'] = $data['scope'] === 'category' ? ($data['category_id'] ?? null) : null;
        $data['country_ids'] = !empty($data['country_ids']) ? $data['country_ids'] : null;
        $data['eligible_customer_ids'] = $data['customer_eligibility'] === 'specific_users' ? ($data['eligible_customer_ids'] ?? []) : null;
        $data['vendor_share_pct'] = $data['funded_by'] === 'shared' ? $data['vendor_share_pct'] : null;

        DB::transaction(fn () => $coupon->update($data));

        return $coupon->refresh();
    }

    /**
     * Delete a never-used coupon, or soft-retire a used one instead.
     *
     * @return array{deleted: bool, deactivated: bool}
     */
    public function deleteOrDeactivate(Coupon $coupon): array
    {
        if ($coupon->times_used > 0) {
            $coupon->update(['is_active' => false, 'valid_until' => now()]);

            return ['deleted' => false, 'deactivated' => true];
        }

        $coupon->delete();

        return ['deleted' => true, 'deactivated' => false];
    }

    /**
     * Invalidate the "product_coupons:*" cache used by ProductDetailEnrichmentService
     * by bumping its version segment, since those keys are per product/country/customer
     * and can't be targeted individually without cache tag support.
     */
    public function bustProductCouponsCache(): void
    {
        Cache::increment('product_coupons:version');
    }

    public function validateCodeUnique(string $code, ?string $excludeId = null): void
    {
        $query = Coupon::query()->where('code', strtoupper(trim($code)));

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => __('admin.coupons_section.code_already_used'),
            ]);
        }
    }
}
