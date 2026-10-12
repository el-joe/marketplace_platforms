<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromotionCategory;
use App\Http\Controllers\Controller;
use App\Models\AdminListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-curated products of promotion categories 3 (intermediaries) and 4 (Nawy products):
 * list what is in a category, convert an existing platform listing into it, move it between 3/4, or take it out.
 * New products are created on the normal admin listing form (it carries the same category option).
 */
class PromotionCategoryProductController extends Controller
{
    public function index(Request $request): View
    {
        $category = $this->category($request->input('category', PromotionCategory::AdminIntermediary->value));
        $with = ['productVariant' => fn ($q) => $q->withTrashed(), 'productVariant.product' => fn ($q) => $q->withTrashed(), 'country'];

        $assigned = AdminListing::query()->with($with)
            ->where('promotion_category', $category->value)
            ->latest()->paginate(25, ['*'], 'page')->withQueryString();

        $candidates = collect();
        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $candidates = AdminListing::query()->with($with)
                ->where(fn ($q) => $q->whereNull('promotion_category')->orWhere('promotion_category', '!=', $category->value))
                ->whereHas('productVariant.product', fn ($q) => $q->where('name_en', 'like', "%{$term}%")->orWhere('name_ar', 'like', "%{$term}%"))
                ->limit(20)->get();
        }

        return view('admin.promotion_products.index', [
            'category' => $category,
            'categories' => PromotionCategory::listingCategories(),
            'assigned' => $assigned,
            'candidates' => $candidates,
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admin_listing_id' => ['required', 'uuid', 'exists:admin_listings,id'],
            'promotion_category' => ['required', Rule::in($this->allowedValues())],
        ]);

        AdminListing::whereKey($data['admin_listing_id'])->update(['promotion_category' => $data['promotion_category']]);

        return redirect()->route('admin.promotion-products.index', ['category' => $data['promotion_category']])
            ->with('success', __('admin.promotion_products.assigned'));
    }

    public function remove(AdminListing $adminListing): RedirectResponse
    {
        $category = $adminListing->promotion_category?->value ?? PromotionCategory::AdminIntermediary->value;
        $adminListing->update(['promotion_category' => null]);

        return redirect()->route('admin.promotion-products.index', ['category' => $category])
            ->with('success', __('admin.promotion_products.removed'));
    }

    private function allowedValues(): array
    {
        return array_map(fn ($c) => $c->value, PromotionCategory::listingCategories());
    }

    private function category(mixed $value): PromotionCategory
    {
        $category = PromotionCategory::tryFrom((int) $value);

        return $category && in_array($category->value, $this->allowedValues(), true) ? $category : PromotionCategory::AdminIntermediary;
    }
}
