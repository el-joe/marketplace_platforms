<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SizeGuide;
use Illuminate\Http\JsonResponse;

class SizeGuideController extends Controller
{
    /**
     * Public "size map" page for a vendor size guide. Only guides attached to
     * at least one active made-to-measure listing are visible, so drafts a
     * vendor is still preparing never leak.
     */
    public function show(string $country, string $sizeGuide): JsonResponse
    {
        $guide = SizeGuide::query()
            ->whereKey($sizeGuide)
            ->whereHas('listings', fn ($q) => $q->where('status', 'active')
                ->whereHas('productVariant.product', fn ($p) => $p->where('has_custom_attributes', true)))
            ->with(['rows', 'vendor:id,store_name,store_slug'])
            ->first();

        abort_unless($guide, 404);

        return ApiResponse::success([
            ...$guide->toCustomerArray(),
            'vendor' => $guide->vendor ? [
                'id' => $guide->vendor->id,
                'store_name' => $guide->vendor->store_name,
                'store_slug' => $guide->vendor->store_slug,
            ] : null,
        ]);
    }
}
