<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketingFeeType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Models\MarketingFeeVerticalSetting;
use App\Services\MarketingFeeReportService;
use App\Services\MarketingFeeService;
use App\Traits\HasExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin "Marketing Fees": fixed fee per country, variable fee per country x category x type, calculator. */
class MarketingFeeController extends Controller
{
    use HasExport;

    public function index(Request $request): View
    {
        $countries = Country::where('is_active', true)->orderBy('name_ar')->get();
        $countrySettings = MarketingFeeCountrySetting::get()->keyBy('country_id');

        $variableQuery = MarketingFeeCategorySetting::with(['country', 'category'])->latest('updated_at');
        if ($request->filled('country_id')) {
            $variableQuery->where('country_id', $request->query('country_id'));
        }
        $variableSettings = $variableQuery->get();

        // Classified and travel campaign fees: one variable fee per country and vertical.
        $verticalSettings = MarketingFeeVerticalSetting::get()->keyBy(fn ($row) => $row->country_id.'|'.$row->vertical);
        $verticals = MarketingFeeVerticalSetting::VERTICALS;

        $types = MarketingFeeType::cases();
        $tab = in_array($request->query('tab'), ['fixed', 'variable', 'verticals', 'report'], true) ? $request->query('tab') : 'fixed';

        $report = $this->reportData($request);
        $currencies = Country::where('is_active', true)->whereNotNull('currency_code')->distinct()->orderBy('currency_code')->pluck('currency_code');

        return view('admin.marketing_fees.index', compact('countries', 'countrySettings', 'variableSettings', 'verticalSettings', 'verticals', 'types', 'tab', 'report', 'currencies'));
    }

    /** Report tab CSV: the per-type campaign table, then the affiliate accrual table. */
    public function exportReport(Request $request): StreamedResponse
    {
        $data = $this->reportData($request);
        $rows = collect();

        $m = fn ($v) => number_format((float) $v, 2, '.', '');

        foreach ($data['campaigns'] as $r) {
            $rows->push([
                __('marketing_fees.reports.section_influencer'), $r['country_name'], $r['currency'], $r['type_label'],
                (int) $r['campaigns'], (int) $r['influencers'], $m($r['subtotal']), $m($r['fixed_fee']), $m($r['variable_fee']), $m($r['marketing_fees']),
                $m($r['grand_total']), $m($r['collected']), $m($r['pending']), '', '', '', '', '',
            ]);
        }
        foreach ($data['affiliate'] as $r) {
            $rows->push([
                __('marketing_fees.reports.section_affiliate'), $r['country_name'], $r['currency'], $r['type_label'],
                '', '', '', '', '', '', '', '', '',
                $m($r['accrued']), $m($r['refunded']), $m($r['settled']), $m($r['pending']), $m($r['settlement_refunds']),
            ]);
        }

        $headers = [
            'Section', 'Country', 'Currency', 'Type',
            'Campaigns', 'Influencers', 'Subtotal', 'Fixed fee', 'Variable fee', 'Marketing fees',
            'Grand total', 'Collected', 'Pending',
            'Affiliate accrued', 'Affiliate refunded (reversed)', 'Affiliate settled', 'Affiliate pending settlement', 'Refunded after settlement',
        ];

        return $this->exportCsv('marketing-fees-report-'.$data['from']->toDateString().'-to-'.$data['to']->toDateString(), $headers, $rows);
    }

    /** @return array{from: Carbon, to: Carbon, campaigns: array, affiliate: array} */
    private function reportData(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::enum(MarketingFeeType::class)],
            'country_id' => ['nullable', 'uuid'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : now();
        $filters = array_filter($request->only(['country_id', 'currency', 'type']));

        $service = app(MarketingFeeReportService::class);

        return [
            'from' => $from,
            'to' => $to,
            'campaigns' => $service->campaignTypeReport($from, $to, $filters),
            'affiliate' => $service->affiliateAccrualReport($from, $to, $filters),
        ];
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

    /** Variable campaign fee of the classified / travel verticals for one country (fixed amount + % of the budget). */
    public function updateVertical(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'vertical' => ['required', Rule::in(array_map(fn ($v) => $v->value, MarketingFeeVerticalSetting::VERTICALS))],
            'fixed_amount' => $this->amountRules(),
            'percentage' => $this->percentRules(),
        ]);

        MarketingFeeVerticalSetting::updateOrCreate(
            ['country_id' => $validated['country_id'], 'vertical' => $validated['vertical']],
            [
                'fixed_amount' => $validated['fixed_amount'],
                'percentage' => $validated['percentage'],
                'updated_by_admin_id' => auth('admin')->id(),
            ]
        );

        return redirect()->route('admin.marketing-fees.index', ['tab' => 'verticals'])
            ->with('success', __('marketing_fees.vertical_saved'));
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
            'subtotal' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'fulfillment_type' => ['required', Rule::enum(MarketingFeeType::class)],
        ]);

        $subtotal = round((float) $validated['subtotal'], 2);

        $fee = $service->calculate(
            $subtotal,
            $validated['country_id'],
            $validated['category_id'] ?? null,
            MarketingFeeType::from($validated['fulfillment_type']),
        );

        return response()->json([
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
