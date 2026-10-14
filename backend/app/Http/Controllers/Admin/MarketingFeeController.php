<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketingFeeType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Services\MarketingFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin "Marketing Fees": fixed fee per country, variable fee per country x category x type, calculator. */
class MarketingFeeController extends Controller
{
    public function index(Request $request): View
    {
        $countries = Country::where('is_active', true)->orderBy('name_ar')->get();
        $countrySettings = MarketingFeeCountrySetting::get()->keyBy('country_id');

        $variableQuery = MarketingFeeCategorySetting::with(['country', 'category'])->latest('updated_at');
        if ($request->filled('country_id')) {
            $variableQuery->where('country_id', $request->query('country_id'));
        }
        $variableSettings = $variableQuery->get();

        $types = MarketingFeeType::cases();
        $tab = in_array($request->query('tab'), ['fixed', 'variable', 'report'], true) ? $request->query('tab') : 'fixed';

        return view('admin.marketing_fees.index', compact('countries', 'countrySettings', 'variableSettings', 'types', 'tab'));
    }

    public function updateFixed(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'fixed_amount' => $this->amountRules(),
            'fixed_percentage' => $this->percentRules(),
        ]);

        MarketingFeeCountrySetting::updateOrCreate(
            ['country_id' => $validated['country_id']],
            [
                'fixed_amount' => $validated['fixed_amount'],
                'fixed_percentage' => $validated['fixed_percentage'],
                'updated_by_admin_id' => auth('admin')->id(),
            ]
        );

        return redirect()->route('admin.marketing-fees.index', ['tab' => 'fixed'])
            ->with('success', __('marketing_fees.fixed_saved'));
    }

    public function updateVariable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'fulfillment_type' => ['nullable', Rule::enum(MarketingFeeType::class)],
            'all_types' => ['nullable', 'boolean'],
            'fixed_amount' => $this->amountRules(),
            'percentage' => $this->percentRules(),
        ]);

        if (empty($validated['all_types']) && empty($validated['fulfillment_type'])) {
            return back()->withInput()->withErrors(['fulfillment_type' => __('marketing_fees.type_required')]);
        }

        $types = ! empty($validated['all_types'])
            ? MarketingFeeType::cases()
            : [MarketingFeeType::from($validated['fulfillment_type'])];

        foreach ($types as $type) {
            MarketingFeeCategorySetting::updateOrCreate(
                [
                    'country_id' => $validated['country_id'],
                    'category_id' => $validated['category_id'],
                    'fulfillment_type' => $type->value,
                ],
                [
                    'fixed_amount' => $validated['fixed_amount'],
                    'percentage' => $validated['percentage'],
                    'updated_by_admin_id' => auth('admin')->id(),
                ]
            );
        }

        return redirect()->route('admin.marketing-fees.index', ['tab' => 'variable'])
            ->with('success', __('marketing_fees.variable_saved'));
    }

    public function destroyVariable(MarketingFeeCategorySetting $setting): RedirectResponse
    {
        $setting->delete();

        return redirect()->route('admin.marketing-fees.index', ['tab' => 'variable'])
            ->with('success', __('marketing_fees.variable_deleted'));
    }

    /** Category search for the select (id + breadcrumb path label), suitable for large trees. */
    public function searchCategories(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $rows = Category::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name_en', 'like', "%{$q}%")->orWhere('name_ar', 'like', "%{$q}%")))
            ->orderBy('name_en')
            ->limit(30)
            ->get();

        return response()->json([
            'results' => $rows->map(fn (Category $c) => ['id' => $c->id, 'text' => $this->categoryPath($c)])->values(),
        ]);
    }

    /** Live calculator: same service the flows use, so the page can verify the formula. */
    public function calculate(Request $request, MarketingFeeService $service): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['influencer', 'affiliate'])],
            'subtotal' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'fulfillment_type' => ['required', Rule::enum(MarketingFeeType::class)],
            'first_order' => ['nullable', 'boolean'],
        ]);

        $subtotal = round((float) $validated['subtotal'], 2);
        // Affiliate accruals after the first order only add the percentage parts (flat amounts are charged once).
        $applyFlat = $validated['mode'] === 'influencer' || $request->boolean('first_order', true);

        $fee = $service->calculate(
            $subtotal,
            $validated['country_id'],
            $validated['category_id'] ?? null,
            MarketingFeeType::from($validated['fulfillment_type']),
            $applyFlat,
        );

        return response()->json([
            'mode' => $validated['mode'],
            'subtotal' => $subtotal,
            'fixed_fee' => $fee['fixed_fee'],
            'variable_fee' => $fee['variable_fee'],
            'marketing_fees' => $fee['total_fee'],
            'grand_total' => round($subtotal + $fee['total_fee'], 2),
            'type_label' => MarketingFeeType::from($validated['fulfillment_type'])->label(),
        ]);
    }

    private function categoryPath(Category $category): string
    {
        $names = [$category->name];
        $seen = [$category->id => true];
        $parent = $category->parent_id ? Category::find($category->parent_id) : null;
        while ($parent && ! isset($seen[$parent->id]) && count($names) < 6) {
            $seen[$parent->id] = true;
            array_unshift($names, $parent->name);
            $parent = $parent->parent_id ? Category::find($parent->parent_id) : null;
        }

        return implode(' › ', $names);
    }

    /** @return array<int, string> */
    private function amountRules(): array
    {
        return ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'];
    }

    /** @return array<int, string> */
    private function percentRules(): array
    {
        return ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,4'];
    }
}
