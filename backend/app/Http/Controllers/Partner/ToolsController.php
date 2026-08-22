<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\ShippingWeightSlab;
use App\Models\Vendor;
use App\Services\ShippingCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ToolsController extends Controller
{
    public function __construct(
        private readonly ShippingCalculationService $shippingCalculator,
    ) {
    }

    private function vendor(): Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    public function weightCalculator(): View
    {
        $vendor = $this->vendor();

        $methods = ShippingMethod::whereIn('id', function ($query) use ($vendor) {
                $query->select('shipping_method_id')
                    ->from('country_shipping_settings')
                    ->where('country_id', $vendor->country_id)
                    ->where('is_active', true);
            })
            ->where('is_active', true)
            ->orderBy('display_priority')
            ->get();

        $slabs = ShippingWeightSlab::where('country_id', $vendor->country_id)
            ->where('is_active', true)
            ->with('shippingMethod')
            ->orderBy('shipping_method_id')
            ->orderBy('min_weight_grams')
            ->get()
            ->groupBy('shipping_method_id');

        return view('partner.tools.weight-calculator', [
            'methods' => $methods,
            'slabs' => $slabs,
            'currency' => $vendor->country?->currency_code,
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $vendor = $this->vendor();

        $validated = $request->validate([
            'length_cm' => ['required', 'numeric', 'min:0.01'],
            'width_cm' => ['required', 'numeric', 'min:0.01'],
            'height_cm' => ['required', 'numeric', 'min:0.01'],
            'actual_weight_kg' => ['required', 'numeric', 'min:0.01'],
            'shipping_method_id' => ['required', 'uuid', 'exists:shipping_methods,id'],
        ]);

        $actualWeightGrams = (int) round($validated['actual_weight_kg'] * 1000);

        $effectiveWeightGrams = $this->shippingCalculator->resolveEffectiveWeightGrams(
            (int) round($validated['length_cm']),
            (int) round($validated['width_cm']),
            (int) round($validated['height_cm']),
            $actualWeightGrams,
        );

        $volumetricWeightGrams = (int) round((
            ($validated['length_cm'] * $validated['width_cm'] * $validated['height_cm']) / 5000
        ) * 1000);

        $slabFeeCents = $this->shippingCalculator->getWeightSlabFee(
            $validated['shipping_method_id'],
            $vendor->country_id,
            $effectiveWeightGrams,
        );

        $slab = ShippingWeightSlab::where('shipping_method_id', $validated['shipping_method_id'])
            ->where('country_id', $vendor->country_id)
            ->where('is_active', true)
            ->where('min_weight_grams', '<=', $effectiveWeightGrams)
            ->where(function ($query) use ($effectiveWeightGrams) {
                $query->whereNull('max_weight_grams')
                    ->orWhere('max_weight_grams', '>=', $effectiveWeightGrams);
            })
            ->first();

        $slabLabel = $slab
            ? $this->formatSlabLabel($slab->min_weight_grams, $slab->max_weight_grams)
            : null;

        return response()->json([
            'volumetric_weight_kg' => round($volumetricWeightGrams / 1000, 2),
            'actual_weight_kg' => round($actualWeightGrams / 1000, 2),
            'effective_weight_kg' => round($effectiveWeightGrams / 1000, 2),
            'is_volumetric_heavier' => $volumetricWeightGrams > $actualWeightGrams,
            'slab_extra_fee' => $slabFeeCents,
            'currency' => $vendor->country?->currency_code,
            'slab_label' => $slabLabel,
        ]);
    }

    private function formatSlabLabel(int $minGrams, ?int $maxGrams): string
    {
        $min = rtrim(rtrim(number_format($minGrams / 1000, 2), '0'), '.');

        if ($maxGrams === null) {
            return "{$min} kg+";
        }

        $max = rtrim(rtrim(number_format($maxGrams / 1000, 2), '0'), '.');

        return "{$min} kg – {$max} kg";
    }
}
