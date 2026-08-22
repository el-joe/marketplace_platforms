<?php

namespace App\Services\Vendor;

use App\Models\Coupon;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Vendor panel may only create vendor/product-scoped coupons.
     * Platform/category-scoped coupons are owned and managed by the admin panel.
     */
    public const VENDOR_MANAGEABLE_SCOPES = ['vendor', 'product'];

    public function create(Vendor $vendor, VendorAdmin $actor, array $data): Coupon
    {
        $data['code'] = strtoupper(trim($data['code']));
        $this->validateCodeUnique($data['code']);
        $this->validateScope($data['scope']);

        if ($data['scope'] === 'product') {
            $this->validateProductOwnership($vendor, $data['product_ids'] ?? []);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['is_stackable'] = (bool) ($data['is_stackable'] ?? false);
        $data['customer_eligibility'] = $data['customer_eligibility'] ?? 'all';
        $data['vendor_id'] = $vendor->id;
        $data['category_id'] = null;
        $data['created_by_user_id'] = $actor->id;

        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);

        return DB::transaction(function () use ($data, $productIds) {
            $coupon = Coupon::query()->create(array_merge(
                ['id' => Str::uuid()->toString()],
                $data
            ));

            if (!empty($productIds)) {
                $coupon->products()->sync($productIds);
            }

            return $coupon;
        });
    }

    public function update(Coupon $coupon, Vendor $vendor, array $data): Coupon
    {
        $data['code'] = strtoupper(trim($data['code']));
        $this->validateCodeUnique($data['code'], $coupon->id);
        $this->validateScope($data['scope']);

        if ($data['scope'] === 'product') {
            $this->validateProductOwnership($vendor, $data['product_ids'] ?? []);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? $coupon->is_active);
        $data['is_stackable'] = (bool) ($data['is_stackable'] ?? false);
        $data['vendor_id'] = $vendor->id;
        $data['category_id'] = null;

        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);

        DB::transaction(function () use ($coupon, $data, $productIds) {
            $coupon->update($data);

            if ($coupon->scope->value === 'product') {
                $coupon->products()->sync($productIds);
            } else {
                $coupon->products()->sync([]);
            }
        });

        return $coupon->refresh();
    }

    public function delete(Coupon $coupon): void
    {
        if ($coupon->times_used > 0) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon has already been used and cannot be deleted.'),
            ]);
        }

        $coupon->delete();
    }

    public function validateScope(string $scope): void
    {
        if (!in_array($scope, self::VENDOR_MANAGEABLE_SCOPES, true)) {
            throw ValidationException::withMessages([
                'scope' => __('Vendors may only create vendor or product scoped coupons.'),
            ]);
        }
    }

    /**
     * @param array<int, string> $productIds
     */
    public function validateProductOwnership(Vendor $vendor, array $productIds): void
    {
        if (empty($productIds)) {
            throw ValidationException::withMessages([
                'product_ids' => __('At least one product is required for a product-scoped coupon.'),
            ]);
        }

        $ownedProductIds = VendorListing::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->join('product_variants', 'vendor_listings.product_variant_id', '=', 'product_variants.id')
            ->whereIn('product_variants.product_id', $productIds)
            ->pluck('product_variants.product_id')
            ->unique()
            ->values()
            ->all();

        $invalidProductIds = array_diff($productIds, $ownedProductIds);

        if (!empty($invalidProductIds)) {
            throw ValidationException::withMessages([
                'product_ids' => __('The following products do not have an active listing owned by you: :ids', [
                    'ids' => implode(', ', $invalidProductIds),
                ]),
            ]);
        }
    }

    public function validateCodeUnique(string $code, ?string $excludeId = null): void
    {
        $query = Coupon::query()->where('code', strtoupper(trim($code)));

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => __('This coupon code is already in use.'),
            ]);
        }
    }
}
