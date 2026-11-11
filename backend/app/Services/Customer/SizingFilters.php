<?php

namespace App\Services\Customer;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Storefront filters for made-to-measure products (custom attributes on):
 *  - "made_to_measure": products whose custom attributes are switched on.
 *  - "guide_size": sizes listed in the selling vendor's size guide.
 *
 * Both reuse the generic `attributes[code][]` query param and are returned
 * in the same facet shape as catalog attributes, so the storefront filter
 * sidebar renders them with no special-casing.
 */
class SizingFilters
{
    public const SIZE_CODE = 'guide_size';

    public const MADE_TO_MEASURE_CODE = 'made_to_measure';

    public static function handles(string $code): bool
    {
        return in_array($code, [self::SIZE_CODE, self::MADE_TO_MEASURE_CODE], true);
    }

    /**
     * Narrows a query to products/listings matching a sizing filter.
     *
     * @param  mixed  $builder  Eloquent or query builder already joined to `products as p`
     *                          (or exposing the product id via $productIdColumn).
     * @param  list<string>  $values
     * @param  string  $productIdColumn  Column holding the product id (e.g. "p.id", "bb.product_id").
     * @param  string|null  $vendorListingIdColumn  When the row IS a vendor listing, match that
     *                                              listing's own guide; otherwise any active vendor
     *                                              listing of the product in $countryColumn's country.
     * @param  string|null  $countryColumn  Country column used when matching by product.
     */
    public static function apply($builder, string $code, array $values, string $productIdColumn, ?string $vendorListingIdColumn = null, ?string $countryColumn = null): void
    {
        if ($code === self::MADE_TO_MEASURE_CODE) {
            $builder->whereExists(fn (QueryBuilder $sub) => $sub->select(DB::raw(1))
                ->from('products as p_mtm')
                ->whereColumn('p_mtm.id', $productIdColumn)
                ->where('p_mtm.has_custom_attributes', true));

            return;
        }

        $builder->whereExists(function (QueryBuilder $sub) use ($values, $productIdColumn, $vendorListingIdColumn, $countryColumn) {
            $sub->select(DB::raw(1))
                ->from('vendor_listings as vl_sg')
                ->join('size_guide_rows as sgr', 'sgr.size_guide_id', '=', 'vl_sg.size_guide_id')
                ->join('product_variants as pv_sg', 'pv_sg.id', '=', 'vl_sg.product_variant_id')
                ->join('products as p_sg', 'p_sg.id', '=', 'pv_sg.product_id')
                ->where('p_sg.has_custom_attributes', true)
                ->whereIn('sgr.size_label', $values);

            if ($vendorListingIdColumn !== null) {
                $sub->whereColumn('vl_sg.id', $vendorListingIdColumn);

                return;
            }

            $sub->whereColumn('pv_sg.product_id', $productIdColumn)
                ->where('vl_sg.status', 'active')
                ->whereNull('vl_sg.deleted_at');
            if ($countryColumn !== null) {
                $sub->whereColumn('vl_sg.country_id', $countryColumn);
            }
        });
    }

    /**
     * Facets in the same shape as catalog attribute facets. Costs no query
     * unless the (already filtered) set contains made-to-measure products.
     *
     * @param  Collection<int, string>  $madeToMeasureProductIds  Filtered candidates with custom attributes on.
     * @return list<array<string, mixed>>
     */
    public static function facets(Collection $madeToMeasureProductIds, string $countryId): array
    {
        $productIds = $madeToMeasureProductIds->unique()->values();
        $madeToMeasureCount = $productIds->count();

        if ($madeToMeasureCount === 0) {
            return [];
        }

        $facets = [[
            'id' => self::MADE_TO_MEASURE_CODE,
            'code' => self::MADE_TO_MEASURE_CODE,
            'name' => ['en' => __('common.sizing.made_to_measure_filter', [], 'en'), 'ar' => __('common.sizing.made_to_measure_filter', [], 'ar')],
            'type' => 'select',
            'unit' => null,
            'values' => [[
                'id' => self::MADE_TO_MEASURE_CODE,
                'value' => ['en' => __('common.sizing.made_to_measure', [], 'en'), 'ar' => __('common.sizing.made_to_measure', [], 'ar')],
                'color_hex' => null,
                'count' => $madeToMeasureCount,
            ]],
        ]];

        $sizes = DB::table('vendor_listings as vl')
            ->join('size_guide_rows as sgr', 'sgr.size_guide_id', '=', 'vl.size_guide_id')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->whereIn('pv.product_id', $productIds)
            ->where('p.has_custom_attributes', true)
            ->where('vl.country_id', $countryId)
            ->where('vl.status', 'active')
            ->whereNull('vl.deleted_at')
            ->groupBy('sgr.size_label')
            ->selectRaw('sgr.size_label, COUNT(DISTINCT pv.product_id) as cnt')
            ->get()
            ->sortBy(fn ($row) => [is_numeric($row->size_label) ? 0 : 1, is_numeric($row->size_label) ? (float) $row->size_label : $row->size_label])
            ->values();

        if ($sizes->isNotEmpty()) {
            $facets[] = [
                'id' => self::SIZE_CODE,
                'code' => self::SIZE_CODE,
                'name' => ['en' => __('common.sizing.size_filter', [], 'en'), 'ar' => __('common.sizing.size_filter', [], 'ar')],
                'type' => 'select',
                'unit' => null,
                'values' => $sizes->map(fn ($row) => [
                    'id' => self::SIZE_CODE.':'.$row->size_label,
                    'value' => ['en' => $row->size_label, 'ar' => $row->size_label],
                    'color_hex' => null,
                    'count' => (int) $row->cnt,
                ])->all(),
            ];
        }

        return $facets;
    }
}
