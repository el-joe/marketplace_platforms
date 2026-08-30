<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\ShippingMethod;
use App\Models\ShippingWeightSlab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShippingWeightSlabController extends Controller
{
    public function index()
    {
        $methods = ShippingMethod::orderBy('name')->get();
        $countries = Country::where('is_active', 1)->orderBy('name_en')->get();

        return view('admin.shipping.weight-slabs', compact('methods', 'countries'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = ShippingWeightSlab::query()
            ->with(['shippingMethod', 'country']);

        if ($request->filled('shipping_method_id')) {
            $query->where('shipping_method_id', $request->shipping_method_id);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $draw = (int) $request->draw;
        $start = (int) $request->start;
        $length = (int) ($request->length ?? 25);

        $query->orderBy('shipping_method_id')
            ->orderBy('country_id')
            ->orderBy('min_weight_grams');

        $total = $query->count();
        $filtered = $total;

        $slabs = $query->skip($start)->take($length)->get();

        $data = $slabs->map(function (ShippingWeightSlab $slab) {
            return $this->formatSlab($slab);
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'country_id' => ['required', 'exists:countries,id'],
            'min_weight_grams' => ['required', 'integer', 'min:0'],
            'max_weight_grams' => ['nullable', 'integer', 'gt:min_weight_grams'],
            'extra_fee' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $this->assertNoOverlap($data);

        $slab = ShippingWeightSlab::create($data);

        return response()->json(['success' => true, 'message' => 'Weight slab created.', 'data' => $this->formatSlab($slab)], 201);
    }

    public function update(Request $request, ShippingWeightSlab $slab): JsonResponse
    {
        $data = $request->validate([
            'shipping_method_id' => ['sometimes', 'required', 'exists:shipping_methods,id'],
            'country_id' => ['sometimes', 'required', 'exists:countries,id'],
            'min_weight_grams' => ['sometimes', 'required', 'integer', 'min:0'],
            'max_weight_grams' => ['nullable', 'integer', 'gt:min_weight_grams'],
            'extra_fee' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $merged = array_merge([
            'shipping_method_id' => $slab->shipping_method_id,
            'country_id' => $slab->country_id,
            'min_weight_grams' => $slab->min_weight_grams,
            'max_weight_grams' => $slab->max_weight_grams,
        ], array_intersect_key($data, array_flip(['shipping_method_id', 'country_id', 'min_weight_grams', 'max_weight_grams'])));

        if (!array_key_exists('max_weight_grams', $data)) {
            $merged['max_weight_grams'] = $slab->max_weight_grams;
        }

        $this->assertNoOverlap($merged, $slab->id);

        $slab->update($data);

        return response()->json(['success' => true, 'message' => 'Weight slab updated.', 'data' => $this->formatSlab($slab->fresh(['shippingMethod', 'country']))]);
    }

    public function destroy(ShippingWeightSlab $slab): JsonResponse
    {
        $slab->delete();

        return response()->json(['success' => true, 'message' => 'Weight slab deleted.']);
    }

    protected function assertNoOverlap(array $data, ?string $excludeId = null): void
    {
        $min = $data['min_weight_grams'];
        $max = $data['max_weight_grams'] ?? null;

        $query = ShippingWeightSlab::query()
            ->where('shipping_method_id', $data['shipping_method_id'])
            ->where('country_id', $data['country_id'])
            ->where('min_weight_grams', '<=', $max ?? PHP_INT_MAX)
            ->where(function ($q) use ($min) {
                $q->whereNull('max_weight_grams')->orWhere('max_weight_grams', '>=', $min);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'min_weight_grams' => 'This weight range overlaps with an existing slab for the same method and country.',
            ]);
        }
    }

    protected function formatSlab(ShippingWeightSlab $slab): array
    {
        $range = $slab->max_weight_grams !== null
            ? number_format($slab->min_weight_grams / 1000, 2) . ' kg – ' . number_format($slab->max_weight_grams / 1000, 2) . ' kg'
            : number_format($slab->min_weight_grams / 1000, 2) . ' kg+';

        return [
            'id' => $slab->id,
            'shipping_method_id' => $slab->shipping_method_id,
            'method_name' => optional($slab->shippingMethod)->name ?? '—',
            'country_id' => $slab->country_id,
            'country_name' => optional($slab->country)->name_en ?? '—',
            'min_weight_grams' => $slab->min_weight_grams,
            'max_weight_grams' => $slab->max_weight_grams,
            'weight_range' => $range,
            'extra_fee' => $slab->extra_fee,
            'extra_fee_formatted' => number_format($slab->extra_fee, 2),
            'is_active' => $slab->is_active,
            'active_badge' => $slab->is_active
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-gray">Inactive</span>',
        ];
    }
}
