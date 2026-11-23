<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertShippingDistancePricingRequest;
use App\Models\Category;
use App\Models\ShippingDistancePricing;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Warehouse;
use App\Services\Shipping\DistancePricing\ShippingDistancePricingService;
use Illuminate\Http\JsonResponse;

/**
 * Distance-based delivery rules on the shipping zone page (client item #19).
 */
class ShippingDistancePricingController extends Controller
{
    public function __construct(private readonly ShippingDistancePricingService $service) {}

    public function index(ShippingZone $zone): JsonResponse
    {
        $rules = ShippingDistancePricing::where('shipping_zone_id', $zone->id)
            ->with(['tiers', 'categories:id'])
            ->get()
            ->map(fn (ShippingDistancePricing $pricing) => $this->service->present($pricing))
            ->keyBy('shipping_method_id');

        $categories = Category::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('parent_id')->orWhereIn('parent_id', Category::whereNull('parent_id')->select('id')))
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'name_en', 'name_ar']);

        $roots = $categories->whereNull('parent_id');
        $categoryOptions = $roots->flatMap(fn (Category $root) => collect([['id' => $root->id, 'name' => localized($root), 'depth' => 0]])
            ->merge($categories->where('parent_id', $root->id)->map(fn (Category $child) => ['id' => $child->id, 'name' => localized($child), 'depth' => 1])))
            ->values();

        $sampleLocations = Warehouse::query()
            ->where('country_id', $zone->country_id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude'])
            ->map(fn (Warehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'lat' => (float) $warehouse->latitude,
                'lng' => (float) $warehouse->longitude,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'rules' => $rules,
                'categories' => $categoryOptions,
                'sample_locations' => $sampleLocations,
                'coverage' => $this->service->coordinateCoverage((string) $zone->country_id),
            ],
        ]);
    }

    public function update(UpsertShippingDistancePricingRequest $request, ShippingZone $zone, ShippingMethod $method): JsonResponse
    {
        $pricing = $this->service->save($zone, $method, $request->validated(), auth()->guard('admin')->user());

        $warnings = collect($request->analysis()['warnings'])
            ->map(fn (array $warning) => __('distance_delivery.admin.warnings.'.$warning['key'], $warning['params']))
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'message' => __('distance_delivery.admin.saved'),
            'data' => [
                'rule' => $this->service->present($pricing),
                'warnings' => $warnings,
            ],
        ]);
    }

    public function destroy(ShippingZone $zone, ShippingMethod $method): JsonResponse
    {
        $pricing = ShippingDistancePricing::where('shipping_zone_id', $zone->id)
            ->where('shipping_method_id', $method->id)
            ->firstOrFail();

        $this->service->delete($pricing, auth()->guard('admin')->user());

        return response()->json([
            'success' => true,
            'message' => __('distance_delivery.admin.deleted'),
        ]);
    }
}
