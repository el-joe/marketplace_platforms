<?php

namespace App\Services\Customer;

use App\Models\Country;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnifiedListingQueryService
{
    /**
     * Optimized getBuyBoxForProducts using a single UNION ALL + ROW_NUMBER query.
     * Priority: AdminListing (1) > VendorListing (2) > MarketerListing (3); lower price wins ties.
     *
     * Returns array keyed by product_id → best listing (AdminListing|VendorListing|MarketerListing|null)
     */
    public function getBuyBoxForProducts(
        Collection $products,
        Country $country,
    ): array {
        $variantMap = []; // variant_id => product_id
        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $variantMap[$variant->id] = $product->id;
            }
        }
        $variantIds = array_keys($variantMap);

        if (empty($variantIds)) {
            return array_fill_keys($products->pluck('id')->all(), null);
        }

        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));

        $sql = "
            SELECT listing_id, listing_type, product_variant_id, price
            FROM (
                SELECT al.id AS listing_id, 'admin' AS listing_type, al.product_variant_id, al.price,
                    ROW_NUMBER() OVER (PARTITION BY al.product_variant_id ORDER BY al.price ASC) AS rn
                FROM admin_listings al
                WHERE al.country_id = ? AND al.status = 'active' AND al.deleted_at IS NULL
                  AND al.product_variant_id IN ({$placeholders})

                UNION ALL

                SELECT vl.id, 'vendor', vl.product_variant_id, vl.price,
                    ROW_NUMBER() OVER (PARTITION BY vl.product_variant_id ORDER BY vl.price ASC) AS rn
                FROM vendor_listings vl
                JOIN vendors v ON v.id = vl.vendor_id AND v.global_status = 'active'
                WHERE vl.country_id = ? AND vl.status = 'active' AND vl.deleted_at IS NULL
                  AND vl.product_variant_id IN ({$placeholders})

                UNION ALL

                SELECT ml.id, 'marketer', ml.product_variant_id, ml.price,
                    ROW_NUMBER() OVER (PARTITION BY ml.product_variant_id ORDER BY ml.price ASC) AS rn
                FROM marketer_listings ml
                JOIN marketers mk ON mk.id = ml.marketer_id AND mk.global_status = 'active'
                WHERE ml.country_id = ? AND ml.status = 'active' AND ml.deleted_at IS NULL AND ml.listing_category = 'product'
                  AND ml.product_variant_id IN ({$placeholders})
            ) ranked
            WHERE rn = 1
        ";

        $bindings = array_merge(
            [$country->id], $variantIds,
            [$country->id], $variantIds,
            [$country->id], $variantIds,
        );

        $rows = collect(DB::select($sql, $bindings));

        $priority = ['admin' => 1, 'vendor' => 2, 'marketer' => 3];

        // Group by variant_id → best row per variant (priority, then price)
        $bestByVariant = []; // variant_id => row
        foreach ($rows as $row) {
            $existing = $bestByVariant[$row->product_variant_id] ?? null;
            if ($existing === null) {
                $bestByVariant[$row->product_variant_id] = $row;
                continue;
            }
            $existingPriority = $priority[$existing->listing_type] ?? 99;
            $newPriority = $priority[$row->listing_type] ?? 99;
            if ($newPriority < $existingPriority || ($newPriority === $existingPriority && $row->price < $existing->price)) {
                $bestByVariant[$row->product_variant_id] = $row;
            }
        }

        // Collect IDs by type for batch hydration
        $adminIds    = collect($bestByVariant)->where('listing_type', 'admin')->pluck('listing_id')->all();
        $vendorIds   = collect($bestByVariant)->where('listing_type', 'vendor')->pluck('listing_id')->all();
        $marketerIds = collect($bestByVariant)->where('listing_type', 'marketer')->pluck('listing_id')->all();

        $sharedWith = [
            'primaryShippingMethod:id,name,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
            'productVariant.images',
            'productVariant.product.brand',
        ];

        $hydratedAdmin    = !empty($adminIds)    ? \App\Models\AdminListing::whereIn('id', $adminIds)->with($sharedWith)->get()->keyBy('id') : collect();
        $hydratedVendor   = !empty($vendorIds)   ? \App\Models\VendorListing::whereIn('id', $vendorIds)->with(array_merge($sharedWith, ['vendor:id,store_name,store_rating_avg']))->get()->keyBy('id') : collect();
        $hydratedMarketer = !empty($marketerIds) ? \App\Models\MarketerListing::whereIn('id', $marketerIds)->with(array_merge($sharedWith, ['marketer:id,name,marketer_type']))->get()->keyBy('id') : collect();

        // Build result keyed by product_id
        $result = array_fill_keys($products->pluck('id')->all(), null);

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                if (!isset($bestByVariant[$variant->id])) {
                    continue;
                }

                $row     = $bestByVariant[$variant->id];
                $listing = match ($row->listing_type) {
                    'admin'    => $hydratedAdmin->get($row->listing_id),
                    'vendor'   => $hydratedVendor->get($row->listing_id),
                    'marketer' => $hydratedMarketer->get($row->listing_id),
                    default    => null,
                };

                if ($listing && $result[$product->id] === null) {
                    $result[$product->id] = $listing;
                    break;
                }
            }
        }

        return $result;
    }
}
