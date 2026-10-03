<?php

namespace App\Http\Controllers\Partner\Api;

use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\VendorListingResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryMovement;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Services\ListingCertificationGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $query = VendorListing::where('vendor_id', $vendorId)
            ->with(['productVariant.product.images', 'country', 'primaryShippingMethod'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('productVariant.product', function ($pq) use ($request) {
                $pq->where('name_en', 'like', "%{$request->search}%")
                    ->orWhere('name_ar', 'like', "%{$request->search}%")
                    ->orWhere('model_number', 'like', "%{$request->search}%");
            }))
            ->latest();

        return ApiResponse::paginated($query->paginate((int) ($request->per_page ?? 20)), VendorListingResource::class);
    }

    public function show(string $id): JsonResponse
    {
        $listing = VendorListing::with([
            'productVariant.product.images',
            'productVariant.variantAttributes',
            'country',
            'primaryShippingMethod',
        ])->findOrFail($id);

        Gate::authorize('view', $listing);

        return ApiResponse::success(new VendorListingResource($listing));
    }

    /** POST /api/partner/v1/listings/{id}/toggle-status */
    public function toggleStatus(string $id): JsonResponse
    {
        $listing = VendorListing::findOrFail($id);
        Gate::authorize('update', $listing);

        if (! in_array($listing->status, [VendorListingStatus::Active, VendorListingStatus::Paused], true)) {
            return response()->json(['success' => false, 'message' => 'Listing status cannot be changed.'], 422);
        }

        if ($listing->status === VendorListingStatus::Paused) {
            $available = WarehouseInventory::where('vendor_listing_id', $listing->id)
                ->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) as total')
                ->value('total');

            if ((int) $available <= 0) {
                return response()->json(['success' => false, 'message' => 'Cannot activate listing with zero available stock.'], 422);
            }

            try {
                ListingCertificationGate::assertCanGoLive($listing);
            } catch (ValidationException) {
                return response()->json(['success' => false, 'message' => 'Listing requires a valid local certification before activation.'], 422);
            }
        }

        $newStatus = $listing->status === VendorListingStatus::Active ? VendorListingStatus::Paused : VendorListingStatus::Active;
        $listing->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'new_status' => $newStatus->value,
            'message' => $newStatus === VendorListingStatus::Active ? 'Listing activated.' : 'Listing paused.',
        ]);
    }

    /** POST /api/partner/v1/listings/{id}/update-price */
    public function updatePrice(Request $request, string $id): JsonResponse
    {
        $listing = VendorListing::findOrFail($id);
        Gate::authorize('update', $listing);

        $request->validate(['price' => ['required', 'integer', 'min:1', 'max:999999999']]);

        $listing->update(['price' => (int) round((float) $request->price)]);

        return response()->json([
            'success' => true,
            'message' => 'Price updated successfully.',
            'price_formatted' => number_format($listing->price, 2),
        ]);
    }

    /** POST /api/partner/v1/listings/{id}/adjust-stock */
    public function adjustStock(Request $request, string $id): JsonResponse
    {
        $listing = VendorListing::findOrFail($id);
        Gate::authorize('update', $listing);

        $request->validate([
            'warehouse_inventory_id' => ['required', 'exists:warehouse_inventories,id'],
            'adjustment' => ['required', 'integer', 'between:-99999,99999', 'not_in:0'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        try {
            $newOnHand = DB::transaction(function () use ($request, $listing) {
                $inventory = WarehouseInventory::lockForUpdate()
                    ->where('id', $request->warehouse_inventory_id)
                    ->where('vendor_listing_id', $listing->id)
                    ->firstOrFail();

                $newOnHand = $inventory->quantity_on_hand + (int) $request->adjustment;

                if ($newOnHand < 0) {
                    throw new \InvalidArgumentException('Stock cannot go below zero.');
                }

                $inventory->update(['quantity_on_hand' => $newOnHand]);

                InventoryMovement::create([
                    'warehouse_inventory_id' => $inventory->id,
                    'movement_type' => 'adjustment',
                    'quantity_delta' => (int) $request->adjustment,
                    'quantity_after' => $newOnHand,
                    'reference_type' => 'adjustment',
                    'reference_id' => $listing->id,
                    'reason' => $request->reason,
                    'created_by_user_id' => Auth::guard('vendor_api')->user()->id,
                ]);

                return $newOnHand;
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('API adjustStock failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'An error occurred during the adjustment.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock adjusted successfully.',
            'new_quantity' => $newOnHand,
        ]);
    }

    /** Read-only (this API is GET-only by design); writes live in the vendor API. */
    public function promoBadges(string $id): JsonResponse
    {
        $listing = VendorListing::findOrFail($id);

        Gate::authorize('view', $listing);

        return ApiResponse::success($listing->promoBadges()->orderBy('sort_order')->get()->map(fn ($b) => [
            'id' => $b->id,
            'label' => ['ar' => $b->label_ar, 'en' => $b->label_en],
            'icon_key' => $b->icon_key,
            'color_hex' => $b->color_hex,
            'text_color_hex' => $b->text_color_hex,
            'sort_order' => $b->sort_order,
            'is_active' => (bool) $b->is_active,
        ])->all());
    }
}
