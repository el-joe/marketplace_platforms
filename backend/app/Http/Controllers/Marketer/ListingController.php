<?php

namespace App\Http\Controllers\Marketer;

use App\Enums\MarketerListingStatus;
use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\InventoryMovement;
use App\Models\Marketer;
use App\Models\MarketerListing;
use App\Models\OpenMarketListingPrice;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Shared\PromoBadgeSyncService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ListingController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns all active FBN warehouses keyed by country_id.
     *
     * @return Collection<int, Warehouse>
     */
    private function fbnWarehousesByCountry(): Collection
    {
        return Warehouse::where('is_active', true)
            ->where('type', WarehouseType::PlatformFbn)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'country_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * All marketer listings (campaign-linked + independent).
     */
    public function index(Request $request): View
    {
        $marketer = $this->marketer();

        $listings = MarketerListing::where('marketer_id', $marketer->id)
            ->with([
                'productVariant.product.images',
                'productVariant.product.category:id,name_ar,name_en',
                'productVariant.product.brand:id,name_en,name_ar',
                'classifiedListing:id,classified_category_id,title_ar',
                'invitation.campaign.vendor:id,store_name',
                'country:id,name_ar,name_en,currency_code',
                'warehouseInventories',
            ])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return view('marketer.listings.index', compact('marketer', 'listings'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Product Search (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Search for products/variants to add as independent listings.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $variants = ProductVariant::query()
            ->join('products as p', 'p.id', '=', 'product_variants.product_id')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->whereNull('product_variants.deleted_at')
            ->where('product_variants.is_active', true)
            ->where('p.is_hidden', false)
            ->where('product_variants.is_hidden', false)
            ->where(function ($sq) use ($q) {
                $sq->where('p.name_en', 'like', "%{$q}%")
                    ->orWhere('p.name_ar', 'like', "%{$q}%")
                    ->orWhere('product_variants.sku', 'like', "%{$q}%");
            })
            ->select('product_variants.id', 'product_variants.sku', 'product_variants.variant_name', 'p.name_en', 'p.name_ar')
            ->limit(15)
            ->get();

        return response()->json($variants->map(fn ($v) => [
            'id' => $v->id,
            'sku' => $v->sku,
            'variant_name' => $v->variant_name,
            'name_ar' => $v->name_ar,
            'name_en' => $v->name_en,
            'display_name' => trim($v->name_en.' '.$v->variant_name),
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show create form for independent FBN listing.
     */
    public function create(): View
    {
        $marketer = $this->marketer();
        $countries = Country::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en', 'currency_code']);
        $fbnWarehouses = $this->fbnWarehousesByCountry();

        return view('marketer.listings.create', compact('marketer', 'countries', 'fbnWarehouses'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Store an independent FBN listing (always draft — needs admin approval).
     */
    public function store(Request $request): RedirectResponse
    {
        $marketer = $this->marketer();

        $request->validate([
            'product_variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'price' => ['required', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'integer', 'min:1'],
            'condition' => ['required', 'in:new,like_new,good,acceptable,refurbished'],
            'condition_notes' => ['nullable', 'string', 'max:500'],
            'vendor_sku' => ['nullable', 'string', 'max:100'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);

        if ($warehouse->type !== WarehouseType::PlatformFbn) {
            return back()->withErrors(['warehouse_id' => 'يجب اختيار مستودع FBN تابع للمنصة.'])->withInput();
        }

        $variant = ProductVariant::with('product')->findOrFail($request->product_variant_id);

        if (! $variant->is_active || $variant->trashed() || $variant->is_hidden || $variant->product?->is_hidden) {
            return back()->withErrors(['product_variant_id' => 'هذا المنتج غير متاح للإضافة كقائمة.'])->withInput();
        }

        $country = Country::findOrFail($request->country_id);

        MarketerListing::create([
            'marketer_id' => $marketer->id,
            'product_variant_id' => $request->product_variant_id,
            'country_id' => $request->country_id,
            'warehouse_id' => $request->warehouse_id,
            'fulfillment_model' => 'fbn',
            'price' => (int) $request->price,
            'compare_at_price' => $request->compare_at_price ? (int) $request->compare_at_price : null,
            'currency' => $country->currency_code,
            'condition' => $request->condition,
            'condition_notes' => $request->condition_notes,
            'vendor_sku' => $request->vendor_sku,
            'low_stock_threshold' => $request->low_stock_threshold ?? 5,
            'status' => MarketerListingStatus::Draft,
        ]);

        return redirect()->route('marketer.listings.index')
            ->with('success', 'تم إضافة القائمة بنجاح. ستظهر للعملاء بعد مراجعة الإدارة.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Display listing detail page.
     */
    public function show(MarketerListing $listing): View
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $listing->load([
            'productVariant.product.category',
            'productVariant.product.images',
            'country',
            'warehouse',
        ]);

        $warehouseInventory = WarehouseInventory::where('marketer_listing_id', $listing->id)->first();

        $movements = collect();
        if ($warehouseInventory) {
            $movements = InventoryMovement::where('warehouse_inventory_id', $warehouseInventory->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        return view('marketer.listings.show', compact('listing', 'warehouseInventory', 'movements'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show edit form for a listing.
     */
    public function edit(MarketerListing $listing): View
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $listing->load([
            'productVariant.product.images',
            'country',
            'warehouse',
        ]);

        $conditions = [
            'new' => 'جديد',
            'like_new' => 'كالجديد',
            'good' => 'جيد',
            'acceptable' => 'مقبول',
            'refurbished' => 'مُجدَّد',
        ];

        return view('marketer.listings.edit', compact('listing', 'conditions'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Update editable listing fields (price, condition, notes, sku, threshold).
     */
    public function update(Request $request, MarketerListing $listing): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $editableStatuses = [
            MarketerListingStatus::Draft,
            MarketerListingStatus::Rejected,
            MarketerListingStatus::Paused,
        ];

        if (! in_array($listing->status, $editableStatuses, true)) {
            return back()->withErrors(['status' => 'لا يمكن تعديل القائمة في حالتها الحالية. يرجى إيقافها مؤقتاً أولاً.'])->withInput();
        }

        $validated = $request->validate([
            'price' => ['required', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'integer', 'min:1'],
            'condition' => ['required', 'in:new,like_new,good,acceptable,refurbished'],
            'condition_notes' => ['nullable', 'string', 'max:500'],
            'vendor_sku' => ['nullable', 'string', 'max:100'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $listing->update([
            'price' => (int) $validated['price'],
            'compare_at_price' => isset($validated['compare_at_price']) ? (int) $validated['compare_at_price'] : null,
            'condition' => $validated['condition'],
            'condition_notes' => $validated['condition_notes'] ?? null,
            'vendor_sku' => $validated['vendor_sku'] ?? null,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 5,
        ]);

        return redirect()->route('marketer.listings.show', $listing)
            ->with('success', 'تم تحديث القائمة بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Adjust Stock
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Adjust warehouse stock — append-only inventory movement, never update/delete.
     */
    public function adjustStock(Request $request, MarketerListing $listing): JsonResponse
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $request->validate([
            'warehouse_inventory_id' => ['required', 'exists:warehouse_inventories,id'],
            'adjustment' => ['required', 'integer', 'between:-99999,99999', 'not_in:0'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        try {
            $result = DB::transaction(function () use ($request, $listing) {
                $inventory = WarehouseInventory::lockForUpdate()
                    ->where('id', $request->warehouse_inventory_id)
                    ->where('marketer_listing_id', $listing->id)
                    ->firstOrFail();

                $newOnHand = $inventory->quantity_on_hand + (int) $request->adjustment;

                if ($newOnHand < 0) {
                    throw new \InvalidArgumentException('لا يمكن أن يكون المخزون بالسالب.');
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
                    'created_by_user_id' => Auth::guard('marketer')->user()->id,
                ]);

                return $newOnHand;
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('marketer adjustStock failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء التعديل.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل المخزون بنجاح.',
            'new_quantity' => $result,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resubmit
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resubmit a rejected listing for review.
     */
    public function resubmit(MarketerListing $listing): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        if ($listing->status !== MarketerListingStatus::Rejected) {
            return back()->withErrors(['status' => 'لا يمكن إعادة تقديم هذه القائمة.']);
        }

        $listing->update([
            'status' => MarketerListingStatus::PendingReview,
            'rejection_reason' => null,
        ]);

        return redirect()->route('marketer.listings.show', $listing)
            ->with('success', 'تم إعادة تقديم القائمة للمراجعة.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Status
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Toggle listing status (active ↔ paused).
     */
    public function toggleStatus(MarketerListing $listing): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $listing->update([
            'status' => $listing->status === MarketerListingStatus::Active
                ? MarketerListingStatus::Paused
                : MarketerListingStatus::Active,
            'paused_reason' => $listing->status === MarketerListingStatus::Active ? 'manual' : null,
        ]);

        return back()->with('success', 'تم تحديث حالة القائمة.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update Price
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Update listing price (FBN: free pricing; classified: bounded by category rule).
     */
    public function updatePrice(Request $request, MarketerListing $listing): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $request->validate([
            'price' => ['required', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'integer', 'min:1'],
        ]);

        // Open-market (classified) listings are still price-bounded by the
        // category's admin-set OpenMarketListingPrice rule.
        if (($listing->listing_category ?? 'product') === 'classified') {
            $classifiedCategoryId = $listing->classifiedListing?->classified_category_id;

            $listingPrice = $classifiedCategoryId
                ? OpenMarketListingPrice::where('classified_category_id', $classifiedCategoryId)->first()
                : null;

            if (! $listingPrice || ! $listingPrice->allow_marketer_override) {
                return back()->withErrors(['price' => 'لا يمكن تعديل سعر هذا الإعلان — السعر مثبّت من الإدارة.']);
            }

            if (! $listingPrice->isPriceInBounds((int) $request->price)) {
                $bounds = collect([$listingPrice->min_price, $listingPrice->max_price])->filter()->implode(' - ');

                return back()->withErrors(['price' => 'السعر يجب أن يكون ضمن الحدود المسموح بها'.($bounds ? " ({$bounds})" : '').'.']);
            }
        }

        // FBN own listings: no source listing → free pricing.
        $listing->update([
            'price' => (int) $request->price,
            'compare_at_price' => $request->compare_at_price ? (int) $request->compare_at_price : null,
        ]);

        return back()->with('success', 'تم تحديث السعر.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Promo Badges
    // ─────────────────────────────────────────────────────────────────────────

    public function promoBadges(MarketerListing $listing): View
    {
        abort_unless($listing->marketer_id === $this->marketer()->id && $listing->product_variant_id, 403);

        return view('marketer.listings.promo-badges', [
            'listing' => $listing->load(['promoBadges', 'productVariant.product']),
        ]);
    }

    public function updatePromoBadges(Request $request, MarketerListing $listing): RedirectResponse
    {
        abort_unless($listing->marketer_id === $this->marketer()->id && $listing->product_variant_id, 403);

        $data = $request->validate(PromoBadgeSyncService::rules());

        app(PromoBadgeSyncService::class)->sync(
            $listing->productVariant->product_id, 'marketer_listing_id', $listing->id, $data['promo_badges'] ?? [],
        );

        return back()->with('success', __('marketer.promo_badges_saved'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Archive (soft-delete) a listing.
     */
    public function destroy(MarketerListing $listing): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $listing->delete();

        return back()->with('success', 'تم حذف القائمة.');
    }
}
