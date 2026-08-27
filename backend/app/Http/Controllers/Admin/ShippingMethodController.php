<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShippingMethodController extends Controller
{
    public function index()
    {
        $shippingMethods = ShippingMethod::withCount('categoryShippingMethods')
            ->orderBy('display_priority')
            ->orderBy('name')
            ->get();

        return view('admin.shipping-methods.index', compact('shippingMethods'));
    }

    public function create()
    {
        $shippingMethod = new ShippingMethod([
            'badge_color_hex' => '#1a1a2e',
            'badge_text_color_hex' => '#FFFFFF',
            'handling_time_hours' => 24,
            'display_priority' => 0,
            'is_active' => true,
        ]);

        return view('admin.shipping-methods.create', compact('shippingMethod'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        ShippingMethod::create($data);

        return redirect()->route('admin.shipping-methods.index')
            ->with('success', __('admin.shipping_section.shipping_method_created'));
    }

    public function edit(ShippingMethod $shippingMethod)
    {
        return view('admin.shipping-methods.edit', compact('shippingMethod'));
    }

    public function update(Request $request, ShippingMethod $shippingMethod): RedirectResponse
    {
        $data = $this->validateData($request, $shippingMethod);

        // code is immutable after creation
        unset($data['code']);

        $shippingMethod->update($data);

        return redirect()->route('admin.shipping-methods.index')
            ->with('success', __('admin.shipping_section.shipping_method_updated'));
    }

    public function destroy(ShippingMethod $shippingMethod): RedirectResponse
    {
        $shippingMethod->delete();

        return redirect()->route('admin.shipping-methods.index')
            ->with('success', __('admin.shipping_section.shipping_method_deleted'));
    }

    public function uploadBadgeImage(Request $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:1024'],
        ]);

        if ($shippingMethod->badge_image_path) {
            Storage::disk('public')->delete($shippingMethod->badge_image_path);
        }

        $file = $request->file('image');
        $ext  = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $path = $file->storeAs(
            'shipping-methods/' . $shippingMethod->id,
            'badge_' . Str::random(8) . '.' . $ext,
            'public'
        );

        $shippingMethod->update(['badge_image_path' => $path]);

        return response()->json([
            'message'         => 'Badge image uploaded.',
            'badge_image_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function deleteBadgeImage(ShippingMethod $shippingMethod): JsonResponse
    {
        if (!$shippingMethod->badge_image_path) {
            return response()->json(['message' => 'No image to remove.'], 404);
        }

        Storage::disk('public')->delete($shippingMethod->badge_image_path);
        $shippingMethod->update(['badge_image_path' => null]);

        return response()->json(['message' => 'Badge image removed.']);
    }

    private function validateData(Request $request, ?ShippingMethod $shippingMethod = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[a-z_]+$/',
                'unique:shipping_methods,code' . ($shippingMethod ? ',' . $shippingMethod->id : ''),
            ],
            'description' => ['nullable', 'string'],
            'min_delivery_days' => ['nullable', 'integer', 'min:0'],
            'max_delivery_days' => ['nullable', 'integer', 'min:0', 'gte:min_delivery_days'],
            'is_active' => ['boolean'],
            'badge_label_en' => ['nullable', 'string', 'max:50'],
            'badge_label_ar' => ['nullable', 'string', 'max:50'],
            'badge_color_hex' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'badge_text_color_hex' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'delivery_label_en' => ['nullable', 'string', 'max:100'],
            'delivery_label_ar' => ['nullable', 'string', 'max:100'],
            'is_express_type' => ['boolean'],
            'show_estimated_price' => ['boolean'],
            'display_priority' => ['nullable', 'integer', 'min:0'],
            'order_cutoff_time' => ['nullable', 'date_format:H:i'],
            'handling_time_hours' => ['nullable', 'integer', 'min:0', 'max:72'],
        ]);
    }
}
