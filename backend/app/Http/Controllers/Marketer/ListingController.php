<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\MarketerListing;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ListingController extends Controller
{
    private function marketer(): \App\Models\Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

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
                'invitation.campaign.vendor:id,store_name',
                'country:id,name_ar,name_en,currency_code',
            ])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return view('marketer.listings.index', compact('marketer', 'listings'));
    }

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
            'id'           => $v->id,
            'sku'          => $v->sku,
            'variant_name' => $v->variant_name,
            'name_ar'      => $v->name_ar,
            'name_en'      => $v->name_en,
        ]));
    }

    /**
     * Show create form for independent listing.
     */
    public function create(): View
    {
        $marketer  = $this->marketer();
        $countries = Country::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en', 'currency_code']);

        return view('marketer.listings.create', compact('marketer', 'countries'));
    }

    /**
     * Store an independent listing.
     */
    public function store(Request $request): RedirectResponse
    {
        $marketer = $this->marketer();

        $request->validate([
            'product_variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'country_id'         => ['required', 'uuid', 'exists:countries,id'],
            'price'              => ['required', 'integer', 'min:1'],
            'compare_at_price'   => ['nullable', 'integer', 'min:1'],
            'condition'          => ['required', 'in:new,like_new,good,acceptable,refurbished'],
        ]);

        $country = Country::findOrFail($request->country_id);

        $variant = ProductVariant::with('product')->findOrFail($request->product_variant_id);

        if (! $variant->is_active || $variant->trashed() || $variant->is_hidden || $variant->product?->is_hidden) {
            return back()->withErrors(['product_variant_id' => 'هذا المنتج غير متاح للإضافة كقائمة.']);
        }

        $exists = MarketerListing::where('marketer_id', $marketer->id)
            ->where('product_variant_id', $request->product_variant_id)
            ->where('country_id', $request->country_id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['product_variant_id' => 'لديك قائمة لهذا المنتج في هذه الدولة بالفعل.']);
        }

        MarketerListing::create([
            'marketer_id'        => $marketer->id,
            'product_variant_id' => $request->product_variant_id,
            'country_id'         => $request->country_id,
            'price'              => (int) $request->price,
            'compare_at_price'   => $request->compare_at_price ? (int) $request->compare_at_price : null,
            'currency'           => $country->currency_code,
            'condition'          => $request->condition,
            'status'             => 'active',
        ]);

        return redirect()->route('marketer.listings.index')
            ->with('success', 'تم إضافة القائمة بنجاح.');
    }

    /**
     * Toggle listing status (active ↔ paused).
     */
    public function toggleStatus(MarketerListing $listing): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $listing->update([
            'status' => $listing->status === 'active' ? 'paused' : 'active',
        ]);

        return back()->with('success', 'تم تحديث حالة القائمة.');
    }

    /**
     * Update listing price.
     */
    public function updatePrice(Request $request, MarketerListing $listing): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($listing->marketer_id === $marketer->id, 403);

        $request->validate([
            'price'            => ['required', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'integer', 'min:1'],
        ]);

        $listing->update([
            'price'            => (int) $request->price,
            'compare_at_price' => $request->compare_at_price ? (int) $request->compare_at_price : null,
        ]);

        return back()->with('success', 'تم تحديث السعر.');
    }

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
