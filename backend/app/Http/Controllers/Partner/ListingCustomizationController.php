<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\VendorListing;
use App\Models\VendorListingAddonGroup;
use App\Models\VendorListingAddonOption;
use App\Models\VendorListingCustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListingCustomizationController extends Controller
{
    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    private function findOwnedListing(string $listingId): VendorListing
    {
        $listing = VendorListing::where('id', $listingId)
            ->where('vendor_id', $this->vendorId())
            ->firstOrFail();

        return $listing;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Settings (order notes flag + size guide image)
    // ─────────────────────────────────────────────────────────────────────────

    public function updateSettings(Request $request, string $listing): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);

        $validated = $request->validate([
            'has_order_notes' => ['nullable', 'boolean'],
        ]);

        $listing->update([
            'has_order_notes' => (bool) ($validated['has_order_notes'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully.',
        ]);
    }

    public function uploadSizeGuide(Request $request, string $listing): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('file')->store('listing-size-guides/'.$listing->vendor_id, 'public');

        $listing->update([
            'size_guide_image_url' => Storage::disk('public')->url($path),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully.',
            'data' => ['size_guide_image_url' => $listing->size_guide_image_url],
        ]);
    }

    public function deleteSizeGuide(string $listing): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);
        $listing->update(['size_guide_image_url' => null]);

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Custom fields (measurements etc.)
    // ─────────────────────────────────────────────────────────────────────────

    public function storeCustomField(Request $request, string $listing): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);

        $validated = $request->validate([
            'label_en' => ['required', 'string', 'max:255'],
            'label_ar' => ['nullable', 'string', 'max:255'],
            'field_type' => ['required', 'in:text,number,textarea,date'],
            'placeholder_en' => ['nullable', 'string', 'max:255'],
            'placeholder_ar' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $field = VendorListingCustomField::create([
            'id' => (string) Str::uuid(),
            'vendor_listing_id' => $listing->id,
            'label_en' => $validated['label_en'],
            'label_ar' => $validated['label_ar'] ?? null,
            'field_type' => $validated['field_type'],
            'placeholder_en' => $validated['placeholder_en'] ?? null,
            'placeholder_ar' => $validated['placeholder_ar'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'is_required' => $validated['is_required'] ?? true,
            'position' => $validated['position'] ?? ($listing->customFields()->max('position') + 1),
        ]);

        return response()->json(['success' => true, 'data' => $field]);
    }

    public function updateCustomField(Request $request, string $listing, string $field): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);
        $customField = $listing->customFields()->findOrFail($field);

        $validated = $request->validate([
            'label_en' => ['required', 'string', 'max:255'],
            'label_ar' => ['nullable', 'string', 'max:255'],
            'field_type' => ['required', 'in:text,number,textarea,date'],
            'placeholder_en' => ['nullable', 'string', 'max:255'],
            'placeholder_ar' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $customField->update($validated);

        return response()->json(['success' => true, 'data' => $customField]);
    }

    public function destroyCustomField(string $listing, string $field): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);
        $listing->customFields()->findOrFail($field)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Add-on groups
    // ─────────────────────────────────────────────────────────────────────────

    public function storeAddonGroup(Request $request, string $listing): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'selection_type' => ['required', 'in:single,multiple'],
            'is_required' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $group = VendorListingAddonGroup::create([
            'id' => (string) Str::uuid(),
            'vendor_listing_id' => $listing->id,
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'] ?? null,
            'selection_type' => $validated['selection_type'],
            'is_required' => $validated['is_required'] ?? false,
            'position' => $validated['position'] ?? ($listing->addonGroups()->max('position') + 1),
        ]);

        return response()->json(['success' => true, 'data' => $group]);
    }

    public function updateAddonGroup(Request $request, string $listing, string $group): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);
        $addonGroup = $listing->addonGroups()->findOrFail($group);

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'selection_type' => ['required', 'in:single,multiple'],
            'is_required' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $addonGroup->update($validated);

        return response()->json(['success' => true, 'data' => $addonGroup]);
    }

    public function destroyAddonGroup(string $listing, string $group): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);
        $listing->addonGroups()->findOrFail($group)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Add-on options
    // ─────────────────────────────────────────────────────────────────────────

    private function findOwnedAddonGroup(VendorListing $listing, string $groupId): VendorListingAddonGroup
    {
        return $listing->addonGroups()->findOrFail($groupId);
    }

    public function storeAddonOption(Request $request, string $listing, string $group): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);
        $addonGroup = $this->findOwnedAddonGroup($listing, $group);

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'extra_price' => ['required', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $option = VendorListingAddonOption::create([
            'id' => (string) Str::uuid(),
            'addon_group_id' => $addonGroup->id,
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'] ?? null,
            'extra_price' => $validated['extra_price'],
            'is_default' => $validated['is_default'] ?? false,
            'position' => $validated['position'] ?? ($addonGroup->options()->max('position') + 1),
        ]);

        return response()->json(['success' => true, 'data' => $option]);
    }

    public function updateAddonOption(Request $request, string $listing, string $group, string $option): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);
        $addonGroup = $this->findOwnedAddonGroup($listing, $group);
        $addonOption = $addonGroup->options()->findOrFail($option);

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'extra_price' => ['required', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $addonOption->update($validated);

        return response()->json(['success' => true, 'data' => $addonOption]);
    }

    public function destroyAddonOption(string $listing, string $group, string $option): JsonResponse
    {
        $listing = $this->findOwnedListing($listing);
        $addonGroup = $this->findOwnedAddonGroup($listing, $group);
        $addonGroup->options()->findOrFail($option)->delete();

        return response()->json(['success' => true]);
    }
}
