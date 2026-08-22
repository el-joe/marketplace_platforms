<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
