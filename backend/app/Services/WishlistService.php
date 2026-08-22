<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WishlistGroup;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    public function resolveDefaultGroup(Customer $customer): WishlistGroup
    {
        return WishlistGroup::firstOrCreate(
            ['customer_id' => $customer->id, 'is_default' => true],
            [
                'name' => 'My Wishlist',
                'is_public' => false,
                'sort_order' => 0,
            ]
        );
    }

    /**
     * @throws ModelNotFoundException
     */
    public function resolveGroup(string $groupId, Customer $customer): WishlistGroup
    {
        return WishlistGroup::where('id', $groupId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
    }

    public function addItem(
        Customer $customer,
        string $listingId,
        bool $isAdminListing,
        string $productVariantId,
        ?string $groupId = null
    ): array {
        $group = $groupId
            ? $this->resolveGroup($groupId, $customer)
            : $this->resolveDefaultGroup($customer);

        $existingQuery = WishlistItem::where('wishlist_group_id', $group->id)
            ->where('customer_id', $customer->id);

        if ($isAdminListing) {
            $existingQuery->where('admin_listing_id', $listingId);
        } else {
            $existingQuery->where('vendor_listing_id', $listingId);
        }

        $existing = $existingQuery->first();
        if ($existing) {
            return ['item' => $existing, 'group' => $group, 'already_existed' => true];
        }

        try {
            $item = WishlistItem::create([
                'wishlist_group_id' => $group->id,
                'customer_id' => $customer->id,
                'vendor_listing_id' => $isAdminListing ? null : $listingId,
                'admin_listing_id' => $isAdminListing ? $listingId : null,
                'product_variant_id' => $productVariantId,
                'added_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                $existing = $existingQuery->first();

                return ['item' => $existing, 'group' => $group, 'already_existed' => true];
            }
            throw $e;
        }

        return ['item' => $item, 'group' => $group, 'already_existed' => false];
    }

    public function moveItems(Customer $customer, array $itemIds, string $targetGroupId): int
    {
        $targetGroup = $this->resolveGroup($targetGroupId, $customer);

        return WishlistItem::whereIn('id', $itemIds)
            ->where('customer_id', $customer->id)
            ->where('wishlist_group_id', '!=', $targetGroup->id)
            ->update(['wishlist_group_id' => $targetGroup->id]);
    }

    public function listingInGroups(Customer $customer, string $listingId, bool $isAdminListing): array
    {
        $query = WishlistItem::where('customer_id', $customer->id);

        if ($isAdminListing) {
            $query->where('admin_listing_id', $listingId);
        } else {
            $query->where('vendor_listing_id', $listingId);
        }

        return $query->pluck('wishlist_group_id')->toArray();
    }

    public function deleteGroupWithMigration(WishlistGroup $group, Customer $customer): void
    {
        DB::transaction(function () use ($group, $customer) {
            $default = $this->resolveDefaultGroup($customer);

            $itemsToMove = WishlistItem::where('wishlist_group_id', $group->id)->get();

            foreach ($itemsToMove as $item) {
                $alreadyInDefault = WishlistItem::where('wishlist_group_id', $default->id)
                    ->where(function ($q) use ($item) {
                        if ($item->vendor_listing_id) {
                            $q->where('vendor_listing_id', $item->vendor_listing_id);
                        } else {
                            $q->where('admin_listing_id', $item->admin_listing_id);
                        }
                    })->exists();

                if ($alreadyInDefault) {
                    $item->delete();
                } else {
                    $item->update(['wishlist_group_id' => $default->id]);
                }
            }

            $group->delete();
        });
    }
}
