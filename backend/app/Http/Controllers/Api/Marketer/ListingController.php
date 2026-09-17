<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerListing;
use App\Services\Marketer\MarketerListingAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * enhancement.md P-16 task 2 (API parity): mobile/partner-app equivalents
 * of Http\Controllers\Marketer\ListingController (web portal). Delegates
 * every rule (price-bound check, ownership check) to the SAME service the
 * web controller uses — no re-implemented logic, per enhancement.md 0.1
 * rule 3.
 *
 * Full CRUD parity (store/searchProducts) is deferred — see enhancement.md
 * P-16 handback notes for what remains.
 */
class ListingController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    public function index(Request $request): JsonResponse
    {
        $listings = MarketerListing::where('marketer_id', $this->marketer()->id)
            ->with('productVariant.product')
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $listings]);
    }

    public function toggleStatus(MarketerListing $listing): JsonResponse
    {
        abort_unless($listing->marketer_id === $this->marketer()->id, 403);

        $listing->update([
            'status'        => $listing->status === 'active' ? 'paused' : 'active',
            'paused_reason' => $listing->status === 'active' ? 'manual' : null,
        ]);

        return response()->json(['success' => true, 'data' => $listing->fresh()]);
    }

    public function updatePrice(Request $request, MarketerListing $listing, MarketerListingAvailabilityService $availability): JsonResponse
    {
        abort_unless($listing->marketer_id === $this->marketer()->id, 403);

        $request->validate([
            'price'            => ['required', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'integer', 'min:1'],
        ]);

        $source = $availability->loadSource($listing);
        if ($source && ! $availability->isPriceInBounds((int) $request->price, (int) $source->getRawOriginal('price'))) {
            [$min, $max] = $availability->priceBounds((int) $source->getRawOriginal('price'));

            return response()->json(['success' => false, 'message' => "Price must be between {$min} and {$max}."], 422);
        }

        $listing->update([
            'price'            => (int) $request->price,
            'compare_at_price' => $request->compare_at_price ? (int) $request->compare_at_price : null,
        ]);

        return response()->json(['success' => true, 'data' => $listing->fresh()]);
    }

    public function destroy(MarketerListing $listing): JsonResponse
    {
        abort_unless($listing->marketer_id === $this->marketer()->id, 403);
        $listing->delete();

        return response()->json(['success' => true]);
    }
}
