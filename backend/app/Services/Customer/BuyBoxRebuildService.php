<?php

namespace App\Services\Customer;

use App\Models\Country;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-19 task 2: maintains `product_country_buybox`, the
 * read model ProductQueryService's two-phase query runs against.
 *
 * Deterministic buy-box winner rule (never changes on re-run):
 *   1. Any active admin listing wins outright.
 *   2. Else the active vendor listing ranked highest by
 *      (fulfillment_model = 'fbn' first, then price ASC, then score DESC
 *      with NULLs last, then id ASC as the final tie-breaker).
 *   3. Else the active marketer listing ranked by (price ASC, then id ASC).
 *
 * min_price / max_price consider admin + vendor + marketer listings.
 * total_stock sums warehouse_inventories for admin + vendor listings only
 * (marketer listings never carry their own inventory — see MarketerListing
 * docblock — their stock is already counted via the source listing).
 * rating_avg / rating_count are a single weighted aggregate across every
 * admin + vendor + marketer listing for the product (no join fan-out: the
 * listings are aggregated in PHP from three independently-fetched,
 * inventory-free listing sets, so a listing's rating is counted exactly once
 * regardless of how many warehouses stock it).
 */
class BuyBoxRebuildService
{
    /**
     * Rebuild every buy-box row for the given country. Returns the number
     * of product rows written.
     */
    public function rebuildCountry(Country $country): int
    {
        $stock = $this->stockByListing();

        $admin = $this->adminCandidates($country->id);
        $vendor = $this->vendorCandidates($country->id);
        $marketer = $this->marketerCandidates($country->id);

        $productIds = collect($admin)->pluck('product_id')
            ->merge(collect($vendor)->pluck('product_id'))
            ->merge(collect($marketer)->pluck('product_id'))
            ->unique()->values();

        return $this->rebuildProductSet($productIds, $country, $admin, $vendor, $marketer, $stock);
    }

    /**
     * Rebuild the buy-box rows for a specific set of products in one country
     * (used by observers/listeners so a single listing write only recomputes
     * the handful of products it touches, not the whole country).
     */
    public function rebuildProducts(array $productIds, Country $country): int
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if (empty($productIds)) {
            return 0;
        }

        $stock = $this->stockByListing();

        $admin = $this->adminCandidates($country->id, $productIds);
        $vendor = $this->vendorCandidates($country->id, $productIds);
        $marketer = $this->marketerCandidates($country->id, $productIds);

