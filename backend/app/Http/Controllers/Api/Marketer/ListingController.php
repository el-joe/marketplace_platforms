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

    public function promoBadges(MarketerListing $listing): JsonResponse
    {
        abort_unless($listing->marketer_id === $this->marketer()->id && $listing->product_variant_id, 403);

        return response()->json(['success' => true, 'data' => $this->badgePayload($listing)]);
    }

    public function updatePromoBadges(Request $request, MarketerListing $listing): JsonResponse
    {
        abort_unless($listing->marketer_id === $this->marketer()->id && $listing->product_variant_id, 403);

        $data = $request->validate(\App\Services\Shared\PromoBadgeSyncService::rules());

        app(\App\Services\Shared\PromoBadgeSyncService::class)->sync(
            $listing->productVariant->product_id, 'marketer_listing_id', $listing->id, $data['promo_badges'] ?? [],
        );

        return response()->json(['success' => true, 'message' => 'Promo badges saved.', 'data' => $this->badgePayload($listing)]);
    }

    private function badgePayload(MarketerListing $listing): array
    {
        return $listing->promoBadges()->orderBy('sort_order')->get()->map(fn ($b) => [
            'id' => $b->id,
            'label' => ['ar' => $b->label_ar, 'en' => $b->label_en],
            'icon_key' => $b->icon_key,
            'color_hex' => $b->color_hex,
            'text_color_hex' => $b->text_color_hex,
            'sort_order' => $b->sort_order,
            'is_active' => (bool) $b->is_active,
        ])->all();
    }
}
