<?php

namespace App\Http\Controllers\Partner\Api;

use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\ProductCustomAttribute;
use App\Models\VendorListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manages product_custom_attributes — a set of order-scoped, customer-entered
 * fields (e.g. "Engraving text", "Custom size") shared across ALL vendors
 * selling a given product. Ownership is intentionally non-exclusive: any
 * vendor with at least one active listing on the product may manage them,
 * because the flag/definitions apply platform-wide for that product, not
 * per-listing.
 */
class ProductCustomAttributeController extends Controller
{
    /**
     * Ensures the authenticated vendor has at least one active listing for
     * a variant of the given product. Not exclusive ownership.
     */
    private function assertVendorSellsProduct(string $vendorId, Product $product): void
    {
        $sells = VendorListing::where('vendor_id', $vendorId)
            ->where('status', VendorListingStatus::Active)
            ->whereHas('productVariant', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        abort_unless($sells, 403, __('common.exceptions.unauthorized'));
    }

    public function index(Product $product): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;
        $this->assertVendorSellsProduct($vendorId, $product);

        $attributes = $product->customAttributes()->get();

        return ApiResponse::success($attributes->map(fn (ProductCustomAttribute $a) => $this->present($a)));
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;
        $this->assertVendorSellsProduct($vendorId, $product);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $attribute = $product->customAttributes()->create([
            'label' => $validated['label'],
            'unit' => $validated['unit'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return ApiResponse::success($this->present($attribute), __('common.messages.created'), 201);
    }

    public function update(Request $request, Product $product, ProductCustomAttribute $customAttribute): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;
        $this->assertVendorSellsProduct($vendorId, $product);

        abort_unless($customAttribute->product_id === $product->id, 404);

        $validated = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $customAttribute->update($validated);

        return ApiResponse::success($this->present($customAttribute), __('common.messages.updated'));
    }

    public function destroy(Product $product, ProductCustomAttribute $customAttribute): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;
        $this->assertVendorSellsProduct($vendorId, $product);

        abort_unless($customAttribute->product_id === $product->id, 404);

        $customAttribute->delete();

        return ApiResponse::success(null, __('common.messages.deleted'));
    }

    /**
     * Flips products.has_custom_attributes. This is a SHARED flag: toggling it
     * affects how the product is displayed/checked-out for every vendor
     * selling it, not just the caller.
     */
    public function toggle(Product $product): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;
        $this->assertVendorSellsProduct($vendorId, $product);

        $product->update(['has_custom_attributes' => ! $product->has_custom_attributes]);

        return ApiResponse::success([
            'product_id' => $product->id,
            'has_custom_attributes' => (bool) $product->has_custom_attributes,
        ]);
    }

    private function present(ProductCustomAttribute $attribute): array
    {
        return [
            'id' => $attribute->id,
            'product_id' => $attribute->product_id,
            'label' => $attribute->label,
            'unit' => $attribute->unit,
            'is_required' => (bool) $attribute->is_required,
            'sort_order' => $attribute->sort_order,
        ];
    }
}