        return $this->rebuildProductSet(collect($productIds), $country, $admin, $vendor, $marketer, $stock);
    }

    /**
     * @param  \Illuminate\Support\Collection<int,string>  $productIds
     */
    private function rebuildProductSet(
        \Illuminate\Support\Collection $productIds,
        Country $country,
        \Illuminate\Support\Collection $admin,
        \Illuminate\Support\Collection $vendor,
        \Illuminate\Support\Collection $marketer,
        array $stock,
    ): int {
        $adminByProduct = $admin->groupBy('product_id');
        $vendorByProduct = $vendor->groupBy('product_id');
        $marketerByProduct = $marketer->groupBy('product_id');

        $shippingDefaults = $this->categoryDefaultShippingMethods();
        $expressFlags = $this->shippingExpressFlags();

        $rows = [];
        $toDelete = [];

        foreach ($productIds as $productId) {
            $a = $adminByProduct->get($productId, collect());
            $v = $vendorByProduct->get($productId, collect());
            $m = $marketerByProduct->get($productId, collect());

            $all = $a->concat($v)->concat($m);

            if ($all->isEmpty()) {
                $toDelete[] = $productId;
                continue;
            }

            $winner = $this->pickWinner($a, $v, $m);
            $categoryId = $winner->category_id;
            $brandId = $winner->brand_id;

            $totalStock = 0;
            foreach ($a->concat($v) as $cand) {
                $totalStock += $stock[$cand->listing_type . ':' . $cand->listing_id] ?? 0;
            }

            $ratingNumerator = 0.0;
            $ratingDenominator = 0;
            foreach ($all as $cand) {
                if ($cand->rating_count) {
                    $ratingNumerator += (float) $cand->rating_avg * (int) $cand->rating_count;
                    $ratingDenominator += (int) $cand->rating_count;
                }
            }
            $ratingAvg = $ratingDenominator > 0 ? round($ratingNumerator / $ratingDenominator, 2) : 0;

            $shippingMethodId = $winner->listing_type === 'marketer'
                ? null
                : ($winner->primary_shipping_method_id ?: null);
            if ($shippingMethodId === null) {
                $shippingMethodId = $shippingDefaults[$categoryId] ?? null;
            }

            $rows[] = [
                'product_id' => $productId,
                'country_id' => $country->id,
                'listing_type' => $winner->listing_type,
                'listing_id' => $winner->listing_id,
                'variant_id' => $winner->variant_id,
                'price' => (int) $winner->price,
                'compare_at_price' => $winner->compare_at_price !== null ? (int) $winner->compare_at_price : null,
                'min_price' => (int) $all->min('price'),
                'max_price' => (int) $all->max('price'),
                'seller_count' => $v->count(),
                'admin_listing_count' => $a->count(),
                'total_stock' => $totalStock,
                'rating_avg' => $ratingAvg,
                'rating_count' => $ratingDenominator,
                'fulfillment_model' => $winner->listing_type === 'marketer' ? null : $winner->fulfillment_model,
                'shipping_method_id' => $shippingMethodId,
                'is_express' => $shippingMethodId ? (bool) ($expressFlags[$shippingMethodId] ?? false) : false,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'total_sold' => (int) $winner->product_total_sold,
                'score' => $winner->score !== null ? (float) $winner->score : 0,
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_country_buybox')->upsert(
                $chunk,
                ['product_id', 'country_id'],
                ['listing_type', 'listing_id', 'variant_id', 'price', 'compare_at_price', 'min_price',
                 'max_price', 'seller_count', 'admin_listing_count', 'total_stock', 'rating_avg',
                 'rating_count', 'fulfillment_model', 'shipping_method_id', 'is_express', 'category_id',
                 'brand_id', 'total_sold', 'score', 'updated_at'],
            );
        }

        if (!empty($toDelete)) {
            DB::table('product_country_buybox')
                ->where('country_id', $country->id)
                ->whereIn('product_id', $toDelete)
                ->delete();
        }

        return count($rows);
    }

    /**
     * @return object deterministic winner among the candidate sets
     *
     * Explicit comparator functions (rather than Collection::sortBy's
     * multi-criteria array form) so nullable `score` and the mixed
     * string/numeric tie-break keys can never be compared against each
     * other by accident — every candidate set's ordering is total and
     * reproducible on every rebuild.
     */
    private function pickWinner($admin, $vendor, $marketer)
    {
        if ($admin->isNotEmpty()) {
            return $admin->sort(fn ($a, $b) => $this->compareAdminOrMarketer($a, $b))->first();
        }

        if ($vendor->isNotEmpty()) {
            return $vendor->sort(function ($a, $b) {
                $aFbn = $a->fulfillment_model === 'fbn' ? 0 : 1;
                $bFbn = $b->fulfillment_model === 'fbn' ? 0 : 1;
                if ($aFbn !== $bFbn) {
                    return $aFbn <=> $bFbn;
                }

                return $this->compareAdminOrMarketer($a, $b);
            })->first();
        }

        return $marketer->sort(fn ($a, $b) => ((int) $a->price <=> (int) $b->price) ?: strcmp($a->listing_id, $b->listing_id))->first();
    }

    /**
     * price ASC, then score DESC (nulls last), then id ASC.
     */
    private function compareAdminOrMarketer($a, $b): int
    {
        $priceCmp = (int) $a->price <=> (int) $b->price;
        if ($priceCmp !== 0) {
            return $priceCmp;
        }

        $aScore = $a->score;
        $bScore = $b->score;
        if ($aScore === null && $bScore !== null) {
            return 1;
        }
        if ($aScore !== null && $bScore === null) {
            return -1;
        }
        if ($aScore !== null && $bScore !== null) {
            $scoreCmp = (float) $bScore <=> (float) $aScore; // DESC
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
        }

        return strcmp($a->listing_id, $b->listing_id);
    }

    private function adminCandidates(string $countryId, ?array $productIds = null)
    {
        $q = DB::table('admin_listings as al')
            ->join('product_variants as pv', 'pv.id', '=', 'al.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('al.country_id', $countryId)
            ->where('al.status', 'active')
            ->whereNull('al.deleted_at')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->select([
                'al.id as listing_id', 'pv.id as variant_id', 'p.id as product_id',
                'p.category_id', 'p.brand_id', 'p.total_sold as product_total_sold',
                'al.price', 'al.compare_at_price', 'al.rating_avg', 'al.rating_count',
                'al.score', 'al.fulfillment_model', 'al.primary_shipping_method_id',
                DB::raw("'admin' as listing_type"),
            ]);

        if ($productIds !== null) {
            $q->whereIn('p.id', $productIds);
        }

        return collect($q->get());
    }

    private function vendorCandidates(string $countryId, ?array $productIds = null)
    {
        $q = DB::table('vendor_listings as vl')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('vendors as v', 'v.id', '=', 'vl.vendor_id')
            ->where('vl.country_id', $countryId)
            ->where('vl.status', 'active')
            ->whereNull('vl.deleted_at')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->where('v.global_status', 'active')
            ->select([
                'vl.id as listing_id', 'pv.id as variant_id', 'p.id as product_id',
                'p.category_id', 'p.brand_id', 'p.total_sold as product_total_sold',
                'vl.price', 'vl.compare_at_price', 'vl.rating_avg', 'vl.rating_count',
                'vl.score', 'vl.fulfillment_model', 'vl.primary_shipping_method_id',
                DB::raw("'vendor' as listing_type"),
            ]);

        if ($productIds !== null) {
            $q->whereIn('p.id', $productIds);
        }

        return collect($q->get());
    }

    private function marketerCandidates(string $countryId, ?array $productIds = null)
    {
        $q = DB::table('marketer_listings as ml')
            ->join('product_variants as pv', 'pv.id', '=', 'ml.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('marketers as mk', 'mk.id', '=', 'ml.marketer_id')
            ->where('ml.country_id', $countryId)
            ->where('ml.status', 'active')
            ->where('ml.listing_category', 'product')
            ->whereNull('ml.deleted_at')
            ->where('mk.global_status', 'active')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->select([
                'ml.id as listing_id', 'pv.id as variant_id', 'p.id as product_id',
                'p.category_id', 'p.brand_id', 'p.total_sold as product_total_sold',
                'ml.price', 'ml.compare_at_price', 'ml.rating_avg', 'ml.rating_count',
                'ml.score', DB::raw('NULL as fulfillment_model'), DB::raw('NULL as primary_shipping_method_id'),
                DB::raw("'marketer' as listing_type"),
            ]);

        if ($productIds !== null) {
            $q->whereIn('p.id', $productIds);
        }

        return collect($q->get());
    }

    /**
     * @return array<string,int> keyed by "{listing_type}:{listing_id}" -> quantity_available
     */
    private function stockByListing(): array
    {
        $vendor = DB::table('warehouse_inventories')
            ->whereNotNull('vendor_listing_id')
            ->groupBy('vendor_listing_id')
            ->selectRaw('vendor_listing_id, SUM(quantity_available) as stock')
            ->pluck('stock', 'vendor_listing_id');

        $admin = DB::table('warehouse_inventories')
            ->whereNotNull('admin_listing_id')
            ->groupBy('admin_listing_id')
            ->selectRaw('admin_listing_id, SUM(quantity_available) as stock')
            ->pluck('stock', 'admin_listing_id');

        $map = [];
        foreach ($vendor as $id => $qty) {
            $map['vendor:' . $id] = (int) $qty;
        }
        foreach ($admin as $id => $qty) {
            $map['admin:' . $id] = (int) $qty;
        }

        return $map;
    }

    /** @return array<string,string> category_id -> shipping_method_id */
    private function categoryDefaultShippingMethods(): array
    {
        return DB::table('category_shipping_methods')
            ->where('is_default', true)
            ->pluck('shipping_method_id', 'category_id')
            ->all();
    }

    /** @return array<string,bool> shipping_method_id -> is_express_type */
    private function shippingExpressFlags(): array
    {
        return DB::table('shipping_methods')
            ->pluck('is_express_type', 'id')
            ->map(fn ($v) => (bool) $v)
            ->all();
    }
}
